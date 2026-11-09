<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Http\Resources\MemberPaymentHistoryResource;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPayment;

class MemberPaymentHistoryController
{
    public function index(
        Request $request,
        Organization $organization,
        Member $member,
    ): AnonymousResourceCollection {
        $this->ensureOrganizationIsCurrent($organization);
        $this->ensureMemberOrganization($organization, $member);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $payments = MemberPayment::query()
            ->where('organization_id', $organization->id)
            ->where('member_id', $member->id)
            ->with([
                'receivedBy:id,name',
                'lines.obligation:id,organization_id,member_id,item_title,occurrence_key,due_at,status',
            ])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        // Return the resource collection directly so Laravel applies the
        // standard { data, links, meta } pagination envelope.
        return MemberPaymentHistoryResource::collection($payments);
    }

    public function show(
        Organization $organization,
        Member $member,
        MemberPayment $payment,
    ): MemberPaymentHistoryResource {
        $this->ensureOrganizationIsCurrent($organization);
        $this->ensureMemberOrganization($organization, $member);
        $this->ensurePaymentBelongsToMember($payment, $member);

        $payment->load([
            'receivedBy:id,name',
            'application:id,organization_id,status',
            'membership:id,member_id,membership_type_id,status',
            'lines.obligation:id,organization_id,member_id,item_title,occurrence_key,amount,discount_amount,payable_amount,paid_amount,currency,status,due_at,paid_at',
            'lines.allocations',
        ]);

        return new MemberPaymentHistoryResource($payment);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        return $organization;
    }

    private function ensureOrganizationIsCurrent(Organization $organization): void
    {
        if ((int) $organization->id !== (int) $this->organization()->id) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is not the current organization.',
            ]);
        }
    }

    private function ensureMemberOrganization(Organization $organization, Member $member): void
    {
        if ((int) $member->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'member' => 'The selected member does not belong to the current organization.',
            ]);
        }
    }

    private function ensurePaymentBelongsToMember(MemberPayment $payment, Member $member): void
    {
        if (
            (int) $payment->organization_id !== (int) $member->organization_id
            || (int) $payment->member_id !== (int) $member->id
        ) {
            throw ValidationException::withMessages([
                'payment' => 'The selected payment does not belong to this member and organization.',
            ]);
        }
    }
}
