<?php
namespace Modules\Member\Http\Controllers\Api;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Http\Requests\DiscountMemberPaymentRequest;
use Modules\Member\Http\Requests\StoreMemberPaymentRequest;
use Modules\Member\Http\Resources\MemberPaymentObligationResource;
use Modules\Member\Http\Resources\MembershipApplicationResource;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MemberPaymentService;
class MemberPaymentController
{
    public function __construct(private MemberPaymentService $payments) {}
    public function pendingApplications(): JsonResponse { $org=$this->organization(); $apps=MembershipApplication::query()->with(['user.details','membershipType','paymentObligations'])->where('organization_id',$org->id)->where('status',MembershipApplicationStatus::PENDING_PAYMENT)->orderBy('submitted_at')->get(); return response()->json(MembershipApplicationResource::collection($apps)); }
    public function pendingRecurring(): JsonResponse { return response()->json(MemberPaymentObligationResource::collection($this->payments->pendingRecurringObligations($this->organization()->id))); }
    public function pendingMember(Member $member): JsonResponse { $this->ensureMemberOrganization($member); $obligations=$this->payments->pendingMemberObligations($member); return $this->pendingResponse($member->id,$obligations); }
    public function pendingMemberInUnit(Unit $unit,Member $member): JsonResponse { $this->ensureUnit($unit); $this->ensureMemberOrganization($member); if($member->unit_id!==$unit->id) throw ValidationException::withMessages(['member'=>'The member does not belong to the selected unit.']); return $this->pendingResponse($member->id,$this->payments->pendingMemberObligations($member)); }
    public function pendingApplication(MembershipApplication $application): JsonResponse { $this->ensureApplicationOrganization($application); $obligations=$application->paymentObligations()->with(['member','membership','paymentItem','paymentItemRate','allocations'])->orderBy('due_at')->get(); return response()->json(['application_id'=>$application->id,'status'=>$application->status->value,'total_amount'=>(string)$obligations->sum(fn($o)=>(float)$o->amount),'discount_amount'=>(string)$obligations->sum(fn($o)=>(float)$o->discount_amount),'pending_amount'=>(string)$obligations->whereIn('status',[\Modules\Member\Enums\MemberPaymentObligationStatus::PENDING,\Modules\Member\Enums\MemberPaymentObligationStatus::PARTIALLY_PAID])->sum(fn($o)=>max(0,(float)$o->payable_amount-(float)$o->paid_amount)),'currency'=>$obligations->first()?->currency,'obligations'=>MemberPaymentObligationResource::collection($obligations)]); }
    public function discount(DiscountMemberPaymentRequest $request,MemberPaymentObligation $obligation): JsonResponse { $this->ensureObligationOrganization($obligation); return response()->json(new MemberPaymentObligationResource($this->payments->applyDiscount($obligation,$request->validated('reason'),(float)$request->validated('discount_amount'),$request->user()))); }
    public function markPaid(StoreMemberPaymentRequest $request,MemberPaymentObligation $obligation): JsonResponse { $this->ensureObligationOrganization($obligation); if(!in_array($obligation->id,$request->validated('obligation_ids'),true)) throw ValidationException::withMessages(['obligation_ids'=>'The selected payment obligation must be included in the payment.']); $payment=$this->payments->recordPayment($request->validated('obligation_ids'),$request->user(),'organization',$this->organization()->id,$request->validated('payment_method'),$request->validated('reference')); return response()->json(['payment'=>$payment->load('lines.obligation'),'obligations'=>MemberPaymentObligationResource::collection($payment->lines->load('obligation')->pluck('obligation'))],201); }
    public function markPaidInUnit(StoreMemberPaymentRequest $request,Unit $unit,MemberPaymentObligation $obligation): JsonResponse { $this->ensureUnit($unit); if(!in_array($obligation->id,$request->validated('obligation_ids'),true)) throw ValidationException::withMessages(['obligation_ids'=>'The selected payment obligation must be included in the payment.']); $this->ensureObligationOrganization($obligation); if($obligation->member?->unit_id!==$unit->id) throw ValidationException::withMessages(['payment'=>'The obligation does not belong to the selected unit.']); $payment=$this->payments->recordPayment($request->validated('obligation_ids'),$request->user(),'unit',$unit->id,$request->validated('payment_method'),$request->validated('reference')); return response()->json(['payment'=>$payment->load('lines.obligation'),'obligations'=>MemberPaymentObligationResource::collection($payment->lines->load('obligation')->pluck('obligation'))],201); }
    private function pendingResponse(int $memberId,$obligations): JsonResponse { return response()->json(['member_id'=>$memberId,'total_original_amount'=>(string)$obligations->sum(fn($o)=>(float)$o->amount),'total_discount_amount'=>(string)$obligations->sum(fn($o)=>(float)$o->discount_amount),'pending_amount'=>(string)$obligations->sum(fn($o)=>max(0,(float)$o->payable_amount-(float)$o->paid_amount)),'currency'=>$obligations->first()?->currency,'obligations'=>MemberPaymentObligationResource::collection($obligations)]); }
    private function organization(): Organization { $o=app(CurrentOrganization::class)->get(); if(!$o instanceof Organization) throw ValidationException::withMessages(['organization'=>'The selected organization is invalid.']); return $o; }
    private function ensureOrganizationId(int $id): void { if($id!==$this->organization()->id) throw ValidationException::withMessages(['organization'=>'The selected resource does not belong to the current organization.']); }
    private function ensureMemberOrganization(Member $member): void { $this->ensureOrganizationId($member->organization_id); }
    private function ensureApplicationOrganization(MembershipApplication $application): void { $this->ensureOrganizationId($application->organization_id); }
    private function ensureObligationOrganization(MemberPaymentObligation $obligation): void { $this->ensureOrganizationId($obligation->organization_id); }
    private function ensureUnit(Unit $unit): void { $this->ensureOrganizationId($unit->organization_id); }
}
