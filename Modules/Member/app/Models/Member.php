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

    protected static function booted(): void
    {
        static::created(function (Member $member): void {
            app(\Modules\Member\Services\MemberUnitHistoryService::class)->ensureInitial($member);
        });
    }

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

    public function changes(): HasMany
    {
        return $this->hasMany(MemberChange::class)->latest('id');
    }

    public function unitHistories(): HasMany
    {
        return $this->hasMany(MemberUnitHistory::class)->orderBy('started_at');
    }

    public function unitAt(\DateTimeInterface|string $at): ?Unit
    {
        return $this->unitHistories()
            ->where('started_at', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $at);
            })
            ->with('unit')
            ->first()?->unit;
    }

    public function paymentObligations(): HasMany
    {
        return $this->hasMany(MemberPaymentObligation::class);
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