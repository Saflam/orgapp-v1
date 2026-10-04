<?php

namespace App\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\OrganizationAccessService;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected OrganizationAccessService $accessService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        $organization = $this->currentOrganization->get();

        $this->accessService->ensureAccess(
            $request->user(),
            $organization
        );

        return $next($request);
    }
}