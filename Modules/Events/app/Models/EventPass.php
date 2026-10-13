<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class EventPass extends Model
{
    protected $hidden = ['token_hash'];

    protected $fillable = [
        'organization_id', 'event_id', 'event_participant_id', 'token_hash',
        'status', 'capacity', 'current_inside', 'issued_at', 'issued_by', 'revoked_at',
        'notification_delivery_status', 'notification_sent_at', 'notification_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_inside' => 'integer',
            'issued_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'notification_sent_at' => 'immutable_datetime',
            'notification_failed_at' => 'immutable_datetime',
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

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function attendees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventPassAttendee::class, 'event_pass_id');
    }

    public function attendanceScans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventAttendanceScan::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }
}
