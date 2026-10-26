<!doctype html>
@php
    $layoutUser = auth()->user();
    $brandingOrganization = $layoutOrganization ?? null;
    if (! $brandingOrganization && $layoutUser && ! $layoutUser->is_system_admin) {
        $currentOrganization = app(\App\Support\Organization\CurrentOrganization::class);
        $brandingOrganization = $currentOrganization->check() ? $currentOrganization->get() : null;
    }
    $organizationSettings = app(\Modules\Core\Services\OrganizationSettingsService::class);
    $organizationLogoPath = $brandingOrganization ? $organizationSettings->get($brandingOrganization, 'branding.logo') : null;
    $organizationFaviconPath = $brandingOrganization ? $organizationSettings->get($brandingOrganization, 'branding.favicon') : null;
    $organizationLogoUrl = is_string($organizationLogoPath) && $organizationLogoPath !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($organizationLogoPath) : null;
    $organizationFaviconUrl = is_string($organizationFaviconPath) && $organizationFaviconPath !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($organizationFaviconPath) : null;
    $organizationTheme = $brandingOrganization ? $organizationSettings->get($brandingOrganization, 'ui.theme', 'system') : 'light';
    if (! in_array($organizationTheme, ['light', 'dark', 'system'], true)) { $organizationTheme = 'system'; }

    // The membership link belongs to the global organization sidebar. Only show it
    // when the signed-in user can view applications in this organization.
    $canViewMembershipApplications = false;
    $canViewMembersDirectory = false;
    $canViewMemberPayments = false;
    $canViewFinancePeriods = false;
    $canAccessCommittee = false;
    $canViewCommittees = false;
    $canViewDesignations = false;
    $membershipPendingCount = 0;
    if ($layoutUser && ! $layoutUser->is_system_admin && $brandingOrganization) {
        $membershipAuthorization = app(\Modules\Core\Services\AuthorizationService::class);
        $membershipOrganizationContext = new \Modules\Core\Data\AuthorizationContext((int) $brandingOrganization->id);
        $canViewFinancePeriods = \Illuminate\Support\Facades\Route::has('finance.periods')
            && app(\Modules\Core\Services\OrganizationModuleService::class)->isEnabled($brandingOrganization, 'Finance')
            && ($membershipAuthorization->can($layoutUser, 'finance.period.view', $membershipOrganizationContext)
                || $membershipAuthorization->can($layoutUser, 'finance.period.manage', $membershipOrganizationContext));
        $canViewMembershipApplications = $membershipAuthorization->can(
            $layoutUser,
            'membership.application.view',
            $membershipOrganizationContext,
        );
        $canViewMembersDirectory = $membershipAuthorization->can($layoutUser, 'members.view', $membershipOrganizationContext)
            || $membershipAuthorization->can($layoutUser, 'members.manage', $membershipOrganizationContext);
        $canViewMemberPayments = $membershipAuthorization->can($layoutUser, 'membership.payment.view', $membershipOrganizationContext);
        if (! $canViewMemberPayments) {
            $canViewMemberPayments = \Modules\Core\Models\Unit::query()
                ->where('organization_id', $brandingOrganization->id)
                ->get(['id'])
                ->contains(function ($paymentUnit) use ($membershipAuthorization, $layoutUser, $brandingOrganization): bool {
                    return $membershipAuthorization->can(
                        $layoutUser,
                        'membership.payment.view',
                        new \Modules\Core\Data\AuthorizationContext((int) $brandingOrganization->id, (int) $paymentUnit->id),
                    );
                });
        }
        if (! $canViewMembersDirectory) {
            $canViewMembersDirectory = \Modules\Core\Models\Unit::query()
                ->where('organization_id', $brandingOrganization->id)
                ->get(['id'])
                ->contains(function ($membershipUnit) use ($membershipAuthorization, $layoutUser, $brandingOrganization): bool {
                    $unitContext = new \Modules\Core\Data\AuthorizationContext((int) $brandingOrganization->id, (int) $membershipUnit->id);
                    return $membershipAuthorization->can($layoutUser, 'members.view', $unitContext)
                        || $membershipAuthorization->can($layoutUser, 'members.manage', $unitContext);
                });
        }

        $committeeModuleEnabled = app(\Modules\Core\Services\OrganizationModuleService::class)
            ->isEnabled($brandingOrganization, 'Committee');
        if ($committeeModuleEnabled) {
            $canViewCommittees = $membershipAuthorization->can($layoutUser, 'committee.view', $membershipOrganizationContext)
                || $membershipAuthorization->can($layoutUser, 'committee.manage', $membershipOrganizationContext);
            $canViewDesignations = $membershipAuthorization->can($layoutUser, 'committee.designations.view', $membershipOrganizationContext)
                || $membershipAuthorization->can($layoutUser, 'committee.designations.manage', $membershipOrganizationContext);
            $canAccessCommittee = $canViewCommittees || $canViewDesignations
                || $membershipAuthorization->can($layoutUser, 'committee.memberships.view', $membershipOrganizationContext)
                || $membershipAuthorization->can($layoutUser, 'committee.memberships.manage', $membershipOrganizationContext);
        }

        if ($canViewMembershipApplications) {
            $pendingMembershipApplications = \Modules\Member\Models\MembershipApplication::query()
                ->where('organization_id', $brandingOrganization->id)
                ->whereIn('status', ['submitted', 'verified', 'reviewed'])
                ->get(['id', 'status', 'data']);

            $membershipPendingCount = $pendingMembershipApplications->filter(function ($application) use ($membershipAuthorization, $layoutUser, $brandingOrganization): bool {
                $permission = match ($application->status->value) {
                    'submitted' => 'membership.application.verify',
                    'verified' => 'membership.application.review',
                    'reviewed' => 'membership.application.approve',
                    default => null,
                };

                return $permission !== null && $membershipAuthorization->can(
                    $layoutUser,
                    $permission,
                    new \Modules\Core\Data\AuthorizationContext((int) $brandingOrganization->id, $application->unitId()),
                );
            })->count();
        }
    }
