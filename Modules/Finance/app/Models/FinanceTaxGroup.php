<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Organization;

class FinanceTaxGroup extends Model
{
    protected $table = 'finance_tax_groups';

    protected $fillable = ['organization_id', 'code', 'name', 'applies_to', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function rates(): BelongsToMany
    {
        return $this->belongsToMany(FinanceTaxRate::class, 'finance_tax_group_rates', 'finance_tax_group_id', 'finance_tax_rate_id')->withPivot('position')->withTimestamps()->orderByPivot('position');
    }
}
