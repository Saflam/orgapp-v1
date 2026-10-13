<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'name', 'hint' => null, 'error' => null, 'type' => 'text', 'required' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['label', 'name', 'hint' => null, 'error' => null, 'type' => 'text', 'required' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="space-y-1.5">
    <label for="<?php echo e($name); ?>" class="block text-sm font-medium text-slate-800"><?php echo e($label); ?><?php if($required): ?><span class="ml-1 text-rose-700" aria-hidden="true">*</span><?php endif; ?></label>
    <input id="<?php echo e($name); ?>" name="<?php echo e($name); ?>" type="<?php echo e($type); ?>" <?php if($required): ?> required <?php endif; ?> <?php echo e($attributes->class(['block min-h-11 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10', $error ? 'border-rose-400' : 'border-slate-300'])); ?> <?php if($error): ?> aria-invalid="true" aria-describedby="<?php echo e($name); ?>-error" <?php endif; ?>>
    <?php if($hint && !$error): ?><p class="text-xs leading-5 text-slate-500"><?php echo e($hint); ?></p><?php endif; ?>
    <?php if($error): ?><p id="<?php echo e($name); ?>-error" class="text-xs font-medium text-rose-700"><?php echo e($error); ?></p><?php endif; ?>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/components/ui/field.blade.php ENDPATH**/ ?>