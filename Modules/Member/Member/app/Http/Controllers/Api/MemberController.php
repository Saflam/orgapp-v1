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
        Request $request,
        Organization $organization,
    ): JsonResponse {
        $search = trim((string) $request->query('search', ''));

        $query = Member::query()
            ->where('organization_id', $organization->id)
            ->with([
                'user.details',
                'unit',
            ]);

        if ($search !== '') {
            $query->where(function ($members) use ($search): void {
                // A numeric-only search means the four-digit sequence, regardless
                // of the membership-number prefix (e.g. SDA-0123 and SDB-0123).
                if (preg_match('/^\d{1,4}$/', $search) === 1) {
                    $sequence = str_pad($search, 4, '0', STR_PAD_LEFT);

                    $members->where('membership_number', $search)
                        ->orWhere('membership_number', $sequence)
                        ->orWhere('membership_number', 'like', '%-' . $sequence);

                    return;
                }

                // A complete prefixed number is matched exactly. Other search
                // terms can match a member's name, email, phone, or number.
                if (preg_match('/^[A-Za-z]+-\d{4}$/', $search) === 1) {
                    $members->where('membership_number', $search);

                    return;
                }

                $members->where('membership_number', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        $members = $query
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