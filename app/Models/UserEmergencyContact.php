<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmergencyContact extends Model
{
    protected $fillable = [
        'user_id',
        'contact_name',
        'relationship',
        'phone_e164',
        'country_iso2',
        'extension',
        'is_primary',
        'notes',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
