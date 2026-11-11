<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCustomer;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinancePayment;
use Modules\Finance\Models\FinancePaymentAllocation;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinancePurchaseBill;
use Modules\Finance\Models\FinancePurchaseBillLine;
use Modules\Finance\Models\FinanceSalesInvoice;
use Modules\Finance\Models\FinanceSalesInvoiceLine;
use Modules\Finance\Models\FinanceScope;
use Modules\Finance\Models\FinanceSupplier;
use Modules\Finance\Models\FinanceTaxRate;

class FinanceReceivablesPayablesService
{
    public function __construct(
        private readonly FinanceScopeService $scopes,
        private readonly FinancePeriodService $periods,
        private readonly FinanceJournalService $journals,
        private readonly FinanceReceiptService $receipts,
    ) {}

    public function postSalesInvoice(Organization $organization, array $data): FinanceSalesInvoice
    {
        return DB::transaction(function () use ($organization, $data): FinanceSalesInvoice {
            $scope = $this->lockScope($organization, $this->scopes->organizationScope($organization));
            $requestKey = trim((string) ($data['request_key'] ?? ''));
            if ($requestKey !== '') {
                $existing = FinanceSalesInvoice::query()->where('organization_id', $organization->id)->where('request_key', $requestKey)->first();
                if ($existing) return $existing->load(['customer', 'lines', 'journal']);
            }
            $customer = FinanceCustomer::query()->where('organization_id', $organization->id)->where('is_active', true)->findOrFail($data['finance_customer_id']);
            $currency = $this->assertCurrency($organization, $data['currency'] ?? $organization->currency);
            $date = (string) $data['invoice_date'];
            $period = $this->periods->forDate($scope, $date);
            if (! $period) throw new \InvalidArgumentException('No open financial period covers the invoice date.');
            $dueDate = (string) ($data['due_date'] ?? $date);
            if ($dueDate < $date) throw new \InvalidArgumentException('The invoice due date cannot be before its invoice date.');
            $lines = $this->prepareLines($organization, $data['lines'] ?? [], 'sales');
            [$subtotal, $taxTotal, $total] = $this->totals($lines);
            $invoice = FinanceSalesInvoice::create([
                'organization_id' => $organization->id, 'finance_scope_id' => $scope->id,
                'finance_customer_id' => $customer->id, 'finance_period_id' => $period->id,
                'invoice_number' => $data['invoice_number'] ?? 'INV-' . strtoupper((string) Str::ulid()),
                'request_key' => $requestKey !== '' ? $requestKey : null,
                'invoice_date' => $date, 'due_date' => $dueDate, 'status' => 'draft',
                'currency' => $currency,
                'subtotal' => $subtotal, 'tax_total' => $taxTotal, 'total' => $total,
                'paid_amount' => '0.0000', 'balance_due' => $total,
                'notes' => $data['notes'] ?? null, 'metadata' => $data['metadata'] ?? [],
            ]);
            foreach ($lines as $line) $invoice->lines()->create($line);

            $ar = $this->accountByCode($organization, $scope, '1200', 'asset');
            $journalLines = [];
            foreach ($lines as $line) {
                $journalLines[] = ['finance_account_id' => $ar->id, 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => $line['line_total'], 'credit' => '0.0000', 'description' => 'Accounts receivable — ' . $line['description']];
                $journalLines[] = ['finance_account_id' => $line['finance_account_id'], 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => '0.0000', 'credit' => $line['line_subtotal'], 'description' => $line['description']];
                if ((float) $line['tax_amount'] > 0) {
                    $rate = FinanceTaxRate::query()->findOrFail($line['finance_tax_rate_id']);
                    $journalLines[] = ['finance_account_id' => $rate->sales_account_id, 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => '0.0000', 'credit' => $line['tax_amount'], 'description' => 'Sales tax: ' . $rate->name];
                }
            }
            $journal = $this->journals->post($organization, $scope, $period, [
                'transaction_date' => $date, 'description' => 'Sales invoice ' . $invoice->invoice_number,
                'source_type' => 'finance_sales_invoice', 'source_id' => $invoice->id,
                'metadata' => ['document_type' => 'sales_invoice', 'document_id' => $invoice->id], 'lines' => $journalLines,
            ]);
            DB::table('finance_sales_invoices')->where('id', $invoice->id)->update(['finance_journal_id' => $journal->id, 'status' => 'posted', 'updated_at' => now()]);
            return $invoice->refresh()->load(['customer', 'lines', 'journal']);
        });
    }

