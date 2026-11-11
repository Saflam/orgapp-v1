<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceSupplier;

class FinanceSupplierService
{
    public function create(Organization $organization, array $data): FinanceSupplier
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('A supplier name is required.');
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            do { $code = 'SUP-' . Str::upper(Str::random(8)); } while (FinanceSupplier::query()->where('organization_id', $organization->id)->where('code', $code)->exists());
        }
        if (FinanceSupplier::query()->where('organization_id', $organization->id)->where('code', $code)->exists()) {
            throw new \InvalidArgumentException('A supplier with this code already exists.');
        }

        return FinanceSupplier::create([
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => $name,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'payment_terms_days' => min(365, max(0, (int) ($data['payment_terms_days'] ?? 0))),
            'is_active' => true,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
