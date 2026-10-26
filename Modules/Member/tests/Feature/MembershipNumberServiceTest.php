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

    public function test_numeric_manual_number_is_formatted_with_the_organization_prefix_and_series(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SAA-0005', $service->normalizeManualNumber($organization, '5'));
        $service->observeManualNumber($organization, 'SAA-0005');

        $this->assertSame('SAA-0006', $service->suggest($organization->fresh()));
    }

    public function test_manual_number_in_the_configured_series_advances_the_counter(): void
    {
        $organization = $this->organizationWithNumbering('SD', 'A', 1);
        $service = app(MembershipNumberService::class);

        $service->observeManualNumber($organization, 'SDA-0042');

        $this->assertSame('SDA-0043', $service->suggest($organization->fresh()));
    }

    public function test_numeric_manual_number_must_be_between_one_and_9999(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        try {
            $service->normalizeManualNumber($organization, '10000');
            $this->fail('Expected an out-of-range membership number to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('membership_number', $exception->errors());
        }
    }

    public function test_formatted_manual_number_must_use_the_organizations_prefix(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->normalizeManualNumber($organization, 'SDA-0005');
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
