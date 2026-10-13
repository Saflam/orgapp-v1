@extends('layouts.app')

@section('title', 'UI components — Saflivo')
@section('description', 'Local-only preview of Saflivo reusable interface components.')

@section('content')
<div class="min-h-screen bg-stone-50">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
            <a href="/" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700" aria-label="Saflivo home">
                <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-950 font-bold text-white">S</span>
                <span class="text-lg font-semibold tracking-tight text-slate-950">saflivo<span class="text-emerald-700">.</span></span>
            </a>
            <x-ui.badge tone="warning">Local preview only</x-ui.badge>
        </div>
    </header>
    <main class="mx-auto max-w-6xl space-y-8 px-5 py-10 sm:px-8 sm:py-14">
        <div class="max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">Design system · Revision 2</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">A consistent foundation for every workspace.</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">These shared Blade components will help keep forms, actions, status indicators, and content panels consistent across the Saflivo system and organization experiences.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="text-base font-semibold text-slate-950">Buttons</h2>
                <p class="mt-1 text-sm text-slate-500">Consistent actions with visible keyboard focus.</p>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <x-ui.button>Primary action</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="quiet">Quiet action</x-ui.button>
                    <x-ui.button variant="danger">Destructive</x-ui.button>
                    <x-ui.button size="sm" variant="secondary">Compact</x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card>
                <h2 class="text-base font-semibold text-slate-950">Status badges</h2>
                <p class="mt-1 text-sm text-slate-500">Use clear, restrained status colors.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <x-ui.badge>Draft</x-ui.badge>
                    <x-ui.badge tone="success">Approved</x-ui.badge>
                    <x-ui.badge tone="warning">Pending review</x-ui.badge>
                    <x-ui.badge tone="danger">Rejected</x-ui.badge>
                    <x-ui.badge tone="info">In progress</x-ui.badge>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card>
            <div class="max-w-2xl">
                <h2 class="text-base font-semibold text-slate-950">Form fields</h2>
                <p class="mt-1 text-sm text-slate-500">Label, hint, required marker, focus ring, and validation-error patterns.</p>
            </div>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <x-ui.field name="preview-name" label="Full name" placeholder="Enter your full name" required hint="Use the name shown on your official identification." />
                <x-ui.field name="preview-email" label="Email address" type="email" placeholder="you@example.com" />
                <x-ui.field name="preview-invalid" label="Organization code" placeholder="e.g. SARADHI" error="Please check the organization code." />
                <x-ui.field name="preview-disabled" label="Read-only example" value="Inherited from your organization" disabled />
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Content panel</h2>
                    <p class="mt-1 text-sm text-slate-500">A standard panel pattern for dashboard summaries and settings sections.</p>
                </div>
                <x-ui.badge tone="success">Example state</x-ui.badge>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-stone-50 p-4"><p class="text-sm text-slate-500">Applications</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">128</p><p class="mt-1 text-xs text-slate-500">Illustrative value</p></div>
                <div class="rounded-xl bg-stone-50 p-4"><p class="text-sm text-slate-500">Awaiting review</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">16</p><p class="mt-1 text-xs text-slate-500">Illustrative value</p></div>
                <div class="rounded-xl bg-stone-50 p-4"><p class="text-sm text-slate-500">Completed</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">92</p><p class="mt-1 text-xs text-slate-500">Illustrative value</p></div>
            </div>
        </x-ui.card>
        <p class="text-xs leading-5 text-slate-500">This page is only a visual component preview. All displayed counts are examples, and no live organization or member data is shown.</p>
    </main>
</div>
@endsection
