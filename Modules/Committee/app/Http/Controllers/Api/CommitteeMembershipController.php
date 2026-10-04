<?php

namespace Modules\Committee\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Committee\Http\Requests\ChangeCommitteeMembershipDesignationRequest;
use Modules\Committee\Http\Requests\UpdateCommitteeMembershipRequest;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Services\CommitteeMembershipService;
use Modules\Committee\Transformers\CommitteeMembershipResource;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Modules\Committee\Models\Designation;

class CommitteeMembershipController extends Controller
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
        private readonly CommitteeMembershipService $membershipService,
    ) {}

    public function index(
        Organization $organization,
        CommitteeTerm $committeeTerm,
    ): AnonymousResourceCollection {
        $this->ensureCommitteeModuleEnabled($organization);

        abort_unless(
            $committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee term not found.'
        );

        $memberships = CommitteeMembership::query()
            ->where('committee_term_id', $committeeTerm->id)
            ->with([
                'member',
                'designation',
            ])
            ->orderBy('id')
            ->get();

        return CommitteeMembershipResource::collection($memberships);
    }

    public function effective(
        Request $request,
        Organization $organization,
        CommitteeTerm $committeeTerm,
    ): AnonymousResourceCollection {
        $this->ensureCommitteeModuleEnabled($organization);

        abort_unless(
            $committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee term not found.'
        );

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today();

        $memberships = $this->membershipService->getEffectiveMemberships(
            term: $committeeTerm,
            date: $date,
        );

        return CommitteeMembershipResource::collection($memberships);
    }

    public function store(
        Request $request,
        Organization $organization,
        CommitteeTerm $committeeTerm,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization);

        abort_unless(
            $committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee term not found.'
        );

        $validated = $request->validate([
            'member_id' => ['required', 'integer'],
            'designation_id' => ['required', 'integer'],
        ]);

        $member = Member::query()->findOrFail(
            $validated['member_id']
        );

        $designation = Designation::query()->findOrFail(
            $validated['designation_id']
        );

        $membership = $this->membershipService->addMember(
            term: $committeeTerm,
            member: $member,
            designation: $designation,
        );

        $membership->load([
            'member',
            'designation',
        ]);

        return (new CommitteeMembershipResource($membership))->response()->setStatusCode(201);
    }

    public function end(
        Organization $organization,
        CommitteeMembership $membership,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization);

        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        abort_unless(
            $membership->committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee membership not found.'
        );

        $membership = $this->membershipService->end(
            membership: $membership,
        );

        $membership->load([
            'member',
            'designation',
        ]);

        return (new CommitteeMembershipResource($membership))
            ->response()
            ->setStatusCode(200);
    }

    public function cancel(
        Organization $organization,
        CommitteeMembership $membership,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization);

        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        abort_unless(
            $membership->committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee membership not found.'
        );

        $membership = $this->membershipService->cancel(
            membership: $membership,
        );

        $membership->load([
            'member',
            'designation',
        ]);

        return (new CommitteeMembershipResource($membership))
            ->response()
            ->setStatusCode(200);
    }

    public function update(
        UpdateCommitteeMembershipRequest $request,
        Organization $organization,
        CommitteeMembership $membership,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization);

        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        abort_unless(
            $membership->committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee membership not found.'
        );

        $membership = $this->membershipService->update(
            membership: $membership,
            designationId: $request->integer('designation_id'),
        );

        $membership->load([
            'member',
            'designation',
        ]);

        return (new CommitteeMembershipResource($membership))
            ->response()
            ->setStatusCode(200);
    }

    public function changeDesignation(
        ChangeCommitteeMembershipDesignationRequest $request,
        Organization $organization,
        CommitteeMembership $membership,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization);

        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        abort_unless(
            $membership->committeeTerm->committee->organization_id === $organization->id,
            404,
            'Committee membership not found.'
        );

        $designation = Designation::query()->findOrFail(
            $request->integer('designation_id')
        );

        $membership = $this->membershipService->changeDesignation(
            membership: $membership,
            newDesignation: $designation,
            effectiveDate: Carbon::parse(
                $request->string('effective_date')->toString()
            ),
        );

        $membership->load([
            'member',
            'designation',
        ]);

        return (new CommitteeMembershipResource($membership))
            ->response()
            ->setStatusCode(201);
    }

    private function ensureCommitteeModuleEnabled(
        Organization $organization,
    ): void {
        if (! $this->moduleService->isEnabled(
            $organization,
            'Committee',
        )) {
            abort(
                403,
                'The Committee module is not enabled for this organization.'
            );
        }
    }
}