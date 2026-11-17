<div class="space-y-8">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Organization administration</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $organization->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Manage the modules and settings you are authorized to administer.</p>
    </section>
    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-slate-900">Management</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($manageableModules as $module)
                <a href="{{ route($module['route']) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                    <h3 class="font-semibold text-slate-900">{{ $module['name'] }}</h3>
                    <p class="mt-2 text-sm text-slate-500">Open {{ $module['name'] }} management</p>
                </a>
            @endforeach
            @if ($canManageSettings)
                <a href="{{ route('core.organization.settings') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                    <h3 class="font-semibold text-slate-900">Organization Settings</h3>
                    <p class="mt-2 text-sm text-slate-500">Manage organization profile, branding and preferences</p>
                </a>
            @endif
        </div>
    </section>
</div>
