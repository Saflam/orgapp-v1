<?php

namespace Modules\DataMigration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataMigrationBatch extends Model
{
    protected $fillable = [
        'migration_run_id',
        'entity',
        'batch_number',
        'cursor_value',
        'status',
        'processed_count',
        'imported_count',
        'skipped_count',
        'failed_count',
        'last_error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'processed_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DataMigrationRun::class, 'migration_run_id');
    }
}
