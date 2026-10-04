<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\AuthorizationContextResolver;
use Modules\Core\Services\AuthorizationService;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function __construct(
        protected AuthorizationContextResolver $contextResolver,
        protected AuthorizationService $authorizationService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $permission,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $context = $this->contextResolver->resolve($request);

        if (! $this->authorizationService->can(
            $user,
            $permission,
            $context,
        )) {
            abort(403);
        }

        return $next($request);
    }
}