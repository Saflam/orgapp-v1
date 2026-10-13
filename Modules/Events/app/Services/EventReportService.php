<?php

namespace Modules\Events\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventPassAttendee;

class EventReportService
{
    public function summary(Organization $organization, Event $event): array
    {
        $participants = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);

        $passes = EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);

        $scans = EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);

        $individualAttendees = EventPassAttendee::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);

        $participantCount = (clone $participants)->count();
        $participantByType = (clone $participants)
            ->selectRaw('participant_type, COUNT(*) as aggregate')
            ->groupBy('participant_type')
            ->pluck('aggregate', 'participant_type');
        $participantByStatus = (clone $participants)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $passCount = (clone $passes)->count();
        $activePassCount = (clone $passes)->where('status', 'active')->count();
        $revokedPassCount = (clone $passes)->where('status', 'revoked')->count();
        $totalPassCapacity = (int) (clone $passes)->where('status', 'active')->sum('capacity');
        $currentlyInside = (int) (clone $passes)->where('status', 'active')->sum('current_inside');

        $entryQuantity = (int) (clone $scans)->where('action', 'entry')->sum('quantity');
        $exitQuantity = (int) (clone $scans)->where('action', 'exit')->sum('quantity');
        $entryMovements = (int) (clone $scans)->where('action', 'entry')->count();
        $exitMovements = (int) (clone $scans)->where('action', 'exit')->count();
        $passesScanned = (clone $scans)->distinct('event_pass_id')->count('event_pass_id');

        $registeredIndividualAttendees = (clone $individualAttendees)->count();
        $individualInside = (clone $individualAttendees)->where('status', 'inside')->count();
        $individualOutside = (clone $individualAttendees)->where('status', 'outside')->count();
        $uniqueIndividualAttendeesScanned = (clone $scans)
            ->whereNotNull('event_pass_attendee_id')
            ->distinct('event_pass_attendee_id')
            ->count('event_pass_attendee_id');

        $individualByCategory = (clone $individualAttendees)
            ->selectRaw('category, COUNT(*) as registered, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inside', ['inside'])
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) ($row->category ?? 'other') => [
                    'registered' => (int) $row->registered,
                    'inside' => (int) $row->inside,
                    'scanned' => (int) (clone $individualAttendees)
                        ->where('category', $row->category)
                        ->whereHas('scans')
                        ->count(),
                ],
            ])
            ->all();

        $identityScanRecords = (clone $scans)->whereNotNull('event_pass_attendee_id');
        $quantityScanRecords = (clone $scans)->whereNull('event_pass_attendee_id');
        $quantityPassesScanned = (clone $quantityScanRecords)
            ->distinct('event_pass_id')
            ->count('event_pass_id');
        $identityPassesScanned = (clone $identityScanRecords)
            ->distinct('event_pass_id')
            ->count('event_pass_id');

        $passesNeverScanned = (clone $passes)
            ->where('status', 'active')
            ->whereDoesntHave('attendanceScans')
            ->count();

        // Report reconciliation checks without altering operational attendance records.
        $rosteredPasses = (clone $passes)->whereHas('attendees')->with('attendees:id,event_pass_id,status')->get();
        $passesWithOccupancyMismatch = $rosteredPasses->filter(
            fn (EventPass $pass): bool => (int) $pass->current_inside !== $pass->attendees->where('status', 'inside')->count()
        )->count();

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status->value ?? $event->status,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'timezone' => $event->timezone,
            ],
            'participants' => [
                'total' => $participantCount,
                'by_type' => $this->normalizeCounts($participantByType),
                'by_status' => $this->normalizeCounts($participantByStatus),
            ],
            'passes' => [
                'total' => $passCount,
                'active' => $activePassCount,
                'revoked' => $revokedPassCount,
                'active_capacity' => $totalPassCapacity,
                'currently_inside' => $currentlyInside,
                'remaining_capacity' => max(0, $totalPassCapacity - $currentlyInside),
                'never_scanned' => $passesNeverScanned,
                'distinct_passes_scanned' => $passesScanned,
                'quantity_tracking_passes_scanned' => $quantityPassesScanned,
                'identity_tracking_passes_scanned' => $identityPassesScanned,
                'registered_individual_attendees' => $registeredIndividualAttendees,
                'individual_attendees_currently_inside' => $individualInside,
                'individual_attendees_outside' => $individualOutside,
                'unique_individual_attendees_scanned' => $uniqueIndividualAttendeesScanned,
                'individual_attendees_by_category' => $individualByCategory,
            ],
            'attendance' => [
                'entry_movements' => $entryMovements,
                'exit_movements' => $exitMovements,
                'entry_quantity_total' => $entryQuantity,
                'exit_quantity_total' => $exitQuantity,
                'scan_records_total' => $entryMovements + $exitMovements,
                'quantity_tracking' => [
                    'scan_records' => (clone $quantityScanRecords)->count(),
                    'entry_quantity_total' => (int) (clone $quantityScanRecords)->where('action', 'entry')->sum('quantity'),
                    'exit_quantity_total' => (int) (clone $quantityScanRecords)->where('action', 'exit')->sum('quantity'),
                ],
                'identity_tracking' => [
                    'scan_records' => (clone $identityScanRecords)->count(),
                    'entry_records' => (clone $identityScanRecords)->where('action', 'entry')->count(),
                    'exit_records' => (clone $identityScanRecords)->where('action', 'exit')->count(),
                ],
            ],
            'data_quality' => [
                'passes_with_individual_rosters' => $rosteredPasses->count(),
                'passes_with_occupancy_mismatch' => $passesWithOccupancyMismatch,
                'occupancy_reconciliation' => $passesWithOccupancyMismatch === 0 ? 'consistent' : 'review_required',
            ],
            'generated_at' => now()->toIso8601String(),
            'notes' => [
                'entry_quantity_total counts all recorded entries, including re-entry; it is not a unique-person count.',
                'unique_individual_attendees_scanned counts distinct attendee records used in identity-based scans; quantity-only scans are not included.',
                'quantity_tracking and identity_tracking metrics are separated so a group quantity is not misrepresented as a unique-person count.',
                'passes_with_occupancy_mismatch compares pass.current_inside with the number of rostered attendees marked inside; review is recommended if nonzero.',
                'never_scanned counts active passes with no attendance scan records.',
            ],
        ];
    }

    /**
     * Organization-wide metrics. The event query is always scoped to the current
     * organization before optional filters are applied.
     */
    public function organizationSummary(Organization $organization, array $filters = []): array
    {
        $events = $this->filteredEvents($organization, $filters);
        $eventIds = $events->pluck('id')->all();

        $participants = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds);
        $passes = EventPass::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds);
        $scans = EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds);

        return [
            'filters' => array_diff_key($filters, ['_visible_event_ids' => true]),
            'events' => [
                'total' => $events->count(),
                'by_status' => $events->groupBy(fn (Event $event): string => (string) ($event->status->value ?? $event->status))
                    ->map(fn (Collection $group): int => $group->count())->all(),
            ],
            'registrations' => [
                'total' => (clone $participants)->count(),
                'by_status' => $this->normalizeCounts(
                    (clone $participants)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
                ),
                'cancelled' => (clone $participants)->where('status', 'cancelled')->count(),
                'no_shows' => (clone $participants)->where('status', 'no_show')->count(),
            ],
            'passes' => [
                'total' => (clone $passes)->count(),
                'active' => (clone $passes)->where('status', 'active')->count(),
                'revoked' => (clone $passes)->where('status', 'revoked')->count(),
                'currently_inside' => (int) (clone $passes)->where('status', 'active')->sum('current_inside'),
            ],
            'attendance' => [
                'entry_movements' => (clone $scans)->where('action', 'entry')->count(),
                'exit_movements' => (clone $scans)->where('action', 'exit')->count(),
                'entry_quantity_total' => (int) (clone $scans)->where('action', 'entry')->sum('quantity'),
                'exit_quantity_total' => (int) (clone $scans)->where('action', 'exit')->sum('quantity'),
                'unique_individual_attendees_scanned' => (clone $scans)
                    ->whereNotNull('event_pass_attendee_id')
                    ->distinct('event_pass_attendee_id')
                    ->count('event_pass_attendee_id'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Event-category breakdown based on the same filtered event set as the
     * organization summary. Aggregates are grouped by event first to avoid
     * join multiplication between participants, passes and scan records.
     */
    public function categorySummary(Organization $organization, array $filters = []): array
    {
        $events = $this->filteredEvents($organization, $filters)->load('category');
        if ($events->isEmpty()) {
            return ['filters' => array_diff_key($filters, ['_visible_event_ids' => true]), 'categories' => [], 'generated_at' => now()->toIso8601String()];
        }

        $eventIds = $events->pluck('id')->all();
        $participantsByEvent = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw('event_id, COUNT(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id');
        $cancelledByEvent = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->where('status', 'cancelled')
            ->selectRaw('event_id, COUNT(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id');
        $noShowsByEvent = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->where('status', 'no_show')
            ->selectRaw('event_id, COUNT(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id');
        $passesByEvent = EventPass::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw('event_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN current_inside ELSE 0 END) as current_inside', ['active'])
            ->groupBy('event_id')
            ->get()->keyBy('event_id');
        $attendanceByEvent = EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw("event_id, SUM(CASE WHEN action = 'entry' THEN quantity ELSE 0 END) as entries, SUM(CASE WHEN action = 'exit' THEN quantity ELSE 0 END) as exits, COUNT(DISTINCT event_pass_attendee_id) as unique_attendees")
            ->groupBy('event_id')
            ->get()->keyBy('event_id');

        $categories = [];
        foreach ($events as $event) {
            $key = $event->category_id === null ? 'uncategorized' : (string) $event->category_id;
            if (! isset($categories[$key])) {
                $categories[$key] = [
                    'category_id' => $event->category_id,
                    'category_name' => $event->category?->name ?? 'Uncategorized',
                    'event_count' => 0,
                    'events_by_status' => ['draft' => 0, 'published' => 0, 'cancelled' => 0],
                    'registrations_total' => 0,
                    'registrations_cancelled' => 0,
                    'no_shows' => 0,
                    'passes_total' => 0,
                    'currently_inside' => 0,
                    'entry_quantity_total' => 0,
                    'exit_quantity_total' => 0,
                    'unique_individual_attendees_scanned' => 0,
                ];
            }

            $status = (string) ($event->status->value ?? $event->status);
            $categories[$key]['event_count']++;
            if (array_key_exists($status, $categories[$key]['events_by_status'])) {
                $categories[$key]['events_by_status'][$status]++;
            }
            $categories[$key]['registrations_total'] += (int) ($participantsByEvent[$event->id] ?? 0);
            $categories[$key]['registrations_cancelled'] += (int) ($cancelledByEvent[$event->id] ?? 0);
            $categories[$key]['no_shows'] += (int) ($noShowsByEvent[$event->id] ?? 0);
            $passMetrics = $passesByEvent->get($event->id);
            $categories[$key]['passes_total'] += (int) ($passMetrics->total ?? 0);
            $categories[$key]['currently_inside'] += (int) ($passMetrics->current_inside ?? 0);
            $scanMetrics = $attendanceByEvent->get($event->id);
            $categories[$key]['entry_quantity_total'] += (int) ($scanMetrics->entries ?? 0);
            $categories[$key]['exit_quantity_total'] += (int) ($scanMetrics->exits ?? 0);
            $categories[$key]['unique_individual_attendees_scanned'] += (int) ($scanMetrics->unique_attendees ?? 0);
        }

        return [
            'filters' => array_diff_key($filters, ['_visible_event_ids' => true]),
            'categories' => array_values($categories),
            'generated_at' => now()->toIso8601String(),
            'notes' => [
                'Unique individual attendees are based on identity-linked attendance scans; quantity-only scans are not included.',
                'No-shows and cancelled registrations are counted from participant status values.',
            ],
        ];
    }


    public function registrations(Organization $organization, Event $event, array $filters = [])
    {
        $query = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->with(['member.user', 'inviter']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('registered_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('registered_at', '<=', $filters['to']);
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    public function attendance(Organization $organization, Event $event, array $filters = [])
    {
        $query = EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->with(['pass.participant', 'attendee', 'scanner']);

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('scanned_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('scanned_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('scanned_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    public function passes(Organization $organization, Event $event, array $filters = [])
    {
        $query = EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->with(['participant', 'attendees']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('issued_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('issued_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('issued_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    private function filteredEvents(Organization $organization, array $filters): Collection
    {
        $query = Event::query()->where('organization_id', $organization->id);

        if (array_key_exists('_visible_event_ids', $filters)) {
            $query->whereIn('id', $filters['_visible_event_ids']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('starts_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('starts_at', '<=', $filters['to']);
        }
        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('starts_at')->get();
    }

    private function normalizeCounts(Collection $counts): array
    {
        return $counts
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
