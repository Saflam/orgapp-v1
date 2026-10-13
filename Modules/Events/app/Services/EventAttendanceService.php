<?php

namespace Modules\Events\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventVolunteer;

class EventAttendanceService
{
    /** Record an explicit entry or exit action under a row lock to prevent capacity races. */
    public function scan(Organization $organization, string $token, string $action, int $quantity, int $scannerId, array $attendeeIds = []): array
    {
        if (strlen($token) < 40 || strlen($token) > 128) {
            throw ValidationException::withMessages(['token' => 'The pass token is invalid.']);
        }

        return DB::transaction(function () use ($organization, $token, $action, $quantity, $scannerId, $attendeeIds): array {
            $passLookup = EventPass::query()
                ->where('organization_id', $organization->id)
                ->where('token_hash', hash('sha256', $token))
                ->first(['id', 'event_id']);

            if (! $passLookup) {
                abort(404, 'The pass was not found for this organization.');
            }

            // All admission transactions lock event first, then pass, to serialize venue capacity across passes.
            $event = \Modules\Events\Models\Event::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($passLookup->event_id);
            $pass = EventPass::query()->where('organization_id', $organization->id)
                ->whereKey($passLookup->id)->lockForUpdate()->with(['participant'])->firstOrFail();
            $pass->setRelation('event', $event);

            $isAssignedVolunteer = EventVolunteer::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $pass->event_id)
                ->where('user_id', $scannerId)
                ->where('status', 'active')
                ->exists();

            if (! $isAssignedVolunteer) {
                abort(403, 'You are not assigned to scan passes for this event.');
            }

            $isExitFromCancelledEvent = $action === 'exit'
                && $pass->event->status === EventStatus::Cancelled
                && (int) $pass->current_inside > 0;

            if ($pass->status !== 'active' && ! $isExitFromCancelledEvent) {
                throw ValidationException::withMessages(['pass' => 'This pass is not active.']);
            }

            if ($pass->event->status !== EventStatus::Published && ! $isExitFromCancelledEvent) {
                throw ValidationException::withMessages(['event' => 'Attendance can only be recorded for a published event, except exits for occupants already inside a cancelled event.']);
            }

            if ($action === 'entry' && $pass->event->ends_at?->isPast()) {
                throw ValidationException::withMessages(['event' => 'New entries are closed because the event has ended.']);
            }

            if (in_array($pass->participant->status, ['cancelled', 'declined'], true)) {
                throw ValidationException::withMessages(['participant' => 'This participant is not eligible for attendance.']);
            }

            $individualRosterCount = $pass->attendees()->count();
            $hasLegacyScans = $pass->attendanceScans()->whereNull('event_pass_attendee_id')->exists();

            // Detect historical or out-of-band writes before applying another movement.
            // The pass row lock serializes all supported scan/roster/revocation operations
            // for this pass on databases that implement SELECT ... FOR UPDATE.
            if ($individualRosterCount > 0) {
                $rosteredInside = $pass->attendees()->where('status', 'inside')->count();
                if ($hasLegacyScans || $rosteredInside !== (int) $pass->current_inside) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Attendance records and the pass occupancy counter are inconsistent. Review the attendance audit before scanning again.',
                    ]);
                }
            } else {
                $entryQuantity = (int) $pass->attendanceScans()->where('action', 'entry')->sum('quantity');
                $exitQuantity = (int) $pass->attendanceScans()->where('action', 'exit')->sum('quantity');
                $correctionDelta = (int) \Modules\Events\Models\EventAttendanceCorrection::query()
                    ->where('event_pass_id', $pass->id)->sum('occupancy_delta');
                if (($entryQuantity - $exitQuantity + $correctionDelta) !== (int) $pass->current_inside) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Attendance scan history and the pass occupancy counter are inconsistent. Review the attendance audit before scanning again.',
                    ]);
                }
            }

            if ($attendeeIds !== []) {
                if ($hasLegacyScans) {
                    throw ValidationException::withMessages(['attendee_ids' => 'This pass already has quantity-based attendance records; individual tracking cannot be mixed with legacy scans.']);
                }

                // Always acquire attendee locks in primary-key order to reduce deadlock
                // risk when multiple scanners submit overlapping attendee batches.
                $sortedAttendeeIds = $attendeeIds;
                sort($sortedAttendeeIds, SORT_NUMERIC);
                $attendees = $pass->attendees()
                    ->whereIn('id', $sortedAttendeeIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($attendees->count() !== count(array_unique($attendeeIds))) {
                    throw ValidationException::withMessages(['attendee_ids' => 'One or more attendees do not belong to this pass.']);
                }

                $expectedStatus = $action === 'entry' ? 'outside' : 'inside';
                if ($attendees->contains(fn ($attendee) => $attendee->status !== $expectedStatus)) {
                    throw ValidationException::withMessages(['attendee_ids' => $action === 'entry'
                        ? 'All selected attendees must currently be outside.'
                        : 'All selected attendees must currently be inside.']);
                }

                $quantity = $attendees->count();
                if ($action === 'entry' && $pass->current_inside + $quantity > $pass->capacity) {
                    throw ValidationException::withMessages(['attendee_ids' => 'This pass does not have enough remaining capacity.']);
                }

                foreach ($attendees as $attendee) {
                    $now = now();
                    if ($action === 'entry') {
                        $attendee->status = 'inside';
                        $attendee->first_entered_at ??= $now;
                        $attendee->last_entered_at = $now;
                        $attendee->entry_count++;
                    } else {
                        $attendee->status = 'outside';
                        $attendee->last_exited_at = $now;
                    }
                    $attendee->save();

                    EventAttendanceScan::query()->create([
                        'organization_id' => $organization->id,
                        'event_id' => $pass->event_id,
                        'event_pass_id' => $pass->id,
                        'event_pass_attendee_id' => $attendee->id,
                        'scanned_by' => $scannerId,
                        'action' => $action,
                        'quantity' => 1,
                        'scanned_at' => $now,
                    ]);
                }

                $pass->current_inside += $action === 'entry' ? $quantity : -$quantity;
                $scan = $pass->attendanceScans()->whereIn('event_pass_attendee_id', $attendeeIds)->latest('id')->first();
            } else {
                if ($individualRosterCount > 0) {
                    throw ValidationException::withMessages(['quantity' => 'This pass uses individual attendee tracking. Supply attendee_ids instead of quantity.']);
                }

                if ($action === 'entry') {
                    if ($pass->current_inside + $quantity > $pass->capacity) {
                        throw ValidationException::withMessages(['quantity' => 'This pass does not have enough remaining capacity.']);
                    }
                    $pass->current_inside += $quantity;
                } else {
                    if ($quantity > $pass->current_inside) {
                        throw ValidationException::withMessages(['quantity' => 'Exit quantity cannot exceed the number currently inside.']);
                    }
                    $pass->current_inside -= $quantity;
                }

                $scan = EventAttendanceScan::query()->create([
                    'organization_id' => $organization->id,
                    'event_id' => $pass->event_id,
                    'event_pass_id' => $pass->id,
                    'scanned_by' => $scannerId,
                    'action' => $action,
                    'quantity' => $quantity,
                    'scanned_at' => now(),
                ]);
            }

            if ($action === 'entry' && $event->venue_capacity !== null) {
                $insideForOtherPasses = (int) EventPass::query()
                    ->where('organization_id', $organization->id)
                    ->where('event_id', $event->id)
                    ->where('id', '!=', $pass->id)
                    ->sum('current_inside');
                if ($insideForOtherPasses + (int) $pass->current_inside > (int) $event->venue_capacity) {
                    throw ValidationException::withMessages(['event' => 'The event venue capacity would be exceeded.']);
                }
            }

            // Once the final occupant exits a cancelled event, revoke the pass.
            if ($pass->event->status === EventStatus::Cancelled
                && $action === 'exit'
                && (int) $pass->current_inside === 0
                && $pass->status === 'active') {
                $pass->status = 'revoked';
                $pass->revoked_at = now();
            }

            $pass->save();

            return [
                'scan' => $scan,
                'pass' => $pass->refresh(),
                'event' => $pass->event,
                'participant' => $pass->participant,
            ];
        });
    }
}
