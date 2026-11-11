<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class FinanceCostCenter extends Model
{
    protected $table = 'finance_cost_centers';

    protected $fillable = [
        'organization_id', 'name', 'code', 'owner_type', 'owner_id', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isCentral(): bool
    {
        return $this->owner_type === 'organization' && (int) $this->owner_id === (int) $this->organization_id;
    }
}
