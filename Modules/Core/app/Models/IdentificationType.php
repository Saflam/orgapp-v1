<?php

namespace Modules\Core\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentificationType extends Model
{
    use Sluggable;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    /**
     * Generate the globally unique identification code from the name only when an explicit code
     * was not supplied. Explicit codes (for example, civil_id) must be preserved because
     * organization settings and submitted identification payloads use this stable code.
     *
     * @return array<string, mixed>
     */
    public function sluggable(): array
    {
        return [
            'code' => [
                'source' => 'name',
                'unique' => true,
                'onUpdate' => false,
            ],
        ];
    }

    public function organizationIdentifications(): HasMany
    {
        return $this->hasMany(OrganizationIdentification::class);
    }
}
