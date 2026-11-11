<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCustomer;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Models\FinancePayment;
use Modules\Finance\Models\FinancePurchaseBill;
use Modules\Finance\Models\FinanceSalesInvoice;
use Modules\Finance\Models\FinanceSupplier;
use Modules\Finance\Models\FinanceTaxRate;
use Modules\Finance\Services\FinanceReceivablesPayablesService;

class FinanceReceivablesPayablesManagement extends Component
{
    use WithPagination;

    public Organization $organization;
    public string $activeTab = 'receivables';
    public string $successMessage = '';

    public string $invoiceSearch = '';
    public string $invoiceStatusFilter = 'outstanding';
    public string $billSearch = '';
    public string $billStatusFilter = 'outstanding';
    public string $paymentSearch = '';
    public string $paymentDirectionFilter = 'all';

    public ?int $invoiceCustomerId = null;
    public string $invoiceDate = '';
    public string $invoiceDueDate = '';
    public string $invoiceNotes = '';
    public string $invoiceRequestKey = '';
    public array $invoiceLines = [];

    public ?int $billSupplierId = null;
    public string $billDate = '';
    public string $billDueDate = '';
    public string $billSupplierReference = '';
    public string $billNotes = '';
    public string $billRequestKey = '';
    public array $billLines = [];

    public string $paymentDirection = 'customer_receipt';
    public string $paymentDate = '';
    public ?int $paymentAccountId = null;
    public string $paymentAmount = '';
    public array $paymentAllocations = [];

    public string $paymentReference = '';
    public string $paymentRequestKey = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->invoiceDate = $this->invoiceDueDate = $this->billDate = $this->billDueDate = $this->paymentDate = now()->toDateString();
        $this->invoiceRequestKey = (string) Str::uuid();
        $this->billRequestKey = (string) Str::uuid();
        $this->paymentRequestKey = (string) Str::uuid();
        $this->invoiceLines = [$this->emptyLine()];
        $this->billLines = [$this->emptyLine()];
        $this->paymentAllocations = [$this->emptyAllocation()];
        abort_unless($this->can($authorization, 'finance.sales_invoice.view')
            || $this->can($authorization, 'finance.sales_invoice.manage')
            || $this->can($authorization, 'finance.purchase_bill.view')
            || $this->can($authorization, 'finance.purchase_bill.manage')
            || $this->can($authorization, 'finance.payment.view')
            || $this->can($authorization, 'finance.payment.manage')
            || $this->can($authorization, 'finance.aging.view'), 403);

        $canViewSales = $this->can($authorization, 'finance.sales_invoice.view')
            || $this->can($authorization, 'finance.sales_invoice.manage');
        $canViewPurchases = $this->can($authorization, 'finance.purchase_bill.view')
            || $this->can($authorization, 'finance.purchase_bill.manage');
        $canViewAging = $this->can($authorization, 'finance.aging.view');

