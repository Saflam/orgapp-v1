<?php
namespace Modules\Member\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;
use Modules\Core\Services\NotificationService;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Models\Membership;
use Modules\Member\Models\UserIdentification;

class MemberScheduledNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MemberPaymentService $payments,
    ) {}

    public function sendSubscriptionReminders(?Carbon $today = null): int
    {
        $today ??= now()->startOfDay();
        $sent = 0;

        Membership::query()
            ->with('member.organization')
            ->where('status', MembershipStatus::ACTIVE)
            ->chunkById(100, function (Collection $memberships) use ($today, &$sent): void {
                foreach ($memberships as $membership) {
                    $member = $membership->member;
                    if ($member === null || $member->organization === null) {
                        continue;
                    }

                    $items = MemberPaymentItem::query()
                        ->where('organization_id', $member->organization_id)
                        ->where('is_active', true)
                        ->where('is_mandatory', true)
                        ->where('is_recurring', true)
                        ->where(function ($query) use ($membership): void {
                            $query->whereNull('membership_type_id')
                                ->orWhere('membership_type_id', $membership->membership_type_id);
                        })
                        ->get(['reminder_days_before_due']);

                    $maxBeforeReminderDays = (int) $items
                        ->filter(fn (MemberPaymentItem $item): bool => $item->reminder_days_before_due !== null)
                        ->max('reminder_days_before_due');

                    $this->payments->syncOutstandingObligations(
                        $membership,
                        $today->copy()->addDays($maxBeforeReminderDays)->toDateString(),
                    );

                    $pending = MemberPaymentObligation::query()
                        ->with('paymentItem')
                        ->where('membership_id', $membership->id)
                        ->whereIn('status', ['pending', 'partially_paid'])
                        ->get();

                    foreach ($pending as $obligation) {
                        $item = $obligation->paymentItem;
                        if ($item === null) {
                            continue;
                        }

                        $beforeDays = $item->reminder_days_before_due;
                        if (
                            $beforeDays !== null
                            && $obligation->due_at->isSameDay($today->copy()->addDays($beforeDays))
                        ) {
                            $sent += $this->notifySubscription(
                                $member->user,
                                $member->organization,
                                $obligation,
                                'before',
                                $beforeDays,
                            ) ? 1 : 0;
                        }

                        $afterDays = $item->reminder_days_after_due;
                        if (
                            $afterDays !== null
                            && $afterDays > 0
                            && $obligation->due_at->isSameDay($today->copy()->subDays($afterDays))
                        ) {
                            $sent += $this->notifySubscription(
                                $member->user,
                                $member->organization,
                                $obligation,
                                'overdue',
                                $afterDays,
                            ) ? 1 : 0;
                        }
                    }
                }
            });

        return $sent;
    }

    public function sendIdentificationExpiryReminders(?Carbon $today = null): int
    {
        $today ??= now()->startOfDay();
        $sent = 0;

        OrganizationIdentification::query()
            ->with('organization')
            ->where('is_enabled', true)
            ->where('has_expiry', true)
            ->chunkById(100, function (Collection $configurations) use ($today, &$sent): void {
                foreach ($configurations as $configuration) {
                    $reminderDays = collect($configuration->expiry_reminder_days ?? [])
                        ->map(fn ($days): int => (int) $days)
                        ->filter(fn (int $days): bool => $days >= 0)
                        ->unique()
                        ->values();

                    if ($reminderDays->isEmpty() || $configuration->organization === null) {
                        continue;
                    }

                    $memberUserIds = Member::query()
                        ->where('organization_id', $configuration->organization_id)
                        ->whereHas('memberships', fn ($query) => $query->where('status', MembershipStatus::ACTIVE))
                        ->pluck('user_id');

                    if ($memberUserIds->isEmpty()) {
                        continue;
                    }

                    UserIdentification::query()
                        ->with(['user', 'identificationType'])
                        ->where('identification_type_id', $configuration->identification_type_id)
                        ->whereIn('user_id', $memberUserIds)
                        ->whereNotNull('expires_at')
                        ->chunkById(100, function (Collection $identifications) use ($configuration, $reminderDays, $today, &$sent): void {
                            foreach ($identifications as $identification) {
                                foreach ($reminderDays as $days) {
                                    if (! $identification->expires_at->isSameDay($today->copy()->addDays($days))) {
                                        continue;
                                    }

                                    $sent += $this->notifyIdentificationExpiry(
                                        $identification->user,
                                        $configuration,
                                        $identification,
                                        $days,
                                    ) ? 1 : 0;
                                }
                            }
                        });
                }
            });

        return $sent;
    }

    private function notifySubscription(
        ?User $user,
        Organization $organization,
        MemberPaymentObligation $obligation,
        string $kind,
        int $days,
    ): bool {
        if ($user === null) {
            return false;
        }

        $overdue = $kind === 'overdue';

        return $this->notifications->notifyOnce(
            organization: $organization,
            user: $user,
            event: $overdue ? 'member.payment.overdue' : 'member.payment.due',
            title: $overdue ? 'Member payment overdue' : 'Member payment due',
            body: $overdue
                ? "Your member payment due on {$obligation->due_at->toDateString()} is overdue."
                : "Your member payment is due on {$obligation->due_at->toDateString()}.",
            dedupeKey: "member-payment:{$obligation->id}:{$kind}:{$days}",
            data: [
                'member_id' => $obligation->member_id,
                'member_payment_obligation_id' => $obligation->id,
                'due_at' => $obligation->due_at->toDateString(),
                'reminder_days' => $days,
            ],
            sourceModule: 'Member',
        ) !== null;
    }

    private function notifyIdentificationExpiry(
        ?User $user,
        OrganizationIdentification $configuration,
        UserIdentification $identification,
        int $days,
    ): bool {
        if ($user === null) {
            return false;
        }

        $typeName = $identification->identificationType?->name ?? 'Identification';

        return $this->notifications->notifyOnce(
            organization: $configuration->organization,
            user: $user,
            event: 'member.identification.expiry',
            title: "{$typeName} expiry reminder",
            body: "Your {$typeName} expires on {$identification->expires_at->toDateString()}.",
            dedupeKey: "identification:{$identification->id}:expiry:{$days}",
            data: [
                'user_identification_id' => $identification->id,
                'identification_type_id' => $configuration->identification_type_id,
                'identification_type_code' => $identification->identificationType?->code,
                'expires_at' => $identification->expires_at->toDateString(),
                'reminder_days' => $days,
            ],
            sourceModule: 'Member',
        ) !== null;
    }
}