    public function postPurchaseBill(Organization $organization, array $data): FinancePurchaseBill
    {
        return DB::transaction(function () use ($organization, $data): FinancePurchaseBill {
            $scope = $this->lockScope($organization, $this->scopes->organizationScope($organization));
            $requestKey = trim((string) ($data['request_key'] ?? ''));
            if ($requestKey !== '') {
                $existing = FinancePurchaseBill::query()->where('organization_id', $organization->id)->where('request_key', $requestKey)->first();
                if ($existing) return $existing->load(['supplier', 'lines', 'journal']);
            }
            $supplier = FinanceSupplier::query()->where('organization_id', $organization->id)->where('is_active', true)->findOrFail($data['finance_supplier_id']);
            $currency = $this->assertCurrency($organization, $data['currency'] ?? $organization->currency);
            $date = (string) $data['bill_date'];
            $period = $this->periods->forDate($scope, $date);
            if (! $period) throw new \InvalidArgumentException('No open financial period covers the bill date.');
            $dueDate = (string) ($data['due_date'] ?? $date);
            if ($dueDate < $date) throw new \InvalidArgumentException('The bill due date cannot be before its bill date.');
            $lines = $this->prepareLines($organization, $data['lines'] ?? [], 'purchases');
            [$subtotal, $taxTotal, $total] = $this->totals($lines);
            $bill = FinancePurchaseBill::create([
                'organization_id' => $organization->id, 'finance_scope_id' => $scope->id,
                'finance_supplier_id' => $supplier->id, 'finance_period_id' => $period->id,
                'bill_number' => $data['bill_number'] ?? 'BILL-' . strtoupper((string) Str::ulid()),
                'request_key' => $requestKey !== '' ? $requestKey : null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'bill_date' => $date, 'due_date' => $dueDate, 'status' => 'draft',
                'currency' => $currency,
                'subtotal' => $subtotal, 'tax_total' => $taxTotal, 'total' => $total,
                'paid_amount' => '0.0000', 'balance_due' => $total,
                'notes' => $data['notes'] ?? null, 'metadata' => $data['metadata'] ?? [],
            ]);
            foreach ($lines as $line) $bill->lines()->create($line);

            $ap = $this->accountByCode($organization, $scope, '2000', 'liability');
            $journalLines = [];
            foreach ($lines as $line) {
                $journalLines[] = ['finance_account_id' => $line['finance_account_id'], 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => $line['line_subtotal'], 'credit' => '0.0000', 'description' => $line['description']];
                if ((float) $line['tax_amount'] > 0) {
                    $rate = FinanceTaxRate::query()->findOrFail($line['finance_tax_rate_id']);
                    $journalLines[] = ['finance_account_id' => $rate->purchase_account_id, 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => $line['tax_amount'], 'credit' => '0.0000', 'description' => 'Purchase tax: ' . $rate->name];
                }
                $journalLines[] = ['finance_account_id' => $ap->id, 'finance_cost_center_id' => $line['finance_cost_center_id'], 'debit' => '0.0000', 'credit' => $line['line_total'], 'description' => 'Accounts payable — ' . $line['description']];
            }
            $journal = $this->journals->post($organization, $scope, $period, [
                'transaction_date' => $date, 'description' => 'Supplier bill ' . $bill->bill_number,
                'source_type' => 'finance_purchase_bill', 'source_id' => $bill->id,
                'metadata' => ['document_type' => 'purchase_bill', 'document_id' => $bill->id], 'lines' => $journalLines,
            ]);
            DB::table('finance_purchase_bills')->where('id', $bill->id)->update(['finance_journal_id' => $journal->id, 'status' => 'posted', 'updated_at' => now()]);
            return $bill->refresh()->load(['supplier', 'lines', 'journal']);
        });
    }

