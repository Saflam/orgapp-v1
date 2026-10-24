@extends('layouts.app')
@section('title', 'Membership application status — ' . $organization->name)
@section('content')
<div class="min-h-screen bg-stone-50">
    @include('organization.partials.header')
    <main class="mx-auto max-w-2xl px-5 py-12 sm:px-8">
        <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-9">
            @if($application->status === \Modules\Member\Enums\MembershipApplicationStatus::REJECTED)
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-700" aria-hidden="true">!</div>
                <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-950">Your membership application was rejected</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">The organization has reviewed your application and recorded the following reason.</p>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4" role="status">
                    <h2 class="text-sm font-bold text-red-900">Rejection reason</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-900">{{ filled($rejectionReason ?? null) ? $rejectionReason : 'No reason was provided. Please contact the organization for clarification.' }}</p>
                </div>
            @else
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800">✓</div>
                <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-950">Your membership application is already on file</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">We have received your application. You do not need to submit another one. The organization will notify you when its status changes.</p>
            @endif
            <dl class="mt-6 grid gap-4 rounded-2xl bg-stone-50 p-4 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Application reference</dt><dd class="mt-1 font-semibold">#{{ $application->id }}</dd></div><div><dt class="text-xs text-slate-500">Current status</dt><dd class="mt-1 font-semibold">{{ str($application->status->value)->replace('_', ' ')->title() }}</dd></div></dl>
            @if(($spouseRequest ?? null) && $spouseRequest->status === 'pending')
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-950">Spouse request pending acceptance</p>
                    <p class="mt-1 text-sm text-amber-900">The selected member has not accepted your spouse request. No relationship has been created.</p>
                    <form method="post" action="{{ route('organization.member.spouse-requests.cancel', $spouseRequest->id) }}" class="mt-3" onsubmit="return confirm('Cancel this spouse request?')">
                        @csrf
                        <button class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-950">Cancel spouse request</button>
                    </form>
                </div>
            @endif
            <div class="mt-7 flex flex-wrap gap-3">
                @if($application->status === \Modules\Member\Enums\MembershipApplicationStatus::REJECTED && ($canReapply ?? false))
                    <a href="{{ route('organization.membership.apply', ['reapply' => 1]) }}" class="inline-flex min-h-12 items-center rounded-full px-5 text-sm font-semibold text-white" style="background: {{ $primaryColor }}">Re-apply for membership</a>
                @endif
                <a href="{{ route('organization.home') }}" class="inline-flex min-h-12 items-center rounded-full border border-stone-300 px-5 text-sm font-semibold text-slate-700">Return to home</a>
            </div>
        </section>
    </main>
</div>
@endsection
