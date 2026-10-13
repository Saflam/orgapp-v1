<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Http\Requests\Api\StoreEventParticipantRequest;
use Modules\Events\Models\Event;
use Modules\Events\Services\EventParticipantService;

class EventParticipantController extends Controller
{
    public function __construct(
        private readonly EventParticipantService $participants,
    ) {
    }

    public function index(int $event): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);

        $participants = $this->participants
            ->queryForOrganization($organization, $eventRecord)
            ->with(['member.user', 'inviter'])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($participants);
    }

    public function store(StoreEventParticipantRequest $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);

        $participant = $this->participants->invite(
            $organization,
            $eventRecord,
            (int) $request->user()->getAuthIdentifier(),
            $request->validated(),
        );

        return response()->json(['data' => $participant], 201);
    }

    public function noShow(int $event, int $participant): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);
        $participantRecord = \Modules\Events\Models\EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $eventRecord->id)
            ->findOrFail($participant);

        $updated = $this->participants->markNoShow($organization, $eventRecord, $participantRecord);

        return response()->json(['data' => $updated]);
    }

    public function cancel(int $event, int $participant): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);
        $participantRecord = \Modules\Events\Models\EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $eventRecord->id)
            ->findOrFail($participant);

        $cancelled = $this->participants->cancel($organization, $eventRecord, $participantRecord);

        return response()->json(['data' => $cancelled]);
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
