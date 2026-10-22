<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="theme-color" content="#173f35">
    <title><?php echo $__env->yieldContent('title', config('app.name', 'Saflivo')); ?></title>
    <meta name="description" content="<?php echo $__env->yieldContent('description', 'A connected workspace for membership, events, and organization operations.'); ?>">
    <?php echo app('Illuminate\Foundation\Vite')->fonts(); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->routeIs('organization.membership.apply', 'organization.membership.apply.spouse')): ?>
        <?php echo app('Illuminate\Foundation\Vite')('Modules/Member/resources/assets/js/membership-application.js'); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="min-h-screen bg-stone-50 font-sans text-slate-900 antialiased">
    <?php echo $__env->yieldContent('content'); ?>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/layouts/app.blade.php ENDPATH**/ ?>