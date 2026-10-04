<?php

namespace Modules\Member\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Permission;

class MemberPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'module' => 'member',
                'name' => 'View Members',
                'code' => 'members.view',
                'description' => 'View the organization members list.',
            ],
            [
                'module' => 'member',
                'name' => 'Update Members',
                'code' => 'members.update',
                'description' => 'Update organization member information.',
            ],
            [
                'module' => 'member',
                'name' => 'View Own Profile',
                'code' => 'members.profile.view',
                'description' => 'View the authenticated member profile.',
            ],
            [
                'module' => 'member',
                'name' => 'Update Own Profile',
                'code' => 'members.profile.update',
                'description' => 'Update the authenticated member profile.',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }
    }
}