@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])
@php
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
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
