<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotificationChannel extends Model
{
    protected $fillable = [
        'channel',
        'is_enabled',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }
}
