<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCostReconciliationItem extends Model
{
    protected $table = 'whatsapp_cost_reconciliation_items';

    protected $fillable = [
        'batch_id', 'usage_id', 'organization_id', 'provider_message_id',
        'provider_cost_amount', 'provider_cost_currency', 'pricing_category',
        'billable', 'provider_occurred_at', 'reconciliation_status', 'issue', 'source_row',
        'internal_cost_amount', 'internal_cost_currency', 'internal_pricing_category', 'internal_billable', 'discrepancy_details',
    ];

    protected function casts(): array
    {
        return [
            'provider_cost_amount' => 'decimal:6',
            'billable' => 'boolean',
            'provider_occurred_at' => 'datetime',
            'source_row' => 'array',
            'internal_cost_amount' => 'decimal:6',
            'internal_billable' => 'boolean',
            'discrepancy_details' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCostReconciliationBatch::class, 'batch_id');
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageUsage::class, 'usage_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
