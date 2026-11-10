<div class="finance-tax-configuration-management">
    <div class="page-head">
        <div>
            <h1>Tax Configuration</h1>
            <p class="muted">Configure organization-wide tax rates, tax groups and their Chart of Accounts mappings.</p>
        </div>
    </div>

    @if ($successMessage !== '')
        <div class="notice" role="status">{{ $successMessage }}</div>
    @endif
    @error('tax') <div class="error" role="alert">{{ $message }}</div> @enderror
    @error('group') <div class="error" role="alert">{{ $message }}</div> @enderror

    @if ($canManage)
        <section class="card">
            <h2>{{ $editingRateId ? 'Edit Tax Rate' : 'Create Tax Rate' }}</h2>
            <p class="muted">Rates are percentages. Sales tax is mapped to a liability account; recoverable purchase tax is mapped to an asset account.</p>
            <form wire:submit="saveRate">
                <div class="grid">
                    <div class="field">
                        <label for="tax-code">Code</label>
                        <input id="tax-code" type="text" wire:model="code" maxlength="50" required>
                        @error('code') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="tax-name">Name</label>
                        <input id="tax-name" type="text" wire:model="name" maxlength="150" required>
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="tax-rate">Rate (%)</label>
                        <input id="tax-rate" type="number" min="0" max="100" step="0.0001" wire:model="rate" required>
                        @error('rate') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="tax-applies-to">Applies to</label>
                        <select id="tax-applies-to" wire:model.live="appliesTo" required>
                            <option value="both">Sales and purchases</option>
                            <option value="sales">Sales only</option>
                            <option value="purchases">Purchases only</option>
                        </select>
                        @error('appliesTo') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    @if (in_array($appliesTo, ['sales', 'both'], true))
                        <div class="field">
                            <label for="tax-sales-account">Sales tax liability account</label>
                            <select id="tax-sales-account" wire:model="salesAccountId" required>
                                <option value="">Select liability account</option>
                                @foreach ($salesAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('salesAccountId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    @if (in_array($appliesTo, ['purchases', 'both'], true))
                        <div class="field">
                            <label for="tax-purchase-account">Purchase tax asset account</label>
                            <select id="tax-purchase-account" wire:model="purchaseAccountId" required>
                                <option value="">Select asset account</option>
                                @foreach ($purchaseAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('purchaseAccountId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveRate">{{ $editingRateId ? 'Save Changes' : 'Create Tax Rate' }}</button>
                    @if ($editingRateId)
                        <button class="btn secondary" type="button" wire:click="resetRateForm">Cancel</button>
                    @endif
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>Tax Rates</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Rate</th><th>Applicability</th><th>Sales tax account</th><th>Purchase tax account</th><th>Status</th>@if ($canManage)<th>Actions</th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($rates as $taxRate)
                        <tr wire:key="finance-tax-rate-{{ $taxRate->id }}">
                            <td>{{ $taxRate->code }}</td>
                            <td>{{ $taxRate->name }}</td>
                            <td>{{ number_format((float) $taxRate->rate, 4) }}%</td>
                            <td>{{ ucfirst($taxRate->applies_to) }}</td>
                            <td>{{ $taxRate->salesAccount ? $taxRate->salesAccount->code.' — '.$taxRate->salesAccount->name : '—' }}</td>
                            <td>{{ $taxRate->purchaseAccount ? $taxRate->purchaseAccount->code.' — '.$taxRate->purchaseAccount->name : '—' }}</td>
                            <td><span class="pill {{ $taxRate->is_active ? 'ok' : 'off' }}">{{ $taxRate->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($canManage)
                                <td>
                                    @if ($taxRate->is_active)
                                        <div class="actions">
                                            <button class="btn secondary" type="button" wire:click="editRate({{ $taxRate->id }})">Edit</button>
                                            <button class="btn danger" type="button" wire:click="deactivateRate({{ $taxRate->id }})" wire:confirm="Deactivate this tax rate?">Deactivate</button>
                                        </div>
                                    @else
                                        <span class="muted">No actions</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="empty">No tax rates configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage)
        <section class="card">
            <h2>Create Tax Group</h2>
            <p class="muted">Combine component rates into a reusable group, such as a local split-tax combination. A group can only contain rates valid for its applicability.</p>
            <form wire:submit="createGroup">
                <div class="grid">
                    <div class="field">
                        <label for="tax-group-code">Group code</label>
                        <input id="tax-group-code" type="text" wire:model="groupCode" maxlength="50" required>
                        @error('groupCode') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="tax-group-name">Group name</label>
                        <input id="tax-group-name" type="text" wire:model="groupName" maxlength="150" required>
                        @error('groupName') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="tax-group-applies">Applies to</label>
                        <select id="tax-group-applies" wire:model="groupAppliesTo" required>
                            <option value="both">Sales and purchases</option>
                            <option value="sales">Sales only</option>
                            <option value="purchases">Purchases only</option>
                        </select>
                        @error('groupAppliesTo') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="field">
                    <label>Component tax rates</label>
                    @forelse ($availableRates as $availableRate)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="groupRateIds" value="{{ $availableRate->id }}">
                            <span>{{ $availableRate->code }} — {{ $availableRate->name }} ({{ number_format((float) $availableRate->rate, 4) }}%)</span>
                        </label>
                    @empty
                        <p class="muted">Create an active tax rate before creating a group.</p>
                    @endforelse
                    @error('groupRateIds') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="createGroup">Create Tax Group</button>
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>Tax Groups</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Applicability</th><th>Component rates</th><th>Status</th>@if ($canManage)<th>Actions</th>@endif</tr></thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr wire:key="finance-tax-group-{{ $group->id }}">
                            <td>{{ $group->code }}</td>
                            <td>{{ $group->name }}</td>
                            <td>{{ ucfirst($group->applies_to) }}</td>
                            <td>{{ $group->rates->map(fn ($item) => $item->code.' ('.number_format((float) $item->rate, 4).'%)')->implode(', ') }}</td>
                            <td><span class="pill {{ $group->is_active ? 'ok' : 'off' }}">{{ $group->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($canManage)
                                <td>
                                    @if ($group->is_active)
                                        <button class="btn danger" type="button" wire:click="deactivateGroup({{ $group->id }})" wire:confirm="Deactivate this tax group?">Deactivate</button>
                                    @else
                                        <span class="muted">No actions</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="empty">No tax groups configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
