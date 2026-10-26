<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Committee Terms</h1><p class="committee-page-description">{{ $committee->name }} · {{ $committee->unit?->name ?? 'Central organization' }}</p></div>
        <div class="committee-page-actions"><a href="{{ route('committee.committees') }}" class="committee-button committee-button-secondary">← Back to committees</a></div>
    </header>
    @if (session()->has('committee_message'))<div class="committee-alert committee-alert-success">{{ session('committee_message') }}</div>@endif
    @if (session()->has('committee_error'))<div class="committee-alert committee-alert-error">{{ session('committee_error') }}</div>@endif
    <section class="committee-form-card">
        <h2>{{ $editingTermId ? 'Edit term' : 'Add committee term' }}</h2>
        <form wire:submit="saveTerm" class="committee-term-form-inline">
            <div class="committee-form-field"><label for="committee-term-start">Term start date</label><input id="committee-term-start" type="date" wire:model="termStartDate" class="committee-search-input" required>@error('termStartDate')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
            <div class="committee-form-field"><label for="committee-term-end">Term end date</label><input id="committee-term-end" type="date" wire:model="termEndDate" class="committee-search-input" required>@error('termEndDate')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
            <div class="committee-term-form-actions"><button type="submit" class="committee-button committee-button-primary">{{ $editingTermId ? 'Update term' : 'Add term' }}</button>@if($editingTermId)<button type="button" wire:click="cancelEdit" class="committee-button committee-button-secondary">Cancel</button>@endif</div>
        </form>
    </section>
    <section class="committee-table-card"><div class="committee-table-scroll"><table class="committee-table"><thead><tr><th>Term period</th><th>Members</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($committee->terms as $term)
            @php
                $termStatus = $term->status?->value ?? (string) $term->status;
                $isCurrentTerm = $termStatus === 'active' && $term->start_date?->lte(today()) && $term->end_date?->gte(today());
                $activeMembers = $term->memberships->sortBy(fn ($membership) => $membership->member?->user?->name ?? '')->values();
                $avatarMembers = $activeMembers->take(3);
            @endphp
            <tr wire:key="committee-term-{{ $term->id }}">
                <td><strong>{{ $term->start_date?->format('d M Y') ?? '—' }} – {{ $term->end_date?->format('d M Y') ?? '—' }}</strong>@if($isCurrentTerm)<div><span class="committee-status is-current-term">Current term</span></div>@endif</td>
                <td><div class="committee-term-avatar-group" aria-label="{{ $activeMembers->count() }} active members">
                    @forelse($avatarMembers as $membership)
                        @php($avatarName = trim((string) $membership->member?->user?->name) ?: 'Member')
                        <img class="committee-term-avatar" src="{{ route('committee.member-photo', $membership->member_id) }}" alt="{{ $avatarName }}" title="{{ $avatarName }}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="committee-term-avatar committee-term-avatar-fallback" hidden title="{{ $avatarName }}">{{ mb_substr($avatarName, 0, 1) }}</span>
                    @empty
                        <span class="committee-muted">No members</span>
                    @endforelse
                    @if($activeMembers->count() > 3)<span class="committee-term-avatar-more">+{{ $activeMembers->count() - 3 }}</span>@endif
                    <span class="committee-term-member-count">{{ $activeMembers->count() }}</span>
                </div></td>
                <td><span class="committee-status {{ $isCurrentTerm ? 'is-active' : ($termStatus === 'active' ? 'is-active' : 'is-inactive') }}">{{ $isCurrentTerm ? 'Current' : ucfirst($termStatus) }}</span></td>
                <td><div class="committee-row-actions">
                    @if($termStatus !== 'completed' && $termStatus !== 'cancelled')<button type="button" wire:click="editTerm({{ $term->id }})" class="committee-button committee-button-secondary">Edit</button>@endif
                    @if($termStatus === 'draft')<button type="button" wire:click="activateTerm({{ $term->id }})" wire:confirm="Activate this committee term?" class="committee-button committee-button-primary">Activate</button>@elseif($termStatus === 'active')<a href="{{ route('committee.members', $term->id) }}" class="committee-button committee-button-primary">Manage members</a><button type="button" wire:click="completeTerm({{ $term->id }})" wire:confirm="Mark this committee term as completed?" class="committee-button committee-button-secondary">Complete</button>@endif
                </div></td>
            </tr>
        @empty<tr><td colspan="4" class="committee-empty-state">No terms have been created for this committee yet.</td></tr>@endforelse
    </tbody></table></div></section>
</div>

