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
            'membership_number' => $this->membership_number,
            'unit_id' => $this->unit_id,
            'joined_at' => $this->joined_at?->toDateString(),
            'membership' => $this->whenLoaded(
                'memberships',
                fn () => $this->memberships->map(
                    fn ($membership): array => [
                        'id' => $membership->id,
                        'membership_type_id' =>
                            $membership->membership_type_id,
                        'starts_at' =>
                            $membership->starts_at?->toDateString(),
                        'ends_at' =>
                            $membership->ends_at?->toDateString(),
                        'status' => $membership->status->value,
                    ]
                )->values()->all()
            ),
        ];
    }
}