<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Modules\Member\Http\Requests\UpdateMemberProfileRequest;
use Modules\Member\Models\Member;
use Modules\Member\Models\UserIdentification;
use Modules\Core\Models\OrganizationIdentification;
use Modules\Member\Models\MemberDependantIdentification;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Services\MemberProfileService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use libphonenumber\PhoneNumberUtil;

class MemberPortalController extends Controller
{
    public function __construct(private readonly MemberProfileService $profiles) {}

    public function show(CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user && auth()->id() === $member->user_id, 403);

        $user = $member->user->load(['details', 'addresses', 'phoneNumbers', 'emergencyContacts']);
        $currentUnit = $member->unit;
        if (! $currentUnit && $member->unit_id) {
            $currentUnit = \Modules\Core\Models\Unit::query()
                ->where('organization_id', $organization->id)
                ->find($member->unit_id);
        }
        if (! $currentUnit) {
            $currentUnit = $member->unitHistories()
                ->whereNull('ended_at')->with('unit')->latest('started_at')->first()?->unit;
        }
        if (! $currentUnit) {
            $currentUnit = $member->unitHistories()->with('unit')->latest('started_at')->first()?->unit;
        }
        // Older member records may retain the application-selected unit in metadata.
        if (! $currentUnit) {
            $metadataUnitId = data_get($member->metadata, 'preferred_unit_id')
                ?: data_get($member->metadata, 'unit_id');
            if ($metadataUnitId) {
                $currentUnit = \Modules\Core\Models\Unit::query()
                    ->where('organization_id', $organization->id)
                    ->find($metadataUnitId);
            }
        }
        $identifications = UserIdentification::query()
            ->where('user_id', $user->id)
            ->with('identificationType')
            ->orderBy('id')
            ->get();
        $identificationConfigurations = OrganizationIdentification::query()
            ->where('organization_id', $organization->id)
            ->where('is_enabled', true)
            ->get()
            ->keyBy('identification_type_id');
        $membershipRecords = $member->memberships()
            ->with('membershipType')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
        $currentMembership = $membershipRecords->first();
        $moduleProfileSections = app(\Modules\Member\Services\MemberProfileSectionExtensionRegistry::class)
            ->sections($organization, $member);
        $dependants = $member->dependants()->wherePivotNull('ended_at')->with('identifications.identificationType')->get();
        $dependantIdentificationTypes = OrganizationIdentification::query()
            ->where('organization_id', $organization->id)
            ->where('is_enabled', true)
            ->with('identificationType')
            ->get();
        $spouses = $member->relationships()
            ->whereNull('ended_at')
            ->whereIn('relationship_type', ['spouse', 'spouse_of'])
            ->with('relatedMember.user')
            ->get()
            ->map(fn ($relationship) => $relationship->relatedMember)
            ->filter();

        $spouseRequests = \Modules\Member\Models\SpouseRelationshipRequest::query()
            ->where('organization_id', $organization->id)
            ->where('target_member_id', $member->id)
            ->where('status', 'pending')
            ->with(['application.user', 'requesterMember.user'])
            ->latest('id')
            ->get();

        $outgoingSpouseRequests = \Modules\Member\Models\SpouseRelationshipRequest::query()
            ->where('organization_id', $organization->id)
            ->where('requester_member_id', $member->id)
            ->where('status', 'pending')
            ->with('targetMember.user')
            ->latest('id')
            ->get();

        // Only check whether eligible members exist. Do not load every member
        // into the page; the search endpoint returns a small, paginated-style result set.
        $hasAvailableSpouseMembers = Member::query()
            ->where('organization_id', $organization->id)
            ->whereKeyNot($member->id)
            ->whereHas('memberships', fn ($builder) => $builder->where('status', 'active'))
            ->whereHas('user')
            ->whereNotExists(function ($relationships): void {
                $relationships->selectRaw('1')
                    ->from('member_relationships')
                    ->whereNull('ended_at')
                    ->whereIn('relationship_type', ['spouse', 'spouse_of'])
                    ->where(function ($pair): void {
                        $pair->whereColumn('member_relationships.member_id', 'members.id')
                            ->orWhereColumn('member_relationships.related_member_id', 'members.id');
                    });
            })
            ->exists();

