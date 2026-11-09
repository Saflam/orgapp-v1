@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('member.profile', $memberRecord->id) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">&larr; Back to member profile</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Edit Membership Details</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $memberRecord->user?->name ?? 'Member' }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('member.profile.membership.update', $memberRecord->id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Membership details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="membership_number" class="block text-sm font-semibold text-slate-700">Membership number <span class="text-red-600">*</span></label>
                    <input id="membership_number" name="membership_number" type="text" required maxlength="255"
                        value="{{ old('membership_number', $memberRecord->membership_number) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('membership_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-semibold text-slate-700">Membership status</label>
                    @if($membershipRecord)
                        @php
                            $recordStatus = $membershipRecord->status;
                            $recordStatus = is_object($recordStatus) && property_exists($recordStatus, 'value') ? $recordStatus->value : (string) $recordStatus;
                        @endphp
                        <select id="status" name="status" data-status-select
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $recordStatus) === $status->value)>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Changing status updates the latest membership record.</p>
                        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @else
                        <input type="text" value="No membership record" disabled
                            class="mt-1 block w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500">
                        <p class="mt-1 text-xs text-slate-500">The membership number can be updated now. A membership record must exist before a status can be changed.</p>
                    @endif
                </div>

                @if($membershipRecord)
                    <div class="sm:col-span-2" data-status-reason-wrap>
                        <label for="status_reason" class="block text-sm font-semibold text-slate-700">Reason / notes <span data-reason-required class="text-red-600 hidden">*</span></label>
                        <textarea id="status_reason" name="status_reason" rows="4" maxlength="2000"
                            placeholder="Required when suspending a membership or marking the member as deceased."
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('status_reason', $membershipRecord->status_reason) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">Provide a reason for suspension or deceased status. The reason will be visible in membership details and history.</p>
                        @error('status_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('member.profile', $memberRecord->id) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Save Membership Details</button>
        </div>
    </form>
</div>

@if($membershipRecord)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.querySelector('[data-status-select]');
    const reason = document.getElementById('status_reason');
    const requiredMark = document.querySelector('[data-reason-required]');

    if (!statusSelect || !reason) return;

    function updateReasonRequirement() {
        const required = ['suspended', 'deceased'].includes(statusSelect.value);
        reason.required = required;
        if (requiredMark) requiredMark.classList.toggle('hidden', !required);
    }

    statusSelect.addEventListener('change', updateReasonRequirement);
    updateReasonRequirement();
});
</script>
@endif
@endcomponent
