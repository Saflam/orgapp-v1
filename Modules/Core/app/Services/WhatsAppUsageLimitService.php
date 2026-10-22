<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationWhatsAppUsageLimit;
use Modules\Core\Models\WhatsAppMessageUsage;
use RuntimeException;

class WhatsAppUsageLimitService
{
    /** Atomically reserves one slot. A null limit means unlimited. */
    public function reserve(Organization|int|null $organization): bool
    {
        $organizationId = $organization instanceof Organization ? (int) $organization->getKey() : (int) $organization;
        if ($organizationId <= 0) return true;

        return DB::transaction(function () use ($organizationId): bool {
            $limit = OrganizationWhatsAppUsageLimit::query()->where('organization_id', $organizationId)->lockForUpdate()->first();
            if (! $limit || $limit->monthly_limit === null) return false;

            $periodStart = now()->startOfMonth()->toDateString();
            if (! $limit->period_start || $limit->period_start->toDateString() !== $periodStart) {
                $accepted = WhatsAppMessageUsage::query()->where('organization_id', $organizationId)
                    ->where('status', 'accepted')->where('created_at', '>=', $periodStart)->count();
                $limit->period_start = $periodStart;
                $limit->period_reserved_count = $accepted;
            }
            if ($limit->period_reserved_count >= $limit->monthly_limit) {
                $limit->save();
                throw new WhatsAppUsageLimitExceeded('This organization has reached its monthly WhatsApp message limit.');
            }
            $limit->period_reserved_count++;
            $limit->save();
            return true;
        }, 3);
    }

    public function release(Organization|int|null $organization, bool $reserved): void
    {
        if (! $reserved) return;
        $organizationId = $organization instanceof Organization ? (int) $organization->getKey() : (int) $organization;
        if ($organizationId <= 0) return;
        DB::transaction(function () use ($organizationId): void {
            $limit = OrganizationWhatsAppUsageLimit::query()->where('organization_id', $organizationId)->lockForUpdate()->first();
            if (! $limit || $limit->monthly_limit === null) return;
            $periodStart = now()->startOfMonth()->toDateString();
            if ($limit->period_start?->toDateString() === $periodStart && $limit->period_reserved_count > 0) {
                $limit->decrement('period_reserved_count');
            }
        }, 3);
    }

    public function setLimit(
        Organization $organization,
        ?int $monthlyLimit,
        int $warningThreshold = 80,
        ?int $changedBy = null,
        ?string $reason = null,
        ?int $requestId = null,
    ): OrganizationWhatsAppUsageLimit {
        return DB::transaction(function () use ($organization, $monthlyLimit, $warningThreshold, $changedBy, $reason, $requestId): OrganizationWhatsAppUsageLimit {
            $existing = OrganizationWhatsAppUsageLimit::query()->where('organization_id', $organization->id)->lockForUpdate()->first();
            $previousLimit = $existing?->monthly_limit;
            $periodStart = now()->startOfMonth()->toDateString();
            $used = WhatsAppMessageUsage::query()->where('organization_id', $organization->id)
                ->where('status', 'accepted')->where('created_at', '>=', $periodStart)->count();
            $limit = OrganizationWhatsAppUsageLimit::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                ['monthly_limit' => $monthlyLimit, 'warning_threshold' => $warningThreshold, 'period_start' => $periodStart, 'period_reserved_count' => max($used, (int) ($existing?->period_reserved_count ?? 0))],
            );
            DB::table('whatsapp_usage_limit_audits')->insert([
                'organization_id' => $organization->id,
                'changed_by' => $changedBy ?? auth()->id(),
                'request_id' => $requestId,
                'previous_limit' => $previousLimit,
                'new_limit' => $monthlyLimit,
                'action' => $requestId ? 'request_approved' : 'limit_changed',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $limit;
        }, 3);
    }

    public function currentUsage(Organization $organization): array
    {
        $start = now()->startOfMonth();
        $accepted = WhatsAppMessageUsage::query()->where('organization_id', $organization->id)
            ->where('status', 'accepted')->where('created_at', '>=', $start)->count();
        $limit = OrganizationWhatsAppUsageLimit::query()->where('organization_id', $organization->id)->first();
        $used = max($accepted, (int) ($limit?->period_reserved_count ?? 0));
        $maximum = $limit?->monthly_limit;
        return [
            'used' => $used,
            'limit' => $maximum,
            'remaining' => $maximum === null ? null : max(0, $maximum - $used),
            'percentage' => $maximum ? min(100, (int) floor($used * 100 / $maximum)) : 0,
            'warning_threshold' => (int) ($limit?->warning_threshold ?? 80),
            'period_start' => $start->toDateString(),
            'reset_at' => $start->copy()->addMonth()->toDateString(),
            'exhausted' => $maximum !== null && $used >= $maximum,
        ];
    }
}

class WhatsAppUsageLimitExceeded extends RuntimeException {}
