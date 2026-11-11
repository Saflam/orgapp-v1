<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class FinanceSettlement extends Model
{
    protected $table = 'finance_settlements';

    protected $fillable = [
        'organization_id', 'finance_settlement_obligation_id', 'amount',
        'transaction_date', 'payer_cash_account_id', 'payee_cash_account_id',
        'finance_journal_id', 'status', 'reference', 'recorded_by',
        'confirmed_by', 'confirmed_at', 'rejected_by', 'rejected_at',
        'rejection_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function obligation(): BelongsTo { return $this->belongsTo(FinanceSettlementObligation::class, 'finance_settlement_obligation_id'); }
    public function payerCashAccount(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'payer_cash_account_id'); }
    public function payeeCashAccount(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'payee_cash_account_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(FinanceJournal::class, 'finance_journal_id'); }
    public function audits(): HasMany { return $this->hasMany(FinanceSettlementAudit::class, 'finance_settlement_id'); }
}
