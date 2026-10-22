<?php

namespace Modules\Core\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Database\Factories\UnitFactory;

class Unit extends Model implements AuthorizationContext
{
    use HasFactory;
    use Sluggable;

    /**
     * Generate a stable, URL-friendly unit code from the unit name.
     * Duplicate names are suffixed within the same organization.
     */
    public function sluggable(): array
    {
        return [
            'code' => [
                'source' => 'name',
                'onUpdate' => false,
                'unique' => true,
                'maxLength' => 50,
            ],
        ];
    }

    /**
     * Unit codes only need to be unique inside their organization.
     */
    public function scopeWithUniqueSlugConstraints(
        Builder $query,
        Model $model,
        string $attribute,
        array $config,
        string $slug
    ): Builder {
        return $query->where('organization_id', $model->organization_id);
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'code',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function contextType(): string
    {
        return 'unit';
    }

    public function contextId(): int|string
    {
        return $this->getKey();
    }

    public function organizationId(): int
    {
        return $this->organization_id;
    }
}