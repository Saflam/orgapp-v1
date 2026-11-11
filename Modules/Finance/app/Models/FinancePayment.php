<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Core\Models\Organization;
use RuntimeException;

class FinancePayment extends Model
{
    protected $table = 'finance_payments';
    protected $fillable = ['organization_id', 'finance_scope_id', 'finance_period_id', 'finance_journal_id', 'direction', 'payment_number', 'request_key', 'payment_date', 'finance_account_id', 'amount', 'currency', 'reference', 'status', 'metadata'];
    protected function casts(): array { return ['payment_date' => 'date', 'amount' => 'decimal:4', 'metadata' => 'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function journal(): BelongsTo { return $this->belongsTo(FinanceJournal::class, 'finance_journal_id'); }
    public function account(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'finance_account_id'); }
    public function allocations(): HasMany { return $this->hasMany(FinancePaymentAllocation::class, 'finance_payment_id'); }
    public function receipt(): HasOne { return $this->hasOne(FinanceReceipt::class, 'finance_journal_id', 'finance_journal_id'); }
    protected static function booted(): void
    {
        static::updating(function (): void { throw new RuntimeException('Posted finance payments are immutable.'); });
        static::deleting(function (): void { throw new RuntimeException('Posted finance payments cannot be deleted.'); });
    }
}
