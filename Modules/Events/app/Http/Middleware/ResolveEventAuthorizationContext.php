<?php

namespace Modules\Events\Http\Middleware;

use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
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

        // Event creation has no event ID yet. For a unit-scoped creation,
        // resolve the submitted unit before the permission middleware runs so
        // a unit-scoped events.manage assignment can create events only in its
        // own unit. Central-event creation remains organization-scoped.
        if ($eventId === null && $request->routeIs('*.events.store')
            && $request->input('scope') === 'unit'
            && is_numeric($request->input('unit_id'))) {
            $unit = Unit::query()
                ->where('organization_id', $organization->id)
                ->find((int) $request->input('unit_id'));

            if ($unit !== null) {
                $request->attributes->set(
                    'authorization.context',
                    new AuthorizationContext((int) $organization->id, (int) $unit->id),
                );
            }

            return $next($request);
        }

        // Collection, category, central-event creation, and malformed-token
        // requests remain organization-context operations. The controller/service
        // performs its own resource validation for those requests.
        if ($eventId === null) {
            return $next($request);
        }

        $event = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail((int) $eventId);

        // Updating an event is authorized against its current unit by the route
        // permission middleware. If the request also changes its scope or unit,
        // independently authorize the destination so a Unit A manager cannot
        // move an event into Unit B or convert it into a central event.
        if ($request->routeIs('*.events.update')
            && ($request->exists('scope') || $request->exists('unit_id'))) {
            $targetScope = (string) ($request->input('scope') ?? $event->scope?->value ?? $event->scope);
            $targetUnitId = $targetScope === 'unit'
                ? (int) ($request->input('unit_id') ?? $event->unit_id ?? 0)
                : null;

            if ($targetScope === 'central') {
                $targetContext = new AuthorizationContext((int) $organization->id);
            } elseif ($targetScope === 'unit' && $targetUnitId > 0) {
                $targetUnit = Unit::query()
                    ->where('organization_id', $organization->id)
                    ->find($targetUnitId);

                // Invalid/foreign unit IDs are left to the request/service
                // validation layer, which returns the standard 422 response.
                $targetContext = $targetUnit === null
                    ? null
                    : new AuthorizationContext((int) $organization->id, (int) $targetUnit->id);
            } else {
                $targetContext = null;
            }

            if ($targetContext !== null && ! app(AuthorizationService::class)->can(
                $request->user(),
                'events.manage',
                $targetContext,
            )) {
                abort(403, 'You do not have permission to move this event to the selected scope.');
            }
        }

        $request->attributes->set(
            'authorization.context',
            EventAuthorizationContext::fromEvent($event),
        );

        return $next($request);
    }
}
