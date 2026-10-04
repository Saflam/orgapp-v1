<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentificationType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function organizationIdentifications(): HasMany
    {
        return $this->hasMany(
            OrganizationIdentification::class
        );
    }
}