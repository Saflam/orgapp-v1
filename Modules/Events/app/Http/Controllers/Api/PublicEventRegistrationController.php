<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Modules\Events\Services\EventParticipantService;

class PublicEventRegistrationController extends Controller
{
    public function __construct(private readonly EventParticipantService $participants)
    {
    }

    public function store(Request $request, int $event): JsonResponse
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }

        $eventRecord = Event::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($event);

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'custom_fields' => ['sometimes', 'array'],
        ];

        foreach (($eventRecord->registration_fields ?? []) as $field) {
            if (! is_array($field) || empty($field['key'])) {
                continue;
            }

            $key = 'custom_fields.'.$field['key'];
            $required = ! empty($field['required']) ? 'required' : 'nullable';
            $type = $field['type'] ?? 'text';

            $rules[$key] = match ($type) {
                'email' => [$required, 'email', 'max:254'],
                'phone' => [$required, 'string', 'max:40'],
                'checkbox' => [$required, 'boolean'],
                'select' => [$required, 'string', Rule::in($field['options'] ?? [])],
                default => [$required, 'string', 'max:500'],
            };
        }

        $validated = Validator::make($request->all(), $rules)->validate();
        $participant = $this->participants->registerPublic($organization, $eventRecord, $validated);

        return response()->json([
            'data' => [
                'id' => $participant->id,
                'event_id' => $participant->event_id,
                'name' => $participant->name,
                'status' => $participant->status,
                'registered_at' => $participant->registered_at,
            ],
            'message' => 'Registration completed successfully.',
        ], 201);
    }
}
