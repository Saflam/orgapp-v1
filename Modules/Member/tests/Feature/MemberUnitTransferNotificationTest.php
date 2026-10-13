<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberUnitTransferService;
use Tests\TestCase;

class MemberUnitTransferNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_notifies_users_in_both_units_and_deduplicates_each_recipient(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $from = Unit::factory()->create(['organization_id' => $organization->id, 'name' => 'Unit A']);
        $to = Unit::factory()->create(['organization_id' => $organization->id, 'name' => 'Unit B']);
        $memberUser = User::factory()->create();
        $outgoingUser = User::factory()->create();
        $incomingUser = User::factory()->create();

        $member = Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $memberUser->id,
        ]);
        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingUser->id,
        ]);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingUser->id,
        ]);

        $transfer = app(MemberUnitTransferService::class)->submit($member, $to->id, false);

        foreach ([$memberUser, $outgoingUser, $incomingUser] as $user) {
            $this->assertDatabaseHas('notifications', [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'event' => 'member.unit_transfer.submitted',
            ]);
        }

        $count = Notification::query()
            ->where('event', 'member.unit_transfer.submitted')
            ->where('data->unit_transfer_id', $transfer->id)
            ->count();

        $this->assertSame(3, $count);

        app(\Modules\Member\Services\MemberUnitTransferNotificationService::class)->submitted($transfer->fresh());

        $this->assertSame(3, Notification::query()
            ->where('event', 'member.unit_transfer.submitted')
            ->where('data->unit_transfer_id', $transfer->id)
            ->count());
    }
}
