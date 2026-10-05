<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class CreateOrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_system_admin_can_create_an_organization(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = $systemAdmin->createToken('test-token')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+919876543210',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'organization.name',
                'Kerala Cultural Association',
            )
            ->assertJsonPath(
                'organization.code',
                'KCA',
            )
            ->assertJsonPath(
                'organization.slug',
                'kerala-cultural-association',
            );

        $this->assertDatabaseHas('organizations', [
            'name' => 'Kerala Cultural Association',
            'code' => 'KCA',
            'slug' => 'kerala-cultural-association',
        ]);
    }

    public function test_non_system_admin_cannot_create_an_organization(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_unauthenticated_user_cannot_create_an_organization(): void
    {
        $response = $this->postJson('/api/v1/system/organizations', [
            'organization' => [
                'name' => 'Kerala Cultural Association',
                'code' => 'KCA',
            ],
            'super_admin' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_creating_an_organization_creates_its_super_admin(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = $systemAdmin->createToken('test-token')->plainTextToken;

        $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+919876543210',
                ],
            ])
            ->assertCreated();

        $organization = Organization::query()
            ->where('code', 'KCA')
            ->firstOrFail();

        $superAdmin = User::query()
            ->where('email', 'john@example.com')
            ->firstOrFail();

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $superAdmin->id)
            ->firstOrFail();

        $role = Role::query()
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->where('is_system', true)
            ->firstOrFail();

        $this->assertSame('active', $membership->status);

        $this->assertDatabaseHas('role_assignments', [
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);
    }

    public function test_creating_an_organization_does_not_create_a_member_record(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = $systemAdmin->createToken('test-token')->plainTextToken;

        $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_required_modules_are_enabled_when_an_organization_is_created(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = $systemAdmin->createToken('test-token')->plainTextToken;

        $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ])
            ->assertCreated();

        $organization = Organization::query()
            ->where('code', 'KCA')
            ->firstOrFail();

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Core',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Member',
            'is_enabled' => true,
        ]);
    }

    public function test_other_modules_are_disabled_by_default(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $response = $this
            ->withToken(
                $systemAdmin->createToken('test-token')->plainTextToken
            )
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        $response->assertCreated();

        $organization = Organization::query()
            ->where('code', 'KCA')
            ->firstOrFail();

        foreach (['Committee', 'Finance'] as $module) {
            $this->assertDatabaseHas('organization_modules', [
                'organization_id' => $organization->id,
                'module' => $module,
                'is_enabled' => false,
            ]);
        }
    }

    public function test_existing_super_admin_email_is_rejected(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $token = $systemAdmin->createToken('test-token')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Kerala Cultural Association',
                    'code' => 'KCA',
                ],
                'super_admin' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'super_admin.email',
            ]);

        $this->assertDatabaseMissing('organizations', [
            'code' => 'KCA',
        ]);
    }
}