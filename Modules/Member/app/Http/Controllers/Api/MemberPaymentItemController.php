<?php
namespace Modules\Member\Http\Controllers\Api;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Http\Requests\StoreMemberPaymentItemRateRequest;
use Modules\Member\Http\Requests\StoreMemberPaymentItemRequest;
use Modules\Member\Http\Requests\UpdateMemberPaymentItemRequest;
use Modules\Member\Http\Resources\MemberPaymentItemRateResource;
use Modules\Member\Http\Resources\MemberPaymentItemResource;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Services\MemberPaymentItemConfigurationService;
class MemberPaymentItemController
{
    public function __construct(private MemberPaymentItemConfigurationService $service) {}
    public function index(): JsonResponse { return response()->json(MemberPaymentItemResource::collection($this->service->list($this->organization()))); }
    public function store(StoreMemberPaymentItemRequest $request): JsonResponse { $item=$this->service->create($this->organization(),$request->validated()); return response()->json(new MemberPaymentItemResource($item),201); }
    public function update(UpdateMemberPaymentItemRequest $request, MemberPaymentItem $memberPaymentItem): JsonResponse { $item=$this->service->update($this->organization(),$memberPaymentItem,$request->validated()); return response()->json(new MemberPaymentItemResource($item)); }
    public function addRate(StoreMemberPaymentItemRateRequest $request, MemberPaymentItem $memberPaymentItem): JsonResponse { $rate=$this->service->addRate($this->organization(),$memberPaymentItem,$request->validated()); return response()->json(new MemberPaymentItemRateResource($rate),201); }
    private function organization(): Organization { $organization=app(CurrentOrganization::class)->get(); if(!$organization instanceof Organization) throw ValidationException::withMessages(['organization'=>'The selected organization is invalid.']); return $organization; }
}
