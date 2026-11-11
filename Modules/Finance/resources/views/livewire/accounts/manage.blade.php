<div class="finance-chart-of-accounts-management">
    <div class="page-head">
        <div>
            <h1>Chart of Accounts</h1>
            <p class="muted">One organization-wide chart of accounts. Units are tracked on transactions, not through separate ledgers.</p>
        </div>
        @if ($canManage)
            <button class="btn" type="button" wire:click="resetForm">Add Account</button>
        @endif
    </div>

    @if ($successMessage !== '')
        <div class="notice" role="status">{{ $successMessage }}</div>
    @endif
    @error('account') <div class="error" role="alert">{{ $message }}</div> @enderror

    @if ($canManage)
        <section class="card">
            <h2>{{ $editingId ? 'Edit Account' : 'Create Account' }}</h2>
            <p class="muted">Account type determines the normal balance. System-managed accounts are maintained by Finance or their source module.</p>
            <form wire:submit="save">
                <div class="grid">
                    <div class="field">
                        <label for="account-code">Account code</label>
                        <input id="account-code" type="text" wire:model="code" maxlength="50" required>
                        @error('code') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="account-name">Account name</label>
                        <input id="account-name" type="text" wire:model="name" maxlength="255" required>
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="account-type">Account type</label>
                        <select id="account-type" wire:model.live="accountType" @disabled($editingId) required>
                            @foreach ($accountTypes as $type)
                                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                            @endforeach
                        </select>
                        @if ($editingId)<small class="muted">Account type cannot be changed after creation.</small>@endif
                        @error('accountType') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="account-parent">Parent account (optional)</label>
                        <select id="account-parent" wire:model="parentId">
                            <option value="">No parent — top-level account</option>
                            @foreach ($parentCandidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->code }} — {{ $candidate->name }}</option>
                            @endforeach
                        </select>
                        @error('parentId') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label>
                            <input type="checkbox" wire:model.live="isCashAccount" @disabled($accountType !== 'asset')>
                            Cash / bank account
                        </label>
                        <small class="muted">Select this for cash-on-hand, petty cash, or bank accounts. Only Asset accounts can be marked as cash / bank.</small>
                        @error('isCashAccount') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="cash-flow-category">Cash flow category</label>
                        <select id="cash-flow-category" wire:model="cashFlowCategory" @disabled($isCashAccount)>
                            <option value="">Not classified</option>
                            <option value="operating">Operating</option>
                            <option value="investing">Investing</option>
                            <option value="financing">Financing</option>
                        </select>
                        <small class="muted">Classifies non-cash accounts used as the counterpart in cash-flow transactions. Leave blank if not applicable.</small>
                        @error('cashFlowCategory') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Account' }}</button>
                    @if ($editingId)<button class="btn secondary" type="button" wire:click="resetForm">Cancel</button>@endif
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>Organization Accounts</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Type</th><th>Parent</th><th>Normal balance</th><th>Cash flow</th><th>Source</th><th>Status</th>@if ($canManage)<th>Actions</th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr wire:key="finance-account-{{ $account->id }}">
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ ucfirst($account->account_type->value) }}</td>
                            <td>{{ $account->parent ? $account->parent->code.' — '.$account->parent->name : '—' }}</td>
                            <td>{{ ucfirst($account->normal_balance->value) }}</td>
                            <td>
                                @if ($account->is_cash_account)
                                    Cash / Bank
                                @elseif ($account->cash_flow_category)
                                    {{ ucfirst($account->cash_flow_category) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $account->is_system_managed ? ($account->source_type ?: 'System') : 'Manual' }}</td>
                            <td><span class="pill {{ $account->is_active ? 'ok' : 'off' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($canManage)
                                <td>
                                    @if (! $account->is_system_managed && $account->is_active)
                                        <div class="actions">
                                            <button class="btn secondary" type="button" wire:click="edit({{ $account->id }})">Edit</button>
                                            <button class="btn danger" type="button" wire:click="deactivate({{ $account->id }})" wire:confirm="Deactivate this account? Historical records will be retained.">Deactivate</button>
                                        </div>
                                    @else
                                        <span class="muted">{{ $account->is_system_managed ? 'Managed by system' : 'No actions' }}</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 9 : 8 }}" class="empty">No accounts have been configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
