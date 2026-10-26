<?php

namespace Modules\Member\Services;

use App\Services\UserContactPersistenceService;
use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;

class MemberProfileService
{
    public function findForOrganization(
        int $organizationId,
        int $userId,
    ): Member {
        return Member::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->with([
                'user.details',
                'user.phoneNumbers',
                'user.emergencyContacts',
                'unit',
            ])
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        Member $member,
        array $data,
    ): Member {
        return DB::transaction(function () use ($member, $data) {
            $userData = array_intersect_key(
                $data,
                array_flip([
                    'name',
                    'email',
                    'phone',
                ])
            );

            if (
                array_key_exists('phone', $userData)
                && trim((string) ($userData['phone'] ?? '')) !== ''
                && filled($data['phone_country_iso2'] ?? null)
            ) {
                $normalizedPhone = app(PhoneNumberNormalizer::class)->normalize(
                    (string) $userData['phone'],
                    (string) $data['phone_country_iso2'],
                );
                $userData['phone'] = $normalizedPhone['phone_e164'];
            }
            unset($userData['phone_country_iso2']);

            if ($userData !== []) {
                $member->user->update($userData);
            }

            $detailFields = array_intersect_key(
                $data,
                array_flip(['date_of_birth', 'blood_group', 'whatsapp', 'whatsapp_calling_code']),
            );
            if (
                array_key_exists('whatsapp', $detailFields)
                && trim((string) ($detailFields['whatsapp'] ?? '')) !== ''
                && filled($data['whatsapp_country_iso2'] ?? null)
            ) {
                $normalizedWhatsapp = app(PhoneNumberNormalizer::class)->normalize(
                    (string) $detailFields['whatsapp'],
                    (string) $data['whatsapp_country_iso2'],
                );
                $detailFields['whatsapp'] = $normalizedWhatsapp['national_number'];
                $detailFields['whatsapp_calling_code'] = '+'.$normalizedWhatsapp['country_calling_code'];
            }
            if ($detailFields !== []) {
                $member->user->details()->updateOrCreate([], $detailFields);
            }

            if (array_key_exists('addresses', $data)) {
                $addressesRelation = $member->user->addresses();
                $requiredTypes = ['permanent', 'permanent_address', 'local', 'local_address', 'residential', 'residential_address', 'current', 'current_address'];
                $normalizeType = static fn ($type): string => strtolower(str_replace([' ', '-'], '_', trim((string) $type)));
                $existingAddresses = $addressesRelation->get()->keyBy('id');

                foreach (($data['addresses'] ?? []) as $address) {
                    $id = isset($address['id']) ? (int) $address['id'] : null;
                    $existing = $id !== null ? $existingAddresses->get($id) : null;
                    if ($id !== null && ! $existing) {
                        abort(404);
                    }
                    $existingType = $existing ? $normalizeType($existing->address_type) : null;
                    $isRequired = $existingType !== null && in_array($existingType, $requiredTypes, true);

                    if (($address['_delete'] ?? false) === true || ($address['_delete'] ?? null) === '1') {
                        if ($isRequired) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'addresses' => 'Permanent and local addresses are required and cannot be deleted.',
                            ]);
                        }
                        if ($existing) {
                            $existing->delete();
                        }
                        continue;
                    }

                    $values = array_intersect_key($address, array_flip([
                        'address_type', 'address_line_1', 'address_line_2', 'locality',
                        'city', 'state', 'postal_code', 'country', 'is_primary',
                    ]));

                    // HTML checkboxes are omitted when unchecked. Treat omission as false
                    // for each address included in this submission, rather than retaining
                    // the previous true value in the database.
                    $values['is_primary'] = filter_var($address['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    // Keep primary-address state exclusive: choosing one address as primary
                    // clears the flag from all other addresses for this user.
                    if ($values['is_primary']) {
                        $addressesRelation->where('is_primary', true)->update(['is_primary' => false]);
                    }

                    if ($existing) {
                        if ($isRequired && $normalizeType($values['address_type'] ?? $existing->address_type) !== $existingType) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'addresses' => 'The type of permanent and local addresses cannot be changed.',
                            ]);
                        }
                        // Do not allow profile requests to rename a required address type.
                        if ($isRequired) {
                            unset($values['address_type']);
                        }
                        $existing->update($values);
                    } else {
                        $addressesRelation->create($values);
                    }
                }

            }

            if (array_key_exists('phone_numbers', $data) || array_key_exists('emergency_contacts', $data)) {
                app(UserContactPersistenceService::class)->syncForProfile(
                    user: $member->user,
                    phoneNumbers: array_key_exists('phone_numbers', $data) ? $data['phone_numbers'] : null,
                    emergencyContacts: array_key_exists('emergency_contacts', $data) ? $data['emergency_contacts'] : null,
                );
            }

            return $member->refresh()->load([
                'user.details',
                'user.phoneNumbers',
                'user.emergencyContacts',
                'unit',
            ]);
        });
    }
}