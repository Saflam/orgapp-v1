<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;
use Modules\Core\Services\OrganizationIdentificationService;
use Tests\TestCase;

class OrganizationIdentificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_configure_an_identification_type(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $configuration = $service->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
        );

        $this->assertInstanceOf(
            OrganizationIdentification::class,
            $configuration
        );

        $this->assertTrue($configuration->is_enabled);
        $this->assertTrue($configuration->is_required);
        $this->assertTrue($configuration->requires_document);
    }

    public function test_configure_updates_existing_configuration(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $first = $service->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: false,
            requiresDocument: false,
        );

        $second = $service->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'organization_identifications',
            1
        );

        $this->assertDatabaseHas(
            'organization_identifications',
            [
                'id' => $first->id,
                'is_enabled' => true,
                'is_required' => true,
                'requires_document' => true,
            ]
        );
    }

    public function test_it_can_enable_an_identification_type(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $configuration = $service->enable(
            $organization,
            $identificationType,
        );

        $this->assertTrue(
            $configuration->is_enabled
        );

        $this->assertFalse(
            $configuration->is_required
        );

        $this->assertFalse(
            $configuration->requires_document
        );
    }

    public function test_it_can_disable_an_identification_type(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->enable(
            $organization,
            $identificationType,
        );

        $configuration = $service->disable(
            $organization,
            $identificationType,
        );

        $this->assertFalse(
            $configuration->is_enabled
        );
    }

    public function test_it_can_make_an_identification_type_required(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $configuration = $service->setRequired(
            organization: $organization,
            identificationType: $identificationType,
        );

        $this->assertTrue(
            $configuration->is_enabled
        );

        $this->assertTrue(
            $configuration->is_required
        );
    }

    public function test_it_can_make_a_document_required(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $configuration = $service->setDocumentRequired(
            organization: $organization,
            identificationType: $identificationType,
        );

        $this->assertTrue(
            $configuration->is_enabled
        );

        $this->assertFalse(
            $configuration->is_required
        );

        $this->assertTrue(
            $configuration->requires_document
        );
    }

    public function test_a_disabled_identification_cannot_be_required(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->disable(
            $organization,
            $identificationType,
        );

        $this->expectException(
            ValidationException::class
        );

        $service->setRequired(
            organization: $organization,
            identificationType: $identificationType,
        );
    }

    public function test_a_disabled_identification_cannot_require_a_document(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->disable(
            $organization,
            $identificationType,
        );

        $this->expectException(
            ValidationException::class
        );

        $service->setDocumentRequired(
            organization: $organization,
            identificationType: $identificationType,
        );
    }

    public function test_it_can_get_a_configuration(): void
    {
        $organization = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $created = $service->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: true,
            requiresDocument: false,
        );

        $configuration = $service->get(
            organization: $organization,
            identificationType: $identificationType,
        );

        $this->assertNotNull($configuration);

        $this->assertSame(
            $created->id,
            $configuration->id
        );
    }

    public function test_it_returns_only_enabled_identifications(): void
    {
        $organization = Organization::factory()->create();

        $enabledType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $disabledType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->enable(
            $organization,
            $enabledType,
        );

        $service->disable(
            $organization,
            $disabledType,
        );

        $enabled = $service->enabled($organization);

        $this->assertCount(1, $enabled);

        $this->assertSame(
            'civil_id',
            $enabled->first()->identificationType->code
        );
    }

    public function test_it_returns_only_required_enabled_identifications(): void
    {
        $organization = Organization::factory()->create();

        $requiredType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $optionalType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->configure(
            organization: $organization,
            identificationType: $requiredType,
            isEnabled: true,
            isRequired: true,
        );

        $service->configure(
            organization: $organization,
            identificationType: $optionalType,
            isEnabled: true,
            isRequired: false,
        );

        $required = $service->required(
            $organization
        );

        $this->assertCount(1, $required);

        $this->assertSame(
            'civil_id',
            $required->first()->identificationType->code
        );
    }

    public function test_identification_configuration_is_organization_scoped(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(OrganizationIdentificationService::class);

        $service->configure(
            organization: $organizationA,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: true,
        );

        $this->assertNotNull(
            $service->get(
                $organizationA,
                $identificationType
            )
        );

        $this->assertNull(
            $service->get(
                $organizationB,
                $identificationType
            )
        );
    }
}