<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Enums\FinanceNormalBalance;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceScope;

class FinanceAccountProvisioningService
{
    public function ensure(
        Organization $organization,
        FinanceScope $scope,
        array $definition,
    ): FinanceAccount {
        if ($scope->organization_id !== $organization->id) {
            throw new \InvalidArgumentException('The finance scope does not belong to the organization.');
        }

        $type = $definition['account_type'] instanceof FinanceAccountType
            ? $definition['account_type']
            : FinanceAccountType::from($definition['account_type']);
        $balance = $definition['normal_balance'] instanceof FinanceNormalBalance
            ? $definition['normal_balance']
            : FinanceNormalBalance::from($definition['normal_balance']);

        return FinanceAccount::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'finance_scope_id' => $scope->id,
                'code' => $definition['code'],
            ],
            [
                'name' => $definition['name'],
                'account_type' => $type,
                'normal_balance' => $balance,
                'is_system_managed' => $definition['is_system_managed'] ?? true,
                'source_type' => $definition['source_type'] ?? null,
                'source_id' => $definition['source_id'] ?? null,
                'is_active' => $definition['is_active'] ?? true,
                'metadata' => $definition['metadata'] ?? [],
            ]
        );
    }

    public function provisionDefaults(Organization $organization, FinanceScope $scope): void
    {
        DB::transaction(function () use ($organization, $scope): void {
            $defaults = [
                ['code' => '1000', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '1100', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '1200', 'name' => 'Accounts Receivable', 'account_type' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit'],
                ['code' => '3000', 'name' => 'Equity', 'account_type' => 'equity', 'normal_balance' => 'credit'],
                ['code' => '4000', 'name' => 'Sales Income', 'account_type' => 'income', 'normal_balance' => 'credit'],
                ['code' => '5000', 'name' => 'General Expense', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ];

            foreach ($defaults as $definition) {
                $this->ensure($organization, $scope, $definition);
            }
        });
    }
}
