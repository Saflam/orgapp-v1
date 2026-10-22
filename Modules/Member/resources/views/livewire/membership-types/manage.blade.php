<div>
    @if ($successMessage !== '')
        <div class="notice" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif

    <div class="page-head">
        <div>
            <h1>Membership types</h1>
            <div class="muted">Define the membership categories available in {{ $organization->name }}. Reviewers select the appropriate type during application review.</div>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ auth()->user()?->is_system_admin ? route('core.organizations.manage', $organization) : route('core.organization.settings') }}">← Back to organization settings</a>
        </div>
    </div>

    <section class="card">
        <h2>{{ $editingId ? 'Edit membership type' : 'Create membership type' }}</h2>
        <form wire:submit="save">
            <div class="grid">
                <div class="field">
                    <label for="membership-type-name">Name</label>
                    <input id="membership-type-name" type="text" wire:model="name" maxlength="255" required placeholder="e.g. Premium Member">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="membership-type-code">Code</label>
                    <input id="membership-type-code" type="text" wire:model="code" maxlength="100" required placeholder="e.g. PREMIUM">
                    <div class="muted">Use letters, numbers, hyphens or underscores. Codes are unique within this organization.</div>
                    @error('code') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="field">
                <label for="membership-type-description">Description (optional)</label>
                <textarea id="membership-type-description" wire:model="description" maxlength="2000" rows="3"></textarea>
                @error('description') <span class="error">{{ $message }}</span> @enderror
            </div>
            <label class="field"><span><input type="checkbox" wire:model="isActive"> Active and available for reviewer assignment</span></label>
            @error('isActive') <span class="error">{{ $message }}</span> @enderror
            <div class="actions">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create membership type' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                @if ($editingId)
                    <button class="btn secondary" type="button" wire:click="resetForm">Cancel edit</button>
                @endif
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Configured membership types</h2>
        @if ($types->isEmpty())
            <p class="muted">No membership types have been configured for this organization yet.</p>
        @else
            <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>Name</th><th>Code</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @foreach ($types as $type)
                        <tr wire:key="membership-type-{{ $type->id }}">
                            <td>{{ $type->name }}</td>
                            <td><code>{{ $type->code }}</code></td>
                            <td>{{ $type->description ?: '—' }}</td>
                            <td><span class="pill {{ $type->is_active ? 'ok' : 'off' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="actions">
                                    <button class="btn secondary" type="button" wire:click="edit({{ $type->id }})">Edit</button>
                                    <button class="btn secondary" type="button" wire:click="toggleActive({{ $type->id }})" wire:confirm="{{ $type->is_active ? 'Deactivate' : 'Activate' }} this membership type?">{{ $type->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
