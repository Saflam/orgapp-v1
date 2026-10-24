<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Services\MembershipApplicationWorkflowService;
use Tests\TestCase;

class MembershipApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_every_accepted_transition(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);

        $service->transition($application, MembershipApplicationStatus::VERIFIED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::REVIEWED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::APPROVED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::PENDING_PAYMENT, $actor);

        $this->assertSame(MembershipApplicationStatus::PENDING_PAYMENT, $application->refresh()->status);
        $this->assertDatabaseCount('membership_application_status_histories', 4);
    }

    public function test_preparing_payment_invoice_does_not_record_payment(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $workflow = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $obligations = app(\Modules\Member\Services\MemberPaymentService::class)
            ->prepareApplicationInvoice($application->refresh(), $actor);

        $this->assertCount(1, $obligations);
        $this->assertDatabaseCount('member_payment_obligations', 1);
        $this->assertSame(MembershipApplicationStatus::PENDING_PAYMENT, $application->refresh()->status);
        $this->assertDatabaseCount('member_payments', 0);
        $this->assertDatabaseHas('member_payment_obligations', [
            'membership_application_id' => $application->id,
            'item_title' => 'Registration Fee',
            'amount' => '1000.00',
        ]);
    }

    public function test_initial_invoice_includes_all_mandatory_one_time_and_recurring_items_but_excludes_optional_items(): void
    {
        [$application, $actor] = $this->applicationContext();
        $typeId = $application->membership_type_id;

        $otherMembershipType = MembershipType::create([
            'organization_id' => $application->organization_id,
            'name' => 'Associate',
            'code' => 'ASSOCIATE-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->createPaymentItem($application, $typeId, 'Membership Fee', 'membership-fee', true, false, null, null, null, 'individual');
        // Application invoice rules use all mandatory organization payment items,
        // even if a configured item is linked to a different membership type.
        $this->createPaymentItem($application, $otherMembershipType->id, 'Subscription Fee', 'subscription-fee', true, true, 'yearly', 'specific_date', '12-31', 'individual');
        $this->createPaymentItem($application, $typeId, 'Optional Donation', 'optional-donation', false, true, 'yearly', 'specific_date', '12-31', 'individual');

        $workflow = app(MembershipApplicationWorkflowService::class);
        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $invoice = app(\Modules\Member\Services\MemberPaymentService::class)
            ->prepareApplicationInvoice($application->refresh(), $actor);

        $this->assertEqualsCanonicalizing(
            ['Membership Fee', 'Subscription Fee'],
            $invoice->pluck('item_title')->all(),
        );
        $this->assertTrue($invoice->firstWhere('item_title', 'Subscription Fee')->due_at->isFuture());
        $this->assertDatabaseMissing('member_payment_obligations', [
            'membership_application_id' => $application->id,
            'item_title' => 'Optional Donation',
        ]);
    }

    public function test_reopening_invoice_reconciles_snapshots_when_payment_categories_are_changed(): void
    {
        [$application, $actor] = $this->applicationContext();
        $item = $this->createPaymentItem(
            $application,
            $application->membership_type_id,
            'Membership Fee',
            'membership-fee',
            true,
            false,
            null,
            null,
            null,
            'individual',
        );
        $workflow = app(MembershipApplicationWorkflowService::class);
        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $payments = app(\Modules\Member\Services\MemberPaymentService::class);
        $firstInvoice = $payments->prepareApplicationInvoice($application->refresh(), $actor);
        $this->assertCount(1, $firstInvoice);
        $this->assertSame('Membership Fee', $firstInvoice->first()->item_title);

        // Updating the category must update the pending invoice snapshot immediately.
        app(\Modules\Member\Services\MemberPaymentItemConfigurationService::class)->update(
            Organization::query()->findOrFail($application->organization_id),
            $item,
            ['title' => 'Updated Membership Fee', 'is_active' => true, 'is_mandatory' => true],
        );
        $updatedInvoice = $payments->prepareApplicationInvoice($application->refresh(), $actor);
        $this->assertSame('Updated Membership Fee', $updatedInvoice->first()->item_title);

        // A newly created active mandatory category must be added to existing invoices.
        app(\Modules\Member\Services\MemberPaymentItemConfigurationService::class)->create(
            Organization::query()->findOrFail($application->organization_id),
            [
                'membership_type_id' => null,
                'title' => 'Subscription Fee',
                'slug' => 'subscription-fee-' . uniqid(),
                'description' => null,
                'family_payment_policy' => 'individual',
                'is_mandatory' => true,
                'is_recurring' => true,
                'recurring_period' => 'yearly',
                'recurring_by' => 'specific_date',
                'recurring_date' => '12-31',
                'is_active' => true,
                'rate' => [
                    'amount' => 250,
                    'currency' => 'INR',
                    'effective_from' => '2020-01-01',
                    'allocations' => [['scope_type' => 'organization', 'percentage' => 100]],
                ],
            ],
        );
        $afterCreate = $payments->prepareApplicationInvoice($application->refresh(), $actor);
        $this->assertEqualsCanonicalizing(
            ['Updated Membership Fee', 'Subscription Fee'],
            $afterCreate->pluck('item_title')->all(),
        );

        // Deactivating every category removes unpaid snapshots from the invoice,
        // while retaining cancelled rows for audit history.
        app(\Modules\Member\Services\MemberPaymentItemConfigurationService::class)->update(
            Organization::query()->findOrFail($application->organization_id),
            $item->refresh(),
            ['is_active' => false],
        );
        $subscription = MemberPaymentItem::query()
            ->where('organization_id', $application->organization_id)
            ->where('title', 'Subscription Fee')
            ->firstOrFail();
        app(\Modules\Member\Services\MemberPaymentItemConfigurationService::class)->update(
            Organization::query()->findOrFail($application->organization_id),
            $subscription,
            ['is_active' => false],
        );

        $emptyInvoice = $payments->prepareApplicationInvoice($application->refresh(), $actor);
        $this->assertCount(0, $emptyInvoice);
        $this->assertDatabaseHas('member_payment_obligations', [
            'membership_application_id' => $application->id,
            'member_payment_item_id' => $item->id,
            'status' => MemberPaymentObligationStatus::CANCELLED->value,
        ]);
    }

    public function test_invoice_query_excludes_stale_snapshots_when_category_is_deactivated_or_made_optional(): void
    {
        [$application, $actor] = $this->applicationContext();
        $inactiveItem = $this->createPaymentItem(
            $application, $application->membership_type_id, 'Inactive Fee', 'inactive-fee',
            true, false, null, null, null, 'individual',
        );
        $optionalItem = $this->createPaymentItem(
            $application, $application->membership_type_id, 'Optional Fee', 'optional-fee',
            true, false, null, null, null, 'individual',
        );

        $workflow = app(MembershipApplicationWorkflowService::class);
        foreach ([MembershipApplicationStatus::VERIFIED, MembershipApplicationStatus::REVIEWED, MembershipApplicationStatus::APPROVED] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $payments = app(\Modules\Member\Services\MemberPaymentService::class);
        $initialInvoice = $payments->prepareApplicationInvoice($application->refresh(), $actor);
        $this->assertEqualsCanonicalizing(['Inactive Fee', 'Optional Fee'], $initialInvoice->pluck('item_title')->all());

        // Simulate configuration changing while the invoice modal is already open.
        // The query must not trust stale obligation snapshots for display or totals.
        $inactiveItem->update(['is_active' => false]);
        $optionalItem->update(['is_mandatory' => false]);

        $currentInvoice = $payments->pendingApplicationObligations($application->refresh());
        $this->assertCount(0, $currentInvoice);
    }

    public function test_mutual_spouse_item_is_excluded_from_initial_invoice_when_linked_spouse_has_paid_same_occurrence(): void
    {
        [$application, $actor] = $this->applicationContext();
        $organization = Organization::query()->findOrFail($application->organization_id);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $spouseUser = User::factory()->create();
        $spouse = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $spouseUser->id,
            'unit_id' => $unit->id,
            'membership_number' => 'SPOUSE-' . uniqid(),
            'joined_at' => now()->toDateString(),
            'metadata' => [],
        ]);

        $item = $this->createPaymentItem(
            $application,
            $application->membership_type_id,
            'Welfare Fund',
            'welfare-fund',
            true,
            true,
            'yearly',
            'specific_date',
            '12-31',
            'mutual_spouse',
        );
        $dueDate = now()->copy()->startOfDay();
        [$month, $day] = array_map('intval', explode('-', $item->recurring_date));
        $dueDate = \Carbon\Carbon::create(now()->year, $month, min($day, \Carbon\Carbon::create(now()->year, $month, 1)->daysInMonth));
        if ($dueDate->lt(now()->startOfDay())) {
            $dueDate->addYearNoOverflow();
        }
        $occurrenceKey = 'yearly:' . $dueDate->format('Y-m-d');

        MemberPaymentObligation::create([
            'organization_id' => $organization->id,
            'member_id' => $spouse->id,
            'member_payment_item_id' => $item->id,
            'member_payment_item_rate_id' => $item->rates()->firstOrFail()->id,
            'item_title' => $item->title,
            'occurrence_key' => $occurrenceKey,
            'amount' => 500,
            'discount_amount' => 0,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'currency' => 'INR',
            'status' => MemberPaymentObligationStatus::PAID,
            'due_at' => $dueDate->toDateString(),
            'paid_at' => now(),
            'metadata' => [],
        ]);

        $application->update([
            'data' => array_replace_recursive($application->data, [
                'spouse' => ['existing' => 'yes', 'member_id' => $spouse->id],
            ]),
        ]);

        $workflow = app(MembershipApplicationWorkflowService::class);
        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $invoice = app(\Modules\Member\Services\MemberPaymentService::class)
            ->prepareApplicationInvoice($application->refresh(), $actor);

        $this->assertFalse($invoice->contains(fn ($obligation) => $obligation->item_title === 'Welfare Fund'));
        $this->assertDatabaseHas('member_payment_obligations', [
            'membership_application_id' => $application->id,
            'member_payment_item_id' => $item->id,
            'occurrence_key' => $occurrenceKey,
            'status' => MemberPaymentObligationStatus::COVERED_BY_FAMILY->value,
        ]);
    }

    public function test_application_payment_applies_discount_and_records_collection_notes_atomically(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $workflow = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        $payments = app(\Modules\Member\Services\MemberPaymentService::class);
        $obligation = $payments->prepareApplicationInvoice($application->refresh(), $actor)->firstOrFail();
        $paidApplication = $payments->markApplicationPaid(
            application: $application->refresh(),
            actor: $actor,
            scopeType: 'organization',
            scopeId: $application->organization_id,
            paymentMethod: 'bank_transfer',
            reference: 'BANK-REF-001',
            discounts: [$obligation->id => ['amount' => 100, 'reason' => 'Approved concession']],
            collectionNotes: 'Verified against bank statement',
        );

        $this->assertSame(MembershipApplicationStatus::PENDING_CONFIRMATION, $paidApplication->status);
        $this->assertDatabaseHas('member_payment_obligation_discounts', [
            'member_payment_obligation_id' => $obligation->id,
            'discount_amount' => '100.00',
            'reason' => 'Approved concession',
            'actor_user_id' => $actor->id,
        ]);
        $this->assertDatabaseHas('member_payments', [
            'membership_application_id' => $application->id,
            'total_amount' => '900.00',
            'payment_method' => 'bank_transfer',
            'reference' => 'BANK-REF-001',
        ]);
        $payment = \Modules\Member\Models\MemberPayment::query()->where('membership_application_id', $application->id)->firstOrFail();
        $this->assertSame('Verified against bank statement', data_get($payment->metadata, 'collection_notes'));
    }

    public function test_rejection_requires_reason_and_can_allow_reapplication(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);

        $this->expectException(ValidationException::class);

        $service->reject(
            application: $application,
            actor: $actor,
            notes: '',
            allowReapply: true,
        );
    }

    public function test_invalid_transition_is_rejected(): void
    {
        [$application, $actor] = $this->applicationContext();

        $this->expectException(ValidationException::class);

        app(MembershipApplicationWorkflowService::class)->transition(
            application: $application,
            to: MembershipApplicationStatus::APPROVED,
            actor: $actor,
        );
    }

    public function test_confirmation_rejects_a_primary_phone_already_linked_to_another_user(): void
    {
        [$application, $actor] = $this->applicationContext();

        User::factory()->create([
            'phone' => '+919876543210',
        ]);

        $application->update([
            'data' => array_replace_recursive($application->data, [
                'member' => [
                    'primary_phone' => '9876543210',
                    'primary_phone_country_iso2' => 'IN',
                ],
            ]),
        ]);

        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $service->transition($application->refresh(), $status, $actor);
        }

        app(\Modules\Member\Services\MemberPaymentService::class)
            ->markApplicationPaid($application->refresh(), $actor, 'organization', $application->organization_id);

        try {
            $service->confirm(
                application: $application->refresh(),
                actor: $actor,
                membershipNumber: 'MEM-DUPLICATE-PHONE',
                startsAt: '2026-10-05',
            );

            $this->fail('Confirmation must reject a phone number already assigned to another user.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_phone', $exception->errors());
        }

        $this->assertSame(
            MembershipApplicationStatus::PENDING_CONFIRMATION,
            $application->refresh()->status,
        );
        $this->assertNull($actor->refresh()->phone);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_confirmation_creates_member_and_active_membership(): void
    {
        [$application, $actor] = $this->applicationContext();
        $application->update([
            'data' => array_replace_recursive($application->data, [
                'member' => [
                    'first_name' => 'Asha',
                    'middle_name' => 'M',
                    'last_name' => 'Nair',
                    'primary_phone' => '9876501234',
                    'primary_phone_country_iso2' => 'IN',
                    'preferred_unit_id' => Unit::factory()->create(['organization_id' => $application->organization_id])->id,
                    'gender' => 'female',
                    'blood_group' => 'O+',
                    'phone_numbers' => [[
                        'type' => 'whatsapp',
                        'phone' => '9876543210',
                        'country_iso2' => 'IN',
                    ]],
                    'emergency_contacts' => [[
                        'contact_name' => 'Emergency Person',
                        'relationship' => 'Sibling',
                        'phone' => '+971501234567',
                        'country_iso2' => 'AE',
                    ]],
                ],
            ]),
        ]);
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $service->transition($application->refresh(), $status, $actor);
        }

        app(\Modules\Member\Services\MemberPaymentService::class)
            ->markApplicationPaid($application->refresh(), $actor, 'organization', $application->organization_id);

        $confirmed = $service->confirm(
            application: $application->refresh(),
            actor: $actor,
            membershipNumber: 'MEM-TEST-001',
            startsAt: '2026-10-05',
        );

        $this->assertSame(MembershipApplicationStatus::CONFIRMED, $confirmed->status);

        $member = \Modules\Member\Models\Member::query()->firstOrFail();
        $this->assertSame(
            now()->addYear()->toDateString(),
            $member->memberships()->latest('id')->firstOrFail()->ends_at->toDateString(),
        );
        $this->assertSame('MEM-TEST-001', $member->membership_number);
        $this->assertSame($member->id, $confirmed->member_id);
        $this->assertNotNull($member->unit_id);
        $this->assertSame('Asha M Nair', $actor->refresh()->name);
        $this->assertSame('+919876501234', $actor->refresh()->phone);
        $this->assertDatabaseHas('user_details', [
            'user_id' => $actor->id,
            'first_name' => 'Asha',
            'middle_name' => 'M',
            'last_name' => 'Nair',
            'gender' => 'female',
            'blood_group' => 'O+',
        ]);
        $this->assertNull($confirmed->data);
        $this->assertNull($confirmed->completed_steps);
        $this->assertSame(1, $confirmed->current_step);
        $this->assertSame(0, $confirmed->changes()->count());
        $this->assertDatabaseHas('membership_application_status_histories', [
            'membership_application_id' => $confirmed->id,
            'to_status' => MembershipApplicationStatus::CONFIRMED->value,
            'actor_user_id' => $actor->id,
        ]);
        $this->assertDatabaseHas('memberships', [
            'member_id' => $member->id,
            'status' => MembershipStatus::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('user_phone_numbers', [
            'user_id' => $actor->id,
            'type' => 'whatsapp',
            'phone_e164' => '+919876543210',
            'country_iso2' => 'IN',
        ]);
        $this->assertDatabaseHas('user_emergency_contacts', [
            'user_id' => $actor->id,
            'contact_name' => 'Emergency Person',
            'phone_e164' => '+971501234567',
            'country_iso2' => 'AE',
        ]);
    }

    public function test_confirmation_automatically_assigns_a_prefixed_membership_number_when_omitted(): void
    {
        [$application, $actor] = $this->applicationContext();
        $organization = Organization::query()->findOrFail($application->organization_id);
        $organization->update(['settings' => [
            'membership_numbering' => [
                'base_prefix' => 'SD',
                'series' => 'A',
                'next_sequence' => 1,
                'initialized' => true,
            ],
        ]]);

        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);
        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $service->transition($application->refresh(), $status, $actor);
        }
        app(\Modules\Member\Services\MemberPaymentService::class)
            ->markApplicationPaid($application->refresh(), $actor, 'organization', $application->organization_id);

        $confirmed = $service->confirm(
            application: $application->refresh(),
            actor: $actor,
            membershipNumber: null,
            startsAt: '2026-10-05',
        );

        $this->assertSame('SDA-0001', $confirmed->member->membership_number);
        $this->assertSame(2, Organization::query()->findOrFail($organization->id)->settings['membership_numbering']['next_sequence']);
    }

    public function test_confirmation_failure_rolls_back_all_database_changes_and_can_be_retried(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $workflow = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        app(\Modules\Member\Services\MemberPaymentService::class)
            ->markApplicationPaid(
                $application->refresh(),
                $actor,
                'organization',
                $application->organization_id,
            );

        $this->assertSame(
            MembershipApplicationStatus::PENDING_CONFIRMATION,
            $application->refresh()->status,
        );

        $failingLifecycle = \Mockery::mock(\Modules\Member\Services\MembershipLifecycleService::class);
        $failingLifecycle->shouldReceive('activate')
            ->once()
            ->andThrow(new \RuntimeException('Simulated activation failure.'));

        $this->app->instance(\Modules\Member\Services\MembershipLifecycleService::class, $failingLifecycle);
        $this->app->forgetInstance(MembershipApplicationWorkflowService::class);

        try {
            app(MembershipApplicationWorkflowService::class)->confirm(
                application: $application->refresh(),
                actor: $actor,
                membershipNumber: null,
                startsAt: '2026-10-05',
            );
            $this->fail('Confirmation should fail when membership activation throws.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated activation failure.', $exception->getMessage());
        }

        $application->refresh();

        $this->assertSame(MembershipApplicationStatus::PENDING_CONFIRMATION, $application->status);
        $this->assertNull($application->member_id);
        $this->assertSame(0, Member::query()->where('organization_id', $application->organization_id)->count());
        $this->assertDatabaseCount('memberships', 0);
        $this->assertSame(
            0,
            $application->statusHistories()
                ->where('to_status', MembershipApplicationStatus::CONFIRMED->value)
                ->count(),
        );
        $this->assertDatabaseHas('member_payment_obligations', [
            'membership_application_id' => $application->id,
            'member_id' => null,
            'membership_id' => null,
        ]);

        // Remove the injected failure and verify the same application can be
        // confirmed successfully after the rolled-back attempt.
        $this->app->forgetInstance(\Modules\Member\Services\MembershipLifecycleService::class);
        $this->app->forgetInstance(MembershipApplicationWorkflowService::class);

        $confirmed = app(MembershipApplicationWorkflowService::class)->confirm(
            application: $application->refresh(),
            actor: $actor,
            membershipNumber: null,
            startsAt: '2026-10-05',
        );

        $this->assertSame(MembershipApplicationStatus::CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->member_id);
        $this->assertSame(1, Member::query()->where('organization_id', $application->organization_id)->count());
        $this->assertSame(
            1,
            $application->statusHistories()
                ->where('to_status', MembershipApplicationStatus::CONFIRMED->value)
                ->count(),
        );
    }

    public function test_confirmation_cannot_create_a_second_member_for_the_same_application(): void
    {
        [$application, $actor] = $this->applicationContext();
        $this->configureMandatoryCharge($application, $application->membership_type_id);
        $service = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $service->transition($application->refresh(), $status, $actor);
        }

        app(\Modules\Member\Services\MemberPaymentService::class)
            ->markApplicationPaid($application->refresh(), $actor, 'organization', $application->organization_id);

        $service->confirm(
            application: $application->refresh(),
            actor: $actor,
            membershipNumber: null,
            startsAt: '2026-10-05',
        );

        $this->assertSame(MembershipApplicationStatus::CONFIRMED, $application->refresh()->status);
        $this->assertSame(1, Member::query()->where('organization_id', $application->organization_id)->count());

        try {
            // Pass the now-stale application model deliberately. The service must
            // reload and lock the current row before deciding whether to confirm.
            $service->confirm(
                application: $application,
                actor: $actor,
                membershipNumber: null,
                startsAt: '2026-10-05',
            );
            $this->fail('A confirmed application must not be confirmed a second time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(1, Member::query()->where('organization_id', $application->organization_id)->count());
        $this->assertSame(1, $application->statusHistories()->where('to_status', MembershipApplicationStatus::CONFIRMED->value)->count());
    }


    public function test_spouse_relationship_helper_reactivates_existing_rows_without_duplicate_inserts(): void
    {
        [$application] = $this->applicationContext();
        $organization = Organization::query()->findOrFail($application->organization_id);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);

        $first = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $application->user_id,
            'unit_id' => $unit->id,
            'membership_number' => 'MEM-A-' . uniqid(),
            'joined_at' => '2026-01-01',
            'metadata' => [],
        ]);
        $second = Member::create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id,
            'membership_number' => 'MEM-B-' . uniqid(),
            'joined_at' => '2026-01-01',
            'metadata' => [],
        ]);

        foreach ([[$first->id, $second->id], [$second->id, $first->id]] as [$memberId, $relatedMemberId]) {
            \Modules\Member\Models\MemberRelationship::create([
                'member_id' => $memberId,
                'related_member_id' => $relatedMemberId,
                'relationship_type' => 'spouse',
                'started_at' => '2025-01-01',
                'ended_at' => '2025-12-31',
                'metadata' => ['source' => 'test'],
            ]);
        }

        $workflow = app(MembershipApplicationWorkflowService::class);
        $method = new \ReflectionMethod($workflow, 'ensureActiveSpouseRelationship');
        $method->invoke($workflow, $first->id, $second->id);
        $method->invoke($workflow, $second->id, $first->id);

        $this->assertDatabaseCount('member_relationships', 2);
        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $first->id,
            'related_member_id' => $second->id,
            'relationship_type' => 'spouse',
            'ended_at' => null,
        ]);
        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $second->id,
            'related_member_id' => $first->id,
            'relationship_type' => 'spouse',
            'ended_at' => null,
        ]);
    }

    private function createPaymentItem(
        MembershipApplication $application,
        int $membershipTypeId,
        string $title,
        string $slug,
        bool $mandatory,
        bool $recurring,
        ?string $period,
        ?string $recurringBy,
        ?string $recurringDate,
        string $familyPolicy,
    ): MemberPaymentItem {
        $item = MemberPaymentItem::create([
            'organization_id' => $application->organization_id,
            'membership_type_id' => $membershipTypeId,
            'title' => $title,
            'slug' => $slug . '-' . uniqid(),
            'family_payment_policy' => $familyPolicy,
            'is_mandatory' => $mandatory,
            'is_recurring' => $recurring,
            'recurring_period' => $period,
            'recurring_by' => $recurringBy,
            'recurring_date' => $recurringDate,
            'is_active' => true,
            'metadata' => [],
        ]);
        $rate = MemberPaymentItemRate::create([
            'member_payment_item_id' => $item->id,
            'amount' => 500,
            'currency' => 'INR',
            'effective_from' => '2020-01-01',
        ]);
        $rate->allocations()->create(['scope_type' => 'organization', 'percentage' => 100]);

        return $item;
    }

    private function configureMandatoryCharge(MembershipApplication $application, int $membershipTypeId): void
    {
        $definition = MemberPaymentItem::create([
            'organization_id' => $application->organization_id,
            'membership_type_id' => $membershipTypeId,
            'title' => 'Registration Fee',
            'slug' => 'registration-' . uniqid(),
            'is_recurring' => false,
            'is_mandatory' => true,
            'is_active' => true,
            'metadata' => [],
        ]);
        $rate = MemberPaymentItemRate::create([
            'member_payment_item_id' => $definition->id,
            'amount' => 1000,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
        ]);
        $rate->allocations()->create(['scope_type' => 'organization', 'percentage' => 100]);
    }

    private function applicationContext(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular',
            'code' => 'REGULAR-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $application = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 3,
            'completed_steps' => [1, 2, 3],
            'data' => [
                'member' => [
                    'first_name' => 'Test',
                    'blood_group' => 'O+',
                ],
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                ],
                'extension_data' => [],
                'identifications' => [],
            ],
            'submitted_at' => now(),
        ]);

        return [$application, $actor];
    }
}
