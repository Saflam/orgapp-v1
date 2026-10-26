<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\MembershipNumberingSettingsService;
use Tests\TestCase;

class MembershipNumberingSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suggests_a_two_letter_prefix_from_the_organization_name(): void
    {
        $service = app(MembershipNumberingSettingsService::class);

        $this->assertSame('KC', $service->suggestPrefix('Kerala Cultural Association'));
        $this->assertSame('SA', $service->suggestPrefix('Saradhi'));
    }

    public function test_it_initializes_an_automatic_prefix_and_first_series(): void
    {
        $organization = Organization::factory()->create(['name' => 'Saradhi']);

        $numbering = app(MembershipNumberingSettingsService::class)->initialize($organization);

        $this->assertSame([
            'base_prefix' => 'SA',
            'series' => 'A',
            'next_sequence' => 1,
            'initialized' => true,
        ], $numbering);
        $this->assertSame('SA', $organization->fresh()->settings['membership_numbering']['base_prefix']);
    }

    public function test_it_derives_the_prefix_from_the_organization_name(): void
    {
        $organization = Organization::factory()->create(['name' => 'Saradhi']);

        $numbering = app(MembershipNumberingSettingsService::class)->initialize($organization);

        $this->assertSame('SA', $numbering['base_prefix']);
        $this->assertSame('A', $numbering['series']);
        $this->assertSame(1, $numbering['next_sequence']);
    }
}