        $spouseSourceApplication = \Modules\Member\Models\MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', auth()->id())
            ->where('status', 'confirmed')
            ->whereNotNull('submitted_at')
            ->latest('id')
            ->first();

        $obligations = $member->paymentObligations()->latest('id')->get();
        $primaryColor = data_get($organization->settings, 'branding.primary_color', data_get($organization->settings, 'theme.primary_color', '#174b3d'));
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#174b3d';

        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneCountries = collect($phoneUtil->getSupportedRegions())
            ->filter(fn (string $region): bool => preg_match('/^[A-Z]{2}$/', $region) === 1)
            ->map(fn (string $region): array => [
                'iso2' => $region,
                'calling_code' => (string) $phoneUtil->getCountryCodeForRegion($region),
            ])
            ->filter(fn (array $country): bool => $country['calling_code'] !== '0')
            ->sortBy('iso2')
            ->values();

        $primaryPhoneCountryIso2 = 'IN';
        $primaryPhoneNationalNumber = (string) ($user->phone ?? '');
        if ($user->phone) {
            try {
                $parsedPhone = $phoneUtil->parse($user->phone, null);
                $detectedRegion = $phoneUtil->getRegionCodeForNumber($parsedPhone);
                if (is_string($detectedRegion) && preg_match('/^[A-Z]{2}$/', $detectedRegion)) {
                    $primaryPhoneCountryIso2 = $detectedRegion;
                }
                $primaryPhoneNationalNumber = (string) $parsedPhone->getNationalNumber();
            } catch (\Throwable) {
                // Preserve legacy values and use the default country if parsing fails.
            }
        }
        $whatsappCountryIso2 = $primaryPhoneCountryIso2;
        if ($user->details?->whatsapp_calling_code) {
            $callingCode = ltrim((string) $user->details->whatsapp_calling_code, '+');
            $country = $phoneCountries->firstWhere('calling_code', $callingCode);
            if ($country) $whatsappCountryIso2 = $country['iso2'];
        }

        return response()->view('member::organization.member-profile', compact('organization', 'member', 'user', 'identifications', 'identificationConfigurations', 'membershipRecords', 'currentMembership', 'moduleProfileSections', 'dependants', 'dependantIdentificationTypes', 'spouses', 'spouseRequests', 'outgoingSpouseRequests', 'hasAvailableSpouseMembers', 'spouseSourceApplication', 'obligations', 'primaryColor', 'currentUnit', 'phoneCountries', 'primaryPhoneCountryIso2', 'primaryPhoneNationalNumber', 'whatsappCountryIso2'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateMemberProfileRequest $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        // Email is intentionally excluded: members cannot change their login email here.
        $data = $request->safe()->except(['email']);
        $this->profiles->update($member, $data);

        return redirect()->route('organization.member.profile')->with('status', 'Your profile has been updated.');
    }

    /** Update profile fields owned by an enabled optional module. */
    public function updateModuleProfile(
        Request $request,
        string $module,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        app(\Modules\Member\Services\MemberProfileSectionExtensionRegistry::class)
            ->update($module, $request, $organization, $member);

        return redirect()->route('organization.member.profile')
            ->with('status', 'Organization-specific details have been updated.');
    }

    public function profilePhoto(CurrentOrganization $currentOrganization): StreamedResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        $media = $member->user->getFirstMedia('profile-photo');
        abort_unless($media, 404);
        abort_unless(Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()), 404);