    /**
     * Record a receipt/payment and allocate it atomically. Direction is customer_receipt or supplier_payment.
     * Each allocation must reference an open document in the same organization and allocations must equal payment amount.
     */
    public function recordPayment(Organization $organization, array $data): FinancePayment
    {
        return DB::transaction(function () use ($organization, $data): FinancePayment {
            $direction = (string) ($data['direction'] ?? '');
            if (! in_array($direction, ['customer_receipt', 'supplier_payment'], true)) throw new \InvalidArgumentException('Choose a valid payment direction.');
            $currency = $this->assertCurrency($organization, $data['currency'] ?? $organization->currency);
            $date = (string) $data['payment_date'];
            $scope = $this->lockScope($organization, $this->scopes->organizationScope($organization));
            $requestKey = trim((string) ($data['request_key'] ?? ''));
            if ($requestKey !== '') {
                $existing = FinancePayment::query()->where('organization_id', $organization->id)->where('request_key', $requestKey)->first();
                if ($existing) return $existing->load(['allocations.invoice', 'allocations.bill', 'journal', 'account', 'receipt']);
            }
            $period = $this->periods->forDate($scope, $date);
            if (! $period) throw new \InvalidArgumentException('No open financial period covers the payment date.');
            $amount = $this->money($this->inputDecimal($data['amount'] ?? 0, 'payment amount'));
            if ((float) $amount <= 0) throw new \InvalidArgumentException('The payment amount must be greater than zero.');
            $allocations = $data['allocations'] ?? [];
            if (! is_array($allocations) || $allocations === []) throw new \InvalidArgumentException('Allocate the payment to at least one invoice or bill.');
            $allocationTotal = 0.0;
            $lockedDocuments = [];
            $allocationTargets = [];
            $partyId = null;
            foreach ($allocations as $allocation) {
                $allocationAmount = $this->money($this->inputDecimal($allocation['amount'] ?? 0, 'allocation amount'));
                if ((float) $allocationAmount <= 0) throw new \InvalidArgumentException('Allocation amounts must be greater than zero.');
                if ($direction === 'customer_receipt') {
                    $targetId = (int) ($allocation['finance_sales_invoice_id'] ?? 0);
                    if (isset($allocationTargets['sales:' . $targetId])) throw new \InvalidArgumentException('A document can only appear once in a payment allocation.');
                    $allocationTargets['sales:' . $targetId] = true;
                    $document = FinanceSalesInvoice::query()->where('organization_id', $organization->id)->with('lines')->lockForUpdate()->findOrFail($targetId);
                    if (! in_array($document->status, ['posted', 'partially_paid'], true) || (float) $allocationAmount > (float) $document->balance_due) throw new \InvalidArgumentException('The invoice allocation exceeds its outstanding balance or the invoice is not payable.');
                    if ($date < $document->invoice_date->toDateString() || $document->currency !== $currency) throw new \InvalidArgumentException('The receipt date or currency is incompatible with the selected invoice.');
                    if ($partyId !== null && $partyId !== (int) $document->finance_customer_id) throw new \InvalidArgumentException('A customer receipt can only be allocated to invoices for the same customer.');
                    $partyId = (int) $document->finance_customer_id;
                    $lockedDocuments[] = ['model' => $document, 'type' => 'sales', 'amount' => $allocationAmount];
                } else {
                    $targetId = (int) ($allocation['finance_purchase_bill_id'] ?? 0);
                    if (isset($allocationTargets['purchase:' . $targetId])) throw new \InvalidArgumentException('A document can only appear once in a payment allocation.');
                    $allocationTargets['purchase:' . $targetId] = true;
                    $document = FinancePurchaseBill::query()->where('organization_id', $organization->id)->with('lines')->lockForUpdate()->findOrFail($targetId);
                    if (! in_array($document->status, ['posted', 'partially_paid'], true) || (float) $allocationAmount > (float) $document->balance_due) throw new \InvalidArgumentException('The bill allocation exceeds its outstanding balance or the bill is not payable.');
                    if ($date < $document->bill_date->toDateString() || $document->currency !== $currency) throw new \InvalidArgumentException('The payment date or currency is incompatible with the selected bill.');
                    if ($partyId !== null && $partyId !== (int) $document->finance_supplier_id) throw new \InvalidArgumentException('A supplier payment can only be allocated to bills for the same supplier.');
                    $partyId = (int) $document->finance_supplier_id;
                    $lockedDocuments[] = ['model' => $document, 'type' => 'purchase', 'amount' => $allocationAmount];
                }
                $allocationTotal += (float) $allocationAmount;
            }
            if (abs($allocationTotal - (float) $amount) > 0.00005) throw new \InvalidArgumentException('Payment allocations must equal the payment amount.');

            $cashAccount = FinanceAccount::query()->where('organization_id', $organization->id)->where('finance_scope_id', $scope->id)->where('is_active', true)->findOrFail($data['finance_account_id'] ?? 0);
            if ($cashAccount->account_type !== FinanceAccountType::ASSET || ! $cashAccount->is_cash_account) throw new \InvalidArgumentException('Select an active cash or bank account.');
            $controlAccount = $this->accountByCode($organization, $scope, $direction === 'customer_receipt' ? '1200' : '2000', $direction === 'customer_receipt' ? 'asset' : 'liability');
            $allocationParts = [];
            foreach ($lockedDocuments as $entry) {
                $groups = $entry['model']->lines->groupBy(fn ($line) => $line->finance_cost_center_id ?? 'none')
                    ->filter(fn ($groupLines) => (float) $groupLines->sum('line_total') > 0);
                $documentTotal = (float) $entry['model']->total;
                $allocatedSoFar = 0.0;
                $groupIndex = 0;
                $groupCount = $groups->count();
                foreach ($groups as $centerKey => $groupLines) {
                    $groupIndex++;
                    $centerTotal = (float) $groupLines->sum('line_total');
                    $partAmount = $groupIndex === $groupCount
                        ? round((float) $entry['amount'] - $allocatedSoFar, 4)
                        : floor(round((float) $entry['amount'] * 10000) * $centerTotal / $documentTotal) / 10000;
                    $allocatedSoFar += $partAmount;
                    if ($partAmount > 0) {
                        $allocationParts[] = [
                            'document' => $entry['model'], 'type' => $entry['type'],
                            'finance_cost_center_id' => $centerKey === 'none' ? null : (int) $centerKey,
                            'amount' => $this->money($partAmount),
                        ];
                    }
                }
            }
            $journalLines = [];
            foreach ($allocationParts as $part) {
                if ($direction === 'customer_receipt') {
                    $journalLines[] = ['finance_account_id' => $cashAccount->id, 'finance_cost_center_id' => $part['finance_cost_center_id'], 'debit' => $part['amount'], 'credit' => '0.0000'];
                    $journalLines[] = ['finance_account_id' => $controlAccount->id, 'finance_cost_center_id' => $part['finance_cost_center_id'], 'debit' => '0.0000', 'credit' => $part['amount']];
                } else {
                    $journalLines[] = ['finance_account_id' => $controlAccount->id, 'finance_cost_center_id' => $part['finance_cost_center_id'], 'debit' => $part['amount'], 'credit' => '0.0000'];
                    $journalLines[] = ['finance_account_id' => $cashAccount->id, 'finance_cost_center_id' => $part['finance_cost_center_id'], 'debit' => '0.0000', 'credit' => $part['amount']];
                }
            }
            $payment = FinancePayment::create([
                'organization_id' => $organization->id, 'finance_scope_id' => $scope->id, 'finance_period_id' => $period->id,
                'finance_journal_id' => null, 'direction' => $direction,
                'payment_number' => $data['payment_number'] ?? strtoupper(($direction === 'customer_receipt' ? 'RCV-' : 'PAY-') . Str::ulid()),
                'request_key' => $requestKey !== '' ? $requestKey : null,
                'payment_date' => $date, 'finance_account_id' => $cashAccount->id,
                'amount' => $amount, 'currency' => $currency,
                'reference' => $data['reference'] ?? null, 'status' => 'draft', 'metadata' => $data['metadata'] ?? [],
            ]);
            $journal = $this->journals->post($organization, $scope, $period, [
                'transaction_date' => $date,
                'description' => $direction === 'customer_receipt' ? 'Customer receipt' : 'Supplier payment',
                'source_type' => 'finance_payment', 'source_id' => $payment->id,
                'metadata' => ['direction' => $direction, 'finance_payment_id' => $payment->id], 'lines' => $journalLines,
            ]);
            DB::table('finance_payments')->where('id', $payment->id)->update([
                'finance_journal_id' => $journal->id, 'status' => 'posted', 'updated_at' => now(),
            ]);
            $payment->refresh();
            foreach ($allocationParts as $part) {
                $document = $part['document'];
                $payment->allocations()->create([
                    'finance_sales_invoice_id' => $part['type'] === 'sales' ? $document->id : null,
                    'finance_purchase_bill_id' => $part['type'] === 'purchase' ? $document->id : null,
                    'finance_cost_center_id' => $part['finance_cost_center_id'],
                    'amount' => $part['amount'],
                ]);
            }
            foreach ($lockedDocuments as $entry) {
                $document = $entry['model'];
                $paid = round((float) $document->paid_amount + (float) $entry['amount'], 4);
                $balance = max(0, round((float) $document->total - $paid, 4));
                DB::table($entry['type'] === 'sales' ? 'finance_sales_invoices' : 'finance_purchase_bills')
                    ->where('id', $document->id)
                    ->update([
                        'paid_amount' => $this->money($paid),
                        'balance_due' => $this->money($balance),
                        'status' => $balance <= 0 ? 'paid' : 'partially_paid',
                        'updated_at' => now(),
                    ]);
            }
            if ($direction === 'customer_receipt') $this->receipts->issue($organization, $scope, $journal, ['metadata' => ['finance_payment_id' => $payment->id]]);
            return $payment->load(['allocations.invoice', 'allocations.bill', 'journal', 'account', 'receipt']);
        });
    }

