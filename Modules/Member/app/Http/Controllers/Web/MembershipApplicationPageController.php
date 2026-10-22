<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MembershipApplicationService;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;
use libphonenumber\PhoneNumberUtil;

class MembershipApplicationPageController
{
    /** Render the authenticated applicant's membership application page. */
    public function show(CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $authenticatedUser = request()->user();

        return response()
            ->view('member::organization.membership-apply', [
                'organization' => $organization,
                'authenticatedUser' => $authenticatedUser,
                'spouseMode' => false,
                'spousePrefill' => [],
                'spouseApplicantName' => null,
                'primaryColor' => data_get($organization, 'settings.theme.primary_color', '#174b3d'),
                'accentColor' => data_get($organization, 'settings.theme.accent_color', '#d9a441'),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    /** Render the spouse application and prefill safe, reusable details from the submitted source application. */
    public function showSpouse(Request $request, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $sourceId = (int) $request->query('source_application', 0);

        $sourceApplication = MembershipApplication::query()
            ->whereKey($sourceId)
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('submitted_at')
            ->firstOrFail();

        $data = is_array($sourceApplication->data) ? $sourceApplication->data : [];
        $member = data_get($data, 'member', []);
        $addresses = data_get($data, 'addresses', []);
        if (! is_array($addresses) || $addresses === []) {
            $address = data_get($data, 'address', []);
            $addresses = is_array($address) && $address !== [] ? [$address] : [];
        }

        $gender = strtolower(trim((string) data_get($member, 'gender', '')));
        $oppositeGender = match ($gender) {
            'male' => 'female',
            'female' => 'male',
            default => null,
        };

        $spousePrefill = [
            'preferred_unit_id' => data_get($member, 'preferred_unit_id'),
            'gender' => $oppositeGender,
            'addresses' => array_values(array_slice($addresses, 0, 2)),
            'emergency_contacts' => array_values(array_slice(
                is_array(data_get($member, 'emergency_contacts', [])) ? data_get($member, 'emergency_contacts', []) : [],
                0,
                5
            )),
        ];

        $applicantName = trim(implode(' ', array_filter([
            data_get($member, 'first_name'),
            data_get($member, 'middle_name'),
            data_get($member, 'last_name'),
        ])));
        if ($applicantName === '') {
            $applicantName = (string) $request->user()->name;
        }

        return response()
            ->view('member::organization.membership-apply', [
                'organization' => $organization,
                'authenticatedUser' => $request->user(),
                'spouseMode' => true,
                'spousePrefill' => $spousePrefill,
                'spouseApplicantName' => $applicantName,
                'sourceApplicationId' => $sourceApplication->id,
                'primaryColor' => data_get($organization, 'settings.theme.primary_color', '#174b3d'),
                'accentColor' => data_get($organization, 'settings.theme.accent_color', '#d9a441'),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function configuration(
        CurrentOrganization $currentOrganization,
        MembershipApplicationController $applicationController,
    ): JsonResponse {
        $organization = $currentOrganization->get();
        $responseData = $applicationController->configuration()->getData(true);

        $phoneUtil = PhoneNumberUtil::getInstance();
        $countries = collect($phoneUtil->getSupportedRegions())
            ->filter(fn (string $region): bool => preg_match('/^[A-Z]{2}$/', $region) === 1)
            ->map(fn (string $region): array => [
                'iso2' => $region,
                'calling_code' => (string) $phoneUtil->getCountryCodeForRegion($region),
            ])
            ->filter(fn (array $country): bool => $country['calling_code'] !== '0')
            ->sortBy('iso2')
            ->values()
            ->all();

        $responseData['countries'] = $countries;
        $user = request()->user();
        $responseData['current_user_phone'] = null;
        if ($user?->phone) {
            try {
                $parsedPhone = $phoneUtil->parse($user->phone, null);
                $responseData['current_user_phone'] = [
                    'e164' => $phoneUtil->format($parsedPhone, \libphonenumber\PhoneNumberFormat::E164),
                    'country_iso2' => $phoneUtil->getRegionCodeForNumber($parsedPhone),
                    'national_number' => (string) $parsedPhone->getNationalNumber(),
                ];
            } catch (\Throwable) {
                $responseData['current_user_phone'] = ['e164' => $user->phone, 'country_iso2' => null, 'national_number' => $user->phone];
            }
        }
        $responseData['units'] = Unit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
            ])
            ->all();

        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user?->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();
        $draftStepOne = data_get($draft?->data, 'draft.step_1', []);
        $responseData['draft'] = $draft ? [
            'id' => $draft->id,
            'current_step' => max(2, (int) $draft->current_step),
            'step_1' => is_array($draftStepOne) ? $draftStepOne : [],
            'step_2' => is_array(data_get($draft->data, 'draft.step_2', [])) ? data_get($draft->data, 'draft.step_2', []) : [],
            'step_3' => is_array(data_get($draft->data, 'draft.step_3', [])) ? data_get($draft->data, 'draft.step_3', []) : [],
            'step_4' => is_array(data_get($draft->data, 'draft.step_4', [])) ? data_get($draft->data, 'draft.step_4', []) : [],
            'step_5' => is_array(data_get($draft->data, 'draft.step_5', [])) ? data_get($draft->data, 'draft.step_5', []) : [],
            'extension_data' => is_array(data_get($draft->data, 'draft.extension_data', data_get($draft->data, 'draft.step_3.extension_data', [])))
                ? data_get($draft->data, 'draft.extension_data', data_get($draft->data, 'draft.step_3.extension_data', []))
                : [],
            'identification_documents' => $this->draftIdentificationDocuments($draft),
            'profile_photo_url' => route('organization.membership.apply.profile-photo'),
        ] : null;

        return response()->json($responseData)
            ->header('Cache-Control', 'private, no-store');
    }

    /** Stream the authenticated applicant’s private draft photo without exposing storage. */
    public function profilePhoto(Request $request, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->firstOrFail();

        $media = $draft->getFirstMedia('profile-photo');
        abort_unless($media, 404);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** Stream a saved private identification proof belonging to the current applicant's draft. */
    public function draftDocument(Request $request, CurrentOrganization $currentOrganization, int $mediaId)
    {
        $organization = $currentOrganization->get();
        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->firstOrFail();

        $media = $draft->getMedia('application-documents')->firstWhere('id', $mediaId);
        abort_unless($media && $media->getCustomProperty('draft_upload') === true, 404);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** Return safe metadata and private URLs for identification files saved to this draft. */
    private function draftIdentificationDocuments(MembershipApplication $draft): array
    {
        return $draft->getMedia('application-documents')
            ->filter(fn ($media): bool => $media->getCustomProperty('draft_upload') === true)
            ->map(fn ($media): array => [
                'id' => $media->id,
                'code' => (string) $media->getCustomProperty('identification_code'),
                'side' => (string) $media->getCustomProperty('document_side'),
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'url' => route('organization.membership.apply.draft-document', ['mediaId' => $media->id]),
            ])
            ->values()
            ->all();
    }

    /** Persist step one so an applicant can resume after closing the browser or logging out. */
    public function saveDraft(Request $request, CurrentOrganization $currentOrganization, MembershipApplicationService $applicationService): JsonResponse
    {
        $draftStep = (int) $request->input('draft_step', 1);
        if ($draftStep === 2) {
            return $this->saveAddressDraft($request, $currentOrganization);
        }
        if ($draftStep >= 3) {
            return $this->saveAdditionalStepDraft($request, $currentOrganization);
        }

        $organization = $currentOrganization->get();
        $user = $request->user();

        $existingDraft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();
        $hasSavedPhoto = $existingDraft?->getMedia('profile-photo')->isNotEmpty() ?? false;

        $validated = Validator::make($request->all(), [
            'preferred_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('organization_id', $organization->id)->where('is_active', true)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['required', 'string', 'max:20'],
            'profession' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'primary_phone' => ['required', 'string', 'max:50', new \App\Rules\ValidPhoneNumberForCountry($request->input('primary_phone_country_iso2'))],
            'primary_phone_country_iso2' => ['required', 'string', 'size:2', 'alpha'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'whatsapp_calling_code' => ['nullable', 'string', 'max:10'],
            'home_contact' => ['nullable', 'string', 'max:50'],
            'phone_numbers' => ['sometimes', 'array', 'max:10'],
            'emergency_contacts' => ['required', 'array', 'min:1', 'max:5'],
            'emergency_contacts.*.contact_name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:50'],
            'emergency_contacts.*.country_iso2' => ['required', 'string', 'size:2', 'alpha'],
            'emergency_contacts.*.notes' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => [$hasSavedPhoto ? 'sometimes' : 'required', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ])->validate();

        $normalizedPhone = app(\App\Support\PhoneNumbers\PhoneNumberNormalizer::class)->normalize(
            $validated['primary_phone'],
            $validated['primary_phone_country_iso2'],
        );
        if (\App\Models\User::query()->where('phone', $normalizedPhone['phone_e164'])->whereKeyNot($user->id)->exists()) {
            return response()->json(['message' => 'This phone number is already linked to another account.', 'errors' => ['primary_phone' => ['This phone number is already linked to another account.']]], 422);
        }

        $draft = $existingDraft ?? $applicationService->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: null,
        );

        $stepOne = collect($validated)->except(['profile_photo'])->all();
        // WhatsApp and home phone are stored in their dedicated Step 1 fields.
        // Do not persist them a second time as additional phone rows.
        $stepOne['phone_numbers'] = collect($stepOne['phone_numbers'] ?? [])
            ->filter(fn ($phone): bool => is_array($phone)
                && ! in_array(strtolower((string) ($phone['type'] ?? 'alternate')), ['whatsapp', 'home'], true))
            ->unique(fn (array $phone): string => implode('|', [
                strtolower((string) ($phone['type'] ?? 'alternate')),
                strtoupper((string) ($phone['country_iso2'] ?? '')),
                preg_replace('/\D+/', '', (string) ($phone['phone'] ?? '')),
            ]))
            ->values()
            ->all();
        $data = $draft->data ?? [];
        data_set($data, 'draft.step_1', $stepOne);
        $draft->update([
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => $data,
        ]);

        if ($request->hasFile('profile_photo')) {
            $draft->clearMediaCollection('profile-photo');
            $draft->addMediaFromRequest('profile_photo')->toMediaCollection('profile-photo');
        }

        return response()->json([
            'saved' => true,
            'application_id' => $draft->id,
            'current_step' => 2,
            'step_1' => data_get($draft->refresh()->data, 'draft.step_1', []),
            'profile_photo_url' => route('organization.membership.apply.profile-photo'),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Persist address-step data without submitting the membership application. */
    private function saveAddressDraft(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $organization = $currentOrganization->get();
        $user = $request->user();

        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();

        if (! $draft) {
            return response()->json(['message' => 'Save your personal details before saving your addresses.'], 409);
        }

        $validated = Validator::make($request->all(), [
            'draft_step' => ['required', 'in:2'],
            'addresses' => ['required', 'array', 'min:2', 'max:6'],
            'addresses.*' => ['required', 'array'],
            'addresses.*.address_type' => ['required', 'string', 'max:50'],
            'addresses.0.address_type' => ['required', 'in:residential'],
            'addresses.1.address_type' => ['required', 'in:permanent'],
            'addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.locality' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:50'],
            'addresses.*.country' => ['required', 'string', 'max:255'],
            'addresses.*.is_primary' => ['sometimes', 'boolean'],
            'home_contact' => ['nullable', 'string', 'max:50'],
            'home_contact_country_iso2' => ['nullable', 'string', 'size:2', 'alpha'],
        ])->validate();

        $addresses = collect($validated['addresses'])
            ->values()
            ->map(fn (array $address): array => collect($address)->only([
                'address_type', 'address_line_1', 'address_line_2', 'locality',
                'city', 'state', 'postal_code', 'country', 'is_primary',
            ])->all())
            ->all();

        $stepTwo = [
            'addresses' => $addresses,
            'home_contact' => $validated['home_contact'] ?? null,
            'home_contact_country_iso2' => $validated['home_contact_country_iso2'] ?? null,
        ];
        $data = $draft->data ?? [];
        data_set($data, 'draft.step_2', $stepTwo);

        $completedSteps = collect($draft->completed_steps ?? [])->map(fn ($step): int => (int) $step);
        $completedSteps = $completedSteps->push(2)->unique()->sort()->values()->all();

        $draft->update([
            'current_step' => max(3, (int) $draft->current_step),
            'completed_steps' => $completedSteps,
            'data' => $data,
        ]);

        return response()->json([
            'saved' => true,
            'application_id' => $draft->id,
            'current_step' => $draft->current_step,
            'step_2' => data_get($draft->refresh()->data, 'draft.step_2', []),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Persist scalar fields and private proof uploads from later application steps. */
    private function saveAdditionalStepDraft(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $organization = $currentOrganization->get();
        $user = $request->user();
        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();

        if (! $draft) {
            return response()->json(['message' => 'Save your personal details before saving later application steps.'], 409);
        }

        $configurations = app(OrganizationIdentificationService::class)
            ->enabled($organization)
            ->keyBy(fn ($configuration) => $configuration->identificationType->code);

        $rules = [
            'draft_step' => ['required', 'integer', 'min:3', 'max:5'],
            'advance_step' => ['sometimes', 'boolean'],
            'extension_data' => ['sometimes', 'array'],
            'identifications' => ['sometimes', 'array'],
            'identification_expiry_dates' => ['sometimes', 'array'],
            'identification_documents' => ['sometimes', 'array'],
        ];

        foreach ($configurations as $code => $configuration) {
            $requirements = $configuration->document_requirements ?? [];
            $rules["identifications.{$code}"] = ['sometimes', 'nullable', 'string', 'max:255'];
            if ($configuration->has_expiry) {
                $rules["identification_expiry_dates.{$code}"] = ['sometimes', 'nullable', 'date'];
            }
            if (! $configuration->requires_document || ! is_array($requirements)) {
                continue;
            }
            $rules["identification_documents.{$code}"] = ['sometimes', 'array'];
            foreach ($requirements as $side) {
                $rules["identification_documents.{$code}.{$side}"] = [
                    'sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240',
                ];
            }
        }

        $validated = Validator::make($request->all(), $rules)->validate();

        // Reject uploads for disabled identification types or unconfigured sides.
        foreach ((array) ($validated['identification_documents'] ?? []) as $code => $files) {
            $configuration = $configurations->get($code);
            abort_unless($configuration && $configuration->requires_document, 422, 'This identification type does not accept proof documents.');
            $requirements = $configuration->document_requirements ?? [];
            foreach (array_keys((array) $files) as $side) {
                abort_unless(in_array($side, $requirements, true), 422, 'This proof document side is not configured.');
            }
        }

        $stepData = collect($validated)->only([
            'extension_data', 'identifications', 'identification_expiry_dates',
        ])->map(fn ($value) => $this->sanitizeDraftValue($value))->all();

        $stepNumber = (int) $validated['draft_step'];
        $data = $draft->data ?? [];
        // Merge into the current step so a partial autosave cannot erase fields
        // saved by a preceding request for the same step.
        $existingStepData = data_get($data, "draft.step_{$stepNumber}", []);
        $stepData = array_replace_recursive(is_array($existingStepData) ? $existingStepData : [], $stepData);
        data_set($data, "draft.step_{$stepNumber}", $stepData);

        // Keep extension data at a stable draft-level path as well as in its
        // step snapshot. This lets the UI restore it regardless of resume step.
        if ($stepNumber === 3 && isset($stepData['extension_data']) && is_array($stepData['extension_data'])) {
            $existingExtensionData = data_get($data, 'draft.extension_data', []);
            data_set(
                $data,
                'draft.extension_data',
                array_replace_recursive(
                    is_array($existingExtensionData) ? $existingExtensionData : [],
                    $stepData['extension_data'],
                ),
            );
        }

        foreach ((array) ($validated['identification_documents'] ?? []) as $code => $files) {
            $configuration = $configurations->get($code);
            foreach ((array) $files as $side => $file) {
                if (! $file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }
                foreach ($draft->getMedia('application-documents') as $existingMedia) {
                    if ((string) $existingMedia->getCustomProperty('identification_code') === (string) $code
                        && (string) $existingMedia->getCustomProperty('document_side') === (string) $side) {
                        $existingMedia->delete();
                    }
                }
                $draft->addMedia($file)
                    ->withCustomProperties([
                        'identification_type_id' => (int) $configuration->identificationType->id,
                        'identification_code' => (string) $code,
                        'document_side' => (string) $side,
                        'draft_upload' => true,
                    ])
                    ->toMediaCollection('application-documents');
            }
        }

        $completedSteps = collect($draft->completed_steps ?? [])->map(fn ($step): int => (int) $step);
        $advance = (bool) ($validated['advance_step'] ?? false);
        if ($advance) {
            $completedSteps = $completedSteps->push($stepNumber)->unique()->sort()->values()->all();
        } else {
            $completedSteps = $completedSteps->all();
        }

        $nextStep = $stepNumber + ($advance ? 1 : 0);
        $draft->update([
            'current_step' => max((int) $draft->current_step, $nextStep),
            'completed_steps' => $completedSteps,
            'data' => $data,
        ]);

        return response()->json([
            'saved' => true,
            'application_id' => $draft->id,
            'current_step' => $draft->current_step,
            'step' => $stepNumber,
            'step_data' => data_get($draft->refresh()->data, "draft.step_{$stepNumber}", []),
            'identification_documents' => $this->draftIdentificationDocuments($draft->refresh()),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Keep draft payloads to bounded, scalar values and nested arrays. */
    private function sanitizeDraftValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 5) {
            return null;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach (array_slice($value, 0, 100, true) as $key => $child) {
                if (! is_string($key) && ! is_int($key)) {
                    continue;
                }
                $sanitized[$key] = $this->sanitizeDraftValue($child, $depth + 1);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 5000);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        return null;
    }

}
