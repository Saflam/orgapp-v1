<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinancePeriodStatus;

class FinancePeriod extends Model
{
    protected $table = 'finance_periods';

    protected $fillable = [
        'organization_id',
        'finance_scope_id',
        'name',
        'code',
        'starts_on',
        'ends_on',
        'status',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => FinancePeriodStatus::class,
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(FinanceScope::class, 'finance_scope_id');
    }

    public function isOpen(): bool
    {
        return $this->status === FinancePeriodStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === FinancePeriodStatus::CLOSED;
    }
}
