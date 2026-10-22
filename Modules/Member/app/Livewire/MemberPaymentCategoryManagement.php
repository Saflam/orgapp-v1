<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Enums\MemberPaymentRecurrenceBy;
use Modules\Member\Enums\MemberPaymentRecurrencePeriod;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MemberPaymentItemConfigurationService;

class MemberPaymentCategoryManagement extends Component
{
    public Organization $organization;

    public ?int $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $familyPaymentPolicy = 'individual';
    public ?string $membershipTypeId = '';
    public bool $isMandatory = true;
    public bool $isRecurring = false;
    public ?string $recurringPeriod = '';
    public ?string $recurringBy = '';
    public ?string $recurringDate = '';
    public bool $isActive = true;
    public ?string $reminderDaysBeforeDue = '';
    public ?string $reminderDaysAfterDue = '';

    public string $amount = '0.00';
    public string $currency = 'INR';
    public string $effectiveFrom = '';
    public ?string $effectiveTo = '';
    public string $organizationShare = '100';
    public string $unitShare = '0';

    public ?int $rateItemId = null;
    public string $rateAmount = '';
    public string $rateCurrency = 'INR';
    public string $rateEffectiveFrom = '';
    public ?string $rateEffectiveTo = '';
    public string $rateOrganizationShare = '100';
    public string $rateUnitShare = '0';

    public string $successMessage = '';

    public function mount(
        ?Organization $organization = null,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        $this->organization = ($organization instanceof Organization
            && $organization->exists
            && $organization->id !== null)
                ? $organization
                : $currentOrganization->get();

        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->currency = strtoupper($this->organization->currency ?: 'INR');
        $this->rateCurrency = $this->currency;
        $this->effectiveFrom = now()->toDateString();
        $this->rateEffectiveFrom = now()->toDateString();
        $this->ensureCanManage($authorization);
    }

