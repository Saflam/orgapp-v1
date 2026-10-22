<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Committee member profile</h1><p class="committee-page-description">{{ trim((string) $member->user?->name) ?: 'Unnamed member' }} · {{ $member->membership_number ?: ('Member #' . $member->id) }}</p><p class="committee-page-description">Unit: {{ $member->unit?->name ?? '—' }}</p></div>
        <div class="committee-page-actions"><a href="{{ url()->previous() }}" class="committee-button committee-button-secondary">← Back</a></div>
    </header>
    <section class="committee-table-card"><div class="committee-table-card-header"><h2>Current committee assignments</h2></div><div class="committee-table-scroll"><table class="committee-table"><thead><tr><th>Committee</th><th>Scope</th><th>Term</th><th>Designation</th><th>Start date</th><th>End date</th></tr></thead><tbody>
    @forelse($currentAssignments as $assignment)
        <tr wire:key="committee-profile-current-{{ $assignment->id }}"><td>{{ $assignment->committeeTerm?->committee?->name ?? '—' }}</td><td>{{ $assignment->committeeTerm?->committee?->unit?->name ?? 'Central organization' }}</td><td>{{ $assignment->committeeTerm?->start_date?->format('d M Y') }} – {{ $assignment->committeeTerm?->end_date?->format('d M Y') }}</td><td>{{ $assignment->designation?->name ?? '—' }}</td><td>{{ $assignment->start_date?->format('d M Y') ?? '—' }}</td><td>{{ $assignment->end_date?->format('d M Y') ?? '—' }}</td></tr>
    @empty<tr><td colspan="6" class="committee-empty-state">This member has no current committee assignments.</td></tr>@endforelse
    </tbody></table></div></section>
    <section class="committee-table-card" style="margin-top:20px"><div class="committee-table-card-header"><h2>Previous committee assignments</h2></div><div class="committee-table-scroll"><table class="committee-table"><thead><tr><th>Committee</th><th>Scope</th><th>Term</th><th>Designation</th><th>Start date</th><th>Concluded date</th><th>Status</th><th>Reason / note</th></tr></thead><tbody>
    @forelse($historyAssignments as $assignment)
        <tr wire:key="committee-profile-history-{{ $assignment->id }}"><td>{{ $assignment->committeeTerm?->committee?->name ?? '—' }}</td><td>{{ $assignment->committeeTerm?->committee?->unit?->name ?? 'Central organization' }}</td><td>{{ $assignment->committeeTerm?->start_date?->format('d M Y') }} – {{ $assignment->committeeTerm?->end_date?->format('d M Y') }}</td><td>{{ $assignment->designation?->name ?? '—' }}</td><td>{{ $assignment->start_date?->format('d M Y') ?? '—' }}</td><td>{{ $assignment->end_date?->format('d M Y') ?? '—' }}</td><td>{{ ucfirst($assignment->status?->value ?? (string) $assignment->status) }}</td><td>{{ $assignment->end_reason ?: 'No reason recorded' }}</td></tr>
    @empty<tr><td colspan="8" class="committee-empty-state">No previous committee assignments recorded.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
