<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Events\MembershipStatusChanged;

class Membership extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Membership $membership): void {
            if (! $membership->wasChanged('status')) {
                return;
            }

            $membership->loadMissing('member');

            if ($membership->member) {
                event(new MembershipStatusChanged(
                    $membership->member,
                    $membership,
                    $membership->getRawOriginal('status'),
                    $membership->status,
                ));
            }
        });
    }

    protected $fillable = [
        'member_id',
        'membership_type_id',
        'starts_at',
        'ends_at',
        'status',
        'status_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => MembershipStatus::class,
            'metadata' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function paymentObligations(): HasMany
    {
        return $this->hasMany(MemberPaymentObligation::class);
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }
}
