<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Livewire\Organizations\OrganizationIndex;
use Modules\Core\Livewire\Organizations\OrganizationWizard;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class OrganizationManagementUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizationDefinitionProvisioningService::class)->provision();
    }

    public function test_system_admin_can_open_organization_list(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        Organization::factory()->create(['name' => 'Test Association', 'code' => 'TEST']);

        $this->actingAs($admin)
            ->get(route('core.organizations.index'))
            ->assertOk()
            ->assertSee('Test Association');
    }

    public function test_non_system_admin_cannot_open_system_organization_list(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);

        $this->actingAs($user)
            ->get(route('core.organizations.index'))
            ->assertForbidden();
    }

    public function test_organization_creation_wizard_is_system_admin_only(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);

        $this->actingAs($user)
            ->get(route('core.organizations.create'))
            ->assertForbidden();
    }

    public function test_system_admin_can_complete_organization_creation_wizard(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);

        Livewire::actingAs($admin)
            ->test(OrganizationWizard::class)
            ->set('organization.name', 'Wizard Association')
            ->set('organization.code', 'WIZARD')
            ->set('organization.timezone', 'Asia/Kolkata')
            ->set('organization.currency', 'INR')
            ->call('next')
            ->set('super_admin.name', 'Org Admin')
            ->set('super_admin.email', 'wizard-admin@example.test')
            ->call('next')
            ->set('notification_channels.sms.use_system_default', true)
            ->call('next')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organizations', [
            'name' => 'Wizard Association',
            'code' => 'WIZARD',
        ]);
    }
}
