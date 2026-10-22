<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCostReconciliationBatch extends Model
{
    protected $table = 'whatsapp_cost_reconciliation_batches';

    protected $fillable = [
        'source_filename', 'imported_by', 'row_count', 'matched_count',
        'unmatched_count', 'duplicate_count', 'invalid_count', 'discrepancy_count', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'imported_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WhatsAppCostReconciliationItem::class, 'batch_id');
    }
}
