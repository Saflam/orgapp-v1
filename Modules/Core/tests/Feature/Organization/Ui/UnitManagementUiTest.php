<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Livewire\Organizations\UnitManagement;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Tests\TestCase;

class UnitManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_create_a_top_level_unit(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();

        Livewire::actingAs($admin)
            ->test(UnitManagement::class, ['organization' => $organization])
            ->set('name', 'Northern District')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Northern District');

        $this->assertDatabaseHas('units', [
            'organization_id' => $organization->id,
            'name' => 'Northern District',
            'code' => 'northern-district',
            'parent_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_unit_names_generate_unique_codes_within_the_organization(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();
        Unit::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'North',
            'code' => 'north',
        ]);

        Livewire::actingAs($admin)
            ->test(UnitManagement::class, ['organization' => $organization])
            ->set('name', 'North')
            ->call('save')
            ->assertHasNoErrors();

        $duplicate = Unit::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'North')
            ->where('code', '!=', 'north')
            ->first();

        $this->assertNotNull($duplicate, 'A second unit with the duplicate name should be created.');
        $this->assertStringStartsWith('north-', $duplicate->code);
        $this->assertNotSame('north', $duplicate->code);
        $this->assertSame(
            2,
            Unit::query()
                ->where('organization_id', $organization->id)
                ->where('name', 'North')
                ->count()
        );
    }

    public function test_parent_unit_must_belong_to_the_same_organization(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        Livewire::actingAs($admin)
            ->test(UnitManagement::class, ['organization' => $organization])
            ->set('name', 'Child Unit')
            ->set('parent_id', $otherUnit->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);
    }

    public function test_non_admin_cannot_open_unit_management_for_an_organization(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);
        $organization = Organization::factory()->create();

        $this->actingAs($user)
            ->get(route('core.organizations.units', $organization))
            ->assertForbidden();
    }
}
