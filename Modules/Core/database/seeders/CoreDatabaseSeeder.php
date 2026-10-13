<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(AuthorizationDefinitionProvisioningService::class)->provision();
    }
}
