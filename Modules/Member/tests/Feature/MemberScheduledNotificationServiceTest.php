<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;
use Modules\Core\Models\Unit;
use Modules\Core\Models\Notification;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\MemberScheduledNotificationService;
use Tests\TestCase;

class MemberScheduledNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_due_reminder_is_generated_from_charge_configuration(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member->update(['unit_id' => $unit->id]);
        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
        $membership = Membership::query()->create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipStatus::ACTIVE,
            'starts_at' => Carbon::parse('2026-01-15'),
            'ends_at' => null,
            'metadata' => [],
        ]);
        $definition = MemberPaymentItem::query()->create([
            'organization_id' => $organization->id,
            'membership_type_id' => $membership->membership_type_id,
            'title' => 'Monthly Subscription',
            'slug' => 'subscription-' . uniqid(),
            'is_recurring' => true,
            'recurring_period' => 'monthly',
            'recurring_by' => 'join_date',
            'is_mandatory' => true,
            'is_active' => true,
            'reminder_days_before_due' => 7,
            'reminder_days_after_due' => null,
            'metadata' => [],
        ]);
        $rate = MemberPaymentItemRate::query()->create([
            'member_payment_item_id' => $definition->id,
            'amount' => 50,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $rate->allocations()->create(['scope_type' => 'unit', 'percentage' => 100]);

        $service = app(MemberScheduledNotificationService::class);
        $service->sendSubscriptionReminders(Carbon::parse('2026-10-08'));

        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'member.payment.due',
        ]);
    }

    public function test_subscription_overdue_reminder_is_generated_after_configured_days(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member->update(['unit_id' => $unit->id]);
        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
        $membership = Membership::query()->create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipStatus::ACTIVE,
            'starts_at' => Carbon::parse('2026-01-15'),
            'ends_at' => null,
            'metadata' => [],
        ]);
        $definition = MemberPaymentItem::query()->create([
            'organization_id' => $organization->id,
            'membership_type_id' => $membership->membership_type_id,
            'title' => 'Monthly Subscription',
            'slug' => 'subscription-' . uniqid(),
            'is_recurring' => true,
            'recurring_period' => 'monthly',
            'recurring_by' => 'join_date',
            'is_mandatory' => true,
            'is_active' => true,
            'reminder_days_before_due' => null,
            'reminder_days_after_due' => 3,
            'metadata' => [],
        ]);
        $rate = MemberPaymentItemRate::query()->create([
            'member_payment_item_id' => $definition->id,
            'amount' => 50,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $rate->allocations()->create(['scope_type' => 'unit', 'percentage' => 100]);

        $service = app(MemberScheduledNotificationService::class);
        $service->sendSubscriptionReminders(Carbon::parse('2026-01-18'));

        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'member.payment.overdue',
        ]);
    }

    public function test_subscription_overdue_reminder_is_not_duplicated_on_repeated_runs(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member->update(['unit_id' => $unit->id]);
        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
        $membership = Membership::query()->create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipStatus::ACTIVE,
            'starts_at' => Carbon::parse('2026-01-15'),
            'ends_at' => null,
            'metadata' => [],
        ]);
        $definition = MemberPaymentItem::query()->create([
            'organization_id' => $organization->id,
            'membership_type_id' => $membership->membership_type_id,
            'title' => 'Monthly Subscription',
            'slug' => 'subscription-' . uniqid(),
            'is_recurring' => true,
            'recurring_period' => 'monthly',
            'recurring_by' => 'join_date',
            'is_mandatory' => true,
            'is_active' => true,
            'reminder_days_before_due' => null,
            'reminder_days_after_due' => 3,
            'metadata' => [],
        ]);
        $rate = MemberPaymentItemRate::query()->create([
            'member_payment_item_id' => $definition->id,
            'amount' => 50,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $rate->allocations()->create(['scope_type' => 'unit', 'percentage' => 100]);

        $service = app(MemberScheduledNotificationService::class);
        $today = Carbon::parse('2026-01-18');
        $service->sendSubscriptionReminders($today);
        $service->sendSubscriptionReminders($today);

        $this->assertSame(1, Notification::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('event', 'member.payment.overdue')
            ->count());
    }

    public function test_identification_expiry_uses_organization_specific_configuration(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member->update(['unit_id' => $unit->id]);
        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
        Membership::query()->create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipStatus::ACTIVE,
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => null,
            'metadata' => [],
        ]);
        $identificationType = IdentificationType::query()->create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);
        OrganizationIdentification::query()->create([
            'organization_id' => $organization->id,
            'identification_type_id' => $identificationType->id,
            'is_enabled' => true,
            'is_required' => false,
            'requires_document' => false,
            'has_expiry' => true,
            'expiry_reminder_days' => [30],
        ]);
        UserIdentification::query()->create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'P123456',
            'expires_at' => '2026-11-07',
        ]);

        $service = app(MemberScheduledNotificationService::class);
        $service->sendIdentificationExpiryReminders(Carbon::parse('2026-10-08'));

        $notification = Notification::query()->where('event', 'member.identification.expiry')->first();
        $this->assertNotNull($notification);
        $this->assertSame('passport', $notification->data['identification_type_code']);
    }
}
