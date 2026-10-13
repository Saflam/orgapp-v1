<?php

namespace Modules\Events\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventAttendanceCorrection;
use Modules\Events\Models\EventPass;

class EventAttendanceCorrectionService
{
    public function correct(Organization $organization, int $eventId, int $passId, int $targetInside, int $userId, string $reason): EventAttendanceCorrection
    {
        return DB::transaction(function () use ($organization, $eventId, $passId, $targetInside, $userId, $reason): EventAttendanceCorrection {
            $event = Event::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($eventId);
            $pass = EventPass::query()->where('organization_id', $organization->id)->where('event_id', $event->id)->lockForUpdate()->findOrFail($passId);

            if ($pass->attendees()->exists()) {
                throw ValidationException::withMessages(['pass' => 'This pass uses individual attendee tracking. Correct individual attendee history through a dedicated attendee-level correction workflow.']);
            }
            if ($targetInside < 0 || $targetInside > $pass->capacity) {
                throw ValidationException::withMessages(['current_inside' => 'Corrected occupancy must be between zero and the pass capacity.']);
            }
            if ($event->venue_capacity !== null) {
                $otherInside = (int) EventPass::query()->where('organization_id', $organization->id)->where('event_id', $event->id)->where('id', '!=', $pass->id)->sum('current_inside');
                if ($otherInside + $targetInside > $event->venue_capacity) {
                    throw ValidationException::withMessages(['current_inside' => 'The correction would exceed the event venue capacity.']);
                }
            }

            $before = (int) $pass->current_inside;
            $delta = $targetInside - $before;
            $pass->current_inside = $targetInside;
            $pass->save();

            return EventAttendanceCorrection::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'event_pass_id' => $pass->id,
                'corrected_by' => $userId,
                'before_inside' => $before,
                'after_inside' => $targetInside,
                'occupancy_delta' => $delta,
                'reason' => trim($reason),
                'corrected_at' => now(),
            ]);
        });
    }

    public function correctAttendee(Organization $organization, int $eventId, int $passId, int $attendeeId, string $targetStatus, int $userId, string $reason): EventAttendanceCorrection
    {
        return DB::transaction(function () use ($organization, $eventId, $passId, $attendeeId, $targetStatus, $userId, $reason): EventAttendanceCorrection {
            $event = Event::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($eventId);
            $pass = EventPass::query()->where('organization_id', $organization->id)->where('event_id', $event->id)->lockForUpdate()->findOrFail($passId);
            $attendee = $pass->attendees()->where('organization_id', $organization->id)->where('event_id', $event->id)->whereKey($attendeeId)->lockForUpdate()->firstOrFail();

            if (! in_array($targetStatus, ['inside', 'outside'], true)) {
                throw ValidationException::withMessages(['status' => 'The corrected attendee status must be inside or outside.']);
            }
            if ($pass->attendanceScans()->whereNull('event_pass_attendee_id')->exists()) {
                throw ValidationException::withMessages(['attendee_id' => 'Individual attendee corrections cannot be applied to a pass with quantity-based scan history.']);
            }

            $beforeInside = (int) $pass->current_inside;
            $beforeStatus = $attendee->status;
            $delta = ($targetStatus === 'inside' ? 1 : 0) - ($beforeStatus === 'inside' ? 1 : 0);
            $afterInside = $beforeInside + $delta;

            if ($afterInside < 0 || $afterInside > (int) $pass->capacity) {
                throw ValidationException::withMessages(['status' => 'The correction would put pass occupancy outside its allowed capacity.']);
            }
            if ($event->venue_capacity !== null) {
                $otherInside = (int) EventPass::query()->where('organization_id', $organization->id)->where('event_id', $event->id)->where('id', '!=', $pass->id)->sum('current_inside');
                if ($otherInside + $afterInside > (int) $event->venue_capacity) {
                    throw ValidationException::withMessages(['status' => 'The correction would exceed the event venue capacity.']);
                }
            }

            $attendee->status = $targetStatus;
            if ($targetStatus === 'inside') {
                $attendee->first_entered_at ??= now();
                $attendee->last_entered_at = now();
                $attendee->entry_count = max(1, (int) $attendee->entry_count);
            } else {
                $attendee->last_exited_at = now();
            }
            $attendee->save();
            $pass->current_inside = $afterInside;
            $pass->save();

            return EventAttendanceCorrection::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'event_pass_id' => $pass->id,
                'event_pass_attendee_id' => $attendee->id,
                'corrected_by' => $userId,
                'before_inside' => $beforeInside,
                'after_inside' => $afterInside,
                'occupancy_delta' => $delta,
                'before_attendee_status' => $beforeStatus,
                'after_attendee_status' => $targetStatus,
                'reason' => trim($reason),
                'corrected_at' => now(),
            ]);
        });
    }

}
