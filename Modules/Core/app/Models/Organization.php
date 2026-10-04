<?php

namespace Modules\Core\Models;

use App\Models\OrganizationMembership;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Database\Factories\OrganizationFactory;

class Organization extends Model implements AuthorizationContext
{
    use HasFactory;
    use Sluggable;

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'slug',
        'timezone',
        'currency',
        'is_active',
        'settings',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(OrganizationSetting::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    public function identifications(): HasMany
    {
        return $this->hasMany(
            OrganizationIdentification::class
        );
    }

    public function contextType(): string
    {
        return 'organization';
    }

    public function contextId(): int|string
    {
        return $this->getKey();
    }

    public function organizationId(): int
    {
        return $this->getKey();
    }
}