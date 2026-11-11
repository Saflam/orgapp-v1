<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Finance\Providers\FinancePermissionProvider;
use Modules\Finance\Providers\FinanceRoleProvider;
use Tests\TestCase;

class FinanceJournalPermissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_permissions_are_registered_for_designation_assignment(): void
    {
        app(PermissionRegistrationService::class)->register(new FinancePermissionProvider());

        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'View Journal Entries',
            'code' => 'finance.journal.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'Post Journal Entries',
            'code' => 'finance.journal.post',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'Reverse Journal Entries',
            'code' => 'finance.journal.reverse',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'View Settlement Ledger',
            'code' => 'finance.settlement.view',
        ]);
        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'Manage Settlements',
            'code' => 'finance.settlement.manage',
        ]);
        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'name' => 'Confirm Settlements',
            'code' => 'finance.settlement.confirm',
        ]);
    }

    public function test_finance_administrator_role_includes_journal_permissions(): void
    {
        $roles = (new FinanceRoleProvider())->roles();
        $financeAdministrator = collect($roles)->firstWhere('code', 'finance_admin');

        $this->assertNotNull($financeAdministrator);
        $this->assertContains('finance.journal.view', $financeAdministrator['permissions']);
        $this->assertContains('finance.journal.post', $financeAdministrator['permissions']);
        $this->assertContains('finance.journal.reverse', $financeAdministrator['permissions']);
        $this->assertContains('finance.settlement.view', $financeAdministrator['permissions']);
        $this->assertContains('finance.settlement.manage', $financeAdministrator['permissions']);
        $this->assertContains('finance.settlement.confirm', $financeAdministrator['permissions']);
    }
}
