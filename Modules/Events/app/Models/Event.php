<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Events\Enums\EventScope;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Enums\EventVisibility;

class Event extends Model
{
    protected $fillable = [
        'organization_id',
        'unit_id',
        'category_id',
        'created_by',
        'title',
        'subtitle',
        'description',
        'venue_name',
        'venue_address',
        'starts_at',
        'ends_at',
        'timezone',
        'scope',
        'visibility',
        'registration_fields',
        'registration_opens_at',
        'registration_closes_at',
        'registration_capacity',
        'venue_capacity',
        'status',
        'cover_image_path',
        'thumbnail_image_path',
        'published_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'scope' => EventScope::class,
            'visibility' => EventVisibility::class,
            'status' => EventStatus::class,
            'registration_fields' => 'array',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
            'registration_capacity' => 'integer',
            'venue_capacity' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, int|string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published->value);
    }

    public function getScheduleStatusAttribute(): string
    {
        if ($this->status === EventStatus::Cancelled) {
            return 'cancelled';
        }

        if ($this->status !== EventStatus::Published) {
            return 'draft';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'completed';
        }

        if ($this->starts_at !== null && $this->starts_at->isPast()) {
            return 'ongoing';
        }

        return 'upcoming';
    }

    public function participants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function passes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventPass::class);
    }

    public function volunteers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventVolunteer::class);
    }
}
