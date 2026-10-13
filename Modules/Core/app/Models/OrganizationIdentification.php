<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationIdentification extends Model
{
    protected $fillable = [
        'organization_id',
        'identification_type_id',
        'is_enabled',
        'is_required',
        'requires_document',
        'document_requirements',
        'has_expiry',
        'expiry_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_required' => 'boolean',
            'requires_document' => 'boolean',
            'document_requirements' => 'array',
            'has_expiry' => 'boolean',
            'expiry_reminder_days' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(IdentificationType::class);
    }
}