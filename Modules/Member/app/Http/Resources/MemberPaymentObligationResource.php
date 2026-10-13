<?php
namespace Modules\Member\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class MemberPaymentObligationResource extends JsonResource
{
    public function toArray(Request $request): array { return [
        'id'=>$this->id,'organization_id'=>$this->organization_id,'membership_application_id'=>$this->membership_application_id,'membership_id'=>$this->membership_id,'member_id'=>$this->member_id,'member_payment_item_id'=>$this->member_payment_item_id,'item_title'=>$this->item_title,'occurrence_key'=>$this->occurrence_key,'amount'=>(string)$this->amount,'original_amount'=>(string)$this->amount,'discount_amount'=>(string)$this->discount_amount,'payable_amount'=>(string)$this->payable_amount,'paid_amount'=>(string)$this->paid_amount,'outstanding_amount'=>(string)max(0,(float)$this->payable_amount-(float)$this->paid_amount),'currency'=>$this->currency,'status'=>$this->status->value,'due_at'=>$this->due_at?->toDateString(),'paid_at'=>$this->paid_at?->toISOString(),'allocations'=>$this->whenLoaded('allocations',fn()=> $this->allocations->map(fn($a)=>['scope_type'=>$a->scope_type,'scope_id'=>$a->scope_id,'percentage'=>(string)$a->percentage,'original_amount'=>(string)$a->original_amount,'payable_amount'=>(string)$a->payable_amount])->values()->all()),
        'member'=>$this->whenLoaded('member',fn()=>['id'=>$this->member->id,'membership_number'=>$this->member->membership_number,'name'=>$this->member->user?->name]),
    ]; }
}
