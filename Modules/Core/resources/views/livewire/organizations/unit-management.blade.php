<div>
    <div class="page-head">
        <div>
            <h1>Units</h1>
            <div class="muted">Manage the organizational units for {{ $organization->name }}.</div>
        </div>
        <div class="actions">
            @if (auth()->user()?->is_system_admin)
                <a class="btn secondary" href="{{ route('core.organizations.manage', $organization) }}">← Organization</a>
            @else
                <a class="btn secondary" href="{{ route('core.organization.settings') }}">← Organization settings</a>
            @endif
        </div>
    </div>

    @if ($successMessage !== '')
        <div class="notice" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif

    <section class="card">
        <h2>{{ $editingUnitId ? 'Edit unit' : 'Add unit' }}</h2>
        <p class="muted">Unit codes are generated automatically from the unit name. Duplicate names receive a numeric suffix within this organization. Parent units are optional.</p>
        <form wire:submit="save">
            <div class="grid">
                <div class="field">
                    <label for="unit-name">Unit name</label>
                    <input id="unit-name" wire:model="name" maxlength="255" required>
                    @error('name')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="unit-parent">Parent unit (optional)</label>
                    <select id="unit-parent" wire:model="parent_id">
                        <option value="">No parent (top-level unit)</option>
                        @foreach ($parentOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="unit-active">Status</label>
                    <select id="unit-active" wire:model="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    @error('is_active')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="actions">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $editingUnitId ? 'Save changes' : 'Add unit' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                @if ($editingUnitId)
                    <button class="btn secondary" type="button" wire:click="cancelEdit">Cancel</button>
                @endif
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Organization units</h2>
        @if ($units->isEmpty())
            <p class="muted">No units have been created for this organization yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Code</th><th>Parent</th><th>Child units</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @foreach ($units as $unit)
                        <tr>
                            <td>{{ $unit->name }}</td>
                            <td>{{ $unit->code }}</td>
                            <td>{{ $unit->parent?->name ?? '—' }}</td>
                            <td>{{ $unit->children_count }}</td>
                            <td><span class="pill {{ $unit->is_active ? 'ok' : 'off' }}">{{ $unit->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td><div class="actions"><button type="button" class="btn secondary" wire:click="edit({{ $unit->id }})">Edit</button><button type="button" class="btn secondary" wire:click="toggleActive({{ $unit->id }})" wire:confirm="{{ $unit->is_active ? 'Deactivate' : 'Activate' }} this unit?">{{ $unit->is_active ? 'Deactivate' : 'Activate' }}</button></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
</div>
