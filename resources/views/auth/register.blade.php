@extends('layouts.app')

@section('title', 'Create account — ' . $organization->name)
@section('description', 'Create your Saflivo user account to access ' . $organization->name . '.')

@section('content')
<div class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_left,_rgba(190,220,194,0.55),_transparent_48%),linear-gradient(180deg,#f7f8f2_0%,#fafaf8_100%)]"></div>
    <main class="w-full max-w-md">
        <a href="{{ route('organization.home') }}" class="mx-auto mb-8 block w-fit text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-4">
            <span class="block text-2xl font-semibold tracking-tight text-slate-950">{{ $organization->name }}</span>
            <span class="mt-1 block text-sm text-slate-500">Create your Saflivo account</span>
        </a>
        <x-ui.card padding="lg" class="shadow-[0_24px_80px_-36px_rgba(15,50,40,0.28)]">
            <div class="mb-7">
                <x-ui.badge tone="info">New user registration</x-ui.badge>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Create your account</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Your Saflivo account can be used across organizations. Registering here grants access to {{ $organization->name }}; membership in this organization is a separate process.</p>
            </div>
            @if (session('status'))
                <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">We couldn't create your account.</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                    @if ($errors->has('email'))
                        <p class="mt-3"><a class="font-semibold underline underline-offset-2" href="{{ route('login') }}">Go to sign in</a></p>
                    @endif
                </div>
            @endif
            <form method="POST" action="{{ route('organization.register.store') }}" class="space-y-5">
                @csrf
                <x-ui.field label="Full name" name="name" type="text" required autocomplete="name" placeholder="Your full name" :value="old('name')" :error="$errors->first('name')" />
                <x-ui.field label="Email address" name="email" type="email" required autocomplete="email" placeholder="you@example.com" :value="old('email')" :error="$errors->first('email')" />
                <div>
                    <label for="phone_country_iso2" class="mb-1.5 block text-sm font-medium text-slate-700">Country</label>
                    <select id="phone_country_iso2" name="phone_country_iso2" required autocomplete="tel-country-code" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                        @foreach ($phoneCountries as $country)
                            <option value="{{ $country['iso2'] }}" data-calling-code="{{ $country['calling_code'] }}" @selected(old('phone_country_iso2', $defaultPhoneCountry) === $country['iso2'])>
                                {{ $country['label'] }} (+{{ $country['calling_code'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('phone_country_iso2')<p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700">Phone number <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel-national" value="{{ old('phone') }}" placeholder="Your phone number" aria-describedby="phone-help phone-error" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                    <p id="phone-help" class="mt-1.5 text-xs leading-5 text-slate-500">Choose the country for a local number. You can also enter an international number with its country code, such as +91.</p>
                    @if ($errors->has('phone'))<p id="phone-error" class="mt-1.5 text-sm text-rose-700">{{ $errors->first('phone') }}</p>@endif
                </div>
                <x-ui.button type="submit" size="lg" class="w-full">Send verification code <span aria-hidden="true">→</span></x-ui.button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-600">Already registered? <a href="{{ route('login') }}" class="font-semibold text-emerald-900 underline-offset-4 hover:underline">Sign in</a></p>
        </x-ui.card>
        <p class="mt-6 text-center text-sm text-slate-500"><a href="{{ route('organization.home') }}" class="font-medium text-emerald-900 underline-offset-4 hover:underline">Return to {{ $organization->name }}</a></p>
    </main>
</div>
<script>
    (() => {
        const country = document.getElementById('phone_country_iso2');
        const phone = document.getElementById('phone');
        if (!country || !phone) return;

        phone.addEventListener('input', () => {
            let value = phone.value.trim();
            if (value.startsWith('00')) value = value.slice(2);
            else if (value.startsWith('+')) value = value.slice(1);
            else return;

            const digits = value.replace(/\D/g, '');
            if (!digits) return;
            const options = Array.from(country.options)
                .filter((option) => digits.startsWith(option.dataset.callingCode || ''))
                .sort((a, b) => (b.dataset.callingCode || '').length - (a.dataset.callingCode || '').length);
            if (!options.length) return;
            const selectedMatch = options.find((option) => option.value === country.value);
            country.value = (selectedMatch || options.find((option) => option.value === 'US') || options[0]).value;
        });
    })();
</script>
@endsection
