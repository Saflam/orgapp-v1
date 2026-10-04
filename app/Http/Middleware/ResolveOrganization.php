<?php

namespace App\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use App\Support\Organization\OrganizationResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function __construct(
        protected OrganizationResolver $resolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolver->resolve(
            $request->getHost()
        );

        if (! $organization) {
            abort(404, 'Organization not found.');
        }

        $this->currentOrganization->set($organization);

        return $next($request);
    }
}