        return response()->stream(function () use ($media): void {
            $stream = Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot());
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($media->file_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function updatePhoto(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $validated = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $member->user->addMediaFromRequest('photo')->toMediaCollection('profile-photo');
        return redirect()->route('organization.member.profile')->with('status', 'Profile photo updated.');
    }


    public function idCard(CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $user = $member->user;
        $primaryColor = data_get($organization->settings, 'branding.primary_color', data_get($organization->settings, 'theme.primary_color', '#174b3d'));
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#174b3d';
        $verificationUrl = URL::signedRoute('organization.member.id-card.verify', ['member' => $member->id]);
        $qrCodeDataUri = $this->qrCodeDataUri($verificationUrl);

        return response()->view('member::organization.member-id-card', compact(
            'organization', 'member', 'user', 'primaryColor', 'qrCodeDataUri'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function storeDependant(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'relationship_type' => ['required', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array'],
            'documents.*.identification_type_id' => ['required_with:documents', 'integer'],
            'documents.*.identification_number' => ['nullable', 'string', 'max:255'],
            'documents.*.expires_at' => ['nullable', 'date'],
            'documents.*.document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'documents.*.front' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'documents.*.back' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'],
        ]);
        $member->dependants()->attach($dependant->id, [
            'relationship_type' => $data['relationship_type'],
            'started_at' => now()->toDateString(),
        ]);
        if ($request->hasFile('photo')) {
            $dependant->addMediaFromRequest('photo')->toMediaCollection('profile-photo');
        }

        foreach (($data['documents'] ?? []) as $documentData) {
            $typeId = (int) ($documentData['identification_type_id'] ?? 0);
            $configuredType = OrganizationIdentification::query()
                ->where('organization_id', $organization->id)
                ->where('identification_type_id', $typeId)
                ->where('is_enabled', true)
                ->first();
            if (! $configuredType) {
                abort(422, 'One of the selected identification types is not enabled for this organization.');
            }
            $allowedSides = $configuredType->document_requirements ?: ($configuredType->requires_document ? ['document'] : []);
            $documentIndex = array_search($documentData, $data['documents'], true);
            foreach (['document', 'front', 'back'] as $side) {
                if (! in_array($side, $allowedSides, true) && $request->hasFile('documents.'.$documentIndex.'.'.$side)) {
                    abort(422, 'The uploaded proof does not match the organization document settings.');
                }
            }

            $hasDetails = filled($documentData['identification_number'] ?? null)
                || filled($documentData['expires_at'] ?? null)
                || $request->hasFile('documents.'.array_search($documentData, $data['documents'], true).'.document')
                || $request->hasFile('documents.'.array_search($documentData, $data['documents'], true).'.front')
                || $request->hasFile('documents.'.array_search($documentData, $data['documents'], true).'.back');
            if (! $hasDetails) {
                continue;
            }

            $identification = MemberDependantIdentification::updateOrCreate(
                ['dependant_id' => $dependant->id, 'identification_type_id' => $typeId],
                [
                    'identification_number' => $documentData['identification_number'] ?? null,
                    'expires_at' => $documentData['expires_at'] ?? null,
                ]
            );
            $documentIndex = array_search($documentData, $data['documents'], true);
            foreach (['document', 'front', 'back'] as $side) {
                $fileKey = 'documents.'.$documentIndex.'.'.$side;
                if ($request->hasFile($fileKey)) {
                    $identification->addMediaFromRequest($fileKey)
                        ->withCustomProperties(['document_side' => $side])
                        ->toMediaCollection('identification-documents');
                }
            }
        }

        return redirect()->route('organization.member.profile')->with('status', 'Dependant added.');
    }

    public function updateDependant(Request $request, int $dependant, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        abort_if($record->converted_member_id !== null, 422, 'A converted dependant cannot be edited.');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'relationship_type' => ['required', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $record->update([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'],
        ]);
        $member->dependantRelationships()->where('dependant_id', $record->id)->whereNull('ended_at')->update([
            'relationship_type' => $data['relationship_type'],
        ]);
        if ($request->hasFile('photo')) {
            $record->addMediaFromRequest('photo')->toMediaCollection('profile-photo');
        }

        return redirect()->route('organization.member.profile')->with('status', 'Dependant updated.');
    }

    public function destroyDependant(int $dependant, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        abort_if($record->converted_member_id !== null, 422, 'A converted dependant cannot be removed.');
        $member->dependantRelationships()->where('dependant_id', $record->id)->whereNull('ended_at')->update(['ended_at' => now()->toDateString()]);

        return redirect()->route('organization.member.profile')->with('status', 'Dependant removed from your active family list.');
    }

    public function storeDependantDocument(Request $request, int $dependant, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        abort_if($record->converted_member_id !== null, 422, 'A converted dependant cannot be edited.');

        $data = $request->validate([
            'identification_type_id' => ['required', 'integer', 'exists:identification_types,id'],
            'identification_number' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'front' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'back' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $configuredType = OrganizationIdentification::query()
            ->where('organization_id', $organization->id)
            ->where('identification_type_id', $data['identification_type_id'])
            ->where('is_enabled', true)
            ->firstOrFail();
        abort_if($record->identifications()->where('identification_type_id', $configuredType->identification_type_id)->exists(), 422, 'This document has already been added.');
        $allowedSides = $configuredType->document_requirements ?: ($configuredType->requires_document ? ['document'] : []);
        foreach (['document', 'front', 'back'] as $side) {
            if (! in_array($side, $allowedSides, true) && $request->hasFile($side)) {
                abort(422, 'The uploaded proof does not match the organization document settings.');
            }
        }
        if ($configuredType->requires_document) {
            foreach ($allowedSides as $side) {
                if (! $request->hasFile($side)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$side => 'This document proof is required.']);
                }
            }
        }
        if ($configuredType->is_required && blank($data['identification_number'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['identification_number' => 'A document number is required.']);
        }

        $identification = MemberDependantIdentification::updateOrCreate(
            ['dependant_id' => $record->id, 'identification_type_id' => $configuredType->identification_type_id],
            [
                'identification_number' => $data['identification_number'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );
        foreach (['document', 'front', 'back'] as $side) {
            if ($request->hasFile($side)) {
                $identification->addMediaFromRequest($side)
                    ->withCustomProperties(['document_side' => $side])
                    ->toMediaCollection('identification-documents');
            }
        }

        return redirect()->route('organization.member.profile')->with('status', 'Dependant identification details saved.');
    }

    public function updateDependantDocument(Request $request, int $dependant, int $identification, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        abort_if($record->converted_member_id !== null, 422, 'A converted dependant cannot be edited.');
        $document = $record->identifications()->whereKey($identification)->firstOrFail();
        $configuration = OrganizationIdentification::query()->where('organization_id', $organization->id)->where('identification_type_id', $document->identification_type_id)->where('is_enabled', true)->firstOrFail();
        $sides = $configuration->document_requirements ?: ($configuration->requires_document ? ['document'] : []);
        $rules = ['identification_number' => ['nullable', 'string', 'max:255'], 'expires_at' => [$configuration->has_expiry ? 'nullable' : 'nullable', 'date']];
        foreach ($sides as $side) $rules[$side] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];
        $data = $request->validate($rules);
        $document->update(['identification_number' => $data['identification_number'] ?? null, 'expires_at' => $data['expires_at'] ?? null]);
        foreach ($sides as $side) {
            if ($request->hasFile($side)) {
                $document->getMedia('identification-documents')->filter(fn ($media) => $media->getCustomProperty('document_side') === $side)->each(fn ($media) => $media->delete());
                $document->addMediaFromRequest($side)->withCustomProperties(['document_side' => $side])->toMediaCollection('identification-documents');
            }
        }
        return redirect()->route('organization.member.profile')->with('status', 'Dependant document updated.');
    }

    public function destroyDependantDocument(int $dependant, int $identification, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        abort_if($record->converted_member_id !== null, 422, 'A converted dependant cannot be edited.');
        $document = $record->identifications()->whereKey($identification)->firstOrFail();
        $configuration = OrganizationIdentification::query()->where('organization_id', $organization->id)->where('identification_type_id', $document->identification_type_id)->where('is_enabled', true)->first();
        abort_if($configuration && ($configuration->is_required || $configuration->requires_document), 422, 'This document is required by the organization and cannot be removed.');
        $document->clearMediaCollection('identification-documents');
        $document->delete();
        return redirect()->route('organization.member.profile')->with('status', 'Dependant document removed.');
    }

    public function dependantDocument(int $dependant, int $identification, string $side, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        $document = $record->identifications()->whereKey($identification)->firstOrFail();
        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);
        $media = $document->document($side);
        abort_unless($media && is_file($media->getPath()), 404);
        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function dependantPhoto(int $dependant, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        $record = $member->dependants()
            ->wherePivotNull('ended_at')
            ->where('member_dependants.id', $dependant)
            ->firstOrFail();
        $media = $record->getFirstMedia('profile-photo');
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function dependantIdCard(int $dependant, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        $record = $member->dependants()->wherePivotNull('ended_at')->where('member_dependants.id', $dependant)->firstOrFail();
        $primaryColor = data_get($organization->settings, 'branding.primary_color', data_get($organization->settings, 'theme.primary_color', '#174b3d'));
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#174b3d';

        $verificationUrl = URL::signedRoute('organization.member.dependants.id-card.verify', [
            'member' => $member->id,
            'dependant' => $record->id,
        ]);
        $qrCodeDataUri = $this->qrCodeDataUri($verificationUrl);

        return response()->view('member::organization.member-dependant-id-card', [
            'organization' => $organization,
            'member' => $member,
            'dependant' => $record,
            'primaryColor' => $primaryColor,
            'qrCodeDataUri' => $qrCodeDataUri,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * QR verification is available only to authenticated users who can view members
     * in the active organization. Signed URLs prevent tampering with record IDs.
     */
    public function verifyIdCard(int $member, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $record = Member::query()
            ->where('organization_id', $organization->id)
            ->with(['user.details', 'unit', 'memberships.membershipType'])
            ->findOrFail($member);
        $membership = $record->memberships()->with('membershipType')->latest('id')->first();
        $primaryColor = data_get($organization->settings, 'branding.primary_color', data_get($organization->settings, 'theme.primary_color', '#174b3d'));
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#174b3d';

        return response()->view('member::organization.member-id-card-verify', [
            'organization' => $organization,
            'member' => $record,
            'membership' => $membership,
            'dependant' => null,
            'primaryColor' => $primaryColor,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function verifyDependantIdCard(int $member, int $dependant, CurrentOrganization $currentOrganization)
    {
        $organization = $currentOrganization->get();
        $parent = Member::query()
            ->where('organization_id', $organization->id)
            ->with(['user', 'unit'])
            ->findOrFail($member);
        $dependantRecord = $parent->dependants()
            ->wherePivotNull('ended_at')
            ->where('member_dependants.organization_id', $organization->id)
            ->where('member_dependants.id', $dependant)
            ->firstOrFail();
        $primaryColor = data_get($organization->settings, 'branding.primary_color', data_get($organization->settings, 'theme.primary_color', '#174b3d'));
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) $primaryColor = '#174b3d';

        return response()->view('member::organization.member-id-card-verify', [
            'organization' => $organization,
            'member' => $parent,
            'membership' => $parent->memberships()->with('membershipType')->latest('id')->first(),
            'dependant' => $dependantRecord,
            'primaryColor' => $primaryColor,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function qrCodeDataUri(string $url): string
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(240)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build()
            ->getDataUri();
    }

    public function updateProfileDocument(Request $request, int $identification, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);

        $document = UserIdentification::query()
            ->where('user_id', $member->user_id)
            ->whereKey($identification)
            ->firstOrFail();

        $configuration = OrganizationIdentification::query()
            ->where('organization_id', $organization->id)
            ->where('identification_type_id', $document->identification_type_id)
            ->where('is_enabled', true)
            ->firstOrFail();

        $allowedSides = $configuration->document_requirements
            ?: ($configuration->requires_document ? ['document'] : []);
        $rules = [
            'identification_number' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
        foreach (['document', 'front', 'back'] as $side) {
            if (in_array($side, $allowedSides, true)) {
                $rules[$side] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];
            }
        }

        $data = $request->validate($rules);
        if ($configuration->is_required && blank($data['identification_number'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'identification_number' => 'A document number is required for this document type.',
            ]);
        }

        $document->update([
            'identification_number' => $data['identification_number'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        foreach ($allowedSides as $side) {
            if ($request->hasFile($side)) {
                $document->getMedia('identification-documents')
                    ->filter(fn ($media) => $media->getCustomProperty('document_side') === $side)
                    ->each(fn ($media) => $media->delete());
                $document->addMediaFromRequest($side)
                    ->withCustomProperties(['document_side' => $side])
                    ->toMediaCollection('identification-documents');
            }
        }

        return redirect()->route('organization.member.profile')
            ->with('status', 'Identification document updated.');
    }

    public function document(int $identification, string $side, CurrentOrganization $currentOrganization): StreamedResponse
    {
        $organization = $currentOrganization->get();
        $member = $this->profiles->findForOrganization((int) $organization->id, (int) auth()->id());
        abort_unless($member->user_id === auth()->id(), 403);
        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);

        $record = UserIdentification::query()->where('user_id', $member->user_id)->findOrFail($identification);
        $media = $record->media()->where('collection_name', 'identification-documents')->get()
            ->first(fn ($item) => $item->getCustomProperty('document_side') === $side);
        abort_unless($media, 404);
        abort_unless(Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()), 404);

        return response()->stream(function () use ($media): void {
            $stream = Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot());
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($media->file_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
