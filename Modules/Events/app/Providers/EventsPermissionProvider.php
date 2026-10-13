<?php

namespace Modules\Events\Providers;

use Modules\Core\Contracts\PermissionProvider;

class EventsPermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            ['module' => 'events', 'name' => 'View Events', 'code' => 'events.view', 'description' => 'View events within the organization.'],
            ['module' => 'events', 'name' => 'Manage Events', 'code' => 'events.manage', 'description' => 'Create and update draft events within the authorized scope.'],
            ['module' => 'events', 'name' => 'Publish Events', 'code' => 'events.publish', 'description' => 'Publish and cancel events within the authorized scope.'],
            ['module' => 'events', 'name' => 'Send Event Invitations', 'code' => 'events.invitations.send', 'description' => 'Explicitly send invitations for published events.'],
            ['module' => 'events', 'name' => 'Manage Event Attendance Outcomes', 'code' => 'events.participants.no_show', 'description' => 'Mark eligible participants as no-shows after an event ends.'],
            ['module' => 'events', 'name' => 'Scan Event Passes', 'code' => 'events.attendance.scan', 'description' => 'Validate passes and record attendance for assigned events.'],
            ['module' => 'events', 'name' => 'Correct Attendance', 'code' => 'events.attendance.correct', 'description' => 'Apply and audit authorized corrections to quantity-based attendance occupancy.'],
            ['module' => 'events', 'name' => 'View Attendance Audit History', 'code' => 'events.attendance.audit.view', 'description' => 'View filtered attendance scan history for events in the organization.'],
            ['module' => 'events', 'name' => 'Manage Event Volunteers', 'code' => 'events.volunteers.manage', 'description' => 'Assign and revoke organization members as event attendance volunteers.'],
            ['module' => 'events', 'name' => 'View Event Reports', 'code' => 'events.reports.view', 'description' => 'View event invitation and attendance reports.'],
            ['module' => 'events', 'name' => 'Manage Event Settings', 'code' => 'events.settings.manage', 'description' => 'Manage organization event settings and pass presentation.'],
        ];
    }
}
