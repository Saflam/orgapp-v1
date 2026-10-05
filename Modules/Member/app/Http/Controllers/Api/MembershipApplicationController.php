<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Http\Requests\ConfirmMembershipApplicationRequest;
use Modules\Member\Http\Requests\MembershipApplicationDecisionRequest;
use Modules\Member\Http\Requests\StoreMembershipApplicationRequest;
use Modules\Member\Http\Resources\MembershipApplicationResource;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Modules\Member\Services\MembershipApplicationService;
use Modules\Member\Services\MembershipApplicationWorkflowService;

class MembershipApplicationController
{
    public function __construct(
        private MembershipApplicationService $applicationService,
        private MembershipApplicationWorkflowService $workflowService,
        private OrganizationIdentificationService $identificationService,
        private MembershipApplicationExtensionRegistry $extensionRegistry,
    ) {
    }

    public function configuration(): JsonResponse
    {
        $organization = $this->organization();

        return response()->json([
            'steps' => $this->applicationSteps($organization),
            'personal' => [
                'fields' => $this->personalFields(),
            ],
            'address' => [
                'fields' => $this->addressFields(),
            ],
            'extensions' => $this->extensionRegistry->fields($organization),
            'identifications' => $this->identificationFields($organization),
            'documents' => $this->documentFields($organization),
        ]);
    }

    public function store(StoreMembershipApplicationRequest $request): JsonResponse
    {
        $organization = $this->organization();
        $validated = $request->validated();
        $user = \App\Models\User::query()->findOrFail($validated['user_id']);
        $steps = $this->applicationSteps($organization);

        $application = $this->applicationService->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: (int) $validated['membership_type_id'],
        );

        $identificationData = $this->resolveIdentificationData(
            organization: $organization,
            identifications: $validated['identifications'] ?? [],
            documents: $validated['identification_documents'] ?? [],
        );

        $application = $this->applicationService->submit(
            organization: $organization,
            user: $user,
            membershipTypeId: (int) $validated['membership_type_id'],
            data: [
                'member' => [
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'blood_group' => $validated['blood_group'],
                    'whatsapp' => $validated['whatsapp'] ?? null,
                    'whatsapp_calling_code' => $validated['whatsapp_calling_code'] ?? null,
                    'home_contact' => $validated['home_contact'] ?? null,
                    'profession' => $validated['profession'] ?? null,
                    'company' => $validated['company'] ?? null,
                    'unit_id' => $validated['unit_id'] ?? null,
                    'metadata' => [],
                ],
                'address' => $validated['address'],
                'extension_data' => $validated['extension_data'] ?? [],
                'identifications' => collect($identificationData)
                    ->mapWithKeys(
                        fn (array $identification): array => [
                            $identification['code'] => [
                                'identification_type_id' => $identification['identification_type_id'],
                                'identification_number' => $identification['identification_number'],
                            ],
                        ]
                    )
                    ->all(),
            ],
            identificationData: $identificationData,
            stepCount: count($steps),
        );

