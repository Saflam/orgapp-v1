<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class MemberDependant extends Model
{
    protected $fillable = [
        'organization_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'metadata',
        'converted_member_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'metadata' => 'array',
            'converted_at' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(MemberDependantRelationship::class, 'dependant_id');
    }
}