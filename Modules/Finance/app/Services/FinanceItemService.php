<?php

namespace Modules\Finance\Services;

use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceItemSource;
use Modules\Finance\Models\FinanceItem;

class FinanceItemService
{
    public function resolveModuleItem(Organization $organization, array $definition): FinanceItem
    {
        return FinanceItem::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'source' => FinanceItemSource::MODULE,
                'source_type' => $definition['source_type'],
                'source_id' => $definition['source_id'],
            ],
            [
                'sku' => $definition['sku'],
                'title' => $definition['title'],
                'unit' => $definition['unit'] ?? 'unit',
                'selling_price' => $definition['selling_price'],
                'currency' => $definition['currency'] ?? $organization->currency,
                'sales_account_id' => $definition['sales_account_id'],
                'is_system_managed' => true,
                'is_active' => $definition['is_active'] ?? true,
                'metadata' => $definition['metadata'] ?? [],
            ]
        );
    }
}
