<?php
namespace Modules\Member\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Member\Enums\MemberPaymentObligationStatus;
class MembershipApplicationResource extends JsonResource
{
    public function toArray(Request $request): array { return [
        'id'=>$this->id,'organization_id'=>$this->organization_id,'user_id'=>$this->user_id,'membership_type_id'=>$this->membership_type_id,'member_id'=>$this->member_id,'status'=>$this->status->value,'current_step'=>$this->current_step,'completed_steps'=>$this->completed_steps??[],'data'=>$this->data??[],'submitted_at'=>$this->submitted_at?->toISOString(),
        'membership_type'=>$this->whenLoaded('membershipType',fn()=>['id'=>$this->membershipType->id,'name'=>$this->membershipType->name,'code'=>$this->membershipType->code]),
        'payment_summary'=>$this->whenLoaded('paymentObligations',fn()=>['total_amount'=>(string)$this->paymentObligations->sum(fn($o)=>(float)$o->amount),'discount_amount'=>(string)$this->paymentObligations->sum(fn($o)=>(float)$o->discount_amount),'pending_amount'=>(string)$this->paymentObligations->whereIn('status',[MemberPaymentObligationStatus::PENDING,MemberPaymentObligationStatus::PARTIALLY_PAID])->sum(fn($o)=>max(0,(float)$o->payable_amount-(float)$o->paid_amount)),'currency'=>$this->paymentObligations->first()?->currency]),
        'payment_obligations'=>$this->whenLoaded('paymentObligations',fn()=>MemberPaymentObligationResource::collection($this->paymentObligations)),
        'changes'=>$this->whenLoaded('changes',fn()=> $this->changes->map(fn($c)=>['id'=>$c->id,'actor_user_id'=>$c->actor_user_id,'reason'=>$c->reason,'before_data'=>$c->before_data,'after_data'=>$c->after_data,'created_at'=>$c->created_at?->toISOString()])->values()->all()),
        'history'=>$this->whenLoaded('statusHistories',fn()=> $this->statusHistories->map(fn($h)=>['id'=>$h->id,'from_status'=>$h->from_status?->value,'to_status'=>$h->to_status->value,'actor_user_id'=>$h->actor_user_id,'notes'=>$h->notes,'allow_reapply'=>$h->allow_reapply,'created_at'=>$h->created_at?->toISOString()])->values()->all()),
    ]; }
}
