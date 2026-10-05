<?php

namespace Modules\Member\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Permission;
use Modules\Member\Providers\MemberPermissionProvider;

class MemberPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (app(MemberPermissionProvider::class)->permissions() as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }
    }
}
