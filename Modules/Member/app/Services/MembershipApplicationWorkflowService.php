<?php

namespace Modules\Member\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationWorkflowService
{
    public function __construct(
        private MemberRegistrationService $registrationService,
        private MembershipLifecycleService $membershipLifecycleService,
        private MemberPaymentService $paymentObligationService,
    ) {
    }

    public function transition(
        MembershipApplication $application,
        MembershipApplicationStatus $to,
        User $actor,
        ?string $notes = null,
        bool $allowReapply = false,
    ): MembershipApplication {
        $from = $application->status;

        if (! $this->isAllowed($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "The application cannot transition from [{$from->value}] to [{$to->value}].",
            ]);
        }

        return DB::transaction(function () use (
            $application,
            $from,
            $to,
            $actor,
            $notes,
            $allowReapply,
        ): MembershipApplication {
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
                $this->paymentObligationService->createApplicationObligations($application);
            }

            $application = $application->refresh();

            $event = match ($to) {
                MembershipApplicationStatus::VERIFIED => new MembershipApplicationVerified($application),
                MembershipApplicationStatus::REVIEWED => new MembershipApplicationReviewed($application),
                MembershipApplicationStatus::APPROVED => new MembershipApplicationApproved($application),
                MembershipApplicationStatus::PENDING_PAYMENT => new MembershipPaymentRequired($application),
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
        string $membershipNumber,
        string $startsAt,
    ): MembershipApplication {
        return DB::transaction(function () use (
            $application,
            $actor,
            $membershipNumber,
            $startsAt,
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

            // Check payment state inside the same transaction as confirmation
            // so a concurrent confirmation/payment update cannot slip between
            // validation and member creation.
            $this->paymentObligationService->ensureApplicationFullyPaid($application);

            $data = $application->data ?? [];
            $memberData = $data['member'] ?? [];

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
                    'unit_id' => $memberData['unit_id'] ?? null,
                    'joined_at' => $startsAt,
                    'metadata' => $memberData['metadata'] ?? [],
                ],
                membershipTypeId: $application->membership_type_id,
                startsAt: $startsAt,
                extensionData: $data['extension_data'] ?? [],
                addressData: $data['address'] ?? [],
                identificationData: [],
            );

            $this->registrationService->finalizeApplicationIdentifications(
                application: $application,
                member: $member,
            );

            $membership = $member->memberships()->latest('id')->firstOrFail();
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
                ],
            ]);

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
