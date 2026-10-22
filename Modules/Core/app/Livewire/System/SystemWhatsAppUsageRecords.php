<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\Organization;

class SystemWhatsAppUsageRecords extends Component
{
    use WithPagination;

    public string $organizationId = '';
    public string $fromDate = '';
    public string $toDate = '';

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

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $base = DB::table('whatsapp_message_usages');
        if ($this->organizationId !== '') {
            $base->where('organization_id', (int) $this->organizationId);
        }
        if ($this->fromDate !== '') $base->whereDate('created_at', '>=', $this->fromDate);
        if ($this->toDate !== '') $base->whereDate('created_at', '<=', $this->toDate);

        $totalAccepted = (clone $base)->where('status', 'accepted')->count();

        $rowsQuery = DB::table('whatsapp_message_usages')
            ->leftJoin('organizations', 'organizations.id', '=', 'whatsapp_message_usages.organization_id')
            ->selectRaw('whatsapp_message_usages.organization_id, organizations.name as organization_name, whatsapp_message_usages.service_category, whatsapp_message_usages.channel_source, whatsapp_message_usages.status, whatsapp_message_usages.delivery_status, COUNT(*) as message_count');
        if ($this->organizationId !== '') $rowsQuery->where('whatsapp_message_usages.organization_id', (int) $this->organizationId);
        if ($this->fromDate !== '') $rowsQuery->whereDate('whatsapp_message_usages.created_at', '>=', $this->fromDate);
        if ($this->toDate !== '') $rowsQuery->whereDate('whatsapp_message_usages.created_at', '<=', $this->toDate);

        $rows = $rowsQuery
            ->groupBy('whatsapp_message_usages.organization_id', 'organizations.name', 'whatsapp_message_usages.service_category', 'whatsapp_message_usages.channel_source', 'whatsapp_message_usages.status', 'whatsapp_message_usages.delivery_status')
            ->orderBy('organizations.name')
            ->orderBy('whatsapp_message_usages.service_category')
            ->paginate(25);

        $organizations = Organization::query()->orderBy('name')->get(['id', 'name']);

        return view('core::livewire.system.whatsapp-usage-records', compact('rows', 'totalAccepted', 'organizations'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp usage records']);
    }
}
