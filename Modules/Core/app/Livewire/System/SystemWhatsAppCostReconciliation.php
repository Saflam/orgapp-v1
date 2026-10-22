<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\Core\Models\Organization;
use Modules\Core\Models\WhatsAppCostReconciliationBatch;
use Modules\Core\Services\WhatsAppCostReconciliationService;
use RuntimeException;

class SystemWhatsAppCostReconciliation extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $csvFile = null;
    public string $organizationId = '';
    public string $fromDate = '';
    public string $toDate = '';
    public string $importMessage = '';
    public string $importError = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function updatingOrganizationId(): void { $this->resetPage(); }
    public function updatingFromDate(): void { $this->resetPage(); }
    public function updatingToDate(): void { $this->resetPage(); }

    public function import(WhatsAppCostReconciliationService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $batch = $service->importCsv($this->csvFile, auth()->id());
            $this->importMessage = "Import #{$batch->id} processed: {$batch->matched_count} matched, {$batch->discrepancy_count} with discrepancies, {$batch->unmatched_count} unmatched, {$batch->duplicate_count} duplicate, {$batch->invalid_count} invalid.";
            $this->importError = '';
            $this->csvFile = null;
            $this->resetPage();
        } catch (RuntimeException $exception) {
            $this->importError = $exception->getMessage();
            $this->importMessage = '';
        }
    }

    public function reconcilePending(WhatsAppCostReconciliationService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $result = $service->reconcilePending();
        $this->importMessage = "Retry completed: {$result['matched']} matched, {$result['discrepancy']} with discrepancies, {$result['duplicate']} marked duplicate, {$result['still_unmatched']} still unmatched.";
        $this->importError = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $query = $this->filteredItemsQuery();
        $filename = 'whatsapp-cost-reconciliation-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM helps spreadsheet applications display organization names correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'reconciliation_item_id', 'batch_id', 'source_filename', 'provider_occurred_at',
                'organization_id', 'organization_name', 'provider_message_id', 'provider_cost_amount',
                'provider_cost_currency', 'pricing_category', 'billable', 'reconciliation_status', 'issue',
                'internal_cost_amount', 'internal_cost_currency', 'internal_pricing_category', 'internal_billable', 'discrepancy_details',
            ]);

            foreach ($query->orderBy('items.id')->cursor() as $item) {
                fputcsv($output, [
                    $item->id,
                    $item->batch_id,
                    $this->safeCsvCell($item->source_filename),
                    $item->provider_occurred_at,
                    $item->organization_id,
                    $this->safeCsvCell($item->organization_name),
                    $this->safeCsvCell($item->provider_message_id),
                    $item->provider_cost_amount,
                    $item->provider_cost_currency,
                    $this->safeCsvCell($item->pricing_category),
                    is_null($item->billable) ? '' : ($item->billable ? 'true' : 'false'),
                    $item->reconciliation_status,
                    $this->safeCsvCell($item->issue),
                    $item->internal_cost_amount,
                    $item->internal_cost_currency,
                    $this->safeCsvCell($item->internal_pricing_category),
                    is_null($item->internal_billable) ? '' : ($item->internal_billable ? 'true' : 'false'),
                    $this->safeCsvCell(json_encode($item->discrepancy_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredItemsQuery()
    {
        $query = DB::table('whatsapp_cost_reconciliation_items as items')
            ->leftJoin('organizations', 'organizations.id', '=', 'items.organization_id')
            ->leftJoin('whatsapp_cost_reconciliation_batches as batches', 'batches.id', '=', 'items.batch_id')
            ->select([
                'items.*',
                'organizations.name as organization_name',
                'batches.source_filename',
            ]);

        if ($this->organizationId !== '') {
            $query->where(function ($nested): void {
                $nested->where('items.organization_id', (int) $this->organizationId)
                    ->orWhereNull('items.organization_id');
            });
        }
        if ($this->fromDate !== '') {
            $query->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) >= ?', [$this->fromDate]);
        }
        if ($this->toDate !== '') {
            $query->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) <= ?', [$this->toDate]);
        }

        return $query;
    }

    private function safeCsvCell(?string $value): string
    {
        $value = (string) $value;

        // Prevent spreadsheet formula execution for provider-controlled or organization-controlled text.
        if ($value !== '' && preg_match('/^[\s]*[=+@-]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $base = DB::table('whatsapp_cost_reconciliation_items as items')
            ->whereIn('items.reconciliation_status', ['matched', 'discrepancy']);
        if ($this->organizationId !== '') {
            $base->where('items.organization_id', (int) $this->organizationId);
        }
        if ($this->fromDate !== '') {
            $base->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) >= ?', [$this->fromDate]);
        }
        if ($this->toDate !== '') {
            $base->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) <= ?', [$this->toDate]);
        }

        $costTotals = (clone $base)
            ->selectRaw('items.provider_cost_currency as currency, COUNT(*) as matched_messages, SUM(items.provider_cost_amount) as total_cost')
            ->groupBy('items.provider_cost_currency')
            ->orderBy('items.provider_cost_currency')
            ->get();

        $issueQuery = DB::table('whatsapp_cost_reconciliation_items as items')
            ->whereIn('items.reconciliation_status', ['unmatched', 'duplicate', 'invalid', 'discrepancy']);
        if ($this->organizationId !== '') {
            $issueQuery->where(function ($query): void {
                $query->where('items.organization_id', (int) $this->organizationId)
                    ->orWhereNull('items.organization_id');
            });
        }
        if ($this->fromDate !== '') {
            $issueQuery->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) >= ?', [$this->fromDate]);
        }
        if ($this->toDate !== '') {
            $issueQuery->whereRaw('DATE(COALESCE(items.provider_occurred_at, items.created_at)) <= ?', [$this->toDate]);
        }
        $issueCounts = (clone $issueQuery)
            ->selectRaw('items.reconciliation_status, COUNT(*) as item_count')
            ->groupBy('items.reconciliation_status')
            ->pluck('item_count', 'reconciliation_status');

        $items = $this->filteredItemsQuery()->orderByDesc('items.id')->paginate(25);

        $batches = WhatsAppCostReconciliationBatch::query()->with('importer')->latest('id')->limit(10)->get();
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name']);

        return view('core::livewire.system.whatsapp-cost-reconciliation', compact(
            'costTotals', 'issueCounts', 'items', 'batches', 'organizations'
        ))->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp cost reconciliation']);
    }
}
