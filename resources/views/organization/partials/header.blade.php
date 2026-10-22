<header class="border-b border-slate-200/80 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-10">
        <a href="{{ route('organization.home') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="--tw-ring-color: var(--org-primary, {{ $primaryColor ?? '#174b3d' }})" aria-label="{{ $organization->name }} home">
            @if (!empty($logoPath))
                <img src="{{ $logoPath }}" alt="" class="size-11 shrink-0 rounded-xl object-contain">
            @else
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl text-lg font-bold text-white" style="background: var(--org-primary, {{ $primaryColor ?? '#174b3d' }})">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
            @endif
            <span class="truncate text-lg font-semibold tracking-tight text-slate-950 sm:text-xl">{{ $organization->name }}</span>
        </a>

        @php
            // Resolve the authenticated user when the parent view has not supplied it.
            $authenticatedUser = $authenticatedUser ?? auth()->user();

            $isMember = $authenticatedUser instanceof \App\Models\User
                && isset($organization)
                && $authenticatedUser->isMember($organization);

            // Keep the header consistent across pages: applicants with an existing
            // non-reapplicable application must not be offered a second application.
            if (! isset($hasPendingMembershipApplication)) {
                $latestApplication = $authenticatedUser instanceof \App\Models\User && isset($organization)
                    ? \Modules\Member\Models\MembershipApplication::query()
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $authenticatedUser->id)
                        ->latest('id')
                        ->first()
                    : null;

                $hasPendingMembershipApplication = $latestApplication !== null
                    && ! (
                        $latestApplication->status === \Modules\Member\Enums\MembershipApplicationStatus::REJECTED
                        && $latestApplication->latestRejectionAllowsReapply()
                    );
                $hasDraftMembershipApplication = $latestApplication?->status === \Modules\Member\Enums\MembershipApplicationStatus::DRAFT;
            }
        @endphp
        
        <nav aria-label="Organization navigation" class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            <a href="{{ route('organization.home') }}#about" class="hidden min-h-10 items-center justify-center rounded-full px-3 text-sm font-semibold text-slate-700 transition hover:bg-stone-100 sm:inline-flex">About Us</a>
            <a href="{{ route('organization.home') }}#events" class="hidden min-h-10 items-center justify-center rounded-full px-3 text-sm font-semibold text-slate-700 transition hover:bg-stone-100 sm:inline-flex">Events</a>
            <a href="{{ route('organization.home') }}#contact" class="hidden min-h-10 items-center justify-center rounded-full px-3 text-sm font-semibold text-slate-700 transition hover:bg-stone-100 sm:inline-flex">Contact Us</a>

            @if (!empty($authenticatedUser))
                @if (!empty($canManageOrganization))
                    <a href="{{ route('core.organization.settings') }}" class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-100">Manage Organization</a>
                @endif
                <a href="{{ $isMember ? route('organization.member.profile') : route('organization.membership.apply') }}" class="inline-flex min-h-10 items-center justify-center rounded-full px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--org-primary, {{ $primaryColor ?? '#174b3d' }})">{{ $isMember ? 'Member Profile' : (($hasDraftMembershipApplication ?? false) ? 'Continue Application' : ($hasPendingMembershipApplication ? 'Application in Progress' : 'Apply Membership')) }}</a>
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full text-sm font-bold text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="background: var(--org-primary, {{ $primaryColor ?? '#174b3d' }})" aria-label="Open account menu" title="Account menu">
                        {{ mb_strtoupper(mb_substr($authenticatedUser->name ?? 'U', 0, 1)) }}
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl" role="menu">
                        <div class="border-b border-slate-100 px-3 py-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $authenticatedUser->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $authenticatedUser->email }}</p>
                        </div>
                        <a href="{{ route('organization.profile') }}" class="mt-2 block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-stone-100" role="menuitem">View Profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1">@csrf<button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-stone-100" role="menuitem">Logout</button></form>
                    </div>
                </details>
            @else
                <a href="{{ route('login') }}" class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-100">Sign in</a>
                <a href="{{ route('organization.membership.apply') }}" class="inline-flex min-h-10 items-center justify-center rounded-full px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--org-primary, {{ $primaryColor ?? '#174b3d' }})">Apply Membership</a>
            @endif
        </nav>
    </div>
</header>
