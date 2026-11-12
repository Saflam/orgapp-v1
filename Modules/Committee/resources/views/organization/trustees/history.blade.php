@component('core::layouts.organization-admin', ['layoutOrganization' => $organization, 'pageTitle' => 'Former & Inactive Trustees'])
<div class="committee-page">
    <header class="committee-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="committee-eyebrow">Committee management</p>
            <h1>Former &amp; Inactive Trustees</h1>
            <p class="committee-page-description">Historical trustee records, including removals and automatic deactivation after a member status change.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('committee.trustees.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">Active Trustees</a>
            <a href="{{ route('committee.trustees.history.export', request()->query()) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Export PDF</a>
        </div>
    </header>

    <nav class="mt-5 flex flex-wrap gap-2" aria-label="Trustee history filters">
        @foreach(['all' => 'All history', 'removed' => 'Former trustees', 'inactive' => 'Inactive trustees'] as $value => $label)
            <a href="{{ route('committee.trustees.history', array_merge(request()->query(), ['status' => $value, 'page' => null])) }}" class="rounded-full border px-4 py-2 text-sm font-medium {{ $historyStatus === $value ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="committee-form-card mt-5">
        <form method="GET" action="{{ route('committee.trustees.history') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="status" value="{{ $historyStatus }}">
            <div class="min-w-[240px] flex-1">
                <label for="trustee-history-search" class="mb-1 block text-sm font-medium">Search trustees</label>
                <input id="trustee-history-search" type="search" name="search" value="{{ $search }}" placeholder="Trustee ID, member name or membership number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
            @if($search !== '')<a href="{{ route('committee.trustees.history', ['status' => $historyStatus]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Clear</a>@endif
        </form>
    </section>

    <section class="committee-table-card mt-5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr><th class="px-4 py-3">Trustee ID</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Membership number</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Last appointment</th><th class="px-4 py-3">End date</th><th class="px-4 py-3">Record status</th><th class="px-4 py-3">Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($trustees as $trustee)
                        @php($appointment = $trustee->appointments->first())
                        @php($appointmentStatus = $appointment?->status?->value ?? 'inactive')
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $trustee->trustee_id }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->user?->name ?: 'Unnamed member' }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->membership_number ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->unit?->name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $appointment?->appointed_at?->format('d M Y') ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $appointment?->ended_at?->format('d M Y') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($appointmentStatus === 'removed')<span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Former</span>
                                @else<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Inactive</span>@endif
                            </td>
                            <td class="max-w-sm whitespace-normal px-4 py-3">{{ $appointment?->end_reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No former or inactive trustees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($trustees->hasPages())<div class="border-t border-gray-200 px-4 py-3">{{ $trustees->links() }}</div>@endif
    </section>
</div>

@endcomponent
