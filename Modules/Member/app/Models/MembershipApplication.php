<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;

class MembershipApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'membership_type_id',
        'status',
        'current_step',
        'completed_steps',
        'data',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipApplicationStatus::class,
            'current_step' => 'integer',
            'completed_steps' => 'array',
            'data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(
            MembershipType::class,
            'membership_type_id'
        );
    }
}