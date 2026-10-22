<div class="access-control-page">
    <div class="page-head"><div><p class="eyebrow">Access Control</p><h1>Roles and permissions</h1><p class="muted">Create organization-specific roles and assign permissions from enabled modules.</p></div></div>
    @if ($successMessage)<div class="card" style="color:var(--ok);padding:12px 16px">{{ $successMessage }}</div>@endif
    @error('roleDelete')<div class="card" style="color:var(--danger);padding:12px 16px">{{ $message }}</div>@enderror
    <div class="content-grid" style="display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.4fr);gap:20px;align-items:start">
        <section class="card"><h2>{{ $editingRoleId ? 'Edit custom role' : 'Create custom role' }}</h2>
            <form wire:submit="save" class="form-stack" style="display:grid;gap:12px">
                <label>Role name<input type="text" wire:model="name" maxlength="150" required></label>@error('name')<small class="error-text">{{ $message }}</small>@enderror
                <label class="role-description-field" style="display:grid;gap:7px;font-weight:600">Description<textarea class="form-textarea" style="display:block;width:100%;min-height:104px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:var(--surface, #fff);color:inherit;font:inherit;font-weight:400;resize:vertical;box-sizing:border-box" wire:model="description" rows="3" maxlength="1000" placeholder="Describe what this role is responsible for"></textarea></label>@error('description')<small class="error-text">{{ $message }}</small>@enderror
                <div class="panel-subheading"><strong>Permissions</strong><span class="muted">{{ count($selectedPermissions) }} selected</span></div>
                <input type="search" wire:model.live.debounce.250ms="permissionSearch" placeholder="Search permissions or modules" aria-label="Search permissions">
                @error('selectedPermissions')<small class="error-text">{{ $message }}</small>@enderror
                <div class="permission-groups" style="max-height:480px;overflow:auto;display:grid;gap:10px">
                    @forelse ($permissions as $module => $modulePermissions)
                    <details class="permission-group" open><summary><strong>{{ $module }}</strong><span class="muted">{{ $modulePermissions->count() }} permissions</span></summary><div class="permission-items" style="padding:10px;display:grid;gap:8px">
                        @foreach ($modulePermissions as $permission)<label class="permission-option" style="display:flex;gap:9px;align-items:flex-start"><input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->id }}"><span><strong>{{ $permission->name }}</strong><small class="muted" style="display:block">{{ $permission->code }}@if ($permission->description) — {{ $permission->description }}@endif</small></span></label>@endforeach
                    </div></details>
                    @empty<p class="muted">No permissions available for enabled modules.</p>@endforelse
                </div>
                <div class="button-row" style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn primary" type="submit">{{ $editingRoleId ? 'Save role' : 'Create role' }}</button>@if ($editingRoleId)<button class="btn secondary" type="button" wire:click="cancelEdit">Cancel</button>@endif</div>
            </form>
        </section>
        <section style="display:grid;gap:18px;min-width:0"><div class="card"><h2>Organization custom roles</h2>
            @if ($roles->isEmpty())<p class="muted">No custom roles have been created yet.</p>@else<div class="table-wrap" style="overflow-x:auto"><table class="data-table"><thead><tr><th>Role</th><th>Permissions</th><th>Assignments</th><th>Actions</th></tr></thead><tbody>@foreach ($roles as $role)<tr><td><strong>{{ $role->name }}</strong>@if ($role->description)<div class="muted">{{ $role->description }}</div>@endif</td><td>{{ $role->permissions_count }}</td><td>{{ $role->assignments_count }}</td><td><div class="button-row"><button class="btn secondary" type="button" wire:click="edit({{ $role->id }})">Edit</button><button class="btn danger" type="button" wire:click="delete({{ $role->id }})" wire:confirm="Delete this role? This cannot be undone.">Delete</button></div></td></tr>@endforeach</tbody></table></div>@endif
        </div><div class="card"><h2>Built-in roles</h2><p class="muted">Built-in roles are managed by the application and cannot be edited here.</p>
            @forelse ($builtInRoles as $role)<div class="list-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)"><div><strong>{{ $role->name }}</strong><div class="muted">{{ $role->code }}</div></div><span class="pill">{{ $role->permissions_count }} permissions</span></div>@empty<p class="muted">No built-in roles registered.</p>@endforelse
        </div></section>
    </div>
</div>
