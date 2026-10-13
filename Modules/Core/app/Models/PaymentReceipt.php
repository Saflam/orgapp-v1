<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    protected $fillable = [
        'organization_id',
        'owner_module',
        'source_type',
        'source_id',
        'receipt_number',
        'issued_at',
        'data',
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
