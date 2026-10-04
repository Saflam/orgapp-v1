<?php

namespace Modules\Member\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,

            'organization_id' => $this->organization_id,
            'unit_id' => $this->unit_id,
            'membership_number' => $this->membership_number,

            'date_of_birth' => $this->user?->details?->date_of_birth?->toDateString(),

            'joined_at' => $this->joined_at?->toDateString(),
            'metadata' => $this->metadata,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}