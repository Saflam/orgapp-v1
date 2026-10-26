<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Services\MembershipNumberService;
use Tests\TestCase;

class MembershipNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suggests_and_allocates_sequential_numbers(): void
    {
        $organization = $this->organizationWithNumbering('SD', 'A', 1);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SDA-0001', $service->suggest($organization));
        $this->assertSame('SDA-0001', $service->allocate($organization));
        $this->assertSame('SDA-0002', $service->suggest($organization->fresh()));
        $this->assertSame('SDA-0002', $service->allocate($organization->fresh()));
    }

    public function test_it_rolls_over_from_series_a_to_series_b_after_9999(): void
    {
        $organization = $this->organizationWithNumbering('SD', 'A', 9999);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SDA-9999', $service->suggest($organization));
        $this->assertSame('SDA-9999', $service->allocate($organization));
        $this->assertSame('SDB-0001', $service->suggest($organization->fresh()));
        $this->assertSame('SDB-0001', $service->allocate($organization->fresh()));
    }

    public function test_manual_number_in_the_configured_series_advances_the_counter(): void
    {
        $organization = $this->organizationWithNumbering('SD', 'A', 1);
        $service = app(MembershipNumberService::class);

        $service->observeManualNumber($organization, 'SDA-0042');

        $this->assertSame('SDA-0043', $service->suggest($organization->fresh()));
    }

    public function test_series_z_exhaustion_is_reported(): void
    {
        $organization = $this->organizationWithNumbering('SD', 'Z', 9999);
        $service = app(MembershipNumberService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->allocate($organization);
    }

    private function organizationWithNumbering(string $prefix, string $series, int $nextSequence): Organization
    {
        return Organization::factory()->create([
            'settings' => [
                'membership_numbering' => [
                    'base_prefix' => $prefix,
                    'series' => $series,
                    'next_sequence' => $nextSequence,
                    'initialized' => true,
                ],
            ],
        ]);
    }
}
