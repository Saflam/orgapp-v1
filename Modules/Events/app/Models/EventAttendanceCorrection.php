<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class EventAttendanceCorrection extends Model
{
    protected $fillable = [
        'organization_id', 'event_id', 'event_pass_id', 'event_pass_attendee_id', 'corrected_by',
        'before_inside', 'after_inside', 'occupancy_delta', 'before_attendee_status', 'after_attendee_status', 'reason', 'corrected_at',
    ];

    protected function casts(): array
    {
        return ['before_inside' => 'integer', 'after_inside' => 'integer', 'occupancy_delta' => 'integer', 'corrected_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function pass(): BelongsTo { return $this->belongsTo(EventPass::class, 'event_pass_id'); }
    public function attendee(): BelongsTo { return $this->belongsTo(EventPassAttendee::class, 'event_pass_attendee_id'); }
    public function corrector(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'corrected_by'); }
}
