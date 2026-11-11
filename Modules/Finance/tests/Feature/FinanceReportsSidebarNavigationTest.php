<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class FinanceReportsSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_reports_are_nested_inside_the_finance_sidebar_group(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Member');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Reports Navigation Role',
            'code' => 'finance_reports_nav_' . Str::lower(Str::random(8)),
        ]);

        $permissions = collect([
            'finance.account.view',
            'finance.report.view',
            'finance.customer.view',
            'finance.sales_invoice.view',
        ])->map(fn (string $code) => Permission::create([
            'module' => 'finance',
            'name' => str($code)->headline(),
            'code' => $code,
        ]));

        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $url = 'http://' . $organization->slug . '.lvh.me:8000'
            . route('finance.accounts', [], false);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk()
            ->assertSee('Finance')
            ->assertSee('Reports')
            ->assertSee('Trial Balance')
            ->assertSee('Income Statement')
            ->assertSee('Balance Sheet')
            ->assertSee('Receivables & Payables')
            ->assertSee(route('finance.receivables-payables', [], false))
            ->assertSee('Customers & Suppliers')
            ->assertSee(route('finance.parties', [], false));

        $document = new \DOMDocument();
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $xpath = new \DOMXPath($document);
        $reportsGroups = $xpath->query(
            '//details[contains(concat(" ", normalize-space(@class), " "), " nav-group ")][summary//span[normalize-space(.)="Reports"]]'
        );

        $this->assertSame(1, $reportsGroups->length, 'Finance Reports sidebar group was not rendered.');

        $reportsGroup = $reportsGroups->item(0);
        file_put_contents(
            storage_path('logs/finance-sidebar-test.html'),
            $response->getContent()
        );
        $financeAncestors = $xpath->query(
            'ancestor::details[summary//span[normalize-space(.)="Finance"]]',
            $reportsGroup
        );

        $this->assertSame(
            1,
            $financeAncestors->length,
            'Reports must be nested inside the Finance sidebar group.'
        );

        $receivablesLinks = $xpath->query(
            '//a[contains(concat(" ", normalize-space(@class), " "), " nav-link ")][span[normalize-space(.)="Receivables & Payables"]]'
        );
        $this->assertSame(1, $receivablesLinks->length, 'Receivables & Payables sidebar link was not rendered.');

        $receivablesFinanceAncestors = $xpath->query(
            'ancestor::details[summary//span[normalize-space(.)="Finance"]]',
            $receivablesLinks->item(0)
        );
        $this->assertSame(
            1,
            $receivablesFinanceAncestors->length,
            'Receivables & Payables must be nested inside the Finance sidebar group.'
        );
    }

    public function test_receivables_and_payables_link_is_hidden_without_a_relevant_permission(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Member');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Accounts Navigation Role',
            'code' => 'finance_accounts_nav_' . Str::lower(Str::random(8)),
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Chart of Accounts',
            'code' => 'finance.account.view',
        ]);

        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $url = 'http://' . $organization->slug . '.lvh.me:8000'
            . route('finance.accounts', [], false);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk()
            ->assertSee('Finance')
            ->assertDontSee('Receivables & Payables')
            ->assertDontSee(route('finance.receivables-payables', [], false))
            ->assertDontSee('Customers & Suppliers')
            ->assertDontSee(route('finance.parties', [], false));
    }

}
