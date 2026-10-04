<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Member\Models\Member;

class CommitteeMembership extends Model
{
    protected $fillable = [
        'committee_term_id',
        'member_id',
        'designation_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => CommitteeMembershipStatus::class,
        ];
    }

    public function committeeTerm(): BelongsTo
    {
        return $this->belongsTo(
            CommitteeTerm::class,
            'committee_term_id'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }
}