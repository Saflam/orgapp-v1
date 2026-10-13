<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Services\EventAttendanceCorrectionService;
use Modules\Events\Models\EventAttendanceCorrection;

class EventAttendanceCorrectionController extends Controller
{
    public function __construct(private readonly EventAttendanceCorrectionService $corrections) {}


    public function index(int $event): JsonResponse
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }
        $corrections = EventAttendanceCorrection::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event)
            ->with(['corrector:id,name,email', 'pass:id,event_id,event_participant_id', 'attendee:id,name,email,category,status'])
            ->orderByDesc('corrected_at')->orderByDesc('id')->paginate(25);
        return response()->json($corrections);
    }

    public function store(Request $request, int $event, int $pass): JsonResponse
    {
        $data = $request->validate([
            'current_inside' => [
                'required_without:attendee_id',
                'integer',
                'min:0',
                'max:65535',
            ],
            'attendee_id' => [
                'required_without:current_inside',
                'integer',
                'min:1',
            ],
            'status' => [
                'required_with:attendee_id',
                'string',
                'in:inside,outside',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ]);

        if (
            array_key_exists('attendee_id', $data)
            && array_key_exists('current_inside', $data)
        ) {
            throw ValidationException::withMessages([
                'correction' => 'Provide either attendee_id or current_inside, not both.',
            ]);
        }

        if (
            ! array_key_exists('attendee_id', $data)
            && $request->exists('status')
        ) {
            throw ValidationException::withMessages([
                'status' => 'The status field is only allowed for attendee-level corrections.',
            ]);
        }
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }
        $userId = (int) $request->user()->getAuthIdentifier();
        $correction = array_key_exists('attendee_id', $data)
            ? $this->corrections->correctAttendee($organization, $event, $pass, (int) $data['attendee_id'], $data['status'], $userId, $data['reason'])
            : $this->corrections->correct($organization, $event, $pass, (int) $data['current_inside'], $userId, $data['reason']);
        return response()->json(['data' => $correction], 201);
    }
}
