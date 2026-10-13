<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceScopeType;

class FinanceScope extends Model
{
    protected $table = 'finance_scopes';

    protected $fillable = [
        'organization_id', 'scope_type', 'scope_id', 'name', 'code', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return ['scope_type' => FinanceScopeType::class, 'is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class, 'scope_id'); }

    public function isOrganizationScope(): bool { return $this->scope_type === FinanceScopeType::ORGANIZATION; }
    public function isUnitScope(): bool { return $this->scope_type === FinanceScopeType::UNIT; }
}
