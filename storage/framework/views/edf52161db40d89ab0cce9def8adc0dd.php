<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['padding' => 'md']));

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

foreach (array_filter((['padding' => 'md']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $paddings = ['sm' => 'p-4', 'md' => 'p-5 sm:p-6', 'lg' => 'p-6 sm:p-8'];
?>
<section <?php echo e($attributes->class(['rounded-2xl border border-slate-200/80 bg-white shadow-sm', $paddings[$padding] ?? $paddings['md']])); ?>><?php echo e($slot); ?></section>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/components/ui/card.blade.php ENDPATH**/ ?>