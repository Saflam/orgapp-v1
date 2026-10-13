<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventPassAttendee;

class EventPassAttendeeController extends Controller
{
    public function store(Request $request, int $event, int $pass): JsonResponse
    {
        $validated = $request->validate([
            'attendees' => ['required', 'array', 'min:1', 'max:100'],
            'attendees.*.name' => ['required', 'string', 'max:200'],
            'attendees.*.email' => ['nullable', 'email', 'max:254', 'distinct:ignore_case'],
            'attendees.*.category' => ['sometimes', 'string', Rule::in(['member', 'guest', 'vip', 'volunteer', 'other'])],
        ]);

        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }

        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);

        $records = DB::transaction(function () use ($organization, $eventRecord, $pass, $validated, $request) {
            $passRecord = EventPass::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $eventRecord->id)
                ->whereKey($pass)
                ->lockForUpdate()
                ->firstOrFail();

            if ($passRecord->status !== 'active') {
                throw ValidationException::withMessages(['pass' => 'Attendees can only be added to an active pass.']);
            }

            if ($passRecord->current_inside > 0 || $passRecord->attendanceScans()->exists()) {
                throw ValidationException::withMessages(['attendees' => 'The attendee roster cannot be changed after attendance scanning begins.']);
            }

            $existing = $passRecord->attendees()->count();
            if ($existing + count($validated['attendees']) > $passRecord->capacity) {
                throw ValidationException::withMessages(['attendees' => 'The attendee roster cannot exceed the pass capacity.']);
            }

            $created = [];
            foreach ($validated['attendees'] as $data) {
                $created[] = EventPassAttendee::query()->create([
                    'organization_id' => $organization->id,
                    'event_id' => $eventRecord->id,
                    'event_pass_id' => $passRecord->id,
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'category' => $data['category'] ?? 'guest',
                    'status' => 'outside',
                    'created_by' => $request->user()->getAuthIdentifier(),
                ]);
            }

            return $created;
        });

        return response()->json([
            'data' => array_map(fn (EventPassAttendee $attendee): array => [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'category' => $attendee->category,
                'status' => $attendee->status,
            ], $records),
        ], 201);
    }
}