    public function save(MemberPaymentItemConfigurationService $service, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'familyPaymentPolicy' => ['required', 'in:individual,mutual_spouse'],
            'membershipTypeId' => ['nullable', 'integer'],
            'isMandatory' => ['required', 'boolean'],
            'isRecurring' => ['required', 'boolean'],
            'recurringPeriod' => ['nullable', 'in:monthly,quarterly,half_yearly,yearly'],
            'recurringBy' => ['nullable', 'in:join_date,specific_date'],
            'recurringDate' => ['nullable', 'string', 'max:10'],
            'isActive' => ['required', 'boolean'],
            'reminderDaysBeforeDue' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'reminderDaysAfterDue' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
        if ($this->editingId === null) {
            $rules += [
                'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
                'currency' => ['required', 'string', 'size:3'],
                'effectiveFrom' => ['required', 'date'],
                'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
                'organizationShare' => ['required', 'numeric', 'min:0', 'max:100'],
                'unitShare' => ['required', 'numeric', 'min:0', 'max:100'],
            ];
        }
        $validated = $this->validate($rules);

        $membershipTypeId = $validated['membershipTypeId'] !== null && $validated['membershipTypeId'] !== ''
            ? (int) $validated['membershipTypeId']
            : null;

        if ($membershipTypeId !== null && ! MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->whereKey($membershipTypeId)
            ->exists()) {
            throw ValidationException::withMessages([
                'membershipTypeId' => 'Choose a membership type belonging to this organization.',
            ]);
        }

        $isRecurring = (bool) $validated['isRecurring'];
        $recurringBy = $isRecurring ? ($validated['recurringBy'] ?: null) : null;
        $recurringPeriod = $isRecurring ? ($validated['recurringPeriod'] ?: null) : null;
        $recurringDate = $isRecurring && $recurringBy === MemberPaymentRecurrenceBy::SPECIFIC_DATE->value
            ? ($validated['recurringDate'] ?: null)
            : null;

        if ($isRecurring && $recurringPeriod === null) {
            throw ValidationException::withMessages(['recurringPeriod' => 'Select a recurrence period.']);
        }
        if ($isRecurring && $recurringBy === null) {
            throw ValidationException::withMessages(['recurringBy' => 'Select when the recurrence is anchored.']);
        }
        if ($isRecurring && $recurringBy === MemberPaymentRecurrenceBy::SPECIFIC_DATE->value && $recurringDate === null) {
            throw ValidationException::withMessages(['recurringDate' => 'Enter the recurring date.']);
        }

        $data = [
            'membership_type_id' => $membershipTypeId,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?: null,
            'family_payment_policy' => $validated['familyPaymentPolicy'],
            'is_mandatory' => (bool) $validated['isMandatory'],
            'is_recurring' => $isRecurring,
            'recurring_period' => $recurringPeriod,
            'recurring_by' => $recurringBy,
            'recurring_date' => $recurringDate,
            'is_active' => (bool) $validated['isActive'],
            'reminder_days_before_due' => $validated['reminderDaysBeforeDue'] !== null && $validated['reminderDaysBeforeDue'] !== ''
                ? (int) $validated['reminderDaysBeforeDue'] : null,
            'reminder_days_after_due' => $validated['reminderDaysAfterDue'] !== null && $validated['reminderDaysAfterDue'] !== ''
                ? (int) $validated['reminderDaysAfterDue'] : null,
        ];

        if ($this->editingId !== null) {
            $item = MemberPaymentItem::query()
                ->where('organization_id', $this->organization->id)
                ->findOrFail($this->editingId);
            $service->update($this->organization, $item, $data);
            $this->successMessage = 'Payment category updated successfully.';
        } else {
            $data['slug'] = Str::slug($validated['title']) ?: 'payment-category';
            $data['rate'] = [
                'amount' => $validated['amount'],
                'currency' => strtoupper($validated['currency']),
                'effective_from' => $validated['effectiveFrom'],
                'effective_to' => $validated['effectiveTo'] ?: null,
                'allocations' => $this->allocationsFromShares($validated['organizationShare'], $validated['unitShare']),
            ];
            $service->create($this->organization, $data);
            $this->successMessage = 'Payment category created successfully.';
        }

        $this->resetForm();
    }

    public function edit(int $id, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $item = MemberPaymentItem::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->description = $item->description ?? '';
        $this->familyPaymentPolicy = $item->family_payment_policy ?? 'individual';
        $this->membershipTypeId = $item->membership_type_id === null ? '' : (string) $item->membership_type_id;
        $this->isMandatory = $item->is_mandatory;
        $this->isRecurring = $item->is_recurring;
        $this->recurringPeriod = $item->recurring_period ?? '';
        $this->recurringBy = $item->recurring_by ?? '';
        $this->recurringDate = $item->recurring_date ?? '';
        $this->isActive = $item->is_active;
        $this->reminderDaysBeforeDue = $item->reminder_days_before_due === null ? '' : (string) $item->reminder_days_before_due;
        $this->reminderDaysAfterDue = $item->reminder_days_after_due === null ? '' : (string) $item->reminder_days_after_due;
        $this->rateItemId = null;
        $this->resetValidation();
        $this->successMessage = '';
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->familyPaymentPolicy = 'individual';
        $this->membershipTypeId = '';
        $this->isMandatory = true;
        $this->isRecurring = false;
        $this->recurringPeriod = '';
        $this->recurringBy = '';
        $this->recurringDate = '';
        $this->isActive = true;
        $this->reminderDaysBeforeDue = '';
        $this->reminderDaysAfterDue = '';
        $this->amount = '0.00';
        $this->currency = strtoupper($this->organization->currency ?: 'INR');
        $this->effectiveFrom = now()->toDateString();
        $this->effectiveTo = '';
        $this->organizationShare = '100';
        $this->unitShare = '0';
        $this->rateItemId = null;
        $this->resetValidation();
    }

