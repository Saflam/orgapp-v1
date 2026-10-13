<?php

namespace Modules\Events\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Models\EventCategory;

class EventCategoryService
{
    public function queryForOrganization(Organization $organization): Builder
    {
        return EventCategory::query()->where('organization_id', $organization->id);
    }

    public function create(Organization $organization, int $actorId, array $data): EventCategory
    {
        return DB::transaction(function () use ($organization, $actorId, $data): EventCategory {
            $data['organization_id'] = $organization->id;
            $data['created_by'] = $actorId;
            $data['is_system'] = false;
            $data['slug'] = $this->uniqueSlug($organization, $data['slug'] ?? $data['name']);

            return EventCategory::query()->create($data);
        });
    }

    public function update(EventCategory $category, Organization $organization, array $data): EventCategory
    {
        $this->assertOrganization($category, $organization);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'System event categories cannot be modified.',
            ]);
        }

        return DB::transaction(function () use ($category, $organization, $data): EventCategory {
            if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
                $candidate = $data['slug'] ?? $data['name'] ?? $category->slug;
                $data['slug'] = $this->uniqueSlug($organization, $candidate, (int) $category->id);
            }

            // The service controls category identity and system status.
            unset($data['organization_id'], $data['created_by'], $data['is_system']);
            $category->fill($data)->save();

            return $category->refresh();
        });
    }

    public function delete(EventCategory $category, Organization $organization): void
    {
        $this->assertOrganization($category, $organization);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'System event categories cannot be deleted.',
            ]);
        }

        if ($category->events()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'A category assigned to one or more events cannot be deleted. Deactivate it instead.',
            ]);
        }

        $category->delete();
    }

    private function uniqueSlug(Organization $organization, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            throw ValidationException::withMessages(['slug' => 'The category slug must contain letters or numbers.']);
        }

        $slug = $base;
        $suffix = 2;
        while (EventCategory::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function assertOrganization(EventCategory $category, Organization $organization): void
    {
        if ((int) $category->organization_id !== (int) $organization->id) {
            abort(404);
        }
    }
}
