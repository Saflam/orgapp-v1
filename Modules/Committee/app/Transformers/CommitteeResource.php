<?php

namespace Modules\Committee\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'committee_type_id' => $this->committee_type_id,
            'unit_id' => $this->unit_id,
            'parent_committee_id' => $this->parent_committee_id,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}