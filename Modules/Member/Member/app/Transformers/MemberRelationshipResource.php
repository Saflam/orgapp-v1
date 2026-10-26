<?php

namespace Modules\Member\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'member_id' => $this->member_id,
            'related_member_id' => $this->related_member_id,

            'relationship_type' => $this->relationship_type,

            'started_at' => $this->started_at?->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),

            'metadata' => $this->metadata,

            'member' => $this->whenLoaded(
                'member',
                fn () => [
                    'id' => $this->member->id,
                    'membership_number' => $this->member->membership_number,
                    'full_name' => $this->member->full_name,
                ]
            ),

            'related_member' => $this->whenLoaded(
                'relatedMember',
                fn () => [
                    'id' => $this->relatedMember->id,
                    'membership_number' => $this->relatedMember->membership_number,
                    'full_name' => $this->relatedMember->full_name,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}