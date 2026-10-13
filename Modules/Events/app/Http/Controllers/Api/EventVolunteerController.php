<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Models\OrganizationMembership;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventVolunteer;

class EventVolunteerController extends Controller
{
    public function index(int $event): JsonResponse
    {
        $organization = $this->organization();
        $record = $this->eventQuery($organization)->findOrFail($event);

        $volunteers = EventVolunteer::query()
            ->with(['user:id,name,email', 'assigner:id,name,email', 'revoker:id,name,email'])
            ->where('organization_id', $organization->id)
            ->where('event_id', $record->id)
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json(['data' => $volunteers]);
    }

    public function store(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
        ]);

        $organization = $this->organization();
        $record = $this->eventQuery($organization)->findOrFail($event);
        $userId = (int) $validated['user_id'];

        $hasActiveMembership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveMembership) {
            throw ValidationException::withMessages([
                'user_id' => 'The volunteer must have an active membership in this organization.',
            ]);
        }

        $existing = EventVolunteer::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $record->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing->load('user:id,name,email')]);
        }

        $volunteer = EventVolunteer::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $record->id,
            'user_id' => $userId,
            'assigned_by' => (int) $request->user()->getAuthIdentifier(),
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        return response()->json(['data' => $volunteer->load('user:id,name,email')], 201);
    }

    public function destroy(Request $request, int $event, int $user): JsonResponse
    {
        $organization = $this->organization();
        $record = $this->eventQuery($organization)->findOrFail($event);

        $volunteer = EventVolunteer::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $record->id)
            ->where('user_id', $user)
            ->where('status', 'active')
            ->latest('assigned_at')
            ->firstOrFail();

        $volunteer->status = 'revoked';
        $volunteer->revoked_by = (int) $request->user()->getAuthIdentifier();
        $volunteer->revoked_at = now();
        $volunteer->save();

        return response()->json(['data' => $volunteer->refresh()->load('user:id,name,email')]);
    }

    private function eventQuery(Organization $organization)
    {
        return Event::query()->where('organization_id', $organization->id);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        return $organization;
    }
}
