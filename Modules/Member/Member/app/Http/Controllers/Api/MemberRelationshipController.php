<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Http\Requests\StoreMemberRelationshipRequest;
use Modules\Member\Http\Requests\UpdateMemberRelationshipRequest;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Services\MemberRelationshipService;
use Modules\Member\Transformers\MemberRelationshipResource;

class MemberRelationshipController extends Controller
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
        private readonly MemberRelationshipService $relationshipService,
    ) {}

    public function index(
        Organization $organization,
        int $member,
    ): AnonymousResourceCollection {
        $this->ensureMemberModuleEnabled($organization);

        $relationships = MemberRelationship::query()
            ->whereHas('member', function ($query) use ($organization, $member) {
                $query
                    ->where('id', $member)
                    ->where('organization_id', $organization->id);
            })
            ->with([
                'member',
                'relatedMember',
            ])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return MemberRelationshipResource::collection(
            $relationships
        );
    }

    public function store(
        StoreMemberRelationshipRequest $request,
        Organization $organization,
        int $member,
    ): JsonResponse {
        $this->ensureMemberModuleEnabled($organization);

        $memberModel = $organization
            ->members()
            ->findOrFail($member);

        $relatedMember = $organization
            ->members()
            ->findOrFail(
                $request->integer('related_member_id')
            );

        $relationship = $this->relationshipService->add(
            member: $memberModel,
            relatedMember: $relatedMember,
            relationshipType: $request->enum(
                'relationship_type',
                \Modules\Member\Enums\MemberRelationshipType::class
            ),
            startedAt: $request->filled('started_at')
                ? Carbon::parse($request->string('started_at')->toString())
                : null,
            metadata: $request->input('metadata'),
        );

        $relationship->load([
            'member',
            'relatedMember',
        ]);

        return (new MemberRelationshipResource($relationship))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMemberRelationshipRequest $request,
        Organization $organization,
        MemberRelationship $relationship,
    ): JsonResponse {
        $this->ensureMemberModuleEnabled($organization);

        $relationship->loadMissing([
            'member',
        ]);

        abort_unless(
            $relationship->member->organization_id === $organization->id,
            404,
            'Member relationship not found.'
        );

        $relatedMember = $organization
            ->members()
            ->findOrFail(
                $request->integer('related_member_id')
            );

        $relationship = $this->relationshipService->update(
            relationship: $relationship,
            relatedMember: $relatedMember,
            relationshipType: $request->enum(
                'relationship_type',
                \Modules\Member\Enums\MemberRelationshipType::class
            ),
            startedAt: $request->filled('started_at')
                ? Carbon::parse($request->string('started_at')->toString())
                : null,
            endedAt: $request->filled('ended_at')
                ? Carbon::parse($request->string('ended_at')->toString())
                : null,
            metadata: $request->input('metadata'),
        );

        $relationship->load([
            'member',
            'relatedMember',
        ]);

        return (new MemberRelationshipResource($relationship))
            ->response()
            ->setStatusCode(200);
    }

    public function end(
        Organization $organization,
        MemberRelationship $relationship,
    ): JsonResponse {
        $this->ensureMemberModuleEnabled($organization);

        $relationship->loadMissing([
            'member',
        ]);

        abort_unless(
            $relationship->member->organization_id === $organization->id,
            404,
            'Member relationship not found.'
        );

        $relationship = $this->relationshipService->end(
            relationship: $relationship,
        );

        $relationship->load([
            'member',
            'relatedMember',
        ]);

        return (new MemberRelationshipResource($relationship))
            ->response()
            ->setStatusCode(200);
    }

    private function ensureMemberModuleEnabled(
        Organization $organization,
    ): void {
        if (! $this->moduleService->isEnabled(
            $organization,
            'Member',
        )) {
            abort(
                403,
                'The Member module is not enabled for this organization.'
            );
        }
    }
}