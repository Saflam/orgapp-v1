<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">FINANCE DIRECTORY</p>
            <h1 class="text-2xl font-bold text-gray-900">Customers &amp; Suppliers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage customer and supplier records used by invoices, bills and payment workflows.</p>
        </div>
        <a href="{{ route('finance.receivables-payables') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">← Receivables &amp; Payables</a>
    </header>

    @if ($successMessage)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $successMessage }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        @if ($canViewCustomers)
            <section class="min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Customers</h2>
                        <p class="text-sm text-gray-500">Member and external customer records</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $customers->total() }} records</span>
                </div>

                @if ($canManageCustomers)
                    <details class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">+ Add external customer</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-sm sm:col-span-2">Name<input wire:model="customerName" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Customer name"></label>
                            <label class="text-sm">Email<input type="email" wire:model="customerEmail" class="mt-1 w-full rounded-lg border-gray-300" placeholder="name@example.com"></label>
                            <label class="text-sm">Phone<input wire:model="customerPhone" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Phone number"></label>
                        </div>
                        <button wire:click="createCustomer" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save customer</button>
                    </details>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:col-span-2">Search customers
                        <input type="search" wire:model.live.debounce.300ms="customerSearch" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case" placeholder="Name, email or phone">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Customer type
                        <select wire:model.live="customerType" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="all">All types</option><option value="member">Members</option><option value="external">External</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                        <select wire:model.live="customerStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All statuses</option>
                        </select>
                    </label>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-3">Customer</th><th class="px-3 py-3">Contact</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($customers as $customer)
                                <tr>
                                    <td class="px-3 py-3"><div class="font-semibold text-gray-900">{{ $customer->name }}</div><div class="text-xs text-gray-500">{{ $customer->user_id ? 'Member account' : 'External record' }}</div></td>
                                    <td class="px-3 py-3"><div>{{ $customer->email ?: '—' }}</div><div class="text-xs text-gray-500">{{ $customer->phone ?: '—' }}</div></td>
                                    <td class="px-3 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ ucfirst($customer->customer_type?->value ?? (string) $customer->customer_type) }}</span></td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $customer->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">No customers match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>{{ $customers->links() }}</div>
            </section>
        @endif

        @if ($canViewSuppliers)
            <section class="min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Suppliers</h2>
                        <p class="text-sm text-gray-500">Supplier records and default payment terms</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $suppliers->total() }} records</span>
                </div>

                @if ($canManageSuppliers)
                    <details class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">+ Add supplier</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-sm">Supplier code (optional)<input wire:model="supplierCode" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Auto-generated if blank">@error('supplierCode')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                            <label class="text-sm">Name<input wire:model="supplierName" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Supplier name"></label>
                            <label class="text-sm">Email<input type="email" wire:model="supplierEmail" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Phone<input wire:model="supplierPhone" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Tax number<input wire:model="supplierTaxNumber" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Payment terms (days)<input type="number" min="0" max="365" wire:model="supplierPaymentTerms" class="mt-1 w-full rounded-lg border-gray-300"></label>
                        </div>
                        <button wire:click="createSupplier" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save supplier</button>
                    </details>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:col-span-2">Search suppliers
                        <input type="search" wire:model.live.debounce.300ms="supplierSearch" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case" placeholder="Code, name, email, phone or tax number">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                        <select wire:model.live="supplierStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All statuses</option>
                        </select>
                    </label>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Contact</th><th class="px-3 py-3">Terms</th><th class="px-3 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    <td class="px-3 py-3"><div class="font-semibold text-gray-900">{{ $supplier->name }}</div><div class="text-xs text-gray-500">{{ $supplier->code }}</div></td>
                                    <td class="px-3 py-3"><div>{{ $supplier->email ?: '—' }}</div><div class="text-xs text-gray-500">{{ $supplier->phone ?: '—' }}</div></td>
                                    <td class="px-3 py-3">{{ $supplier->payment_terms_days }} days</td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $supplier->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">No suppliers match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>{{ $suppliers->links() }}</div>
            </section>
        @endif
    </div>
</div>
