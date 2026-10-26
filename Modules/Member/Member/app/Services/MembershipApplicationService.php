<?php

namespace Modules\Member\Services;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
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
        ?int $membershipTypeId,
    ): MembershipApplication {
        /*
         * Lock the active organization membership first. This serializes
         * application start/resume requests for the same user and organization,
         * including the case where no application row exists yet.
         */
        [$application, $clearDocuments] = DB::transaction(function () use (
            $organization,
            $userId,
            $membershipTypeId,
        ): array {
            $organizationMembership = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            $isPendingSpouseApplicant = $organizationMembership?->status === 'pending'
                && data_get($organizationMembership->metadata, 'source') === 'spouse_membership_application';

            if (! $organizationMembership || ($organizationMembership->status !== 'active' && ! $isPendingSpouseApplicant)) {
                throw ValidationException::withMessages([
                    'user_id' => 'The selected user does not have an active membership in the selected organization.',
                ]);
            }

            $membershipType = $membershipTypeId === null ? null : $this->membershipType(
                organization: $organization,
                membershipTypeId: $membershipTypeId,
            );

            $this->ensureUserIsNotAlreadyMember(
                organization: $organization,
                userId: $userId,
            );

            $application = MembershipApplication::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $userId)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($application?->status === MembershipApplicationStatus::DRAFT) {
                return [$application, false];
            }

            if (
                $application?->status === MembershipApplicationStatus::REJECTED
                && $application->latestRejectionAllowsReapply()
            ) {
                $application->update([
                    'status' => MembershipApplicationStatus::DRAFT,
                    'membership_type_id' => $membershipType?->id,
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

                return [$application->refresh(), true];
            }

            if ($application !== null) {
                throw ValidationException::withMessages([
                    'application' => 'An active membership application already exists for this user in this organization.',
                ]);
            }

            return [
                MembershipApplication::create([
                    'organization_id' => $organization->id,
                    'user_id' => $userId,
                    'membership_type_id' => $membershipType?->id,
                    'status' => MembershipApplicationStatus::DRAFT,
                    'current_step' => 1,
                    'completed_steps' => [],
                    'data' => [],
                ]),
                false,
            ];
        });

        /*
         * Media-library file deletion is an external side effect and cannot be
         * reliably rolled back with the database transaction. Clear the prior
         * submission's files only after the draft reset commits successfully.
         */
        if ($clearDocuments) {
            $application->clearMediaCollection('application-documents');
        }

        return $application->refresh();
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
        ?int $membershipTypeId,
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
            $membershipType = $membershipTypeId === null ? null : $this->membershipType(
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
                'membership_type_id' => $membershipType?->id,
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

            $application = $application->refresh()->load([
                'membershipType',
                'statusHistories.actor',
            ]);

            DB::afterCommit(function () use ($application): void {
                event(new MembershipApplicationSubmitted($application));
            });

            return $application;
        });
    }

    private function membershipType(
        Organization $organization,
        ?int $membershipTypeId,
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
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->first();
        $isPendingSpouseApplicant = $membership?->status === 'pending'
            && data_get($membership->metadata, 'source') === 'spouse_membership_application';

        if ($membership === null || ($membership->status !== 'active' && ! $isPendingSpouseApplicant)) {
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
