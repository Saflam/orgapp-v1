<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class FinanceSettlementObligation extends Model
{
    protected $table = 'finance_settlement_obligations';

    protected $fillable = [
        'organization_id', 'debtor_scope_type', 'debtor_scope_id',
        'creditor_scope_type', 'creditor_scope_id', 'member_payment_id',
        'member_payment_line_id', 'original_amount', 'outstanding_amount',
        'currency', 'status', 'transaction_date', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'transaction_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function payment(): BelongsTo { return $this->belongsTo(\Modules\Member\Models\MemberPayment::class, 'member_payment_id'); }
    public function paymentLine(): BelongsTo { return $this->belongsTo(\Modules\Member\Models\MemberPaymentLine::class, 'member_payment_line_id'); }
    public function settlements(): HasMany { return $this->hasMany(FinanceSettlement::class, 'finance_settlement_obligation_id'); }
}
