<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Designations</h1><p class="committee-page-description">Committee positions and their assigned permission counts.</p></div>
        <div class="committee-page-actions">
            <a href="{{ route('committee.dashboard') }}" class="committee-button committee-button-secondary"><span aria-hidden="true">←</span> Committee overview</a>
            @if ($canManage)<button type="button" wire:click="create" class="committee-button committee-button-primary"><span aria-hidden="true">+</span> Create designation</button>@endif
        </div>
    </header>
    @if (session()->has('committee_message'))<div class="committee-alert committee-alert-success">{{ session('committee_message') }}</div>@endif
    @if ($showCreateForm)
        <section class="committee-form-card">
            <h2>{{ $editingDesignationId ? 'Edit designation' : 'Create designation' }}</h2>
            <form wire:submit="save" class="committee-form-grid">
                <div class="committee-form-field"><label for="designation-name">Designation name</label><input id="designation-name" wire:model.live="name" class="committee-search-input" required>@error('name')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label for="designation-scope">Scope</label><select id="designation-scope" wire:model="designationScope" class="committee-select"><option value="central">Central</option><option value="unit">Unit</option></select>@error('designationScope')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label for="designation-sort-order">Display order</label><input id="designation-sort-order" type="number" min="1" max="9999" wire:model="sortOrder" class="committee-search-input" required><small class="committee-muted">Lower numbers appear first in committee member lists.</small>@error('sortOrder')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field"><label class="committee-check-label" for="designation-key-position"><input id="designation-key-position" type="checkbox" wire:model="isKeyPosition"> Key position</label><small class="committee-muted">Highlight important offices such as President or Secretary.</small></div>
                <div class="committee-form-field committee-form-field-full"><label for="designation-description">Description (optional)</label><textarea id="designation-description" wire:model="description" rows="3" class="committee-search-input"></textarea>@error('description')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                @if ($canManagePermissions)
                    <div class="committee-form-field committee-form-field-full">
                        <label>Designation permissions</label>
                        <p class="committee-muted">Only permissions belonging to modules enabled for this organization are listed. These permissions are granted to members holding this designation on an active committee term, subject to organization and unit scope checks.</p>
                        <div style="max-width:420px;margin:.75rem 0">
                            <label class="committee-sr-only" for="create-permission-search">Search permissions</label>
                            <input id="create-permission-search" type="search" wire:model.live.debounce.250ms="permissionSearch" placeholder="Search name, code, or module…" class="committee-search-input">
                        </div>
                        @if ($permissionsByModule->isEmpty())
                            <p class="committee-muted">No permissions match your search, or no permissions are available from enabled modules.</p>
                        @else
                            <div style="display:grid;gap:.65rem;margin-top:.5rem">
                                @foreach ($permissionsByModule as $module => $modulePermissions)
                                    <details open style="border:1px solid var(--committee-border,#ddd);border-radius:.6rem;overflow:hidden">
                                        <summary style="cursor:pointer;display:flex;justify-content:space-between;gap:1rem;padding:.75rem 1rem;background:var(--committee-surface,#f8fafc);font-weight:700">
                                            <span>{{ $module }}</span><span class="committee-muted" style="font-size:.85rem;font-weight:500">{{ $modulePermissions->count() }} permissions · {{ $modulePermissions->whereIn('id', $selectedPermissionIds)->count() }} selected</span>
                                        </summary>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:.6rem;padding:.75rem">
                                            @foreach ($modulePermissions as $permission)
                                                <label class="committee-check-label" style="align-items:flex-start;border:1px solid var(--committee-border,#ddd);border-radius:.5rem;padding:.65rem;gap:.5rem">
                                                    <input type="checkbox" wire:model="selectedPermissionIds" value="{{ $permission->id }}">
                                                    <span><strong>{{ $permission->name }}</strong><br><small class="committee-muted">{{ $permission->code }}</small>@if($permission->description)<br><small>{{ $permission->description }}</small>@endif</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                        @error('selectedPermissionIds')<small class="committee-field-error">{{ $message }}</small>@enderror
                    </div>
                @elseif ($editingDesignationId)
                    <div class="committee-form-field committee-form-field-full"><p class="committee-muted">You can edit designation details, but you do not have permission to change its permission assignments.</p></div>
                @endif
                <div class="committee-form-actions"><button type="submit" class="committee-button committee-button-primary">{{ $editingDesignationId ? 'Update designation' : 'Save designation' }}</button><button type="button" wire:click="cancelCreate" class="committee-button">Cancel</button></div>
            </form>
        </section>
    @endif
    <section class="committee-filter-card committee-filter-row">
        <div class="committee-filter-field">
            <label class="committee-sr-only" for="designation-search">Search designations</label>
            <input id="designation-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search designations…" class="committee-search-input">
        </div>
        <div class="committee-filter-field committee-scope-field">
            <label class="committee-sr-only" for="designation-scope">Scope</label>
            <select id="designation-scope" wire:model.live="scope" class="committee-select"><option value="all">All scopes</option><option value="central">Central</option><option value="unit">Unit</option></select>
        </div>
    </section>
    <section class="committee-table-card">
        <div class="committee-table-scroll"><table class="committee-table">
            <thead><tr><th scope="col">Order</th><th scope="col">Designation</th><th scope="col">Scope</th><th scope="col">Permissions</th><th scope="col">Status</th>@if($canManage)<th scope="col">Actions</th>@endif</tr></thead>
            <tbody>
                @forelse ($designations as $designation)
                    <tr><td>{{ $designation->sort_order }}</td><td class="committee-primary-cell">{{ $designation->name }} @if($designation->is_key_position)<span class="committee-status is-key-position">Key position</span>@endif</td><td>{{ ucfirst($designation->scope?->value ?? (string) $designation->scope) }}</td><td>{{ $designation->permissions_count }}</td><td><span class="committee-status {{ $designation->is_active ? 'is-active' : 'is-inactive' }}">{{ $designation->is_active ? 'Active' : 'Inactive' }}</span></td>@if($canManage)<td><a href="{{ route('committee.designations.edit', $designation->id) }}" class="committee-button committee-button-secondary">Edit</a></td>@endif</tr>
                @empty
                    <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="committee-empty-state">No designations found for this organization.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="committee-pagination">{{ $designations->links() }}</div>
    </section>
</div>
