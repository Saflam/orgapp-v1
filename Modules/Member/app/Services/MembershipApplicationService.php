<?php

namespace Modules\Member\Services;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;

class MembershipApplicationService
{
    public function __construct(
        private MembershipApplicationDocumentService $documentService,
    ) {
    }

    public function startOrResume(
        Organization $organization,
        int $userId,
        int $membershipTypeId,
    ): MembershipApplication {
        $membershipType = $this->membershipType(
            organization: $organization,
            membershipTypeId: $membershipTypeId,
        );

        $this->ensureUserBelongsToOrganization(
            organization: $organization,
            userId: $userId,
        );

        $this->ensureUserIsNotAlreadyMember(
            organization: $organization,
            userId: $userId,
        );

        $application = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        if ($application?->status === MembershipApplicationStatus::DRAFT) {
            return $application;
        }

        if (
            $application?->status === MembershipApplicationStatus::REJECTED
            && $application->latestRejectionAllowsReapply()
        ) {
            $application->clearMediaCollection('application-documents');

            $application->update([
                'status' => MembershipApplicationStatus::DRAFT,
                'membership_type_id' => $membershipType->id,
                'current_step' => 1,
                'completed_steps' => [],
                'data' => [],
                'submitted_at' => null,
            ]);

            $application->statusHistories()->create([
                'from_status' => MembershipApplicationStatus::REJECTED->value,
                'to_status' => MembershipApplicationStatus::DRAFT->value,
                'actor_user_id' => auth()->id(),
                'notes' => 'Application reopened for revision and re-application.',
                'allow_reapply' => false,
                'metadata' => [],
            ]);

            return $application->refresh();
        }

        if ($application !== null) {
            throw ValidationException::withMessages([
                'application' => 'An active membership application already exists for this user in this organization.',
            ]);
        }

        return MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $userId,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 1,
            'completed_steps' => [],
            'data' => [],
        ]);
    }

    /**
     * Submit a completed application and preserve the submitted snapshot.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<int, mixed>> $identificationData
     */
    public function submit(
        Organization $organization,
        User $user,
        int $membershipTypeId,
        array $data,
        array $identificationData = [],
        int $stepCount = 0,
    ): MembershipApplication {
        return DB::transaction(function () use (
            $organization,
            $user,
            $membershipTypeId,
            $data,
            $identificationData,
            $stepCount,
        ): MembershipApplication {
            $membershipType = $this->membershipType(
                organization: $organization,
                membershipTypeId: $membershipTypeId,
            );

            $this->ensureUserBelongsToOrganization(
                organization: $organization,
                userId: $user->id,
            );

            $this->ensureUserIsNotAlreadyMember(
                organization: $organization,
                userId: $user->id,
            );

            $application = MembershipApplication::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->where('status', MembershipApplicationStatus::DRAFT)
                ->latest('id')
                ->first();

            if (! $application) {
                throw ValidationException::withMessages([
                    'application' => 'Start a membership application before submitting it.',
                ]);
            }

            $application->update([
                'membership_type_id' => $membershipType->id,
                'status' => MembershipApplicationStatus::SUBMITTED,
                'current_step' => $stepCount > 0 ? $stepCount : $application->current_step,
                'completed_steps' => $stepCount > 0
                    ? range(1, $stepCount)
                    : ($application->completed_steps ?? []),
                'data' => $data,
                'submitted_at' => now(),
            ]);

            foreach ($identificationData as $identification) {
                $documents = $identification['documents'] ?? [];

                if ($documents !== []) {
                    $this->documentService->saveMany(
                        application: $application,
                        identificationTypeId: (int) $identification['identification_type_id'],
                        documents: $documents,
                    );
                }
            }

            $application->statusHistories()->create([
                'from_status' => MembershipApplicationStatus::DRAFT->value,
                'to_status' => MembershipApplicationStatus::SUBMITTED->value,
                'actor_user_id' => $user->id,
                'notes' => null,
                'allow_reapply' => false,
                'metadata' => [],
            ]);

            return $application->refresh()->load([
                'membershipType',
                'statusHistories.actor',
            ]);
        });
    }

    private function membershipType(
        Organization $organization,
        int $membershipTypeId,
    ): MembershipType {
        $membershipType = MembershipType::query()
            ->whereKey($membershipTypeId)
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        if (! $membershipType) {
            throw ValidationException::withMessages([
                'membership_type_id' =>
                    'The selected membership type is not available for this organization.',
            ]);
        }

        return $membershipType;
    }

    private function ensureUserBelongsToOrganization(
        Organization $organization,
        int $userId,
    ): void {
        $exists = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user does not have an active membership in the selected organization.',
            ]);
        }
    }

    private function ensureUserIsNotAlreadyMember(
        Organization $organization,
        int $userId,
    ): void {
        $exists = Member::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is already a member of this organization.',
            ]);
        }
    }
}
