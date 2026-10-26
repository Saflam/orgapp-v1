<?php

namespace Modules\Member\Http\Controllers\Web;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\AdminMemberNote;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberProfileService;

class AdminMemberProfileController extends Controller
{
    public function show(int $member, CurrentOrganization $currentOrganization, AuthorizationService $authorization)
    {
        [$organization, $memberRecord, $unitContext] = $this->resolveAuthorizedMember($member, $currentOrganization, $authorization, 'view');

        $organizationContext = new AuthorizationContext((int) $organization->id);
        $canEdit = $authorization->can(auth()->user(), 'members.update', $organizationContext)
            || ($unitContext !== null && $authorization->can(auth()->user(), 'members.update', $unitContext));

        $activeMembership = $memberRecord->memberships
            ->where('status', 'active')
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();
        $profilePhotoUrl = $memberRecord->user?->getFirstMediaUrl('profile-photo');
        $adminNotes = $memberRecord->adminNotes()->with('author')->latest('created_at')->get();

        return response()
            ->view('member::organization.admin-member-profile', compact(
                'organization', 'memberRecord', 'activeMembership', 'profilePhotoUrl', 'canEdit', 'adminNotes'
            ))
            ->header('Cache-Control', 'private, no-store');
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

        // Export only profile information. Internal notes and identification documents
        // are intentionally excluded from this downloadable artifact.
        $memberRecord->loadMissing([
            'user.details',
            'user.addresses',
            'unit',
            'memberships.membershipType',
        ]);

        $activeMembership = $memberRecord->memberships
            ->where('status', 'active')
            ->sortByDesc(fn ($membership) => $membership->starts_at?->timestamp ?? 0)
            ->first();

        $pdf = Pdf::loadView('member::organization.admin-member-profile-pdf', [
            'organization' => $organization,
            'memberRecord' => $memberRecord,
            'activeMembership' => $activeMembership,
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
        Request $request,
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($memberRecord->user_id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
        ]);

        $profiles->update($memberRecord, $validated);

        return redirect()
            ->route('member.profile', $memberRecord->id)
            ->with('status', 'Member profile updated successfully.');
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
