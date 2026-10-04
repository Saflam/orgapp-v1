<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Http\Requests\StoreMembershipApplicationRequest;
use Modules\Member\Http\Resources\MembershipApplicationResource;
use Modules\Member\Services\MemberRegistrationService;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;

class MembershipApplicationController
{
    public function __construct(
        private MemberRegistrationService $registrationService,
        private OrganizationIdentificationService $identificationService,
        private MembershipApplicationExtensionRegistry $extensionRegistry,
    ) {
    }

    public function configuration(): JsonResponse
    {
        $organization = app(CurrentOrganization::class)->get();

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        return response()->json([
            'steps' => $this->applicationSteps(
                $organization
            ),
            'personal' => [
                'fields' => $this->personalFields(),
            ],
            'address' => [
                'fields' => $this->addressFields(),
            ],
            'extensions' => $this->extensionRegistry->fields(
                $organization
            ),
            'identifications' => $this->identificationFields(
                $organization
            ),
            'documents' => $this->documentFields(
                $organization
            ),
        ]);
    }

    public function store(
        StoreMembershipApplicationRequest $request,
    ): JsonResponse {
        $validated = $request->validated();
        $organization = app(CurrentOrganization::class)->get();

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        $member = $this->registrationService->register(
            memberData: [
                'organization_id' => $organization->id,
                'user_id' => $validated['user_id'],
                'membership_number' => $validated['membership_number'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'whatsapp' => $validated['whatsapp'] ?? null,
                'whatsapp_calling_code' =>
                    $validated['whatsapp_calling_code'] ?? null,
                'home_contact' => $validated['home_contact'] ?? null,
                'profession' => $validated['profession'] ?? null,
                'company' => $validated['company'] ?? null,
                'unit_id' => $validated['unit_id'] ?? null,
                'joined_at' => $validated['joined_at'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ],
            membershipTypeId: $validated['membership_type_id'],
            startsAt: $validated['starts_at'],
            extensionData: $validated['extension_data'] ?? [],
            addressData: $validated['address'],
            identificationData: $this->resolveIdentificationData(
                $organization,
                $validated['identifications'] ?? [],
                $validated['identification_documents'] ?? [],
            ),
        );

        return response()->json(
            new MembershipApplicationResource($member),
            201
        );
    }

    private function applicationSteps(
        Organization $organization,
    ): array {
        $steps = [
            [
                'key' => 'personal',
                'label' => 'Personal Details',
                'type' => 'core',
            ],
            [
                'key' => 'address',
                'label' => 'Address',
                'type' => 'core',
            ],
        ];

        foreach (
            $this->extensionRegistry->fields($organization)
            as $module => $fields
        ) {
            $steps[] = [
                'key' => 'extension.' . $module,
                'label' => $module . ' Details',
                'type' => 'extension',
                'module' => $module,
            ];
        }

        $steps[] = [
            'key' => 'documents',
            'label' => 'Documents & Uploads',
            'type' => 'documents',
        ];

        return $steps;
    }

    private function personalFields(): array
    {
        return [
            'first_name' => [
                'type' => 'string',
                'label' => 'First Name',
                'required' => true,
            ],
            'middle_name' => [
                'type' => 'string',
                'label' => 'Middle Name',
                'required' => false,
            ],
            'last_name' => [
                'type' => 'string',
                'label' => 'Last Name',
                'required' => false,
            ],
            'date_of_birth' => [
                'type' => 'date',
                'label' => 'Date of Birth',
                'required' => false,
            ],
            'gender' => [
                'type' => 'string',
                'label' => 'Gender',
                'required' => false,
            ],
            'whatsapp' => [
                'type' => 'string',
                'label' => 'WhatsApp',
                'required' => false,
            ],
            'whatsapp_calling_code' => [
                'type' => 'string',
                'label' => 'WhatsApp Calling Code',
                'required' => false,
            ],
            'home_contact' => [
                'type' => 'string',
                'label' => 'Home Contact',
                'required' => false,
            ],
            'profession' => [
                'type' => 'string',
                'label' => 'Profession',
                'required' => false,
            ],
            'company' => [
                'type' => 'string',
                'label' => 'Company',
                'required' => false,
            ],
        ];
    }

    private function addressFields(): array
    {
        return [
            'address_type' => [
                'type' => 'string',
                'label' => 'Address Type',
                'required' => true,
            ],
            'address_line_1' => [
                'type' => 'string',
                'label' => 'Address Line 1',
                'required' => true,
            ],
            'address_line_2' => [
                'type' => 'string',
                'label' => 'Address Line 2',
                'required' => false,
            ],
            'locality' => [
                'type' => 'string',
                'label' => 'Locality',
                'required' => false,
            ],
            'city' => [
                'type' => 'string',
                'label' => 'City',
                'required' => false,
            ],
            'state' => [
                'type' => 'string',
                'label' => 'State',
                'required' => false,
            ],
            'postal_code' => [
                'type' => 'string',
                'label' => 'Postal Code',
                'required' => false,
            ],
            'country' => [
                'type' => 'string',
                'label' => 'Country',
                'required' => false,
            ],
            'is_primary' => [
                'type' => 'boolean',
                'label' => 'Primary Address',
                'required' => false,
            ],
        ];
    }

    private function identificationFields(
        Organization $organization,
    ): array {
        return $this->identificationService
            ->enabled($organization)
            ->mapWithKeys(
                fn ($configuration): array => [
                    $configuration->identificationType->code => [
                        'type' => 'string',
                        'label' =>
                            $configuration->identificationType->name,
                        'required' => $configuration->is_required,
                        'requires_document' =>
                            $configuration->requires_document,
                        'document_requirements' =>
                            $configuration->document_requirements ?? [],
                    ],
                ]
            )
            ->all();
    }

    private function documentFields(
        Organization $organization,
    ): array {
        return $this->identificationService
            ->enabled($organization)
            ->filter(
                fn ($configuration): bool =>
                    $configuration->requires_document
            )
            ->mapWithKeys(
                fn ($configuration): array => [
                    $configuration->identificationType->code => [
                        'label' =>
                            $configuration->identificationType->name,
                        'required' => true,
                        'requirements' =>
                            $configuration->document_requirements ?? [],
                    ],
                ]
            )
            ->all();
    }

    /**
     * @param array<string, string> $identifications
     * @param array<string, array<string, mixed>> $documents
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveIdentificationData(
        Organization $organization,
        array $identifications,
        array $documents = [],
    ): array {
        if (
            $identifications === []
            && $documents === []
        ) {
            return [];
        }

        $configurations = $this->identificationService
            ->enabled($organization)
            ->keyBy(
                fn ($configuration) =>
                    $configuration->identificationType->code
            );

        $codes = array_unique([
            ...array_keys($identifications),
            ...array_keys($documents),
        ]);

        return collect($codes)
            ->map(
                function (string $code) use (
                    $identifications,
                    $documents,
                    $configurations,
                ): array {
                    $configuration = $configurations->get($code);

                    if ($configuration === null) {
                        throw ValidationException::withMessages([
                            "identifications.{$code}" =>
                                'The identification type is not enabled for this organization.',
                        ]);
                    }

                    return [
                        'identification_type' =>
                            $configuration->identificationType,
                        'identification_number' =>
                            $identifications[$code] ?? null,
                        'documents' =>
                            $documents[$code] ?? [],
                    ];
                }
            )
            ->values()
            ->all();
    }
}