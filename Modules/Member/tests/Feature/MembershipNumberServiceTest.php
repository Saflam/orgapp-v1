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

    public function test_it_continues_the_same_series_after_9999(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 9999);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SAA-9999', $service->suggest($organization));
        $this->assertSame('SAA-9999', $service->allocate($organization));
        $this->assertSame('SAA-10,000', $service->suggest($organization->fresh()));
        $this->assertSame('SAA-10,000', $service->allocate($organization->fresh()));
        $this->assertSame('SAA-10,001', $service->suggest($organization->fresh()));
    }

    public function test_numeric_manual_number_is_formatted_with_the_organization_prefix_and_series(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SAA-0005', $service->normalizeManualNumber($organization, '5'));
        $this->assertSame('SAA-10,000', $service->normalizeManualNumber($organization, '10000'));
        $this->assertSame('SAA-10,000', $service->normalizeManualNumber($organization, '10,000'));
        $service->observeManualNumber($organization, 'SAA-0005');

        $this->assertSame('SAA-0006', $service->suggest($organization->fresh()));
    }

    public function test_manual_number_in_the_configured_series_advances_the_counter(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        $service->observeManualNumber($organization, 'SAA-10,000');

        $this->assertSame('SAA-10,001', $service->suggest($organization->fresh()));
    }

    public function test_formatted_manual_number_must_use_the_organizations_prefix_and_series(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        $service = app(MembershipNumberService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->normalizeManualNumber($organization, 'SAB-0005');
    }

    public function test_it_skips_an_occupied_imported_number_when_suggesting(): void
    {
        $organization = $this->organizationWithNumbering('SA', 'A', 1);
        Member::factory()->create([
            'organization_id' => $organization->id,
            'membership_number' => 'SAA-0001',
        ]);
        $service = app(MembershipNumberService::class);

        $this->assertSame('SAA-0002', $service->suggest($organization));
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
