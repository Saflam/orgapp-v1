<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Committee\Enums\TrusteeAppointmentStatus;
use Modules\Member\Models\Member;

class TrusteeAppointment extends Model
{
    protected $fillable = [
        'trustee_id', 'committee_id', 'member_id', 'appointed_at',
        'ended_at', 'status', 'end_reason', 'created_by', 'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'appointed_at' => 'date',
            'ended_at' => 'date',
            'status' => TrusteeAppointmentStatus::class,
        ];
    }

    public function trustee(): BelongsTo
    {
        return $this->belongsTo(Trustee::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
