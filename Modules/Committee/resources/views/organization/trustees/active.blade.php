@component('core::layouts.organization-admin', ['layoutOrganization' => $organization, 'pageTitle' => 'Active Trustees'])
<div class="committee-page">
    <header class="committee-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="committee-eyebrow">Committee management</p>
            <h1>Active Trustees</h1>
            <p class="committee-page-description">Current trustees of {{ $organization->name }}.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('committee.trustees.history') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">Former &amp; Inactive Trustees</a>
            <a href="{{ route('committee.trustees.export', request()->query()) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Export PDF</a>
        </div>
    </header>

    <section class="committee-form-card mt-5">
        <form method="GET" action="{{ route('committee.trustees.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] flex-1">
                <label for="trustee-search" class="mb-1 block text-sm font-medium">Search trustees</label>
                <input id="trustee-search" type="search" name="search" value="{{ $search }}" placeholder="Trustee ID, member name or membership number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
            @if($search !== '')
                <a href="{{ route('committee.trustees.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Clear</a>
            @endif
        </form>
    </section>

    <section class="committee-table-card mt-5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr><th class="px-4 py-3">Trustee ID</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Membership number</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Appointment date</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($trustees as $trustee)
                        @php($appointment = $trustee->appointments->first(fn ($item) => $item->status->value === 'active'))
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $trustee->trustee_id }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->user?->name ?: 'Unnamed member' }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->membership_number ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $trustee->member?->unit?->name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $appointment?->appointed_at?->format('d M Y') ?: '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Active</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No active trustees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($trustees->hasPages())<div class="border-t border-gray-200 px-4 py-3">{{ $trustees->links() }}</div>@endif
    </section>
</div>

@endcomponent
