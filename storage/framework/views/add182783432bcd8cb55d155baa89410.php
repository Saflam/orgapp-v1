<?php $__env->startSection('title', 'Profile — ' . $organization->name); ?>
<?php $__env->startPush('head'); ?>
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-stone-50" style="--org-primary: <?php echo e($primaryColor); ?>">
    <?php echo $__env->make('organization.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider" style="color: var(--org-primary)">Account</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Your Profile</h1>
        <p class="mt-3 text-slate-600">Your account details for <?php echo e($organization->name); ?>.</p>
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <dl class="grid gap-5 sm:grid-cols-2"><div><dt class="text-sm text-slate-500">Name</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($user->name); ?></dd></div><div><dt class="text-sm text-slate-500">Email</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($user->email); ?></dd></div></dl>
        </section>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMember): ?>
            <a href="<?php echo e(route('organization.member.profile')); ?>" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">View Member Profile</a>
        <?php elseif($hasDraftMembershipApplication ?? false): ?>
            <a href="<?php echo e(route('organization.membership.apply')); ?>" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">Continue Application</a>
        <?php elseif($hasPendingMembershipApplication): ?>
            <a href="<?php echo e(route('organization.membership.apply')); ?>" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">View Application Status</a>
        <?php else: ?>
            <a href="<?php echo e(route('organization.membership.apply')); ?>" class="mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold text-white" style="background: var(--org-primary)">Apply Membership</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </main>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/organization/profile.blade.php ENDPATH**/ ?>