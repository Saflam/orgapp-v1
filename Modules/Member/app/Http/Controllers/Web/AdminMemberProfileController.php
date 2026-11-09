<?php

namespace Modules\Member\Http\Controllers\Web;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Member\Http\Requests\UpdateMemberProfileRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\AdminMemberNote;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Models\MemberDependantRelationship;
use Modules\Member\Models\Membership;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Services\MemberProfileService;
use Modules\Member\Services\MemberDependantService;
use Modules\Member\Services\MemberDependantRelationshipService;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\MemberProfileSectionExtensionRegistry;

class AdminMemberProfileController extends Controller
{
    public function show(int $member, CurrentOrganization $currentOrganization, AuthorizationService $authorization)
    {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember($member, $currentOrganization, $authorization, 'view');

        $emergencyContacts = $memberRecord->user?->emergencyContacts ?? collect();
        $identificationDocuments = UserIdentification::query()
            ->where('user_id', $memberRecord->user_id)
            ->with(['identificationType', 'media'])
            ->orderBy('id')
            ->get();

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));

        $activeMembership = $memberRecord->memberships
            ->where('status', 'active')
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();
        $membershipForEdit = $activeMembership
            ?? $memberRecord->memberships->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)->first();
        $profilePhotoUrl = $memberRecord->user?->getFirstMediaUrl('profile-photo');
        $adminNotes = $memberRecord->adminNotes()->with('author')->latest('created_at')->get();
        $paymentObligations = $memberRecord->paymentObligations()
            ->where('organization_id', $organization->id)
            ->orderByDesc('due_at')
            ->get();
        $applications = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($memberRecord): void {
                $query->where('member_id', $memberRecord->id)
                    ->orWhere('user_id', $memberRecord->user_id);
            })
            ->with(['membershipType', 'statusHistories'])
            ->latest('created_at')
            ->get();
        $moduleSections = app(MemberProfileSectionExtensionRegistry::class)
            ->sections($organization, $memberRecord);

        // Show only currently active family relationships on the admin profile.
        // Use an explicit query rather than loadMissing so ended relationships
        // cannot leak in through a previously loaded relation.
        $memberRecord->setRelation(
            'dependants',
            $memberRecord->dependants()
                ->wherePivotNull('ended_at')
                ->with('media')
                ->orderBy('member_dependants.first_name')
                ->orderBy('member_dependants.last_name')
                ->get()
        );

        return response()
            ->view('member::organization.admin-member-profile', compact(
                'organization', 'memberRecord', 'activeMembership', 'membershipForEdit', 'profilePhotoUrl', 'canEdit',
                'adminNotes', 'paymentObligations', 'applications', 'moduleSections',
                'emergencyContacts', 'identificationDocuments'
            ))
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Stream a member identification proof only after resolving the member in
     * the current organization and checking the caller's member-view access.
     */
    public function document(
        int $member,
        int $identification,
        string $side,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): StreamedResponse {
        [, $memberRecord] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'view',
        );

        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);

        $record = UserIdentification::query()
            ->where('user_id', $memberRecord->user_id)
            ->whereKey($identification)
            ->firstOrFail();

        $media = $record->getMedia('identification-documents')
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
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Stream a dependant's profile photo after checking tenant, member access,
     * and that the dependant is actively linked to this member.
     */
    public function dependantPhoto(
        int $member,
        int $dependant,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ) {
        [, $memberRecord] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'view',
        );

        $record = $memberRecord->dependants()
            ->wherePivotNull('ended_at')
            ->where('member_dependants.id', $dependant)
            ->firstOrFail();

        $media = $record->getFirstMedia('profile-photo');
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Add a dependant and an active relationship from the admin member profile. */
    public function storeDependant(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberDependantService $dependantService,
        MemberDependantRelationshipService $relationshipService,
    ): RedirectResponse {
        [$organization, $memberRecord] = $this->resolveAuthorizedMember(
            $member, $currentOrganization, $authorization, 'edit'
        );

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'relationship_type' => ['required', 'string', 'in:spouse,child,parent,sibling,guardian,other'],
            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        try {
            DB::transaction(function () use ($organization, $memberRecord, $validated, $dependantService, $relationshipService): void {
                $dependant = $dependantService->create(
                    organizationId: (int) $organization->id,
                    data: [
                        'first_name' => $validated['first_name'],
                        'middle_name' => $validated['middle_name'] ?? null,
                        'last_name' => $validated['last_name'] ?? null,
                        'date_of_birth' => $validated['date_of_birth'] ?? null,
                        'gender' => $validated['gender'] ?? null,
                    ],
                );

                $relationshipService->add(
                    member: $memberRecord,
                    dependant: $dependant,
                    relationshipType: $validated['relationship_type'],
                    startedAt: $validated['started_at'] ?? null,
                );
            });
        } catch (\DomainException $exception) {
            return back()->withErrors(['relationship_type' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('member.profile', $memberRecord->id)->with('status', 'Dependant added successfully.');
    }

    /** Update a dependant and its active relationship from the admin profile. */
    public function updateDependant(
        Request $request,
        int $member,
        int $dependant,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberDependantService $dependantService,
        MemberDependantRelationshipService $relationshipService,
    ): RedirectResponse {
        [$organization, $memberRecord] = $this->resolveAuthorizedMember(
            $member, $currentOrganization, $authorization, 'edit'
        );

        $dependantRecord = $memberRecord->dependants()
            ->wherePivotNull('ended_at')
            ->where('member_dependants.organization_id', $organization->id)
            ->where('member_dependants.id', $dependant)
            ->firstOrFail();

        $relationship = MemberDependantRelationship::query()
            ->where('member_id', $memberRecord->id)
            ->where('dependant_id', $dependantRecord->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'relationship_type' => ['required', 'string', 'in:spouse,child,parent,sibling,guardian,other'],
            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        try {
            DB::transaction(function () use ($dependantService, $relationshipService, $dependantRecord, $relationship, $validated): void {
                $dependantService->update($dependantRecord, [
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                ]);

                $relationshipService->update(
                    relationship: $relationship,
                    relationshipType: $validated['relationship_type'],
                    startedAt: $validated['started_at'] ?? null,
                );
            });
        } catch (\DomainException $exception) {
            return back()->withErrors(['relationship_type' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('member.profile', $memberRecord->id)->with('status', 'Dependant updated successfully.');
    }

    /** Remove a dependant from the active family list by ending the relationship; preserve history. */
    public function destroyDependant(
        int $member,
        int $dependant,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberDependantRelationshipService $relationshipService,
    ): RedirectResponse {
        [, $memberRecord] = $this->resolveAuthorizedMember(
            $member, $currentOrganization, $authorization, 'edit'
        );

        $dependantRecord = $memberRecord->dependants()
            ->wherePivotNull('ended_at')
            ->where('member_dependants.id', $dependant)
            ->firstOrFail();

        $relationship = MemberDependantRelationship::query()
            ->where('member_id', $memberRecord->id)
            ->where('dependant_id', $dependantRecord->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        try {
            $relationshipService->end($relationship);
        } catch (\DomainException $exception) {
            return back()->withErrors(['family' => $exception->getMessage()]);
        }

        return redirect()->route('member.profile', $memberRecord->id)->with('status', 'Dependant removed from the active family list. Relationship history has been preserved.');
    }

    public function downloadPdf(
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ) {
        [$organization, $memberRecord] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'view'
        );

        $memberRecord->loadMissing([
            'user.details',
            'user.addresses',
            'user.phoneNumbers',
            'user.emergencyContacts',
            'unit',
            'memberships.membershipType',
        ]);

        // Match the profile page: only active dependant relationships are shown.
        $memberRecord->setRelation(
            'dependants',
            $memberRecord->dependants()
                ->wherePivotNull('ended_at')
                ->with('media')
                ->orderBy('member_dependants.first_name')
                ->orderBy('member_dependants.last_name')
                ->get()
        );

        $activeMembership = $memberRecord->memberships
            ->where('status', 'active')
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();

        $identificationDocuments = UserIdentification::query()
            ->where('user_id', $memberRecord->user_id)
            ->with(['identificationType', 'media'])
            ->orderBy('id')
            ->get();

        $paymentObligations = $memberRecord->paymentObligations()
            ->where('organization_id', $organization->id)
            ->orderByDesc('due_at')
            ->get();

        $applications = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($memberRecord): void {
                $query->where('member_id', $memberRecord->id)
                    ->orWhere('user_id', $memberRecord->user_id);
            })
            ->with(['membershipType', 'statusHistories.actor'])
            ->latest('created_at')
            ->get();

        $moduleSections = app(MemberProfileSectionExtensionRegistry::class)
            ->sections($organization, $memberRecord);

        // Embed the private profile photo as data so the PDF does not need a public URL.
        $profilePhotoData = null;
        $profilePhoto = $memberRecord->user?->getFirstMedia('profile-photo');
        if ($profilePhoto && is_file($profilePhoto->getPath())) {
            $mime = $profilePhoto->mime_type ?: 'image/jpeg';
            $profilePhotoData = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($profilePhoto->getPath()));
        }

        $pdf = Pdf::loadView('member::organization.admin-member-profile-pdf', [
            'organization' => $organization,
            'memberRecord' => $memberRecord,
            'activeMembership' => $activeMembership,
            'identificationDocuments' => $identificationDocuments,
            'paymentObligations' => $paymentObligations,
            'applications' => $applications,
            'moduleSections' => $moduleSections,
            'profilePhotoData' => $profilePhotoData,
        ])->setPaper('a4');

        $safeMemberNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($memberRecord->membership_number ?: $memberRecord->id));

        return $pdf->download('member-profile-'.$safeMemberNumber.'.pdf')
            ->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function edit(int $member, CurrentOrganization $currentOrganization, AuthorizationService $authorization)
    {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember($member, $currentOrganization, $authorization, 'edit');

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));
        abort_unless($canEdit, 403);

        $profilePhotoUrl = $memberRecord->user?->getFirstMediaUrl('profile-photo');

        return response()
            ->view('member::organization.admin-member-profile-edit', compact('organization', 'memberRecord', 'profilePhotoUrl'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateMemberProfileRequest $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberProfileService $profiles,
    ): RedirectResponse {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember($member, $currentOrganization, $authorization, 'edit');

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));
        abort_unless($canEdit, 403);

        $validated = $request->validated();

        Validator::make($validated, [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($memberRecord->user_id),
            ],
        ])->validate();

        // MemberProfileService handles the uploaded profile photo as part of the
        // profile update. Do not process the same temporary upload a second time.
        $profiles->update($memberRecord, $validated);

        return redirect()
            ->route('member.profile', $memberRecord->id)
            ->with('status', 'Member profile updated successfully.');
    }

    public function editMembership(
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ) {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'edit',
        );

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));
        abort_unless($canEdit, 403);

        $membershipRecord = $memberRecord->memberships
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();

        return response()
            ->view('member::organization.admin-member-membership-edit', [
                'organization' => $organization,
                'memberRecord' => $memberRecord,
                'membershipRecord' => $membershipRecord,
                'statuses' => MembershipStatus::cases(),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateMembership(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): RedirectResponse {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'edit',
        );

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));
        abort_unless($canEdit, 403);

        $membershipRecord = $memberRecord->memberships
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();

        $validated = $request->validate([
            'membership_number' => [
                'required', 'string', 'max:255',
                Rule::unique('members', 'membership_number')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id))
                    ->ignore($memberRecord->id),
            ],
            'status' => ['nullable', Rule::in(array_column(MembershipStatus::cases(), 'value'))],
            'status_reason' => [
                'nullable', 'string', 'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (in_array($request->input('status'), ['suspended', 'deceased'], true) && trim((string) $value) === '') {
                        $fail('Please provide a reason when suspending or marking a member as deceased.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($memberRecord, $membershipRecord, $validated): void {
            $memberRecord->membership_number = trim($validated['membership_number']);
            $memberRecord->save();

            // A member may not yet have a membership record. In that case, the
            // membership number can still be maintained, but no status is changed.
            if ($membershipRecord && isset($validated['status'])) {
                $membershipRecord->status = $validated['status'];
                $membershipRecord->status_reason = trim((string) ($validated['status_reason'] ?? '')) ?: null;
                $membershipRecord->save();
            }
        });

        return redirect()
            ->route('member.profile', $memberRecord->id)
            ->with('status', 'Membership details updated successfully.');
    }

    /**
     * Save a module-contributed member profile section (for example, Saradhi details).
     * The owning module validates and persists its own fields through the extension contract.
     */
    public function updateModuleSection(
        Request $request,
        int $member,
        string $module,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberProfileSectionExtensionRegistry $extensions,
    ): RedirectResponse {
        [$organization, $memberRecord] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'edit'
        );

        $extensions->update($module, $request, $organization, $memberRecord);

        return redirect()
            ->route('member.profile', $memberRecord->id)
            ->with('status', ucfirst($module).' profile details updated successfully.');
    }

    public function storeNote(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): RedirectResponse {
        [$organization, $memberRecord] = $this->resolveAuthorizedMember(
            $member,
            $currentOrganization,
            $authorization,
            'edit'
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        AdminMemberNote::query()->create([
            'organization_id' => $organization->id,
            'member_id' => $memberRecord->id,
            'author_user_id' => auth()->id(),
            'body' => trim($validated['body']),
        ]);

        return redirect()
            ->route('member.profile', $memberRecord->id)
            ->with('status', 'Admin note added successfully.');
    }

    /** @return array{0: Organization, 1: Member, 2: ?AuthorizationContext} */
    private function resolveAuthorizedMember(
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        string $access,
    ): array {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);

        // Resolve the member inside the active tenant before checking permissions.
        $memberRecord = Member::query()
            ->where('organization_id', $organization->id)
            ->with([
                'user.details', 'user.addresses', 'user.phoneNumbers', 'user.emergencyContacts',
                'unit', 'memberships.membershipType',
            ])
            ->findOrFail($member);

        $user = auth()->user();
        abort_unless($user, 403);

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $permission = $access === 'edit' ? 'members.update' : 'members.view';
        $canViewOrganization = $authorization->can($user, $permission, $organizationContext)
            || ($access === 'view' && $authorization->can($user, 'members.manage', $organizationContext));

        $unitContext = $memberRecord->unit_id
            ? new AuthorizationContext((int) $organization->id, (int) $memberRecord->unit_id)
            : null;
        $canViewUnit = $unitContext !== null && (
            $authorization->can($user, $permission, $unitContext)
            || ($access === 'view' && $authorization->can($user, 'members.manage', $unitContext))
        );

        abort_unless($canViewOrganization || $canViewUnit, 403);

        return [$organization, $memberRecord, $unitContext];
    }
}
