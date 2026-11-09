<div class="grid gap-3 sm:grid-cols-2">
    <label class="text-sm font-semibold text-slate-700">Governorate
        <input name="governorate" maxlength="255" value="{{ old('governorate', $profile->governorate) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">SNDP branch
        <input name="sndp_branch" maxlength="255" value="{{ old('sndp_branch', $profile->sndp_branch) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">SNDP branch number
        <input name="sndp_branch_number" maxlength="255" value="{{ old('sndp_branch_number', $profile->sndp_branch_number) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">SNDP union
        <input name="sndp_union" maxlength="255" value="{{ old('sndp_union', $profile->sndp_union) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">Introducer name
        <input name="introducer_name" maxlength="255" value="{{ old('introducer_name', $profile->introducer_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">Introducer MID
        <input name="introducer_mid" maxlength="255" value="{{ old('introducer_mid', $profile->introducer_mid) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">Introducer calling code
        <input name="introducer_calling_code" maxlength="8" placeholder="+91" value="{{ old('introducer_calling_code', $profile->introducer_calling_code) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700">Introducer phone
        <input name="introducer_phone" maxlength="30" value="{{ old('introducer_phone', $profile->introducer_phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
    </label>
    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Introducer unit
        <select name="introducer_unit_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            <option value="">No unit selected</option>
            @foreach($units as $unit)
                <option value="{{ $unit->id }}" @selected((string) old('introducer_unit_id', $profile->introducer_unit_id) === (string) $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
    </label>
</div>
