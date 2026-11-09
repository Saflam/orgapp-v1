@php
    // Optional module profile data may be absent for members without a saved
    // Saradhi profile. Normalize it before rendering to avoid count(null).
    $fields = is_iterable($fields ?? null) ? $fields : [];
@endphp

@if(count($fields) > 0)
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach($fields as $field)
            <div class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
                <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $field['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@else
    <p class="mt-3 text-sm text-slate-500">Saradhi details have not been added yet.</p>
@endif
