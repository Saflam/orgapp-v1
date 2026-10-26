<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberUnitTransferStatus;

class MemberUnitTransfer extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $transfer): void {
            if ($transfer->spouse_included === null) {
                $transfer->spouse_included = true;
            }
        });
    }

    protected $fillable = [
        'organization_id',
        'member_id',
        'from_unit_id',
        'to_unit_id',
        'spouse_included',
        'spouse_member_id',
        'status',
        'submitted_at',
        'completed_at',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'spouse_included' => 'boolean',
            'status' => MemberUnitTransferStatus::class,
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function fromUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'from_unit_id'); }
    public function toUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'to_unit_id'); }
    public function spouse(): BelongsTo { return $this->belongsTo(Member::class, 'spouse_member_id'); }
    public function rejectedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'rejected_by'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'cancelled_by'); }
    public function approvals(): HasMany { return $this->hasMany(MemberUnitTransferApproval::class); }
}
