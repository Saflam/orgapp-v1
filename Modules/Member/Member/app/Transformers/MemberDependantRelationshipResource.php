<?php

namespace Modules\Member\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDependantRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'dependant_id' => $this->dependant_id,
            'relationship_type' => $this->relationship_type,
            'started_at' => $this->started_at?->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
        ];
    }
}