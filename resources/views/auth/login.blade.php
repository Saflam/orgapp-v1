@extends('layouts.app')

@section('title', 'Sign in — Saflivo')
@section('description', 'Sign in to Saflivo securely using a one-time code sent to your email.')

@section('content')
<div class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_left,_rgba(190,220,194,0.55),_transparent_48%),linear-gradient(180deg,#f7f8f2_0%,#fafaf8_100%)]"></div>
    <main class="w-full max-w-md">
        <a href="/" class="mx-auto mb-8 flex w-fit items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-4" aria-label="Back to Saflivo home">
            <span class="flex size-11 items-center justify-center rounded-2xl bg-emerald-950 text-lg font-bold text-white shadow-sm">S</span>
            <span class="text-2xl font-semibold tracking-tight text-slate-950">saflivo<span class="text-emerald-700">.</span></span>
        </a>
        <x-ui.card padding="lg" class="shadow-[0_24px_80px_-36px_rgba(15,50,40,0.28)]">
            <div class="mb-7">
                <x-ui.badge tone="info">Secure sign in</x-ui.badge>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Welcome back</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Enter the email address associated with your account. We'll send you a one-time sign-in code.</p>
            </div>
            @if (session('status'))
                <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">We couldn't continue.</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('login.request-otp') }}" class="space-y-5">
                @csrf
                <x-ui.field label="Email address" name="email" type="email" required autocomplete="email" placeholder="you@example.com" :error="$errors->first('email')" />
                <x-ui.button type="submit" size="lg" class="w-full">Send sign-in code <span aria-hidden="true">→</span></x-ui.button>
            </form>
            <p class="mt-6 text-center text-xs leading-5 text-slate-500">Your account access is checked by Saflivo. Organization access is still enforced separately.</p>
        </x-ui.card>
        <p class="mt-6 text-center text-sm text-slate-500"><a href="/" class="font-medium text-emerald-900 underline-offset-4 hover:underline">Return to Saflivo home</a></p>
    </main>
</div>
@endsection
