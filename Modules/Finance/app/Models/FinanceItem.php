<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceItemSource;

class FinanceItem extends Model
{
    protected $table = 'finance_items';

    protected $fillable = [
        'organization_id', 'sku', 'title', 'unit', 'selling_price', 'currency', 'sales_account_id',
        'source', 'source_type', 'source_id', 'is_system_managed', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:4',
            'source' => FinanceItemSource::class,
            'is_system_managed' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function salesAccount(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'sales_account_id'); }
}
