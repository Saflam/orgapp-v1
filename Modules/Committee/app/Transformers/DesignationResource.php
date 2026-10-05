<?php

namespace Modules\Committee\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'code' => $this->code,
            'scope' => $this->scope?->value,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->map(fn ($permission) => [
                    'id' => $permission->id,
                    'module' => $permission->module,
                    'name' => $permission->name,
                    'code' => $permission->code,
                    'description' => $permission->description,
                ])
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}