<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;

class MemberUnitHistory extends Model
{
    protected $fillable = [
        'organization_id',
        'member_id',
        'unit_id',
        'started_at',
        'ended_at',
        'source',
        'actor_user_id',
        'member_unit_transfer_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(MemberUnitTransfer::class, 'member_unit_transfer_id');
    }
}
