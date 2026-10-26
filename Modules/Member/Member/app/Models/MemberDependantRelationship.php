<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDependantRelationship extends Model
{
    protected $fillable = [
        'member_id',
        'dependant_id',
        'relationship_type',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function dependant(): BelongsTo
    {
        return $this->belongsTo(MemberDependant::class, 'dependant_id');
    }
}