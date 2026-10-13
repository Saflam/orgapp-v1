<?php

namespace Modules\Member\Http\Requests;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Organization;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;

class UpdateMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('extension_data')) {
                return;
            }

            $organization = $this->resolveOrganization();

            if (! $organization instanceof Organization) {
                return;
            }

            $application = $this->route('membershipApplication');
            $existingData = $application instanceof MembershipApplication
                ? ($application->data['extension_data'] ?? [])
                : [];
            $submittedData = $this->input('extension_data', []);

            if (! is_array($submittedData)) {
                return;
            }

            $extensionData = is_array($existingData) ? $existingData : [];

            foreach ($submittedData as $module => $moduleData) {
                if (! is_array($moduleData)) {
                    $extensionData[$module] = $moduleData;
                    continue;
                }

                $extensionData[$module] = array_replace(
                    is_array($extensionData[$module] ?? null)
                        ? $extensionData[$module]
                        : [],
                    $moduleData,
                );
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

        $rules = [
            'member' => ['sometimes', 'array'],
            'member.first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'member.middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member.date_of_birth' => ['sometimes', 'nullable', 'date'],
            'member.gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'member.blood_group' => ['sometimes', 'required', 'string', 'max:20'],
            'member.whatsapp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'member.whatsapp_calling_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'member.home_contact' => ['sometimes', 'nullable', 'string', 'max:50'],
            'member.profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member.company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member.unit_id' => [
                'sometimes',
                'nullable',
                'integer',
                $organization instanceof Organization
                    ? Rule::exists('units', 'id')->where('organization_id', $organization->id)
                    : 'exists:units,id',
            ],
            'address' => ['sometimes', 'array'],
            'address.address_type' => ['sometimes', 'string', 'max:50'],
            'address.address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.locality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address.country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'extension_data' => ['sometimes', 'array'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];

        if ($organization instanceof Organization) {
            $extensionRules = app(
                MembershipApplicationExtensionRegistry::class
            )->rules($organization);

            foreach ($extensionRules as $module => $moduleRules) {
                foreach ($moduleRules as $field => $fieldRules) {
                    $rules["extension_data.{$module}.{$field}"] = $fieldRules;
                }
            }
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
            // Fall back to the bound application when the context is unavailable.
        }

        $application = $this->route('membershipApplication');

        if ($application instanceof MembershipApplication) {
            return $application->organization;
        }

        $routeOrganization = $this->route('organization');

        return $routeOrganization instanceof Organization
            ? $routeOrganization
            : null;
    }
}