    public function toggleActive(
        int $id,
        MemberPaymentItemConfigurationService $service,
        AuthorizationService $authorization,
    ): void {
        $this->ensureCanManage($authorization);
        $item = MemberPaymentItem::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);

        // Route status changes through the configuration service so pending
        // application invoice snapshots are reconciled immediately.
        $service->update($this->organization, $item, ['is_active' => ! $item->is_active]);
        $this->successMessage = 'Payment category status updated.';
    }

    public function showAddRate(int $id, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $item = MemberPaymentItem::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);
        $this->rateItemId = $item->id;
        $this->rateAmount = '';
        $this->rateCurrency = strtoupper($this->organization->currency ?: 'INR');
        $this->rateEffectiveFrom = now()->toDateString();
        $this->rateEffectiveTo = '';
        $this->rateOrganizationShare = '100';
        $this->rateUnitShare = '0';
        $this->resetValidation();
    }

    public function saveRate(MemberPaymentItemConfigurationService $service, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        abort_unless($this->rateItemId !== null, 404);

        $validated = $this->validate([
            'rateItemId' => ['required', 'integer'],
            'rateAmount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'rateCurrency' => ['required', 'string', 'size:3'],
            'rateEffectiveFrom' => ['required', 'date'],
            'rateEffectiveTo' => ['nullable', 'date', 'after_or_equal:rateEffectiveFrom'],
            'rateOrganizationShare' => ['required', 'numeric', 'min:0', 'max:100'],
            'rateUnitShare' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $item = MemberPaymentItem::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($this->rateItemId);

        $service->addRate($this->organization, $item, [
            'amount' => $validated['rateAmount'],
            'currency' => strtoupper($validated['rateCurrency']),
            'effective_from' => $validated['rateEffectiveFrom'],
            'effective_to' => $validated['rateEffectiveTo'] ?: null,
            'allocations' => $this->allocationsFromShares($validated['rateOrganizationShare'], $validated['rateUnitShare'], 'rateOrganizationShare', 'rateUnitShare'),
        ]);

        $this->rateItemId = null;
        $this->successMessage = 'New payment rate added successfully.';
        $this->resetValidation();
    }

    public function cancelAddRate(): void
    {
        $this->rateItemId = null;
        $this->resetValidation();
    }

    public function render(AuthorizationService $authorization, MemberPaymentItemConfigurationService $service): View
    {
        $this->ensureCanManage($authorization);
        $items = $service->list($this->organization);
        $membershipTypes = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->orderBy('name')
            ->get();

        return view('member::livewire.payment-categories.manage', compact('items', 'membershipTypes'))
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Member payment categories',
                'layoutOrganization' => $this->organization,
            ]);
    }

    private function allocationsFromShares(mixed $organizationShare, mixed $unitShare, string $organizationField, string $unitField): array
    {
        $organizationShare = round((float) $organizationShare, 4);
        $unitShare = round((float) $unitShare, 4);
        if (abs(($organizationShare + $unitShare) - 100.0) > 0.0001) {
            throw ValidationException::withMessages([
                $organizationField => 'Organization and unit shares must total exactly 100%.',
                $unitField => 'Organization and unit shares must total exactly 100%.',
            ]);
        }

        $allocations = [];
        if ($organizationShare > 0) {
            $allocations[] = ['scope_type' => 'organization', 'percentage' => $organizationShare];
        }
        if ($unitShare > 0) {
            $allocations[] = ['scope_type' => 'unit', 'percentage' => $unitShare];
        }
        if ($allocations === []) {
            throw ValidationException::withMessages([$organizationField => 'At least one share must be greater than zero.']);
        }

        return $allocations;
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && $this->organization->exists
            && $this->organization->id !== null
            && $authorization->can(
                $user,
                'organization.settings.manage',
                new AuthorizationContext((int) $this->organization->id),
            ),
            403,
        );
    }
}
