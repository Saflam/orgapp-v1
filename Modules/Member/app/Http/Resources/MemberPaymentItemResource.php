<?php
namespace Modules\Member\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class MemberPaymentItemResource extends JsonResource
{
    public function toArray(Request $request): array { return [
        'id'=>$this->id,'organization_id'=>$this->organization_id,'membership_type_id'=>$this->membership_type_id,'title'=>$this->title,'slug'=>$this->slug,'description'=>$this->description,'is_mandatory'=>$this->is_mandatory,'is_recurring'=>$this->is_recurring,'recurring_period'=>$this->recurring_period,'recurring_by'=>$this->recurring_by,'recurring_date'=>$this->recurring_date,'is_active'=>$this->is_active,'reminder_days_before_due'=>$this->reminder_days_before_due,'reminder_days_after_due'=>$this->reminder_days_after_due,'metadata'=>$this->metadata??[], 'rates'=>MemberPaymentItemRateResource::collection($this->whenLoaded('rates')),
    ]; }
}
