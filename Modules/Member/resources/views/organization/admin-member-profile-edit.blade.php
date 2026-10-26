@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('member.profile', $memberRecord->id) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">&larr; Back to member profile</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Edit member profile</h1>
        <p class="mt-1 text-sm text-slate-500">Changes apply to this member's account in the current organization.</p>
    </div>

    @if (session('status'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('member.profile.update', $memberRecord->id) }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="flex items-center gap-4 border-b border-slate-100 pb-5">
            @if ($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="Profile photo of {{ $memberRecord->user?->name }}" class="h-16 w-16 rounded-full object-cover">
            @else
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-xl font-bold text-slate-500">{{ strtoupper(mb_substr($memberRecord->user?->name ?? '?', 0, 1)) }}</div>
            @endif
            <div><p class="font-semibold text-slate-900">{{ $memberRecord->user?->name }}</p><p class="text-sm text-slate-500">MID: {{ $memberRecord->membership_number ?: 'Not assigned' }}</p></div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Full name</label>
                <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $memberRecord->user?->name) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $memberRecord->user?->email) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
                <input id="phone" name="phone" type="text" maxlength="50" value="{{ old('phone', $memberRecord->user?->phone) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="date_of_birth" class="block text-sm font-medium text-slate-700">Date of birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $memberRecord->user?->details?->date_of_birth?->format('Y-m-d') ?? $memberRecord->user?->details?->date_of_birth) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                @error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="blood_group" class="block text-sm font-medium text-slate-700">Blood group</label>
                <select id="blood_group" name="blood_group" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    <option value="">Not provided</option>
                    @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group)
                        <option value="{{ $group }}" @selected(old('blood_group', $memberRecord->user?->details?->blood_group) === $group)>{{ $group }}</option>
                    @endforeach
                </select>
                @error('blood_group')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
            <a href="{{ route('member.profile', $memberRecord->id) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save changes</button>
        </div>
    </form>
</div>
@endcomponent
