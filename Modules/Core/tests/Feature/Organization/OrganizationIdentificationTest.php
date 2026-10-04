<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;
use Tests\TestCase;

class OrganizationIdentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_configure_an_identification_type_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $configuration = OrganizationIdentification::create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => true,
            'requires_document' => true,
        ]);

        $this->assertDatabaseHas('organization_identifications', [
            'id' => $configuration->id,
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => true,
            'requires_document' => true,
        ]);
    }

    public function test_identification_configuration_is_isolated_between_organizations(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        OrganizationIdentification::create([
            'organization_id' => $organizationA->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => true,
            'requires_document' => false,
        ]);

        $this->assertTrue(
            $organizationA
                ->identifications()
                ->where(
                    'identification_type_id',
                    $identificationType->id
                )
                ->exists()
        );

        $this->assertFalse(
            $organizationB
                ->identifications()
                ->where(
                    'identification_type_id',
                    $identificationType->id
                )
                ->exists()
        );
    }

    public function test_an_identification_type_can_be_enabled_without_being_required(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $configuration = OrganizationIdentification::create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => false,
            'requires_document' => false,
        ]);

        $this->assertTrue($configuration->is_enabled);
        $this->assertFalse($configuration->is_required);
        $this->assertFalse($configuration->requires_document);
    }

    public function test_document_requirement_is_independent_from_identification_requirement(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $configuration = OrganizationIdentification::create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => false,
            'requires_document' => true,
        ]);

        $this->assertFalse($configuration->is_required);
        $this->assertTrue($configuration->requires_document);
    }

    public function test_the_same_identification_type_cannot_be_configured_twice_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        OrganizationIdentification::create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        OrganizationIdentification::create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
        ]);
    }
}