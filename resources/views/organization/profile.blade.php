@extends('layouts.app')
@section('title', 'Profile — ' . $organization->name)
@push('head')
    <meta name="theme-color" content="{{ $primaryColor }}">
@endpush
@section('content')
<div class="min-h-screen bg-stone-50" style="--org-primary: {{ $primaryColor }}">
    @include('organization.partials.header')
    <main class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider" style="color: var(--org-primary)">Account</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Your Profile</h1>
        <p class="mt-3 text-slate-600">Your account details for {{ $organization->name }}.</p>
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <dl class="grid gap-5 sm:grid-cols-2"><div><dt class="text-sm text-slate-500">Name</dt><dd class="mt-1 font-semibold text-slate-900">{{ $user->name }}</dd></div><div><dt class="text-sm text-slate-500">Email</dt><dd class="mt-1 font-semibold text-slate-900">{{ $user->email }}</dd></div></dl>
        </section>
        @if ($isMember)
            <a href="{{ route('organization.member.profile') }}" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">View Member Profile</a>
        @elseif ($hasDraftMembershipApplication ?? false)
            <a href="{{ route('organization.membership.apply') }}" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">Continue Application</a>
        @elseif ($hasPendingMembershipApplication)
            <a href="{{ route('organization.membership.apply') }}" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">View Application Status</a>
        @else
            <a href="{{ route('organization.membership.apply') }}" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">Apply Membership</a>
        @endif
    </main>
</div>
@endsection
