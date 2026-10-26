<?php
namespace Modules\Member\Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\MembershipType;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;
class MemberPaymentItemConfigurationApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;
    public function test_admin_can_create_yearly_specific_date_payment_item_with_central_and_unit_share(): void
    {
        $organization=Organization::factory()->create(['slug' => 'org-' . uniqid()]); $actor=User::factory()->create(); $this->actAsMemberOrganizationAdmin($organization,$actor);
        $type=MembershipType::create(['organization_id'=>$organization->id,'name'=>'Regular','code'=>'REG-'.uniqid(),'is_active'=>true,'metadata'=>[]]);
        $response=$this->postJson($this->organizationUrl($organization, '/api/v1/member-payment-items'),['membership_type_id'=>$type->id,'title'=>'Service Fee','slug'=>'service-fee','is_mandatory'=>true,'is_recurring'=>true,'recurring_period'=>'yearly','recurring_by'=>'specific_date','recurring_date'=>'12-31','rate'=>['amount'=>50,'currency'=>'INR','effective_from'=>'2026-01-01','allocations'=>[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>80]]]]);
        $response->assertCreated()->assertJsonPath('title','Service Fee');
        $this->assertDatabaseHas('member_payment_items',['organization_id'=>$organization->id,'slug'=>'service-fee','is_recurring'=>1,'recurring_date'=>'12-31']);
        $this->assertDatabaseHas('member_payment_item_allocations',['percentage'=>'20.0000']); $this->assertDatabaseHas('member_payment_item_allocations',['percentage'=>'80.0000']);
    }
    public function test_allocation_percentages_must_total_one_hundred(): void
    {
        $organization=Organization::factory()->create(['slug' => 'org-' . uniqid()]); $actor=User::factory()->create(); $this->actAsMemberOrganizationAdmin($organization,$actor); $type=MembershipType::create(['organization_id'=>$organization->id,'name'=>'Regular','code'=>'REG-'.uniqid(),'is_active'=>true,'metadata'=>[]]);
        $this->postJson($this->organizationUrl($organization, '/api/v1/member-payment-items'),['membership_type_id'=>$type->id,'title'=>'Service Fee','slug'=>'service-fee','is_mandatory'=>true,'is_recurring'=>true,'recurring_period'=>'yearly','recurring_by'=>'specific_date','recurring_date'=>'12-31','rate'=>['amount'=>50,'currency'=>'INR','effective_from'=>'2026-01-01','allocations'=>[['scope_type'=>'organization','percentage'=>20],['scope_type'=>'unit','percentage'=>70]]]])->assertStatus(422)->assertJsonValidationErrors(['rate.allocations']);
    }
}
