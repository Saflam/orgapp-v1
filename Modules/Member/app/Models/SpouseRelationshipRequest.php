<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class SpouseRelationshipRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'requester_application_id',
        'requester_member_id',
        'target_member_id',
        'status',
        'responded_by_user_id',
        'responded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'requester_application_id');
    }

    public function requesterMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_member_id');
    }

    public function targetMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'target_member_id');
    }
}
