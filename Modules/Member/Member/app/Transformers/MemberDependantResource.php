<?php

namespace Modules\Member\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDependantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,

            'full_name' => trim(implode(' ', array_filter([
                $this->first_name,
                $this->middle_name,
                $this->last_name,
            ]))),

            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,

            'converted_member_id' => $this->converted_member_id,
            'converted_at' => $this->converted_at?->toDateString(),

            'metadata' => $this->metadata,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}