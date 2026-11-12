<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">FINANCE OPERATIONS</p>
            <h1 class="text-2xl font-bold">Receivables &amp; Payables</h1>
            <p class="mt-1 text-sm text-gray-500">Post customer invoices and supplier bills, allocate payments and monitor outstanding balances.</p>
        </div>
        @if ($canViewParties)
            <a href="{{ route('finance.parties') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Customers &amp; Suppliers</a>
        @endif
    </header>

    @if ($successMessage)<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $successMessage }}</div>@endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @error('invoice')<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror
    @error('bill')<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror
    @error('payment')<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror

    <nav class="flex flex-wrap gap-2 border-b pb-3">
        @if ($canViewSales || $receivablesAging)
            <button type="button" wire:click="$set('activeTab', 'receivables')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $activeTab === 'receivables' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Receivables</button>
        @endif
        @if ($canViewPurchases || $payablesAging)
            <button type="button" wire:click="$set('activeTab', 'payables')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $activeTab === 'payables' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Payables</button>
        @endif
        @if ($canViewPayments)
            <button type="button" wire:click="$set('activeTab', 'payments')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $activeTab === 'payments' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Payments</button>
        @endif
    </nav>

    @if ($activeTab === 'receivables')
        @if ($canViewSales)
        @if ($receivablesAging)
            <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"><h2 class="mb-3 font-bold">Receivables ageing summary</h2><div class="overflow-x-auto"><div class="grid min-w-[760px] grid-cols-5 gap-3">@foreach ($receivablesAging['buckets'] as $bucket => $amount)<div class="rounded-lg bg-gray-50 p-3"><p class="text-xs uppercase text-gray-500">{{ str_replace('_', ' ', $bucket) }}</p><p class="mt-1 text-lg font-bold">{{ $amount }}</p></div>@endforeach</div></div><p class="mt-3 text-right font-bold">Total outstanding: {{ $receivablesAging['total_outstanding'] }}</p></section>
        @endif
        @if ($canManageSales)
