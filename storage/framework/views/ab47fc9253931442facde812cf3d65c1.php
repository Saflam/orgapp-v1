<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
]));

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

foreach (array_filter(([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
    $variants = [
        'primary' => 'bg-emerald-950 text-white shadow-sm hover:bg-emerald-800',
        'secondary' => 'border border-slate-300 bg-white text-slate-800 hover:border-emerald-700 hover:text-emerald-900',
        'quiet' => 'bg-transparent text-slate-700 hover:bg-slate-100 hover:text-emerald-900',
        'danger' => 'bg-rose-700 text-white hover:bg-rose-800',
    ];
    $sizes = [
        'sm' => 'min-h-9 px-3.5 text-xs',
        'md' => 'min-h-11 px-4.5 text-sm',
        'lg' => 'min-h-12 px-6 text-sm',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
?>
<?php if($href): ?>
    <a href="<?php echo e($href); ?>" <?php echo e($attributes->merge(['class' => $classes])); ?>><?php echo e($slot); ?></a>
<?php else: ?>
    <button type="<?php echo e($type); ?>" <?php echo e($attributes->merge(['class' => $classes])); ?>><?php echo e($slot); ?></button>
<?php endif; ?>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\resources\views/components/ui/button.blade.php ENDPATH**/ ?>