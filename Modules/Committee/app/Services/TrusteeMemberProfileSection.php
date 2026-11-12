<?php

namespace Modules\Committee\Services;

use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Contracts\MemberProfileSectionExtension;
use Modules\Member\Models\Member;
use Modules\Member\Enums\MembershipStatus;
use Modules\Committee\Models\Trustee;

class TrusteeMemberProfileSection implements MemberProfileSectionExtension
{
    public function module(): string
    {
        return 'Committee';
    }

    public function title(): string
    {
        return 'Trustee Details';
    }

    public function view(): string
    {
        return 'committee::organization.member-profile-trustee-section';
    }

    public function editView(): string
    {
        return '';
    }

    public function data(Organization $organization, Member $member): ?array
    {
        $authorization = app(AuthorizationService::class);
        $context = new AuthorizationContext((int) $organization->id);
        $user = auth()->user();

        $canView = $user && (
            $authorization->can($user, 'trustee.view', $context)
            || $authorization->can($user, 'trustee.manage', $context)
        );
        if (! $canView) {
            return null;
        }

        $trustee = Trustee::query()
            ->where('organization_id', $organization->id)
            ->where('member_id', $member->id)
            ->with('appointments.committee')
            ->first();

        $appointments = $trustee?->appointments ?? collect();
        $activeAppointment = $appointments->first(
            fn ($appointment) => $appointment->status->value === 'active'
        );
        $latestMembership = $member->memberships()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
        $membershipStatus = $latestMembership?->status instanceof \BackedEnum
            ? $latestMembership->status->value
            : (string) ($latestMembership?->status ?? '');

        return [
            'trusteeRecord' => $trustee,
            'trusteeAppointmentHistory' => $appointments,
            'activeTrusteeAppointment' => $activeAppointment,
            'memberEligibleForTrustee' => $membershipStatus === MembershipStatus::ACTIVE->value,
            'canManageTrustees' => $authorization->can($user, 'trustee.manage', $context),
        ];
    }

    public function update(\Illuminate\Http\Request $request, Organization $organization, Member $member): void
    {
        abort(404);
    }
}
