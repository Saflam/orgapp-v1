<?php

namespace Modules\Member\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberUnitHistoryService;
use Tests\TestCase;

class MemberUnitHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creation_creates_initial_unit_history(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($unit)->create([
            'organization_id' => $organization->id,
            'joined_at' => '2026-01-15',
        ]);

        $history = $member->unitHistories()->first();

        $this->assertNotNull($history);
        $this->assertSame($unit->id, $history->unit_id);
        $this->assertSame('2026-01-15 00:00:00', $history->started_at->format('Y-m-d H:i:s'));
        $this->assertNull($history->ended_at);
        $this->assertSame('initial', $history->source);
    }

    public function test_unit_at_returns_the_unit_that_was_current_at_the_requested_time(): void
    {
        $organization = Organization::factory()->create();
        $unitA = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($unitA)->create([
            'organization_id' => $organization->id,
            'joined_at' => '2026-01-01',
        ]);
        $service = app(MemberUnitHistoryService::class);

        $initial = $service->ensureInitial($member);
        $service->changeUnit(
            member: $member,
            newUnitId: $unitB->id,
            changedAt: Carbon::parse('2026-06-01 10:00:00'),
            source: 'transfer',
            reason: 'Unit transfer completed.',
        );
        $member->forceFill(['unit_id' => $unitB->id])->save();

        $this->assertSame($unitA->id, $member->unitAt(Carbon::parse('2026-05-31 23:59:59'))?->id);
        $this->assertSame($unitB->id, $member->unitAt(Carbon::parse('2026-06-01 10:00:00'))?->id);
        $this->assertSame($unitB->id, $member->unitAt(Carbon::parse('2026-07-01 00:00:00'))?->id);
        $this->assertSame($unitA->id, $initial?->unit_id);
    }

    public function test_unit_history_closes_previous_unit_when_member_changes_unit(): void
    {
        $organization = Organization::factory()->create();
        $unitA = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($unitA)->create([
            'organization_id' => $organization->id,
            'joined_at' => '2026-01-01',
        ]);
        $service = app(MemberUnitHistoryService::class);
        $service->ensureInitial($member, Carbon::parse('2026-01-01 09:00:00'));

        $service->changeUnit(
            member: $member,
            newUnitId: $unitB->id,
            changedAt: Carbon::parse('2026-06-01 10:00:00'),
            source: 'transfer',
        );
        $member->forceFill(['unit_id' => $unitB->id])->save();

        $history = $member->unitHistories()->orderBy('started_at')->get();

        $this->assertCount(2, $history);
        $this->assertSame($unitA->id, $history[0]->unit_id);
        $this->assertSame('2026-06-01 10:00:00', $history[0]->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame($unitB->id, $history[1]->unit_id);
        $this->assertSame('2026-06-01 10:00:00', $history[1]->started_at->format('Y-m-d H:i:s'));
        $this->assertNull($history[1]->ended_at);
    }
}
