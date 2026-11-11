<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceSettlementAudit extends Model
{
    protected $table = 'finance_settlement_audits';

    protected $fillable = ['finance_settlement_id', 'actor_user_id', 'action', 'before_state', 'after_state', 'notes'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array'];
    }

    public function settlement(): BelongsTo { return $this->belongsTo(FinanceSettlement::class, 'finance_settlement_id'); }
}