        if (! $canViewSales && ! $canViewAging && $canViewPurchases) {
            $this->activeTab = 'payables';
        } elseif (! $canViewSales && ! $canViewPurchases && ! $canViewAging) {
            $this->activeTab = 'payments';
        }
    }

    public function updatedInvoiceSearch(): void { $this->resetPage('invoicesPage'); }
    public function updatedInvoiceStatusFilter(): void { $this->resetPage('invoicesPage'); }
    public function updatedBillSearch(): void { $this->resetPage('billsPage'); }
    public function updatedBillStatusFilter(): void { $this->resetPage('billsPage'); }
    public function updatedPaymentSearch(): void { $this->resetPage('paymentsPage'); }
    public function updatedPaymentDirectionFilter(): void { $this->resetPage('paymentsPage'); }

    public function addInvoiceLine(): void { $this->invoiceLines[] = $this->emptyLine(); }
    public function addBillLine(): void { $this->billLines[] = $this->emptyLine(); }
    public function addPaymentAllocation(): void { $this->paymentAllocations[] = $this->emptyAllocation(); }
    public function removePaymentAllocation(int $index): void { if (count($this->paymentAllocations) > 1) { unset($this->paymentAllocations[$index]); $this->paymentAllocations = array_values($this->paymentAllocations); } }
    public function removeInvoiceLine(int $index): void { if (count($this->invoiceLines) > 1) { unset($this->invoiceLines[$index]); $this->invoiceLines = array_values($this->invoiceLines); } }
    public function removeBillLine(int $index): void { if (count($this->billLines) > 1) { unset($this->billLines[$index]); $this->billLines = array_values($this->billLines); } }

    public function postInvoice(FinanceReceivablesPayablesService $service, AuthorizationService $authorization): void
    {
        $this->ensureCan($authorization, 'finance.sales_invoice.manage');
        $data = $this->validate(['invoiceCustomerId' => ['required', 'integer'], 'invoiceDate' => ['required', 'date'], 'invoiceDueDate' => ['required', 'date', 'after_or_equal:invoiceDate'], 'invoiceNotes' => ['nullable', 'string', 'max:2000'], 'invoiceLines' => ['required', 'array', 'min:1', 'max:100'], 'invoiceLines.*.description' => ['required', 'string', 'max:1000'], 'invoiceLines.*.quantity' => ['required', 'numeric', 'gt:0'], 'invoiceLines.*.unit_price' => ['required', 'numeric', 'min:0'], 'invoiceLines.*.finance_account_id' => ['nullable', 'integer'], 'invoiceLines.*.finance_cost_center_id' => ['nullable', 'integer'], 'invoiceLines.*.finance_tax_rate_id' => ['nullable', 'integer']]);
        try {
            $service->postSalesInvoice($this->organization, ['finance_customer_id' => $data['invoiceCustomerId'], 'request_key' => $this->invoiceRequestKey, 'invoice_date' => $data['invoiceDate'], 'due_date' => $data['invoiceDueDate'], 'notes' => $data['invoiceNotes'], 'lines' => $data['invoiceLines']]);
        } catch (\InvalidArgumentException $exception) { throw ValidationException::withMessages(['invoice' => $exception->getMessage()]); }
        $this->invoiceCustomerId = null; $this->invoiceNotes = ''; $this->invoiceLines = [$this->emptyLine()]; $this->invoiceRequestKey = (string) Str::uuid();
        $this->successMessage = 'Sales invoice posted and journal generated.';
    }

    public function postBill(FinanceReceivablesPayablesService $service, AuthorizationService $authorization): void
    {
        $this->ensureCan($authorization, 'finance.purchase_bill.manage');
        $data = $this->validate(['billSupplierId' => ['required', 'integer'], 'billDate' => ['required', 'date'], 'billDueDate' => ['required', 'date', 'after_or_equal:billDate'], 'billSupplierReference' => ['nullable', 'string', 'max:100'], 'billNotes' => ['nullable', 'string', 'max:2000'], 'billLines' => ['required', 'array', 'min:1', 'max:100'], 'billLines.*.description' => ['required', 'string', 'max:1000'], 'billLines.*.quantity' => ['required', 'numeric', 'gt:0'], 'billLines.*.unit_price' => ['required', 'numeric', 'min:0'], 'billLines.*.finance_account_id' => ['nullable', 'integer'], 'billLines.*.finance_cost_center_id' => ['nullable', 'integer'], 'billLines.*.finance_tax_rate_id' => ['nullable', 'integer']]);
        try {
            $service->postPurchaseBill($this->organization, ['finance_supplier_id' => $data['billSupplierId'], 'request_key' => $this->billRequestKey, 'bill_date' => $data['billDate'], 'due_date' => $data['billDueDate'], 'supplier_invoice_number' => $data['billSupplierReference'], 'notes' => $data['billNotes'], 'lines' => $data['billLines']]);
        } catch (\InvalidArgumentException $exception) { throw ValidationException::withMessages(['bill' => $exception->getMessage()]); }
        $this->billSupplierId = null; $this->billSupplierReference = $this->billNotes = ''; $this->billLines = [$this->emptyLine()]; $this->billRequestKey = (string) Str::uuid();
        $this->successMessage = 'Supplier bill posted and journal generated.';
    }

    public function recordPayment(FinanceReceivablesPayablesService $service, AuthorizationService $authorization): void
    {
        $this->ensureCan($authorization, 'finance.payment.manage');
        $data = $this->validate([
            'paymentDirection' => ['required', 'in:customer_receipt,supplier_payment'],
            'paymentDate' => ['required', 'date'], 'paymentAccountId' => ['required', 'integer'],
            'paymentAmount' => ['required', 'numeric', 'gt:0'], 'paymentReference' => ['nullable', 'string', 'max:150'],
            'paymentAllocations' => ['required', 'array', 'min:1', 'max:100'],
            'paymentAllocations.*.document_id' => ['required', 'integer'],
            'paymentAllocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $allocations = array_map(fn (array $row): array => $data['paymentDirection'] === 'customer_receipt'
            ? ['finance_sales_invoice_id' => (int) $row['document_id'], 'amount' => $row['amount']]
            : ['finance_purchase_bill_id' => (int) $row['document_id'], 'amount' => $row['amount']], $data['paymentAllocations']);
        try {
            $service->recordPayment($this->organization, ['direction' => $data['paymentDirection'], 'request_key' => $this->paymentRequestKey, 'payment_date' => $data['paymentDate'], 'finance_account_id' => $data['paymentAccountId'], 'amount' => $data['paymentAmount'], 'reference' => $data['paymentReference'], 'allocations' => $allocations]);
        } catch (\InvalidArgumentException $exception) { throw ValidationException::withMessages(['payment' => $exception->getMessage()]); }
        $this->paymentAmount = $this->paymentReference = ''; $this->paymentAllocations = [$this->emptyAllocation()]; $this->paymentRequestKey = (string) Str::uuid();
        $this->successMessage = 'Payment posted and allocated.';
    }

    public function updatedPaymentDirection(): void { $this->paymentAllocations = [$this->emptyAllocation()]; }

    public function updatedBillSupplierId(): void
    {
        if (! $this->billSupplierId) return;
        $supplier = FinanceSupplier::query()->where('organization_id', $this->organization->id)->find($this->billSupplierId);
        if ($supplier) $this->billDueDate = \Illuminate\Support\Carbon::parse($this->billDate)->addDays($supplier->payment_terms_days)->toDateString();
    }

    public function updatedBillDate(): void { $this->updatedBillSupplierId(); }

    public function render(AuthorizationService $authorization, FinanceReceivablesPayablesService $service): View
    {
        $canViewPayments = $this->can($authorization, 'finance.payment.view') || $this->can($authorization, 'finance.payment.manage');
        $canViewSales = $this->can($authorization, 'finance.sales_invoice.view') || $this->can($authorization, 'finance.sales_invoice.manage');
        $canViewPurchases = $this->can($authorization, 'finance.purchase_bill.view') || $this->can($authorization, 'finance.purchase_bill.manage');
        $canManageSales = $this->can($authorization, 'finance.sales_invoice.manage');
        $canManagePurchases = $this->can($authorization, 'finance.purchase_bill.manage');
        $canViewCustomers = $this->can($authorization, 'finance.customer.view') || $this->can($authorization, 'finance.customer.manage');
        $canViewSuppliers = $this->can($authorization, 'finance.supplier.view') || $this->can($authorization, 'finance.supplier.manage');

        $customers = ($canViewCustomers || $canManageSales)
            ? FinanceCustomer::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $suppliers = ($canViewSuppliers || $canManagePurchases)
            ? FinanceSupplier::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $accounts = FinanceAccount::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('code')->get();
        $cashAccounts = $accounts->filter(fn ($account) => $account->is_cash_account);
        $costCenters = FinanceCostCenter::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('name')->get();
        $taxRates = FinanceTaxRate::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('name')->get();

        $invoicesQuery = FinanceSalesInvoice::query()
            ->where('organization_id', $this->organization->id)
            ->with('customer')
            ->when($this->invoiceStatusFilter === 'outstanding', fn ($query) => $query->whereIn('status', ['posted', 'partially_paid']))
            ->when($this->invoiceStatusFilter !== 'outstanding' && $this->invoiceStatusFilter !== 'all', fn ($query) => $query->where('status', $this->invoiceStatusFilter))
            ->when(trim($this->invoiceSearch) !== '', function ($query): void {
                $term = '%' . trim($this->invoiceSearch) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term));
                });
            })
            ->latest('invoice_date');

        $billsQuery = FinancePurchaseBill::query()
            ->where('organization_id', $this->organization->id)
            ->with('supplier')
            ->when($this->billStatusFilter === 'outstanding', fn ($query) => $query->whereIn('status', ['posted', 'partially_paid']))
            ->when($this->billStatusFilter !== 'outstanding' && $this->billStatusFilter !== 'all', fn ($query) => $query->where('status', $this->billStatusFilter))
            ->when(trim($this->billSearch) !== '', function ($query): void {
                $term = '%' . trim($this->billSearch) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('bill_number', 'like', $term)
                        ->orWhere('supplier_invoice_number', 'like', $term)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term));
                });
            })
            ->latest('bill_date');

        $paymentsQuery = FinancePayment::query()
            ->where('organization_id', $this->organization->id)
            ->with(['account', 'receipt', 'allocations.invoice.customer', 'allocations.bill.supplier'])
            ->when($this->paymentDirectionFilter !== 'all', fn ($query) => $query->where('direction', $this->paymentDirectionFilter))
            ->when(trim($this->paymentSearch) !== '', function ($query): void {
                $term = '%' . trim($this->paymentSearch) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('payment_number', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhereHas('allocations.invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $term))
                        ->orWhereHas('allocations.bill', fn ($bill) => $bill->where('bill_number', 'like', $term))
                        ->orWhereHas('allocations.invoice.customer', fn ($customer) => $customer->where('name', 'like', $term))
                        ->orWhereHas('allocations.bill.supplier', fn ($supplier) => $supplier->where('name', 'like', $term));
                });
            })
            ->latest('payment_date');

        return view('finance::livewire.receivables-payables.manage', [
            'customers' => $customers,
            'suppliers' => $suppliers,
            'accounts' => $accounts,
            'cashAccounts' => $cashAccounts,
            'taxRates' => $taxRates,
            'costCenters' => $costCenters,
            'invoices' => $canViewSales ? $invoicesQuery->paginate(10, ['*'], 'invoicesPage') : collect(),
            'bills' => $canViewPurchases ? $billsQuery->paginate(10, ['*'], 'billsPage') : collect(),
            'invoiceOptions' => ($canViewSales || $canViewPayments) ? FinanceSalesInvoice::query()->where('organization_id', $this->organization->id)->whereIn('status', ['posted', 'partially_paid'])->with('customer')->orderByDesc('invoice_date')->limit(500)->get() : collect(),
            'billOptions' => ($canViewPurchases || $canViewPayments) ? FinancePurchaseBill::query()->where('organization_id', $this->organization->id)->whereIn('status', ['posted', 'partially_paid'])->with('supplier')->orderByDesc('bill_date')->limit(500)->get() : collect(),
            'payments' => $canViewPayments ? $paymentsQuery->paginate(10, ['*'], 'paymentsPage') : collect(),
            'receivablesAging' => $this->can($authorization, 'finance.aging.view') ? $service->receivablesAging($this->organization) : null,
            'payablesAging' => $this->can($authorization, 'finance.aging.view') ? $service->payablesAging($this->organization) : null,
            'canViewSales' => $canViewSales,
            'canViewPurchases' => $canViewPurchases,
            'canViewPayments' => $canViewPayments,
            'canViewParties' => $canViewCustomers || $canViewSuppliers,
            'canManageSales' => $canManageSales,
            'canManagePurchases' => $canManagePurchases,
            'canManagePayments' => $this->can($authorization, 'finance.payment.manage'),
        ])->layout('core::layouts.organization-admin', ['pageTitle' => 'Receivables & Payables', 'layoutOrganization' => $this->organization]);
    }

    private function emptyLine(): array { return ['description' => '', 'quantity' => '1.0000', 'unit_price' => '0.0000', 'finance_account_id' => null, 'finance_cost_center_id' => null, 'finance_tax_rate_id' => null]; }
    private function emptyAllocation(): array { return ['document_id' => null, 'amount' => '']; }
    private function can(AuthorizationService $authorization, string $permission): bool { $user = auth()->user(); return $user !== null && $authorization->can($user, $permission, new AuthorizationContext((int) $this->organization->id)); }
    private function ensureCan(AuthorizationService $authorization, string $permission): void { abort_unless($this->can($authorization, $permission), 403); }
}
