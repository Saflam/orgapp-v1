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
            'registrations' => [
                'total' => $participantCount,
                'invited' => (int) ($participantByStatus['invited'] ?? 0),
                'registered' => (int) ($participantByStatus['registered'] ?? 0),
                'cancelled' => (int) ($participantByStatus['cancelled'] ?? 0),
                'no_shows' => (int) ($participantByStatus['no_show'] ?? 0),
                'by_status' => $this->normalizeCounts($participantByStatus),
            ],
            'passes' => [
                'total' => $passCount,
                'active' => $activePassCount,
                'revoked' => $revokedPassCount,
                'expired' => (int) (clone $passes)->where('status', 'expired')->count(),
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

        $participantMetrics = $this->participantMetricsByEvent($organization, $eventIds);
        $passMetrics = $this->passMetricsByEvent($organization, $eventIds);
        $scanMetrics = $this->scanMetricsByEvent($organization, $eventIds);
        $rosterMetrics = $this->rosterMetricsByEvent($organization, $eventIds);

        $registrations = $this->sumMetrics($participantMetrics, [
            'total', 'invited', 'registered', 'cancelled', 'no_shows',
        ]);
        $registrations['by_status'] = $this->sumStatusMetrics($participantMetrics);

        $passes = $this->sumMetrics($passMetrics, [
            'total', 'active', 'revoked', 'expired', 'active_capacity',
            'currently_inside', 'never_scanned',
        ]);
        $passes['remaining_capacity'] = max(0, $passes['active_capacity'] - $passes['currently_inside']);
        $passes['distinct_passes_scanned'] = (int) $scanMetrics->sum('distinct_passes_scanned');
        $passes['registered_individual_attendees'] = (int) $rosterMetrics->sum('registered_individual_attendees');
        $passes['individual_attendees_currently_inside'] = (int) $rosterMetrics->sum('individual_attendees_currently_inside');
        $passes['individual_attendees_outside'] = (int) $rosterMetrics->sum('individual_attendees_outside');
        $passes['unique_individual_attendees_scanned'] = (int) $scanMetrics->sum('unique_individual_attendees_scanned');

        $attendance = $this->sumMetrics($scanMetrics, [
            'entry_movements', 'exit_movements', 'entry_quantity_total', 'exit_quantity_total',
            'scan_records_total',
        ]);
        $attendance['identity_tracking'] = [
            'scan_records' => (int) $scanMetrics->sum('identity_scan_records'),
            'entry_records' => (int) $scanMetrics->sum('identity_entry_records'),
            'exit_records' => (int) $scanMetrics->sum('identity_exit_records'),
            // Sum per-event distinct attendee records; never deduplicate across events.
            'unique_individual_attendees_scanned' => $passes['unique_individual_attendees_scanned'],
        ];
        $attendance['quantity_tracking'] = [
            'scan_records' => (int) $scanMetrics->sum('quantity_scan_records'),
            'entry_movements' => (int) $scanMetrics->sum('quantity_entry_movements'),
            'exit_movements' => (int) $scanMetrics->sum('quantity_exit_movements'),
            'entry_quantity_total' => (int) $scanMetrics->sum('quantity_entry_total'),
            'exit_quantity_total' => (int) $scanMetrics->sum('quantity_exit_total'),
        ];

        $mismatchCount = (int) $rosterMetrics->sum('passes_with_occupancy_mismatch');

        return [
            'filters' => array_diff_key($filters, ['_visible_event_ids' => true]),
            'events' => [
                'total' => $events->count(),
                'by_status' => $events->groupBy(fn (Event $event): string => (string) ($event->status->value ?? $event->status))
                    ->map(fn (Collection $group): int => $group->count())->all(),
            ],
            'registrations' => $registrations,
            'passes' => $passes,
            'attendance' => $attendance,
            'data_quality' => [
                'passes_with_individual_rosters' => (int) $rosterMetrics->sum('passes_with_individual_rosters'),
                'passes_with_occupancy_mismatch' => $mismatchCount,
                'occupancy_reconciliation' => $mismatchCount === 0 ? 'consistent' : 'review_required',
            ],
            'generated_at' => now()->toIso8601String(),
            'notes' => [
                'Unique individual attendees are summed as distinct attendee records within each event; the same person attending multiple events is counted once per event.',
                'Quantity-only scans are reported separately and are not included in unique individual attendee counts.',
                'Entry quantity includes re-entry movements and is not a unique-person count.',
                'Occupancy reconciliation compares each rostered pass current_inside value with its attendees marked inside.',
                'Organization summary date filters apply to event starts_at.',
            ],
        ];
    }

    /**
     * Category metrics are accumulated from per-event aggregates. In particular,
     * unique attendee counts are summed per event rather than deduplicated across
     * the whole organization or category.
     */
    public function categorySummary(Organization $organization, array $filters = []): array
    {
        $events = $this->filteredEvents($organization, $filters)->load('category');
        if ($events->isEmpty()) {
            return [
                'filters' => array_diff_key($filters, ['_visible_event_ids' => true]),
                'categories' => [],
                'generated_at' => now()->toIso8601String(),
                'notes' => [
                    'Unique individual attendees are summed per event; the same person attending multiple events is counted once per event.',
                    'Quantity-only scans are not included in unique individual attendee counts.',
                ],
            ];
        }

        $eventIds = $events->pluck('id')->all();
        $participantMetrics = $this->participantMetricsByEvent($organization, $eventIds);
        $passMetrics = $this->passMetricsByEvent($organization, $eventIds);
        $scanMetrics = $this->scanMetricsByEvent($organization, $eventIds);
        $rosterMetrics = $this->rosterMetricsByEvent($organization, $eventIds);

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
                    'registrations_invited' => 0,
                    'registrations_registered' => 0,
                    'registrations_cancelled' => 0,
                    'no_shows' => 0,
                    'registrations_by_status' => [],
                    'passes_total' => 0,
                    'passes_active' => 0,
                    'passes_revoked' => 0,
                    'passes_expired' => 0,
                    'active_capacity' => 0,
                    'currently_inside' => 0,
                    'remaining_capacity' => 0,
                    'passes_never_scanned' => 0,
                    'distinct_passes_scanned' => 0,
                    'entry_movements' => 0,
                    'exit_movements' => 0,
                    'scan_records_total' => 0,
                    'entry_quantity_total' => 0,
                    'exit_quantity_total' => 0,
                    'identity_scan_records' => 0,
                    'identity_entry_records' => 0,
                    'identity_exit_records' => 0,
                    'quantity_scan_records' => 0,
                    'quantity_entry_movements' => 0,
                    'quantity_exit_movements' => 0,
                    'quantity_entry_total' => 0,
                    'quantity_exit_total' => 0,
                    'unique_individual_attendees_scanned' => 0,
                    'registered_individual_attendees' => 0,
                    'individual_attendees_currently_inside' => 0,
                    'individual_attendees_outside' => 0,
                    'passes_with_individual_rosters' => 0,
                    'passes_with_occupancy_mismatch' => 0,
                ];
            }

            $status = (string) ($event->status->value ?? $event->status);
            $categories[$key]['event_count']++;
            if (array_key_exists($status, $categories[$key]['events_by_status'])) {
                $categories[$key]['events_by_status'][$status]++;
            }

            $participant = $participantMetrics->get($event->id, []);
            foreach (['total', 'invited', 'registered', 'cancelled', 'no_shows'] as $metric) {
                $field = $metric === 'total' ? 'registrations_total' : (
                    $metric === 'no_shows' ? 'no_shows' : 'registrations_'.$metric
                );
                $categories[$key][$field] += (int) ($participant[$metric] ?? 0);
            }
            foreach (($participant['by_status'] ?? []) as $participantStatus => $count) {
                $categories[$key]['registrations_by_status'][$participantStatus] =
                    ($categories[$key]['registrations_by_status'][$participantStatus] ?? 0) + (int) $count;
            }

            $pass = $passMetrics->get($event->id, []);
            $passFieldMap = [
                'total' => 'passes_total',
                'active' => 'passes_active',
                'revoked' => 'passes_revoked',
                'expired' => 'passes_expired',
                'active_capacity' => 'active_capacity',
                'currently_inside' => 'currently_inside',
                'never_scanned' => 'passes_never_scanned',
            ];
            foreach ($passFieldMap as $metric => $field) {
                $categories[$key][$field] += (int) ($pass[$metric] ?? 0);
            }

            $scan = $scanMetrics->get($event->id, []);
            $scanFieldMap = [
                'distinct_passes_scanned' => 'distinct_passes_scanned',
                'entry_movements' => 'entry_movements',
                'exit_movements' => 'exit_movements',
                'scan_records_total' => 'scan_records_total',
                'entry_quantity_total' => 'entry_quantity_total',
                'exit_quantity_total' => 'exit_quantity_total',
                'identity_scan_records' => 'identity_scan_records',
                'identity_entry_records' => 'identity_entry_records',
                'identity_exit_records' => 'identity_exit_records',
                'quantity_scan_records' => 'quantity_scan_records',
                'quantity_entry_movements' => 'quantity_entry_movements',
                'quantity_exit_movements' => 'quantity_exit_movements',
                'quantity_entry_total' => 'quantity_entry_total',
                'quantity_exit_total' => 'quantity_exit_total',
                'unique_individual_attendees_scanned' => 'unique_individual_attendees_scanned',
            ];
            foreach ($scanFieldMap as $metric => $field) {
                $categories[$key][$field] += (int) ($scan[$metric] ?? 0);
            }

            $roster = $rosterMetrics->get($event->id, []);
            foreach ([
                'registered_individual_attendees',
                'individual_attendees_currently_inside',
                'individual_attendees_outside',
                'passes_with_individual_rosters',
                'passes_with_occupancy_mismatch',
            ] as $metric) {
                $categories[$key][$metric] += (int) ($roster[$metric] ?? 0);
            }
        }

        foreach ($categories as &$category) {
            $category['remaining_capacity'] = max(0, $category['active_capacity'] - $category['currently_inside']);
            $category['occupancy_reconciliation'] = $category['passes_with_occupancy_mismatch'] === 0
                ? 'consistent'
                : 'review_required';
        }
        unset($category);

        return [
            'filters' => array_diff_key($filters, ['_visible_event_ids' => true]),
            'categories' => array_values($categories),
            'generated_at' => now()->toIso8601String(),
            'notes' => [
                'Unique individual attendees are summed as distinct attendee records within each event; the same person attending multiple events is counted once per event.',
                'Quantity-only scans are reported separately and are not included in unique individual attendee counts.',
                'Entry quantity includes re-entry movements and is not a unique-person count.',
                'No-shows and cancellations are counted from participant status values.',
            ],
        ];
    }

    private function participantMetricsByEvent(Organization $organization, array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        $rows = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw('event_id, status, COUNT(*) as aggregate')
            ->groupBy('event_id', 'status')
            ->get();

        return $rows->groupBy('event_id')->map(function (Collection $group): array {
            $byStatus = $group->mapWithKeys(fn ($row): array => [(string) $row->status => (int) $row->aggregate])->all();

            return [
                'total' => array_sum($byStatus),
                'invited' => (int) ($byStatus['invited'] ?? 0),
                'registered' => (int) ($byStatus['registered'] ?? 0),
                'cancelled' => (int) ($byStatus['cancelled'] ?? 0),
                'no_shows' => (int) ($byStatus['no_show'] ?? 0),
                'by_status' => $byStatus,
            ];
        });
    }

    private function passMetricsByEvent(Organization $organization, array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        $rows = EventPass::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw(
                "event_id, COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked, SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired, SUM(CASE WHEN status = 'active' THEN capacity ELSE 0 END) as active_capacity, SUM(CASE WHEN status = 'active' THEN current_inside ELSE 0 END) as currently_inside"
            )
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $neverScanned = EventPass::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->where('status', 'active')
            ->whereDoesntHave('attendanceScans')
            ->selectRaw('event_id, COUNT(*) as aggregate')
            ->groupBy('event_id')
            ->pluck('aggregate', 'event_id');

        return $rows->map(fn ($row): array => [
            'total' => (int) $row->total,
            'active' => (int) $row->active,
            'revoked' => (int) $row->revoked,
            'expired' => (int) $row->expired,
            'active_capacity' => (int) $row->active_capacity,
            'currently_inside' => (int) $row->currently_inside,
            'never_scanned' => (int) ($neverScanned[$row->event_id] ?? 0),
        ]);
    }

    private function scanMetricsByEvent(Organization $organization, array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        return EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw("
                event_id,
                COUNT(*) as scan_records_total,
                SUM(CASE WHEN action = 'entry' THEN 1 ELSE 0 END) as entry_movements,
                SUM(CASE WHEN action = 'exit' THEN 1 ELSE 0 END) as exit_movements,
                SUM(CASE WHEN action = 'entry' THEN quantity ELSE 0 END) as entry_quantity_total,
                SUM(CASE WHEN action = 'exit' THEN quantity ELSE 0 END) as exit_quantity_total,
                SUM(CASE WHEN event_pass_attendee_id IS NOT NULL THEN 1 ELSE 0 END) as identity_scan_records,
                SUM(CASE WHEN event_pass_attendee_id IS NOT NULL AND action = 'entry' THEN 1 ELSE 0 END) as identity_entry_records,
                SUM(CASE WHEN event_pass_attendee_id IS NOT NULL AND action = 'exit' THEN 1 ELSE 0 END) as identity_exit_records,
                SUM(CASE WHEN event_pass_attendee_id IS NULL THEN 1 ELSE 0 END) as quantity_scan_records,
                SUM(CASE WHEN event_pass_attendee_id IS NULL AND action = 'entry' THEN 1 ELSE 0 END) as quantity_entry_movements,
                SUM(CASE WHEN event_pass_attendee_id IS NULL AND action = 'exit' THEN 1 ELSE 0 END) as quantity_exit_movements,
                SUM(CASE WHEN event_pass_attendee_id IS NULL AND action = 'entry' THEN quantity ELSE 0 END) as quantity_entry_total,
                SUM(CASE WHEN event_pass_attendee_id IS NULL AND action = 'exit' THEN quantity ELSE 0 END) as quantity_exit_total,
                COUNT(DISTINCT event_pass_id) as distinct_passes_scanned,
                COUNT(DISTINCT event_pass_attendee_id) as unique_individual_attendees_scanned
            ")
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id')
            ->map(fn ($row): array => collect($row)->except(['event_id'])->map(fn ($value): int => (int) $value)->all());
    }

    private function rosterMetricsByEvent(Organization $organization, array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        $attendeeRows = EventPassAttendee::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw("
                event_id,
                COUNT(*) as registered_individual_attendees,
                SUM(CASE WHEN status = 'inside' THEN 1 ELSE 0 END) as individual_attendees_currently_inside,
                SUM(CASE WHEN status = 'outside' THEN 1 ELSE 0 END) as individual_attendees_outside
            ")
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $passRows = EventPass::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_id', $eventIds)
            ->whereHas('attendees')
            ->withCount(['attendees as roster_inside_count' => fn (Builder $query) => $query->where('status', 'inside')])
            ->get(['id', 'event_id', 'current_inside']);

        $result = collect();
        foreach ($attendeeRows as $eventId => $row) {
            $result[$eventId] = [
                'registered_individual_attendees' => (int) $row->registered_individual_attendees,
                'individual_attendees_currently_inside' => (int) $row->individual_attendees_currently_inside,
                'individual_attendees_outside' => (int) $row->individual_attendees_outside,
                'passes_with_individual_rosters' => 0,
                'passes_with_occupancy_mismatch' => 0,
            ];
        }

        foreach ($passRows as $pass) {
            $eventMetrics = $result->get($pass->event_id, [
                'registered_individual_attendees' => 0,
                'individual_attendees_currently_inside' => 0,
                'individual_attendees_outside' => 0,
                'passes_with_individual_rosters' => 0,
                'passes_with_occupancy_mismatch' => 0,
            ]);

            $eventMetrics['passes_with_individual_rosters']++;
            if ((int) $pass->current_inside !== (int) $pass->roster_inside_count) {
                $eventMetrics['passes_with_occupancy_mismatch']++;
            }

            // Collection offset access returns a value; update it through put()
            // rather than attempting indirect modification of the overloaded item.
            $result->put($pass->event_id, $eventMetrics);
        }

        return $result;
    }

    private function sumMetrics(Collection $metrics, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = (int) $metrics->sum($key);
        }

        return $result;
    }

    private function sumStatusMetrics(Collection $metrics): array
    {
        $counts = [];
        foreach ($metrics as $eventMetrics) {
            foreach ($eventMetrics['by_status'] ?? [] as $status => $count) {
                $counts[$status] = ($counts[$status] ?? 0) + (int) $count;
            }
        }

        ksort($counts);

        return $counts;
    }

    public function registrations(Organization $organization, Event $event, array $filters = [])
    {
        return $this->registrationQuery($organization, $event, $filters)
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function registrationQuery(Organization $organization, Event $event, array $filters = []): Builder
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

        return $query;
    }

    public function attendance(Organization $organization, Event $event, array $filters = [])
    {
        return $this->attendanceQuery($organization, $event, $filters)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function attendanceQuery(Organization $organization, Event $event, array $filters = []): Builder
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

        return $query;
    }

    public function passes(Organization $organization, Event $event, array $filters = [])
    {
        return $this->passQuery($organization, $event, $filters)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function passQuery(Organization $organization, Event $event, array $filters = [], bool $withAttendees = true): Builder
    {
        $query = EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->with('participant');

        if ($withAttendees) {
            $query->with('attendees');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('issued_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('issued_at', '<=', $filters['to']);
        }

        return $query;
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
