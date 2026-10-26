@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Membership payments</h1>
            <p class="mt-1 text-sm text-slate-600">Review recurring dues, record collections, and inspect recent payment history within your authorized organization and unit scope.</p>
        </div>
        <a href="{{ route('member.directory') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Member directory</a>
    </div>

    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please review the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" action="{{ route('member.payments.index') }}" class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label class="min-w-[16rem] flex-1">
            <span class="mb-1 block text-sm font-medium text-slate-700">Search member, MID, payment category or receipt</span>
            <input name="search" value="{{ $search }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" placeholder="Name, MID, category, receipt number">
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Search</button>
            <a href="{{ route('member.payments.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
        </div>
    </form>

    <section class="space-y-4">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Outstanding recurring dues</h2>
                <p class="text-sm text-slate-500">Select one or more outstanding obligations for the same member to record a collection.</p>
            </div>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800">{{ $obligationsByMember->flatten(1)->count() }} outstanding</span>
        </div>

        @forelse($obligationsByMember as $memberId => $obligations)
            @php
                $member = $obligations->first()->member;
                $displayName = $member?->user?->name ?? 'Member #'.$memberId;
                $remainingTotal = $obligations->sum(fn ($obligation) => max(0, (float) $obligation->payable_amount - (float) $obligation->paid_amount));
            @endphp
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $displayName }}</h3>
                        <p class="mt-1 text-sm text-slate-500">MID: {{ $member?->membership_number ?: 'Not assigned' }} · Unit: {{ $member?->unit?->name ?? 'No unit' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding total</p>
                        <p class="text-lg font-bold text-slate-900">{{ $obligations->first()->currency }} {{ number_format($remainingTotal, 2) }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('member.payments.collect', $memberId) }}" class="p-5">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="px-3 py-3">Select</th><th class="px-3 py-3">Payment category</th><th class="px-3 py-3">Due date</th><th class="px-3 py-3 text-right">Payable</th><th class="px-3 py-3 text-right">Paid</th><th class="px-3 py-3 text-right">Balance</th><th class="px-3 py-3">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($obligations as $obligation)
                                    @php $balance = max(0, (float) $obligation->payable_amount - (float) $obligation->paid_amount); @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-3"><input type="checkbox" name="obligation_ids[]" value="{{ $obligation->id }}" class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"></td>
                                        <td class="px-3 py-3 font-medium text-slate-800">{{ $obligation->item_title }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $obligation->due_at?->format('d M Y') ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ $obligation->currency }} {{ number_format((float) $obligation->payable_amount, 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ $obligation->currency }} {{ number_format((float) $obligation->paid_amount, 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums">{{ $obligation->currency }} {{ number_format($balance, 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">{{ str($obligation->status->value)->replace('_', ' ')->title() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <label>
                            <span class="mb-1 block text-sm font-medium text-slate-700">Payment method</span>
                            <select name="payment_method" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">Select method</option><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="online">Online</option><option value="other">Other</option>
                            </select>
                        </label>
                        <label class="sm:col-span-1">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Transaction reference (optional)</span>
                            <input name="reference" maxlength="150" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" placeholder="Bank / cheque / transaction ID">
                        </label>
                        <div class="flex items-end">
                            <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800" onclick="return confirm('Record payment for the selected obligations? This action creates a payment record and receipt number.');">Record selected payment</button>
                        </div>
                    </div>
                </form>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                <p class="font-semibold text-slate-800">No outstanding recurring dues found</p>
                <p class="mt-1 text-sm text-slate-500">Try clearing the search or check back after recurring obligations have been generated.</p>
            </div>
        @endforelse
    </section>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Recent payment history</h2>
            <p class="text-sm text-slate-500">The latest 100 payment records visible to your current permission scope.</p>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Received</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Receipt number</th><th class="px-4 py-3">Method / reference</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Collected by</th><th class="px-4 py-3">Items</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($paymentsHistory as $payment)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->received_at?->format('d M Y, h:i A') ?? '—' }}</td>
                            <td class="px-4 py-3"><div class="font-medium text-slate-800">{{ $payment->member?->user?->name ?? 'Member' }}</div><div class="text-xs text-slate-500">{{ $payment->member?->membership_number ?? 'No MID' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-700">{{ $payment->receipt_number }}</td>
                            <td class="px-4 py-3"><div>{{ str($payment->payment_method ?? '—')->replace('_', ' ')->title() }}</div><div class="text-xs text-slate-500">{{ $payment->reference ?: 'No reference' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $payment->receivedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $payment->lines->map(fn ($line) => $line->obligation?->item_title)->filter()->unique()->implode(', ') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payment records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endcomponent
