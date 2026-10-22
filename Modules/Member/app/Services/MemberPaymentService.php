<?php
namespace Modules\Member\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Models\MemberPaymentObligationDiscount;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\MemberPaymentLine;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipApplication;

class MemberPaymentService
{
    public function createApplicationObligations(MembershipApplication $application): Collection
    {
        return DB::transaction(function () use ($application) {
            $application = MembershipApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $created = collect();
            $items = $this->mandatoryItemsForApplicationInvoice($application->organization_id);
            $today = now()->startOfDay();
            $desired = $items->map(function (MemberPaymentItem $item) use ($today): array {
                $due = $this->applicationDueDate($item, $today);
                $occurrence = $item->is_recurring ? $this->occurrenceKey($item, $due) : 'one-time';

                return [
                    'item' => $item,
                    'due' => $due,
                    'occurrence' => $occurrence,
                    'key' => $item->id . '|' . $occurrence,
                ];
            });
            $desiredKeys = $desired->pluck('key')->all();

            // Invoice rows are snapshots in member_payment_obligations. Reconcile
            // them against the current organization configuration rather than trusting
            // rows created during an earlier visit to the payment stage. Matching on
            // both item and occurrence also handles recurrence-rule changes correctly.
            $staleObligations = MemberPaymentObligation::query()
                ->where('membership_application_id', $application->id)
                ->whereNotIn('status', [
                    MemberPaymentObligationStatus::PAID,
                    MemberPaymentObligationStatus::PARTIALLY_PAID,
                    MemberPaymentObligationStatus::WAIVED,
                    MemberPaymentObligationStatus::COVERED_BY_FAMILY,
                ])
                ->get()
                ->filter(fn (MemberPaymentObligation $obligation): bool =>
                    ! in_array(($obligation->member_payment_item_id ?? '') . '|' . $obligation->occurrence_key, $desiredKeys, true)
                );

            foreach ($staleObligations as $stale) {
                if ((float) $stale->paid_amount > 0 || $stale->paymentLines()->exists()) {
                    continue;
                }
                $metadata = $stale->metadata ?? [];
                $metadata['invoice_sync_cancelled'] = true;
                $metadata['invoice_sync_cancelled_at'] = now()->toISOString();
                $metadata['invoice_sync_cancel_reason'] = 'payment_item_inactive_non_mandatory_or_occurrence_changed';
                $stale->update([
                    'status' => MemberPaymentObligationStatus::CANCELLED,
                    'metadata' => $metadata,
                ]);
            }

            foreach ($desired as $entry) {
                $created->push($this->createObligationForApplication(
                    $application,
                    $entry['item'],
                    $entry['due'],
                    $entry['occurrence'],
                ));
            }

            return $created;
        });
    }

