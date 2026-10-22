<?php
namespace Modules\Member\Tests\Feature;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MemberPaymentService;
use Tests\TestCase;

class MemberPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_specific_date_item_generates_only_when_due_and_not_twice(): void
    {
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $item=$this->item($organization,$membershipType,'service-fee','Service Fee',true,true,'yearly','specific_date','12-31',50,[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>80]]);
        $service=app(MemberPaymentService::class);
        $this->assertCount(0,$service->syncOutstandingObligations($membership,'2026-12-30'));
        $created=$service->syncOutstandingObligations($membership,'2026-12-31');
        $this->assertCount(1,$created);
        $this->assertSame('2026-12-31',$created->first()->due_at->toDateString());
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$created->first()->id,'scope_type'=>'organization','scope_id'=>$organization->id,'percentage'=>'20.0000','original_amount'=>'10.00']);
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$created->first()->id,'scope_type'=>'unit','scope_id'=>$member->unit_id,'percentage'=>'80.0000','original_amount'=>'40.00']);
        $this->assertCount(0,$service->syncOutstandingObligations($membership,'2026-12-31'));
    }

    public function test_pending_member_payment_list_contains_unpaid_mandatory_items_and_excludes_paid_occurrence(): void
    {
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $service=$this->serviceItem($organization,$membershipType,'service-fee','Service Fee',50);
        $other=$this->serviceItem($organization,$membershipType,'welfare-fund','Welfare Fund',25);
        $serviceApp=app(MemberPaymentService::class);
        $serviceApp->syncOutstandingObligations($membership,'2026-01-01');
        $obligations=$serviceApp->pendingMemberObligations($member);
        $this->assertCount(2,$obligations);
        $serviceApp->recordPayment([$obligations->first()],User::factory()->create(),'unit',$member->unit_id);
        $pending=$serviceApp->pendingMemberObligations($member);
        $this->assertCount(1,$pending);
        $this->assertSame($other->id,$pending->first()->member_payment_item_id);
    }

    public function test_one_payment_can_settle_multiple_items_and_snapshots_central_unit_split(): void
    {
        Event::fake();
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $serviceItem=$this->item($organization,$membershipType,'service-fee','Service Fee',true,true,'yearly','specific_date','12-31',50,[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>80]]);
        $other=$this->serviceItem($organization,$membershipType,'welfare-fund','Welfare Fund',100);
        $service=app(MemberPaymentService::class);
        $obligations=$service->syncOutstandingObligations($membership,'2026-12-31');
        $this->assertCount(2,$obligations);
        $actor=User::factory()->create();
        $payment=$service->recordPayment($obligations,$actor,'unit',$member->unit_id,'cash','REF-1');
        $this->assertSame('150.00',$payment->total_amount);
        $this->assertSame(2,$payment->lines()->count());
        $this->assertDatabaseHas('member_payments',['id'=>$payment->id,'collector_scope_type'=>'unit','collector_scope_id'=>$member->unit_id,'total_amount'=>'150.00']);
        $serviceLine=$payment->lines()->whereHas('obligation',fn($q)=>$q->where('member_payment_item_id',$serviceItem->id))->firstOrFail();
        $this->assertDatabaseHas('member_payment_line_allocations',['member_payment_line_id'=>$serviceLine->id,'scope_type'=>'organization','scope_id'=>$organization->id,'amount'=>'10.00']);
        $this->assertDatabaseHas('member_payment_line_allocations',['member_payment_line_id'=>$serviceLine->id,'scope_type'=>'unit','scope_id'=>$member->unit_id,'amount'=>'40.00']);
        $this->assertSame(2,$payment->lines()->whereHas('obligation',fn($q)=>$q->where('status',MemberPaymentObligationStatus::PAID))->count());
        Event::assertDispatchedTimes(MembershipPaymentReceived::class,1);
    }

    public function test_discount_reduces_allocation_amounts_pro_rata(): void
    {
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $this->item($organization,$membershipType,'service-fee','Service Fee',true,true,'yearly','specific_date','12-31',50,[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>80]]);
        $service=app(MemberPaymentService::class); $obligation=$service->syncOutstandingObligations($membership,'2026-12-31')->first();
        $service->applyDiscount($obligation,'Approved discount',10,User::factory()->create());
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$obligation->id,'scope_type'=>'organization','payable_amount'=>'8.00']);
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$obligation->id,'scope_type'=>'unit','payable_amount'=>'32.00']);
    }

    public function test_rate_changes_apply_only_to_future_occurrences(): void
    {
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $item=$this->item($organization,$membershipType,'service-fee','Service Fee',true,true,'yearly','specific_date','12-31',50,[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>80]]);
        $newRate=MemberPaymentItemRate::create(['member_payment_item_id'=>$item->id,'amount'=>60,'currency'=>'INR','effective_from'=>'2027-01-01']);
        $newRate->allocations()->create(['scope_type'=>'organization','percentage'=>30]);
        $newRate->allocations()->create(['scope_type'=>'unit','percentage'=>70]);
        $item->rates()->where('id','!=',$newRate->id)->update(['effective_to'=>'2026-12-31']);
        $service=app(MemberPaymentService::class);
        $old=$service->syncOutstandingObligations($membership,'2026-12-31')->first();
        $this->assertSame('50.00',$old->amount);
        $future=$service->syncOutstandingObligations($membership,'2027-12-31')->last();
        $this->assertSame('60.00',$future->amount);
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$old->id,'scope_type'=>'organization','percentage'=>'20.0000','original_amount'=>'10.00']);
        $this->assertDatabaseHas('member_payment_obligation_allocations',['member_payment_obligation_id'=>$future->id,'scope_type'=>'organization','percentage'=>'30.0000','original_amount'=>'18.00']);
    }

    public function test_unit_collector_cannot_collect_for_another_unit(): void
    {
        [$organization,$member,$membershipType,$membership]=$this->memberContext('2026-01-01');
        $otherUnit=Unit::factory()->create(['organization_id'=>$organization->id]);
        $this->serviceItem($organization,$membershipType,'service-fee','Service Fee',50);
        $obligation=app(MemberPaymentService::class)->syncOutstandingObligations($membership,'2026-01-01')->first();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MemberPaymentService::class)->recordPayment([$obligation],User::factory()->create(),'unit',$otherUnit->id);
    }

    private function serviceItem(Organization $organization,MembershipType $type,string $slug,string $title,float $amount): MemberPaymentItem
    { return $this->item($organization,$type,$slug,$title,true,false,null,null,null,$amount,[['scope_type'=>'unit','percentage'=>100]]); }
    private function item(Organization $organization,MembershipType $type,string $slug,string $title,bool $mandatory,bool $recurring,?string $period,?string $by,?string $date,float $amount,array $allocations): MemberPaymentItem
    { $item=MemberPaymentItem::create(['organization_id'=>$organization->id,'membership_type_id'=>$type->id,'title'=>$title,'slug'=>$slug,'is_mandatory'=>$mandatory,'is_recurring'=>$recurring,'recurring_period'=>$period,'recurring_by'=>$by,'recurring_date'=>$date,'is_active'=>true,'metadata'=>[]]); $rate=MemberPaymentItemRate::create(['member_payment_item_id'=>$item->id,'amount'=>$amount,'currency'=>'INR','effective_from'=>'2026-01-01']); foreach($allocations as $a)$rate->allocations()->create($a); return $item; }
    private function memberContext(string $startsAt): array
    { $organization=Organization::factory()->create(['slug'=>'org-'.uniqid()]); $user=User::factory()->create(); OrganizationMembership::create(['organization_id'=>$organization->id,'user_id'=>$user->id,'status'=>'active']); $unit=Unit::factory()->create(['organization_id'=>$organization->id]); $type=MembershipType::create(['organization_id'=>$organization->id,'name'=>'Regular','code'=>'REG-'.uniqid(),'is_active'=>true,'metadata'=>[]]); $member=Member::create(['organization_id'=>$organization->id,'user_id'=>$user->id,'unit_id'=>$unit->id,'membership_number'=>'MEM-'.uniqid(),'joined_at'=>$startsAt,'metadata'=>[]]); $membership=Membership::create(['member_id'=>$member->id,'membership_type_id'=>$type->id,'starts_at'=>$startsAt,'status'=>'active','metadata'=>[]]); return [$organization,$member,$type,$membership]; }
    public function test_daily_generation_creates_join_date_obligation_when_due(): void
    {
        $organization = Organization::factory()->create();
        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $type = MembershipType::query()->create(['organization_id'=>$organization->id,'name'=>'Regular','code'=>'REG-'.uniqid(),'is_active'=>true,'metadata'=>[]]);
        $membership = Membership::query()->create(['member_id'=>$member->id,'membership_type_id'=>$type->id,'status'=>MembershipStatus::ACTIVE,'starts_at'=>'2025-10-08','ends_at'=>null,'metadata'=>[]]);
        $item = MemberPaymentItem::query()->create(['organization_id'=>$organization->id,'membership_type_id'=>$type->id,'title'=>'Annual Subscription','slug'=>'annual-sub-'.uniqid(),'is_mandatory'=>true,'is_recurring'=>true,'recurring_period'=>'yearly','recurring_by'=>'join_date','recurring_date'=>null,'is_active'=>true,'metadata'=>[]]);
        $rate = MemberPaymentItemRate::query()->create(['member_payment_item_id'=>$item->id,'amount'=>500,'currency'=>'INR','effective_from'=>'2025-01-01','effective_to'=>null]);
        $rate->allocations()->create(['scope_type'=>'organization','percentage'=>100]);

        $created = app(MemberPaymentService::class)->syncOutstandingObligations($membership, '2026-10-08');

        $this->assertCount(2, $created);
        $this->assertDatabaseHas('member_payment_obligations',['membership_id'=>$membership->id,'occurrence_key'=>'yearly:2025-10-08']);
        $this->assertDatabaseHas('member_payment_obligations',['membership_id'=>$membership->id,'occurrence_key'=>'yearly:2026-10-08']);
    }

    public function test_daily_generation_is_idempotent_for_join_date_occurrence(): void
    {
        $organization = Organization::factory()->create();
        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $type = MembershipType::query()->create(['organization_id'=>$organization->id,'name'=>'Regular','code'=>'REG-'.uniqid(),'is_active'=>true,'metadata'=>[]]);
        $membership = Membership::query()->create(['member_id'=>$member->id,'membership_type_id'=>$type->id,'status'=>MembershipStatus::ACTIVE,'starts_at'=>'2025-10-08','ends_at'=>null,'metadata'=>[]]);
        $item = MemberPaymentItem::query()->create(['organization_id'=>$organization->id,'membership_type_id'=>$type->id,'title'=>'Annual Subscription','slug'=>'annual-sub-'.uniqid(),'is_mandatory'=>true,'is_recurring'=>true,'recurring_period'=>'yearly','recurring_by'=>'join_date','recurring_date'=>null,'is_active'=>true,'metadata'=>[]]);
        $rate = MemberPaymentItemRate::query()->create(['member_payment_item_id'=>$item->id,'amount'=>500,'currency'=>'INR','effective_from'=>'2025-01-01','effective_to'=>null]);
        $rate->allocations()->create(['scope_type'=>'organization','percentage'=>100]);

        $service = app(MemberPaymentService::class);
        $first = $service->syncOutstandingObligations($membership, '2026-10-08');
        $second = $service->syncOutstandingObligations($membership, '2026-10-08');

        $this->assertCount(2, $first);
        $this->assertCount(0, $second);
        $this->assertDatabaseCount('member_payment_obligations', 2);
    }

    public function test_same_obligation_cannot_be_collected_twice(): void
    {
        [$organization, $member, $membershipType, $membership] =
            $this->memberContext('2026-01-01');

        $this->serviceItem(
            $organization,
            $membershipType,
            'service-fee',
            'Service Fee',
            50,
        );

        $service = app(MemberPaymentService::class);
        $obligation = $service
            ->syncOutstandingObligations($membership, '2026-01-01')
            ->firstOrFail();

        $actor = User::factory()->create();

        $firstPayment = $service->recordPayment(
            [$obligation],
            $actor,
            'unit',
            $member->unit_id,
        );

        $this->assertSame('50.00', $firstPayment->total_amount);

        try {
            $service->recordPayment(
                [$obligation],
                $actor,
                'unit',
                $member->unit_id,
            );

            $this->fail('A second collection for the same paid obligation should be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertDatabaseCount('member_payments', 1);
        $this->assertDatabaseCount('member_payment_lines', 1);

        $this->assertDatabaseHas('member_payment_obligations', [
            'id' => $obligation->id,
            'status' => MemberPaymentObligationStatus::PAID->value,
            'paid_amount' => '50.00',
        ]);
    }


}
