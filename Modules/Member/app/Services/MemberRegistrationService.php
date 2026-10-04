<?php

namespace Modules\Member\Services;

use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Throwable;

class MemberRegistrationService
{
    public function __construct(
        private MemberService $memberService,
        private MembershipApplicationExtensionRegistry $extensionRegistry,
        private UserIdentificationService $userIdentificationService,
        private IdentificationDocumentService $identificationDocumentService,
    ) {
    }

    public function register(
        array $memberData,
        int $membershipTypeId,
        string $startsAt,
        array $extensionData = [],
        array $addressData = [],
        array $identificationData = [],
    ): Member {
        return DB::transaction(function () use (
            $memberData,
            $membershipTypeId,
            $startsAt,
            $extensionData,
            $addressData,
            $identificationData,
        ) {
            $membershipType = MembershipType::query()->find(
                $membershipTypeId
            );

            if (! $membershipType) {
                throw ValidationException::withMessages([
                    'membership_type_id' =>
                        'The selected membership type does not exist.',
                ]);
            }

            if (
                $membershipType->organization_id
                !== $memberData['organization_id']
            ) {
                throw ValidationException::withMessages([
                    'membership_type_id' =>
                        'The selected membership type does not belong to the selected organization.',
                ]);
            }

            $organization = Organization::query()->findOrFail(
                $memberData['organization_id']
            );

            $member = $this->memberService->create($memberData);

            Membership::create([
                'member_id' => $member->id,
                'membership_type_id' => $membershipType->id,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'status' => MembershipStatus::IN_REVIEW,
                'metadata' => [],
            ]);

            if ($addressData !== []) {
                UserAddress::create([
                    'user_id' => $member->user_id,
                    ...$addressData,
                ]);
            }

            /*
             * Database transactions do not automatically remove physical
             * files written by Media Library. Keep track of every media
             * item created during registration so it can be removed if
             * anything later in the transaction fails.
             */
            $savedMedia = [];

            try {
                foreach ($identificationData as $identification) {
                    $identificationType =
                        $identification['identification_type'];

                    $userIdentification =
                        $this->userIdentificationService->save(
                            user: $member->user,
                            identificationType: $identificationType,
                            data: [
                                'identification_number' =>
                                    $identification['identification_number'],
                                'metadata' =>
                                    $identification['metadata'] ?? null,
                            ],
                        );

                    $documents = $identification['documents'] ?? [];

                    if ($documents !== []) {
                        $media =
                            $this->identificationDocumentService->saveMany(
                                identification: $userIdentification,
                                documents: $documents,
                            );

                        $savedMedia = array_merge(
                            $savedMedia,
                            array_values($media),
                        );
                    }
                }

                $this->extensionRegistry->persist(
                    organization: $organization,
                    member: $member,
                    extensionData: $extensionData,
                );

                return $member->load([
                    'user.details',
                    'memberships',
                ]);
            } catch (Throwable $exception) {
                /*
                 * Roll back physical files as well as database state.
                 *
                 * Media::delete() removes the physical file and its media
                 * record. The surrounding DB transaction will also roll
                 * back any remaining database writes.
                 */
                foreach ($savedMedia as $media) {
                    try {
                        $media->delete();
                    } catch (Throwable) {
                        /*
                         * Never replace the original registration exception
                         * with a cleanup exception.
                         */
                    }
                }

                throw $exception;
            }
        });
    }
}