<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\SpouseRelationshipRequest;

class SpouseRelationshipRequestController extends Controller
{
    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = Member::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'different:own_member_id'],
        ]);
        $target = Member::query()
            ->where('organization_id', $organization->id)
            ->with('user')
            ->findOrFail((int) $validated['member_id']);

        abort_if((int) $target->id === (int) $member->id, 422, 'You cannot select yourself as your spouse.');
        $this->ensureNoActiveSpouse($member->id);
        $this->ensureNoActiveSpouse($target->id);

        $spouseRequest = SpouseRelationshipRequest::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'requester_member_id' => $member->id,
                'target_member_id' => $target->id,
                'status' => 'pending',
            ],
            ['metadata' => ['source' => 'member_profile']],
        );

        if ($spouseRequest->wasRecentlyCreated && $target->user) {
            $target->user->notify(new \Modules\Member\Notifications\SpouseRelationshipRequested($spouseRequest));
        }

        return redirect()->route('organization.member.profile')->with('status', 'Spouse request sent. The relationship will be created only after the other member accepts.');
    }

    public function respond(
        Request $request,
        int $spouseRequest,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        $member = Member::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $spouseRequest = SpouseRelationshipRequest::query()
            ->where('organization_id', $organization->id)
            ->where('target_member_id', $member->id)
            ->where('status', 'pending')
            ->findOrFail($spouseRequest);

        DB::transaction(function () use ($spouseRequest, $member, $request, $validated): void {
            $spouseRequest->refresh();
            abort_unless($spouseRequest->status === 'pending', 409);

            if ($validated['decision'] === 'accept') {
                $this->ensureNoActiveSpouse($member->id);
            }

            $spouseRequest->update([
                'status' => $validated['decision'] === 'accept' ? 'accepted' : 'declined',
                'responded_by_user_id' => $request->user()->id,
                'responded_at' => now(),
            ]);

            if ($validated['decision'] !== 'accept') {
                return;
            }

            $application = $spouseRequest->application;
            $requesterMember = $spouseRequest->requester_member_id
                ? Member::query()->where('organization_id', $spouseRequest->organization_id)->find($spouseRequest->requester_member_id)
                : ($application?->member_id
                    ? Member::query()->where('organization_id', $spouseRequest->organization_id)->find($application->member_id)
                    : null);

            // A new applicant does not have a persistent member record until confirmation.
            // Keep the accepted request; the confirmation workflow creates the link then.
            if ($requesterMember !== null) {
                $this->ensureNoActiveSpouse($requesterMember->id);
                $this->createSymmetricSpouseRelationship($requesterMember, $member);
            }
        });

        return redirect()->route('organization.member.profile')
            ->with('status', $validated['decision'] === 'accept'
                ? 'Spouse request accepted. The relationship will be linked when the applicant becomes a member, if not already linked.'
                : 'Spouse request declined. No relationship was created.');
    }

    public function cancel(Request $request, int $spouseRequest, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $record = SpouseRelationshipRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'pending')
            ->whereKey($spouseRequest)
            ->firstOrFail();

        $isRequesterMember = $record->requester_member_id !== null
            && Member::query()->whereKey($record->requester_member_id)->where('user_id', $request->user()->id)->exists();
        $isApplicant = $record->application !== null && (int) $record->application->user_id === (int) $request->user()->id;
        abort_unless($isRequesterMember || $isApplicant, 403);

        $record->update([
            'status' => 'cancelled',
            'responded_by_user_id' => $request->user()->id,
            'responded_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Pending spouse request cancelled. You can submit a corrected request.');
    }

    public function disconnect(
        Request $request,
        int $memberId,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        $member = Member::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $relationship = MemberRelationship::query()
            ->where('relationship_type', 'spouse')
            ->whereNull('ended_at')
            ->where(function ($query) use ($member, $memberId): void {
                $query->where('member_id', $member->id)->where('related_member_id', $memberId)
                    ->orWhere('member_id', $memberId)->where('related_member_id', $member->id);
            })
            ->firstOrFail();

        DB::transaction(function () use ($member, $memberId): void {
            MemberRelationship::query()
                ->where('relationship_type', 'spouse')
                ->whereNull('ended_at')
                ->where(function ($query) use ($member, $memberId): void {
                    $query->where('member_id', $member->id)->where('related_member_id', $memberId)
                        ->orWhere('member_id', $memberId)->where('related_member_id', $member->id);
                })
                ->update(['ended_at' => now()->toDateString()]);
        });

        return redirect()->route('organization.member.profile')->with('status', 'Spouse relationship disconnected.');
    }

    private function ensureNoActiveSpouse(int $memberId): void
    {
        $hasSpouse = MemberRelationship::query()
            ->where('relationship_type', 'spouse')
            ->whereNull('ended_at')
            ->where(fn ($query) => $query->where('member_id', $memberId)->orWhere('related_member_id', $memberId))
            ->exists();

        abort_if($hasSpouse, 422, 'One of the selected members already has an active spouse relationship.');
    }

    private function createSymmetricSpouseRelationship(Member $left, Member $right): void
    {
        abort_if($left->organization_id !== $right->organization_id || $left->id === $right->id, 422);
        foreach ([[$left->id, $right->id], [$right->id, $left->id]] as [$memberId, $relatedId]) {
            $relationship = MemberRelationship::query()->firstOrCreate(
                ['member_id' => $memberId, 'related_member_id' => $relatedId, 'relationship_type' => 'spouse'],
                ['started_at' => now()->toDateString(), 'metadata' => ['source' => 'spouse_request']],
            );
            if ($relationship->ended_at !== null) {
                $relationship->update(['started_at' => now()->toDateString(), 'ended_at' => null, 'metadata' => ['source' => 'spouse_request']]);
            }
        }
    }
}
