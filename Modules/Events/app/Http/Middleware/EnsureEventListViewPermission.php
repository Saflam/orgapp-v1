<?php

namespace Modules\Events\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventListViewPermission
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $this->currentOrganization->get();

        if ($user === null) {
            abort(401);
        }

        if (! $organization instanceof Organization) {
            abort(404, 'Organization not found.');
        }

        if ($this->authorization->can(
            $user,
            'events.view',
            new AuthorizationContext((int) $organization->id),
        )) {
            $request->attributes->set('events.view.organization_wide', true);
            $request->attributes->set('events.view.unit_ids', []);
            return $next($request);
        }

        $allowedUnitIds = [];
        Unit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($unitId) use ($user, $organization, &$allowedUnitIds): void {
                if ($this->authorization->can(
                    $user,
                    'events.view',
                    new AuthorizationContext((int) $organization->id, (int) $unitId),
                )) {
                    $allowedUnitIds[] = (int) $unitId;
                }
            });

        if ($allowedUnitIds === []) {
            abort(403);
        }

        $request->attributes->set('events.view.organization_wide', false);
        $request->attributes->set('events.view.unit_ids', $allowedUnitIds);

        return $next($request);
    }
}
