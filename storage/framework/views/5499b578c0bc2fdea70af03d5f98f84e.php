<!doctype html>
<?php
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
?>
<html lang="en" data-theme="<?php echo e($organizationTheme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($organizationFaviconUrl): ?><link rel="icon" href="<?php echo e($organizationFaviconUrl); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <title><?php echo e($pageTitle ?? 'Admin'); ?> · <?php echo e($brandingOrganization?->name ?? 'Saflivo'); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'Modules/Core/resources/css/organization-admin.css', 'resources/js/app.js']); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    
</head>
<body class="saradhi-admin">
<div class="app-shell">
    <input id="nav-toggle" type="checkbox" aria-label="Toggle navigation">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="brand" href="<?php echo e(auth()->user()?->is_system_admin ? route('core.organizations.index') : route('core.organization.settings')); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($organizationLogoUrl): ?>
                <img class="brand-logo" src="<?php echo e($organizationLogoUrl); ?>" alt="<?php echo e($brandingOrganization?->name ?? 'Organization'); ?> logo">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true">S</span><span><?php echo e($brandingOrganization?->name ?? 'Saflivo'); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </a>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->is_system_admin): ?>
            <div class="nav-caption">System administration</div>
            <nav class="nav-list">
                <a class="nav-link <?php echo e(request()->routeIs('core.organizations.index', 'core.organizations.manage') ? 'active' : ''); ?>" href="<?php echo e(route('core.organizations.index')); ?>">
                    <span class="nav-icon" aria-hidden="true">▦</span><span>Organizations</span>
                </a>
                <a class="nav-link <?php echo e(request()->routeIs('core.organizations.create') ? 'active' : ''); ?>" href="<?php echo e(route('core.organizations.create')); ?>">
                    <span class="nav-icon" aria-hidden="true">＋</span><span>Add Organization</span>
                </a>
                <?php ($whatsAppNavActive = request()->routeIs('core.whatsapp.settings', 'core.whatsapp.usage') || request()->is('system/whatsapp-templates')); ?>
                <details class="nav-group" <?php echo e($whatsAppNavActive ? 'open' : ''); ?>>
                    <summary class="nav-group-toggle <?php echo e($whatsAppNavActive ? 'active' : ''); ?>">
                        <span class="nav-icon" aria-hidden="true">◉</span><span>Notifications</span><span class="nav-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-sublist">
                        <a class="nav-link <?php echo e(request()->routeIs('core.whatsapp.settings') ? 'active' : ''); ?>" href="<?php echo e(route('core.whatsapp.settings')); ?>"><span class="nav-icon" aria-hidden="true">⚙</span><span>Default Channel Settings</span></a>
                        <a class="nav-link <?php echo e(request()->routeIs('core.whatsapp.usage') ? 'active' : ''); ?>" href="<?php echo e(route('core.whatsapp.usage')); ?>"><span class="nav-icon" aria-hidden="true">▤</span><span>WhatsApp Usage</span></a>
                        <a class="nav-link <?php echo e(request()->is('system/whatsapp-templates') ? 'active' : ''); ?>" href="/system/whatsapp-templates"><span class="nav-icon" aria-hidden="true">▧</span><span>WhatsApp Template Guide</span></a>
                    </div>
                </details>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (auth()->user()?->is_system_admin)): ?>
            <nav class="nav-list" aria-label="Primary organization navigation">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewMembersDirectory && \Illuminate\Support\Facades\Route::has('member.directory')): ?>
                    <a class="nav-link <?php echo e(request()->routeIs('member.directory') ? 'active' : ''); ?>" href="<?php echo e(route('member.directory')); ?>">
                        <span class="nav-icon" aria-hidden="true">♙</span><span>Members</span>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewMembershipApplications && \Illuminate\Support\Facades\Route::has('member.membership-applications')): ?>
                    <a class="nav-link <?php echo e(request()->routeIs('member.membership-applications') ? 'active' : ''); ?>" href="<?php echo e(route('member.membership-applications')); ?>">
                        <span class="nav-icon" aria-hidden="true">▤</span><span>Membership Applications</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($membershipPendingCount > 0): ?><span class="nav-count" aria-label="<?php echo e($membershipPendingCount); ?> pending actions"><?php echo e($membershipPendingCount > 99 ? '99+' : $membershipPendingCount); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewMemberPayments && \Illuminate\Support\Facades\Route::has('member.payments.index')): ?>
                    <a class="nav-link <?php echo e(request()->routeIs('member.payments.*') ? 'active' : ''); ?>" href="<?php echo e(route('member.payments.index')); ?>">
                        <span class="nav-icon" aria-hidden="true">₹</span><span>Membership Payments</span>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAccessCommittee && \Illuminate\Support\Facades\Route::has('committee.dashboard')): ?>
                    <?php ($committeeNavActive = request()->routeIs('committee.dashboard', 'committee.committees', 'committee.designations')); ?>
                    <details class="nav-group" <?php echo e($committeeNavActive ? 'open' : ''); ?>>
                        <summary class="nav-group-toggle <?php echo e($committeeNavActive ? 'active' : ''); ?>">
                            <span class="nav-icon" aria-hidden="true">♧</span><span>Committee</span><span class="nav-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="nav-sublist">
                            <a class="nav-link <?php echo e(request()->routeIs('committee.dashboard') ? 'active' : ''); ?>" href="<?php echo e(route('committee.dashboard')); ?>"><span class="nav-icon" aria-hidden="true">⌂</span><span>Overview</span></a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewCommittees && \Illuminate\Support\Facades\Route::has('committee.committees')): ?>
                                <a class="nav-link <?php echo e(request()->routeIs('committee.committees') ? 'active' : ''); ?>" href="<?php echo e(route('committee.committees')); ?>"><span class="nav-icon" aria-hidden="true">▦</span><span>Committees</span></a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewDesignations && \Illuminate\Support\Facades\Route::has('committee.designations')): ?>
                                <a class="nav-link <?php echo e(request()->routeIs('committee.designations') ? 'active' : ''); ?>" href="<?php echo e(route('committee.designations')); ?>"><span class="nav-icon" aria-hidden="true">♙</span><span>Designations</span></a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="sidebar-spacer"></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (auth()->user()?->is_system_admin)): ?>
            <div class="sidebar-divider"></div>
            <nav class="nav-list" aria-label="Organization settings navigation">
                <a class="nav-link <?php echo e(request()->routeIs('organization.home') ? 'active' : ''); ?>" href="<?php echo e(route('organization.home')); ?>" target="_self">
                    <span class="nav-icon" aria-hidden="true">⌂</span><span>Organization Home</span>
                </a>
                <?php ($organizationSettingsActive = request()->routeIs('core.organization.settings', 'core.organization.notifications', 'finance.periods')); ?>
                <details class="nav-group" <?php echo e($organizationSettingsActive ? 'open' : ''); ?>>
                    <summary class="nav-group-toggle <?php echo e($organizationSettingsActive ? 'active' : ''); ?>">
                        <span class="nav-icon" aria-hidden="true">⚙</span><span>Settings</span><span class="nav-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-sublist">
                        <a class="nav-link <?php echo e(request()->routeIs('core.organization.settings') ? 'active' : ''); ?>" href="<?php echo e(route('core.organization.settings')); ?>"><span class="nav-icon" aria-hidden="true">⚙</span><span>Organization Settings</span></a>
                        <a class="nav-link <?php echo e(request()->routeIs('core.organization.notifications') ? 'active' : ''); ?>" href="<?php echo e(route('core.organization.notifications')); ?>"><span class="nav-icon" aria-hidden="true">◉</span><span>Notification Settings</span></a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewFinancePeriods): ?>
                            <a class="nav-link <?php echo e(request()->routeIs('finance.periods') ? 'active' : ''); ?>" href="<?php echo e(route('finance.periods')); ?>"><span class="nav-icon" aria-hidden="true">▦</span><span>Finance Settings</span></a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </details>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="user-chip">
            <span class="avatar" aria-hidden="true"><?php echo e(strtoupper(substr(auth()->user()?->name ?? auth()->user()?->email ?? 'U', 0, 1))); ?></span>
            <div class="user-meta"><strong><?php echo e(auth()->user()?->name ?: 'Signed in'); ?></strong><span><?php echo e(auth()->user()?->email); ?></span></div>
        </div>
    </aside>
    <label class="mobile-overlay" for="nav-toggle" aria-label="Close navigation"></label>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar-left">
                <label class="menu-toggle" for="nav-toggle" aria-label="Open navigation">☰</label>
                <div><div class="eyebrow"><?php echo e(auth()->user()?->is_system_admin ? 'System Admin' : 'Organization Admin'); ?></div><div class="topbar-title"><?php echo e($pageTitle ?? 'Admin workspace'); ?></div></div>
            </div>
            <div class="topbar-right">
                <span class="topbar-user"><?php echo e(auth()->user()?->email); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (auth()->user()?->is_system_admin)): ?>
                    <a class="btn secondary" href="<?php echo e(route('organization.home')); ?>">Organization home</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <form method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn secondary">Log out</button>
                </form>
            </div>
        </header>
        <main class="container">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?> <div class="notice" role="status"><?php echo e(session('success')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php echo e($slot); ?>

        </main>
    </div>
</div>
<?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/layouts/organization-admin.blade.php ENDPATH**/ ?>