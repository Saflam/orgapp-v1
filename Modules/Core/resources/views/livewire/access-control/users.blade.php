<div class="access-control-page">
    <div class="page-head"><div><p class="eyebrow">Access Control</p><h1>Organization users</h1><p class="muted">Search users registered with this organization and review their organization roles.</p></div></div>
    <section class="card">
        
        <div class="toolbar access-user-filters" style="display:flex;flex-direction:row;gap:12px;flex-wrap:nowrap;align-items:center;margin-bottom:18px">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, or phone" aria-label="Search users" style="min-width:0;flex:1 1 auto">
            <select wire:model.live="status" aria-label="Membership status" style="flex:0 0 190px;min-width:170px"><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select>
        </div>
        @if ($memberships->isEmpty())<p class="muted">No organization users match your search.</p>@else
        <div class="table-wrap" style="overflow-x:auto"><table class="data-table"><thead><tr><th>User</th><th>Phone</th><th>Organization status</th><th>Organization roles</th><th>Joined</th></tr></thead><tbody>
        @foreach ($memberships as $membership)<tr><td><strong>{{ $membership->user?->name ?? 'Unknown user' }}</strong><div class="muted">{{ $membership->user?->email }}</div></td><td>{{ $membership->user?->phone ?: '—' }}</td><td><span class="pill">{{ ucfirst($membership->status) }}</span></td><td>{{ $membership->organizationRoles->pluck('name')->join(', ') ?: 'No roles assigned' }}</td><td>{{ $membership->joined_at?->format('d M Y') ?? '—' }}</td></tr>@endforeach
        </tbody></table></div><div style="margin-top:16px">{{ $memberships->links() }}</div>@endif
    </section>
</div>
