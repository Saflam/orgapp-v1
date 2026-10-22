@extends('layouts.app')
@section('title', 'Member Profile — ' . $organization->name)
@push('head')
    <meta name="theme-color" content="{{ $primaryColor }}">
@endpush
@section('content')
<div class="min-h-screen bg-stone-50" style="--org-primary: {{ $primaryColor }}">
    @include('organization.partials.header')
    <main class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider" style="color: var(--org-primary)">Member area</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Your Member Profile</h1>
        <p class="mt-3 text-slate-600">View your account details. Profile editing and membership-specific information can be added here in the next member UI iteration.</p>
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <dl class="grid gap-5 sm:grid-cols-2"><div><dt class="text-sm text-slate-500">Name</dt><dd class="mt-1 font-semibold text-slate-900">{{ $user->name }}</dd></div><div><dt class="text-sm text-slate-500">Email</dt><dd class="mt-1 font-semibold text-slate-900">{{ $user->email }}</dd></div></dl>
        </section>
    </main>
</div>
@endsection
