<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Models\Organization;
use Modules\Member\Http\Requests\EndMemberDependantRelationshipRequest;
use Modules\Member\Http\Requests\StoreMemberDependantRelationshipRequest;
use Modules\Member\Http\Requests\UpdateMemberDependantRelationshipRequest;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Models\MemberDependantRelationship;
use Modules\Member\Services\MemberDependantRelationshipService;
use Modules\Member\Transformers\MemberDependantRelationshipResource;

class MemberDependantRelationshipController extends Controller
{
    public function __construct(
        private readonly MemberDependantRelationshipService $relationshipService,
    ) {}

    public function index(
        Organization $organization,
        Member $member,
    ): AnonymousResourceCollection {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        $relationships = $member->dependantRelationships()->with('dependant')->get();

        return MemberDependantRelationshipResource::collection(
            $relationships
        );
    }

    public function show(
        Organization $organization,
        Member $member,
        MemberDependantRelationship $relationship,
    ): JsonResponse {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        if ($relationship->member_id !== $member->id) {
            abort(404);
        }

        $relationship->load('dependant');

        return (new MemberDependantRelationshipResource($relationship))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Store a newly created dependant relationship.
     */
    public function store(
        StoreMemberDependantRelationshipRequest $request,
        Organization $organization,
        Member $member,
    ): JsonResponse {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        $dependant = MemberDependant::query()->findOrFail(
            $request->validated('dependant_id')
        );

        if ($dependant->organization_id !== $organization->id) {
            abort(404);
        }

        $relationship = $this->relationshipService->add(
            member: $member,
            dependant: $dependant,
            relationshipType: $request->validated('relationship_type'),
            startedAt: $request->validated('started_at'),
        );

        return (new MemberDependantRelationshipResource($relationship))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMemberDependantRelationshipRequest $request,
        Organization $organization,
        Member $member,
        MemberDependantRelationship $relationship,
    ): JsonResponse {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        if ($relationship->member_id !== $member->id) {
            abort(404);
        }

        $relationship = $this->relationshipService->update(
            relationship: $relationship,
            relationshipType: $request->validated('relationship_type'),
            startedAt: $request->validated('started_at'),
            endedAt: $request->validated('ended_at'),
        );

        return (new MemberDependantRelationshipResource($relationship))
            ->response()
            ->setStatusCode(200);
    }

    public function end(
        EndMemberDependantRelationshipRequest $request,
        Organization $organization,
        Member $member,
        MemberDependantRelationship $relationship,
    ): JsonResponse {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        if ($relationship->member_id !== $member->id) {
            abort(404);
        }

        try {
            $relationship = $this->relationshipService->end(
                relationship: $relationship,
                endedAt: $request->validated('ended_at'),
            );
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new MemberDependantRelationshipResource($relationship))
            ->response()
            ->setStatusCode(200);
    }
}