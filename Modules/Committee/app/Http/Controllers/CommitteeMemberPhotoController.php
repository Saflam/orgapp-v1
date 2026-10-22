<?php

namespace Modules\Committee\Http\Controllers;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;

class CommitteeMemberPhotoController
{
    public function show(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ) {
        $organization = $currentOrganization->get();
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $context = new AuthorizationContext((int) $organization->id);
        $user = $request->user();
        $hasCommitteePermission = $user && (
            $authorization->can($user, 'committee.view', $context)
            || $authorization->can($user, 'committee.manage', $context)
            || $authorization->can($user, 'committee.memberships.view', $context)
            || $authorization->can($user, 'committee.memberships.manage', $context)
        );
        $viewerMember = $user ? Member::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->first() : null;
        abort_unless($hasCommitteePermission || ($viewerMember && $user->isMember($organization)), 403);

        $memberRecord = Member::query()->where('organization_id', $organization->id)->with('user')->whereKey($member)->firstOrFail();
        if (! $hasCommitteePermission) {
            $today = today();
            $isVisibleCurrentCommitteeMember = \Modules\Committee\Models\CommitteeMembership::query()
                ->where('member_id', $memberRecord->id)
                ->where('status', 'active')
                ->where(function ($query) use ($today): void { $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today); })
                ->where(function ($query) use ($today): void { $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today); })
                ->whereHas('committeeTerm', function ($terms) use ($organization, $today, $viewerMember): void {
                    $terms->where('status', 'active')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
                        ->whereHas('committee', function ($committees) use ($organization, $viewerMember): void {
                            $committees->where('organization_id', $organization->id)->where('status', 'active')
                                ->where(function ($scope) use ($viewerMember): void {
                                    $scope->whereNull('unit_id');
                                    if ($viewerMember?->unit_id !== null) { $scope->orWhere('unit_id', $viewerMember->unit_id); }
                                });
                        });
                })->exists();
            abort_unless($isVisibleCurrentCommitteeMember, 404);
        }
        $media = $memberRecord->user?->getFirstMedia('profile-photo');
        abort_unless($media, 404);
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