    public function receivablesAging(Organization $organization, ?string $asOf = null): array
    {
        return $this->aging(FinanceSalesInvoice::query()->where('organization_id', $organization->id)->with('customer'), 'due_date', $asOf);
    }

    public function payablesAging(Organization $organization, ?string $asOf = null): array
    {
        return $this->aging(FinancePurchaseBill::query()->where('organization_id', $organization->id)->with('supplier'), 'due_date', $asOf);
    }

    private function aging($query, string $dateColumn, ?string $asOf): array
    {
        $date = $asOf ?? now()->toDateString();
        $rows = $query->whereIn('status', ['posted', 'partially_paid'])->where('balance_due', '>', 0)->get();
        $buckets = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'over_90' => 0.0];
        $items = [];
        foreach ($rows as $row) {
            $days = max(0, (int) \Illuminate\Support\Carbon::parse($row->{$dateColumn})->diffInDays($date, false));
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : ($days <= 90 ? 'days_61_90' : 'over_90')));
            $buckets[$bucket] += (float) $row->balance_due;
            $items[] = ['document' => $row, 'days_overdue' => $days, 'bucket' => $bucket];
        }
        foreach ($buckets as $key => $value) $buckets[$key] = $this->money($value);
        return ['as_of' => $date, 'buckets' => $buckets, 'total_outstanding' => $this->money(array_sum(array_map('floatval', $buckets))), 'items' => $items];
    }

    private function prepareLines(Organization $organization, array $lines, string $kind): array
    {
        if ($lines === [] || count($lines) > 100) throw new \InvalidArgumentException('A document must contain between 1 and 100 lines.');
        $scope = $this->scopes->organizationScope($organization);
        $prepared = [];
        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $quantity = $this->inputDecimal($line['quantity'] ?? 0, 'quantity');
            $unitPrice = $this->inputDecimal($line['unit_price'] ?? 0, 'unit price');
            if ($description === '' || $quantity <= 0 || $unitPrice < 0) throw new \InvalidArgumentException('Each line requires a description, positive quantity and non-negative unit price.');
            $item = null;
            if (! empty($line['finance_item_id'])) {
                $item = \Modules\Finance\Models\FinanceItem::query()->where('organization_id', $organization->id)->where('is_active', true)->findOrFail($line['finance_item_id']);
            }
            $accountId = $kind === 'sales'
                ? ($line['finance_account_id'] ?? $item?->sales_account_id)
                : ($line['finance_account_id'] ?? null);
            $account = $accountId
                ? FinanceAccount::query()->where('organization_id', $organization->id)->where('finance_scope_id', $scope->id)->where('is_active', true)->findOrFail($accountId)
                : $this->accountByCode($organization, $scope, $kind === 'sales' ? '4000' : '5000', $kind === 'sales' ? 'income' : 'expense');
            if ($kind === 'sales' && $account->account_type !== FinanceAccountType::INCOME) throw new \InvalidArgumentException('Sales invoice lines must post to an income account.');
            if ($kind === 'purchases' && ! in_array($account->account_type, [FinanceAccountType::EXPENSE, FinanceAccountType::ASSET], true)) throw new \InvalidArgumentException('Purchase bill lines must post to an expense or asset account.');
            $costCenterId = $line['finance_cost_center_id'] ?? null;
            if ($costCenterId && ! \Modules\Finance\Models\FinanceCostCenter::query()->where('organization_id', $organization->id)->where('is_active', true)->whereKey($costCenterId)->exists()) throw new \InvalidArgumentException('The selected cost centre is invalid.');
            $taxRate = null;
            if (! empty($line['finance_tax_rate_id'])) {
                $taxRate = FinanceTaxRate::query()->where('organization_id', $organization->id)->where('is_active', true)->findOrFail($line['finance_tax_rate_id']);
                if (! in_array($taxRate->applies_to, [$kind, 'both'], true)) throw new \InvalidArgumentException('The selected tax rate is not applicable to this document.');
                $taxAccountId = $kind === 'sales' ? $taxRate->sales_account_id : $taxRate->purchase_account_id;
                $taxAccount = $taxAccountId ? FinanceAccount::query()->where('organization_id', $organization->id)->where('finance_scope_id', $scope->id)->where('is_active', true)->find($taxAccountId) : null;
                if (! $taxAccount) throw new \InvalidArgumentException('The selected tax rate has no valid tax account mapping.');
            }
            $base = round($quantity * $unitPrice, 4);
            $rate = (float) ($taxRate?->rate ?? 0);
            $tax = round($base * $rate / 100, 4);
            $prepared[] = [
                'finance_item_id' => $item?->id, 'finance_account_id' => $account->id,
                'finance_cost_center_id' => $costCenterId, 'finance_tax_rate_id' => $taxRate?->id,
                'description' => $description, 'quantity' => $this->money($quantity), 'unit_price' => $this->money($unitPrice),
                'tax_rate' => $this->money($rate), 'line_subtotal' => $this->money($base),
                'tax_amount' => $this->money($tax), 'line_total' => $this->money($base + $tax), 'metadata' => $line['metadata'] ?? [],
            ];
        }
        return $prepared;
    }

    private function totals(array $lines): array
    {
        $subtotal = $tax = 0.0;
        foreach ($lines as $line) { $subtotal += (float) $line['line_subtotal']; $tax += (float) $line['tax_amount']; }
        $subtotal = round($subtotal, 4); $tax = round($tax, 4);
        if ($subtotal <= 0) throw new \InvalidArgumentException('A sales invoice or supplier bill must have a total greater than zero.');
        return [$this->money($subtotal), $this->money($tax), $this->money($subtotal + $tax)];
    }

    private function accountByCode(Organization $organization, FinanceScope $scope, string $code, string $type): FinanceAccount
    {
        $account = FinanceAccount::query()->where('organization_id', $organization->id)->where('finance_scope_id', $scope->id)->where('code', $code)->where('is_active', true)->first();
        if (! $account || $account->account_type->value !== $type) throw new \InvalidArgumentException("Required active {$code} control account is missing or has the wrong account type. Run Finance account provisioning first.");
        return $account;
    }

    private function lockScope(Organization $organization, FinanceScope $scope): FinanceScope
    {
        $locked = FinanceScope::query()->whereKey($scope->id)->lockForUpdate()->firstOrFail();
        if ((int) $locked->organization_id !== (int) $organization->id || ! $locked->isOrganizationScope()) {
            throw new \InvalidArgumentException('The finance scope does not belong to this organization.');
        }
        return $locked;
    }

    private function assertCurrency(Organization $organization, mixed $currency): string
    {
        $currency = strtoupper(trim((string) $currency));
        $organizationCurrency = strtoupper(trim((string) $organization->currency));
        if (! preg_match('/^[A-Z]{3}$/', $currency) || $currency !== $organizationCurrency) {
            throw new \InvalidArgumentException('Invoices, bills and payments must use the organization currency. Foreign-currency transactions are not enabled in this release.');
        }
        return $currency;
    }

    private function inputDecimal(mixed $value, string $label): float
    {
        $raw = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $raw)) {
            throw new \InvalidArgumentException(ucfirst($label) . ' must be a non-negative decimal with no more than four decimal places.');
        }
        return (float) $raw;
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 9999999999.9999) throw new \InvalidArgumentException('Enter a valid non-negative amount.');
        return number_format((float) $value, 4, '.', '');
    }
}
