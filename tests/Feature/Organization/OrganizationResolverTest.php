<?php

namespace Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use App\Support\Organization\OrganizationResolver;
use Tests\TestCase;

class OrganizationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_an_organization_from_a_subdomain(): void
    {
        config([
            'app.organization_base_domain' => 'saflivo.com',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $resolver = app(OrganizationResolver::class);

        $resolved = $resolver->resolve('org1.saflivo.com');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($organization));
    }

    public function test_it_returns_null_for_an_unknown_subdomain(): void
    {
        config([
            'app.organization_base_domain' => 'saflivo.com',
        ]);

        $resolver = app(OrganizationResolver::class);

        $this->assertNull(
            $resolver->resolve('unknown.saflivo.com')
        );
    }

    public function test_it_does_not_resolve_an_inactive_organization(): void
    {
        config([
            'app.organization_base_domain' => 'saflivo.com',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => false,
        ]);

        $resolver = app(OrganizationResolver::class);

        $this->assertNull(
            $resolver->resolve('org1.saflivo.com')
        );
    }

    public function test_it_does_not_resolve_an_unrelated_domain(): void
    {
        config([
            'app.organization_base_domain' => 'saflivo.com',
        ]);

        $resolver = app(OrganizationResolver::class);

        $this->assertNull(
            $resolver->resolve('org1.example.com')
        );
    }

    public function test_it_resolves_the_default_organization_in_single_tenant_mode(): void
    {
        config([
            'app.deployment_mode' => 'single',
            'app.default_organization_id' => null,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        config([
            'app.default_organization_id' => $organization->id,
        ]);

        $resolver = app(OrganizationResolver::class);

        $resolved = $resolver->resolve('saradheeyam.com');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($organization));
    }

    public function test_it_does_not_resolve_an_inactive_default_organization(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => false,
        ]);

        config([
            'app.deployment_mode' => 'single',
            'app.default_organization_id' => $organization->id,
        ]);

        $resolver = app(OrganizationResolver::class);

        $this->assertNull(
            $resolver->resolve('saradheeyam.com')
        );
    }

    public function test_multi_tenant_mode_resolves_by_subdomain(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $resolver = app(OrganizationResolver::class);

        $resolved = $resolver->resolve('org1.lvh.me');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($organization));
    }
}