@props(['label', 'name', 'hint' => null, 'error' => null, 'type' => 'text', 'required' => false])
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-800">{{ $label }}@if($required)<span class="ml-1 text-rose-700" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($required) required @endif {{ $attributes->class(['block min-h-11 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10', $error ? 'border-rose-400' : 'border-slate-300']) }} @if($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
    @if($hint && !$error)<p class="text-xs leading-5 text-slate-500">{{ $hint }}</p>@endif
    @if($error)<p id="{{ $name }}-error" class="text-xs font-medium text-rose-700">{{ $error }}</p>@endif
</div>
