<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;

class EventParticipant extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'member_id',
        'participant_type',
        'name',
        'email',
        'phone',
        'status',
        'invited_by',
        'invited_at',
        'registered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'immutable_datetime',
            'registered_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pass(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EventPass::class, 'event_participant_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'invited_by');
    }
}
