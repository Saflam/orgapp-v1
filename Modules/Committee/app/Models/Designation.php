<?php

namespace Modules\Committee\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Committee\Enums\DesignationScope;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;

class Designation extends Model
{
    use Sluggable;

    public function sluggable(): array
    {
        return [
            'code' => [
                'source' => 'name',
                'unique' => true,
                'onUpdate' => false,
                'extraScope' => fn ($query) => $query->where('organization_id', $this->organization_id),
            ],
        ];
    }
    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'scope',
        'description',
        'is_active',
        'sort_order',
        'is_key_position',
    ];

    protected function casts(): array
    {
        return [
            'scope' => DesignationScope::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'is_key_position' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'designation_permissions',
            'designation_id',
            'permission_id',
        );
    }
}
