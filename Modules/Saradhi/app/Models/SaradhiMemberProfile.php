<?php

namespace Modules\Saradhi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;

class SaradhiMemberProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'governorate',
        'sndp_branch',
        'sndp_branch_number',
        'sndp_union',
        'introducer_name',
        'introducer_calling_code',
        'introducer_phone',
        'introducer_mid',
        'introducer_unit_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function introducerUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'introducer_unit_id');
    }
}