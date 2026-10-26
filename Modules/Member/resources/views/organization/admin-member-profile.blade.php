@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('member.directory') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">&larr; Back to members</a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Member profile</h1>
            <p class="mt-1 text-sm text-slate-500">View member information within your authorized organization and unit scope.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('member.profile.pdf', $memberRecord->id) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Download PDF</a>
            @if($canEdit)
                <a href="{{ route('member.profile.edit', $memberRecord->id) }}" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Edit profile</a>
            @endif
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center">
            @if($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="Profile photo of {{ $memberRecord->user?->name }}" class="h-24 w-24 rounded-full object-cover ring-4 ring-slate-100">
            @else
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-slate-100 text-2xl font-bold text-slate-500">{{ strtoupper(mb_substr($memberRecord->user?->name ?? '?', 0, 1)) }}</div>
            @endif
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-slate-900">{{ $memberRecord->user?->name ?? 'Unnamed member' }}</h2>
                <p class="mt-1 text-sm text-slate-500">MID: {{ $memberRecord->membership_number ?: 'Not assigned' }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $memberRecord->unit?->name ?? 'No unit assigned' }}</span>
                    @if($activeMembership)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-800">Active member</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-800">No active membership</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-2">
            <section>
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Contact information</h3>
                <dl class="mt-4 space-y-4">
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Email</dt><dd class="mt-1 break-words text-sm text-slate-800">@if($memberRecord->user?->email)<a class="hover:underline" href="mailto:{{ $memberRecord->user->email }}">{{ $memberRecord->user->email }}</a>@else Not provided @endif</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Phone</dt><dd class="mt-1 text-sm text-slate-800">@if($memberRecord->user?->phone)<a class="hover:underline" href="tel:{{ $memberRecord->user->phone }}">{{ $memberRecord->user->phone }}</a>@else Not provided @endif</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Date joined</dt><dd class="mt-1 text-sm text-slate-800">{{ $memberRecord->joined_at?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                </dl>
            </section>

            <section>
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Membership</h3>
                <dl class="mt-4 space-y-4">
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Membership type</dt><dd class="mt-1 text-sm text-slate-800">{{ $activeMembership?->membershipType?->name ?? 'Not available' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Membership start</dt><dd class="mt-1 text-sm text-slate-800">{{ $activeMembership?->starts_at?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-400">Membership end</dt><dd class="mt-1 text-sm text-slate-800">{{ $activeMembership?->ends_at?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                </dl>
            </section>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Addresses</h3>
        @forelse($memberRecord->user?->addresses ?? collect() as $address)
            <div class="mt-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                <p class="text-sm font-semibold capitalize text-slate-800">{{ str_replace('_', ' ', $address->address_type ?? 'Address') }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ collect([$address->address_line_1, $address->address_line_2, $address->locality, $address->city, $address->state, $address->postal_code, $address->country])->filter()->implode(', ') ?: 'No address details' }}</p>
            </div>
        @empty
            <p class="mt-3 text-sm text-slate-500">No addresses recorded.</p>
        @endforelse
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Admin notes</h3>
                <p class="mt-1 text-sm text-slate-500">Internal notes visible to administrators authorized to view this member profile.</p>
            </div>
            @if($canEdit)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Internal · not visible to members</span>
            @endif
        </div>

        @if($canEdit)
            <form method="POST" action="{{ route('member.profile.notes.store', $memberRecord->id) }}" class="mt-5 space-y-3">
                @csrf
                <label for="admin-note-body" class="block text-sm font-medium text-slate-700">Add a note</label>
                <textarea id="admin-note-body" name="body" rows="3" maxlength="5000" required class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600" placeholder="Enter an internal note about this member..."></textarea>
                @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Add note</button>
                </div>
            </form>
        @endif

        @if(session('status'))
            <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
        @endif

        <div class="mt-5 space-y-4">
            @forelse($adminNotes as $note)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $note->author?->name ?? 'Former administrator' }}</p>
                        <time class="text-xs text-slate-500" datetime="{{ $note->created_at?->toIso8601String() }}">{{ $note->created_at?->format('d M Y, h:i A') }}</time>
                    </div>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $note->body }}</p>
                </article>
            @empty
                <p class="text-sm text-slate-500">No admin notes have been added.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Membership history</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-3 pr-4 font-semibold">Type</th><th class="py-3 pr-4 font-semibold">Status</th><th class="py-3 pr-4 font-semibold">Start</th><th class="py-3 font-semibold">End</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($memberRecord->memberships->sortByDesc('starts_at') as $membership)
                    <tr><td class="py-3 pr-4 text-slate-800">{{ $membership->membershipType?->name ?? 'Membership' }}</td><td class="py-3 pr-4 capitalize text-slate-700">{{ $membership->status }}</td><td class="py-3 pr-4 text-slate-600">{{ $membership->starts_at?->format('d M Y') ?? '—' }}</td><td class="py-3 text-slate-600">{{ $membership->ends_at?->format('d M Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">No membership records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endcomponent
