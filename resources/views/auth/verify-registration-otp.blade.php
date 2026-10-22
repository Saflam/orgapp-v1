@extends('layouts.app')

@section('title', 'Verify account — ' . $organization->name)
@section('description', 'Verify your email address to finish creating your Saflivo account.')

@section('content')
<div class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_left,_rgba(190,220,194,0.55),_transparent_48%),linear-gradient(180deg,#f7f8f2_0%,#fafaf8_100%)]"></div>
    <main class="w-full max-w-md">
        <a href="{{ route('organization.home') }}" class="mx-auto mb-8 block w-fit text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-4">
            <span class="block text-2xl font-semibold tracking-tight text-slate-950">{{ $organization->name }}</span>
            <span class="mt-1 block text-sm text-slate-500">Saflivo account verification</span>
        </a>
        <x-ui.card padding="lg" class="shadow-[0_24px_80px_-36px_rgba(15,50,40,0.28)]">
            <div class="mb-7">
                <x-ui.badge tone="success">Verify email</x-ui.badge>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Enter your verification code</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">We sent a six-digit code to <span class="font-medium text-slate-900">{{ $email }}</span>. Verify your account to continue.</p>
            </div>
            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('organization.register.verify') }}" class="space-y-5">
                @csrf
                <x-ui.field label="Six-digit code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code" placeholder="000000" :error="$errors->first('code')" />
                <x-ui.button type="submit" size="lg" class="w-full">Verify and continue <span aria-hidden="true">→</span></x-ui.button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-600"><a href="{{ route('organization.register') }}" class="font-semibold text-emerald-900 underline-offset-4 hover:underline">Start over</a></p>
        </x-ui.card>
    </main>
</div>
@endsection
