<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Http\Requests\ConfirmMembershipApplicationRequest;
use Modules\Member\Http\Requests\MembershipApplicationDecisionRequest;
use Modules\Member\Http\Requests\StoreMembershipApplicationRequest;
use Modules\Member\Http\Requests\UpdateMembershipApplicationRequest;
use Modules\Member\Http\Resources\MembershipApplicationResource;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Modules\Member\Services\MembershipApplicationEditService;
use Modules\Member\Services\MembershipApplicationService;
use Modules\Member\Services\MembershipApplicationWorkflowService;
use Modules\Member\Services\MemberPaymentService;

class MembershipApplicationController
{
    public function __construct(
        private MembershipApplicationService $applicationService,
        private MembershipApplicationEditService $editService,
        private MembershipApplicationWorkflowService $workflowService,
        private MemberPaymentService $paymentObligationService,
        private OrganizationIdentificationService $identificationService,
        private MembershipApplicationExtensionRegistry $extensionRegistry,
        private AuthorizationService $authorizationService,
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
            'membership_types' => $this->membershipTypes($organization),
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
        if (! empty($validated['primary_phone'])) {
            $normalizedPrimary = app(\App\Support\PhoneNumbers\PhoneNumberNormalizer::class)->normalize(
                (string) $validated['primary_phone'],
                $validated['primary_phone_country_iso2'] ?? null,
            );
            if (\App\Models\User::query()->where('phone', $normalizedPrimary['phone_e164'])->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['primary_phone' => 'This phone number is already linked to another account.']);
            }
        }
        $steps = $this->applicationSteps($organization);

