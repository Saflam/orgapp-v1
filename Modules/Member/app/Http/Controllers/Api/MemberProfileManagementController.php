<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Services\MemberProfileSectionExtensionRegistry;

class MemberProfileManagementController extends Controller
{
    public function membership(
        Organization $organization,
        Member $member,
    ): JsonResponse {
        $this->ensureMember($organization, $member);

        $membership = $member->memberships()
            ->with('membershipType')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'membership_number' => $member->membership_number,
                'current' => $membership ? [
                    'id' => $membership->id,
                    'membership_type' => $membership->membershipType?->name,
                    'status' => $membership->status?->value ?? $membership->status,
                    'status_reason' => $membership->status_reason,
                    'starts_at' => $membership->starts_at?->toDateString(),
                    'ends_at' => $membership->ends_at?->toDateString(),
                ] : null,
            ],
        ]);
    }

    public function membershipHistory(
        Organization $organization,
        Member $member,
    ): JsonResponse {
        $this->ensureMember($organization, $member);

        $history = $member->memberships()
            ->with('membershipType')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'membership_type_id' => $membership->membership_type_id,
                'membership_type' => $membership->membershipType?->name,
                'status' => $membership->status?->value ?? $membership->status,
                'status_reason' => $membership->status_reason,
                'starts_at' => $membership->starts_at?->toDateString(),
                'ends_at' => $membership->ends_at?->toDateString(),
            ]);

        return response()->json(['data' => $history]);
    }

    public function updateMembership(
        Request $request,
        Organization $organization,
        Member $member,
    ): JsonResponse {
        $this->ensureMember($organization, $member);

        $validated = $request->validate([
            'membership_number' => [
                'required', 'string', 'max:255',
                Rule::unique('members', 'membership_number')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id))
                    ->ignore($member->id),
            ],
            'status' => ['sometimes', 'required', Rule::enum(MembershipStatus::class)],
            'status_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $membership = $member->memberships()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if (array_key_exists('status', $validated) && ! $membership) {
            return response()->json([
                'message' => 'A membership record is required before its status can be changed.',
            ], 422);
        }

        if (
            in_array($validated['status'] ?? null, [
                MembershipStatus::SUSPENDED->value,
                MembershipStatus::DECEASED->value,
            ], true)
            && trim((string) ($validated['status_reason'] ?? '')) === ''
        ) {
            return response()->json([
                'message' => 'A reason is required for suspended or deceased status.',
                'errors' => ['status_reason' => ['A reason is required for this status.']],
            ], 422);
        }

        DB::transaction(function () use ($member, $membership, $validated): void {
            $member->membership_number = trim($validated['membership_number']);
            $member->save();

            if ($membership && array_key_exists('status', $validated)) {
                $membership->status = $validated['status'];
                $membership->status_reason = trim((string) ($validated['status_reason'] ?? '')) ?: null;
                $membership->save();
            }
        });

        return $this->membership($organization, $member->fresh());
    }

    public function updateModuleProfile(
        Request $request,
        Organization $organization,
        Member $member,
        string $module,
        MemberProfileSectionExtensionRegistry $extensions,
    ): JsonResponse {
        $this->ensureMember($organization, $member);
        $extensions->update($module, $request, $organization, $member);

        return response()->json([
            'message' => ucfirst($module).' profile details updated successfully.',
        ]);
    }

    private function ensureMember(Organization $organization, Member $member): void
    {
        abort_unless((int) $member->organization_id === (int) $organization->id, 404);
    }
}
