<?php $__env->startSection('title', 'Apply for Membership — ' . $organization->name); ?>
<?php $__env->startSection('description', 'Complete your membership application for ' . $organization->name . '.'); ?>

<?php $__env->startPush('head'); ?>
    <link rel="manifest" href="<?php echo e(route('organization.manifest')); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <style>
        :root {
            --membership-primary: <?php echo e($primaryColor ?? '#174b3d'); ?>;
            --membership-accent: <?php echo e($accentColor ?? '#d9a441'); ?>;
        }
        .membership-app { padding-bottom: max(1.25rem, env(safe-area-inset-bottom)); }
        .membership-control { width: 100%; min-height: 48px; border: 1px solid #d6d9d5; border-radius: 0.9rem; background: #fff; padding: 0.72rem 0.9rem; color: #17211d; font: inherit; }
        .membership-control:focus { outline: 3px solid color-mix(in srgb, var(--membership-primary) 20%, transparent); border-color: var(--membership-primary); }
        .membership-label { display: block; margin-bottom: .4rem; color: #34443d; font-size: .875rem; font-weight: 650; }
        .membership-field { min-width: 0; }
        .membership-card { border: 1px solid #e7e8e4; border-radius: 1.2rem; background: #fff; box-shadow: 0 8px 28px rgba(24, 39, 31, .035); }
        .membership-primary { background: var(--membership-primary); color: #fff; }
        .membership-primary:focus-visible, .membership-secondary:focus-visible { outline: 3px solid color-mix(in srgb, var(--membership-primary) 30%, transparent); outline-offset: 3px; }
        .membership-secondary { border: 1px solid #d9ded9; background: #fff; color: #27372f; }
        .membership-step[aria-current="step"] .membership-step-dot { background: var(--membership-primary); color: #fff; border-color: var(--membership-primary); }
        .membership-step[data-complete="true"] .membership-step-dot { background: #e5f3eb; color: var(--membership-primary); border-color: #b8d9c7; }
        .membership-step-label { display: none; }
        @media (min-width: 640px) { .membership-step-label { display: block; } }
        .membership-error { color: #b42318; font-size: .875rem; }
        .membership-panel[hidden] { display: none !important; }
        .membership-actionbar { padding-bottom: max(.9rem, env(safe-area-inset-bottom)); }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="membership-app min-h-screen bg-[#f7f8f5] text-slate-900" data-membership-application
     data-config-url="<?php echo e(route('organization.membership.apply.configuration')); ?>"
     data-submit-url="<?php echo e(($spouseMode ?? false) ? route('organization.membership.apply.spouse.submit') : route('organization.membership.apply.submit')); ?>"
     data-draft-url="<?php echo e(route('organization.membership.apply.draft')); ?>"
     data-spouse-search-url="<?php echo e(route('organization.membership.apply.spouse-search')); ?>"
     data-spouse-registration-url="<?php echo e(route('organization.membership.apply.spouse')); ?>"
     data-spouse-mode="<?php echo e(($spouseMode ?? false) ? 'true' : 'false'); ?>"
     data-spouse-prefill="<?php echo e(e(json_encode($spousePrefill ?? []))); ?>"
     data-user-id="<?php echo e($authenticatedUser->id); ?>"
     data-user-name="<?php echo e($authenticatedUser->name); ?>"
     data-user-email="<?php echo e($authenticatedUser->email); ?>"
     data-user-phone="<?php echo e($authenticatedUser->phone ?? ''); ?>"
     style="--membership-primary: <?php echo e($primaryColor ?? '#174b3d'); ?>; --membership-accent: <?php echo e($accentColor ?? '#d9a441'); ?>">
    <header class="border-b border-stone-200/80 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="<?php echo e(route('organization.home')); ?>" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="--tw-ring-color: var(--membership-primary)" aria-label="<?php echo e($organization->name); ?> home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-bold" style="background: color-mix(in srgb, var(--membership-primary) 10%, white); color: var(--membership-primary)"><?php echo e(mb_strtoupper(mb_substr($organization->name, 0, 1))); ?></span>
                <span class="min-w-0"><span class="block truncate text-sm font-bold text-slate-950 sm:text-base"><?php echo e($organization->name); ?></span><span class="block text-xs text-slate-500">Membership application</span></span>
            </a>
            <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                <a href="<?php echo e(route('organization.home')); ?>" class="hidden min-h-11 items-center rounded-full px-3 text-sm font-semibold text-slate-600 hover:bg-stone-100 sm:inline-flex">Back to home</a>
                <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button type="submit" class="min-h-11 rounded-full px-3 text-sm font-semibold text-slate-600 hover:bg-stone-100">Log out</button></form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10 lg:px-8 lg:pb-14">
        <div class="mx-auto max-w-4xl">
            <div class="mb-7 sm:mb-9">
                <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide" style="color: var(--membership-primary)">
                    <span class="h-2 w-2 rounded-full" style="background: var(--membership-accent)"></span>
                    Membership
                </div>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Become a member</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Start with your preferred unit and personal details. Step 1 is saved securely so you can continue later; the full application is submitted only when you confirm it.</p>
                    </div>
                    <div class="hidden shrink-0 rounded-2xl border border-stone-200 bg-white px-4 py-3 sm:block">
                        <p class="text-xs font-medium text-slate-500">Applying as</p>
                        <p class="mt-1 max-w-56 truncate text-sm font-semibold text-slate-900"><?php echo e($authenticatedUser->name); ?></p>
                    </div>
                </div>
            </div>

            <div id="membership-loading" class="membership-card flex items-center gap-3 p-5" role="status">
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-stone-200 border-t-emerald-800"></span>
                <span class="text-sm text-slate-600">Preparing your organization’s application form…</span>
            </div>

            <div id="membership-load-error" class="membership-card hidden p-6 sm:p-8" role="alert">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-700"><svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17.2A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <h2 class="mt-4 text-lg font-bold">We couldn’t load the application</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600" data-load-error-message>Please check your connection and try again.</p>
                <button type="button" data-retry class="membership-primary mt-5 min-h-12 rounded-full px-5 text-sm font-semibold">Try again</button>
            </div>

            <form id="membership-application-form" class="hidden" enctype="multipart/form-data" novalidate>
                <div class="membership-card mb-5 p-3 sm:p-5">
                    <div id="membership-stepper" class="grid grid-cols-4 gap-2 sm:gap-3" aria-label="Application progress"></div>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-label="Application progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="membership-progress"><div class="h-full rounded-full transition-all duration-300" data-progress-fill style="width:0;background:var(--membership-primary)"></div></div>
                </div>

                <div id="membership-panels" class="space-y-5"></div>

                <div id="membership-form-error" class="membership-card mt-5 hidden border-rose-200 bg-rose-50 p-4" role="alert" aria-live="polite">
                    <p class="font-semibold text-rose-900">Please check the information below.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-800" data-error-list></ul>
                </div>

                <div class="membership-actionbar fixed inset-x-0 bottom-0 z-20 border-t border-stone-200 bg-white/95 px-4 pt-3 backdrop-blur sm:static sm:mt-6 sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:backdrop-blur-none">
                    <div class="mx-auto flex max-w-4xl items-center justify-between gap-3">
                        <button type="button" id="membership-previous" class="membership-secondary inline-flex min-h-12 min-w-28 items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40" disabled><span aria-hidden="true">←</span> Previous</button>
                        <p class="hidden text-center text-xs text-slate-500 sm:block">Your details are submitted securely to <?php echo e($organization->name); ?>.</p>
                        <button type="button" id="membership-next" class="membership-primary inline-flex min-h-12 min-w-32 items-center justify-center gap-2 rounded-full px-5 text-sm font-semibold shadow-sm disabled:cursor-wait disabled:opacity-60">Continue <span aria-hidden="true">→</span></button>
                    </div>
                </div>
            </form>

            <div id="membership-success" class="membership-card hidden p-6 text-center sm:p-10" role="status" aria-live="polite">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50" style="color:var(--membership-primary)"><svg viewBox="0 0 24 24" fill="none" class="h-8 w-8" aria-hidden="true"><path d="m5 12.5 4.2 4.2L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <p class="mt-5 text-sm font-bold uppercase tracking-wider" style="color:var(--membership-primary)">Application submitted</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Thank you for applying</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600" data-success-message>Your application has been sent to the organization for processing.</p>
                <a href="<?php echo e(route('organization.home')); ?>" class="membership-primary mt-7 inline-flex min-h-12 items-center justify-center rounded-full px-6 text-sm font-semibold">Return to home</a>
            </div>
        </div>
    </main>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/organization/membership-apply.blade.php ENDPATH**/ ?>