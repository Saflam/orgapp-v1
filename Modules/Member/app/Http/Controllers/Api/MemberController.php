<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Core\Models\Organization;
use Modules\Member\Http\Requests\StoreMemberRequest;
use Modules\Member\Http\Requests\UpdateMemberProfileRequest;
use Modules\Member\Http\Requests\UpdateMemberRequest;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberProfileService;
use Modules\Member\Services\MemberService;
use Modules\Member\Transformers\MemberProfileResource;
use Modules\Member\Transformers\MemberResource;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
        private readonly MemberProfileService $memberProfileService,
    ) {
    }

    public function index(
        Organization $organization,
    ): JsonResponse {
        $members = Member::query()
            ->where('organization_id', $organization->id)
            ->with([
                'user.details',
                'unit',
            ])
            ->orderBy('membership_number')
            ->get();

        return response()->json([
            'data' => MemberResource::collection($members),
        ]);
    }

    public function store(
        StoreMemberRequest $request,
        Organization $organization,
    ): JsonResponse {
        $member = $this->memberService->create([
            ...$request->validated(),
            'organization_id' => $organization->id,
        ]);

        $member->load([
            'user.details',
            'unit',
        ]);

        return (new MemberResource($member))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Organization $organization,
        Member $member,
    ): MemberResource {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $member->load([
            'user.details',
            'unit',
        ]);

        return new MemberResource($member);
    }

    public function update(
        UpdateMemberRequest $request,
        Organization $organization,
        Member $member,
    ): MemberResource {
        $this->ensureMemberBelongsToOrganization(
            $organization,
            $member
        );

        $member = $this->memberService->update(
            member: $member,
            data: $request->validated(),
        );

        $member->load([
            'user.details',
            'unit',
        ]);

        return new MemberResource($member);
    }

    public function profile(
        Request $request,
        Organization $organization,
    ): MemberProfileResource {
        $member = $this->memberProfileService->findForOrganization(
            organizationId: $organization->id,
            userId: $request->user()->id,
        );

        return new MemberProfileResource($member);
    }

    public function updateProfile(
        UpdateMemberProfileRequest $request,
        Organization $organization,
    ): MemberProfileResource {
        $member = $this->memberProfileService->findForOrganization(
            organizationId: $organization->id,
            userId: $request->user()->id,
        );

        $member = $this->memberProfileService->update(
            member: $member,
            data: $request->validated(),
        );

        return new MemberProfileResource($member);
    }

    private function ensureMemberBelongsToOrganization(
        Organization $organization,
        Member $member,
    ): void {
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }
    }
}