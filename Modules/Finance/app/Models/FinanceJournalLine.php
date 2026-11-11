<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Modules\Finance\Enums\FinanceJournalStatus;

class FinanceJournalLine extends Model
{
    protected $table = 'finance_journal_lines';

    protected $fillable = [
        'journal_id', 'finance_account_id', 'finance_cost_center_id',
        'description', 'debit', 'credit', 'metadata',
    ];

    protected function casts(): array
    {
        return ['debit' => 'decimal:4', 'credit' => 'decimal:4', 'metadata' => 'array'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(FinanceJournal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinanceCostCenter::class, 'finance_cost_center_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            if ($line->journal()->value('status') === FinanceJournalStatus::POSTED->value) {
                throw new RuntimeException('Lines belonging to posted journals are immutable.');
            }
        });

        static::deleting(function (self $line): void {
            if ($line->journal()->value('status') === FinanceJournalStatus::POSTED->value) {
                throw new RuntimeException('Lines belonging to posted journals cannot be deleted.');
            }
        });
    }
}
