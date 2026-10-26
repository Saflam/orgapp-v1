<div class="committee-page">
    <header class="committee-page-header"><div><h1>Current Committees</h1><p class="committee-page-description">Current central committees and committees for {{ $member->unit?->name ?? 'your unit (not assigned)' }}.</p></div></header>
    @if($committees->isEmpty())
        <section class="committee-form-card"><p class="committee-muted">There are no current committees available for your organization or unit.</p></section>
    @else
        @foreach($committees as $committee)
            @foreach($committee->terms as $term)
                <section class="committee-current-group">
                    <header class="committee-current-group-header"><div><p class="committee-eyebrow">{{ $committee->unit?->name ?? 'Central committee' }}</p><h2>{{ $committee->name }}</h2><p class="committee-page-description">{{ $term->start_date?->format('d M Y') }} – {{ $term->end_date?->format('d M Y') }}</p></div><span class="committee-status is-active">Current</span></header>
                    <div class="committee-table-card"><div class="committee-table-scroll"><table class="committee-table committee-members-table"><thead><tr><th>Photo</th><th>Order</th><th>Designation</th><th>Member</th><th>Membership number</th><th>Unit</th></tr></thead><tbody>
                        @forelse($term->memberships as $assignment)
                            @php($personName = trim((string) $assignment->member?->user?->name) ?: 'Unnamed member')
                            <tr wire:key="current-committee-member-{{ $assignment->id }}"><td><img src="{{ route('committee.member-photo', $assignment->member_id) }}" alt="" class="committee-member-avatar committee-member-table-avatar" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="committee-member-avatar committee-member-avatar-placeholder committee-member-table-avatar" hidden>{{ mb_substr($personName, 0, 1) }}</span></td><td>{{ $assignment->designation?->sort_order ?? 100 }}</td><td class="committee-primary-cell">{{ $assignment->designation?->name ?? '—' }} @if($assignment->designation?->is_key_position)<span class="committee-status is-key-position">Key position</span>@endif</td><td>{{ $personName }}</td><td>{{ $assignment->member?->membership_number ?: ('Member #' . $assignment->member_id) }}</td><td>{{ $assignment->member?->unit?->name ?? '—' }}</td></tr>
                        @empty<tr><td colspan="6" class="committee-empty-state">No current members have been assigned.</td></tr>@endforelse
                    </tbody></table></div></div>
                </section>
            @endforeach
        @endforeach
    @endif
</div>

