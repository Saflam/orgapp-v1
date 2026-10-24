<?php

namespace Modules\Member\Services;

use App\Models\User;
use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationRejected;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;
use Modules\Member\Models\Member;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationWorkflowService
{
    public function __construct(
        private MemberRegistrationService $registrationService,
        private MembershipLifecycleService $membershipLifecycleService,
        private MemberPaymentService $paymentObligationService,
        private MembershipNumberService $membershipNumberService,
    ) {
    }

    public function transition(
        MembershipApplication $application,
        MembershipApplicationStatus $to,
        User $actor,
        ?string $notes = null,
        bool $allowReapply = false,
    ): MembershipApplication {
        return DB::transaction(function () use (
            $application,
            $to,
            $actor,
            $notes,
            $allowReapply,
        ): MembershipApplication {
            $application = MembershipApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $application->status;

            if (
                $to === MembershipApplicationStatus::APPROVED
                && $application->membership_type_id === null
            ) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'Assign a membership type before approving this application.',
                ]);
            }

            if (! $this->isAllowed($from, $to)) {
                throw ValidationException::withMessages([
                    'status' => "The application cannot transition from [{$from->value}] to [{$to->value}].",
                ]);
            }

            $application->update([
                'status' => $to,
            ]);

            $application->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_user_id' => $actor->id,
                'notes' => $notes,
                'allow_reapply' => $to === MembershipApplicationStatus::REJECTED
                    ? $allowReapply
                    : false,
                'metadata' => [],
            ]);

            if ($to === MembershipApplicationStatus::PENDING_PAYMENT) {
                $this->paymentObligationService
                    ->createApplicationObligations($application);
            }

            $application->refresh();

            $event = match ($to) {
                MembershipApplicationStatus::VERIFIED =>
                    new MembershipApplicationVerified($application),
                MembershipApplicationStatus::REVIEWED =>
                    new MembershipApplicationReviewed($application),
                MembershipApplicationStatus::APPROVED =>
                    new MembershipApplicationApproved($application),
                MembershipApplicationStatus::PENDING_PAYMENT =>
                    new MembershipPaymentRequired($application),
                MembershipApplicationStatus::REJECTED =>
                    new MembershipApplicationRejected($application),
                default => null,
            };

            if ($event !== null) {
                DB::afterCommit(function () use ($event): void {
                    event($event);
                });
            }

            return $application;
        });
    }


    public function reject(
        MembershipApplication $application,
        User $actor,
        string $notes,
        bool $allowReapply,
    ): MembershipApplication {
        if ($notes === '') {
            throw ValidationException::withMessages([
                'notes' => 'A rejection reason is required.',
            ]);
        }

        return $this->transition(
            application: $application,
            to: MembershipApplicationStatus::REJECTED,
            actor: $actor,
            notes: $notes,
            allowReapply: $allowReapply,
        );
    }

    public function confirm(
        MembershipApplication $application,
        User $actor,
        ?string $membershipNumber,
        string $startsAt,
        ?string $endsAt = null,
    ): MembershipApplication {
        $endsAt ??= now()->addYear()->toDateString();

        if ($endsAt < $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => 'The membership expiry date must be on or after the membership start date.',
            ]);
        }

        return DB::transaction(function () use (
            $application,
            $actor,
            $membershipNumber,
            $startsAt,
            $endsAt,
        ): MembershipApplication {
            // Serialize confirmation attempts for this application. The status
            // check must use the locked, current database row, not a stale model
            // supplied by the controller.
            $application = MembershipApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MembershipApplicationStatus::PENDING_CONFIRMATION) {
                throw ValidationException::withMessages([
                    'status' => 'Only applications awaiting payment confirmation can be confirmed.',
                ]);
            }

            if ($application->membership_type_id === null) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'A staff member must assign a membership type before confirming this application.',
                ]);
            }

            // Check payment state inside the same transaction as confirmation
            // so a concurrent confirmation/payment update cannot slip between
            // validation and member creation.
            $this->paymentObligationService->ensureApplicationFullyPaid($application);

            $organization = \Modules\Core\Models\Organization::query()->findOrFail($application->organization_id);
            $membershipNumber = trim((string) $membershipNumber);
            if ($membershipNumber === '') {
                $membershipNumber = $this->membershipNumberService->allocate($organization);
            } else {
                $this->membershipNumberService->observeManualNumber($organization, $membershipNumber);
            }

            $data = $application->data ?? [];
            $memberData = $data['member'] ?? [];

            $organizationMembership = \App\Models\OrganizationMembership::query()
                ->where('organization_id', $application->organization_id)
                ->where('user_id', $application->user_id)
                ->lockForUpdate()
                ->first();
            if ($organizationMembership !== null && $organizationMembership->status === 'pending'
                && data_get($organizationMembership->metadata, 'source') === 'spouse_membership_application') {
                $organizationMembership->update(['status' => 'active']);
            }

            // Finalize account-level fields from the temporary application payload.
            // Durable account name/phone live on users; profile fields live on user_details.
            $userUpdates = [];
            $fullName = collect([
                $memberData['first_name'] ?? null,
                $memberData['middle_name'] ?? null,
                $memberData['last_name'] ?? null,
            ])->filter(static fn ($part): bool => is_string($part) && trim($part) !== '')
                ->map(static fn (string $part): string => trim($part))
                ->implode(' ');

            if ($fullName !== '') {
                $userUpdates['name'] = $fullName;
            }

            if (! empty($memberData['primary_phone'])) {
                $normalizedPrimary = app(PhoneNumberNormalizer::class)->normalize(
                    (string) $memberData['primary_phone'],
                    $memberData['primary_phone_country_iso2'] ?? null,
                );
                $phoneOwner = User::query()->where('phone', $normalizedPrimary['phone_e164'])->whereKeyNot($application->user_id)->exists();
                if ($phoneOwner) {
                    throw ValidationException::withMessages(['primary_phone' => 'This phone number is already linked to another account.']);
                }
                $userUpdates['phone'] = $normalizedPrimary['phone_e164'];
                $userUpdates['phone_verified_at'] = null;
            }

            if ($userUpdates !== []) {
                try {
                    User::query()->whereKey($application->user_id)->update($userUpdates);
                } catch (QueryException $exception) {
                    // The users.phone unique index is the final protection against races.
                    if (! $this->isPhoneUniqueConstraintViolation($exception)) {
                        throw $exception;
                    }

                    throw ValidationException::withMessages([
                        'primary_phone' => 'This phone number is already linked to another account.',
                    ]);
                }
            }

            $member = $this->registrationService->register(
                memberData: [
                    'organization_id' => $application->organization_id,
                    'user_id' => $application->user_id,
                    'membership_number' => $membershipNumber,
                    'first_name' => $memberData['first_name'] ?? null,
                    'middle_name' => $memberData['middle_name'] ?? null,
                    'last_name' => $memberData['last_name'] ?? null,
                    'date_of_birth' => $memberData['date_of_birth'] ?? null,
                    'gender' => $memberData['gender'] ?? null,
                    'blood_group' => $memberData['blood_group'] ?? null,
                    'whatsapp' => $memberData['whatsapp'] ?? null,
                    'whatsapp_calling_code' => $memberData['whatsapp_calling_code'] ?? null,
                    'home_contact' => $memberData['home_contact'] ?? null,
                    'profession' => $memberData['profession'] ?? null,
                    'company' => $memberData['company'] ?? null,
                    'company_address' => $memberData['company_address'] ?? null,
                    'unit_id' => data_get($memberData, 'preferred_unit_id') ?: data_get($memberData, 'unit_id'),
                    'joined_at' => $startsAt,
                    'metadata' => $memberData['metadata'] ?? [],
                ],
                membershipTypeId: $application->membership_type_id,
                startsAt: $startsAt,
                extensionData: $data['extension_data'] ?? [],
                addressData: $data['addresses'] ?? ($data['address'] ?? []),
                identificationData: [],
                phoneNumbers: $memberData['phone_numbers'] ?? [],
                emergencyContacts: $memberData['emergency_contacts'] ?? [],
            );

            $this->linkSpouseRelationships($application, $member);

            // Media storage is not part of the database transaction. Copy the
            // photo only after the membership transaction commits so a later
            // database failure cannot leave a copied file/media record behind.
            $profilePhoto = $application->getFirstMedia('profile-photo');
            if ($profilePhoto !== null) {
                $targetUser = $member->user;
                $applicationId = $application->id;
                $memberId = $member->id;

                DB::afterCommit(function () use (
                    $profilePhoto,
                    $targetUser,
                    $applicationId,
                    $memberId,
                ): void {
                    try {
                        $profilePhoto->copy($targetUser, 'profile-photo');
                    } catch (\Throwable $exception) {
                        \Illuminate\Support\Facades\Log::warning(
                            'Unable to copy membership application profile photo after confirmation.',
                            [
                                'membership_application_id' => $applicationId,
                                'member_id' => $memberId,
                                'exception' => $exception->getMessage(),
                            ],
                        );
                    }
                });
            }

            $this->registrationService->finalizeApplicationIdentifications(
                application: $application,
                member: $member,
            );

            $membership = $member->memberships()->latest('id')->firstOrFail();
            $membership->ends_at = $endsAt;
            $membership->save();
            $this->membershipLifecycleService->activate($membership);

            $application->paymentObligations()->update([
                'membership_id' => $membership->id,
                'member_id' => $member->id,
            ]);

            \Modules\Member\Models\MemberPayment::query()
                ->where('membership_application_id', $application->id)
                ->whereNull('member_id')
                ->update([
                    'membership_id' => $membership->id,
                    'member_id' => $member->id,
                ]);

            $application->update([
                'member_id' => $member->id,
                'status' => MembershipApplicationStatus::CONFIRMED,
            ]);

            $application->statusHistories()->create([
                'from_status' => MembershipApplicationStatus::PENDING_CONFIRMATION->value,
                'to_status' => MembershipApplicationStatus::CONFIRMED->value,
                'actor_user_id' => $actor->id,
                'notes' => null,
                'allow_reapply' => false,
                'metadata' => [
                    'membership_number' => $membershipNumber,
                    'membership_ends_at' => $endsAt,
                ],
            ]);

            // Retain the application row as the review-history/payment foreign-key anchor,
            // but remove temporary personal data and edit snapshots after successful confirmation.
            $application->changes()->delete();
            $application->update([
                'data' => null,
                'completed_steps' => null,
                'current_step' => 1,
            ]);

            // The profile photo copy callback above runs first after commit. Identification
            // documents are transferred by finalizeApplicationIdentifications().
            $applicationIdForCleanup = $application->id;
            DB::afterCommit(function () use ($applicationIdForCleanup): void {
                $temporaryApplication = MembershipApplication::query()->find($applicationIdForCleanup);
                if ($temporaryApplication !== null) {
                    $temporaryApplication->clearMediaCollection('application-documents');
                    $temporaryApplication->clearMediaCollection('profile-photo');
                }
            });

            $application = $application->refresh()->load([
                'user.details',
                'membershipType',
                'statusHistories.actor',
            ]);

            DB::afterCommit(function () use ($application): void {
                event(new MembershipConfirmed($application));
            });

            return $application;
        });
    }

    private function isPhoneUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'users_phone_unique')
            || str_contains($message, 'users.phone')
            || (
                str_contains($message, 'phone')
                && (
                    str_contains($message, 'duplicate')
                    || str_contains($message, 'unique constraint')
                    || str_contains($message, 'unique violation')
                )
            );
    }

    private function linkSpouseRelationships(MembershipApplication $application, Member $member): void
    {
        $relatedMemberIds = [];
        $selectedMemberId = data_get($application->data, 'spouse.member_id');
        if ($selectedMemberId !== null) {
            $acceptedRequest = \Modules\Member\Models\SpouseRelationshipRequest::query()
                ->where('organization_id', $application->organization_id)
                ->where('requester_application_id', $application->id)
                ->where('target_member_id', (int) $selectedMemberId)
                ->where('status', 'accepted')
                ->first();

            if ($acceptedRequest !== null) {
                $acceptedRequest->update(['requester_member_id' => $member->id]);
                $relatedMemberIds[] = (int) $selectedMemberId;
            }
        }

        $sourceApplicationId = data_get($application->data, 'member.metadata.spouse_of_application_id');
        if ($sourceApplicationId !== null) {
            $sourceApplication = MembershipApplication::query()
                ->whereKey((int) $sourceApplicationId)
                ->where('organization_id', $application->organization_id)
                ->first();
            if ($sourceApplication?->member_id !== null) {
                $relatedMemberIds[] = (int) $sourceApplication->member_id;
            }
        }

        // If the original applicant is confirmed after the spouse, find the spouse application by its source reference.
        $spouseApplications = MembershipApplication::query()
            ->where('organization_id', $application->organization_id)
            ->whereNotNull('member_id')
            ->where('data->member->metadata->spouse_of_application_id', $application->id)
            ->get(['member_id']);
        foreach ($spouseApplications as $spouseApplication) {
            $relatedMemberIds[] = (int) $spouseApplication->member_id;
        }

        foreach (array_unique($relatedMemberIds) as $relatedMemberId) {
            if ($relatedMemberId === (int) $member->id) {
                continue;
            }
            $relatedMember = Member::query()
                ->whereKey($relatedMemberId)
                ->where('organization_id', $application->organization_id)
                ->first();
            if ($relatedMember === null) {
                continue;
            }

            // Keep the directional writes in a deterministic order. When two
            // spouse applications are confirmed concurrently, this reduces the
            // chance that each transaction holds the opposite relationship row.
            $relationshipPairs = [
                [$member->id, $relatedMember->id],
                [$relatedMember->id, $member->id],
            ];
            usort(
                $relationshipPairs,
                static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]],
            );

            foreach ($relationshipPairs as [$relationshipMemberId, $relationshipRelatedMemberId]) {
                $this->ensureActiveSpouseRelationship(
                    (int) $relationshipMemberId,
                    (int) $relationshipRelatedMemberId,
                );
            }
        }
    }

    /**
     * The relationship table has a unique key on member, related member, and
     * type; ended_at is deliberately not part of that key. Reuse and reactivate
     * an existing historical row instead of trying to insert a duplicate row.
     */
    private function ensureActiveSpouseRelationship(int $memberId, int $relatedMemberId): void
    {
        $relationship = \Modules\Member\Models\MemberRelationship::query()->firstOrCreate(
            [
                'member_id' => $memberId,
                'related_member_id' => $relatedMemberId,
                'relationship_type' => 'spouse',
            ],
            [
                'started_at' => now()->toDateString(),
                'ended_at' => null,
                'metadata' => ['source' => 'membership_application'],
            ],
        );

        if ($relationship->ended_at !== null) {
            $relationship->started_at = now()->toDateString();
            $relationship->ended_at = null;
            $relationship->metadata = ['source' => 'membership_application'];
            $relationship->save();
        }
    }

    private function isAllowed(
        MembershipApplicationStatus $from,
        MembershipApplicationStatus $to,
    ): bool {
        return match ($from) {
            MembershipApplicationStatus::DRAFT => $to === MembershipApplicationStatus::SUBMITTED,
            MembershipApplicationStatus::SUBMITTED => in_array($to, [
                MembershipApplicationStatus::VERIFIED,
                MembershipApplicationStatus::REJECTED,
            ], true),
            MembershipApplicationStatus::VERIFIED => in_array($to, [
                MembershipApplicationStatus::REVIEWED,
                MembershipApplicationStatus::REJECTED,
            ], true),
            MembershipApplicationStatus::REVIEWED => in_array($to, [
                MembershipApplicationStatus::APPROVED,
                MembershipApplicationStatus::REJECTED,
            ], true),
            MembershipApplicationStatus::APPROVED => $to === MembershipApplicationStatus::PENDING_PAYMENT,
            MembershipApplicationStatus::REJECTED => $to === MembershipApplicationStatus::DRAFT,
            default => false,
        };
    }
}
