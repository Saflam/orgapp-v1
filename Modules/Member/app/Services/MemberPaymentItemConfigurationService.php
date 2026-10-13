<?php
namespace Modules\Member\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MemberPaymentRecurrenceBy;
use Modules\Member\Enums\MemberPaymentRecurrencePeriod;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemAllocation;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Models\MembershipType;

class MemberPaymentItemConfigurationService
{
    public function list(Organization $organization)
    {
        return MemberPaymentItem::query()
            ->with(['membershipType','rates'=>fn($q)=>$q->with('allocations')->orderBy('effective_from')])
            ->where('organization_id',$organization->id)->orderBy('id')->get();
    }

    public function create(Organization $organization, array $data): MemberPaymentItem
    {
        return DB::transaction(function () use ($organization,$data) {
            $this->validateMembershipType($organization,$data['membership_type_id']??null);
            $this->validateShape($data);
            $this->ensureSlugAvailable($organization,$data['membership_type_id']??null,$data['slug']);
            $item=MemberPaymentItem::create([
                'organization_id'=>$organization->id,
                'membership_type_id'=>$data['membership_type_id']??null,
                'title'=>$data['title'],
                'slug'=>$data['slug'],
                'description'=>$data['description']??null,
                'is_mandatory'=>$data['is_mandatory']??true,
                'is_recurring'=>$data['is_recurring']??false,
                'recurring_period'=>$data['recurring_period']??null,
                'recurring_by'=>$data['recurring_by']??null,
                'recurring_date'=>$data['recurring_date']??null,
                'is_active'=>$data['is_active']??true,
                'reminder_days_before_due'=>$data['reminder_days_before_due']??null,
                'reminder_days_after_due'=>$data['reminder_days_after_due']??null,
                'metadata'=>$data['metadata']??[],
            ]);
            $this->createRate($item,$data['rate']);
            return $item->load(['membershipType','rates.allocations']);
        });
    }

    public function update(Organization $organization, MemberPaymentItem $item, array $data): MemberPaymentItem
    {
        $this->ensureOrganization($organization,$item);
        return DB::transaction(function() use($item,$data){
            $merged=array_merge($item->only(['membership_type_id','title','description','is_mandatory','is_recurring','recurring_period','recurring_by','recurring_date','is_active','reminder_days_before_due','reminder_days_after_due','metadata']),$data);
            $this->validateShape($merged);
            if(array_key_exists('membership_type_id',$data)) $this->validateMembershipType($item->organization,$data['membership_type_id']);
            if(array_key_exists('slug',$data) && $data['slug']!==$item->slug) $this->ensureSlugAvailable($item->organization,$data['membership_type_id']??$item->membership_type_id,$data['slug'],$item->id);
            $item->update($data);
            return $item->refresh()->load(['membershipType','rates.allocations']);
        });
    }

    public function addRate(Organization $organization, MemberPaymentItem $item, array $data): MemberPaymentItemRate
    {
        $this->ensureOrganization($organization,$item);
        return DB::transaction(function() use($item,$data){
            $this->validateRate($data);
            $this->ensureRateDoesNotOverlap($item,$data);
            $from=Carbon::parse($data['effective_from'])->startOfDay();
            $previous=$item->rates()->whereNull('effective_to')->whereDate('effective_from','<',$from->toDateString())->orderByDesc('effective_from')->first();
            if($previous) $previous->update(['effective_to'=>$from->copy()->subDay()->toDateString()]);
            $rate=$item->rates()->create(['amount'=>$data['amount'],'currency'=>strtoupper($data['currency']),'effective_from'=>$data['effective_from'],'effective_to'=>$data['effective_to']??null]);
            foreach($data['allocations'] as $allocation) $rate->allocations()->create(['scope_type'=>$allocation['scope_type'],'percentage'=>$allocation['percentage']]);
            return $rate->load('allocations');
        });
    }

    private function createRate(MemberPaymentItem $item,array $data): MemberPaymentItemRate
    {
        $this->validateRate($data);
        $rate=$item->rates()->create(['amount'=>$data['amount'],'currency'=>strtoupper($data['currency']),'effective_from'=>$data['effective_from'],'effective_to'=>$data['effective_to']??null]);
        foreach($data['allocations'] as $allocation) $rate->allocations()->create(['scope_type'=>$allocation['scope_type'],'percentage'=>$allocation['percentage']]);
        return $rate;
    }

