<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Models\Organization;
use Modules\Member\Http\Requests\StoreMemberDependantRequest;
use Modules\Member\Http\Requests\UpdateMemberDependantRequest;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Services\MemberDependantService;
use Modules\Member\Transformers\MemberDependantResource;

class MemberDependantController extends Controller
{
    public function __construct(
        private readonly MemberDependantService $dependantService,
    ) {}

    public function index(
        Organization $organization,
        Member $member,
    ): JsonResponse {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $dependants = $member->dependants()->get();

        return response()->json([
            'data' => MemberDependantResource::collection($dependants),
        ]);
    }

    public function store(
        StoreMemberDependantRequest $request,
        Organization $organization,
        Member $member,
    ): JsonResponse {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $dependant = $this->dependantService->create(
            organizationId: $organization->id,
            data: $request->validated(),
        );

        return (new MemberDependantResource($dependant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ): MemberDependantResource {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $this->ensureDependantBelongsToOrganization(
            $organization,
            $dependant
        );

        $this->ensureMemberOwnsDependant(
            $member,
            $dependant
        );

        return new MemberDependantResource($dependant);
    }

    public function update(
        UpdateMemberDependantRequest $request,
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ): MemberDependantResource {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $this->ensureDependantBelongsToOrganization(
            $organization,
            $dependant
        );

        $this->ensureMemberOwnsDependant(
            $member,
            $dependant
        );

        $dependant = $this->dependantService->update(
            dependant: $dependant,
            data: $request->validated(),
        );

        return new MemberDependantResource($dependant);
    }

    public function convert(
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ): JsonResponse {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        if ($dependant->organization_id !== $organization->id) {
            abort(404);
        }

        try {
            $dependant = $this->dependantService->markAsConverted(
                dependant: $dependant,
                member: $member,
            );
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new MemberDependantResource($dependant))
            ->response()
            ->setStatusCode(200);
    }

    private function ensureMemberBelongsToOrganization(
        Organization $organization,
        Member $member,
    ): void {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function ensureDependantBelongsToOrganization(
        Organization $organization,
        MemberDependant $dependant,
    ): void {
        if ($dependant->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function ensureMemberOwnsDependant(
        Member $member,
        MemberDependant $dependant,
    ): void {
        $exists = $member->dependants()
            ->where('member_dependants.id', $dependant->id)
            ->exists();

        if (! $exists) {
            abort(404);
        }
    }
}