<?php

namespace Modules\Events\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventAttendanceScan;

class EventAttendanceAuditService
{
    /**
     * Return a filtered, paginated attendance audit trail for one event.
     * All filters are applied inside the organization and event scope.
     */
    public function paginate(
        Organization $organization,
        Event $event,
        array $filters,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = EventAttendanceScan::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->with([
                'scanner:id,name,email',
                'pass:id,event_id,event_participant_id,capacity,status',
                'pass.participant:id,name,participant_type,email',
                'attendee:id,name,category,status',
            ]);

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['pass_id'])) {
            $query->where('event_pass_id', $filters['pass_id']);
        }

        if (isset($filters['attendee_id'])) {
            $query->where('event_pass_attendee_id', $filters['attendee_id']);
        }

        if (isset($filters['scanned_by'])) {
            $query->where('scanned_by', $filters['scanned_by']);
        }

        if (isset($filters['from'])) {
            $query->where('scanned_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('scanned_at', '<=', $filters['to']);
        }

        return $query
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
