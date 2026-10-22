@extends('layouts.app')
@section('title', 'Membership application status — ' . $organization->name)
@section('content')
<div class="min-h-screen bg-stone-50">
    @include('organization.partials.header')
    <main class="mx-auto max-w-2xl px-5 py-12 sm:px-8">
        <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-9">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800">✓</div>
            <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-950">Your membership application is already on file</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">We have received your application. You do not need to submit another one. The organization will notify you when its status changes.</p>
            <dl class="mt-6 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Application reference</dt><dd class="mt-1 font-semibold">#{{ $application->id }}</dd></div><div><dt class="text-xs text-slate-500">Current status</dt><dd class="mt-1 font-semibold">{{ str($application->status->value)->replace('_', ' ')->title() }}</dd></div></dl>
            <a href="{{ route('organization.home') }}" class="mt-7 inline-flex min-h-12 items-center rounded-full px-5 text-sm font-semibold text-white" style="background: {{ $primaryColor }}">Return to home</a>
        </section>
    </main>
</div>
@endsection
