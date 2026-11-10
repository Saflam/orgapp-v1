<?php $__env->startSection('title', $organization->name . ' — Official website'); ?>
<?php $__env->startSection('description', data_get($branding, 'tagline', 'Official website and services for ' . $organization->name)); ?>

<?php $__env->startPush('head'); ?>
    <link rel="icon" href="<?php echo e($faviconPath); ?>">
    <link rel="manifest" href="<?php echo e(route('organization.manifest')); ?>">
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-stone-50" style="--org-primary: <?php echo e($primaryColor); ?>; --org-accent: <?php echo e($accentColor); ?>">
    <?php echo $__env->make('organization.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <main>
        <section class="relative isolate overflow-hidden">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[30rem] opacity-80" style="background: radial-gradient(ellipse at top left, color-mix(in srgb, var(--org-accent) 20%, transparent), transparent 58%), linear-gradient(180deg, #ffffff 0%, #fafaf8 100%)"></div>
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:px-10 lg:py-28">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 shadow-sm">
                        <span class="size-2 rounded-full" style="background: var(--org-accent)"></span>
                        Official organization website
                    </span>
                    <h1 class="mt-7 text-4xl font-semibold leading-tight tracking-[-0.045em] text-slate-950 sm:text-5xl lg:text-6xl">Welcome to <span style="color: var(--org-primary)"><?php echo e($organization->name); ?></span></h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600"><?php echo e(data_get($branding, 'tagline', 'A connected community, working together.')); ?></p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="<?php echo e($isMember ? url('/member/profile') : ($hasPendingMembershipApplication ? url('/membership/apply') : url('/membership/apply'))); ?>" class="inline-flex min-h-12 items-center justify-center rounded-full px-6 text-sm font-semibold text-white shadow-md transition hover:opacity-90" style="background: var(--org-primary)"><?php echo e($isMember ? 'Member Profile' : (($hasDraftMembershipApplication ?? false) ? 'Continue Application' : ($hasPendingMembershipApplication ? 'Application in Progress' : 'Apply Membership'))); ?></a>
                        <a href="#about" class="inline-flex min-h-12 items-center justify-center rounded-full border border-slate-300 bg-white px-6 text-sm font-semibold text-slate-800 transition hover:bg-stone-100">Learn about us</a>
                    </div>
                    <p class="mt-5 text-xs leading-5 text-slate-500">A digital home for your organization, powered by Saflivo.</p>
                </div>

                <div class="relative mx-auto w-full max-w-lg">
                    <div aria-hidden="true" class="absolute -inset-5 rounded-[2.5rem] opacity-20 blur-2xl" style="background: var(--org-primary)"></div>
                    <div class="relative rounded-[2rem] border border-white bg-white p-6 shadow-[0_30px_90px_-35px_rgba(15,23,42,0.25)] sm:p-8">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-6">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Your community, connected</p>
                                <p class="mt-1 text-sm text-slate-500">One place for organization services</p>
                            </div>
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl text-xl font-bold text-white" style="background: var(--org-primary)"><?php echo e(mb_strtoupper(mb_substr($organization->name, 0, 1))); ?></span>
                        </div>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <article class="rounded-2xl bg-stone-50 p-5">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-lg shadow-sm" style="color: var(--org-primary)" aria-hidden="true">◎</span>
                                <h2 class="mt-4 text-sm font-semibold text-slate-900">Membership</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Member information and services in one place.</p>
                            </article>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventsModuleEnabled): ?>
                            <article class="rounded-2xl bg-stone-50 p-5">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-lg shadow-sm" style="color: var(--org-primary)" aria-hidden="true">▦</span>
                                <h2 class="mt-4 text-sm font-semibold text-slate-900">Latest Events</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Demo content: upcoming activities and gatherings will appear here.</p>
                            </article>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="mt-4 rounded-2xl p-5 text-white" style="background: var(--org-primary)">
                            <p class="text-sm font-semibold">Built around your organization</p>
                            <p class="mt-1 text-sm leading-6 text-white/80">A consistent experience with organization-specific identity and secure access.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="border-t border-slate-200/80 bg-white">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--org-primary)">About the organization</p>
                <div class="mt-4 grid gap-8 md:grid-cols-[0.8fr_1.2fr] md:items-start">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">A shared space for people and purpose.</h2>
                    <p class="max-w-2xl text-base leading-7 text-slate-600"><?php echo e(data_get($branding, 'about', 'This website is the digital front door for ' . $organization->name . '. Organization information and services can be tailored as the website evolves.')); ?></p>
                </div>
            </div>
        </section>
    </main>

        <section id="news" class="border-t border-slate-200/80 bg-stone-50">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--org-primary)">Community updates</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Latest News & Announcements</h2>
                <p class="mt-3 max-w-2xl text-slate-600">Demo content for the public website. News and announcements will be supplied by their respective modules when enabled.</p>
                <div class="mt-7 grid gap-4 md:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-6"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sample announcement</p><h3 class="mt-2 text-lg font-semibold text-slate-900">Welcome to our community website</h3><p class="mt-2 text-sm leading-6 text-slate-600">Organization news and public notices will appear here when the relevant module is available.</p></article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Community update</p><h3 class="mt-2 text-lg font-semibold text-slate-900">Stay connected</h3><p class="mt-2 text-sm leading-6 text-slate-600">Check back for public updates and important information from the organization.</p></article>
                </div>
            </div>
        </section>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventsModuleEnabled): ?>
        <section id="events" class="border-t border-slate-200/80 bg-white">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--org-primary)">What’s happening</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Upcoming Events</h2>
                <p class="mt-3 text-slate-600">Sample public event content. Only events marked as public should be shown here.</p>
                <article class="mt-6 rounded-2xl border border-slate-200 p-6"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sample event</p><h3 class="mt-2 text-lg font-semibold text-slate-900">Community gathering</h3><p class="mt-2 text-sm leading-6 text-slate-600">Demo event card. It will be replaced by public events from the Events module.</p></article>
            </div>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <section id="contact" class="border-t border-slate-200/80 bg-white">
            <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10"><h2 class="text-2xl font-semibold tracking-tight text-slate-950">Contact Us</h2><p class="mt-3 text-slate-600">Contact details can be configured for this organization.</p></div>
        </section>

    <footer class="border-t border-slate-200 bg-stone-50">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
            <span>© <?php echo e(now()->year); ?> <?php echo e($organization->name); ?></span>
            <span>Powered by Saflivo</span>
        </div>
    </footer>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/organization/site.blade.php ENDPATH**/ ?>