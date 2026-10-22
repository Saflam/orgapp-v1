<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\MembershipType;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Services\MemberPaymentService;
use Modules\Member\Services\MembershipApplicationWorkflowService;

class MembershipApplicationManagement extends Component
{
    use WithPagination;

    public Organization $organization;

    public string $search = '';
    public string $statusFilter = '';
    public string $unitFilter = '';
    public string $membershipTypeId = '';
    public ?int $selectedApplicationId = null;
    public string $decisionNotes = '';
    public bool $allowReapply = false;
    public string $paymentMethod = '';
    public string $paymentReference = '';
    public string $paymentNotes = '';
    public bool $paymentInvoiceOpen = false;
    public array $discountAmounts = [];
    public array $discountReasons = [];
    public string $membershipNumber = '';
    public string $startsAt = '';
    public string $successMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'unitFilter' => ['except' => ''],
    ];

    public function mount(
        ?Organization $organization = null,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        $this->organization = ($organization instanceof Organization && $organization->exists)
            ? $organization
            : $currentOrganization->get();

        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->ensureCan('membership.application.view', $authorization);
        $this->startsAt = now()->toDateString();

        // Support opening an application directly from the list's Review link.
        $requestedApplicationId = request()->integer('application');
        if ($requestedApplicationId > 0) {
            $application = $this->findApplication($requestedApplicationId, 'membership.application.view', $authorization);
            $this->selectedApplicationId = $application->id;
            $this->membershipTypeId = (string) ($application->membership_type_id ?? '');
            if (in_array($application->status, [MembershipApplicationStatus::REVIEWED, MembershipApplicationStatus::PENDING_CONFIRMATION], true) && $this->membershipTypeId === '') {
                $this->membershipTypeId = (string) $this->defaultMembershipTypeId();
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Apply list filters explicitly so the UI works consistently across Livewire updates. */
    public function applyFilters(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->unitFilter = '';
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUnitFilter(): void
    {
        $this->resetPage();
    }

    public function selectApplication(int $id): void
    {
        $authorization = app(AuthorizationService::class);
        $application = $this->findApplication($id, 'membership.application.view', $authorization);
        $this->selectedApplicationId = $application->id;
        $this->membershipTypeId = (string) ($application->membership_type_id ?? '');
        if (in_array($application->status, [MembershipApplicationStatus::REVIEWED, MembershipApplicationStatus::PENDING_CONFIRMATION], true) && $this->membershipTypeId === '') {
            $this->membershipTypeId = (string) $this->defaultMembershipTypeId();
        }
        $this->decisionNotes = '';
        $this->allowReapply = false;
        $this->paymentMethod = '';
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->paymentInvoiceOpen = false;
        $this->discountAmounts = [];
        $this->discountReasons = [];
        $this->membershipNumber = '';
        $this->resetValidation();
    }

    public function clearSelection()
    {
        // Remove the ?application=... query parameter as well, so a browser refresh
        // does not reopen the application the administrator just left.
        return $this->redirect(route('member.membership-applications'), navigate: true);
    }

    public function verify(AuthorizationService $authorization, MembershipApplicationWorkflowService $workflow): void
    {
        $application = $this->selected('membership.application.verify', $authorization);
        $workflow->transition($application, MembershipApplicationStatus::VERIFIED, auth()->user(), trim($this->decisionNotes) !== '' ? trim($this->decisionNotes) : null);
        $this->successMessage = 'Application verified successfully.';
        $this->refreshSelected();
    }

    public function review(AuthorizationService $authorization, MembershipApplicationWorkflowService $workflow): void
    {
        $application = $this->selected('membership.application.review', $authorization);
        $workflow->transition($application, MembershipApplicationStatus::REVIEWED, auth()->user(), trim($this->decisionNotes) !== '' ? trim($this->decisionNotes) : null);
        $this->successMessage = 'Application review completed.';
        $this->refreshSelected();
    }

    public function approve(AuthorizationService $authorization, MembershipApplicationWorkflowService $workflow): void
    {
        $application = $this->selected('membership.application.approve', $authorization);
        $this->validate([
            'membershipTypeId' => ['required', 'integer'],
        ]);

        $membershipType = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->where('is_active', true)
            ->find((int) $this->membershipTypeId);

        if ($membershipType === null) {
            $this->addError('membershipTypeId', 'Choose an active membership type for this organization before approving.');
            return;
        }

        // The type must be persisted before approval events create payment obligations.
        DB::transaction(function () use ($application, $membershipType, $workflow): void {
            $application->update(['membership_type_id' => $membershipType->id]);
            $application->refresh();
            $workflow->transition(
                $application,
                MembershipApplicationStatus::APPROVED,
                auth()->user(),
                trim($this->decisionNotes) !== '' ? trim($this->decisionNotes) : null,
            );
        });

        $this->successMessage = 'Application approved. Continue to payment processing when applicable.';
        $this->refreshSelected();
    }

    public function reject(AuthorizationService $authorization, MembershipApplicationWorkflowService $workflow): void
    {
        $application = $this->selected(null, $authorization);
        $this->validate([
            'decisionNotes' => ['required', 'string', 'max:5000'],
            'allowReapply' => ['required', 'boolean'],
        ]);

        $permission = match ($application->status) {
            MembershipApplicationStatus::SUBMITTED => 'membership.application.verify',
            MembershipApplicationStatus::VERIFIED => 'membership.application.review',
            MembershipApplicationStatus::REVIEWED => 'membership.application.approve',
            default => null,
        };
        abort_unless($permission !== null, 403);
        $this->ensureCan($permission, $authorization, $this->applicationUnitId($application));

        $reason = trim($this->decisionNotes);
        $workflow->reject($application, auth()->user(), $reason, $this->allowReapply);
        $this->successMessage = 'Application rejected and the reason was recorded.';
        $this->decisionNotes = '';
        $this->refreshSelected();
    }

    public function openPaymentInvoice(AuthorizationService $authorization, MemberPaymentService $payments): void
    {
        $application = $this->selected('membership.payment.receive', $authorization);
        $this->ensureCan('membership.payment.view', $authorization, $this->applicationUnitId($application));
        abort_unless(in_array($application->status, [MembershipApplicationStatus::APPROVED, MembershipApplicationStatus::PENDING_PAYMENT], true), 403);

        $obligations = $payments->prepareApplicationInvoice($application, auth()->user());
        $this->discountAmounts = [];
        $this->discountReasons = [];
        foreach ($obligations as $obligation) {
            $this->discountAmounts[$obligation->id] = (float) $obligation->discount_amount > 0 ? (string) $obligation->discount_amount : '';
            $this->discountReasons[$obligation->id] = (string) ($obligation->discounts->sortByDesc('id')->first()?->reason ?? '');
        }
        $this->paymentInvoiceOpen = true;
        $this->paymentMethod = '';
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->resetValidation();
        $this->refreshSelected();
    }

    public function closePaymentInvoice(): void
    {
        $this->paymentInvoiceOpen = false;
        $this->resetValidation();
    }

    public function receivePayment(AuthorizationService $authorization, MemberPaymentService $payments): void
    {
        abort_unless($this->paymentInvoiceOpen, 403);
        $application = $this->selected('membership.payment.receive', $authorization);
        $this->ensureCan('membership.payment.view', $authorization, $this->applicationUnitId($application));
        $obligations = $payments->pendingApplicationObligations($application);
        $hasPayableAmount = $obligations->contains(fn ($obligation) => (float) $obligation->payable_amount - (float) $obligation->paid_amount > 0);

        $this->validate([
            'paymentMethod' => [$hasPayableAmount ? 'required' : 'nullable', 'string', 'in:cash,bank_transfer,cheque,card,upi,online,other'],
            'paymentReference' => ['nullable', 'string', 'max:150'],
            'paymentNotes' => ['nullable', 'string', 'max:2000'],
            'discountAmounts' => ['array'],
            'discountAmounts.*' => ['nullable', 'numeric', 'min:0'],
            'discountReasons' => ['array'],
            'discountReasons.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $discounts = [];
        foreach ($obligations as $obligation) {
            $amount = round((float) ($this->discountAmounts[$obligation->id] ?? 0), 2);
            if ($amount <= 0 || (float) $obligation->discount_amount > 0) {
                continue;
            }
            $this->ensureCan('membership.payment.discount', $authorization, $this->applicationUnitId($application));
            $reason = trim((string) ($this->discountReasons[$obligation->id] ?? ''));
            if ($reason === '') {
                $this->addError("discountReasons.{$obligation->id}", 'Enter a reason for this discount.');
                return;
            }
            $discounts[$obligation->id] = ['amount' => $amount, 'reason' => $reason];
        }

        $payments->markApplicationPaid(
            application: $application,
            actor: auth()->user(),
            scopeType: $this->applicationUnitId($application) === null ? 'organization' : 'unit',
            scopeId: $this->applicationUnitId($application),
            paymentMethod: trim($this->paymentMethod) !== '' ? trim($this->paymentMethod) : null,
            reference: trim($this->paymentReference) !== '' ? trim($this->paymentReference) : null,
            discounts: $discounts,
            collectionNotes: trim($this->paymentNotes) !== '' ? trim($this->paymentNotes) : null,
        );

        $this->paymentInvoiceOpen = false;
        $this->successMessage = 'Payment has been recorded. The receipt is available in the payment records.';
        $this->refreshSelected();
    }

    public function confirmMembership(
        AuthorizationService $authorization,
        MembershipApplicationWorkflowService $workflow,
    ): void {
        $application = $this->selected('membership.application.confirm', $authorization);
        $this->validate([
            'membershipNumber' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['required', 'date'],
        ]);

        if ($application->membership_type_id === null) {
            $this->addError('membershipTypeId', 'This application has no membership type. Return to the approval stage and assign one before payment.');
            return;
        }

        $membershipType = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->where('is_active', true)
            ->find($application->membership_type_id);

        if ($membershipType === null) {
            $this->addError('membershipTypeId', 'The assigned membership type is no longer active. Please contact an organization administrator.');
            return;
        }

        $number = trim($this->membershipNumber);
        $number = $number !== '' ? $number : null;

        DB::transaction(function () use ($application, $workflow, $number): void {
            $application->refresh();

            $workflow->confirm(
                application: $application,
                actor: auth()->user(),
                membershipNumber: $number,
                startsAt: $this->startsAt,
            );
        });

        $this->successMessage = 'Membership confirmed and activated.';
        $this->refreshSelected();
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCan('membership.application.view', $authorization);

        $applications = MembershipApplication::query()
            ->where('organization_id', $this->organization->id)
            ->with(['user', 'membershipType'])
            ->when($this->unitFilter !== '', function (Builder $query): void {
                $unitId = (string) ((int) $this->unitFilter);
                $driver = $query->getConnection()->getDriverName();

                // Compare normalized JSON text so integer and string IDs both match.
                // preferred_unit_id is authoritative; unit_id is a legacy fallback.
                if ($driver === 'pgsql') {
                    $preferred = "data #>> '{member,preferred_unit_id}'";
                    $legacy = "data #>> '{member,unit_id}'";
                } elseif ($driver === 'sqlite') {
                    $preferred = "json_extract(data, '$.member.preferred_unit_id')";
                    $legacy = "json_extract(data, '$.member.unit_id')";
                } else {
                    $preferred = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.member.preferred_unit_id'))";
                    $legacy = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.member.unit_id'))";
                }

                $preferredCast = $driver === 'mysql' ? "CAST({$preferred} AS CHAR)" : "CAST({$preferred} AS VARCHAR)";
                $legacyCast = $driver === 'mysql' ? "CAST({$legacy} AS CHAR)" : "CAST({$legacy} AS VARCHAR)";
                $query->where(function (Builder $unitQuery) use ($unitId, $preferred, $legacy, $preferredCast, $legacyCast): void {
                    $unitQuery->whereRaw("{$preferredCast} = ?", [$unitId])
                        ->orWhere(function (Builder $fallback) use ($unitId, $preferred, $legacyCast): void {
                            $fallback->whereNull($preferred)
                                ->whereRaw("{$legacyCast} = ?", [$unitId]);
                        });
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhere('data->member->first_name', 'like', $term)
                        ->orWhere('data->member->middle_name', 'like', $term)
                        ->orWhere('data->member->last_name', 'like', $term);
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(15);

        // Build spouse-pair labels for the current page without querying once per row.
        $pageIds = $applications->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $spouseApplicationBySourceId = [];
        if ($pageIds !== []) {
            MembershipApplication::query()
                ->where('organization_id', $this->organization->id)
                ->whereIn('data->member->metadata->spouse_of_application_id', $pageIds)
                ->get(['id', 'data'])
                ->each(function (MembershipApplication $spouseApplication) use (&$spouseApplicationBySourceId): void {
                    $sourceId = (int) data_get($spouseApplication->data, 'member.metadata.spouse_of_application_id');
                    if ($sourceId > 0) {
                        $spouseApplicationBySourceId[$sourceId] = (int) $spouseApplication->id;
                    }
                });
        }

        $selectedApplication = null;
        if ($this->selectedApplicationId !== null) {
            $selectedApplication = MembershipApplication::query()
                ->where('organization_id', $this->organization->id)
                ->with(['user', 'membershipType', 'statusHistories.actor'])
                ->find($this->selectedApplicationId);
            if (! $selectedApplication) {
                $this->selectedApplicationId = null;
            }
        }

        $statuses = collect(MembershipApplicationStatus::cases())
            ->mapWithKeys(fn (MembershipApplicationStatus $status) => [$status->value => str($status->value)->replace('_', ' ')->title()->toString()])
            ->all();

        $units = Unit::query()->where('organization_id', $this->organization->id)->orderBy('name')->get(['id', 'name']);
        $membershipTypes = MembershipType::query()->where('organization_id', $this->organization->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $unitNames = $units->pluck('name', 'id');
        $pendingApplications = MembershipApplication::query()
            ->where('organization_id', $this->organization->id)
            ->whereIn('status', [MembershipApplicationStatus::SUBMITTED->value, MembershipApplicationStatus::VERIFIED->value, MembershipApplicationStatus::REVIEWED->value])
            ->get(['id', 'status', 'data']);
        $pendingCount = $pendingApplications->filter(function (MembershipApplication $application) use ($authorization): bool {
            $permission = match ($application->status) {
                MembershipApplicationStatus::SUBMITTED => 'membership.application.verify',
                MembershipApplicationStatus::VERIFIED => 'membership.application.review',
                MembershipApplicationStatus::REVIEWED => 'membership.application.approve',
                default => null,
            };
            return $permission !== null && $authorization->can(auth()->user(), $permission, new AuthorizationContext((int) $this->organization->id, $application->unitId()));
        })->count();

        $selectedUnitId = $selectedApplication ? $this->applicationUnitId($selectedApplication) : null;
        $canReceivePayment = $selectedApplication !== null
            && $authorization->can(auth()->user(), 'membership.payment.receive', new AuthorizationContext((int) $this->organization->id, $selectedUnitId))
            && $authorization->can(auth()->user(), 'membership.payment.view', new AuthorizationContext((int) $this->organization->id, $selectedUnitId));
        $canDiscountPayment = $selectedApplication !== null
            && $authorization->can(auth()->user(), 'membership.payment.discount', new AuthorizationContext((int) $this->organization->id, $selectedUnitId));
        // Always query the payment service for invoice rows instead of rendering the
        // application's cached paymentObligations relationship. The relationship is a
        // snapshot and may contain unpaid obligations whose payment categories have
        // since been deactivated or made optional. The service filters against current
        // category configuration, so line items and every total use the same collection.
        $invoiceObligations = $selectedApplication && $this->paymentInvoiceOpen
            ? app(MemberPaymentService::class)->pendingApplicationObligations($selectedApplication)
                ->load(['discounts.actor'])
            : collect();

        return view('member::livewire.membership-applications.manage', compact('applications', 'selectedApplication', 'statuses', 'units', 'unitNames', 'membershipTypes', 'pendingCount', 'spouseApplicationBySourceId', 'canReceivePayment', 'canDiscountPayment', 'invoiceObligations'))
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Membership applications',
                'layoutOrganization' => $this->organization,
            ]);
    }

    private function selected(?string $permission, AuthorizationService $authorization): MembershipApplication
    {
        abort_unless($this->selectedApplicationId !== null, 404);
        $application = $this->findApplication($this->selectedApplicationId, $permission ?? 'membership.application.view', $authorization);
        if ($permission === null) {
            $stagePermission = match ($application->status) {
                MembershipApplicationStatus::SUBMITTED => 'membership.application.verify',
                MembershipApplicationStatus::VERIFIED => 'membership.application.review',
                MembershipApplicationStatus::REVIEWED => 'membership.application.approve',
                default => null,
            };
            abort_unless($stagePermission !== null, 403);
            $this->ensureCan($stagePermission, $authorization, $this->applicationUnitId($application));
        }
        return $application;
    }

    private function findApplication(int $id, string $permission, AuthorizationService $authorization): MembershipApplication
    {
        $application = MembershipApplication::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);
        $this->ensureCan($permission, $authorization, $this->applicationUnitId($application));
        return $application;
    }

    private function ensureCan(string $permission, AuthorizationService $authorization, ?int $unitId = null): void
    {
        $user = auth()->user();

        abort_unless(
            $user && $authorization->can(
                $user,
                $permission,
                new AuthorizationContext((int) $this->organization->id, $unitId),
            ),
            403,
        );
    }

    /** Preferred Unit is the authorization scope for new applications; unit_id supports legacy data. */
    private function applicationUnitId(MembershipApplication $application): ?int
    {
        $unitId = data_get($application->data, 'member.preferred_unit_id')
            ?? data_get($application->data, 'member.unit_id');

        return $unitId === null || $unitId === '' ? null : (int) $unitId;
    }

    private function defaultMembershipTypeId(): ?int
    {
        return MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    private function refreshSelected(): void
    {
        $this->resetValidation();
        if ($this->selectedApplicationId === null) {
            return;
        }

        $application = MembershipApplication::query()
            ->where('organization_id', $this->organization->id)
            ->find($this->selectedApplicationId);

        if ($application && $application->status === MembershipApplicationStatus::REVIEWED && $this->membershipTypeId === '') {
            $this->membershipTypeId = (string) $this->defaultMembershipTypeId();
        }
    }
}
