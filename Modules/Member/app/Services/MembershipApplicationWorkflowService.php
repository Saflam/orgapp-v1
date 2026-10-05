<?php

namespace Modules\Member\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationWorkflowService
{
    public function __construct(
        private MemberRegistrationService $registrationService,
        private MembershipLifecycleService $membershipLifecycleService,
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

            return $application->refresh();
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
        if ($application->status !== MembershipApplicationStatus::PAYMENT) {
            throw ValidationException::withMessages([
                'status' => 'Only applications awaiting payment confirmation can be confirmed.',
            ]);
        }

        return DB::transaction(function () use (
            $application,
            $actor,
            $membershipNumber,
            $startsAt,
        ): MembershipApplication {
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

            $application->update([
                'member_id' => $member->id,
                'status' => MembershipApplicationStatus::CONFIRMED,
            ]);

            $application->statusHistories()->create([
                'from_status' => MembershipApplicationStatus::PAYMENT->value,
                'to_status' => MembershipApplicationStatus::CONFIRMED->value,
                'actor_user_id' => $actor->id,
                'notes' => null,
                'allow_reapply' => false,
                'metadata' => [
                    'membership_number' => $membershipNumber,
                ],
            ]);

            return $application->refresh()->load([
                'user.details',
                'membershipType',
                'statusHistories.actor',
            ]);
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
            MembershipApplicationStatus::APPROVED => $to === MembershipApplicationStatus::PAYMENT,
            MembershipApplicationStatus::PAYMENT => $to === MembershipApplicationStatus::CONFIRMED,
            MembershipApplicationStatus::REJECTED => $to === MembershipApplicationStatus::DRAFT,
            default => false,
        };
    }
}
