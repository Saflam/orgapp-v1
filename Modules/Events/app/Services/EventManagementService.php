<?php

namespace Modules\Events\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use App\Models\User;
use Modules\Events\Enums\EventScope;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventCategory;

class EventManagementService
{
    public function queryForOrganization(Organization $organization): Builder
    {
        return Event::query()->where('organization_id', $organization->id);
    }

    /**
     * Query events visible to a user under Core's organization/exact-unit authorization model.
     * An organization-scoped events.view permission grants visibility to all events in the org;
     * otherwise, only unit events for units with an exact matching assignment are visible.
     */
    public function queryVisibleTo(Organization $organization, User $user, string $permissionCode = 'events.view'): Builder
    {
        $authorization = app(AuthorizationService::class);
        $organizationContext = new AuthorizationContext((int) $organization->id);

        if ($authorization->can($user, $permissionCode, $organizationContext)) {
            return $this->queryForOrganization($organization);
        }

        $visibleUnitIds = Unit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id'])
            ->filter(fn (Unit $unit): bool => $authorization->can(
                $user,
                $permissionCode,
                new AuthorizationContext((int) $organization->id, (int) $unit->id),
            ))
            ->pluck('id');

        return $this->queryForOrganization($organization)
            ->where('scope', EventScope::Unit->value)
            ->whereIn('unit_id', $visibleUnitIds);
    }

    public function create(Organization $organization, int $userId, array $data): Event
    {
        return DB::transaction(function () use ($organization, $userId, $data): Event {
            $data['created_by'] = $userId;
            $data = $this->validateAndNormalize($organization, $data);
            $data['organization_id'] = $organization->id;
            $data['status'] = EventStatus::Draft;

            return Event::query()->create($data)->load(['category', 'unit', 'creator']);
        });
    }

    public function update(Event $event, Organization $organization, array $data): Event
    {
        if ((int) $event->organization_id !== (int) $organization->id) {
            abort(404);
        }

        if ($event->status !== EventStatus::Draft) {
            throw ValidationException::withMessages([
                'event' => 'Only draft events can be edited in this workflow.',
            ]);
        }

        return DB::transaction(function () use ($event, $organization, $data): Event {
            $merged = array_merge($event->only([
                'title', 'subtitle', 'description', 'venue_name', 'venue_address',
                'starts_at', 'ends_at', 'timezone', 'scope', 'unit_id', 'visibility', 'category_id', 'registration_fields',
                'registration_opens_at', 'registration_closes_at', 'registration_capacity', 'venue_capacity',
            ]), $data);
            $normalized = $this->validateAndNormalize($organization, $merged);
            $this->assertRegistrationFieldsRemainCompatible($event, $normalized['registration_fields'] ?? []);
            $event->fill($normalized);
            $event->save();

            return $event->refresh()->load(['category', 'unit', 'creator']);
        });
    }

    public function ensureDefaultCategory(Organization $organization, ?int $userId = null): EventCategory
    {
        return EventCategory::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'uncategorized'],
            ['name' => 'Uncategorized', 'description' => 'Default category for events without a category.', 'is_system' => true, 'is_active' => true, 'created_by' => $userId],
        );
    }

    private function validateAndNormalize(Organization $organization, array $data): array
    {
        $scope = $data['scope'] instanceof EventScope ? $data['scope'] : EventScope::from((string) $data['scope']);
        $data['scope'] = $scope->value;

        if ($scope === EventScope::Central) {
            $data['unit_id'] = null;
        } else {
            if (empty($data['unit_id'])) {
                throw ValidationException::withMessages(['unit_id' => 'A unit is required for a unit-scoped event.']);
            }
            $unitExists = Unit::query()
                ->where('id', $data['unit_id'])
                ->where('organization_id', $organization->id)
                ->exists();
            if (! $unitExists) {
                throw ValidationException::withMessages(['unit_id' => 'The selected unit does not belong to this organization.']);
            }
        }

        if (! empty($data['category_id'])) {
            $categoryExists = EventCategory::query()
                ->where('id', $data['category_id'])
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->exists();
            if (! $categoryExists) {
                throw ValidationException::withMessages(['category_id' => 'The selected category is unavailable in this organization.']);
            }
        } else {
            $category = $this->ensureDefaultCategory($organization, isset($data['created_by']) ? (int) $data['created_by'] : null);
            $data['category_id'] = $category->id;
        }

        if (isset($data['starts_at'], $data['ends_at']) && strtotime((string) $data['ends_at']) <= strtotime((string) $data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'The event end time must be after its start time.']);
        }

        if (!empty($data['registration_opens_at']) && !empty($data['registration_closes_at'])
            && strtotime((string) $data['registration_closes_at']) < strtotime((string) $data['registration_opens_at'])) {
            throw ValidationException::withMessages(['registration_closes_at' => 'Registration must close at or after its opening time.']);
        }

        $data['timezone'] = $data['timezone'] ?? 'Asia/Kolkata';
        return $data;
    }
    /** Prevent field-definition changes from silently deleting or reinterpreting saved answers. */
    private function assertRegistrationFieldsRemainCompatible(Event $event, array $newFields): void
    {
        $participants = $event->participants()
            ->whereNotNull('metadata')
            ->get(['id', 'metadata']);

        if ($participants->isEmpty()) {
            return;
        }

        $oldFields = collect($event->registration_fields ?? [])->keyBy('key');
        $newFieldsByKey = collect($newFields)->keyBy('key');

        foreach ($participants as $participant) {
            $answers = data_get($participant->metadata, 'custom_fields', []);
            if (! is_array($answers) || $answers === []) {
                continue;
            }

            foreach ($answers as $key => $answer) {
                $oldDefinition = $oldFields->get($key);
                $newDefinition = $newFieldsByKey->get($key);

                if (! $newDefinition) {
                    throw ValidationException::withMessages([
                        'registration_fields' => "The field [{$key}] cannot be removed because existing registrations contain an answer.",
                    ]);
                }

                if ($oldDefinition && ($oldDefinition['type'] ?? null) !== ($newDefinition['type'] ?? null)) {
                    throw ValidationException::withMessages([
                        'registration_fields' => "The field [{$key}] cannot change type because existing registrations contain an answer.",
                    ]);
                }

                if (($newDefinition['type'] ?? null) === 'select'
                    && $answer !== null && $answer !== ''
                    && ! in_array((string) $answer, array_map('strval', $newDefinition['options'] ?? []), true)) {
                    throw ValidationException::withMessages([
                        'registration_fields' => "The options for field [{$key}] cannot exclude an answer already stored by a registration.",
                    ]);
                }
            }
        }
    }

}
