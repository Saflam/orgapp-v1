<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use RuntimeException;

class FinanceJournal extends Model
{
    protected $table = 'finance_journals';

    protected $fillable = [
        'organization_id',
        'finance_scope_id',
        'finance_period_id',
        'journal_number',
        'transaction_date',
        'description',
        'source_type',
        'source_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'status' => FinanceJournalStatus::class,
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

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancePeriod::class, 'finance_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinanceJournalLine::class, 'journal_id');
    }

    public function isPosted(): bool
    {
        return $this->status === FinanceJournalStatus::POSTED;
    }

    protected static function booted(): void
    {
        static::updating(function (self $journal): void {
            if ($journal->getRawOriginal('status') === FinanceJournalStatus::POSTED->value) {
                throw new RuntimeException('Posted journals are immutable.');
            }
        });

        static::deleting(function (self $journal): void {
            if ($journal->status === FinanceJournalStatus::POSTED) {
                throw new RuntimeException('Posted journals cannot be deleted.');
            }
        });
    }
}
