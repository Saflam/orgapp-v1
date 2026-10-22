<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationWhatsAppUsageLimit;
use Modules\Core\Models\WhatsAppUsageLimitRequest;
use Modules\Core\Services\WhatsAppUsageLimitService;

class SystemWhatsAppUsage extends Component
{
    public string $fromDate = '';
    public string $toDate = '';
    public array $limits = [];
    public array $requestDecisions = [];
    public string $limitActionMessage = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
        $this->loadLimits();
    }


    public function saveOrganizationLimit(int $organizationId, WhatsAppUsageLimitService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $organization = Organization::query()->findOrFail($organizationId);
        $row = $this->limits[$organizationId] ?? [];
        $validated = Validator::make($row, [
            'unlimited' => ['required', 'boolean'],
            'monthly_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'warning_threshold' => ['required', 'integer', 'in:70,80,90'],
        ])->validate();
        if (! $validated['unlimited'] && empty($validated['monthly_limit'])) {
            $this->addError("limits.{$organizationId}.monthly_limit", 'Enter a monthly limit or select unlimited.');
            return;
        }
        $service->setLimit($organization, $validated['unlimited'] ? null : (int) $validated['monthly_limit'], (int) $validated['warning_threshold'], auth()->id(), 'Manual System Admin configuration.');
        $this->loadLimits();
        $this->limitActionMessage = 'WhatsApp usage limit saved for ' . $organization->name . '.';
    }

    public function reviewLimitRequest(int $requestId, string $decision, WhatsAppUsageLimitService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        abort_unless(in_array($decision, ['approve', 'reject'], true), 422);
        $request = WhatsAppUsageLimitRequest::query()->where('status', 'pending')->findOrFail($requestId);
        $row = $this->requestDecisions[$requestId] ?? [];
        $validated = Validator::make($row, [
            'granted_limit' => ['nullable', 'integer', 'min:' . ($request->current_limit + 1), 'max:10000000'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        if ($decision === 'approve') {
            $granted = isset($validated['granted_limit']) && $validated['granted_limit'] !== '' ? (int) $validated['granted_limit'] : (int) $request->requested_limit;
            if ($granted <= (int) $request->current_limit) {
                $this->addError("requestDecisions.{$requestId}.granted_limit", 'The granted limit must exceed the current limit.');
                return;
            }
            $service->setLimit($request->organization, $granted, (int) (OrganizationWhatsAppUsageLimit::query()->where('organization_id', $request->organization_id)->value('warning_threshold') ?? 80), auth()->id(), $validated['review_note'] ?? 'Usage-limit increase approved.', $request->id);
            $request->update(['status' => 'approved', 'granted_limit' => $granted, 'reviewed_by' => auth()->id(), 'review_note' => $validated['review_note'] ?? null, 'reviewed_at' => now()]);
            $this->limitActionMessage = 'Request approved. The new limit is effective immediately.';
        } else {
            $request->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'review_note' => $validated['review_note'] ?? 'Rejected by System Admin.', 'reviewed_at' => now()]);
            $this->limitActionMessage = 'Usage-limit request rejected.';
        }
        $this->loadLimits();
    }

    private function loadLimits(): void
    {
        $this->limits = [];
        foreach (Organization::query()->orderBy('name')->get() as $organization) {
            $limit = OrganizationWhatsAppUsageLimit::query()->where('organization_id', $organization->id)->first();
            $this->limits[$organization->id] = [
                'unlimited' => ! $limit || $limit->monthly_limit === null,
                'monthly_limit' => $limit?->monthly_limit,
                'warning_threshold' => (int) ($limit?->warning_threshold ?? 80),
            ];
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $organizations = Organization::query()->orderBy('name')->get();
        $pendingLimitRequests = WhatsAppUsageLimitRequest::query()->with(['organization', 'requester'])->where('status', 'pending')->oldest()->get();
        $limitAudits = DB::table('whatsapp_usage_limit_audits')->join('organizations', 'organizations.id', '=', 'whatsapp_usage_limit_audits.organization_id')->leftJoin('users', 'users.id', '=', 'whatsapp_usage_limit_audits.changed_by')->select('whatsapp_usage_limit_audits.*', 'organizations.name as organization_name', 'users.name as changed_by_name')->latest('whatsapp_usage_limit_audits.created_at')->limit(25)->get();
        return view('core::livewire.system.whatsapp-usage', compact('organizations', 'pendingLimitRequests', 'limitAudits'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp usage']);
    }
}
