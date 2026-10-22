<?php

namespace Modules\Member\Http\Requests;

use App\Support\Organization\CurrentOrganization;
use App\Rules\ValidPhoneNumberForCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Modules\Member\Rules\ValidPhoneNumber;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;

class StoreMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $organization = $this->resolveOrganization();

            if (! $organization instanceof Organization) {
                return;
            }

            $extensionData = $this->input('extension_data', []);

            if (! is_array($extensionData)) {
                return;
            }

            foreach (app(MembershipApplicationExtensionRegistry::class)
                ->enabledFor($organization) as $extension) {
                $messages = $extension->validate(
                    organization: $organization,
                    data: is_array($extensionData[$extension->module()] ?? null)
                        ? $extensionData[$extension->module()]
                        : [],
                );

                foreach ($messages as $field => $fieldMessages) {
                    foreach ($fieldMessages as $message) {
                        $validator->errors()->add(
                            "extension_data.{$extension->module()}.{$field}",
                            $message,
                        );
                    }
                }
            }
        });
    }

    public function rules(): array
    {
        $organization = $this->resolveOrganization();

        if (! $organization instanceof Organization) {
            return $this->baseRules();
        }

        $rules = $this->baseRules($organization);

        $extensionRules = app(
            MembershipApplicationExtensionRegistry::class
        )->rules($organization);

        foreach ($extensionRules as $module => $moduleRules) {
            foreach ($moduleRules as $field => $fieldRules) {
                $rules[
                    "extension_data.{$module}.{$field}"
                ] = $fieldRules;
            }
        }

        foreach ($this->identificationRules($organization) as $field => $fieldRules) {
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    private function resolveOrganization(): ?Organization
    {
        try {
            $organization = app(CurrentOrganization::class)->get();

            if ($organization instanceof Organization) {
                return $organization;
            }
        } catch (\RuntimeException) {
            // Fall through to the route-based organization.
        }

        $routeOrganization = $this->route('organization');

        return $routeOrganization instanceof Organization
            ? $routeOrganization
            : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function identificationRules(Organization $organization): array
    {
        $configurations = app(
            OrganizationIdentificationService::class
        )->enabled($organization);

        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $this->user()?->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();
        $savedDraftDocumentSides = $draft
            ? $draft->getMedia('application-documents')
                ->filter(fn ($media): bool => $media->getCustomProperty('draft_upload') === true)
                ->map(fn ($media): string => (string) $media->getCustomProperty('identification_code') . '|' . (string) $media->getCustomProperty('document_side'))
                ->all()
            : [];

        $codes = $configurations
            ->map(fn ($configuration) => $configuration->identificationType->code)
            ->values()
            ->all();

        $rules = [
            'identifications' => [
                'sometimes',
                'array',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ) use ($codes): void {
                    if (! is_array($value)) {
                        return;
                    }

                    foreach (array_keys($value) as $code) {
                        if (! in_array($code, $codes, true)) {
                            $fail(
                                "The identification type [{$code}] is not enabled for this organization."
                            );
                        }
                    }
                },
            ],
            'identification_expiry_dates' => [
                'sometimes',
                'array',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ) use ($configurations): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $configurationMap = $configurations->keyBy(
                        fn ($configuration) => $configuration->identificationType->code
                    );

                    foreach ($value as $code => $date) {
                        $configuration = $configurationMap->get($code);

                        if ($configuration === null || ! $configuration->has_expiry) {
                            $fail(
                                "Identification type [{$code}] does not support expiry dates for this organization."
                            );
                        }
                    }
                },
            ],
            'identification_documents' => [
                'sometimes',
                'array',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ) use ($configurations): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $configurationMap = $configurations->keyBy(
                        fn ($configuration) => $configuration->identificationType->code
                    );

                    foreach ($value as $code => $documents) {
                        $configuration = $configurationMap->get($code);

                        if ($configuration === null) {
                            $fail(
                                "The identification type [{$code}] is not enabled for this organization."
                            );
                            continue;
                        }

                        $requirements = $configuration->document_requirements ?? [];

                        if ($requirements === []) {
                            $fail(
                                "Identification type [{$code}] does not accept identification documents."
                            );
                            continue;
                        }

                        if (! is_array($documents)) {
                            continue;
                        }

                        foreach (array_keys($documents) as $side) {
                            if (! in_array($side, $requirements, true)) {
                                $fail(
                                    "The document side [{$side}] is not required for identification type [{$code}]."
                                );
                            }
                        }
                    }
                },
            ],
        ];

        foreach ($configurations as $configuration) {
            $code = $configuration->identificationType->code;

            $rules["identification_expiry_dates.{$code}"] = [
                'sometimes',
                $configuration->has_expiry ? 'date' : 'prohibited',
            ];

            $rules["identifications.{$code}"] = [
                $configuration->is_required ? 'required' : 'nullable',
                'string',
                'max:255',
            ];

            $documentRequirements = $configuration->document_requirements ?? [];

            $rules["identification_documents.{$code}"] = [
                'sometimes',
                'array',
            ];

            foreach ($documentRequirements as $side) {
                $hasSavedDraftFile = in_array($code . '|' . $side, $savedDraftDocumentSides, true);
                $rules["identification_documents.{$code}.{$side}"] = [
                    $hasSavedDraftFile ? 'sometimes' : 'required',
                    'file',
                    'mimes:jpg,jpeg,png,pdf',
                    'max:10240',
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function baseRules(?Organization $organization = null): array
    {
        $usesAddressCollection = $this->routeIs('organization.membership.apply.submit')
            || $this->routeIs('organization.membership.apply.spouse.submit');

        // The primary applicant uploads the photo while saving Step 1. That
        // media belongs to the draft and must not be uploaded again at final
        // submission unless the applicant explicitly chooses a replacement.
        $hasSavedProfilePhoto = false;
        if (
            $this->routeIs('organization.membership.apply.submit')
            && $organization instanceof Organization
            && $this->user() !== null
        ) {
            $draft = MembershipApplication::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $this->user()->id)
                ->where('status', MembershipApplicationStatus::DRAFT)
                ->latest('id')
                ->first();

            $hasSavedProfilePhoto = $draft?->getMedia('profile-photo')->isNotEmpty() ?? false;
        }

        $profilePhotoRequired = (
            $this->routeIs('organization.membership.apply.submit')
            || $this->routeIs('organization.membership.apply.spouse.submit')
        ) && ! $hasSavedProfilePhoto;

        $rules = [
            'user_id' => [
                $this->routeIs('organization.membership.apply.spouse.submit') ? 'sometimes' : 'required',
                'integer',
                'exists:users,id',
            ],
            'membership_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
            'primary_phone' => [
                ($this->routeIs('organization.membership.apply.submit') || $this->routeIs('organization.membership.apply.spouse.submit')) ? 'required' : 'sometimes',
                'nullable', 'string', 'max:50',
                new \App\Rules\ValidPhoneNumberForCountry($this->input('primary_phone_country_iso2')),
            ],
            'primary_phone_country_iso2' => ['required_with:primary_phone', 'nullable', 'string', 'size:2', 'alpha'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'preferred_unit_id' => [($this->routeIs('organization.membership.apply.submit') || $this->routeIs('organization.membership.apply.spouse.submit')) ? 'required' : 'sometimes', 'nullable', 'integer', $organization instanceof Organization ? Rule::exists('units', 'id')->where('organization_id', $organization->id)->where('is_active', true) : 'exists:units,id'],
            'profile_photo' => [$profilePhotoRequired ? 'required' : 'sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'email' => [$this->routeIs('organization.membership.apply.spouse.submit') ? 'required' : 'sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => [
                'nullable',
                'string',
                'max:50',
            ],
            'blood_group' => [
                'required',
                'string',
                'max:20',
            ],
            'whatsapp' => [
                'nullable',
                'string',
                'max:50',
                new ValidPhoneNumber($this->input('whatsapp_calling_code')),
            ],
            'whatsapp_calling_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^\+?[1-9]\d{0,2}$/',
            ],
            'home_contact' => [
                'nullable',
                'string',
                'max:50',
            ],
            'profession' => [
                'nullable',
                'string',
                'max:255',
            ],
            'company' => [
                'nullable',
                'string',
                'max:255',
            ],
            'unit_id' => [
                'nullable',
                'integer',
                $organization instanceof Organization
                    ? Rule::exists('units', 'id')->where('organization_id', $organization->id)->where('is_active', true)
                    : 'exists:units,id',
            ],
            // The web application uses the structured addresses collection. Keep
            // the legacy single-address API contract available for existing clients.
            'address' => [$usesAddressCollection ? 'sometimes' : 'required', 'array'],
            'addresses' => [$usesAddressCollection ? 'required' : 'sometimes', 'array', 'min:2', 'max:6'],
            'addresses.*' => ['required', 'array'],
            'addresses.*.address_type' => [$usesAddressCollection ? 'required' : 'sometimes', 'string', 'max:50'],
            'addresses.0.address_type' => [$usesAddressCollection ? 'required' : 'sometimes', 'in:residential'],
            'addresses.1.address_type' => [$usesAddressCollection ? 'required' : 'sometimes', 'in:permanent'],
            'addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.locality' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:50'],
            'addresses.*.country' => ['required', 'string', 'max:255'],
            'addresses.*.is_primary' => ['sometimes', 'boolean'],
            'address.address_type' => [
                $usesAddressCollection ? 'sometimes' : 'required',
                'string',
                'max:50',
            ],
            'address.address_line_1' => [
                $usesAddressCollection ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
            'address.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address.locality' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address.city' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address.state' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address.postal_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'address.country' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address.is_primary' => [
                'sometimes',
                'boolean',
            ],
            'address.metadata' => [
                'nullable',
                'array',
            ],
            'extension_data' => [
                'sometimes',
                'array',
            ],
            'identifications' => [
                'sometimes',
                'array',
            ],
            'identification_documents' => [
                'sometimes',
                'array',
            ],
            'spouse' => ['sometimes', 'array'],
            'spouse.needed' => ['sometimes', 'in:yes,no'],
            'spouse.existing' => ['required_if:spouse.needed,yes', 'nullable', 'in:yes,no'],
            'spouse.member_id' => ['required_if:spouse.existing,yes', 'nullable', 'integer', $organization instanceof Organization ? Rule::exists('members', 'id')->where('organization_id', $organization->id) : 'exists:members,id'],
            'source_application_id' => [$this->routeIs('organization.membership.apply.spouse.submit') ? 'required' : 'sometimes', 'nullable', 'integer', 'exists:membership_applications,id'],
            'phone_numbers' => ['sometimes', 'array', 'max:10'],
            'emergency_contacts' => [($this->routeIs('organization.membership.apply.submit') || $this->routeIs('organization.membership.apply.spouse.submit')) ? 'required' : 'sometimes', 'array', 'min:1', 'max:5'],
        ];

        foreach ((array) $this->input('phone_numbers', []) as $index => $phoneNumber) {
            $phoneNumber = is_array($phoneNumber) ? $phoneNumber : [];
            $prefix = "phone_numbers.{$index}";
            $rules[$prefix] = ['required', 'array'];
            $rules["{$prefix}.type"] = ['required', 'string', 'max:50'];
            $rules["{$prefix}.phone"] = [
                'required', 'string', 'max:50',
                new ValidPhoneNumberForCountry(
                    is_string($phoneNumber['country_iso2'] ?? null)
                        ? $phoneNumber['country_iso2']
                        : null,
                ),
            ];
            $rules["{$prefix}.country_iso2"] = ['required', 'string', 'size:2', 'alpha'];
            $rules["{$prefix}.label"] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules["{$prefix}.extension"] = ['sometimes', 'nullable', 'string', 'max:20'];
            $rules["{$prefix}.is_primary"] = ['sometimes', 'boolean'];
        }

        foreach ((array) $this->input('emergency_contacts', []) as $index => $contact) {
            $contact = is_array($contact) ? $contact : [];
            $prefix = "emergency_contacts.{$index}";
            $rules[$prefix] = ['required', 'array'];
            $rules["{$prefix}.contact_name"] = ['required', 'string', 'max:255'];
            $rules["{$prefix}.relationship"] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules["{$prefix}.phone"] = [
                'required', 'string', 'max:50',
                new ValidPhoneNumberForCountry(
                    is_string($contact['country_iso2'] ?? null)
                        ? $contact['country_iso2']
                        : null,
                ),
            ];
            $rules["{$prefix}.country_iso2"] = ['required', 'string', 'size:2', 'alpha'];
            $rules["{$prefix}.extension"] = ['sometimes', 'nullable', 'string', 'max:20'];
            $rules["{$prefix}.is_primary"] = ['sometimes', 'boolean'];
            $rules["{$prefix}.notes"] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }

        return $rules;
    }
}
