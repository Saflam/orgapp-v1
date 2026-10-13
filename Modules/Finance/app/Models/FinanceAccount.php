<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Enums\FinanceNormalBalance;

class FinanceAccount extends Model
{
    protected $table = 'finance_accounts';

    protected $fillable = [
        'organization_id', 'finance_scope_id', 'parent_id', 'code', 'name', 'account_type',
        'normal_balance', 'is_system_managed', 'source_type', 'source_id', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => FinanceAccountType::class,
            'normal_balance' => FinanceNormalBalance::class,
            'is_system_managed' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function scope(): BelongsTo { return $this->belongsTo(FinanceScope::class, 'finance_scope_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}
