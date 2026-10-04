<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;

class Committee extends Model implements AuthorizationContext
{
    protected $fillable = [
        'organization_id',
        'committee_type_id',
        'unit_id',
        'parent_committee_id',
        'name',
        'kind',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CommitteeKind::class,
            'status' => CommitteeStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(
            CommitteeType::class,
            'committee_type_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_committee_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_committee_id'
        );
    }

    public function terms(): HasMany
    {
        return $this->hasMany(CommitteeTerm::class);
    }

    public function contextType(): string
    {
        return 'committee';
    }

    public function contextId(): int|string
    {
        return $this->getKey();
    }

    public function organizationId(): int
    {
        return $this->organization_id;
    }
}