    /** Refresh pending application invoice snapshots after payment-category changes. */
    public function refreshPendingApplicationInvoiceSnapshots(int $organizationId): void
    {
        $applications = MembershipApplication::query()
            ->where('organization_id', $organizationId)
            ->where('status', MembershipApplicationStatus::PENDING_PAYMENT)
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            // Sync all category rows because an item becoming inactive/non-mandatory
            // must also remove its old pending snapshot from the invoice.
            $this->createApplicationObligations($application);
        }
    }

    public function syncOutstandingObligations(Membership $membership, ?string $asOfDate=null): Collection
    {
        $member=$membership->member()->firstOrFail();
        $asOf=Carbon::parse($asOfDate??now()->toDateString())->startOfDay();
        $start=Carbon::parse($membership->starts_at)->startOfDay();
        if($start->gt($asOf)) return collect();
        return DB::transaction(function() use($membership,$member,$start,$asOf){
            $created=collect();
            foreach($this->itemsForMembershipType($member->organization_id,$membership->membership_type_id,true) as $item){
                foreach($this->dueDates($item,$start,$asOf) as $due){
                    $obligation = $this->createObligationForMember($membership,$member,$item,$due);
                    if ($obligation->wasRecentlyCreated) {
                        $created->push($obligation);
                    }
                }
            }
            return $created;
        });
    }

    public function pendingMemberObligations(Member $member): Collection
    {
        $membership=$member->memberships()->where('status',MembershipStatus::ACTIVE)->latest('id')->first();
        if($membership) $this->syncOutstandingObligations($membership);
        return MemberPaymentObligation::query()->with(['membership.membershipType','paymentItem','paymentItemRate','allocations'])
            ->where('organization_id',$member->organization_id)->where('member_id',$member->id)
            ->whereIn('status',[MemberPaymentObligationStatus::PENDING,MemberPaymentObligationStatus::PARTIALLY_PAID])
            ->where('payable_amount','>',0)->orderBy('due_at')->orderBy('id')->get();
    }

    public function pendingRecurringObligations(int $organizationId): Collection
    {
        $memberships=Membership::query()->with('member')->whereHas('member',fn($q)=>$q->where('organization_id',$organizationId))->where('status',MembershipStatus::ACTIVE)->get();
        foreach($memberships as $membership) $this->syncOutstandingObligations($membership);
        return MemberPaymentObligation::query()->with(['member.user.details','membership.membershipType','paymentItem','paymentItemRate','allocations'])
            ->where('organization_id',$organizationId)->whereHas('paymentItem',fn($q)=>$q->where('is_recurring',true))
            ->whereIn('status',[MemberPaymentObligationStatus::PENDING,MemberPaymentObligationStatus::PARTIALLY_PAID])->orderBy('due_at')->orderBy('id')->get();
    }

    public function pendingApplicationObligations(MembershipApplication $application): Collection
    {
        // The obligation table is a financial snapshot, but the current category
        // configuration controls whether an unpaid item is still invoiceable.
        // Filter here as well as reconciling snapshots during invoice preparation:
        // the invoice may already be open when a category is deactivated or made
        // optional in another browser tab. This also keeps displayed totals and
        // the server-side collection amount in sync with the current configuration.
        return $application->paymentObligations()
            ->with(['paymentItem','paymentItemRate','allocations'])
            ->whereIn('status', [MemberPaymentObligationStatus::PENDING, MemberPaymentObligationStatus::PARTIALLY_PAID])
            ->where('payable_amount', '>', 0)
            ->whereHas('paymentItem', fn ($query) => $query
                ->where('organization_id', $application->organization_id)
                ->where('is_active', true)
                ->where('is_mandatory', true))
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
    }

    public function applyDiscount(MemberPaymentObligation $obligation,string $reason,float $discountAmount,User $actor): MemberPaymentObligation
    {
        return DB::transaction(function() use($obligation,$reason,$discountAmount,$actor){
            if(!$obligation->isPending()) throw ValidationException::withMessages(['payment'=>'Only pending payment obligations can be discounted.']);
            if($discountAmount<=0) throw ValidationException::withMessages(['discount_amount'=>'The discount amount must be greater than zero.']);
            $already=(float)$obligation->discount_amount;
            if((float)$obligation->paid_amount>0) throw ValidationException::withMessages(['discount_amount'=>'A payment has already been recorded for this obligation; discount it before collecting payment.']);
            if($already>0) throw ValidationException::withMessages(['discount_amount'=>'A discount has already been applied to this payment obligation.']);
            $original=(float)$obligation->amount;
            if($discountAmount>$original) throw ValidationException::withMessages(['discount_amount'=>'The discount cannot exceed the original obligation amount.']);
            $payable=round($original-$discountAmount,2);
            MemberPaymentObligationDiscount::create(['member_payment_obligation_id'=>$obligation->id,'original_amount'=>$original,'discount_amount'=>$discountAmount,'payable_amount'=>$payable,'reason'=>$reason,'actor_user_id'=>$actor->id]);
            $obligation->update(['discount_amount'=>$discountAmount,'payable_amount'=>$payable,'status'=>$payable===0.0?MemberPaymentObligationStatus::WAIVED:MemberPaymentObligationStatus::PENDING]);
            foreach($obligation->allocations as $allocation){ $allocation->update(['payable_amount'=>round($payable*((float)$allocation->percentage/100),2)]); }
            return $obligation->refresh()->load('allocations');
        });
    }

    /** Prepare the invoice when an authorized collector opens the payment stage. */
    public function prepareApplicationInvoice(MembershipApplication $application, User $actor): Collection
    {
        return DB::transaction(function () use ($application, $actor): Collection {
            $application = MembershipApplication::query()
                ->where('organization_id', $application->organization_id)
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($application->status === MembershipApplicationStatus::APPROVED) {
                $application->update(['status' => MembershipApplicationStatus::PENDING_PAYMENT]);
                $application->statusHistories()->create([
                    'from_status' => MembershipApplicationStatus::APPROVED->value,
                    'to_status' => MembershipApplicationStatus::PENDING_PAYMENT->value,
                    'actor_user_id' => $actor->id,
                    'notes' => 'Application entered the payment stage and its invoice was prepared.',
                    'allow_reapply' => false,
                    'metadata' => [],
                ]);
                $application->refresh();
                $this->createApplicationObligations($application);
            } elseif ($application->status !== MembershipApplicationStatus::PENDING_PAYMENT) {
                throw ValidationException::withMessages(['status' => 'An invoice can only be prepared for an approved or payment-pending application.']);
            }

            // Always reconcile the persisted invoice snapshot. Existing pending-payment
            // applications may predate payment-category changes, so a one-time snapshot
            // created during the original approval transition is not authoritative.
            $this->createApplicationObligations($application->refresh());

            // Family-covered, waived, cancelled, and already-paid obligations remain
            // in the ledger for audit purposes but are not invoice line items.
            return $this->pendingApplicationObligations($application->refresh())
                ->load(['discounts.actor']);
        });
    }

    
    public function markApplicationPaid(
        MembershipApplication $application,
        User $actor,
        string $scopeType,
        ?int $scopeId = null,
        ?string $paymentMethod = null,
        ?string $reference = null,
        array $discounts = [],
        ?string $collectionNotes = null,
    ): MembershipApplication {
        return DB::transaction(function () use (
            $application,
            $actor,
            $scopeType,
            $scopeId,
            $paymentMethod,
            $reference,
            $discounts,
            $collectionNotes,
        ): MembershipApplication {
            $application = MembershipApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $application->status;

            if (
                ! in_array($from, [
                    MembershipApplicationStatus::APPROVED,
                    MembershipApplicationStatus::PENDING_PAYMENT,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved or payment-pending applications can be paid.',
                ]);
            }

            if ($from === MembershipApplicationStatus::APPROVED) {
                $application->update([
                    'status' => MembershipApplicationStatus::PENDING_PAYMENT,
                ]);

                $application->statusHistories()->create([
                    'from_status' => $from->value,
                    'to_status' => MembershipApplicationStatus::PENDING_PAYMENT->value,
                    'actor_user_id' => $actor->id,
                    'notes' => 'Application entered the payment stage.',
                    'allow_reapply' => false,
                    'metadata' => [],
                ]);

                $this->createApplicationObligations($application);
                $application->refresh();
            }

            $obligations = $this->pendingApplicationObligations($application);

            if ($discounts !== []) {
                $eligibleIds = $obligations->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($discounts as $obligationId => $discount) {
                    $obligationId = (int) $obligationId;

                    if (! in_array($obligationId, $eligibleIds, true)) {
                        throw ValidationException::withMessages([
                            'discounts' => 'A discount was submitted for an item that is not payable on this application.',
                        ]);
                    }

                    $amount = round((float) ($discount['amount'] ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $reason = trim((string) ($discount['reason'] ?? ''));

                    if ($reason === '') {
                        throw ValidationException::withMessages([
                            "discountReasons.{$obligationId}" => 'Enter a reason for this discount.',
                        ]);
                    }

                    $this->applyDiscount(
                        $obligations->firstWhere('id', $obligationId),
                        $reason,
                        $amount,
                        $actor,
                    );
                }

                $obligations = $this->pendingApplicationObligations(
                    $application->refresh()
                );
            }

            if ($obligations->isEmpty()) {
                $application->update([
                    'status' => MembershipApplicationStatus::PENDING_CONFIRMATION,
                ]);

                $application->statusHistories()->create([
                    'from_status' => MembershipApplicationStatus::PENDING_PAYMENT->value,
                    'to_status' => MembershipApplicationStatus::PENDING_CONFIRMATION->value,
                    'actor_user_id' => $actor->id,
                    'notes' => 'No payable application obligations remain after invoice preparation and any authorized discounts.',
                    'allow_reapply' => false,
                    'metadata' => [],
                ]);

                return $application->refresh();
            }

            $this->recordPayment(
                $obligations,
                $actor,
                $scopeType,
                $scopeId,
                $paymentMethod,
                $reference,
                $collectionNotes !== null && trim($collectionNotes) !== ''
                    ? ['collection_notes' => trim($collectionNotes)]
                    : [],
            );

            $application->update([
                'status' => MembershipApplicationStatus::PENDING_CONFIRMATION,
            ]);

            $application->statusHistories()->create([
                'from_status' => MembershipApplicationStatus::PENDING_PAYMENT->value,
                'to_status' => MembershipApplicationStatus::PENDING_CONFIRMATION->value,
                'actor_user_id' => $actor->id,
                'notes' => 'All payable application payment obligations were received.',
                'allow_reapply' => false,
                'metadata' => [
                    'obligation_ids' => $obligations->pluck('id')->values()->all(),
                ],
            ]);

            return $application->refresh();
        });
    }
    
    public function ensureApplicationFullyPaid(MembershipApplication $application): void
    {
        $pending = $application->paymentObligations()
            ->whereIn('status', [
                MemberPaymentObligationStatus::PENDING,
                MemberPaymentObligationStatus::PARTIALLY_PAID,
            ])
            ->whereRaw('(payable_amount - paid_amount) > 0')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payment' => 'All payable payment obligations must be received or waived before confirmation.',
            ]);
        }
    }

    public function recordPayment(Collection|array $obligations,User $actor,string $scopeType,?int $scopeId=null,?string $paymentMethod=null,?string $reference=null,array $metadata=[]): MemberPayment
    {
        $obligations=collect($obligations)->map(fn($o)=>$o instanceof MemberPaymentObligation?$o:MemberPaymentObligation::findOrFail($o))->values();
        if($obligations->isEmpty()) throw ValidationException::withMessages(['obligation_ids'=>'At least one payment obligation is required.']);
        return DB::transaction(function() use($obligations,$actor,$scopeType,$scopeId,$paymentMethod,$reference,$metadata){
            $this->lockMutualSpouseObligations($obligations);
            $obligations=$obligations->map(fn($o)=>$o->refresh()->load('allocations'))->values();
            $orgId=$obligations->first()->organization_id; $memberId=$obligations->first()->member_id; $applicationId=$obligations->first()->membership_application_id; $membershipId=$obligations->first()->membership_id;
            foreach($obligations as $obligation){
                if($obligation->organization_id!==$orgId) throw ValidationException::withMessages(['payment'=>'All payment obligations must belong to the same organization.']);
                if($obligation->member_id!==$memberId) throw ValidationException::withMessages(['payment'=>'All payment obligations must belong to the same member.']);
                if($obligation->membership_application_id!==$applicationId) throw ValidationException::withMessages(['payment'=>'Application and membership payment obligations cannot be mixed in one payment.']);
                if(!$obligation->isPending() || (float)$obligation->payable_amount-(float)$obligation->paid_amount<=0) throw ValidationException::withMessages(['payment'=>'Every selected payment obligation must have an outstanding payable amount.']);
            }
            $this->validateCollectorScope($obligations->first(),$scopeType,$scopeId);
            $currency=$obligations->first()->currency;
            if($obligations->contains(fn($o)=>$o->currency!==$currency)) throw ValidationException::withMessages(['payment'=>'All selected obligations must use the same currency.']);
            $total=round($obligations->sum(fn($o)=>max(0,(float)$o->payable_amount-(float)$o->paid_amount)),2);
            $payment=MemberPayment::create(['organization_id'=>$orgId,'membership_application_id'=>$applicationId,'membership_id'=>$membershipId,'member_id'=>$memberId,'receipt_number'=>'MP-'.strtoupper(bin2hex(random_bytes(8))),'total_amount'=>$total,'currency'=>$currency,'payment_method'=>$paymentMethod,'reference'=>$reference,'collector_scope_type'=>$scopeType,'collector_scope_id'=>$scopeId,'received_at'=>now(),'received_by'=>$actor->id,'metadata'=>$metadata]);
            foreach($obligations as $obligation) {
                $this->settleObligation($payment,$obligation,round((float)$obligation->payable_amount-(float)$obligation->paid_amount,2));
                $this->coverActiveSpouseObligation($obligation);
            }
            MembershipPaymentReceived::dispatch($payment->refresh()->load(['lines.obligation.paymentItem','lines.allocations','member.user','application.user']));
            return $payment->refresh()->load('lines.obligation');
        });
    }

    private function settleObligation(MemberPayment $payment,MemberPaymentObligation $obligation,float $amount): void
    {
        $line=$payment->lines()->create(['member_payment_obligation_id'=>$obligation->id,'amount'=>$amount]);
        $allocations=$obligation->allocations->values(); $remaining=$amount;
        foreach($allocations as $index=>$allocation){
            $lineAmount=$index===$allocations->count()-1 ? round($remaining,2) : round($amount*((float)$allocation->percentage/100),2);
            $remaining=round($remaining-$lineAmount,2);
            $line->allocations()->create(['scope_type'=>$allocation->scope_type,'scope_id'=>$allocation->scope_id,'percentage'=>$allocation->percentage,'amount'=>$lineAmount]);
        }
        $paidTotal=round((float)$obligation->paid_amount+$amount,2);
        $payable=(float)$obligation->payable_amount;
        $status=$paidTotal+0.0001>=$payable?MemberPaymentObligationStatus::PAID:MemberPaymentObligationStatus::PARTIALLY_PAID;
        $obligation->update(['paid_amount'=>$paidTotal,'status'=>$status,'paid_at'=>$status===MemberPaymentObligationStatus::PAID?now():null]);
    }

    private function validateCollectorScope(MemberPaymentObligation $obligation,string $scopeType,?int $scopeId): void
    {
        if($scopeType==='organization'){ if($scopeId!==null && $scopeId!==$obligation->organization_id) throw ValidationException::withMessages(['scope_id'=>'The organization collector scope is invalid.']); return; }
        if($scopeType==='unit'){ if($scopeId===null) throw ValidationException::withMessages(['scope_id'=>'A unit collector scope is required.']); $unitId=$obligation->member?->unit_id ?? $obligation->application?->unitId(); if($unitId!==$scopeId) throw ValidationException::withMessages(['scope_id'=>'A unit collector can only collect payments for members or applications in that unit.']); return; }
        throw ValidationException::withMessages(['scope_type'=>'Collector scope must be organization or unit.']);
    }

    private function createObligationForApplication(MembershipApplication $application,MemberPaymentItem $item,Carbon $due,string $occurrence): MemberPaymentObligation
    {
        $rate=$this->rateFor($item,$due->toDateString()); if(!$rate) throw ValidationException::withMessages(['payment'=>"No rate is configured for payment item [{$item->slug}] on {$due->toDateString()}."]);
        $obligation = MemberPaymentObligation::firstOrNew([
            'membership_application_id' => $application->id,
            'member_payment_item_id' => $item->id,
            'occurrence_key' => $occurrence,
        ]);

        if (! $obligation->exists) {
            $obligation->fill([
                'organization_id' => $application->organization_id,
                'member_payment_item_rate_id' => $rate->id,
                'item_title' => $item->title,
                'amount' => $rate->amount,
                'discount_amount' => 0,
                'payable_amount' => $rate->amount,
                'paid_amount' => 0,
                'currency' => $rate->currency,
                'status' => MemberPaymentObligationStatus::PENDING,
                'due_at' => $due->toDateString(),
                'metadata' => ['rate_effective_from' => $rate->effective_from?->toDateString()],
            ])->save();
            $this->snapshotAllocations($obligation, $rate, $application->unitId());
        } elseif (
            $obligation->status === MemberPaymentObligationStatus::CANCELLED
            && data_get($obligation->metadata, 'invoice_sync_cancelled') === true
            && (float) $obligation->paid_amount === 0.0
            && ! $obligation->paymentLines()->exists()
        ) {
            // Reactivate a snapshot that was cancelled only because its category was
            // inactive/non-mandatory, if that category is enabled again.
            $obligation->update([
                'member_payment_item_rate_id' => $rate->id,
                'item_title' => $item->title,
                'amount' => $rate->amount,
                'discount_amount' => 0,
                'payable_amount' => $rate->amount,
                'paid_amount' => 0,
                'currency' => $rate->currency,
                'status' => MemberPaymentObligationStatus::PENDING,
                'due_at' => $due->toDateString(),
                'metadata' => ['rate_effective_from' => $rate->effective_from?->toDateString()],
            ]);
            $obligation->allocations()->delete();
            $this->snapshotAllocations($obligation, $rate, $application->unitId());
        } elseif (
            $obligation->isPending()
            && (float) $obligation->paid_amount === 0.0
            && ! $obligation->discounts()->exists()
            && ! $obligation->paymentLines()->exists()
        ) {
            // Refresh only uncollected, undiscounted obligations. Once a discount or
            // payment is recorded, its historical financial snapshot must not mutate.
            $obligation->update([
                'member_payment_item_rate_id' => $rate->id,
                'item_title' => $item->title,
                'amount' => $rate->amount,
                'discount_amount' => 0,
                'payable_amount' => $rate->amount,
                'currency' => $rate->currency,
                'due_at' => $due->toDateString(),
                'metadata' => array_merge($obligation->metadata ?? [], [
                    'rate_effective_from' => $rate->effective_from?->toDateString(),
                    'snapshot_refreshed_at' => now()->toISOString(),
                ]),
            ]);
            $obligation->allocations()->delete();
            $this->snapshotAllocations($obligation, $rate, $application->unitId());
        }

        $obligation = $obligation->refresh()->load(['allocations', 'paymentItem']);
        $this->coverApplicationObligationFromPaidSpouse($application, $obligation);

        return $obligation->refresh()->load('allocations');
    }

    private function createObligationForMember(Membership $membership,Member $member,MemberPaymentItem $item,Carbon $due): MemberPaymentObligation
    {
        $rate=$this->rateFor($item,$due->toDateString()); if(!$rate) throw ValidationException::withMessages(['payment'=>"No rate is configured for payment item [{$item->slug}] on {$due->toDateString()}."]);
        $occurrence=$this->occurrenceKey($item,$due);
        $obligation=MemberPaymentObligation::firstOrCreate(['member_id'=>$member->id,'member_payment_item_id'=>$item->id,'occurrence_key'=>$occurrence],['organization_id'=>$member->organization_id,'membership_id'=>$membership->id,'member_payment_item_rate_id'=>$rate->id,'item_title'=>$item->title,'amount'=>$rate->amount,'discount_amount'=>0,'payable_amount'=>$rate->amount,'paid_amount'=>0,'currency'=>$rate->currency,'status'=>MemberPaymentObligationStatus::PENDING,'due_at'=>$due->toDateString(),'metadata'=>['rate_effective_from'=>$rate->effective_from?->toDateString(),'unit_id'=>$member->unit_id]]);
        if($obligation->wasRecentlyCreated) $this->snapshotAllocations($obligation,$rate,$member->unit_id);
        $this->coverFromPreviouslyPaidSpouse($obligation);
        return $obligation->refresh()->load('allocations');
    }

    /**
     * Do not charge a new applicant for a mutual-spouse item when the linked spouse
     * has already settled the same occurrence, whether the spouse is an existing
     * member or is completing a linked membership application.
     */
    private function coverApplicationObligationFromPaidSpouse(
        MembershipApplication $application,
        MemberPaymentObligation $obligation,
    ): void {
        $obligation->loadMissing('paymentItem');

        if (
            $obligation->paymentItem?->family_payment_policy !== 'mutual_spouse'
            || ! $obligation->isPending()
            || (float) $obligation->paid_amount > 0
        ) {
            return;
        }

        $memberIds = collect();
        $applicationIds = collect();

        $selectedSpouseMemberId = data_get($application->data, 'spouse.member_id');
        if ($selectedSpouseMemberId !== null) {
            $memberIds->push((int) $selectedSpouseMemberId);
        }

        $sourceApplicationId = data_get($application->data, 'member.metadata.spouse_of_application_id');
        if ($sourceApplicationId !== null) {
            $sourceApplication = MembershipApplication::query()
                ->where('organization_id', $application->organization_id)
                ->whereKey((int) $sourceApplicationId)
                ->first();

            if ($sourceApplication) {
                $applicationIds->push((int) $sourceApplication->id);
                if ($sourceApplication->member_id !== null) {
                    $memberIds->push((int) $sourceApplication->member_id);
                }
            }
        }

        // If this is the original applicant and their spouse applied through the
        // linked-spouse flow, include that application as a possible paid household.
        $linkedSpouseApplications = MembershipApplication::query()
            ->where('organization_id', $application->organization_id)
            ->where('data->member->metadata->spouse_of_application_id', $application->id)
            ->get(['id', 'member_id']);

        foreach ($linkedSpouseApplications as $spouseApplication) {
            $applicationIds->push((int) $spouseApplication->id);
            if ($spouseApplication->member_id !== null) {
                $memberIds->push((int) $spouseApplication->member_id);
            }
        }

        if ($application->member_id !== null) {
            $member = Member::query()
                ->where('organization_id', $application->organization_id)
                ->find($application->member_id);
            if ($member) {
                $memberIds = $memberIds->merge($this->activeSpouseIds($member));
            }
        }

        $memberIds = $memberIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $applicationIds = $applicationIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($memberIds->isEmpty() && $applicationIds->isEmpty()) {
            return;
        }

        $paidSpouseObligation = MemberPaymentObligation::query()
            ->where('organization_id', $application->organization_id)
            ->where('member_payment_item_id', $obligation->member_payment_item_id)
            ->where('occurrence_key', $obligation->occurrence_key)
            ->where('status', MemberPaymentObligationStatus::PAID)
            ->whereColumn('paid_amount', '>=', 'payable_amount')
            ->where(function ($query) use ($memberIds, $applicationIds): void {
                if ($memberIds->isNotEmpty()) {
                    $query->whereIn('member_id', $memberIds);
                }
                if ($applicationIds->isNotEmpty()) {
                    $method = $memberIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('membership_application_id', $applicationIds);
                }
            })
            ->orderBy('paid_at')
            ->orderBy('id')
            ->first();

        if ($paidSpouseObligation) {
            $this->markCoveredByFamily($obligation, $paidSpouseObligation);
        }
    }

    /** Serialize payments for matching obligations belonging to a spouse pair. */
    private function lockMutualSpouseObligations(Collection $obligations): void
    {
        $ids = collect();
        foreach ($obligations as $obligation) {
            $obligation->loadMissing(['paymentItem', 'member']);
            $ids->push($obligation->id);
            if (!$obligation->member || $obligation->paymentItem?->family_payment_policy !== 'mutual_spouse') {
                continue;
            }
            $spouseIds = $this->activeSpouseIds($obligation->member);
            if ($spouseIds->isEmpty()) {
                continue;
            }
            $ids = $ids->merge(MemberPaymentObligation::query()
                ->where('organization_id', $obligation->organization_id)
                ->whereIn('member_id', $spouseIds->push($obligation->member_id)->unique())
                ->where('member_payment_item_id', $obligation->member_payment_item_id)
                ->where('occurrence_key', $obligation->occurrence_key)
                ->pluck('id'));
        }

        $lockIds = $ids->unique()->sort()->values();
        if ($lockIds->isNotEmpty()) {
            MemberPaymentObligation::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    /** Cover the other spouse's matching obligation after a confirmed payment. */
    private function coverActiveSpouseObligation(MemberPaymentObligation $paidObligation): void
    {
        $paidObligation->loadMissing(['paymentItem', 'member']);
        if (!$paidObligation->member || $paidObligation->paymentItem?->family_payment_policy !== 'mutual_spouse') {
            return;
        }

        $spouseIds = $this->activeSpouseIds($paidObligation->member);
        if ($spouseIds->isEmpty()) {
            return;
        }

        $covered = MemberPaymentObligation::query()
            ->where('organization_id', $paidObligation->organization_id)
            ->whereIn('member_id', $spouseIds)
            ->where('member_payment_item_id', $paidObligation->member_payment_item_id)
            ->where('occurrence_key', $paidObligation->occurrence_key)
            ->whereIn('status', [MemberPaymentObligationStatus::PENDING, MemberPaymentObligationStatus::PARTIALLY_PAID])
            ->where('paid_amount', 0)
            ->lockForUpdate()
            ->get();

        foreach ($covered as $obligation) {
            $this->markCoveredByFamily($obligation, $paidObligation);
        }
    }

    /** If the payer paid before the spouse's obligation was generated, cover it when created. */
    private function coverFromPreviouslyPaidSpouse(MemberPaymentObligation $obligation): void
    {
        $obligation->loadMissing(['paymentItem', 'member']);
        if (!$obligation->member || $obligation->paymentItem?->family_payment_policy !== 'mutual_spouse' || !$obligation->isPending()) {
            return;
        }

        $spouseIds = $this->activeSpouseIds($obligation->member);
        if ($spouseIds->isEmpty()) {
            return;
        }

        $paid = MemberPaymentObligation::query()
            ->where('organization_id', $obligation->organization_id)
            ->whereIn('member_id', $spouseIds)
            ->where('member_payment_item_id', $obligation->member_payment_item_id)
            ->where('occurrence_key', $obligation->occurrence_key)
            ->where('status', MemberPaymentObligationStatus::PAID)
            ->where('paid_amount', '>=', DB::raw('payable_amount'))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->first();

        if ($paid) {
            $this->markCoveredByFamily($obligation, $paid);
        }
    }

    private function activeSpouseIds(Member $member): Collection
    {
        $today = now()->toDateString();
        return MemberRelationship::query()
            ->where('relationship_type', 'spouse')
            ->whereNull('ended_at')
            ->where(function ($query) use ($member, $today): void {
                $query->whereNull('started_at')->orWhereDate('started_at', '<=', $today);
            })
            ->where(function ($query) use ($member): void {
                $query->where('member_id', $member->id)->orWhere('related_member_id', $member->id);
            })
            ->whereHas('member', fn ($query) => $query->where('organization_id', $member->organization_id))
            ->whereHas('relatedMember', fn ($query) => $query->where('organization_id', $member->organization_id))
            ->get(['member_id', 'related_member_id'])
            ->map(fn ($relationship) => (int) ($relationship->member_id === $member->id ? $relationship->related_member_id : $relationship->member_id))
            ->unique()
            ->values();
    }

    private function markCoveredByFamily(MemberPaymentObligation $obligation, MemberPaymentObligation $paidObligation): void
    {
        if (!$obligation->isPending() || (float) $obligation->paid_amount > 0) {
            return;
        }

        $metadata = $obligation->metadata ?? [];
        $metadata['family_coverage'] = [
            'covered_by_obligation_id' => $paidObligation->id,
            'payer_member_id' => $paidObligation->member_id,
            'payer_application_id' => $paidObligation->membership_application_id,
            'covered_at' => now()->toISOString(),
            'policy' => 'mutual_spouse',
        ];

        $obligation->update([
            'status' => MemberPaymentObligationStatus::COVERED_BY_FAMILY,
            'metadata' => $metadata,
        ]);
    }

    private function snapshotAllocations(MemberPaymentObligation $obligation,$rate,?int $unitId): void
    {
        $remaining=(float)$rate->amount; $allocs=$rate->allocations->values();
        foreach($allocs as $index=>$config){
            $amount=$index===$allocs->count()-1?round($remaining,2):round((float)$rate->amount*((float)$config->percentage/100),2); $remaining=round($remaining-$amount,2);
            $scopeId=$config->scope_type==='organization'?$obligation->organization_id:$unitId;
            if($config->scope_type==='unit' && $scopeId===null) throw ValidationException::withMessages(['payment'=>'A unit allocation requires the member to belong to a unit.']);
            $obligation->allocations()->create(['scope_type'=>$config->scope_type,'scope_id'=>$scopeId,'percentage'=>$config->percentage,'original_amount'=>$amount,'payable_amount'=>$amount]);
        }
    }

    /**
     * Initial application invoices include every active mandatory payment item
     * configured for this organization, regardless of membership_type_id.
     * Membership-type filtering remains in place for recurring obligations after
     * membership is active.
     */
    private function mandatoryItemsForApplicationInvoice(int $organizationId): Collection
    {
        return MemberPaymentItem::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->with(['rates.allocations'])
            ->orderBy('id')
            ->get();
    }

    private function itemsForMembershipType(int $organizationId,int $membershipTypeId,bool $mandatoryOnly): Collection
    {
        $q=MemberPaymentItem::query()->where('organization_id',$organizationId)->where('is_active',true)->where(function($q)use($membershipTypeId){$q->whereNull('membership_type_id')->orWhere('membership_type_id',$membershipTypeId);}); if($mandatoryOnly)$q->where('is_mandatory',true); return $q->with(['rates.allocations'])->orderBy('id')->get();
    }

    private function applicationDueDate(MemberPaymentItem $item,Carbon $today): Carbon
    {
        if(!$item->is_recurring) return $today->copy();
        if($item->recurring_by==='join_date') return $today->copy();
        return $this->nextSpecificDate($item,$today);
    }

    private function dueDates(MemberPaymentItem $item,Carbon $start,Carbon $asOf): Collection
    {
        if(!$item->is_recurring) return $start->lte($asOf)?collect([$start->copy()]):collect();
        $dates=collect(); $cursor=$item->recurring_by==='join_date'?$start->copy():$this->firstSpecificDate($item,$start);
        $period=$item->recurring_period;
        while($cursor->lte($asOf)){ if($cursor->gte($start))$dates->push($cursor->copy()); $cursor=$this->advance($cursor,$period); }
        return $dates;
    }
    private function advance(Carbon $date,string $period): Carbon { return match($period){ 'monthly'=>$date->copy()->addMonthNoOverflow(), 'quarterly'=>$date->copy()->addMonthsNoOverflow(3), 'half_yearly'=>$date->copy()->addMonthsNoOverflow(6), 'yearly'=>$date->copy()->addYearNoOverflow(), default=>throw ValidationException::withMessages(['recurring_period'=>'Unsupported recurring period.'])}; }
    private function firstSpecificDate(MemberPaymentItem $item,Carbon $start): Carbon { $d=$this->specificDateInYear($item,$start->year); if($d->lt($start))$d=$this->specificDateInYear($item,$start->year+1); return $d; }
    private function nextSpecificDate(MemberPaymentItem $item,Carbon $today): Carbon { return $this->firstSpecificDate($item,$today); }
    private function specificDateInYear(MemberPaymentItem $item,int $year): Carbon { [$m,$d]=array_map('intval',explode('-',$item->recurring_date)); return Carbon::create($year,$m,min($d,Carbon::create($year,$m,1)->daysInMonth),0,0,0); }
    private function occurrenceKey(MemberPaymentItem $item,Carbon $due): string { return $item->is_recurring?strtolower((string)$item->recurring_period).':'.$due->format('Y-m-d'):'one-time'; }
    private function rateFor(MemberPaymentItem $item,string $date){ return $item->rates()->with('allocations')->whereDate('effective_from','<=',$date)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$date))->orderByDesc('effective_from')->first(); }
}
