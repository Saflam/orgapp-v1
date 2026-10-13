<?php
namespace Modules\Member\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class MemberPaymentItemRateResource extends JsonResource
{
    public function toArray(Request $request): array { return ['id'=>$this->id,'member_payment_item_id'=>$this->member_payment_item_id,'amount'=>(string)$this->amount,'currency'=>$this->currency,'effective_from'=>$this->effective_from?->toDateString(),'effective_to'=>$this->effective_to?->toDateString(),'allocations'=>$this->whenLoaded('allocations',fn()=> $this->allocations->map(fn($a)=>['id'=>$a->id,'scope_type'=>$a->scope_type,'percentage'=>(string)$a->percentage])->values()->all())]; }
}
