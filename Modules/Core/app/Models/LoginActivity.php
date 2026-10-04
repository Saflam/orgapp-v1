<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class LoginActivity extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'token_id',
        'channel',
        'ip_address',
        'user_agent',
        'device_name',
        'platform',
        'logged_in_at',
        'last_used_at',
        'logged_out_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'last_used_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(
            PersonalAccessToken::class,
            'token_id'
        );
    }
}