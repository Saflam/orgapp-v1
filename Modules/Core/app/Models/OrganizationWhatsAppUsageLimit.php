<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationWhatsAppUsageLimit extends Model
{
    protected $table = 'organization_whatsapp_usage_limits';

    protected $fillable = ['organization_id', 'monthly_limit', 'warning_threshold', 'period_start', 'period_reserved_count'];

    protected function casts(): array
    {
        return ['monthly_limit' => 'integer', 'warning_threshold' => 'integer', 'period_start' => 'date', 'period_reserved_count' => 'integer'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
