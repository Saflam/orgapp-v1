<?php

namespace Modules\Member\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent a browser-submitted membership application from impersonating
 * another user by replacing user_id with the authenticated account ID.
 */
class UseAuthenticatedApplicant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $request->merge([
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return $next($request);
    }
}
