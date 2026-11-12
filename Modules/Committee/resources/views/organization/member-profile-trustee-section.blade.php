<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Trustee identity and appointment history for this member.</p>
        </div>
        @if($activeTrusteeAppointment)
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Current trustee</span>
        @elseif($trusteeRecord)
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Former trustee</span>
        @else
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Not a trustee</span>
        @endif
    </div>

    @if($errors->has('trustee'))
        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $errors->first('trustee') }}</p>
    @endif

    @if($trusteeRecord)
        <div class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-3">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trustee ID</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $trusteeRecord->trustee_id }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest appointment</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $trusteeAppointmentHistory->first()?->appointed_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Appointment status</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeTrusteeAppointment ? 'Active' : 'Inactive' }}</dd></div>
        </div>
    @else
        <p class="mt-4 text-sm text-slate-600">This member has not been appointed as a trustee.</p>
    @endif

    @if($canManageTrustees && !$activeTrusteeAppointment && $memberEligibleForTrustee)
        <div class="mt-5 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
            <h4 class="text-sm font-bold text-slate-900">{{ $trusteeRecord ? 'Reappoint as trustee' : 'Appoint as trustee' }}</h4>
            <p class="mt-1 text-xs text-slate-600">{{ $trusteeRecord ? 'The original Trustee ID will be retained.' : 'Enter a numeric Trustee ID that is not already assigned in this organization.' }}</p>
            <form method="POST" action="{{ route('committee.trustees.appoint', $member->id) }}" class="mt-4 grid gap-3 sm:grid-cols-3">
                @csrf
                <div>
                    <label for="trustee_id" class="mb-1 block text-xs font-semibold text-slate-700">Trustee ID</label>
                    <input id="trustee_id" name="trustee_id" type="number" min="1" step="1" required value="{{ old('trustee_id', $trusteeRecord?->trustee_id) }}" @if($trusteeRecord) readonly @endif class="w-full rounded-lg border-slate-300 text-sm @if($trusteeRecord) bg-slate-100 @endif">
                </div>
                <div>
                    <label for="trustee_appointed_at" class="mb-1 block text-xs font-semibold text-slate-700">Appointment date</label>
                    <input id="trustee_appointed_at" name="appointed_at" type="date" required value="{{ old('appointed_at', today()->toDateString()) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">{{ $trusteeRecord ? 'Reappoint trustee' : 'Appoint trustee' }}</button>
                </div>
            </form>
        </div>
    @elseif($canManageTrustees && !$memberEligibleForTrustee && !$activeTrusteeAppointment)
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Only a member with active membership can be appointed as a trustee.</p>
    @endif

    @if($trusteeAppointmentHistory->isNotEmpty())
        <h4 class="mt-6 text-sm font-bold text-slate-900">Appointment history</h4>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-3 pr-4">Appointed</th><th class="py-3 pr-4">Ended</th><th class="py-3 pr-4">Status</th><th class="py-3 pr-4">Reason</th>@if($canManageTrustees)<th class="py-3">Action</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($trusteeAppointmentHistory as $appointment)
                        <tr>
                            <td class="py-3 pr-4">{{ $appointment->appointed_at?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $appointment->ended_at?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3 pr-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $appointment->status->value === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ ucfirst($appointment->status->value) }}</span></td>
                            <td class="max-w-sm whitespace-pre-line py-3 pr-4">{{ $appointment->end_reason ?: '—' }}</td>
                            @if($canManageTrustees)
                                <td class="py-3">
                                    @if($appointment->status->value === 'active')
                                        <details class="min-w-56">
                                            <summary class="cursor-pointer text-xs font-semibold text-red-700">Remove trustee</summary>
                                            <form method="POST" action="{{ route('committee.trustees.remove', [$member->id, $appointment->id]) }}" class="mt-2 space-y-2 rounded-lg border border-red-200 bg-red-50 p-3">
                                                @csrf
                                                <label class="block text-xs font-semibold text-slate-700">Removal date</label>
                                                <input type="date" name="ended_at" required value="{{ today()->toDateString() }}" class="w-full rounded-lg border-slate-300 text-xs">
                                                <label class="block text-xs font-semibold text-slate-700">Reason for removal</label>
                                                <textarea name="reason" rows="3" required minlength="3" maxlength="2000" class="w-full rounded-lg border-slate-300 text-xs" placeholder="Enter the reason"></textarea>
                                                <button type="submit" class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white">Confirm removal</button>
                                            </form>
                                        </details>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
