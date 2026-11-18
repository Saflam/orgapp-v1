<?php

namespace Modules\DataMigration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckLegacyConnection extends Command
{
    protected $signature = 'datamigration:check-legacy
        {--connection= : Override the configured legacy database connection}';

    protected $description = 'Verify read access to the configured legacy database and required source tables.';

    public function handle(): int
    {
        $connectionName = $this->option('connection')
            ?: config('datamigration.legacy_connection', 'legacy');

        if (! config("database.connections.{$connectionName}")) {
            $this->components->error(
                "Database connection [{$connectionName}] is not configured."
            );

            return self::FAILURE;
        }

        try {
            $connection = DB::connection($connectionName);
            $connection->getPdo();

            $databaseName = $connection->getDatabaseName();

            $this->components->info('Legacy database connection successful.');
            $this->line("Connection: {$connectionName}");
            $this->line("Database: {$databaseName}");

            $requiredTables = config('datamigration.source_tables', []);

            if ($requiredTables === []) {
                $this->components->warn('No required source tables are configured.');

                return self::SUCCESS;
            }

            $schema = Schema::connection($connectionName);
            $missingTables = [];
            $rows = [];

            foreach ($requiredTables as $table) {
                if (! $schema->hasTable($table)) {
                    $missingTables[] = $table;
                    $rows[] = [$table, 'Missing'];
                    continue;
                }

                $rows[] = [
                    $table,
                    number_format($connection->table($table)->count()),
                ];
            }

            $this->table(['Source table', 'Rows'], $rows);

            if ($missingTables !== []) {
                $this->components->error(
                    'Required source tables are missing: '.implode(', ', $missingTables)
                );

                return self::FAILURE;
            }

            $this->components->success(
                'All configured legacy source tables are available for inspection.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);

            $this->components->error(
                'Could not inspect the legacy database: '.$exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
