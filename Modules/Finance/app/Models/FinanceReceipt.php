<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceReceiptStatus;
use RuntimeException;

class FinanceReceipt extends Model
{
    protected $table = 'finance_receipts';

    protected $fillable = [
        'organization_id',
        'finance_scope_id',
        'finance_journal_id',
        'receipt_number',
        'issued_at',
        'status',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'status' => FinanceReceiptStatus::class,
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

    public function journal(): BelongsTo
    {
        return $this->belongsTo(FinanceJournal::class, 'finance_journal_id');
    }

    public function isIssued(): bool
    {
        return $this->status === FinanceReceiptStatus::ISSUED;
    }

    protected static function booted(): void
    {
        static::updating(function (self $receipt): void {
            throw new RuntimeException('Finance receipts are immutable.');
        });

        static::deleting(function (self $receipt): void {
            throw new RuntimeException('Finance receipts cannot be deleted.');
        });
    }
}