<div class="flex justify-end"><button type="button" wire:click="openModal('invoice')" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">+ Post sales invoice</button></div>
        @if ($activeModal === 'invoice')
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Post sales invoice">
                <div class="my-auto max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3 border-b pb-3"><h2 class="text-lg font-bold text-gray-900">Post sales invoice</h2><button type="button" wire:click="closeModal" aria-label="Close dialog" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">&times;</button></div>
                    <div class="space-y-4">
                <h2 class="font-bold">Post sales invoice</h2>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="text-sm">Customer<select wire:model="invoiceCustomerId" class="mt-1 w-full rounded border-gray-300"><option value="">Select customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>@error('invoiceCustomerId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm">Invoice date<input type="date" wire:model="invoiceDate" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="text-sm">Due date<input type="date" wire:model="invoiceDueDate" class="mt-1 w-full rounded border-gray-300"></label>
                </div>
                @foreach ($invoiceLines as $index => $line)
                    <div class="grid gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-5" wire:key="invoice-line-{{ $index }}">
                        <label class="text-sm md:col-span-2">Description<input wire:model="invoiceLines.{{ $index }}.description" class="mt-1 w-full rounded border-gray-300"></label>
                        <label class="text-sm">Qty<input type="number" step="0.0001" min="0.0001" wire:model="invoiceLines.{{ $index }}.quantity" class="mt-1 w-full rounded border-gray-300"></label>
                        <label class="text-sm">Unit price<input type="number" step="0.0001" min="0" wire:model="invoiceLines.{{ $index }}.unit_price" class="mt-1 w-full rounded border-gray-300"></label>
                        <div class="flex items-end gap-2"><button type="button" wire:click="removeInvoiceLine({{ $index }})" class="rounded border px-3 py-2 text-sm">Remove</button></div>
                        <label class="text-sm">Income account<select wire:model="invoiceLines.{{ $index }}.finance_account_id" class="mt-1 w-full rounded border-gray-300"><option value="">Default sales income</option>@foreach ($accounts->where('account_type.value', 'income') as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                        <label class="text-sm">Tax rate<select wire:model="invoiceLines.{{ $index }}.finance_tax_rate_id" class="mt-1 w-full rounded border-gray-300"><option value="">No tax</option>@foreach ($taxRates->whereIn('applies_to', ['sales', 'both']) as $rate)<option value="{{ $rate->id }}">{{ $rate->name }} ({{ $rate->rate }}%)</option>@endforeach</select></label>
                        <label class="text-sm">Cost centre<select wire:model="invoiceLines.{{ $index }}.finance_cost_center_id" class="mt-1 w-full rounded border-gray-300"><option value="">Organization-wide / unassigned</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->name }}</option>@endforeach</select></label>
                    </div>
                @endforeach
                <button type="button" wire:click="addInvoiceLine" class="rounded border px-3 py-2 text-sm">+ Add line</button>
                <label class="block text-sm">Notes<textarea wire:model="invoiceNotes" rows="2" class="mt-1 w-full rounded border-gray-300"></textarea></label>
                <button wire:click="postInvoice" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Post invoice</button>
            </div>
                </div>
            </div>
        @endif
        @endif

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">Customer invoices</h2><p class="text-xs text-gray-500">Search and review posted invoice balances</p></div><span class="text-sm text-gray-500">{{ $invoices->total() }} records</span></div>
            <div class="mb-4 grid w-full grid-cols-12 items-center gap-3">
                <input type="search" wire:model.live.debounce.300ms="invoiceSearch" placeholder="Search invoice or customer" class="col-span-8 block w-full min-w-0 rounded-lg border-gray-300 text-sm">
                <select wire:model.live="invoiceStatusFilter" class="col-span-4 block w-full min-w-0 rounded-lg border-gray-300 text-sm"><option value="outstanding">Outstanding</option><option value="all">All statuses</option><option value="posted">Unpaid</option><option value="partially_paid">Partially paid</option><option value="paid">Paid</option></select>
            </div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-gray-500"><th class="p-2">Invoice</th><th class="p-2">Customer</th><th class="p-2">Date</th><th class="p-2">Due</th><th class="p-2 text-right">Total</th><th class="p-2 text-right">Paid</th><th class="p-2 text-right">Balance</th></tr></thead><tbody>
                @forelse ($invoices as $invoice)<tr class="border-b"><td class="p-2 font-medium">{{ $invoice->invoice_number }}</td><td class="p-2">{{ $invoice->customer?->name }}</td><td class="p-2">{{ $invoice->invoice_date->format('d M Y') }}</td><td class="p-2">{{ $invoice->due_date->format('d M Y') }}</td><td class="p-2 text-right">{{ $invoice->total }}</td><td class="p-2 text-right">{{ $invoice->paid_amount }}</td><td class="p-2 text-right font-semibold">{{ $invoice->balance_due }}</td></tr>@empty<tr><td colspan="7" class="p-5 text-center text-gray-500">No outstanding invoices.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $invoices->links() }}</div>
        </section>
        @endif

    @elseif ($activeTab === 'payables')
        @if ($canViewPurchases)
        @if ($payablesAging)
            <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"><h2 class="mb-3 font-bold">Payables ageing summary</h2><div class="overflow-x-auto"><div class="grid min-w-[760px] grid-cols-5 gap-3">@foreach ($payablesAging['buckets'] as $bucket => $amount)<div class="rounded-lg bg-gray-50 p-3"><p class="text-xs uppercase text-gray-500">{{ str_replace('_', ' ', $bucket) }}</p><p class="mt-1 text-lg font-bold">{{ $amount }}</p></div>@endforeach</div></div><p class="mt-3 text-right font-bold">Total outstanding: {{ $payablesAging['total_outstanding'] }}</p></section>
        @endif
        @if ($canManagePurchases)
