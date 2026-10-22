<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\Organization;

class SystemWhatsAppMessageHistory extends Component
{
    use WithPagination;

    public string $organizationId = '';
    public string $fromDate = '';
    public string $toDate = '';
    public string $serviceCategory = '';
    public string $apiStatus = '';
    public string $deliveryStatus = '';
    public string $search = '';
    public ?int $selectedAuditUsageId = null;

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
    public function updatingServiceCategory(): void { $this->resetPage(); }
    public function updatingApiStatus(): void { $this->resetPage(); }
    public function updatingDeliveryStatus(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }

    public function toggleAudit(int $usageId): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $exists = DB::table('whatsapp_message_usages')->where('id', $usageId)->exists();
        abort_unless($exists, 404);
        $this->selectedAuditUsageId = $this->selectedAuditUsageId === $usageId ? null : $usageId;
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $query = DB::table('whatsapp_message_usages as wa')
            ->leftJoin('organizations as org', 'org.id', '=', 'wa.organization_id')
            ->leftJoin('users as usr', 'usr.id', '=', 'wa.user_id')
            ->leftJoin('notification_deliveries as nd', 'nd.id', '=', 'wa.notification_delivery_id')
            ->leftJoin('notifications as n', 'n.id', '=', 'nd.notification_id')
            ->select([
                'wa.id', 'wa.organization_id', 'org.name as organization_name',
                'wa.user_id', 'usr.phone as recipient_phone', 'wa.service_category',
                'wa.channel_source', 'wa.provider_message_id', 'wa.status as api_status',
                'wa.delivery_status', 'wa.accepted_at', 'wa.delivered_at', 'wa.created_at',
                'wa.metadata', 'wa.meta_pricing_category', 'wa.meta_billable',
                'nd.attempts', 'nd.error as delivery_error', 'n.title as notification_title',
            ]);

        if ($this->organizationId !== '') {
            $query->where('wa.organization_id', (int) $this->organizationId);
        }
        if ($this->fromDate !== '') {
            $query->whereDate('wa.created_at', '>=', $this->fromDate);
        }
        if ($this->toDate !== '') {
            $query->whereDate('wa.created_at', '<=', $this->toDate);
        }
        if ($this->serviceCategory !== '') {
            $query->where('wa.service_category', $this->serviceCategory);
        }
        if ($this->apiStatus !== '') {
            $query->where('wa.status', $this->apiStatus);
        }
        if ($this->deliveryStatus !== '') {
            if ($this->deliveryStatus === 'awaiting') {
                $query->where('wa.status', 'accepted')->whereNull('wa.delivery_status');
            } else {
                $query->where('wa.delivery_status', $this->deliveryStatus);
            }
        }
        if (trim($this->search) !== '') {
            $term = '%' . addcslashes(trim($this->search), '%_\\') . '%';
            $query->where(function ($sub) use ($term): void {
                $sub->where('wa.provider_message_id', 'like', $term)
                    ->orWhere('n.title', 'like', $term)
                    ->orWhere('usr.phone', 'like', $term);
            });
        }

        $messages = $query->orderByDesc('wa.created_at')->orderByDesc('wa.id')->paginate(25);
        $messages->getCollection()->transform(function ($message) {
            $digits = preg_replace('/\D+/', '', (string) ($message->recipient_phone ?? ''));
            $message->recipient_masked = $digits !== ''
                ? str_repeat('•', max(0, strlen($digits) - 4)) . substr($digits, -4)
                : 'Not available';
            $metadata = is_array($message->metadata)
                ? $message->metadata
                : (json_decode((string) $message->metadata, true) ?: []);
            $message->delivery_error_detail = data_get($metadata, 'delivery_error.message')
                ?: data_get($metadata, 'delivery_error.title')
                ?: $message->delivery_error;
            $message->retry_count = max(0, (int) ($message->attempts ?? 0));
            return $message;
        });

        $organizations = Organization::query()->orderBy('name')->get(['id', 'name']);
        $auditEvents = collect();
        if ($this->selectedAuditUsageId !== null) {
            $selected = DB::table('whatsapp_message_usages')
                ->where('id', $this->selectedAuditUsageId)
                ->first(['id', 'provider_message_id']);
            if ($selected) {
                $auditEvents = DB::table('whatsapp_webhook_events')
                    ->where('provider_message_id', $selected->provider_message_id)
                    ->orderByDesc('provider_timestamp')
                    ->orderByDesc('id')
                    ->get(['status', 'provider_timestamp', 'error_code', 'error_title', 'pricing_category', 'billable', 'processed_at']);
            }
        }

        return view('core::livewire.system.whatsapp-message-history', compact('messages', 'organizations', 'auditEvents'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp message history']);
    }
}
