<?php

namespace Modules\Finance\Livewire;

use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use App\Support\Organization\CurrentOrganization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTaxGroup;
use Modules\Finance\Models\FinanceTaxRate;
use Modules\Finance\Services\FinanceTaxConfigurationService;

class FinanceTaxConfigurationManagement extends Component
{
    public Organization $organization;
    public ?int $editingRateId = null;
    public string $code = '';
    public string $name = '';
    public string $rate = '0.0000';
    public string $appliesTo = 'both';
    public ?int $salesAccountId = null;
    public ?int $purchaseAccountId = null;

    public string $groupCode = '';
    public string $groupName = '';
    public string $groupAppliesTo = 'both';
    public array $groupRateIds = [];
    public string $successMessage = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->ensureCanView($authorization);
    }

    public function updatedAppliesTo(): void
    {
        if ($this->appliesTo === 'sales') {
            $this->purchaseAccountId = null;
        } elseif ($this->appliesTo === 'purchases') {
            $this->salesAccountId = null;
        }
    }

    public function saveRate(FinanceTaxConfigurationService $taxes, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'appliesTo' => ['required', 'in:sales,purchases,both'],
            'salesAccountId' => ['nullable', 'integer'],
            'purchaseAccountId' => ['nullable', 'integer'],
        ]);

        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'applies_to' => $validated['appliesTo'],
            'sales_account_id' => $validated['salesAccountId'],
            'purchase_account_id' => $validated['purchaseAccountId'],
        ];

        try {
            if ($this->editingRateId) {
                $taxes->updateRate(
                    $this->organization,
                    FinanceTaxRate::query()->where('organization_id', $this->organization->id)->findOrFail($this->editingRateId),
                    $data,
                );
                $this->successMessage = 'Tax rate updated.';
            } else {
                $taxes->createRate($this->organization, $data);
                $this->successMessage = 'Tax rate created.';
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tax' => $exception->getMessage()]);
        }

        $this->resetRateForm();
    }

    public function editRate(int $rateId, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $taxRate = FinanceTaxRate::query()->where('organization_id', $this->organization->id)->findOrFail($rateId);
        $this->editingRateId = $taxRate->id;
        $this->code = $taxRate->code;
        $this->name = $taxRate->name;
        $this->rate = (string) $taxRate->rate;
        $this->appliesTo = $taxRate->applies_to;
        $this->salesAccountId = $taxRate->sales_account_id;
        $this->purchaseAccountId = $taxRate->purchase_account_id;
        $this->resetValidation();
    }

    public function deactivateRate(int $rateId, FinanceTaxConfigurationService $taxes, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        try {
            $taxes->deactivateRate(
                $this->organization,
                FinanceTaxRate::query()->where('organization_id', $this->organization->id)->findOrFail($rateId),
            );
            $this->successMessage = 'Tax rate deactivated.';
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tax' => $exception->getMessage()]);
        }
    }

    public function createGroup(FinanceTaxConfigurationService $taxes, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $validated = $this->validate([
            'groupCode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'groupName' => ['required', 'string', 'max:150'],
            'groupAppliesTo' => ['required', 'in:sales,purchases,both'],
            'groupRateIds' => ['required', 'array', 'min:1'],
            'groupRateIds.*' => ['integer'],
        ]);

        try {
            $taxes->createGroup($this->organization, [
                'code' => $validated['groupCode'],
                'name' => $validated['groupName'],
                'applies_to' => $validated['groupAppliesTo'],
                'rate_ids' => $validated['groupRateIds'],
            ]);
            $this->groupCode = '';
            $this->groupName = '';
            $this->groupRateIds = [];
            $this->successMessage = 'Tax group created.';
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['group' => $exception->getMessage()]);
        }
    }

    public function deactivateGroup(int $groupId, FinanceTaxConfigurationService $taxes, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $group = FinanceTaxGroup::query()->where('organization_id', $this->organization->id)->findOrFail($groupId);
        $taxes->deactivateGroup($this->organization, $group);
        $this->successMessage = 'Tax group deactivated.';
    }

    public function resetRateForm(): void
    {
        $this->editingRateId = null;
        $this->code = '';
        $this->name = '';
        $this->rate = '0.0000';
        $this->appliesTo = 'both';
        $this->salesAccountId = null;
        $this->purchaseAccountId = null;
        $this->resetValidation();
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCanView($authorization);
        $accounts = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('is_active', true)
            ->whereHas('scope', fn ($query) => $query->where('scope_type', 'organization'))
            ->orderBy('code')
            ->get();
        $rates = FinanceTaxRate::query()
            ->where('organization_id', $this->organization->id)
            ->with(['salesAccount', 'purchaseAccount', 'groups'])
            ->orderBy('code')
            ->get();
        $groups = FinanceTaxGroup::query()
            ->where('organization_id', $this->organization->id)
            ->with('rates')
            ->orderBy('code')
            ->get();

        return view('finance::livewire.taxes.manage', [
            'accounts' => $accounts,
            'salesAccounts' => $accounts->filter(fn ($account) => $account->account_type->value === 'liability'),
            'purchaseAccounts' => $accounts->filter(fn ($account) => $account->account_type->value === 'asset'),
            'rates' => $rates,
            'groups' => $groups,
            'availableRates' => $rates->where('is_active', true),
            'canManage' => $this->can($authorization, 'finance.tax.manage'),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Tax Configuration',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.tax.view') || $this->can($authorization, 'finance.tax.manage'), 403);
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.tax.manage'), 403);
    }

    private function can(AuthorizationService $authorization, string $permission): bool
    {
        $user = auth()->user();
        return $user !== null && $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $this->organization->id),
        );
    }
}
