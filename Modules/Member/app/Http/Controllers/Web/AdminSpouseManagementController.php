<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\SpouseMembershipInvitation;
use Modules\Member\Models\SpouseRelationshipRequest;
use Modules\Member\Notifications\SpouseMembershipInvitationNotification;
use Modules\Member\Notifications\SpouseRelationshipRequested;

class AdminSpouseManagementController
{
    public function invite(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        $memberRecord = $this->authorizedMember($member, $organization->id, $authorization);
        $validated = $request->validate([
            'invitee_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->ensureNoActiveSpouse($memberRecord->id);

        $email = mb_strtolower(trim($validated['email']));
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser && Member::query()->where('organization_id', $organization->id)->where('user_id', $existingUser->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the organization. Please use the existing-member option instead.',
            ]);
        }

        $pending = SpouseMembershipInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
        if ($pending) {
            throw ValidationException::withMessages(['email' => 'A valid spouse invitation has already been sent to this email address.']);
        }

        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($organization, $memberRecord, $validated, $email, $existingUser, $plainToken): SpouseMembershipInvitation {
            $user = $existingUser ?? User::query()->create([
                'name' => trim($validated['invitee_name']),
                'email' => $email,
                'password' => null,
            ]);

            OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                [
                    'status' => 'pending',
                    'joined_at' => now(),
                    'metadata' => ['source' => 'spouse_membership_application'],
                ],
            );

            return SpouseMembershipInvitation::query()->create([
                'organization_id' => $organization->id,
                'inviter_member_id' => $memberRecord->id,
                'invited_user_id' => $user->id,
                'email' => $email,
                'invitee_name' => trim($validated['invitee_name']),
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'metadata' => ['source' => 'admin_member_profile'],
            ]);
        });

        $invitation->load('inviterMember.user');
        Notification::route('mail', $email)->notify(new SpouseMembershipInvitationNotification($invitation, $plainToken));

        return redirect()->route('member.profile', $memberRecord->id)
            ->with('status', 'Spouse invitation sent. The invited person can sign in with this email address and complete their application.');
    }

    public function requestExisting(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        $memberRecord = $this->authorizedMember($member, $organization->id, $authorization);
        $validated = $request->validate([
            'related_member_id' => ['required', 'integer', 'different:member_id', 'exists:members,id'],
        ]);

        $target = Member::query()
            ->where('organization_id', $organization->id)
            ->whereHas('memberships', fn ($query) => $query->where('status', 'active'))
            ->with('user')
            ->findOrFail((int) $validated['related_member_id']);

        abort_if((int) $target->id === (int) $memberRecord->id, 422, 'A member cannot be their own spouse.');
        $this->ensureNoActiveSpouse($memberRecord->id);
        $this->ensureNoActiveSpouse($target->id);

        $spouseRequest = SpouseRelationshipRequest::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'requester_member_id' => $memberRecord->id,
                'target_member_id' => $target->id,
                'status' => 'pending',
            ],
            ['metadata' => ['source' => 'admin_member_profile']],
        );

        if ($spouseRequest->wasRecentlyCreated && $target->user) {
            $target->user->notify(new SpouseRelationshipRequested($spouseRequest));
        }

        return redirect()->route('member.profile', $memberRecord->id)
            ->with('status', 'Spouse request sent. The other member must accept before the relationship becomes active.');
    }

    private function authorizedMember(int $memberId, int $organizationId, AuthorizationService $authorization): Member
    {
        $member = Member::query()->where('organization_id', $organizationId)->findOrFail($memberId);
        $canUpdate = $authorization->can(
            auth()->user(),
            'members.update',
            new AuthorizationContext($organizationId),
        );
        if (! $canUpdate && $member->unit_id !== null) {
            $canUpdate = $authorization->can(
                auth()->user(),
                'members.update',
                new AuthorizationContext($organizationId, (int) $member->unit_id),
            );
        }
        abort_unless($canUpdate, 403);

        return $member;
    }

    private function ensureNoActiveSpouse(int $memberId): void
    {
        $hasSpouse = MemberRelationship::query()
            ->whereIn('relationship_type', ['spouse', 'spouse_of'])
            ->whereNull('ended_at')
            ->where(fn ($query) => $query->where('member_id', $memberId)->orWhere('related_member_id', $memberId))
            ->exists();

        $hasPendingRequest = SpouseRelationshipRequest::query()
            ->where('status', 'pending')
            ->where(fn ($query) => $query->where('requester_member_id', $memberId)->orWhere('target_member_id', $memberId))
            ->exists();

        $hasPendingInvitation = SpouseMembershipInvitation::query()
            ->where('inviter_member_id', $memberId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        abort_if(
            $hasSpouse || $hasPendingRequest || $hasPendingInvitation,
            422,
            'This member already has an active spouse relationship or a pending spouse request/invitation.',
        );
    }
}
