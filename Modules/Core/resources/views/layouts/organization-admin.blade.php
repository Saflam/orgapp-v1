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
    @livewireStyles
    <style>
        :root {
            color-scheme: light;
            --ink: #172033;
            --muted: #68758b;
            --line: #e3e8f0;
            --panel: #fff;
            --canvas: #f6f8fc;
            --sidebar: #111d32;
            --sidebar-muted: #a8b4c8;
            --sidebar-hover: #1d2c46;
            --brand: #4776e6;
            --brand-soft: #edf2ff;
            --danger: #a12626;
            --ok: #176b45;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --ink: #e8edf7;
            --muted: #a5b1c5;
            --line: #303c50;
            --panel: #182235;
            --canvas: #101827;
            --brand: #7899ff;
            --brand-soft: #253456;
            --danger: #e06b6b;
            --ok: #6bc79a;
        }
        @media (prefers-color-scheme: dark) {
            html[data-theme="system"] {
                color-scheme: dark;
                --ink: #e8edf7;
                --muted: #a5b1c5;
                --line: #303c50;
                --panel: #182235;
                --canvas: #101827;
                --brand: #7899ff;
                --brand-soft: #253456;
                --danger: #e06b6b;
                --ok: #6bc79a;
            }
        }
        * { box-sizing: border-box; }
        html { min-height: 100%; }
        body { margin: 0; background: var(--canvas); color: var(--ink); font: 14px/1.55 Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }
        .app-shell { min-height: 100vh; }
        .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 30; display: flex; flex-direction: column; width: 248px; padding: 22px 14px 16px; background: var(--sidebar); color: #fff; }
        .brand { display: flex; align-items: center; gap: 11px; padding: 2px 12px 26px; font-size: 18px; font-weight: 750; letter-spacing: -.035em; }
        .brand-mark { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(145deg, #6d91f5, #4268d7); color: #fff; font-size: 16px; font-weight: 800; }
        .brand-logo { display: block; max-width: 174px; max-height: 44px; width: auto; height: auto; object-fit: contain; object-position: left center; }
        .nav-caption { padding: 0 12px 9px; color: #8291aa; font-size: 10px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        .nav-list { display: grid; gap: 5px; }
        .nav-group { min-width: 0; }
        .nav-group-toggle { display: flex; align-items: center; gap: 12px; min-height: 43px; padding: 10px 12px; border: 1px solid transparent; border-radius: 9px; color: var(--sidebar-muted); cursor: pointer; list-style: none; font-size: 13px; font-weight: 600; }
        .nav-group-toggle::-webkit-details-marker { display: none; }
        .nav-group-toggle:hover, .nav-group-toggle.active { background: var(--sidebar-hover); color: #fff; }
        .nav-chevron { display: inline-block; flex: 0 0 8px; width: 8px; height: 8px; margin: -4px 3px 0 auto; border-right: 1.7px solid currentColor; border-bottom: 1.7px solid currentColor; transform: rotate(45deg); transition: transform .18s ease, margin .18s ease; }
        .nav-group[open] .nav-chevron { margin-top: 4px; transform: rotate(225deg); }
        .nav-sublist { display: grid; gap: 3px; padding: 4px 0 4px 12px; }
        .nav-sublist .nav-link { min-height: 38px; padding: 8px 10px; font-size: 12px; }
        .nav-link { display: flex; align-items: center; gap: 12px; min-height: 43px; padding: 10px 12px; border: 1px solid transparent; border-radius: 9px; color: var(--sidebar-muted); font-size: 13px; font-weight: 600; transition: background .15s ease, color .15s ease; }
        .nav-link:hover { background: var(--sidebar-hover); color: #fff; }
        .nav-link.active { background: #263a5c; border-color: #344b70; color: #fff; }
        .nav-count { display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 21px; margin-left: auto; padding: 0 6px; border-radius: 999px; background: #e9a23b; color: #182338; font-size: 10px; font-weight: 800; line-height: 1; }
        .nav-icon { display: inline-grid; place-items: center; width: 20px; font-size: 16px; }
        .sidebar-spacer { flex: 1; min-height: 26px; }
        .sidebar-divider { height: 1px; margin: 12px 8px 16px; background: #2a3850; }
        .user-chip { display: flex; align-items: center; gap: 10px; padding: 12px 10px 2px; border-top: 1px solid #2a3850; }
        .avatar { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 50%; background: #344a6c; color: #fff; font-weight: 750; }
        .user-meta { min-width: 0; }
        .user-meta strong, .user-meta span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .user-meta strong { max-width: 160px; font-size: 12px; }
        .user-meta span { max-width: 160px; color: #9eabc0; font-size: 11px; }
        .workspace { min-height: 100vh; margin-left: 248px; }
        .topbar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 68px; padding: 0 34px; border-bottom: 1px solid var(--line); background: color-mix(in srgb, var(--panel) 94%, transparent); backdrop-filter: blur(12px); }
        .topbar-left, .topbar-right { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .eyebrow { color: var(--muted); font-size: 11px; font-weight: 650; }
        .topbar-title { margin: 1px 0 0; font-size: 15px; font-weight: 750; letter-spacing: -.02em; }
        .topbar-user { max-width: 260px; overflow: hidden; color: #526078; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .container { width: min(1240px, 100%); margin: 0 auto; padding: 30px 34px 48px; }
        .page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 23px; }
        .page-head-actions { justify-content: flex-end; flex: 0 0 auto; }
        .long-page-nav a[aria-current="page"] { border-color: var(--line); background: var(--canvas); color: var(--ink); }
        .whatsapp-section-jumps { margin-bottom: 14px; }
        h1 { margin: 0; font-size: clamp(23px, 2.4vw, 29px); line-height: 1.25; letter-spacing: -.04em; }
        h2 { margin: 0 0 12px; font-size: 17px; letter-spacing: -.02em; }
        h3 { font-size: 14px; }
        .muted { color: var(--muted); }
        .card { margin-bottom: 18px; padding: 23px; border: 1px solid var(--line); border-radius: 14px; background: var(--panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        label { font-size: 13px; font-weight: 650; }
        input, select { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; outline: none; background: var(--panel); color: var(--ink); }
        input:focus, select:focus { border-color: #7294ee; box-shadow: 0 0 0 3px rgb(71 118 230 / 13%); }
        input[type=checkbox] { width: auto; min-height: auto; }
        .checkrow { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; }
        .checkrow label { font-weight: 500; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 39px; padding: 9px 13px; border: 1px solid transparent; border-radius: 8px; background: var(--brand); color: #fff; cursor: pointer; font-size: 12px; font-weight: 700; line-height: 1.2; transition: filter .15s ease, background .15s ease; }
        .btn:hover { filter: brightness(.96); }
        .btn:focus-visible, .nav-link:focus-visible, .menu-toggle:focus-visible { outline: 3px solid #9db5ff; outline-offset: 2px; }
        .btn:disabled { cursor: wait; opacity: .7; }
        .btn.secondary { border-color: var(--line); background: var(--panel); color: var(--ink); }
        .btn.danger { background: var(--danger); }
        .actions { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-size: 10px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
        .pill { display: inline-block; padding: 3px 9px; border-radius: 999px; background: #edf0f6; font-size: 11px; font-weight: 750; }
        .pill.ok { background: #e3f6ec; color: var(--ok); }
        .pill.off { background: #fbeaea; color: #7c3030; }
        html[data-theme="dark"] .pill { background: #2a3548; color: #d4dced; }
        html[data-theme="dark"] .pill.ok { background: #1e4435; color: #a8e5c4; }
        html[data-theme="dark"] .pill.off { background: #492d35; color: #ffc0c5; }
        @media (prefers-color-scheme: dark) {
            html[data-theme="system"] .pill { background: #2a3548; color: #d4dced; }
            html[data-theme="system"] .pill.ok { background: #1e4435; color: #a8e5c4; }
            html[data-theme="system"] .pill.off { background: #492d35; color: #ffc0c5; }
        }
        .notice { margin-bottom: 16px; padding: 12px 15px; border: 1px solid #bce5ce; border-radius: 9px; background: #effaf3; color: #145c3b; }
        html[data-theme="dark"] .notice { border-color: #285c46; background: #173629; color: #a8e5c4; }
        @media (prefers-color-scheme: dark) { html[data-theme="system"] .notice { border-color: #285c46; background: #173629; color: #a8e5c4; } }
        .error { color: #a12626; font-size: 12px; }
        .steps { display: flex; gap: 8px; margin: 0 0 22px; padding: 0; list-style: none; }
        .steps li { flex: 1; padding: 9px 4px; border-bottom: 3px solid var(--line); color: var(--muted); font-size: 12px; }
        .steps li.current { border-color: var(--brand); color: var(--brand); font-weight: 750; }
        .pagination { display: flex; justify-content: space-between; padding-top: 15px; }
        .empty { padding: 28px; color: var(--muted); text-align: center; }
        .menu-toggle, .mobile-overlay, #nav-toggle { display: none; }
        /* Reusable in-content navigation for authenticated pages with long, sectioned content. */
        .long-page-layout { display: grid; grid-template-columns: 210px minmax(0, 1fr); align-items: start; gap: 22px; }
        .long-page-nav { position: sticky; top: 92px; display: grid; gap: 5px; padding: 13px; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); }
        .long-page-nav-title { padding: 2px 9px 7px; color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .long-page-nav a { display: block; padding: 9px 10px; border: 1px solid transparent; border-radius: 8px; color: var(--muted); font-size: 12px; font-weight: 650; line-height: 1.35; }
        .long-page-nav a:hover, .long-page-nav a:focus-visible { border-color: var(--line); background: var(--canvas); color: var(--ink); outline: none; }
        .long-page-content { min-width: 0; }
        .long-page-content > section[id] { scroll-margin-top: 94px; }

        /* Committee module pages use local component styles rather than assuming Tailwind is loaded. */
        .committee-page { display: grid; gap: 20px; min-width: 0; }
        .committee-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
        .committee-page-header-row { align-items: center; }
        .committee-page-header h1 { margin: 0; }
        .committee-page-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px; margin-left: auto; }
        .committee-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 9px 14px; border: 1px solid var(--line); border-radius: 9px; background: var(--panel); color: var(--ink); font: inherit; font-size: 12px; font-weight: 750; line-height: 1.2; text-decoration: none; white-space: nowrap; cursor: pointer; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
        .committee-button:hover { background: var(--canvas); border-color: var(--brand); text-decoration: none; }
        .committee-button:focus-visible { outline: 3px solid color-mix(in srgb, var(--brand) 22%, transparent); outline-offset: 2px; }
        .committee-button-primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .committee-button-primary:hover { background: color-mix(in srgb, var(--brand) 88%, #000); border-color: var(--brand); color: #fff; }
        .committee-button-secondary { background: var(--panel); color: var(--ink); }
        .committee-form-card { padding: 22px; border: 1px solid var(--line); border-radius: 14px; background: var(--panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%); }
        .committee-form-card h2 { margin: 0 0 20px; color: var(--ink); font-size: 18px; font-weight: 800; }
        .committee-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .committee-form-field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
        .committee-form-field label { color: var(--ink); font-size: 12px; font-weight: 750; }
        .committee-form-field-full { grid-column: 1 / -1; }
        .committee-form-actions { display: flex; grid-column: 1 / -1; align-items: center; gap: 10px; margin-top: 4px; }
        .committee-field-error { color: #dc2626; font-size: 12px; }
        .committee-alert { padding: 12px 15px; border-radius: 10px; font-size: 13px; }
        .committee-alert-success { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; }
        html[data-theme="dark"] .committee-alert-success { border-color: #1e6a4b; background: #163b2d; color: #a8e5c4; }
        .committee-alert-error { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; }
        html[data-theme="dark"] .committee-alert-error { border-color: #7f1d1d; background: #3a1c22; color: #fecaca; }
        .committee-term-panel { display: grid; gap: 18px; }
        .committee-term-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .committee-term-panel-header h2 { margin-bottom: 0; }
        .committee-term-form { padding-bottom: 18px; border-bottom: 1px solid var(--line); }
        .committee-term-table-card { box-shadow: none; }
        .committee-row-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }
        .committee-muted { color: var(--muted); }
        .committee-eyebrow { margin: 0 0 7px; color: var(--brand); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .committee-page-description { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .committee-stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .committee-stat-card { display: flex; min-width: 0; flex-direction: column; align-items: flex-start; gap: 9px; padding: 22px; border: 1px solid var(--line); border-radius: 14px; background: var(--panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%); transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease; }
        a.committee-stat-card:hover { border-color: color-mix(in srgb, var(--brand) 45%, var(--line)); transform: translateY(-1px); box-shadow: 0 8px 24px rgb(22 37 66 / 7%); }
        .committee-stat-label { color: var(--muted); font-size: 13px; font-weight: 650; }
        .committee-stat-number { color: var(--ink); font-size: 31px; font-weight: 800; letter-spacing: -.045em; line-height: 1.2; }
        .committee-stat-action { margin-top: 5px; color: var(--brand); font-size: 12px; font-weight: 750; }
        .committee-stat-footnote { color: var(--muted); font-size: 12px; }
        .committee-notice { padding: 15px 17px; border: 1px solid #f1d49a; border-radius: 11px; background: #fff8e8; color: #704d0c; font-size: 13px; }
        .committee-notice strong { display: block; margin-bottom: 3px; }
        .committee-notice p { margin: 0; }
        html[data-theme="dark"] .committee-notice { border-color: #6b5428; background: #352b19; color: #f0d79c; }
        @media (prefers-color-scheme: dark) { html[data-theme="system"] .committee-notice { border-color: #6b5428; background: #352b19; color: #f0d79c; } }
        .committee-back-link { display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0; color: var(--brand); font-size: 12px; font-weight: 750; }
        .committee-back-link:hover { text-decoration: underline; }
        .committee-filter-card { display: flex; align-items: center; gap: 12px; padding: 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); box-shadow: 0 3px 14px rgb(22 37 66 / 3%); }
        .committee-filter-row { align-items: center; }
        .committee-filter-field { min-width: 0; }
        .committee-filter-field:first-child { flex: 1 1 340px; }
        .committee-scope-field { flex: 0 0 180px; }
        .committee-search-input { display: block; width: 100%; min-height: 42px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); color: var(--ink); font-size: 13px; }
        .committee-select { display: block; width: 100%; min-height: 42px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); color: var(--ink); font-size: 13px; }
        .committee-search-input:focus, .committee-select:focus { border-color: var(--brand); outline: 3px solid color-mix(in srgb, var(--brand) 15%, transparent); }
        .committee-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .committee-table-card { min-width: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%); }
        .committee-table-scroll { width: 100%; overflow-x: auto; }
        .committee-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .committee-table thead { background: var(--canvas); }
        .committee-table th { padding: 13px 16px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .075em; text-align: left; text-transform: uppercase; white-space: nowrap; }
        .committee-table td { padding: 14px 16px; border-bottom: 1px solid var(--line); color: var(--muted); vertical-align: middle; }
        .committee-table tbody tr:last-child td { border-bottom: 0; }
        .committee-table tbody tr:hover { background: color-mix(in srgb, var(--canvas) 70%, transparent); }
        .committee-table .committee-primary-cell { color: var(--ink); font-weight: 750; }
        .committee-code { padding: 3px 6px; border-radius: 5px; background: var(--canvas); color: var(--ink); font-size: 12px; }
        .committee-status { display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 750; white-space: nowrap; }
        .committee-status.is-active { background: #e3f6ec; color: #176b45; }
        .committee-status.is-inactive { background: #edf0f6; color: #5d687b; }
        html[data-theme="dark"] .committee-status.is-active { background: #1e4435; color: #a8e5c4; }
        html[data-theme="dark"] .committee-status.is-inactive { background: #2a3548; color: #d4dced; }
        .committee-empty-state { padding: 38px 18px !important; color: var(--muted) !important; text-align: center; }
        .committee-pagination { padding: 13px 16px; border-top: 1px solid var(--line); color: var(--muted); font-size: 12px; }
        .committee-pagination nav { display: flex; justify-content: space-between; gap: 10px; }
        .committee-pagination a, .committee-pagination span[aria-current="page"] { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; min-height: 30px; padding: 4px 9px; border: 1px solid var(--line); border-radius: 7px; }
        .committee-pagination span[aria-current="page"] { background: var(--brand-soft); color: var(--brand); font-weight: 750; }
        @media (max-width: 640px) {
            .committee-term-panel-header { flex-direction: column; }
            .committee-row-actions { min-width: 180px; }
        }
        @media (max-width: 900px) { .committee-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 600px) { .committee-stat-grid { grid-template-columns: 1fr; } .committee-page-header-row { align-items: stretch; flex-direction: column; } .committee-page-actions { width: 100%; margin-left: 0; justify-content: flex-start; } .committee-page-actions .committee-button { flex: 1 1 auto; } .committee-form-grid { grid-template-columns: 1fr; } .committee-form-field-full, .committee-form-actions { grid-column: 1 / -1; } .committee-filter-row { align-items: stretch; flex-direction: column; } .committee-filter-field:first-child, .committee-scope-field { flex: 1 1 auto; width: 100%; } .committee-table th, .committee-table td { padding: 11px 12px; } }

        @media (max-width: 900px) {
            .long-page-layout { grid-template-columns: 1fr; gap: 14px; }
            .long-page-nav { position: static; display: flex; align-items: center; gap: 5px; overflow-x: auto; padding: 8px; }
            .long-page-nav-title { flex: 0 0 auto; padding: 0 7px; }
            .long-page-nav a { flex: 0 0 auto; white-space: nowrap; }
            .sidebar { width: 224px; }
            .workspace { margin-left: 224px; }
            .container { padding: 26px 22px 40px; }
            .page-head { align-items: flex-start; flex-wrap: wrap; }
            .page-head-actions { justify-content: flex-start; }
            .topbar { padding: 0 22px; }
        }
        @media (max-width: 700px) {
            .sidebar { width: min(284px, 84vw); transform: translateX(-102%); transition: transform .2s ease; box-shadow: 18px 0 50px rgb(0 0 0 / 15%); }
            .workspace { margin-left: 0; }
            .topbar { min-height: 62px; padding: 0 16px; }
            .menu-toggle { display: inline-grid; place-items: center; width: 38px; height: 38px; border: 1px solid var(--line); border-radius: 9px; background: var(--panel); color: var(--ink); cursor: pointer; font-size: 19px; }
            .mobile-overlay { position: fixed; inset: 0; z-index: 25; background: rgb(12 22 40 / 45%); }
            #nav-toggle:checked ~ .sidebar { transform: translateX(0); }
            #nav-toggle:checked ~ .mobile-overlay { display: block; }
            .container { padding: 22px 15px 34px; }
            .grid { grid-template-columns: 1fr; gap: 0; }
            .page-head { align-items: flex-start; flex-direction: column; }
            .card { padding: 17px; border-radius: 11px; }
            .table-wrap { overflow-x: auto; }
            table { min-width: 720px; }
            .topbar-user { max-width: 150px; }
            .steps { gap: 5px; }
            .steps li { font-size: 10px; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
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
                @if ($canViewMembershipApplications && \Illuminate\Support\Facades\Route::has('member.membership-applications'))
                    <a class="nav-link {{ request()->routeIs('member.membership-applications') ? 'active' : '' }}" href="{{ route('member.membership-applications') }}">
                        <span class="nav-icon" aria-hidden="true">▤</span><span>Membership Applications</span>
                        @if ($membershipPendingCount > 0)<span class="nav-count" aria-label="{{ $membershipPendingCount }} pending actions">{{ $membershipPendingCount > 99 ? '99+' : $membershipPendingCount }}</span>@endif
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
