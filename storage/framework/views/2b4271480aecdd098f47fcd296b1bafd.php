<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tone' => 'neutral']));

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

foreach (array_filter((['tone' => 'neutral']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $tones = [
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-600/10',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-700/15',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-700/15',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-700/15',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-700/15',
    ];
?>
<span <?php echo e($attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $tones[$tone] ?? $tones['neutral']])); ?>><?php echo e($slot); ?></span>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/components/ui/badge.blade.php ENDPATH**/ ?>