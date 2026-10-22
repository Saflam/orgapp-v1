<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_CODE = 'organization.settings.manage';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => self::PERMISSION_CODE],
            [
                'module' => 'Core',
                'name' => 'Manage organization settings',
                'description' => 'Manage general settings for the organization.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')
            ->where('code', self::PERMISSION_CODE)
            ->value('id');

        $role = DB::table('roles')
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->where('is_system', true)
            ->first();

        // Existing installations may already have the global role. Attach
        // the new permission now; fresh installations will receive it when
        // CoreDatabaseSeeder provisions the authorization definitions.
        if ($role !== null && $permissionId !== null) {
            $timestamp = now();

            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('code', self::PERMISSION_CODE)
            ->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', DB::table('roles')
                    ->whereNull('organization_id')
                    ->where('code', 'super_admin')
                    ->where('is_system', true)
                    ->select('id'))
                ->delete();

            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
