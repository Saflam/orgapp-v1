<?php

namespace Modules\Member\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MembershipApplication extends Model implements AuthorizationContext, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'user_id',
        'membership_type_id',
        'member_id',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('application-documents')
            ->useDisk(config('media-library.disk_name'));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(
            MembershipType::class,
            'membership_type_id'
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MembershipApplicationStatusHistory::class)
            ->latest('id');
    }

    public function organizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function contextType(): string
    {
        return $this->unitId() === null ? 'organization' : 'unit';
    }

    public function contextId(): int|string
    {
        return $this->unitId() ?? $this->organizationId();
    }

    public function unitId(): ?int
    {
        $unitId = data_get($this->data, 'member.unit_id');

        return $unitId === null ? null : (int) $unitId;
    }

    public function latestRejectionAllowsReapply(): bool
    {
        if ($this->status !== MembershipApplicationStatus::REJECTED) {
            return false;
        }

        return (bool) $this->statusHistories()
            ->where('to_status', MembershipApplicationStatus::REJECTED->value)
            ->value('allow_reapply');
    }
}
