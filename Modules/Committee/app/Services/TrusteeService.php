<?php

namespace Modules\Committee\Services;

use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\TrusteeAppointmentStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\Trustee;
use Modules\Committee\Models\TrusteeAppointment;
use Modules\Core\Models\OrganizationModule;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;

class TrusteeService
{
    public function appoint(
        Member $member,
        int $trusteeId,
        ?Carbon $appointedAt = null,
        ?int $actorId = null,
    ): TrusteeAppointment {
        return DB::transaction(function () use ($member, $trusteeId, $appointedAt, $actorId): TrusteeAppointment {
            if ($trusteeId < 1) {
                throw new DomainException('Trustee ID must be a positive number.');
            }

            $member = Member::query()->with('memberships')->lockForUpdate()->findOrFail($member->id);
            $this->ensureCommitteeModuleEnabled((int) $member->organization_id);
            $this->ensureMemberIsActive($member);

            $committee = $this->ensureTrusteeCommittee((int) $member->organization_id);
            $trustee = Trustee::query()
                ->where('organization_id', $member->organization_id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($trustee && (int) $trustee->trustee_id !== $trusteeId) {
                throw new DomainException('This member already has a Trustee ID. The original ID must be retained.');
            }

            if (! $trustee) {
                $duplicate = Trustee::query()
                    ->where('organization_id', $member->organization_id)
                    ->where('trustee_id', $trusteeId)
                    ->exists();

                if ($duplicate) {
                    throw new DomainException('This Trustee ID is already assigned within this organization.');
                }

                $trustee = Trustee::create([
                    'organization_id' => $member->organization_id,
                    'member_id' => $member->id,
                    'trustee_id' => $trusteeId,
                ]);
            }

            $activeAppointment = $trustee->activeAppointment()->lockForUpdate()->first();
            if ($activeAppointment) {
                throw new DomainException('This member is already an active trustee.');
            }

            return TrusteeAppointment::create([
                'trustee_id' => $trustee->id,
                'committee_id' => $committee->id,
                'member_id' => $member->id,
                'appointed_at' => ($appointedAt ?? now())->toDateString(),
                'status' => TrusteeAppointmentStatus::ACTIVE,
                'created_by' => $actorId,
            ]);
        });
    }

    public function remove(
        TrusteeAppointment $appointment,
        string $reason,
        ?int $actorId = null,
        ?Carbon $endedAt = null,
    ): TrusteeAppointment {
        return DB::transaction(function () use ($appointment, $reason, $actorId, $endedAt): TrusteeAppointment {
            $appointment = TrusteeAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== TrusteeAppointmentStatus::ACTIVE) {
                throw new DomainException('Only an active trustee appointment can be removed.');
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw new DomainException('A removal reason is required.');
            }

            $appointment->update([
                'status' => TrusteeAppointmentStatus::REMOVED,
                'ended_at' => ($endedAt ?? now())->toDateString(),
                'end_reason' => $reason,
                'ended_by' => $actorId,
            ]);

            return $appointment->refresh();
        });
    }

    /** Deactivate the current appointment when the member's latest membership is not active. */
    public function syncMemberStatus(Member $member): void
    {
        $latestMembership = $member->memberships()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($latestMembership?->status === MembershipStatus::ACTIVE || ! $latestMembership) {
            return;
        }

        $statusValue = $latestMembership->status instanceof MembershipStatus
            ? $latestMembership->status->value
            : (string) $latestMembership->status;

        TrusteeAppointment::query()
            ->where('member_id', $member->id)
            ->where('status', TrusteeAppointmentStatus::ACTIVE)
            ->update([
                'status' => TrusteeAppointmentStatus::INACTIVE->value,
                'ended_at' => now()->toDateString(),
                'end_reason' => 'Automatically deactivated because member status changed to ' . $statusValue . '.',
                'updated_at' => now(),
            ]);
    }

    private function ensureMemberIsActive(Member $member): void
    {
        $latestMembership = $member->memberships()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if (! $latestMembership || $latestMembership->status !== MembershipStatus::ACTIVE) {
            throw new DomainException('Only members with active membership can be appointed as trustees.');
        }
    }

    private function ensureCommitteeModuleEnabled(int $organizationId): void
    {
        $enabled = OrganizationModule::query()
            ->where('organization_id', $organizationId)
            ->where('module', 'Committee')
            ->where('is_enabled', true)
            ->exists();

        if (! $enabled) {
            throw new DomainException('The Committee module is not enabled for this organization.');
        }
    }

    private function ensureTrusteeCommittee(int $organizationId): Committee
    {
        $type = CommitteeType::firstOrCreate(
            ['organization_id' => $organizationId, 'code' => 'TRUSTEE'],
            [
                'name' => 'Trustees',
                'description' => 'System-defined permanent trustee committee.',
                'is_active' => true,
            ]
        );

        if (! $type->is_active) {
            $type->forceFill(['is_active' => true])->save();
        }

        return Committee::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'committee_type_id' => $type->id,
                'unit_id' => null,
                'parent_committee_id' => null,
            ],
            [
                'name' => 'Trustees',
                'kind' => CommitteeKind::RECURRING,
                'status' => CommitteeStatus::ACTIVE,
            ]
        );
    }
}