        $application = $this->applicationService->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: isset($validated['membership_type_id']) ? (int) $validated['membership_type_id'] : null,
        );

        $identificationData = $this->resolveIdentificationData(
            organization: $organization,
            identifications: $validated['identifications'] ?? [],
            documents: $validated['identification_documents'] ?? [],
            expiryDates: $validated['identification_expiry_dates'] ?? [],
        );

        $application = $this->applicationService->submit(
            organization: $organization,
            user: $user,
            membershipTypeId: isset($validated['membership_type_id']) ? (int) $validated['membership_type_id'] : null,
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
                    'company_address' => $validated['company_address'] ?? null,
                    'preferred_unit_id' => $validated['preferred_unit_id'] ?? null,
                    'primary_phone' => $validated['primary_phone'] ?? null,
                    'primary_phone_country_iso2' => $validated['primary_phone_country_iso2'] ?? null,
                    'phone_numbers' => $validated['phone_numbers'] ?? [],
                    'emergency_contacts' => $validated['emergency_contacts'] ?? [],
                    'unit_id' => $validated['unit_id'] ?? null,
                    'metadata' => [],
                ],
                'address' => $validated['address'] ?? ($validated['addresses'][0] ?? []),
                'addresses' => $validated['addresses'] ?? (isset($validated['address']) ? [$validated['address']] : []),
                'spouse' => $validated['spouse'] ?? [],
                'extension_data' => $validated['extension_data'] ?? [],
                'identifications' => collect($identificationData)
                    ->mapWithKeys(
                        fn (array $identification): array => [
                            $identification['code'] => [
                                'identification_type_id' => $identification['identification_type_id'],
                                'identification_number' => $identification['identification_number'],
                                'expires_at' => $identification['expires_at'] ?? null,
                            ],
                        ]
                    )
                    ->all(),
            ],
            identificationData: $identificationData,
            stepCount: count($steps),
        );

        if ($request->hasFile('profile_photo')) {
            $application->addMediaFromRequest('profile_photo')->toMediaCollection('profile-photo');
        }

        return response()->json(
            new MembershipApplicationResource($application),
            201
        );
    }

    public function storeSpouse(StoreMembershipApplicationRequest $request): JsonResponse
    {
        $organization = $this->organization();
        $validated = $request->validated();
        $sourceApplication = MembershipApplication::query()
            ->whereKey((int) ($validated['source_application_id'] ?? 0))
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($sourceApplication === null || $sourceApplication->submitted_at === null) {
            throw ValidationException::withMessages([
                'source_application_id' => 'The original application could not be verified. Please start from your submitted application.',
            ]);
        }

        $name = trim(implode(' ', array_filter([
            $validated['first_name'] ?? null,
            $validated['middle_name'] ?? null,
            $validated['last_name'] ?? null,
        ])));

        // Keep the UploadedFile instance before entering the transaction. The spouse
        // flow does not save a separate draft first, so media must be consumed from
        // this request while PHP's upload temporary file is still available.
        $profilePhoto = $request->file('profile_photo');

        $application = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated, $organization, $name, $sourceApplication): MembershipApplication {
            $phone = null;
            if (! empty($validated['primary_phone'])) {
                $normalized = app(\App\Support\PhoneNumbers\PhoneNumberNormalizer::class)->normalize(
                    (string) $validated['primary_phone'],
                    $validated['primary_phone_country_iso2'] ?? null,
                );
                if (\App\Models\User::query()->where('phone', $normalized['phone_e164'])->exists()) {
                    throw ValidationException::withMessages(['primary_phone' => 'This phone number is already linked to an account.']);
                }
                $phone = $normalized['phone_e164'];
            }

            $user = \App\Models\User::query()->create([
                'name' => $name,
                'email' => $validated['email'],
                'phone' => $phone,
                'password' => null,
            ]);

            \App\Models\OrganizationMembership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'joined_at' => now(),
                'metadata' => ['source' => 'spouse_membership_application'],
            ]);

            $application = $this->applicationService->startOrResume(
                organization: $organization,
                userId: $user->id,
                membershipTypeId: null,
            );

            $identificationData = $this->resolveIdentificationData(
                organization: $organization,
                identifications: $validated['identifications'] ?? [],
                documents: $validated['identification_documents'] ?? [],
                expiryDates: $validated['identification_expiry_dates'] ?? [],
            );

            $application = $this->applicationService->submit(
                organization: $organization,
                user: $user,
                membershipTypeId: null,
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
                        'company_address' => $validated['company_address'] ?? null,
                        'preferred_unit_id' => $validated['preferred_unit_id'] ?? null,
                        'primary_phone' => $validated['primary_phone'] ?? null,
                        'primary_phone_country_iso2' => $validated['primary_phone_country_iso2'] ?? null,
                        'phone_numbers' => $validated['phone_numbers'] ?? [],
                        'emergency_contacts' => $validated['emergency_contacts'] ?? [],
                        'unit_id' => null,
                        'metadata' => ['spouse_of_application_id' => $sourceApplication->id],
                    ],
                    'address' => $validated['addresses'][0] ?? [],
                    'addresses' => $validated['addresses'] ?? [],
                    'extension_data' => $validated['extension_data'] ?? [],
                    'identifications' => collect($identificationData)->mapWithKeys(fn (array $identification): array => [
                        $identification['code'] => [
                            'identification_type_id' => $identification['identification_type_id'],
                            'identification_number' => $identification['identification_number'],
                            'expires_at' => $identification['expires_at'] ?? null,
                        ],
                    ])->all(),
                ],
                identificationData: $identificationData,
                stepCount: count($this->applicationSteps($organization)),
            );

            return $application;
        });

        // Use the UploadedFile captured at the start of the request instead of
        // asking Media Library to resolve it from the request again inside the
        // transaction. This avoids the spouse-flow temporary-file lookup failure.
        if ($profilePhoto !== null && $profilePhoto->isValid()) {
            $application->addMedia($profilePhoto)->toMediaCollection('profile-photo');
        }

        return response()->json(new MembershipApplicationResource($application), 201);
    }

    public function searchSpouseMembers(\Illuminate\Http\Request $request): JsonResponse
    {
        $organization = $this->organization();
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []])->header('Cache-Control', 'private, no-store');
        }

        $members = \Modules\Member\Models\Member::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->whereHas('memberships', fn ($builder) => $builder->where('status', 'active'))
            ->where(function ($search) use ($query): void {
                $search->whereHas('user', function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })->orWhere('membership_number', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return response()->json(['data' => $members->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->user?->name ?? '',
            'email' => $member->user?->email ?? '',
            'membership_number' => $member->membership_number,
            'photo_url' => method_exists($member->user, 'getFirstMediaUrl') ? ($member->user->getFirstMediaUrl('profile-photo') ?: null) : null,
        ])->values()])->header('Cache-Control', 'private, no-store');
    }

    public function show(MembershipApplication $membershipApplication): JsonResponse
    {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);

        return response()->json(
            new MembershipApplicationResource(
                $membershipApplication->load([
                    'membershipType',
                    'statusHistories.actor',
                    'changes.actor',
                ])
            )
        );
    }

    public function update(
        UpdateMembershipApplicationRequest $request,
        MembershipApplication $membershipApplication,
    ): JsonResponse {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);

        $changes = $request->validated();

        if (array_key_exists('membership_type_id', $changes)) {
            if (! in_array($membershipApplication->status, [MembershipApplicationStatus::SUBMITTED, MembershipApplicationStatus::VERIFIED, MembershipApplicationStatus::REVIEWED], true)) {
                throw ValidationException::withMessages(['status' => 'Membership type can only be assigned during verification or review.']);
            }
            $membershipTypeId = (int) $changes['membership_type_id'];
            $membershipType = \Modules\Member\Models\MembershipType::query()
                ->whereKey($membershipTypeId)
                ->where('organization_id', $membershipApplication->organization_id)
                ->where('is_active', true)
                ->first();
            if ($membershipType === null) {
                throw ValidationException::withMessages(['membership_type_id' => 'Choose an active membership type belonging to this organization.']);
            }
            $membershipApplication->update(['membership_type_id' => $membershipType->id]);
            unset($changes['membership_type_id']);
        }

        if (array_key_exists('unit_id', $changes['member'] ?? [])) {
            $this->authorizeUnitAssignment(
                actor: $request->user(),
                unitId: $changes['member']['unit_id'] === null
                    ? null
                    : (int) $changes['member']['unit_id'],
            );
        }

        if ($changes === []) {
            return response()->json(new MembershipApplicationResource($membershipApplication->refresh()));
        }

        $application = $this->editService->update(
            application: $membershipApplication,
            actor: $request->user(),
            changes: $changes,
        );

        return response()->json(
            new MembershipApplicationResource(
                $application->load(['membershipType', 'statusHistories.actor', 'changes.actor'])
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

        $application = $this->paymentObligationService->markApplicationPaid(
            application: $membershipApplication,
            actor: request()->user(),
            scopeType: $membershipApplication->unitId() === null ? 'organization' : 'unit',
            scopeId: $membershipApplication->unitId(),
        );

        return response()->json(
            new MembershipApplicationResource(
                $application->load(['membershipType', 'paymentObligations', 'statusHistories.actor'])
            )
        );
    }

    public function membershipNumberSuggestion(
        MembershipApplication $membershipApplication,
        \Modules\Member\Services\MembershipNumberService $membershipNumberService,
    ): JsonResponse {
        $this->ensureApplicationBelongsToCurrentOrganization($membershipApplication);
        $organization = \Modules\Core\Models\Organization::query()->findOrFail($membershipApplication->organization_id);

        return response()->json([
            'membership_number' => $membershipNumberService->suggest($organization),
        ]);
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
            membershipNumber: $validated['membership_number'] ?? null,
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

    /**
     * Prevent a unit-scoped reviewer from moving an application into a
     * different authorization context (including organization scope).
     *
     * The route middleware authorizes access to the application's current
     * context. When unit_id changes, authorization must also be checked for
     * the target context before the new unit is persisted.
     */
    private function authorizeUnitAssignment(
        \App\Models\User $actor,
        ?int $unitId,
    ): void {
        $organization = $this->organization();

        $context = new AuthorizationContext(
            organizationId: $organization->id,
            unitId: $unitId,
        );

        if (! $this->authorizationService->can(
            $actor,
            'membership.application.edit',
            $context,
        )) {
            abort(403);
        }
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

    private function membershipTypes(Organization $organization): array
    {
        return \Modules\Member\Models\MembershipType::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description'])
            ->map(fn ($membershipType): array => [
                'id' => $membershipType->id,
                'name' => $membershipType->name,
                'code' => $membershipType->code,
                'description' => $membershipType->description,
            ])
            ->all();
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
            'phone_numbers' => [
                'type' => 'array',
                'label' => 'Additional Phone Numbers',
                'required' => false,
                'item_fields' => [
                    'type' => ['type' => 'string', 'required' => true],
                    'phone' => ['type' => 'tel', 'required' => true],
                    'country_iso2' => ['type' => 'country', 'required' => true],
                    'label' => ['type' => 'string', 'required' => false],
                    'extension' => ['type' => 'string', 'required' => false],
                ],
            ],
            'emergency_contacts' => [
                'type' => 'array',
                'label' => 'Emergency Contacts',
                'required' => false,
                'item_fields' => [
                    'contact_name' => ['type' => 'string', 'required' => true],
                    'relationship' => ['type' => 'string', 'required' => false],
                    'phone' => ['type' => 'tel', 'required' => true],
                    'country_iso2' => ['type' => 'country', 'required' => true],
                    'notes' => ['type' => 'string', 'required' => false],
                ],
            ],
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
                    'has_expiry' => $configuration->has_expiry,
                    'expiry_reminder_days' => $configuration->expiry_reminder_days ?? [],
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
        array $expiryDates = [],
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
                    'expires_at' => $expiryDates[$code] ?? null,
                    'documents' => $documents[$code] ?? [],
                ];
            })
            ->values()
            ->all();
    }
}
