<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Committee\Enums\CommitteeTermStatus;

class CommitteeTerm extends Model
{
    protected $fillable = [
        'committee_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => CommitteeTermStatus::class,
        ];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CommitteeMembership::class);
    }

}