<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinanceCustomer;
use Modules\Finance\Models\FinanceSupplier;
use Modules\Finance\Services\FinanceCustomerService;
use Modules\Finance\Services\FinanceSupplierService;

class FinancePartyManagement extends Component
{
    use WithPagination;

    public Organization $organization;
    public string $customerSearch = '';
    public string $customerType = 'all';
    public string $customerStatus = 'active';
    public string $supplierSearch = '';
    public string $supplierStatus = 'active';
    public string $successMessage = '';

    public string $customerName = '';
    public string $customerEmail = '';
    public string $customerPhone = '';

    public string $supplierCode = '';
    public string $supplierName = '';
    public string $supplierEmail = '';
    public string $supplierPhone = '';
    public string $supplierTaxNumber = '';
    public int $supplierPaymentTerms = 0;

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        abort_unless(
            $this->can($authorization, 'finance.customer.view')
            || $this->can($authorization, 'finance.customer.manage')
            || $this->can($authorization, 'finance.supplier.view')
            || $this->can($authorization, 'finance.supplier.manage'),
            403
        );
    }

    public function updatedCustomerSearch(): void { $this->resetPage('customersPage'); }
    public function updatedCustomerType(): void { $this->resetPage('customersPage'); }
    public function updatedCustomerStatus(): void { $this->resetPage('customersPage'); }
    public function updatedSupplierSearch(): void { $this->resetPage('suppliersPage'); }
    public function updatedSupplierStatus(): void { $this->resetPage('suppliersPage'); }

    public function createCustomer(FinanceCustomerService $customers, AuthorizationService $authorization): void
    {
        $this->ensureCan($authorization, 'finance.customer.manage');
        $data = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
        ]);

        $customers->createExternalCustomer($this->organization, [
            'name' => $data['customerName'],
            'email' => $data['customerEmail'],
            'phone' => $data['customerPhone'],
        ]);

        $this->customerName = $this->customerEmail = $this->customerPhone = '';
        $this->resetPage('customersPage');
        $this->successMessage = 'Customer created.';
    }

    public function createSupplier(FinanceSupplierService $suppliers, AuthorizationService $authorization): void
    {
        $this->ensureCan($authorization, 'finance.supplier.manage');
        $data = $this->validate([
            'supplierCode' => ['nullable', 'string', 'max:60'],
            'supplierName' => ['required', 'string', 'max:255'],
            'supplierEmail' => ['nullable', 'email', 'max:255'],
            'supplierPhone' => ['nullable', 'string', 'max:50'],
            'supplierTaxNumber' => ['nullable', 'string', 'max:100'],
            'supplierPaymentTerms' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        try {
            $suppliers->create($this->organization, [
                'code' => $data['supplierCode'] ?: null,
                'name' => $data['supplierName'],
                'email' => $data['supplierEmail'],
                'phone' => $data['supplierPhone'],
                'tax_number' => $data['supplierTaxNumber'],
                'payment_terms_days' => $data['supplierPaymentTerms'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('supplierCode', $exception->getMessage());
            return;
        }

        $this->supplierCode = $this->supplierName = $this->supplierEmail = $this->supplierPhone = $this->supplierTaxNumber = '';
        $this->supplierPaymentTerms = 0;
        $this->resetPage('suppliersPage');
        $this->successMessage = 'Supplier created.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $canViewCustomers = $this->can($authorization, 'finance.customer.view') || $this->can($authorization, 'finance.customer.manage');
        $canViewSuppliers = $this->can($authorization, 'finance.supplier.view') || $this->can($authorization, 'finance.supplier.manage');

        $customers = FinanceCustomer::query()
            ->where('organization_id', $this->organization->id)
            ->when($this->customerStatus !== 'all', fn ($query) => $query->where('is_active', $this->customerStatus === 'active'))
            ->when($this->customerType !== 'all', fn ($query) => $query->where('customer_type', $this->customerType))
            ->when(trim($this->customerSearch) !== '', function ($query): void {
                $term = '%' . trim($this->customerSearch) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'customersPage');

        $suppliers = FinanceSupplier::query()
            ->where('organization_id', $this->organization->id)
            ->when($this->supplierStatus !== 'all', fn ($query) => $query->where('is_active', $this->supplierStatus === 'active'))
            ->when(trim($this->supplierSearch) !== '', function ($query): void {
                $term = '%' . trim($this->supplierSearch) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('tax_number', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'suppliersPage');

        return view('finance::livewire.parties.manage', [
            'customers' => $canViewCustomers ? $customers : collect(),
            'suppliers' => $canViewSuppliers ? $suppliers : collect(),
            'canViewCustomers' => $canViewCustomers,
            'canViewSuppliers' => $canViewSuppliers,
            'canManageCustomers' => $this->can($authorization, 'finance.customer.manage'),
            'canManageSuppliers' => $this->can($authorization, 'finance.supplier.manage'),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Customers & Suppliers',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function can(AuthorizationService $authorization, string $permission): bool
    {
        $user = auth()->user();
        return $user !== null && $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $this->organization->id)
        );
    }

    private function ensureCan(AuthorizationService $authorization, string $permission): void
    {
        abort_unless($this->can($authorization, $permission), 403);
    }
}
