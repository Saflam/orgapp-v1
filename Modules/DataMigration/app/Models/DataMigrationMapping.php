<?php

namespace Modules\DataMigration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;

class DataMigrationMapping extends Model
{
    protected $fillable = [
        'migration_run_id',
        'organization_id',
        'source_connection',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'source_checksum',
        'mapped_at',
    ];

    protected function casts(): array
    {
        return [
            'mapped_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DataMigrationRun::class, 'migration_run_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
