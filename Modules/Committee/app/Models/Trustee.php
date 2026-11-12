<?php

namespace Modules\Committee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;

class Trustee extends Model
{
    protected $fillable = ['organization_id', 'member_id', 'trustee_id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(TrusteeAppointment::class)->orderByDesc('appointed_at')->orderByDesc('id');
    }

    public function activeAppointment(): HasMany
    {
        return $this->hasMany(TrusteeAppointment::class)->where('status', 'active');
    }
}
