<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Services\EventAttendanceAuditService;
use Modules\Events\Services\EventManagementService;

class EventAttendanceAuditController extends Controller
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly EventAttendanceAuditService $audit,
    ) {
    }

    public function index(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'in:entry,exit'],
            'pass_id' => ['sometimes', 'integer', 'min:1'],
            'attendee_id' => ['sometimes', 'integer', 'min:1'],
            'scanned_by' => ['sometimes', 'integer', 'min:1'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        $eventRecord = $this->events
            ->queryForOrganization($organization)
            ->findOrFail($event);

        $paginator = $this->audit->paginate(
            $organization,
            $eventRecord,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        );

        $items = $paginator->getCollection()->map(static function ($scan): array {
            return [
                'id' => $scan->id,
                'action' => $scan->action,
                'quantity' => $scan->quantity,
                'scanned_at' => $scan->scanned_at?->toIso8601String(),
                'scanner' => $scan->scanner ? [
                    'id' => $scan->scanner->id,
                    'name' => $scan->scanner->name,
                ] : null,
                'pass' => $scan->pass ? [
                    'id' => $scan->pass->id,
                    'status' => $scan->pass->status,
                    'capacity' => $scan->pass->capacity,
                    'participant' => $scan->pass->participant ? [
                        'id' => $scan->pass->participant->id,
                        'name' => $scan->pass->participant->name,
                        'participant_type' => $scan->pass->participant->participant_type,
                    ] : null,
                ] : null,
                'attendee' => $scan->attendee ? [
                    'id' => $scan->attendee->id,
                    'name' => $scan->attendee->name,
                    'category' => $scan->attendee->category,
                    'status' => $scan->attendee->status,
                ] : null,
            ];
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
