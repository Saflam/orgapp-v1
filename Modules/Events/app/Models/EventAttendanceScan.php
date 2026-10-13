<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class EventAttendanceScan extends Model
{
    protected $fillable = [
        'organization_id', 'event_id', 'event_pass_id', 'event_pass_attendee_id', 'scanned_by',
        'action', 'quantity', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'scanned_at' => 'immutable_datetime',
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

    public function pass(): BelongsTo
    {
        return $this->belongsTo(EventPass::class, 'event_pass_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(EventPassAttendee::class, 'event_pass_attendee_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'scanned_by');
    }
}
