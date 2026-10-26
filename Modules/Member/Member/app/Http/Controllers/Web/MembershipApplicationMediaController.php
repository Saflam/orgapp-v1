<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationMediaController
{
    /** Stream private application media only to an authorized member of the same organization. */
    public function show(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        int $applicationId,
        int $mediaId,
    ) {
        $organization = $currentOrganization->get();
        abort_unless($organization !== null, 404);

        $application = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($applicationId);


        abort_unless($authorization->can(
            $request->user(),
            'membership.application.view',
            new AuthorizationContext((int) $organization->id, $application->unitId()),
        ), 403);

        $media = $application->getMedia('profile-photo')->firstWhere('id', $mediaId)
            ?? $application->getMedia('application-documents')->firstWhere('id', $mediaId);
        abort_unless($media !== null, 404);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
