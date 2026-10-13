<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Services\EventAttendanceService;

class EventAttendanceController extends Controller
{
    public function __construct(private readonly EventAttendanceService $attendance)
    {
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:40', 'max:128'],
            'action' => ['required', Rule::in(['entry', 'exit'])],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'attendee_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'attendee_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }

        $result = $this->attendance->scan(
            $organization,
            $validated['token'],
            $validated['action'],
            (int) ($validated['quantity'] ?? 1),
            (int) $request->user()->getAuthIdentifier(),
            $validated['attendee_ids'] ?? [],
        );

        return response()->json([
            'data' => [
                'scan' => [
                    'id' => $result['scan']->id,
                    'action' => $result['scan']->action,
                    'quantity' => $result['scan']->quantity,
                    'scanned_at' => $result['scan']->scanned_at,
                ],
                'event' => ['id' => $result['event']->id, 'title' => $result['event']->title],
                'participant' => ['id' => $result['participant']->id, 'name' => $result['participant']->name],
                'pass' => [
                    'id' => $result['pass']->id,
                    'capacity' => $result['pass']->capacity,
                    'current_inside' => $result['pass']->current_inside,
                    'remaining_capacity' => $result['pass']->capacity - $result['pass']->current_inside,
                ],
            ],
        ]);
    }
}
