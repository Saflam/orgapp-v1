<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationNotificationChannel extends Model
{
    protected $fillable = [
        'organization_id',
        'channel',
        'is_allowed',
        'utility_allowed',
        'marketing_allowed',
        'is_enabled',
        'use_system_default',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
            'utility_allowed' => 'boolean',
            'marketing_allowed' => 'boolean',
            'is_enabled' => 'boolean',
            'use_system_default' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
