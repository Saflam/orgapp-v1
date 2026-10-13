<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Http\Requests\Api\StoreEventRequest;
use Modules\Events\Http\Requests\Api\UpdateEventRequest;
use Modules\Events\Models\Event;
use Modules\Events\Services\EventManagementService;
use Modules\Events\Services\EventParticipantService;

class EventController extends Controller
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly EventParticipantService $participants,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization();
        $query = $this->events->queryForOrganization($organization);

        if (! $request->attributes->get('events.view.organization_wide', false)) {
            $unitIds = $request->attributes->get('events.view.unit_ids', []);
            $query->whereIn('unit_id', $unitIds);
        }

        $events = $query
            ->with(['category', 'unit'])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return response()->json($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->events->create(
            $this->organization(),
            (int) $request->user()->getAuthIdentifier(),
            $request->validated(),
        );

        return response()->json(['data' => $event], 201);
    }

    public function show(int $event): JsonResponse
    {
        $record = $this->events->queryForOrganization($this->organization())
            ->with(['category', 'unit', 'creator'])
            ->findOrFail($event);

        return response()->json(['data' => $record]);
    }

    public function update(UpdateEventRequest $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $record = $this->events->queryForOrganization($organization)->findOrFail($event);
        $updated = $this->events->update($record, $organization, $request->validated());

        return response()->json(['data' => $updated]);
    }

    public function publish(int $event): JsonResponse
    {
        $organization = $this->organization();
        $record = DB::transaction(function () use ($organization, $event): Event {
            $record = $this->events->queryForOrganization($organization)->lockForUpdate()->findOrFail($event);

            if ($record->status !== EventStatus::Draft) {
                throw ValidationException::withMessages(['event' => 'Only draft events can be published.']);
            }
            if (! $record->starts_at || ! $record->ends_at || $record->ends_at->lessThanOrEqualTo($record->starts_at)) {
                throw ValidationException::withMessages(['event' => 'The event schedule is invalid.']);
            }

            $record->status = EventStatus::Published;
            $record->published_at = now();
            $record->save();

            return $record->refresh()->load(['category', 'unit']);
        });

        return response()->json(['data' => $record]);
    }

    public function cancel(int $event): JsonResponse
    {
        $organization = $this->organization();

        $wasCancelled = false;
        $record = DB::transaction(function () use ($organization, $event, &$wasCancelled): Event {
            $record = $this->events->queryForOrganization($organization)
                ->lockForUpdate()
                ->findOrFail($event);

            if ($record->status === EventStatus::Cancelled) {
                return $record;
            }

            $wasCancelled = true;
            $record->status = EventStatus::Cancelled;
            $record->cancelled_at = now();
            $record->save();

            // Revoke active passes that have nobody inside. Passes with occupants remain
            // active temporarily so the attendance service can record their exits.
            // The scan history and current_inside counters remain intact for audit.
            $record->passes()
                ->where('status', 'active')
                ->where('current_inside', 0)
                ->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);

            return $record->refresh();
        });

        if ($wasCancelled) {
            $this->participants->notifyEventCancellation($organization, $record);
        }

        return response()->json(['data' => $record]);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }
        return $organization;
    }
}
