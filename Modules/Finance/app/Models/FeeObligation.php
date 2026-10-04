<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;

class FeeObligation extends Model
{
    protected $fillable = [
        'member_id',
        'membership_id',
        'fee_type_id',
        'fee_policy_id',
        'amount',
        'period_start',
        'period_end',
        'due_at',
        'status',
        'waived_at',
        'waiver_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'date',
            'waived_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function feePolicy(): BelongsTo
    {
        return $this->belongsTo(FeePolicy::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}