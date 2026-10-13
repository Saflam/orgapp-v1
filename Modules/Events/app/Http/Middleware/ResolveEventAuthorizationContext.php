<?php

namespace Modules\Events\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Organization;
use Modules\Events\Data\EventAuthorizationContext;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventPass;
use Symfony\Component\HttpFoundation\Response;

class ResolveEventAuthorizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            abort(404, 'Organization not found.');
        }

        $eventParameter = $request->route('event');
        $eventId = is_numeric($eventParameter) ? (int) $eventParameter : null;

        // Attendance scans do not carry an event route parameter. Resolve their
        // event from the opaque pass token so unit-scoped scan permissions are
        // evaluated against the event's actual unit rather than the organization.
        if ($eventId === null && $request->routeIs('*.events.attendance.scan')) {
            $token = $request->input('token');
            if (is_string($token) && strlen($token) >= 40 && strlen($token) <= 128) {
                $pass = EventPass::query()
                    ->where('organization_id', $organization->id)
                    ->where('token_hash', hash('sha256', $token))
                    ->first(['event_id']);

                $eventId = $pass?->event_id;
            }
        }

        // Collection, creation, category, and malformed-token requests remain
        // organization-context operations. The controller/service performs its
        // own resource validation for those requests.
        if ($eventId === null) {
            return $next($request);
        }

        $event = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail((int) $eventId);

        $request->attributes->set(
            'authorization.context',
            EventAuthorizationContext::fromEvent($event),
        );

        return $next($request);
    }
}
