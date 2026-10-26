<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Committees</h1><p class="committee-page-description">Committees configured for this organization.</p></div>
        <div class="committee-page-actions">
            <a href="{{ route('committee.dashboard') }}" class="committee-button committee-button-secondary"><span aria-hidden="true">←</span> Committee overview</a>
            @if ($canManage)<button type="button" wire:click="create" class="committee-button committee-button-primary"><span aria-hidden="true">+</span> Create committee</button>@endif
        </div>
    </header>
    @if (session()->has('committee_message'))<div class="committee-alert committee-alert-success">{{ session('committee_message') }}</div>@endif
    @if (session()->has('committee_error'))<div class="committee-alert committee-alert-error">{{ session('committee_error') }}</div>@endif
    @if ($showCreateForm)
        <section class="committee-form-card">
            <h2>Create committee</h2>
            <form wire:submit="save" class="committee-form-grid">
                <div class="committee-form-field"><label for="committee-name">Committee name</label><input id="committee-name" wire:model="name" class="committee-search-input" required>@error('name')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label for="committee-type">Committee type</label><select id="committee-type" wire:model="committeeTypeId" class="committee-select" required><option value="">Select type</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select>@error('committeeTypeId')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label for="committee-kind">Committee kind</label><select id="committee-kind" wire:model="kind" class="committee-select"><option value="recurring">Recurring</option><option value="temporary">Temporary</option></select></div>
                <div class="committee-form-field"><label for="committee-unit">Unit (leave blank for central)</label><select id="committee-unit" wire:model="unitId" class="committee-select"><option value="">Central organization</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select>@error('unitId')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label for="committee-parent">Parent committee (optional)</label><select id="committee-parent" wire:model="parentCommitteeId" class="committee-select"><option value="">No parent</option>@foreach($parentCommittees as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select>@error('parentCommitteeId')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-actions"><button type="submit" class="committee-button committee-button-primary">Save committee</button><button type="button" wire:click="cancelCreate" class="committee-button">Cancel</button></div>
            </form>
        </section>
    @endif
    <section class="committee-filter-card">
        <label class="committee-sr-only" for="committee-search">Search committees</label>
        <input id="committee-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search committees…" class="committee-search-input">
    </section>
    <section class="committee-table-card">
        <div class="committee-table-scroll"><table class="committee-table">
            <thead><tr><th scope="col">Committee</th><th scope="col">Type</th><th scope="col">Scope</th><th scope="col">Terms</th><th scope="col">Current term</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                @forelse ($committees as $committee)
                    @php
                        $currentTerm = $committee->terms->first(function ($term) {
                            $status = $term->status?->value ?? (string) $term->status;
                            return $status === 'active' && $term->start_date?->lte(today()) && $term->end_date?->gte(today());
                        });
                        $activeTerm = $committee->terms->first(fn ($term) => ($term->status?->value ?? (string) $term->status) === 'active');
                    @endphp
                    <tr><td class="committee-primary-cell">{{ $committee->name }}</td><td>{{ $committee->type?->name ?? '—' }}</td><td>{{ $committee->unit?->name ?? 'Central' }}</td><td>{{ $committee->terms->count() }}</td><td>@if($currentTerm)<span class="committee-status is-active">Current</span><div class="committee-muted">{{ $currentTerm->start_date?->format('d M Y') }} – {{ $currentTerm->end_date?->format('d M Y') }}</div>@elseif($activeTerm)<span class="committee-status is-inactive">Active, outside dates</span><div class="committee-muted">{{ $activeTerm->start_date?->format('d M Y') }} – {{ $activeTerm->end_date?->format('d M Y') }}</div>@else<span class="committee-muted">No current term</span>@endif</td><td><span class="committee-status {{ $committee->status?->value === 'active' ? 'is-active' : 'is-inactive' }}">{{ ucfirst($committee->status?->value ?? 'unknown') }}</span></td><td>@if($canManage && $committee->kind?->value === 'recurring')<a href="{{ route('committee.terms', $committee->id) }}" class="committee-button committee-button-secondary">Committee Terms</a>@else<span class="committee-muted">—</span>@endif</td></tr>
                @empty
                    <tr><td colspan="7" class="committee-empty-state">No committees found for this organization.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="committee-pagination">{{ $committees->links() }}</div>
    </section>


</div>



