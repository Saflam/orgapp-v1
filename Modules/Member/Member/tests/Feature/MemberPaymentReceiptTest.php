<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Core\Models\Organization;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Services\MemberPaymentReceiptService;
use Modules\Member\Services\MemberPaymentReceiptNotificationService;
use Tests\TestCase;

class MemberPaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_receipt_service_is_idempotent_and_does_not_create_notification(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'receipt_number' => 'MP-TEST-001',
            'total_amount' => 150,
            'currency' => 'INR',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => now(),
            'received_by' => $user->id,
        ]);

        $service = app(MemberPaymentReceiptService::class);

        $first = $service->issueFallbackReceipt($payment);
        $second = $service->issueFallbackReceipt($payment);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_fallback_receipt_notification_is_idempotent_for_same_member_payment(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'receipt_number' => 'MP-TEST-002',
            'total_amount' => 150,
            'currency' => 'INR',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => now(),
            'received_by' => $user->id,
        ]);

        $service = app(MemberPaymentReceiptNotificationService::class);

        $first = $service->send($payment);
        $second = $service->send($payment);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'member.payment.receipt.issued',
            'source_module' => 'Member',
        ]);
    }

    public function test_membership_payment_event_creates_one_fallback_receipt_and_one_notification(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'receipt_number' => 'MP-TEST-003',
            'total_amount' => 150,
            'currency' => 'INR',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => now(),
            'received_by' => $user->id,
        ]);

        Event::dispatch(new MembershipPaymentReceived($payment));
        Event::dispatch(new MembershipPaymentReceived($payment));

        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('payment_receipts', [
            'source_type' => 'member_payment',
            'source_id' => $payment->id,
            'receipt_number' => 'MP-TEST-003',
        ]);
    }
}
