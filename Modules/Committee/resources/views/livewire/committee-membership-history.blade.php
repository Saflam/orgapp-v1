<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Membership history</h1><p class="committee-page-description">{{ $term->committee->name }} · {{ $term->start_date?->format('d M Y') }} – {{ $term->end_date?->format('d M Y') }}</p><p class="committee-page-description">{{ $term->committee->unit?->name ?? 'Central organization' }}</p></div>
        <div class="committee-page-actions"><a href="{{ route('committee.members', $term->id) }}" class="committee-button committee-button-secondary">← Back to current members</a></div>
    </header>
    <section class="committee-table-card"><div class="committee-table-scroll"><table class="committee-table committee-members-table"><thead><tr><th>Member</th><th>Membership number</th><th>Designation</th><th>Start date</th><th>Concluded date</th><th>Status</th><th>Reason / note</th></tr></thead><tbody>
    @forelse($term->memberships as $membership)
        @php($name = trim((string) $membership->member?->user?->name) ?: 'Unnamed member')
        <tr wire:key="committee-history-{{ $membership->id }}"><td><a href="{{ route('committee.member-profile', $membership->member_id) }}">{{ $name }}</a></td><td>{{ $membership->member?->membership_number ?: ('Member #' . $membership->member_id) }}</td><td>{{ $membership->designation?->name ?? '—' }}</td><td>{{ $membership->start_date?->format('d M Y') ?? '—' }}</td><td>{{ $membership->end_date?->format('d M Y') ?? '—' }}</td><td><span class="committee-status is-inactive">{{ ucfirst($membership->status?->value ?? (string) $membership->status) }}</span></td><td>{{ $membership->end_reason ?: 'No reason recorded' }}</td></tr>
    @empty<tr><td colspan="7" class="committee-empty-state">There is no membership history for this term yet.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
