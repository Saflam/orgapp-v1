<?php

namespace Tests\Feature\Organization;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class ResolveOrganizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_the_current_organization(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $this->withoutMiddleware();

        $this->app
            ->make(CurrentOrganization::class)
            ->set($organization);

        $current = app(CurrentOrganization::class);

        $this->assertTrue($current->check());
        $this->assertTrue($current->get()->is($organization));
    }
}