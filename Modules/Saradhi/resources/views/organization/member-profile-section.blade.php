<div class="flex justify-end">
    <button type="button"
        onclick="document.getElementById('saradhi-profile-edit').classList.toggle('hidden')"
        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
        Edit details
    </button>
</div>

@if(count($fields))
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
