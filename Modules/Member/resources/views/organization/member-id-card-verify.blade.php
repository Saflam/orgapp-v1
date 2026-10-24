@extends('layouts.app')
@section('title', 'Membership Verification — ' . $organization->name)
@section('content')
<div class="min-h-screen bg-stone-50 px-4 py-8 sm:px-5 sm:py-12">
    <main class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="text-sm font-semibold uppercase tracking-wider" style="color: {{ $primaryColor }}">Membership verification</p>
        <h1 class="mt-2 text-2xl font-semibold">{{ $organization->name }}</h1>
        @if($dependant)
            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">Dependant</p>
                <p class="mt-1 text-lg font-semibold">{{ trim($dependant->first_name.' '.$dependant->middle_name.' '.$dependant->last_name) }}</p>
                <p class="mt-3 text-sm text-slate-600">Dependant ID: DEP-{{ str_pad((string)$dependant->id, 6, '0', STR_PAD_LEFT) }}</p>
                <p class="mt-2 text-sm text-slate-600">Relationship: {{ ucfirst(str_replace('_', ' ', $dependant->pivot->relationship_type ?? 'dependant')) }}</p>
                <p class="mt-2 text-sm text-slate-600">Date of birth: {{ $dependant->date_of_birth?->format('d/m/Y') ?: '—' }}</p>
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Primary member</p>
                    <p class="mt-1 font-semibold">{{ $member->user?->name }}</p>
                    <p class="mt-2 text-sm text-slate-600">Membership number: {{ $member->membership_number ?: 'Not assigned' }}</p>
                </div>
            </div>
        @else
            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">Member</p>
                <p class="mt-1 text-lg font-semibold">{{ $member->user?->name }}</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Membership number</p>
                <p class="mt-1 font-semibold">{{ $member->membership_number ?: 'Not assigned' }}</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Unit</p>
                <p class="mt-1 font-semibold">{{ $member->unit?->name ?? 'Not assigned' }}</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Status</p>
                <p class="mt-1 text-lg font-semibold capitalize">{{ str_replace('_', ' ', $membership?->status?->value ?? $membership?->status ?? 'not assigned') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ $membership?->membershipType?->name ?? 'Membership type unavailable' }}</p>
            </div>
        @endif
        <p class="mt-5 text-xs text-slate-500">This record is shown because you are signed in and have permission to view members in this organization. Contact details and payment history are not displayed here.</p>
    </main>
</div>
@endsection
