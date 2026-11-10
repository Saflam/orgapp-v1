<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Enums\FinanceNormalBalance;
use Modules\Finance\Enums\FinanceScopeType;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournalLine;
use Modules\Finance\Models\FinanceScope;
use Modules\Finance\Models\FinanceTaxRate;

class FinanceChartOfAccountsService
{
    public function createManual(Organization $organization, FinanceScope $scope, array $data): FinanceAccount
    {
        $this->assertOrganizationScope($organization, $scope);
        $type = $this->accountType($data['account_type'] ?? null);
        $this->assertParent($organization, $scope, $data['parent_id'] ?? null, $type);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException('An account code is required.');
        }
        if (FinanceAccount::query()->where('organization_id', $organization->id)->where('finance_scope_id', $scope->id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('An account with this code already exists.');
        }

        return FinanceAccount::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $code,
            'name' => trim((string) $data['name']),
            'account_type' => $type,
            'normal_balance' => $this->normalBalance($type),
            'is_system_managed' => false,
            'source_type' => null,
            'source_id' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
    }

    public function updateManual(Organization $organization, FinanceScope $scope, FinanceAccount $account, array $data): FinanceAccount
    {
        $this->assertOrganizationScope($organization, $scope);
        $this->assertAccountBelongsToChart($organization, $scope, $account);
        if ($account->is_system_managed) {
            throw new InvalidArgumentException('System-managed accounts cannot be edited here.');
        }

        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $account->parent_id;
        $this->assertParent($organization, $scope, $parentId, $account->account_type, $account);
        $code = strtoupper(trim((string) ($data['code'] ?? $account->code)));
        $duplicate = FinanceAccount::query()
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('code', $code)
            ->whereKeyNot($account->id)
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException('An account with this code already exists.');
        }

        $account->update([
            'parent_id' => $parentId,
            'code' => $code,
            'name' => trim((string) ($data['name'] ?? $account->name)),
        ]);

        return $account->refresh();
    }

    public function deactivate(Organization $organization, FinanceScope $scope, FinanceAccount $account): void
    {
        DB::transaction(function () use ($organization, $scope, $account): void {
            $this->assertOrganizationScope($organization, $scope);

            // Match journal posting's account-row lock so an account cannot be
            // deactivated while a journal is being posted against it.
            $lockedAccount = FinanceAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAccountBelongsToChart($organization, $scope, $lockedAccount);

            if ($lockedAccount->is_system_managed) {
                throw new InvalidArgumentException('System-managed accounts cannot be deactivated here.');
            }
            if ($lockedAccount->children()->where('is_active', true)->exists()) {
                throw new InvalidArgumentException('Move or deactivate active child accounts before deactivating this account.');
            }
            if (FinanceJournalLine::query()->where('finance_account_id', $lockedAccount->id)->exists()) {
                throw new InvalidArgumentException('An account used by a journal cannot be deactivated.');
            }
            if (FinanceTaxRate::query()
                ->where('is_active', true)
                ->where(function ($query) use ($lockedAccount): void {
                    $query->where('sales_account_id', $lockedAccount->id)
                        ->orWhere('purchase_account_id', $lockedAccount->id);
                })
                ->exists()) {
                throw new InvalidArgumentException('An account mapped to an active tax rate cannot be deactivated.');
            }

            $lockedAccount->update(['is_active' => false]);
        });
    }

    private function assertParent(Organization $organization, FinanceScope $scope, ?int $parentId, FinanceAccountType $type, ?FinanceAccount $account = null): void
    {
        if ($parentId === null) {
            return;
        }
        if ($account && (int) $parentId === (int) $account->id) {
            throw new InvalidArgumentException('An account cannot be its own parent.');
        }

        $parent = FinanceAccount::query()
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->whereKey($parentId)
            ->first();
        if (! $parent) {
            throw new InvalidArgumentException('The parent account must belong to this organization chart.');
        }
        if (! $parent->is_active) {
            throw new InvalidArgumentException('An inactive account cannot be used as a parent.');
        }
        if ($parent->account_type !== $type) {
            throw new InvalidArgumentException('Parent and child accounts must have the same account type.');
        }

        if ($account && $this->isDescendantOf($parent, $account->id)) {
            throw new InvalidArgumentException('An account cannot be moved beneath one of its descendants.');
        }
    }

    private function isDescendantOf(FinanceAccount $candidate, int $ancestorId): bool
    {
        $visited = [];
        while ($candidate->parent_id !== null) {
            if ((int) $candidate->parent_id === $ancestorId) {
                return true;
            }
            if (in_array((int) $candidate->parent_id, $visited, true)) {
                return true;
            }
            $visited[] = (int) $candidate->parent_id;
            $candidate = FinanceAccount::query()->find($candidate->parent_id);
            if (! $candidate) {
                return false;
            }
        }
        return false;
    }

    private function accountType(mixed $value): FinanceAccountType
    {
        if ($value instanceof FinanceAccountType) {
            return $value;
        }
        return FinanceAccountType::tryFrom((string) $value)
            ?? throw new InvalidArgumentException('Choose a valid account type.');
    }

    private function normalBalance(FinanceAccountType $type): FinanceNormalBalance
    {
        return in_array($type, [FinanceAccountType::ASSET, FinanceAccountType::EXPENSE], true)
            ? FinanceNormalBalance::DEBIT
            : FinanceNormalBalance::CREDIT;
    }

    private function assertOrganizationScope(Organization $organization, FinanceScope $scope): void
    {
        if ((int) $scope->organization_id !== (int) $organization->id || $scope->scope_type !== FinanceScopeType::ORGANIZATION) {
            throw new InvalidArgumentException('The chart of accounts must use this organization\'s organization-wide finance scope.');
        }
    }

    private function assertAccountBelongsToChart(Organization $organization, FinanceScope $scope, FinanceAccount $account): void
    {
        if ((int) $account->organization_id !== (int) $organization->id || (int) $account->finance_scope_id !== (int) $scope->id) {
            throw new InvalidArgumentException('The account does not belong to this organization chart.');
        }
    }
}
