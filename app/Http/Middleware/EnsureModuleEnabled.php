<?php

namespace App\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\OrganizationModuleService;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly OrganizationModuleService $moduleService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $module,
    ): Response {
        $organization = $this->currentOrganization->get();

        if (! $this->moduleService->isEnabled($organization, $module)) {
            abort(
                403,
                "The [{$module}] module is not enabled for this organization."
            );
        }

        return $next($request);
    }
}