<?php

namespace Modules\Finance\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use App\Support\Organization\CurrentOrganization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinanceScopeService;
use InvalidArgumentException;

class FinanceChartOfAccountsManagement extends Component
{
    public Organization $organization;
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $accountType = 'asset';
    public ?int $parentId = null;
    public string $successMessage = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->ensureCanView($authorization);
    }

    public function updatedAccountType(): void
    {
        $this->parentId = null;
    }

    public function edit(int $accountId, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $account = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->whereHas('scope', fn ($query) => $query->where('scope_type', 'organization'))
            ->findOrFail($accountId);
        if ($account->is_system_managed) {
            throw ValidationException::withMessages(['account' => 'System-managed accounts cannot be edited here.']);
        }
        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->accountType = $account->account_type->value;
        $this->parentId = $account->parent_id;
        $this->resetValidation();
    }

    public function save(FinanceChartOfAccountsService $chart, FinanceScopeService $scopes, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'accountType' => ['required', Rule::in(array_column(FinanceAccountType::cases(), 'value'))],
            'parentId' => ['nullable', 'integer'],
        ]);

        $scope = $scopes->organizationScope($this->organization);
        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'account_type' => $validated['accountType'],
            'parent_id' => $validated['parentId'],
        ];

        try {
            if ($this->editingId === null) {
                $chart->createManual($this->organization, $scope, $data);
                $this->successMessage = 'Account created successfully.';
            } else {
                $account = FinanceAccount::query()
                    ->where('organization_id', $this->organization->id)
                    ->where('finance_scope_id', $scope->id)
                    ->findOrFail($this->editingId);
                $chart->updateManual($this->organization, $scope, $account, $data);
                $this->successMessage = 'Account updated successfully.';
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['account' => $exception->getMessage()]);
        }

        $this->resetForm();
    }

    public function deactivate(int $accountId, FinanceChartOfAccountsService $chart, FinanceScopeService $scopes, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $account = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($accountId);
        try {
            $chart->deactivate($this->organization, $scope, $account);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['account' => $exception->getMessage()]);
        }
        $this->successMessage = 'Account deactivated. Historical journal records remain unchanged.';
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->accountType = 'asset';
        $this->parentId = null;
        $this->resetValidation();
    }

    public function render(AuthorizationService $authorization, FinanceScopeService $scopes): View
    {
        $this->ensureCanView($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $accounts = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->with('parent')
            ->withCount('children')
            ->orderByRaw("FIELD(account_type, 'asset', 'liability', 'equity', 'income', 'expense')")
            ->orderBy('code')
            ->get();
        $parentCandidates = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('is_active', true)
            ->where('account_type', $this->accountType)
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->orderBy('code')
            ->get();

        return view('finance::livewire.accounts.manage', [
            'accounts' => $accounts,
            'parentCandidates' => $parentCandidates,
            'canManage' => $this->can($authorization, 'finance.account.manage'),
            'accountTypes' => FinanceAccountType::cases(),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Chart of Accounts',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.account.view') || $this->can($authorization, 'finance.account.manage'), 403);
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.account.manage'), 403);
    }

    private function can(AuthorizationService $authorization, string $permission): bool
    {
        $user = auth()->user();
        return $user !== null && $this->organization->exists && $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $this->organization->id),
        );
    }
}
