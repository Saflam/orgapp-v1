<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'fee_obligation_id',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
        'status',
        'reversed_at',
        'reversal_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function feeObligation(): BelongsTo
    {
        return $this->belongsTo(FeeObligation::class);
    }
}