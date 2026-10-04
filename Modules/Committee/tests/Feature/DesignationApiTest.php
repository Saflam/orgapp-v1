<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class DesignationApiTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_it_lists_designations_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => 'Handles finances.',
            'is_active' => true,
        ]);

        Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'description' => 'Handles records.',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/designations"
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Secretary')
            ->assertJsonPath('data.1.name', 'Treasurer');
    }

    public function test_it_does_not_return_designations_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee',
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/designations"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_can_view_a_designation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => 'Handles finances.',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $designation->id)
            ->assertJsonPath('data.name', 'Treasurer')
            ->assertJsonPath('data.code', 'TREASURER')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_it_cannot_view_a_designation_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee',
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/designations/{$designation->id}"
        );

        $response->assertNotFound();
    }

    public function test_it_rejects_designation_list_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/designations"
        );

        $response->assertForbidden();
    }

    public function test_it_rejects_designation_view_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}"
        );

        $response->assertForbidden();
    }

    public function test_it_can_create_a_designation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations",
            [
                'name' => 'Treasurer',
                'code' => 'TREASURER',
                'description' => 'Handles finances.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Treasurer')
            ->assertJsonPath('data.code', 'TREASURER')
            ->assertJsonPath('data.description', 'Handles finances.')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('designations', [
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => 'Handles finances.',
            'is_active' => true,
        ]);
    }

    public function test_it_validates_designation_creation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'code',
            ]);
    }

    public function test_it_rejects_duplicate_designation_code_within_an_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations",
            [
                'name' => 'Finance Officer',
                'code' => 'TREASURER',
            ]
        );

        $response->assertStatus(500);
    }

    public function test_it_allows_the_same_designation_code_in_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee',
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        Designation::create([
            'organization_id' => $organizationA->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationB->id}/designations",
            [
                'name' => 'Treasurer',
                'code' => 'TREASURER',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'TREASURER');

        $this->assertDatabaseCount('designations', 2);
    }

    public function test_it_rejects_designation_creation_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations",
            [
                'name' => 'Treasurer',
                'code' => 'TREASURER',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('designations', 0);
    }

    public function test_it_can_update_a_designation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => 'Old description.',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}",
            [
                'name' => 'Chief Treasurer',
                'description' => 'Updated description.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $designation->id)
            ->assertJsonPath('data.name', 'Chief Treasurer')
            ->assertJsonPath('data.code', 'TREASURER')
            ->assertJsonPath('data.description', 'Updated description.')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'name' => 'Chief Treasurer',
            'code' => 'TREASURER',
            'description' => 'Updated description.',
        ]);
    }

    public function test_it_validates_designation_update(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_cannot_update_a_designation_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee',
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/v1/organizations/{$organizationA->id}/designations/{$designation->id}",
            [
                'name' => 'Chief Treasurer',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'name' => 'Treasurer',
        ]);
    }

    public function test_it_rejects_designation_update_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}",
            [
                'name' => 'Chief Treasurer',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'name' => 'Treasurer',
        ]);
    }

    public function test_it_can_activate_a_designation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}/activate"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $designation->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => true,
        ]);
    }

    public function test_it_can_deactivate_a_designation(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}/deactivate"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $designation->id)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => false,
        ]);
    }

    public function test_it_cannot_activate_a_designation_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee',
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        $designation = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationA->id}/designations/{$designation->id}/activate"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => false,
        ]);
    }

    public function test_it_rejects_designation_activation_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/designations/{$designation->id}/activate"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => false,
        ]);
    }
}
