<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class FinancePaymentAllocation extends Model
{
    protected $table = 'finance_payment_allocations';
    protected $fillable = ['finance_payment_id', 'finance_sales_invoice_id', 'finance_purchase_bill_id', 'finance_cost_center_id', 'amount'];
    protected function casts(): array { return ['amount' => 'decimal:4']; }
    public function payment(): BelongsTo { return $this->belongsTo(FinancePayment::class, 'finance_payment_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(FinanceSalesInvoice::class, 'finance_sales_invoice_id'); }
    public function bill(): BelongsTo { return $this->belongsTo(FinancePurchaseBill::class, 'finance_purchase_bill_id'); }
    public function costCenter(): BelongsTo { return $this->belongsTo(FinanceCostCenter::class, 'finance_cost_center_id'); }
    protected static function booted(): void
    {
        static::updating(function (): void { throw new RuntimeException('Payment allocations are immutable.'); });
        static::deleting(function (): void { throw new RuntimeException('Payment allocations cannot be deleted.'); });
    }
}
