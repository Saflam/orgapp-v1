<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTaxGroup;
use Modules\Finance\Models\FinanceTaxRate;

class FinanceTaxConfigurationService
{
    public function createRate(Organization $organization, array $data): FinanceTaxRate
    {
        $validated = $this->validateRate($organization, $data);
        if (FinanceTaxRate::query()->where('organization_id', $organization->id)->where('code', $validated['code'])->exists()) {
            throw new InvalidArgumentException('A tax rate with this code already exists.');
        }

        return FinanceTaxRate::create(['organization_id' => $organization->id] + $validated + ['is_active' => true, 'metadata' => []]);
    }

    public function updateRate(Organization $organization, FinanceTaxRate $rate, array $data): FinanceTaxRate
    {
        $this->assertRateOrganization($organization, $rate);
        $validated = $this->validateRate($organization, $data);
        foreach ($rate->groups()->where('finance_tax_groups.is_active', true)->get() as $group) {
            if (! $this->isApplicable($validated['applies_to'], $group->applies_to)) {
                throw new InvalidArgumentException('This rate is used by an active tax group and cannot be changed to an incompatible applicability.');
            }
        }
        $duplicate = FinanceTaxRate::query()
            ->where('organization_id', $organization->id)
            ->where('code', $validated['code'])
            ->whereKeyNot($rate->id)
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException('A tax rate with this code already exists.');
        }
        $rate->update($validated);
        return $rate->refresh();
    }

    public function deactivateRate(Organization $organization, FinanceTaxRate $rate): void
    {
        $this->assertRateOrganization($organization, $rate);
        if ($rate->groups()->where('finance_tax_groups.is_active', true)->exists()) {
            throw new InvalidArgumentException('Remove this rate from active tax groups before deactivating it.');
        }
        $rate->update(['is_active' => false]);
    }

    public function createGroup(Organization $organization, array $data): FinanceTaxGroup
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $appliesTo = (string) ($data['applies_to'] ?? '');
        $rateIds = array_values(array_unique(array_map('intval', $data['rate_ids'] ?? [])));
        if ($code === '' || $name === '' || ! in_array($appliesTo, ['sales', 'purchases', 'both'], true)) {
            throw new InvalidArgumentException('Enter a group code, name and valid applicability.');
        }
        if (FinanceTaxGroup::query()->where('organization_id', $organization->id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('A tax group with this code already exists.');
        }
        if ($rateIds === []) {
            throw new InvalidArgumentException('Select at least one active tax rate for this group.');
        }

        $rates = FinanceTaxRate::query()->where('organization_id', $organization->id)->whereIn('id', $rateIds)->where('is_active', true)->get();
        if ($rates->count() !== count($rateIds)) {
            throw new InvalidArgumentException('Every selected tax rate must be active and belong to this organization.');
        }
        foreach ($rates as $rate) {
            if (! $this->isApplicable($rate->applies_to, $appliesTo)) {
                throw new InvalidArgumentException("Tax rate {$rate->code} is not applicable to this group.");
            }
        }

        return DB::transaction(function () use ($organization, $code, $name, $appliesTo, $rateIds): FinanceTaxGroup {
            $group = FinanceTaxGroup::create([
                'organization_id' => $organization->id,
                'code' => $code,
                'name' => $name,
                'applies_to' => $appliesTo,
                'is_active' => true,
            ]);
            $group->rates()->sync(collect($rateIds)->values()->mapWithKeys(fn ($id, $position) => [$id => ['position' => $position]])->all());
            return $group->load('rates');
        });
    }

    public function deactivateGroup(Organization $organization, FinanceTaxGroup $group): void
    {
        if ((int) $group->organization_id !== (int) $organization->id) {
            throw new InvalidArgumentException('The tax group does not belong to this organization.');
        }
        $group->update(['is_active' => false]);
    }

    private function validateRate(Organization $organization, array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $rate = filter_var($data['rate'] ?? null, FILTER_VALIDATE_FLOAT);
        $appliesTo = (string) ($data['applies_to'] ?? '');
        if ($code === '' || $name === '' || $rate === false || $rate < 0 || $rate > 100 || ! in_array($appliesTo, ['sales', 'purchases', 'both'], true)) {
            throw new InvalidArgumentException('Enter a valid code, name, rate from 0 to 100, and applicability.');
        }

        $salesAccountId = $data['sales_account_id'] ?? null;
        $purchaseAccountId = $data['purchase_account_id'] ?? null;
        if (in_array($appliesTo, ['sales', 'both'], true)) {
            $this->assertMappedAccount($organization, $salesAccountId, 'liability', 'sales');
        } else {
            $salesAccountId = null;
        }
        if (in_array($appliesTo, ['purchases', 'both'], true)) {
            $this->assertMappedAccount($organization, $purchaseAccountId, 'asset', 'purchase');
        } else {
            $purchaseAccountId = null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'rate' => number_format((float) $rate, 4, '.', ''),
            'applies_to' => $appliesTo,
            'sales_account_id' => $salesAccountId ?: null,
            'purchase_account_id' => $purchaseAccountId ?: null,
        ];
    }

    private function assertMappedAccount(Organization $organization, mixed $accountId, string $type, string $label): void
    {
        if (! $accountId) {
            throw new InvalidArgumentException("Select an organization-wide {$label} tax account.");
        }
        $account = FinanceAccount::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where('account_type', $type)
            ->whereHas('scope', fn ($query) => $query
                ->where('organization_id', $organization->id)
                ->where('scope_type', 'organization'))
            ->find($accountId);
        if (! $account) {
            throw new InvalidArgumentException("The {$label} tax account must be an active {$type} account in this organization's chart.");
        }
    }

    private function assertRateOrganization(Organization $organization, FinanceTaxRate $rate): void
    {
        if ((int) $rate->organization_id !== (int) $organization->id) {
            throw new InvalidArgumentException('The tax rate does not belong to this organization.');
        }
    }

    private function isApplicable(string $rateApplicability, string $groupApplicability): bool
    {
        if ($groupApplicability === 'both') {
            return $rateApplicability === 'both';
        }
        return $rateApplicability === 'both' || $rateApplicability === $groupApplicability;
    }
}
