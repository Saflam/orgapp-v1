<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class SpouseMembershipInvitation extends Model
{
    protected $fillable = [
        'organization_id', 'inviter_member_id', 'invited_user_id', 'email',
        'invitee_name', 'token_hash', 'status', 'expires_at',
        'accepted_at', 'membership_application_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviterMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'inviter_member_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'invited_user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }
}
