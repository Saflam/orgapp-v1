<?php

namespace Modules\Member\Http\Requests;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;

class StoreMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->resolveOrganization();

        if (! $organization instanceof Organization) {
            return $this->baseRules();
        }

        $rules = $this->baseRules();

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
                $rules["identification_documents.{$code}.{$side}"] = [
                    'required',
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
    private function baseRules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'membership_type_id' => [
                'required',
                'integer',
                'exists:membership_types,id',
            ],
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
            'date_of_birth' => [
                'nullable',
                'date',
            ],
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
            ],
            'whatsapp_calling_code' => [
                'nullable',
                'string',
                'max:10',
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
                'exists:units,id',
            ],
            'address' => [
                'required',
                'array',
            ],
            'address.address_type' => [
                'required',
                'string',
                'max:50',
            ],
            'address.address_line_1' => [
                'required',
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
        ];
    }
}
