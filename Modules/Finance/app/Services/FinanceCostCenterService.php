<?php

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Committee\Models\Committee;
use Modules\Finance\Models\FinanceCostCenter;

class FinanceCostCenterService
{
    public function central(Organization $organization): FinanceCostCenter
    {
        return FinanceCostCenter::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'owner_type' => 'organization',
                'owner_id' => $organization->id,
            ],
            [
                'name' => 'Central / Organization',
                'code' => 'CENTRAL',
                'is_active' => true,
            ],
        );
    }

    public function resolve(Organization $organization, string $type, ?int $id): FinanceCostCenter
    {
        if ($type === 'organization') {
            if ($id !== null && $id !== (int) $organization->id) {
                throw new \InvalidArgumentException('The central cost center must belong to this organization.');
            }

            return $this->central($organization);
        }

        $owner = match ($type) {
            'unit' => Unit::query()->where('organization_id', $organization->id)->find($id),
            'committee' => Committee::query()->where('organization_id', $organization->id)->find($id),
            default => null,
        };

        if (! $owner instanceof Model) {
            throw new \InvalidArgumentException('The financial cost center owner is invalid or belongs to another organization.');
        }

        $name = $type === 'unit'
            ? 'Unit: ' . $owner->name
            : 'Committee: ' . $owner->name;

        $code = strtoupper($type) . '-' . $owner->getKey();

        return FinanceCostCenter::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'owner_type' => $type,
                'owner_id' => $owner->getKey(),
            ],
            ['name' => $name, 'code' => $code, 'is_active' => true],
        );
    }
}
