<?php

namespace Modules\DataMigration\Providers;

use Modules\DataMigration\Console\Commands\CheckLegacyConnection;
use Modules\DataMigration\Console\Commands\ImportLegacyUnits;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DataMigrationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DataMigration';
    protected string $nameLower = 'data-migration';

    protected array $commands = [
        CheckLegacyConnection::class,
        ImportLegacyUnits::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            module_path($this->name, 'config/config.php'),
            'datamigration'
        );
    }
}
