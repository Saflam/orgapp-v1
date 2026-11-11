<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use RuntimeException;

class FinanceSalesInvoice extends Model
{
    protected $table = 'finance_sales_invoices';
    protected $fillable = ['organization_id', 'finance_scope_id', 'finance_customer_id', 'finance_period_id', 'finance_journal_id', 'invoice_number', 'request_key', 'invoice_date', 'due_date', 'status', 'currency', 'subtotal', 'tax_total', 'total', 'paid_amount', 'balance_due', 'notes', 'metadata'];
    protected function casts(): array { return ['invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:4', 'tax_total' => 'decimal:4', 'total' => 'decimal:4', 'paid_amount' => 'decimal:4', 'balance_due' => 'decimal:4', 'metadata' => 'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function customer(): BelongsTo { return $this->belongsTo(FinanceCustomer::class, 'finance_customer_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(FinanceJournal::class, 'finance_journal_id'); }
    public function lines(): HasMany { return $this->hasMany(FinanceSalesInvoiceLine::class, 'finance_sales_invoice_id'); }
    public function allocations(): HasMany { return $this->hasMany(FinancePaymentAllocation::class, 'finance_sales_invoice_id'); }
    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->getRawOriginal('status') !== 'draft') {
                throw new RuntimeException('Posted sales invoices are immutable.');
            }
        });
        static::deleting(function (self $document): void {
            if ($document->getRawOriginal('status') !== 'draft') {
                throw new RuntimeException('Posted sales invoices cannot be deleted.');
            }
        });
    }

}
