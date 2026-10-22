<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Cviebrock\EloquentSluggable\Sluggable;

class Role extends Model
{
    use Sluggable;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'is_system',
    ];

    public function sluggable(): array
    {
        // Preserve explicit role codes supplied by role providers and existing roles.
        // Slugs are generated only for new custom roles whose code is still empty.
        if ($this->is_system || filled($this->code)) {
            return [];
        }

        return [
            'code' => [
                'source' => 'name',
                'separator' => '_',
                'unique' => true,
                'onUpdate' => true,
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
            'role_permissions',
            'role_id',
            'permission_id',
        );
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}