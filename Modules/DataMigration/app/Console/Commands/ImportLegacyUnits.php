<?php

namespace Modules\DataMigration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\DataMigration\Models\DataMigrationBatch;
use Modules\DataMigration\Models\DataMigrationIssue;
use Modules\DataMigration\Models\DataMigrationMapping;
use Modules\DataMigration\Models\DataMigrationRun;
use Throwable;

class ImportLegacyUnits extends Command
{
    protected $signature = 'datamigration:import-units
        {organization_id : Destination organization ID}
        {--execute : Perform the actual import; otherwise run a dry-run}
        {--chunk= : Override the configured chunk size}';

    protected $description = 'Import legacy member units into an organization.';

    public function handle(): int
    {
        $organizationId = (int) $this->argument('organization_id');
        $dryRun = ! $this->option('execute');
        $chunkSize = (int) ($this->option('chunk') ?: config('datamigration.chunk_size', 200));

        if ($organizationId < 1 || $chunkSize < 1 || $chunkSize > 1000) {
            $this->components->error('Invalid organization ID or chunk size.');
            return self::FAILURE;
        }

        $connectionName = config('datamigration.legacy_connection', 'legacy');

        try {
            $legacy = DB::connection($connectionName);
            $legacy->getPdo();

            if (! DB::table('organizations')->where('id', $organizationId)->exists()) {
                $this->components->error("Organization [{$organizationId}] does not exist.");
                return self::FAILURE;
            }

            if (! $legacy->getSchemaBuilder()->hasTable('member_units')) {
                $this->components->error('Legacy table [member_units] does not exist.');
                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->components->error('Could not validate the databases: '.$exception->getMessage());
            return self::FAILURE;
        }

        $run = DataMigrationRun::create([
            'organization_id' => $organizationId,
            'source_connection' => $connectionName,
            'status' => 'running',
            'is_dry_run' => $dryRun,
            'options' => ['entity' => 'units', 'chunk_size' => $chunkSize],
            'started_at' => now(),
        ]);

        $summary = [
            'entity' => 'units',
            'total_source' => (int) $legacy->table('member_units')->count(),
            'processed' => 0,
            'imported' => 0,
            'would_import' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $this->components->info(($dryRun ? 'Starting unit dry-run' : 'Starting unit import')." for organization {$organizationId}.");
        $batchNumber = 0;

        try {
            $legacy->table('member_units')->orderBy('id')->chunkById($chunkSize, function ($units) use (
                $run, $organizationId, $connectionName, $dryRun, &$summary, &$batchNumber
            ): void {
                $batchNumber++;
                $batch = DataMigrationBatch::create([
                    'migration_run_id' => $run->id,
                    'entity' => 'units',
                    'batch_number' => $batchNumber,
                    'status' => 'running',
                    'started_at' => now(),
                ]);

                $counts = ['processed' => 0, 'imported' => 0, 'would_import' => 0, 'skipped' => 0, 'failed' => 0];

                foreach ($units as $source) {
                    $counts['processed']++;
                    try {
                        $this->processUnit($source, $run, $organizationId, $connectionName, $dryRun, $counts);
                    } catch (Throwable $exception) {
                        $counts['failed']++;
                        $this->recordIssue($run, (string) $source->id, 'unit_import_failed', 'error', $exception->getMessage());
                    }
                }

                foreach ($counts as $key => $value) {
                    $summary[$key] += $value;
                }

                $batch->update([
                    'status' => $counts['failed'] > 0 ? 'completed_with_issues' : 'completed',
                    'processed_count' => $counts['processed'],
                    'imported_count' => $counts['imported'],
                    'skipped_count' => $counts['skipped'],
                    'failed_count' => $counts['failed'],
                    'cursor_value' => (string) $units->last()->id,
                    'completed_at' => now(),
                ]);
            }, 'id');
        } catch (Throwable $exception) {
            $summary['failed']++;
            $this->recordIssue($run, 'batch-'.$batchNumber, 'unit_batch_failed', 'error', $exception->getMessage());
        }

        $hasFailures = $summary['failed'] > 0;
        $run->update([
            'status' => $hasFailures ? 'completed_with_issues' : 'completed',
            'summary' => $summary,
            'completed_at' => now(),
        ]);

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Source units', $summary['total_source']],
            ['Processed', $summary['processed']],
            ['Imported', $summary['imported']],
            ['Would import', $summary['would_import']],
            ['Skipped', $summary['skipped']],
            ['Failed', $summary['failed']],
        ]);
        $this->line("Migration run ID: {$run->id}");

        if ($dryRun) {
            $this->components->info('Dry-run completed. No units or mappings were created. Migration tracking and issue records may have been recorded.');
        } elseif ($hasFailures) {
            $this->components->warn('Import completed with issues. Review the migration issues before continuing.');
        } else {
            $this->components->success('Unit import completed successfully.');
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    private function processUnit(object $source, DataMigrationRun $run, int $organizationId, string $connectionName, bool $dryRun, array &$counts): void
    {
        $sourceId = (string) $source->id;
        $name = trim((string) $source->name);
        $code = trim((string) $source->slug);

        if ($name === '' || mb_strlen($name) > 255) {
            $counts['failed']++;
            $this->recordIssue($run, $sourceId, 'invalid_unit_name', 'error', 'Unit name is empty or exceeds 255 characters.');
            return;
        }
        if ($code === '' || mb_strlen($code) > 50) {
            $counts['failed']++;
            $this->recordIssue($run, $sourceId, 'invalid_unit_code', 'error', 'Unit code is empty or exceeds 50 characters.');
            return;
        }

        $checksum = hash('sha256', json_encode((array) $source, JSON_THROW_ON_ERROR));
        $mapping = DataMigrationMapping::query()
            ->where('organization_id', $organizationId)
            ->where('source_connection', $connectionName)
            ->where('source_table', 'member_units')
            ->where('source_id', $sourceId)
            ->first();

        if ($mapping !== null) {
            $target = $mapping->target_id
                ? DB::table('units')->where('organization_id', $organizationId)->where('id', $mapping->target_id)->first()
                : null;
            if ($mapping->target_table !== 'units' || $target === null || $mapping->source_checksum !== $checksum) {
                $counts['failed']++;
                $this->recordIssue($run, $sourceId, 'existing_mapping_conflict', 'error', 'Existing unit mapping is missing, invalid, or has changed source data.');
                return;
            }
            $counts['skipped']++;
            return;
        }

        if (DB::table('units')->where('organization_id', $organizationId)->where('code', $code)->exists()) {
            $counts['failed']++;
            $this->recordIssue($run, $sourceId, 'unit_code_conflict', 'error', "Unit code [{$code}] already exists without a legacy mapping.");
            return;
        }

        if ($dryRun) {
            $counts['would_import']++;
            return;
        }

        DB::transaction(function () use ($source, $run, $organizationId, $connectionName, $sourceId, $name, $code, $checksum): void {
            $existingMapping = DataMigrationMapping::query()
                ->where('organization_id', $organizationId)
                ->where('source_connection', $connectionName)
                ->where('source_table', 'member_units')
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();
            if ($existingMapping !== null) {
                throw new \RuntimeException('A mapping was created concurrently. Rerun to verify it.');
            }
            if (DB::table('units')->where('organization_id', $organizationId)->where('code', $code)->lockForUpdate()->exists()) {
                throw new \RuntimeException("Unit code [{$code}] was created concurrently.");
            }

            $now = now();
            $targetId = DB::table('units')->insertGetId([
                'organization_id' => $organizationId,
                'parent_id' => null,
                'name' => $name,
                'code' => $code,
                'is_active' => (bool) $source->active,
                'metadata' => json_encode(['legacy_source' => [
                    'connection' => $connectionName,
                    'table' => 'member_units',
                    'id' => $sourceId,
                    'slug' => $code,
                ]], JSON_THROW_ON_ERROR),
                'created_at' => $source->created_at ?? $now,
                'updated_at' => $source->updated_at ?? $now,
            ]);

            DataMigrationMapping::create([
                'migration_run_id' => $run->id,
                'organization_id' => $organizationId,
                'source_connection' => $connectionName,
                'source_table' => 'member_units',
                'source_id' => $sourceId,
                'target_table' => 'units',
                'target_id' => (string) $targetId,
                'source_checksum' => $checksum,
                'mapped_at' => $now,
            ]);
        });

        $counts['imported']++;
    }

    private function recordIssue(DataMigrationRun $run, string $sourceId, string $code, string $severity, string $message): void
    {
        DataMigrationIssue::create([
            'migration_run_id' => $run->id,
            'entity' => 'units',
            'source_table' => 'member_units',
            'source_id' => $sourceId,
            'issue_code' => $code,
            'severity' => $severity,
            'message' => $message,
            'status' => 'open',
        ]);
    }
}
