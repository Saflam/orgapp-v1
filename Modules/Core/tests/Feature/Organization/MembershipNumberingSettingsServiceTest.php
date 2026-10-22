<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_it_initializes_an_explicit_prefix_and_first_series(): void
    {
        $organization = Organization::factory()->create(['name' => 'Saradhi']);

        $numbering = app(MembershipNumberingSettingsService::class)->initialize($organization, 'sd');

        $this->assertSame([
            'base_prefix' => 'SD',
            'series' => 'A',
            'next_sequence' => 1,
            'initialized' => true,
        ], $numbering);
        $this->assertSame('SD', $organization->fresh()->settings['membership_numbering']['base_prefix']);
    }

    public function test_it_rejects_a_prefix_that_is_not_exactly_two_letters(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(ValidationException::class);
        app(MembershipNumberingSettingsService::class)->initialize($organization, 'SDA');
    }
}
