@extends('layouts.app')

@section('title', 'Verify sign-in code — Saflivo')
@section('description', 'Verify your Saflivo sign-in with the one-time code sent to your email.')

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
                <x-ui.badge tone="success">One more step</x-ui.badge>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Check your email</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Enter the six-digit code sent to <span class="font-semibold text-slate-800">{{ $email }}</span>.</p>
            </div>
            @if (session('status'))
                <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">The code could not be verified.</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('login.verify') }}" class="space-y-5">
                @csrf
                <x-ui.field label="Six-digit code" name="code" type="text" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="000000" :error="$errors->first('code')" />
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                    <span>
                        <span class="font-semibold">Keep me signed in on this browser</span>
                        <span class="mt-0.5 block text-xs leading-5 text-slate-500">Use this on your personal device. You will remain signed in after the normal session expires until you sign out.</span>
                    </span>
                </label>
                <x-ui.button type="submit" size="lg" class="w-full">Verify and sign in <span aria-hidden="true">→</span></x-ui.button>
            </form>
            <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                <p class="text-sm text-slate-500">Didn't receive the code?</p>
                <a href="{{ route('login') }}" class="mt-1 inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-emerald-900 hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">Try another email address</a>
            </div>
        </x-ui.card>
    </main>
</div>
@endsection