<div class="flex justify-end"><button type="button" wire:click="openModal('bill')" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">+ Post supplier bill</button></div>
        @if ($activeModal === 'bill')
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Post supplier bill">
                <div class="my-auto max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3 border-b pb-3"><h2 class="text-lg font-bold text-gray-900">Post supplier bill</h2><button type="button" wire:click="closeModal" aria-label="Close dialog" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">&times;</button></div>
                    <div class="space-y-4">
                <div class="grid gap-3 md:grid-cols-4"><label class="text-sm">Supplier<select wire:model="billSupplierId" class="mt-1 w-full rounded border-gray-300"><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label class="text-sm">Bill date<input type="date" wire:model="billDate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Due date<input type="date" wire:model="billDueDate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Supplier invoice ref<input wire:model="billSupplierReference" class="mt-1 w-full rounded border-gray-300"></label></div>
                @foreach ($billLines as $index => $line)<div class="grid gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-5" wire:key="bill-line-{{ $index }}"><label class="text-sm md:col-span-2">Description<input wire:model="billLines.{{ $index }}.description" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Qty<input type="number" step="0.0001" min="0.0001" wire:model="billLines.{{ $index }}.quantity" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Unit price<input type="number" step="0.0001" min="0" wire:model="billLines.{{ $index }}.unit_price" class="mt-1 w-full rounded border-gray-300"></label><div class="flex items-end"><button type="button" wire:click="removeBillLine({{ $index }})" class="rounded border px-3 py-2 text-sm">Remove</button></div><label class="text-sm">Expense / asset account<select wire:model="billLines.{{ $index }}.finance_account_id" class="mt-1 w-full rounded border-gray-300"><option value="">Default general expense</option>@foreach ($accounts->whereIn('account_type.value', ['expense', 'asset']) as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label><label class="text-sm">Purchase tax<select wire:model="billLines.{{ $index }}.finance_tax_rate_id" class="mt-1 w-full rounded border-gray-300"><option value="">No tax</option>@foreach ($taxRates->whereIn('applies_to', ['purchases', 'both']) as $rate)<option value="{{ $rate->id }}">{{ $rate->name }} ({{ $rate->rate }}%)</option>@endforeach</select></label><label class="text-sm">Cost centre<select wire:model="billLines.{{ $index }}.finance_cost_center_id" class="mt-1 w-full rounded border-gray-300"><option value="">Organization-wide / unassigned</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->name }}</option>@endforeach</select></label></div>@endforeach
                <button type="button" wire:click="addBillLine" class="rounded border px-3 py-2 text-sm">+ Add line</button><label class="block text-sm">Notes<textarea wire:model="billNotes" rows="2" class="mt-1 w-full rounded border-gray-300"></textarea></label><button wire:click="postBill" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Post supplier bill</button>
            </div>
                </div>
            </div>
        @endif
        @endif

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">Supplier bills</h2><p class="text-xs text-gray-500">Search and review posted bill balances</p></div><span class="text-sm text-gray-500">{{ $bills->total() }} records</span></div>
            <div class="mb-4 flex w-full flex-row items-center gap-3"><input type="search" wire:model.live.debounce.300ms="billSearch" placeholder="Search bill, supplier or supplier reference" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm"><select wire:model.live="billStatusFilter" class="w-44 shrink-0 rounded-lg border-gray-300 text-sm"><option value="outstanding">Outstanding</option><option value="all">All statuses</option><option value="posted">Unpaid</option><option value="partially_paid">Partially paid</option><option value="paid">Paid</option></select></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-gray-500"><th class="p-2">Bill</th><th class="p-2">Supplier</th><th class="p-2">Date</th><th class="p-2">Due</th><th class="p-2 text-right">Total</th><th class="p-2 text-right">Paid</th><th class="p-2 text-right">Balance</th></tr></thead><tbody>@forelse ($bills as $bill)<tr class="border-b"><td class="p-2 font-medium">{{ $bill->bill_number }}</td><td class="p-2">{{ $bill->supplier?->name }}</td><td class="p-2">{{ $bill->bill_date->format('d M Y') }}</td><td class="p-2">{{ $bill->due_date->format('d M Y') }}</td><td class="p-2 text-right">{{ $bill->total }}</td><td class="p-2 text-right">{{ $bill->paid_amount }}</td><td class="p-2 text-right font-semibold">{{ $bill->balance_due }}</td></tr>@empty<tr><td colspan="7" class="p-5 text-center text-gray-500">No bills match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $bills->links() }}</div></section>
        @endif

    @elseif ($activeTab === 'payments')
        @if ($canViewPayments)

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">Payment history</h2><p class="text-xs text-gray-500">Search receipts and supplier payments</p></div><span class="text-sm text-gray-500">{{ $payments->total() }} records</span></div><div class="mb-4 flex w-full flex-row items-center gap-3"><input type="search" wire:model.live.debounce.300ms="paymentSearch" placeholder="Search payment, reference, invoice or party" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm"><select wire:model.live="paymentDirectionFilter" class="w-44 shrink-0 rounded-lg border-gray-300 text-sm"><option value="all">All payment types</option><option value="customer_receipt">Customer receipts</option><option value="supplier_payment">Supplier payments</option></select></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-gray-500"><th class="p-2">Number</th><th class="p-2">Type</th><th class="p-2">Date</th><th class="p-2">Account</th><th class="p-2">Receipt</th><th class="p-2">Allocated to</th><th class="p-2 text-right">Amount</th></tr></thead><tbody>@forelse ($payments as $payment)<tr class="border-b"><td class="p-2">{{ $payment->payment_number }}</td><td class="p-2">{{ str_replace('_', ' ', $payment->direction) }}</td><td class="p-2">{{ $payment->payment_date->format('d M Y') }}</td><td class="p-2">{{ $payment->account?->name }}</td><td class="p-2">{{ $payment->receipt?->receipt_number ?? '—' }}</td><td class="p-2">@foreach ($payment->allocations as $allocation)<div>{{ $allocation->invoice?->invoice_number ?? $allocation->bill?->bill_number }} — {{ $allocation->amount }}</div>@endforeach</td><td class="p-2 text-right">{{ $payment->amount }}</td></tr>@empty<tr><td colspan="7" class="p-5 text-center text-gray-500">No payments match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $payments->links() }}</div></section>
@if ($canManagePayments)
        <div class="flex justify-end"><button type="button" wire:click="openModal('payment')" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">+ Record payment</button></div>
        @if ($activeModal === 'payment')
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Record payment">
                <div class="my-auto max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3 border-b pb-3"><h2 class="text-lg font-bold text-gray-900">Record payment</h2><button type="button" wire:click="closeModal" aria-label="Close dialog" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">&times;</button></div>
                    <div class="space-y-4"><div class="grid gap-3 md:grid-cols-3"><label class="text-sm">Direction<select wire:model.live="paymentDirection" class="mt-1 w-full rounded border-gray-300"><option value="customer_receipt">Customer receipt</option><option value="supplier_payment">Supplier payment</option></select></label><label class="text-sm">Payment date<input type="date" wire:model="paymentDate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Cash / bank account<select wire:model="paymentAccountId" class="mt-1 w-full rounded border-gray-300"><option value="">Select account</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label><label class="text-sm">Amount<input type="number" min="0.0001" step="0.0001" wire:model="paymentAmount" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Reference<input wire:model="paymentReference" class="mt-1 w-full rounded border-gray-300"></label>
                </div>
                <div class="space-y-2"><div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Payment allocations</h3><button type="button" wire:click="addPaymentAllocation" class="rounded border px-3 py-1.5 text-sm">+ Add allocation</button></div>
                    @foreach ($paymentAllocations as $index => $allocation)
                        <div class="grid gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-3" wire:key="payment-allocation-{{ $index }}">
                            <label class="text-sm md:col-span-2">{{ $paymentDirection === 'customer_receipt' ? 'Invoice' : 'Supplier bill' }}
                                <select wire:model="paymentAllocations.{{ $index }}.document_id" class="mt-1 w-full rounded border-gray-300"><option value="">Select document</option>
                                    @if ($paymentDirection === 'customer_receipt') @foreach ($invoiceOptions as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} — {{ $invoice->customer?->name }} — Outstanding {{ $invoice->balance_due }}</option>@endforeach
                                    @else @foreach ($billOptions as $bill)<option value="{{ $bill->id }}">{{ $bill->bill_number }} — {{ $bill->supplier?->name }} — Outstanding {{ $bill->balance_due }}</option>@endforeach @endif
                                </select>
                            </label>
                            <div class="flex items-end gap-2"><label class="flex-1 text-sm">Allocated amount<input type="number" min="0.0001" step="0.0001" wire:model="paymentAllocations.{{ $index }}.amount" class="mt-1 w-full rounded border-gray-300"></label><button type="button" wire:click="removePaymentAllocation({{ $index }})" class="rounded border px-3 py-2 text-sm">Remove</button></div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500">The total of all allocations must equal the payment amount. A payment can be split across multiple invoices or bills. Partial payments are supported.</p><button wire:click="recordPayment" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Post payment</button></div>
                </div>
            </div>
        @endif
        @endif
        @endif
    @endif
</div>

