<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Database\Factories\UnitFactory;

class Unit extends Model implements AuthorizationContext
{
    use HasFactory;

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