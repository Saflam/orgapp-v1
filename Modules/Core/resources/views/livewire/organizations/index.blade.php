<div>
    <div class="page-head">
        <div><h1>Organizations</h1><div class="muted">Create and manage organizations across Saflivo.</div></div>
        <a class="btn" href="{{ route('core.organizations.create') }}">+ Add organization</a>
    </div>
    <section class="card">
        <div class="grid">
            <div class="field"><label for="org-search">Search</label><input id="org-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Name, code or slug"></div>
            <div class="field"><label for="org-status">Status</label><select id="org-status" wire:model.live="status"><option value="all">All organizations</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Organization</th><th>Code</th><th>Slug</th><th>Status</th><th>Enabled modules</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td><strong>{{ $organization->name }}</strong></td><td>{{ $organization->code }}</td><td>{{ $organization->slug }}</td>
                        <td><span class="pill {{ $organization->is_active ? 'ok' : 'off' }}">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ $organization->enabled_modules_count }}</td>
                        <td><div class="actions"><a class="btn secondary" href="{{ route('core.organizations.manage', $organization) }}">Manage</a><button class="btn secondary" wire:click="toggleStatus({{ $organization->id }})" wire:confirm="{{ $organization->is_active ? 'Deactivate' : 'Activate' }} this organization?">{{ $organization->is_active ? 'Deactivate' : 'Activate' }}</button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No organizations match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $organizations->links() }}</div>
    </section>
</div>
