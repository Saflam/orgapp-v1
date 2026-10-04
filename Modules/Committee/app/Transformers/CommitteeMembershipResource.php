<?php

namespace Modules\Committee\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'committee_term_id' => $this->committee_term_id,
            'member_id' => $this->member_id,
            'designation_id' => $this->designation_id,
            'status' => $this->status?->value,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member->id,
                ];
            }),
            'designation' => $this->whenLoaded('designation', function () {
                return [
                    'id' => $this->designation->id,
                    'name' => $this->designation->name,
                    'code' => $this->designation->code,
                ];
            }),
        ];
    }
}