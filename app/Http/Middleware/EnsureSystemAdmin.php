<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAdmin
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if ($user === null || ! $user->is_system_admin) {
            abort(
                403,
                'Only the system administrator can access this resource.'
            );
        }

        return $next($request);
    }
}