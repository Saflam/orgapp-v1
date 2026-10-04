<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationService;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_it_creates_an_organization(): void
    {
        $service = app(OrganizationService::class);

        $organization = $service->create([
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST',
                'slug' => 'test-organization',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+919876543210',
            ],
        ]);

        $this->assertInstanceOf(
            Organization::class,
            $organization
        );

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test-organization',
        ]);
    }

    public function test_member_module_is_enabled_by_default(): void
    {
        $service = app(OrganizationService::class);

        $organization = $service->create([
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST',
                'slug' => 'test-organization',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertTrue(
            app(\Modules\Core\Services\OrganizationModuleService::class)
                ->isEnabled($organization, 'Member')
        );
    }

    public function test_optional_modules_are_disabled_by_default(): void
    {
        $service = app(OrganizationService::class);

        $organization = $service->create([
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST',
                'slug' => 'test-organization',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $moduleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $this->assertFalse(
            $moduleService->isEnabled(
                $organization,
                'Committee'
            )
        );

        $this->assertFalse(
            $moduleService->isEnabled(
                $organization,
                'Events'
            )
        );

        $this->assertFalse(
            $moduleService->isEnabled(
                $organization,
                'Finance'
            )
        );
    }

    public function test_it_creates_the_organization_super_admin(): void
    {
        $service = app(OrganizationService::class);

        $organization = $service->create([
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST',
                'slug' => 'test-organization',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+919876543210',
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+919876543210',
        ]);

        $user = \App\Models\User::query()
            ->where('email', 'john@example.com')
            ->firstOrFail();

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_it_assigns_the_super_admin_role_when_creating_an_organization(): void
    {
        $service = app(OrganizationService::class);

        $organization = $service->create([
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST',
                'slug' => 'test-organization',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $user = \App\Models\User::query()
            ->where('email', 'john@example.com')
            ->firstOrFail();

        $membership = \App\Models\OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $role = \Modules\Core\Models\Role::query()
            ->where('code', 'super_admin')
            ->whereNull('organization_id')
            ->where('is_system', true)
            ->firstOrFail();

        $this->assertDatabaseHas('role_assignments', [
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);
    }

    public function test_it_rejects_an_existing_super_admin_email(): void
    {
        \App\Models\User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $service = app(OrganizationService::class);

        try {
            $service->create([
                'organization' => [
                    'name' => 'Test Organization',
                    'code' => 'TEST',
                    'slug' => 'test-organization',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'existing@example.com',
                ],
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                'A user with this email address already exists.',
                $exception->errors()['super_admin.email'][0]
            );
        }

        $this->assertDatabaseMissing('organizations', [
            'code' => 'TEST',
        ]);
    }

    public function test_organization_slug_is_generated_from_name(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Kerala Cultural Association',
        ]);

        $this->assertSame(
            'kerala-cultural-association',
            $organization->slug,
        );
    }
}