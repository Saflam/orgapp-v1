<?php
namespace Tests\Feature\Organization;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class CurrentOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_set_and_retrieved(): void
    {
        $organization = Organization::factory()->create();

        $context = app(CurrentOrganization::class);

        $context->set($organization);

        $this->assertTrue($context->check());
        $this->assertTrue($context->get()->is($organization));
    }

    public function test_get_throws_when_no_organization_is_set(): void
    {
        $context = app(CurrentOrganization::class);

        $this->expectException(\RuntimeException::class);

        $context->get();
    }

    public function test_current_organization_is_a_singleton(): void
    {
        $first = app(CurrentOrganization::class);
        $second = app(CurrentOrganization::class);

        $this->assertSame($first, $second);
    }
}