@endphp
<html lang="en" data-theme="{{ $organizationTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    @if ($organizationFaviconUrl)<link rel="icon" href="{{ $organizationFaviconUrl }}">@endif
    <title>{{ $pageTitle ?? 'Admin' }} · {{ $brandingOrganization?->name ?? 'Saflivo' }}</title>
    @vite(['resources/css/app.css', 'Modules/Core/resources/css/organization-admin.css', 'resources/js/app.js'])
    @livewireStyles
    
</head>
<body class="saradhi-admin">
<div class="app-shell">
    <input id="nav-toggle" type="checkbox" aria-label="Toggle navigation">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="brand" href="{{ auth()->user()?->is_system_admin ? route('core.organizations.index') : route('core.organization.settings') }}">
            @if ($organizationLogoUrl)
                <img class="brand-logo" src="{{ $organizationLogoUrl }}" alt="{{ $brandingOrganization?->name ?? 'Organization' }} logo">
            @else
                <span class="brand-mark" aria-hidden="true">S</span><span>{{ $brandingOrganization?->name ?? 'Saflivo' }}</span>
            @endif
        </a>

        @if (auth()->user()?->is_system_admin)
            <div class="nav-caption">System administration</div>
            <nav class="nav-list">
                <a class="nav-link {{ request()->routeIs('core.organizations.index', 'core.organizations.manage') ? 'active' : '' }}" href="{{ route('core.organizations.index') }}">
                    <span class="nav-icon" aria-hidden="true">▦</span><span>Organizations</span>
                </a>
                <a class="nav-link {{ request()->routeIs('core.organizations.create') ? 'active' : '' }}" href="{{ route('core.organizations.create') }}">
                    <span class="nav-icon" aria-hidden="true">＋</span><span>Add Organization</span>
                </a>
                @php($whatsAppNavActive = request()->routeIs('core.whatsapp.settings', 'core.whatsapp.usage') || request()->is('system/whatsapp-templates'))
                <details class="nav-group" {{ $whatsAppNavActive ? 'open' : '' }}>
                    <summary class="nav-group-toggle {{ $whatsAppNavActive ? 'active' : '' }}">
                        <span class="nav-icon" aria-hidden="true">◉</span><span>Notifications</span><span class="nav-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-sublist">
                        <a class="nav-link {{ request()->routeIs('core.whatsapp.settings') ? 'active' : '' }}" href="{{ route('core.whatsapp.settings') }}"><span class="nav-icon" aria-hidden="true">⚙</span><span>Default Channel Settings</span></a>
                        <a class="nav-link {{ request()->routeIs('core.whatsapp.usage') ? 'active' : '' }}" href="{{ route('core.whatsapp.usage') }}"><span class="nav-icon" aria-hidden="true">▤</span><span>WhatsApp Usage</span></a>
                        <a class="nav-link {{ request()->is('system/whatsapp-templates') ? 'active' : '' }}" href="/system/whatsapp-templates"><span class="nav-icon" aria-hidden="true">▧</span><span>WhatsApp Template Guide</span></a>
                    </div>
                </details>
            </nav>
        @endif

        @unless (auth()->user()?->is_system_admin)
            <nav class="nav-list" aria-label="Primary organization navigation">
                @if ($canViewMembersDirectory && \Illuminate\Support\Facades\Route::has('member.directory'))
                    <a class="nav-link {{ request()->routeIs('member.directory') ? 'active' : '' }}" href="{{ route('member.directory') }}">
                        <span class="nav-icon" aria-hidden="true">♙</span><span>Members</span>
                    </a>
                @endif
                @if ($canViewMembershipApplications && \Illuminate\Support\Facades\Route::has('member.membership-applications'))
                    <a class="nav-link {{ request()->routeIs('member.membership-applications') ? 'active' : '' }}" href="{{ route('member.membership-applications') }}">
                        <span class="nav-icon" aria-hidden="true">▤</span><span>Membership Applications</span>
                        @if ($membershipPendingCount > 0)<span class="nav-count" aria-label="{{ $membershipPendingCount }} pending actions">{{ $membershipPendingCount > 99 ? '99+' : $membershipPendingCount }}</span>@endif
                    </a>
                @endif
                @if ($canViewMemberPayments && \Illuminate\Support\Facades\Route::has('member.payments.index'))
                    <a class="nav-link {{ request()->routeIs('member.payments.*') ? 'active' : '' }}" href="{{ route('member.payments.index') }}">
                        <span class="nav-icon" aria-hidden="true">₹</span><span>Membership Payments</span>
                    </a>
                @endif
                @if ($canAccessCommittee && \Illuminate\Support\Facades\Route::has('committee.dashboard'))
                    @php($committeeNavActive = request()->routeIs('committee.dashboard', 'committee.committees', 'committee.designations'))
                    <details class="nav-group" {{ $committeeNavActive ? 'open' : '' }}>
                        <summary class="nav-group-toggle {{ $committeeNavActive ? 'active' : '' }}">
                            <span class="nav-icon" aria-hidden="true">♧</span><span>Committee</span><span class="nav-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="nav-sublist">
                            <a class="nav-link {{ request()->routeIs('committee.dashboard') ? 'active' : '' }}" href="{{ route('committee.dashboard') }}"><span class="nav-icon" aria-hidden="true">⌂</span><span>Overview</span></a>
                            @if ($canViewCommittees && \Illuminate\Support\Facades\Route::has('committee.committees'))
                                <a class="nav-link {{ request()->routeIs('committee.committees') ? 'active' : '' }}" href="{{ route('committee.committees') }}"><span class="nav-icon" aria-hidden="true">▦</span><span>Committees</span></a>
                            @endif
                            @if ($canViewDesignations && \Illuminate\Support\Facades\Route::has('committee.designations'))
                                <a class="nav-link {{ request()->routeIs('committee.designations') ? 'active' : '' }}" href="{{ route('committee.designations') }}"><span class="nav-icon" aria-hidden="true">♙</span><span>Designations</span></a>
                            @endif
                        </div>
                    </details>
                @endif
            </nav>
        @endunless

        <div class="sidebar-spacer"></div>
        @unless (auth()->user()?->is_system_admin)
            <div class="sidebar-divider"></div>
            <nav class="nav-list" aria-label="Organization settings navigation">
                <a class="nav-link {{ request()->routeIs('organization.home') ? 'active' : '' }}" href="{{ route('organization.home') }}" target="_self">
                    <span class="nav-icon" aria-hidden="true">⌂</span><span>Organization Home</span>
                </a>
                @php($organizationSettingsActive = request()->routeIs('core.organization.settings', 'core.organization.notifications', 'finance.periods'))
                <details class="nav-group" {{ $organizationSettingsActive ? 'open' : '' }}>
                    <summary class="nav-group-toggle {{ $organizationSettingsActive ? 'active' : '' }}">
                        <span class="nav-icon" aria-hidden="true">⚙</span><span>Settings</span><span class="nav-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-sublist">
                        <a class="nav-link {{ request()->routeIs('core.organization.settings') ? 'active' : '' }}" href="{{ route('core.organization.settings') }}"><span class="nav-icon" aria-hidden="true">⚙</span><span>Organization Settings</span></a>
                        <a class="nav-link {{ request()->routeIs('core.organization.notifications') ? 'active' : '' }}" href="{{ route('core.organization.notifications') }}"><span class="nav-icon" aria-hidden="true">◉</span><span>Notification Settings</span></a>
                        @if ($canViewFinancePeriods)
                            <a class="nav-link {{ request()->routeIs('finance.periods') ? 'active' : '' }}" href="{{ route('finance.periods') }}"><span class="nav-icon" aria-hidden="true">▦</span><span>Finance Settings</span></a>
                        @endif
                    </div>
                </details>
            </nav>
        @endunless
        <div class="user-chip">
            <span class="avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()?->name ?? auth()->user()?->email ?? 'U', 0, 1)) }}</span>
            <div class="user-meta"><strong>{{ auth()->user()?->name ?: 'Signed in' }}</strong><span>{{ auth()->user()?->email }}</span></div>
        </div>
    </aside>
    <label class="mobile-overlay" for="nav-toggle" aria-label="Close navigation"></label>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar-left">
                <label class="menu-toggle" for="nav-toggle" aria-label="Open navigation">☰</label>
                <div><div class="eyebrow">{{ auth()->user()?->is_system_admin ? 'System Admin' : 'Organization Admin' }}</div><div class="topbar-title">{{ $pageTitle ?? 'Admin workspace' }}</div></div>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">{{ auth()->user()?->email }}</span>
                @unless (auth()->user()?->is_system_admin)
                    <a class="btn secondary" href="{{ route('organization.home') }}">Organization home</a>
                @endunless
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn secondary">Log out</button>
                </form>
            </div>
        </header>
        <main class="container">
            @if (session()->has('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
