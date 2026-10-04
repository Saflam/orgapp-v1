<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Member\Models\MembershipType;

class FeePolicy extends Model
{
    protected $fillable = [
        'fee_type_id',
        'membership_type_id',
        'name',
        'amount',
        'frequency',
        'effective_from',
        'effective_to',
        'due_rule',
        'eligibility_rules',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'due_rule' => 'array',
            'eligibility_rules' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }
}