    private function validateRate(array $data): void
    {
        if(empty($data['allocations'])) throw ValidationException::withMessages(['rate.allocations'=>'At least one allocation is required.']);
        $sum=round(collect($data['allocations'])->sum(fn($a)=>(float)$a['percentage']),4);
        if(abs($sum-100.0)>0.0001) throw ValidationException::withMessages(['rate.allocations'=>'Allocation percentages must total exactly 100%.']);
        $scopeTypes=[];
        foreach($data['allocations'] as $allocation){
            if(!in_array($allocation['scope_type'],['organization','unit'],true)) throw ValidationException::withMessages(['rate.allocations'=>'Allocation scope type must be organization or unit.']);
            if(in_array($allocation['scope_type'],$scopeTypes,true)) throw ValidationException::withMessages(['rate.allocations'=>'Each allocation scope type may appear only once per rate.']);
            $scopeTypes[]=$allocation['scope_type'];
        }
    }

    private function validateShape(array $data): void
    {
        $recurring=(bool)($data['is_recurring']??false);
        $period=$data['recurring_period']??null;
        $by=$data['recurring_by']??null;
        $date=$data['recurring_date']??null;
        if(!$recurring && ($period!==null || $by!==null || $date!==null)) throw ValidationException::withMessages(['is_recurring'=>'Recurring settings are not allowed for a non-recurring payment item.']);
        if($recurring && !$period) throw ValidationException::withMessages(['recurring_period'=>'Recurring period is required for recurring payment items.']);
        if($recurring && !$by) throw ValidationException::withMessages(['recurring_by'=>'Recurring by is required for recurring payment items.']);
        if($recurring && $by===MemberPaymentRecurrenceBy::SPECIFIC_DATE->value && !$date) throw ValidationException::withMessages(['recurring_date'=>'Recurring date is required when recurring by specific date.']);
        if($recurring && $by===MemberPaymentRecurrenceBy::JOIN_DATE->value && $date!==null) throw ValidationException::withMessages(['recurring_date'=>'Recurring date must be empty when recurring by join date.']);
        if($recurring && !in_array($period,array_column(MemberPaymentRecurrencePeriod::cases(),'value'),true)) throw ValidationException::withMessages(['recurring_period'=>'The selected recurring period is invalid.']);
        if($recurring && !in_array($by,array_column(MemberPaymentRecurrenceBy::cases(),'value'),true)) throw ValidationException::withMessages(['recurring_by'=>'The selected recurring anchor is invalid.']);
        if($recurring && $date!==null) $this->validateRecurringDate($period,$date);
    }
    private function validateRecurringDate(string $period,string $date): void
    {
        if($period==='monthly') { if(!preg_match('/^([0-9]{1,2})$/',$date) || (int)$date<1 || (int)$date>31) throw ValidationException::withMessages(['recurring_date'=>'For monthly recurrence, recurring_date must be a day number from 1 to 31.']); return; }
        if(!preg_match('/^([0-9]{2})-([0-9]{2})$/',$date) || !checkdate((int)substr($date,0,2),(int)substr($date,3,2),2024)) throw ValidationException::withMessages(['recurring_date'=>'For non-monthly specific dates, recurring_date must use MM-DD.']);
    }
    private function ensureRateDoesNotOverlap(MemberPaymentItem $item,array $data): void
    {
        $from=Carbon::parse($data['effective_from'])->toDateString(); $to=isset($data['effective_to'])&&$data['effective_to']!==null?Carbon::parse($data['effective_to'])->toDateString():null;
        $overlap=$item->rates()->where(function($q)use($from,$to){$q->where(function($q)use($from){$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$from);})->where(function($q)use($to){if($to===null)$q->whereNotNull('effective_from');else $q->whereDate('effective_from','<=',$to);});})->exists();
        if($overlap && !($to===null && $item->rates()->whereNull('effective_to')->whereDate('effective_from','<',$from)->exists())) throw ValidationException::withMessages(['rate.effective_from'=>'The new rate overlaps an existing rate period.']);
    }
    private function validateMembershipType(Organization $organization,?int $id):void { if($id!==null&&!MembershipType::where('organization_id',$organization->id)->whereKey($id)->exists()) throw ValidationException::withMessages(['membership_type_id'=>'The membership type does not belong to the selected organization.']); }
    private function ensureSlugAvailable(Organization $organization,?int $typeId,string $slug,?int $ignore=null):void { $q=MemberPaymentItem::where('organization_id',$organization->id)->where('slug',$slug); $typeId===null?$q->whereNull('membership_type_id'):$q->where('membership_type_id',$typeId); if($ignore!==null)$q->where('id','!=',$ignore); if($q->exists()) throw ValidationException::withMessages(['slug'=>'A payment item with this slug already exists for the selected membership type.']); }
    private function ensureOrganization(Organization $organization,MemberPaymentItem $item):void { if($item->organization_id!==$organization->id) throw ValidationException::withMessages(['payment_item'=>'The payment item does not belong to the selected organization.']); }
}
