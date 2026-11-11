<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use RuntimeException;

class FinancePurchaseBill extends Model
{
    protected $table = 'finance_purchase_bills';
    protected $fillable = ['organization_id', 'finance_scope_id', 'finance_supplier_id', 'finance_period_id', 'finance_journal_id', 'bill_number', 'request_key', 'supplier_invoice_number', 'bill_date', 'due_date', 'status', 'currency', 'subtotal', 'tax_total', 'total', 'paid_amount', 'balance_due', 'notes', 'metadata'];
    protected function casts(): array { return ['bill_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:4', 'tax_total' => 'decimal:4', 'total' => 'decimal:4', 'paid_amount' => 'decimal:4', 'balance_due' => 'decimal:4', 'metadata' => 'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(FinanceSupplier::class, 'finance_supplier_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(FinanceJournal::class, 'finance_journal_id'); }
    public function lines(): HasMany { return $this->hasMany(FinancePurchaseBillLine::class, 'finance_purchase_bill_id'); }
    public function allocations(): HasMany { return $this->hasMany(FinancePaymentAllocation::class, 'finance_purchase_bill_id'); }
    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->getRawOriginal('status') !== 'draft') {
                throw new RuntimeException('Posted purchase bills are immutable.');
            }
        });
        static::deleting(function (self $document): void {
            if ($document->getRawOriginal('status') !== 'draft') {
                throw new RuntimeException('Posted purchase bills cannot be deleted.');
            }
        });
    }

}
