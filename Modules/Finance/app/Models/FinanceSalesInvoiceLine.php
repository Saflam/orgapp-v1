<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class FinanceSalesInvoiceLine extends Model
{
    protected $table = 'finance_sales_invoice_lines';
    protected $fillable = ['finance_sales_invoice_id', 'finance_item_id', 'finance_account_id', 'finance_cost_center_id', 'finance_tax_rate_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'line_subtotal', 'tax_amount', 'line_total', 'metadata'];
    protected function casts(): array { return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'tax_rate' => 'decimal:4', 'line_subtotal' => 'decimal:4', 'tax_amount' => 'decimal:4', 'line_total' => 'decimal:4', 'metadata' => 'array']; }
    public function invoice(): BelongsTo { return $this->belongsTo(FinanceSalesInvoice::class, 'finance_sales_invoice_id'); }
    public function account(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'finance_account_id'); }
    public function taxRate(): BelongsTo { return $this->belongsTo(FinanceTaxRate::class, 'finance_tax_rate_id'); }
    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            if ($line->invoice()->value('status') !== 'draft') throw new RuntimeException('Lines belonging to posted documents are immutable.');
        });
        static::deleting(function (self $line): void {
            if ($line->invoice()->value('status') !== 'draft') throw new RuntimeException('Lines belonging to posted documents cannot be deleted.');
        });
    }

}
