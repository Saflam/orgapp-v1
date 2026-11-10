<?php $__env->startSection('title'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($spouseMode ?? false): ?>
        Apply as <?php echo e($spouseApplicantName ?: ($authenticatedUser->name ?: 'Applicant')); ?>'s spouse — <?php echo e($organization->name); ?>

    <?php else: ?>
        Apply for Membership — <?php echo e($organization->name); ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('description', 'Complete your membership application for ' . $organization->name . '.'); ?>

<?php $__env->startPush('head'); ?>
    <link rel="manifest" href="<?php echo e(route('organization.manifest')); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="membership-app min-h-screen bg-[#f7f8f5] text-slate-900" style="--membership-primary: <?php echo e($primaryColor ?? '#174b3d'); ?>; --membership-accent: <?php echo e($accentColor ?? '#d9a441'); ?>" data-membership-application
     data-config-url="<?php echo e(route('organization.membership.apply.configuration')); ?>"
     data-submit-url="<?php echo e(($spouseMode ?? false) ? route('organization.membership.apply.spouse.submit') : route('organization.membership.apply.submit')); ?>"
     data-draft-url="<?php echo e(route('organization.membership.apply.draft')); ?>"
     data-spouse-search-url="<?php echo e(route('organization.membership.apply.spouse-search')); ?>"
     data-spouse-registration-url="<?php echo e(route('organization.membership.apply.spouse')); ?>"
     data-spouse-mode="<?php echo e(($spouseMode ?? false) ? 'true' : 'false'); ?>"
     data-spouse-applicant-name="<?php echo e($spouseApplicantName ?? ''); ?>"
     data-spouse-prefill="<?php echo e(e(json_encode($spousePrefill ?? []))); ?>"
     data-spouse-invitation-token="<?php echo e($invitationToken ?? ''); ?>"
     data-user-id="<?php echo e($authenticatedUser->id); ?>"
     data-user-name="<?php echo e($authenticatedUser->name); ?>"
     data-user-email="<?php echo e($authenticatedUser->email); ?>"
     data-user-phone="<?php echo e($authenticatedUser->phone ?? ''); ?>"
     style="--membership-primary: <?php echo e($primaryColor ?? '#174b3d'); ?>; --membership-accent: <?php echo e($accentColor ?? '#d9a441'); ?>">
    <header class="border-b border-stone-200/80 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="<?php echo e(route('organization.home')); ?>" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="--tw-ring-color: var(--membership-primary)" aria-label="<?php echo e($organization->name); ?> home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-bold" style="background: color-mix(in srgb, var(--membership-primary) 10%, white); color: var(--membership-primary)"><?php echo e(mb_strtoupper(mb_substr($organization->name, 0, 1))); ?></span>
                <span class="min-w-0"><span class="block truncate text-sm font-bold text-slate-950 sm:text-base"><?php echo e($organization->name); ?></span><span class="block text-xs text-slate-500"><?php echo e(($spouseMode ?? false) ? 'Spouse membership application' : 'Membership application'); ?></span></span>
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
                        <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl"><?php echo e(($spouseMode ?? false) ? ('Apply as ' . ($spouseApplicantName ?: ($authenticatedUser->name ?: 'Applicant')) . "'s spouse") : 'Become a member'); ?></h1>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($spouseMode ?? false): ?>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                                You were invited by <?php echo e($spouseApplicantName ?? 'an organization member'); ?>. Complete your own application using your personal details. Your information will be reviewed by the organization.
                            </p>
                        <?php else: ?>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Share a few details to get started. Step 1 is saved securely when you continue, so you can return later. Your full application is submitted only at the end.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="hidden shrink-0 rounded-2xl border border-stone-200 bg-white px-4 py-3 sm:block">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($spouseMode ?? false): ?>
                            <p class="max-w-64 text-sm font-semibold text-slate-900">Applying as <?php echo e(($spouseApplicantName ?: $authenticatedUser->name ?: 'Applicant') . "'s spouse"); ?></p>
                        <?php else: ?>
                            <p class="text-xs font-medium text-slate-500">Applying as</p>
                            <p class="mt-1 max-w-56 truncate text-sm font-semibold text-slate-900"><?php echo e($authenticatedUser->name); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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


<?php $__env->startPush('scripts'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('Modules/Member/resources/assets/js/membership-application.js'); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Member\resources/views/organization/membership-apply.blade.php ENDPATH**/ ?>