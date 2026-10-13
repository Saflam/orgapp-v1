<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Enums\MemberUnitTransferApprovalStatus;

class MemberUnitTransferApproval extends Model
{
    protected $fillable = [
        'member_unit_transfer_id',
        'organization_id',
        'unit_id',
        'side',
        'status',
        'decided_by',
        'decided_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'side' => MemberUnitTransferApprovalSide::class,
            'status' => MemberUnitTransferApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo { return $this->belongsTo(MemberUnitTransfer::class, 'member_unit_transfer_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
