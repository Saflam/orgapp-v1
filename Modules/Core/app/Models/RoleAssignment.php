<?php

namespace Modules\Core\Models;

use App\Models\OrganizationMembership;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignment extends Model
{
    protected $fillable = [
        'organization_membership_id',
        'role_id',
        'context_type',
        'context_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at ??= now();

        return (
            ($this->starts_at === null || $this->starts_at <= $at)
            && ($this->ends_at === null || $this->ends_at >= $at)
        );
    }

    public function organizationMembership(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationMembership::class,
            'organization_membership_id'
        );
    }

    public function role(): BelongsTo
        {
            return $this->belongsTo(Role::class);
        }

        public function scopeActive(
        Builder $query,
        ?Carbon $at = null
    ): Builder {
        $at ??= now();

        return $query
            ->where(function (Builder $query) use ($at) {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            });
    }
}