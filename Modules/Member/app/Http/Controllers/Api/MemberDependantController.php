<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $dependants = $member->dependants()
            ->wherePivotNull('ended_at')
            ->with('media')
            ->get();

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

    public function destroy(
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ): JsonResponse {
        $this->ensureMemberBelongsToOrganization($organization, $member);
        $this->ensureDependantBelongsToOrganization($organization, $dependant);
        $this->ensureMemberOwnsDependant($member, $dependant);

        if ($dependant->converted_member_id !== null) {
            return response()->json([
                'message' => 'A dependant who has become a member cannot be removed here.',
            ], 422);
        }

        $relationship = $member->dependantRelationships()
            ->where('dependant_id', $dependant->id)
            ->whereNull('ended_at')
            ->first();

        if ($relationship) {
            $relationship->update(['ended_at' => now()->toDateString()]);
        }

        return response()->json(['message' => 'Dependant removed from your active family list.']);
    }

    public function uploadPhoto(
        Request $request,
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ): JsonResponse {
        $this->ensureMemberBelongsToOrganization($organization, $member);
        $this->ensureDependantBelongsToOrganization($organization, $dependant);
        $this->ensureMemberOwnsDependant($member, $dependant);

        if ($dependant->converted_member_id !== null) {
            return response()->json([
                'message' => 'A converted dependant cannot be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $media = $dependant
            ->addMedia($validated['profile_photo'])
            ->toMediaCollection('profile-photo');

        return response()->json([
            'message' => 'Dependant profile photo updated successfully.',
            'data' => [
                'dependant_id' => $dependant->id,
                'profile_photo_url' => route('member-dependant.photo', [
                    'organization' => $organization->id,
                    'member' => $member->id,
                    'dependant' => $dependant->id,
                ]),
                'media_id' => $media->id,
            ],
        ]);
    }

    public function photo(
        Organization $organization,
        Member $member,
        MemberDependant $dependant,
    ) {
        $this->ensureMemberBelongsToOrganization($organization, $member);
        $this->ensureDependantBelongsToOrganization($organization, $dependant);
        $this->ensureMemberOwnsDependant($member, $dependant);

        abort_unless(
            $member->dependants()->wherePivotNull('ended_at')
                ->where('member_dependants.id', $dependant->id)->exists(),
            404
        );

        $media = $dependant->getFirstMedia('profile-photo');
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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