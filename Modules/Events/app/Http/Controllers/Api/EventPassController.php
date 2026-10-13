<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Services\EventPassService;

class EventPassController extends Controller
{
    public function __construct(private readonly EventPassService $passes)
    {
    }

    public function issue(Request $request, int $event, int $participant): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()->where('organization_id', $organization->id)->findOrFail($event);
        $participantRecord = EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $eventRecord->id)
            ->findOrFail($participant);

        $result = $this->passes->issue(
            $organization,
            $eventRecord,
            $participantRecord,
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->json([
            'data' => [
                'id' => $result['pass']->id,
                'event_id' => $eventRecord->id,
                'participant_id' => $participantRecord->id,
                'status' => $result['pass']->status,
                'capacity' => $result['pass']->capacity,
                'issued_at' => $result['pass']->issued_at,
                // Raw bearer token is shown only once. Do not log or persist it.
                'token' => $result['token'],
                'qr_payload' => $result['qr_payload'],
                'notification_queued_to' => $result['notification_queued_to'],
                'notification_status' => $result['notification_status'],
            ],
        ], 201);
    }

    public function revoke(int $event, int $pass): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);
        $passRecord = \Modules\Events\Models\EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $eventRecord->id)
            ->findOrFail($pass);

        $revoked = $this->passes->revoke($organization, $eventRecord, $passRecord);

        return response()->json(['data' => $revoked]);
    }

    public function retryDelivery(int $event, int $pass): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()->where('organization_id', $organization->id)->findOrFail($event);
        $passRecord = \Modules\Events\Models\EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $eventRecord->id)
            ->findOrFail($pass);

        $updated = $this->passes->retryDelivery($organization, $eventRecord, $passRecord);

        return response()->json(['data' => [
            'pass_id' => $updated->id,
            'notification_status' => $updated->notification_delivery_status,
            'notification_sent_at' => $updated->notification_sent_at,
        ]], 202);
    }

    public function deliveryStatus(int $event): JsonResponse
    {
        $organization = $this->organization();
        $eventRecord = Event::query()->where('organization_id', $organization->id)->findOrFail($event);

        return response()->json(['data' => $this->passes->deliveryStatus($organization, $eventRecord)]);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'min:40', 'max:128']]);
        $organization = $this->organization();
        $pass = $this->passes->findByToken($organization, $validated['token']);
        if (! $pass) {
            return response()->json(['message' => 'The pass is invalid or revoked.'], 404);
        }

        return response()->json([
            'data' => [
                'event' => [
                    'id' => $pass->event->id,
                    'title' => $pass->event->title,
                    'starts_at' => $pass->event->starts_at,
                    'ends_at' => $pass->event->ends_at,
                ],
                'participant' => [
                    'id' => $pass->participant->id,
                    'name' => $pass->participant->name,
                    'status' => $pass->participant->status,
                ],
                'pass' => ['id' => $pass->id, 'status' => $pass->status, 'capacity' => $pass->capacity],
            ],
        ]);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }
        return $organization;
    }
}
