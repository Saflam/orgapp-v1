<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Member\Enums\MembershipApplicationStatus;

class MembershipApplicationStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_application_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'allow_reapply',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => MembershipApplicationStatus::class,
            'to_status' => MembershipApplicationStatus::class,
            'allow_reapply' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            MembershipApplication::class,
            'membership_application_id'
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
