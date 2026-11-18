<?php

namespace Modules\DataMigration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Organization;

class DataMigrationRun extends Model
{
    protected $fillable = [
        'organization_id',
        'source_connection',
        'status',
        'is_dry_run',
        'options',
        'summary',
        'started_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_dry_run' => 'boolean',
            'options' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DataMigrationBatch::class, 'migration_run_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DataMigrationIssue::class, 'migration_run_id');
    }
}
