<?php

namespace Modules\Member\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'membership_type_id' => $this->membership_type_id,
            'member_id' => $this->member_id,
            'status' => $this->status->value,
            'current_step' => $this->current_step,
            'completed_steps' => $this->completed_steps ?? [],
            'data' => $this->data ?? [],
            'submitted_at' => $this->submitted_at?->toISOString(),
            'membership_type' => $this->whenLoaded(
                'membershipType',
                fn () => [
                    'id' => $this->membershipType->id,
                    'name' => $this->membershipType->name,
                    'code' => $this->membershipType->code,
                ]
            ),
            'history' => $this->whenLoaded(
                'statusHistories',
                fn () => $this->statusHistories->map(
                    fn ($history): array => [
                        'id' => $history->id,
                        'from_status' => $history->from_status?->value,
                        'to_status' => $history->to_status->value,
                        'actor_user_id' => $history->actor_user_id,
                        'notes' => $history->notes,
                        'allow_reapply' => $history->allow_reapply,
                        'created_at' => $history->created_at?->toISOString(),
                    ]
                )->values()->all()
            ),
        ];
    }
}
