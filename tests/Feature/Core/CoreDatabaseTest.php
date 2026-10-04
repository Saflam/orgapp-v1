<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Tests\TestCase;

class CoreDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_and_unit_can_be_persisted(): void
    {
        $organization = Organization::create([
            'name' => 'Saradhi Demo Organization',
            'code' => 'DEMO',
            'slug' => 'demo',
        ]);

        $unit = Unit::create([
            'organization_id' => $organization->id,
            'name' => 'Kochi Unit',
            'code' => 'KOCHI',
        ]);

        $this->assertSame($organization->id, $unit->organization->id);
        $this->assertSame($unit->id, $organization->units()->first()->id);
    }

    public function test_unit_cannot_reference_a_parent_from_another_organization(): void
    {
        $organizationA = Organization::create([
            'name' => 'Organization A',
            'code' => 'ORGA',
            'slug' => 'organization-a',
        ]);

        $organizationB = Organization::create([
            'name' => 'Organization B',
            'code' => 'ORGB',
            'slug' => 'organization-b',
        ]);

        $parentUnit = Unit::create([
            'organization_id' => $organizationB->id,
            'name' => 'Organization B Parent',
            'code' => 'PARENT',
        ]);

        $this->expectException(QueryException::class);

        Unit::create([
            'organization_id' => $organizationA->id,
            'parent_id' => $parentUnit->id,
            'name' => 'Invalid Child',
            'code' => 'CHILD',
        ]);
    }
}
