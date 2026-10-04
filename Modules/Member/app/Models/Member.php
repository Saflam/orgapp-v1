<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Database\Factories\MemberFactory;

class Member extends Model
{
    use HasFactory;

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'user_id',
        'unit_id',
        'membership_number',
        'joined_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'metadata' => 'array',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(MemberRelationship::class);
    }

    public function relatedMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'member_relationships',
            'member_id',
            'related_member_id'
        )->withPivot([
            'relationship_type',
            'started_at',
            'ended_at',
        ]);
    }

    public function getFullNameAttribute(): string
    {
        return (string) $this->user?->name;
    }

    public function dependantRelationships(): HasMany
    {
        return $this->hasMany(MemberDependantRelationship::class);
    }

    public function dependants(): BelongsToMany
    {
        return $this->belongsToMany(
            MemberDependant::class,
            'member_dependant_relationships',
            'member_id',
            'dependant_id'
        )->withPivot([
            'relationship_type',
            'started_at',
            'ended_at',
            'metadata',
        ]);
    }
}