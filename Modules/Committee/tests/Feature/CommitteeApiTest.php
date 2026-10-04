<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Services\CommitteeService;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class CommitteeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_committees_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Test Committee',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committees"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_does_not_return_committees_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeA = CommitteeType::create([
            'organization_id' => $organizationA->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $service = app(CommitteeService::class);

        $service->createRecurringCommittee(
            organizationId: $organizationA->id,
            committeeTypeId: $committeeTypeA->id,
            name: 'Organization A Committee',
        );

        $service->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/committees"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Organization A Committee'
            );
    }

    public function test_it_rejects_listing_when_committee_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committees"
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'The Committee module is not enabled for this organization.',
            ]);
    }

    public function test_it_returns_not_found_for_nonexistent_organization(): void
    {
        $response = $this->getJson(
            '/api/v1/organizations/999999/committees'
        );

        $response->assertNotFound();
    }

    public function test_it_can_create_a_recurring_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees",
            [
                'committee_type_id' => $committeeType->id,
                'name' => '2026 Executive Committee',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.committee_type_id', $committeeType->id)
            ->assertJsonPath('data.name', '2026 Executive Committee');
    }

    public function test_it_rejects_committee_creation_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees",
            [
                'committee_type_id' => $committeeType->id,
                'name' => '2026 Executive Committee',
            ]
        );

        $response->assertForbidden();
    }

    public function test_it_can_view_a_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $committee->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.name', '2026 Executive Committee');
    }

    public function test_it_does_not_return_a_committee_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/committees/{$committee->id}"
        );

        $response->assertNotFound();
    }

    public function test_it_can_update_a_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Old Committee Name',
        );

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}",
            [
                'name' => 'Updated Committee Name',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $committee->id)
            ->assertJsonPath('data.name', 'Updated Committee Name');
    }

    public function test_it_cannot_update_a_committee_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        $response = $this->putJson(
            "/api/v1/organizations/{$organizationA->id}/committees/{$committee->id}",
            [
                'name' => 'Unauthorized Update',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'name' => 'Organization B Committee',
        ]);
    }

    public function test_it_can_archive_a_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/archive"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $committee->id)
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'status' => 'archived',
        ]);
    }

    public function test_it_cannot_archive_an_already_archived_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $committee->update([
            'status' => \Modules\Committee\Enums\CommitteeStatus::ARCHIVED,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/archive"
        );

        $response->assertStatus(422);
    }

    public function test_it_cannot_archive_a_committee_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationA->id}/committees/{$committee->id}/archive"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'status' => 'active',
        ]);
    }

    public function test_it_cannot_archive_when_committee_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        app(OrganizationModuleService::class)->disable(
            organization: $organization,
            module: 'Committee'
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/archive"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'status' => 'active',
        ]);
    }

    public function test_it_can_create_a_committee_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms",
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.committee_id', $committee->id)
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', '2026-12-31')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_it_cannot_create_a_term_for_a_temporary_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createTemporaryCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Temporary Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms",
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_it_cannot_create_a_term_for_a_committee_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationA->id}/committees/{$committee->id}/terms",
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        );

        $response->assertNotFound();
    }

    public function test_it_lists_terms_for_a_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-06-30',
        );

        app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-07-01',
            endDate: '2026-12-31',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms"
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.start_date', '2026-01-01')
            ->assertJsonPath('data.0.end_date', '2026-06-30')
            ->assertJsonPath('data.1.start_date', '2026-07-01')
            ->assertJsonPath('data.1.end_date', '2026-12-31');
    }

    public function test_it_does_not_list_terms_from_another_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committeeA = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee A',
        );

        $committeeB = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee B',
        );

        app(CommitteeService::class)->createTerm(
            committee: $committeeA,
            startDate: '2026-01-01',
            endDate: '2026-06-30',
        );

        app(CommitteeService::class)->createTerm(
            committee: $committeeB,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committeeA->id}/terms"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.committee_id', $committeeA->id);
    }

    public function test_it_cannot_list_terms_for_a_committee_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            organization: $organizationA,
            module: 'Committee'
        );

        $moduleService->enable(
            organization: $organizationB,
            module: 'Committee'
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committeeB = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $committeeTypeB->id,
            name: 'Organization B Committee',
        );

        app(CommitteeService::class)->createTerm(
            committee: $committeeB,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/committees/{$committeeB->id}/terms"
        );

        $response->assertNotFound();
    }

    public function test_it_can_activate_a_draft_committee_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}/activate"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $term->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('committee_terms', [
            'id' => $term->id,
            'status' => 'active',
        ]);
    }

    public function test_it_can_complete_an_active_committee_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $term = app(CommitteeService::class)->activateTerm($term);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}/complete"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $term->id)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('committee_terms', [
            'id' => $term->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_cannot_activate_a_completed_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $term = app(CommitteeService::class)->activateTerm($term);
        $term = app(CommitteeService::class)->completeTerm($term);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}/activate"
        );

        $response->assertStatus(422);
    }

    public function test_it_cannot_complete_a_draft_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}/complete"
        );

        $response->assertStatus(422);
    }

    public function test_it_cannot_activate_a_term_from_another_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committeeA = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee A',
        );

        $committeeB = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee B',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committeeB,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committeeA->id}/terms/{$term->id}/activate"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('committee_terms', [
            'id' => $term->id,
            'status' => 'draft',
        ]);
    }

    public function test_it_can_update_a_draft_committee_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}",
            [
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-02-01')
            ->assertJsonPath('data.end_date', '2027-01-31')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_it_can_extend_an_active_committee_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $term = app(CommitteeService::class)->activateTerm($term);

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}",
            [
                'start_date' => '2026-01-01',
                'end_date' => '2027-06-30',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', '2027-06-30')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_it_cannot_update_a_completed_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $term = app(CommitteeService::class)->activateTerm($term);
        $term = app(CommitteeService::class)->completeTerm($term);

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$term->id}",
            [
                'start_date' => '2026-01-01',
                'end_date' => '2027-06-30',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_it_rejects_an_update_that_creates_an_overlapping_term(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committee = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: '2026 Executive Committee',
        );

        $termA = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-01-01',
            endDate: '2026-06-30',
        );

        $termB = app(CommitteeService::class)->createTerm(
            committee: $committee,
            startDate: '2026-07-01',
            endDate: '2026-12-31',
        );

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committee->id}/terms/{$termB->id}",
            [
                'start_date' => '2026-06-01',
                'end_date' => '2026-12-31',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_terms', [
            'id' => $termB->id,
            'start_date' => '2026-07-01 00:00:00',
            'end_date' => '2026-12-31 00:00:00',
        ]);
    }

    public function test_it_cannot_update_a_term_from_another_committee(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee'
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'executive',
        ]);

        $committeeA = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee A',
        );

        $committeeB = app(CommitteeService::class)->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $committeeType->id,
            name: 'Committee B',
        );

        $term = app(CommitteeService::class)->createTerm(
            committee: $committeeB,
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $response = $this->putJson(
            "/api/v1/organizations/{$organization->id}/committees/{$committeeA->id}/terms/{$term->id}",
            [
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
            ]
        );

        $response->assertNotFound();
    }
}