        return response()->json(
            new MembershipApplicationResource($application),
            201
        );
    }

    public function show(MembershipApplication $membershipApplication): JsonResponse
    {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);

        return response()->json(
            new MembershipApplicationResource(
                $membershipApplication->load([
                    'membershipType',
                    'statusHistories.actor',
                ])
            )
        );
    }

    public function verify(
        MembershipApplicationDecisionRequest $request,
        MembershipApplication $membershipApplication,
    ): JsonResponse {
        return $this->decide(
            request: $request,
            application: $membershipApplication,
            acceptedStatus: MembershipApplicationStatus::VERIFIED,
        );
    }

    public function review(
        MembershipApplicationDecisionRequest $request,
        MembershipApplication $membershipApplication,
    ): JsonResponse {
        return $this->decide(
            request: $request,
            application: $membershipApplication,
            acceptedStatus: MembershipApplicationStatus::REVIEWED,
        );
    }

    public function approve(
        MembershipApplicationDecisionRequest $request,
        MembershipApplication $membershipApplication,
    ): JsonResponse {
        return $this->decide(
            request: $request,
            application: $membershipApplication,
            acceptedStatus: MembershipApplicationStatus::APPROVED,
        );
    }

    public function receivePayment(MembershipApplication $membershipApplication): JsonResponse
    {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);

        $application = $this->workflowService->transition(
            application: $membershipApplication,
            to: MembershipApplicationStatus::PAYMENT,
            actor: request()->user(),
        );

        return response()->json(new MembershipApplicationResource($application));
    }

    public function confirm(
        ConfirmMembershipApplicationRequest $request,
        MembershipApplication $membershipApplication,
    ): JsonResponse {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);

        $validated = $request->validated();

        $application = $this->workflowService->confirm(
            application: $membershipApplication,
            actor: $request->user(),
            membershipNumber: $validated['membership_number'],
            startsAt: $validated['starts_at'],
        );

        return response()->json(new MembershipApplicationResource($application));
    }

    private function decide(
        MembershipApplicationDecisionRequest $request,
        MembershipApplication $application,
        MembershipApplicationStatus $acceptedStatus,
    ): JsonResponse {
        $this->ensureApplicationBelongsToCurrentOrganization($application);
        $validated = $request->validated();

        if ($validated['decision'] === 'reject') {
            $application = $this->workflowService->reject(
                application: $application,
                actor: $request->user(),
                notes: $validated['notes'],
                allowReapply: (bool) ($validated['allow_reapply'] ?? false),
            );
        } else {
            $application = $this->workflowService->transition(
                application: $application,
                to: $acceptedStatus,
                actor: $request->user(),
            );
        }

        return response()->json(new MembershipApplicationResource($application));
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        return $organization;
    }

    private function ensureApplicationBelongsToCurrentOrganization(
        MembershipApplication $application,
    ): void {
        if ($application->organization_id !== $this->organization()->id) {
            throw ValidationException::withMessages([
                'application' => 'The application does not belong to the selected organization.',
            ]);
        }
    }

    private function applicationSteps(Organization $organization): array
    {
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

        foreach ($this->extensionRegistry->fields($organization) as $module => $fields) {
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
            'first_name' => ['type' => 'string', 'label' => 'First Name', 'required' => true],
            'middle_name' => ['type' => 'string', 'label' => 'Middle Name', 'required' => false],
            'last_name' => ['type' => 'string', 'label' => 'Last Name', 'required' => false],
            'date_of_birth' => ['type' => 'date', 'label' => 'Date of Birth', 'required' => false],
            'gender' => ['type' => 'string', 'label' => 'Gender', 'required' => false],
            'blood_group' => ['type' => 'string', 'label' => 'Blood Group', 'required' => true],
            'whatsapp' => ['type' => 'string', 'label' => 'WhatsApp', 'required' => false],
            'whatsapp_calling_code' => ['type' => 'string', 'label' => 'WhatsApp Calling Code', 'required' => false],
            'home_contact' => ['type' => 'string', 'label' => 'Home Contact', 'required' => false],
            'profession' => ['type' => 'string', 'label' => 'Profession', 'required' => false],
            'company' => ['type' => 'string', 'label' => 'Company', 'required' => false],
            'unit_id' => ['type' => 'integer', 'label' => 'Unit', 'required' => false],
        ];
    }

    private function addressFields(): array
    {
        return [
            'address_type' => ['type' => 'string', 'label' => 'Address Type', 'required' => true],
            'address_line_1' => ['type' => 'string', 'label' => 'Address Line 1', 'required' => true],
            'address_line_2' => ['type' => 'string', 'label' => 'Address Line 2', 'required' => false],
            'locality' => ['type' => 'string', 'label' => 'Locality', 'required' => false],
            'city' => ['type' => 'string', 'label' => 'City', 'required' => false],
            'state' => ['type' => 'string', 'label' => 'State', 'required' => false],
            'postal_code' => ['type' => 'string', 'label' => 'Postal Code', 'required' => false],
            'country' => ['type' => 'string', 'label' => 'Country', 'required' => false],
            'is_primary' => ['type' => 'boolean', 'label' => 'Primary Address', 'required' => false],
        ];
    }

    private function identificationFields(Organization $organization): array
    {
        return $this->identificationService
            ->enabled($organization)
            ->mapWithKeys(fn ($configuration): array => [
                $configuration->identificationType->code => [
                    'type' => 'string',
                    'label' => $configuration->identificationType->name,
                    'required' => $configuration->is_required,
                    'requires_document' => $configuration->requires_document,
                    'document_requirements' => $configuration->document_requirements ?? [],
                ],
            ])
            ->all();
    }

    private function documentFields(Organization $organization): array
    {
        return $this->identificationService
            ->enabled($organization)
            ->filter(fn ($configuration): bool => $configuration->requires_document)
            ->mapWithKeys(fn ($configuration): array => [
                $configuration->identificationType->code => [
                    'label' => $configuration->identificationType->name,
                    'required' => true,
                    'requirements' => $configuration->document_requirements ?? [],
                ],
            ])
            ->all();
    }

    /**
     * @param array<string, string> $identifications
     * @param array<string, array<string, mixed>> $documents
     * @return array<int, array<string, mixed>>
     */
    private function resolveIdentificationData(
        Organization $organization,
        array $identifications,
        array $documents = [],
    ): array {
        $configurations = $this->identificationService
            ->enabled($organization)
            ->keyBy(fn ($configuration) => $configuration->identificationType->code);

        $codes = array_unique([
            ...array_keys($identifications),
            ...array_keys($documents),
        ]);

        return collect($codes)
            ->map(function (string $code) use ($identifications, $documents, $configurations): array {
                $configuration = $configurations->get($code);

                if ($configuration === null) {
                    throw ValidationException::withMessages([
                        "identifications.{$code}" =>
                            'The identification type is not enabled for this organization.',
                    ]);
                }

                return [
                    'code' => $code,
                    'identification_type_id' => $configuration->identificationType->id,
                    'identification_number' => $identifications[$code] ?? null,
                    'documents' => $documents[$code] ?? [],
                ];
            })
            ->values()
            ->all();
    }
}
