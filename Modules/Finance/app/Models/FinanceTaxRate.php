<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Organization;

class FinanceTaxRate extends Model
{
    protected $table = 'finance_tax_rates';

    protected $fillable = [
        'organization_id', 'code', 'name', 'rate', 'applies_to',
        'sales_account_id', 'purchase_account_id', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function salesAccount(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'sales_account_id'); }
    public function purchaseAccount(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'purchase_account_id'); }
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(FinanceTaxGroup::class, 'finance_tax_group_rates', 'finance_tax_rate_id', 'finance_tax_group_id')->withPivot('position')->withTimestamps();
    }
}
