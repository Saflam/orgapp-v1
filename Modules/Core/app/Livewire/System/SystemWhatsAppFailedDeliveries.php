<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\Organization;

class SystemWhatsAppFailedDeliveries extends Component
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

        $query = DB::table('notification_deliveries')
            ->join('notifications', 'notifications.id', '=', 'notification_deliveries.notification_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'notifications.organization_id')
            ->leftJoin('users', 'users.id', '=', 'notifications.user_id')
            ->where('notification_deliveries.channel', 'whatsapp')
            ->where('notification_deliveries.status', 'failed')
            ->select('notification_deliveries.id', 'notification_deliveries.provider_message_id', 'notification_deliveries.error', 'notification_deliveries.created_at', 'organizations.name as organization_name', 'notifications.title', 'users.phone as recipient_phone');

        if ($this->organizationId !== '') $query->where('notifications.organization_id', (int) $this->organizationId);
        if ($this->fromDate !== '') $query->whereDate('notification_deliveries.created_at', '>=', $this->fromDate);
        if ($this->toDate !== '') $query->whereDate('notification_deliveries.created_at', '<=', $this->toDate);

        $failedDeliveries = $query->latest('notification_deliveries.created_at')->paginate(25);
        $failedDeliveries->getCollection()->transform(function ($delivery) {
            $phone = preg_replace('/\D+/', '', (string) ($delivery->recipient_phone ?? ''));
            $delivery->recipient_masked = $phone !== '' ? str_repeat('•', max(0, strlen($phone) - 4)) . substr($phone, -4) : 'Not available';
            return $delivery;
        });
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name']);

        return view('core::livewire.system.whatsapp-failed-deliveries', compact('failedDeliveries', 'organizations'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Failed WhatsApp deliveries']);
    }
}
