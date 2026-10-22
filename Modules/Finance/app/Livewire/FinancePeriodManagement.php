<?php

namespace Modules\Finance\Livewire;

use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use App\Support\Organization\CurrentOrganization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceScope;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;

class FinancePeriodManagement extends Component
{
    public Organization $organization;

    public ?int $editingId = null;
    public string $name = '';
    public string $code = '';
    public string $startsOn = '';
    public string $endsOn = '';
    public string $successMessage = '';

    public int $startMonth = 4;
    public int $startDay = 1;
    public int $endMonth = 3;
    public int $endDay = 31;
    public int $generateStartYear;

    public function mount(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): void {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);

        $scope = $scopes->organizationScope($this->organization);
        $pattern = data_get($scope->metadata ?? [], 'financial_year_pattern', []);
        $this->startMonth = (int) ($pattern['start_month'] ?? 4);
        $this->startDay = (int) ($pattern['start_day'] ?? 1);
        $this->endMonth = (int) ($pattern['end_month'] ?? 3);
        $this->endDay = (int) ($pattern['end_day'] ?? 31);

        $todayMonthDay = (int) now()->format('md');
        $startMonthDay = ($this->startMonth * 100) + $this->startDay;
        $this->generateStartYear = $todayMonthDay < $startMonthDay
            ? (int) now()->format('Y') - 1
            : (int) now()->format('Y');

        $this->startsOn = now()->startOfYear()->toDateString();
        $this->endsOn = now()->endOfYear()->toDateString();
        $this->ensureCanView($authorization);
    }

    public function savePattern(
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $validated = $this->validate([
            'startMonth' => ['required', 'integer', 'between:1,12'],
            'startDay' => ['required', 'integer', 'between:1,31'],
            'endMonth' => ['required', 'integer', 'between:1,12'],
            'endDay' => ['required', 'integer', 'between:1,31'],
        ]);

        if (! checkdate((int) $validated['startMonth'], (int) $validated['startDay'], 2000)) {
            throw ValidationException::withMessages(['startDay' => 'Choose a valid start month and day.']);
        }

        if (! checkdate((int) $validated['endMonth'], (int) $validated['endDay'], 2000)) {
            throw ValidationException::withMessages(['endDay' => 'Choose a valid end month and day.']);
        }

        $scope = $scopes->organizationScope($this->organization);
        $metadata = $scope->metadata ?? [];
        $metadata['financial_year_pattern'] = [
            'start_month' => (int) $validated['startMonth'],
            'start_day' => (int) $validated['startDay'],
            'end_month' => (int) $validated['endMonth'],
            'end_day' => (int) $validated['endDay'],
        ];
        $scope->update(['metadata' => $metadata]);

        $this->successMessage = 'Financial-year pattern saved for this organization.';
    }

    public function generatePeriod(
        FinancePeriodService $periods,
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $this->validate([
            'generateStartYear' => ['required', 'integer', 'between:2000,2200'],
            'startMonth' => ['required', 'integer', 'between:1,12'],
            'startDay' => ['required', 'integer', 'between:1,31'],
            'endMonth' => ['required', 'integer', 'between:1,12'],
            'endDay' => ['required', 'integer', 'between:1,31'],
        ]);

        if (! checkdate($this->startMonth, $this->startDay, 2000)) {
            throw ValidationException::withMessages(['startDay' => 'Choose a valid start month and day.']);
        }
        if (! checkdate($this->endMonth, $this->endDay, 2000)) {
            throw ValidationException::withMessages(['endDay' => 'Choose a valid end month and day.']);
        }

        $scope = $scopes->organizationScope($this->organization);
        $pattern = [
            'start_month' => $this->startMonth,
            'start_day' => $this->startDay,
            'end_month' => $this->endMonth,
            'end_day' => $this->endDay,
        ];

        // Persist the chosen pattern as part of the same intentional admin action.
        $metadata = $scope->metadata ?? [];
        $metadata['financial_year_pattern'] = $pattern;
        $scope->update(['metadata' => $metadata]);

        try {
            $period = $periods->generateDraft(
                $this->organization,
                $scope,
                $this->generateStartYear,
                $pattern,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['generateStartYear' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages(['generateStartYear' => 'A period with this financial-year code already exists.']);
            }
            throw $exception;
        }

        $this->successMessage = sprintf(
            '%s was generated as a draft. Review it below and explicitly open it before posting transactions.',
            $period->code,
        );
    }

    public function openPeriod(
        int $periodId,
        FinancePeriodService $periods,
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($periodId);

        try {
            $periods->open($period);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }

        $this->successMessage = 'Financial period opened. Transactions can now be posted for dates within this period.';
    }

    public function save(
        FinancePeriodService $periods,
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        if ($this->editingId === null) {
            throw ValidationException::withMessages(['period' => 'Generate a financial year from the configured pattern instead of entering dates manually.']);
        }

        $scope = $scopes->organizationScope($this->organization);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('finance_periods', 'code')
                    ->where('organization_id', $this->organization->id)
                    ->where('finance_scope_id', $scope->id)
                    ->ignore($this->editingId),
            ],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
        ]);

        $data = [
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'starts_on' => $validated['startsOn'],
            'ends_on' => $validated['endsOn'],
        ];

        try {
            $period = FinancePeriod::query()
                ->where('organization_id', $this->organization->id)
                ->where('finance_scope_id', $scope->id)
                ->findOrFail($this->editingId);
            $periods->update($this->organization, $scope, $period, $data);
            $this->successMessage = 'Financial period updated successfully.';
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['startsOn' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages(['code' => 'This period code is already in use.']);
            }
            throw $exception;
        }

        $this->resetForm();
    }

    public function edit(int $periodId, FinanceScopeService $scopes, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($periodId);

        if (! $period->isOpen()) {
            $this->addError('period', 'Only open financial periods can be edited.');
            return;
        }

        if ($period->journals()->exists()) {
            $this->addError('period', 'A period with posted journals cannot be edited.');
            return;
        }

        $this->editingId = $period->id;
        $this->name = $period->name;
        $this->code = $period->code;
        $this->startsOn = $period->starts_on->toDateString();
        $this->endsOn = $period->ends_on->toDateString();
        $this->resetValidation();
    }

    public function close(int $periodId, FinancePeriodService $periods, FinanceScopeService $scopes, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($periodId);

        try {
            $periods->close($period);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }

        if ($this->editingId === $periodId) {
            $this->resetForm();
        }
        $this->successMessage = 'Financial period closed. Payments dated within this period can no longer be posted.';
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->startsOn = now()->startOfYear()->toDateString();
        $this->endsOn = now()->endOfYear()->toDateString();
        $this->resetValidation();
    }

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): View {
        $this->ensureCanView($authorization);
        $scope = $scopes->organizationScope($this->organization);
        $periods = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->withCount('journals')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        $canManage = $this->can($authorization, 'finance.period.manage');

        return view('finance::livewire.periods.manage', compact('periods', 'canManage'))
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Financial periods',
                'layoutOrganization' => $this->organization,
            ]);
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        abort_unless(
            $this->can($authorization, 'finance.period.view')
                || $this->can($authorization, 'finance.period.manage'),
            403,
        );
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.period.manage'), 403);
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
