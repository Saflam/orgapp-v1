<?php

namespace Modules\Member\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberPaymentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'member_id' => $this->member_id,
            'membership_application_id' => $this->membership_application_id,
            'membership_id' => $this->membership_id,
            'receipt_number' => $this->receipt_number,
            'total_amount' => (string) $this->total_amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'collector_scope' => [
                'type' => $this->collector_scope_type,
                'id' => $this->collector_scope_id,
            ],
            'received_at' => $this->received_at?->toISOString(),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ] : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'obligation_id' => $line->member_payment_obligation_id,
                'item_title' => $line->obligation?->item_title,
                'occurrence_key' => $line->obligation?->occurrence_key,
                'due_at' => $line->obligation?->due_at?->toDateString(),
                'amount' => (string) $line->amount,
                'obligation_status' => $line->obligation?->status?->value,
                'allocations' => $line->relationLoaded('allocations')
                    ? $line->allocations->map(fn ($allocation) => [
                        'scope_type' => $allocation->scope_type,
                        'scope_id' => $allocation->scope_id,
                        'percentage' => (string) $allocation->percentage,
                        'amount' => (string) $allocation->amount,
                    ])->values()->all()
                    : [],
            ])->values()->all()),
        ];
    }
}
