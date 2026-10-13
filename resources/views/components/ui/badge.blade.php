@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-600/10',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-700/15',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-700/15',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-700/15',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-700/15',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
