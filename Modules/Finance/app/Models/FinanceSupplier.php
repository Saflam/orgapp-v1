<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class FinanceSupplier extends Model
{
    protected $table = 'finance_suppliers';
    protected $fillable = ['organization_id', 'code', 'name', 'email', 'phone', 'tax_number', 'billing_address', 'payment_terms_days', 'is_active', 'metadata'];
    protected function casts(): array { return ['billing_address' => 'array', 'payment_terms_days' => 'integer', 'is_active' => 'boolean', 'metadata' => 'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function bills(): HasMany { return $this->hasMany(FinancePurchaseBill::class, 'finance_supplier_id'); }
}
