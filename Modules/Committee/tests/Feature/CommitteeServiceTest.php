<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Services\CommitteeService;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class CommitteeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganizationWithCommitteeModule(): Organization
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Committee',
        );

        return $organization;
    }

    public function test_it_creates_a_recurring_committee(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'organization_id' => $organization->id,
            'kind' => CommitteeKind::RECURRING->value,
            'name' => 'Aluva Unit Committee',
        ]);
    }

    public function test_it_creates_a_temporary_committee(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Event',
            'code' => 'EVENT',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createTemporaryCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Saradhi Special Event Committee',
        );

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'kind' => CommitteeKind::TEMPORARY->value,
        ]);
    }

    public function test_temporary_committee_cannot_have_a_term(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Event',
            'code' => 'EVENT',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createTemporaryCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Special Event Committee',
        );

        $this->expectException(\DomainException::class);

        $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-03-31',
        );
    }

    public function test_it_creates_a_term_for_a_recurring_committee(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $term = $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-03-31',
        );

        $this->assertSame(
            CommitteeTermStatus::DRAFT,
            $term->status
        );
    }

    public function test_overlapping_terms_are_rejected(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-03-31',
        );

        $this->expectException(\DomainException::class);

        $service->createTerm(
            $committee,
            '2026-03-01',
            '2026-06-30',
        );
    }

    public function test_committee_type_from_another_organization_is_rejected(): void
    {
        $organizationA = $this->createOrganizationWithCommitteeModule();
        $organizationB = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $this->expectException(\DomainException::class);

        $service->createRecurringCommittee(
            organizationId: $organizationA->id,
            committeeTypeId: $type->id,
            name: 'Invalid Committee',
        );
    }

    public function test_parent_committee_from_another_organization_is_rejected(): void
    {
        $organizationA = $this->createOrganizationWithCommitteeModule();
        $organizationB = $this->createOrganizationWithCommitteeModule();

        $typeA = CommitteeType::create([
            'organization_id' => $organizationA->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $typeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $parent = $service->createRecurringCommittee(
            organizationId: $organizationB->id,
            committeeTypeId: $typeB->id,
            name: 'Organization B Committee',
        );

        $this->expectException(\DomainException::class);

        $service->createRecurringCommittee(
            organizationId: $organizationA->id,
            committeeTypeId: $typeA->id,
            name: 'Invalid Child Committee',
            parentCommitteeId: $parent->id,
        );
    }

    public function test_term_end_date_cannot_be_before_start_date(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->createTerm(
            $committee,
            '2026-03-31',
            '2026-01-01',
        );
    }

    public function test_term_start_and_end_date_cannot_be_the_same(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->createTerm(
            $committee,
            '2026-03-31',
            '2026-03-31',
        );
    }

    public function test_term_overlapping_at_end_is_rejected(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $service->createTerm(
            $committee,
            '2026-06-01',
            '2026-12-31',
        );

        $this->expectException(\DomainException::class);

        $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-06-15',
        );
    }

    public function test_term_completely_inside_existing_term_is_rejected(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-12-31',
        );

        $this->expectException(\DomainException::class);

        $service->createTerm(
            $committee,
            '2026-03-01',
            '2026-06-30',
        );
    }

    public function test_term_completely_surrounding_existing_term_is_rejected(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $service->createTerm(
            $committee,
            '2026-03-01',
            '2026-06-30',
        );

        $this->expectException(\DomainException::class);

        $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-12-31',
        );
    }

    public function test_adjacent_terms_are_allowed(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Aluva Unit Committee',
        );

        $firstTerm = $service->createTerm(
            $committee,
            '2026-01-01',
            '2026-03-31',
        );

        $secondTerm = $service->createTerm(
            $committee,
            '2026-04-01',
            '2026-12-31',
        );

        $this->assertSame(
            CommitteeTermStatus::DRAFT,
            $firstTerm->status
        );

        $this->assertSame(
            CommitteeTermStatus::DRAFT,
            $secondTerm->status
        );
    }

    public function test_different_committees_can_have_overlapping_terms(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $centralCommittee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Central Committee',
        );

        $girlsWingCommittee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Girls Wing Committee',
        );

        $centralTerm = $service->createTerm(
            $centralCommittee,
            '2026-01-01',
            '2026-12-31',
        );

        $girlsWingTerm = $service->createTerm(
            $girlsWingCommittee,
            '2026-01-01',
            '2026-12-31',
        );

        $this->assertSame(
            CommitteeTermStatus::DRAFT,
            $centralTerm->status
        );

        $this->assertSame(
            CommitteeTermStatus::DRAFT,
            $girlsWingTerm->status
        );
    }

    public function test_committee_cannot_be_created_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'The Committee module is not enabled for this organization.'
        );

        $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Disabled Committee',
        );
    }

    public function test_committee_can_be_created_when_module_is_enabled(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive',
            'code' => 'EXECUTIVE',
        ]);

        $service = app(CommitteeService::class);

        $committee = $service->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $type->id,
            name: 'Enabled Committee',
        );

        $this->assertDatabaseHas('committees', [
            'id' => $committee->id,
            'organization_id' => $organization->id,
        ]);
    }
}