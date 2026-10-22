<div>
    @if ($successMessage !== '')
        <div class="notice" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif

    <div class="page-head">
        <div>
            <h1>Member payment categories</h1>
            <div class="muted">Configure organization-level member fees, recurring charges, rates, and how collected amounts are allocated.</div>
        </div>
        <div class="actions">
            @if (auth()->user()?->is_system_admin)
                <a class="btn secondary" href="{{ route('core.organizations.manage', $organization) }}">← Back to organization management</a>
            @else
                <a class="btn secondary" href="{{ route('core.organization.settings') }}">← Back to organization settings</a>
            @endif
        </div>
    </div>

    <section class="card">
        <h2>{{ $editingId ? 'Edit payment category' : 'Create payment category' }}</h2>
        <p class="muted">A category can apply to all membership types or only to one selected type. The initial rate is required when creating a category.</p>

        <form wire:submit="save">
            <div class="grid">
                <div class="field">
                    <label for="payment-title">Category name</label>
                    <input id="payment-title" type="text" wire:model="title" required maxlength="255" placeholder="e.g. Annual Membership Fee">
                    @error('title')<span class="error">{{ $message }}</span>@enderror
                    @error('slug')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="payment-membership-type">Membership type</label>
                    <select id="payment-membership-type" wire:model="membershipTypeId">
                        <option value="">All membership types</option>
                        @foreach ($membershipTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('membershipTypeId')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="payment-description">Description</label>
                <textarea id="payment-description" wire:model="description" rows="2" maxlength="5000"></textarea>
                @error('description')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="family-payment-policy">Family payment policy</label>
                <select id="family-payment-policy" wire:model="familyPaymentPolicy">
                    <option value="individual">Individual — each member pays separately</option>
                    <option value="mutual_spouse">Mutual spouse coverage — the first confirmed payment covers the other spouse</option>
                </select>
                <div class="muted">Spouse coverage applies only to verified active spouse relationships in this organization, for this category and billing period.</div>
                @error('familyPaymentPolicy')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="grid">
                <div class="field">
                    <label><input type="checkbox" wire:model="isMandatory"> Mandatory payment</label>
                    <div class="muted">When enabled, this payment category is required.</div>
                    @error('isMandatory')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label><input type="checkbox" wire:model="isActive"> Active</label>
                    <div class="muted">Inactive categories are not used for new obligations.</div>
                    @error('isActive')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label><input type="checkbox" wire:model.live="isRecurring"> Recurring payment</label>
                    @error('isRecurring')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            @if ($isRecurring)
                <div class="grid">
                    <div class="field">
                        <label for="recurring-period">Recurrence period</label>
                        <select id="recurring-period" wire:model="recurringPeriod" required>
                            <option value="">Select period</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="half_yearly">Half-yearly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        @error('recurringPeriod')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="recurring-by">Recurrence anchored to</label>
                        <select id="recurring-by" wire:model.live="recurringBy" required>
                            <option value="">Select anchor</option>
                            <option value="join_date">Member's join date</option>
                            <option value="specific_date">A specific calendar date</option>
                        </select>
                        @error('recurringBy')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    @if ($recurringBy === 'specific_date')
                        <div class="field">
                            <label for="recurring-date">Recurring date</label>
                            <input id="recurring-date" type="text" wire:model="recurringDate" placeholder="{{ $recurringPeriod === 'monthly' ? 'Day number, e.g. 15' : 'MM-DD, e.g. 12-31' }}" required>
                            <div class="muted">For monthly charges enter a day from 1 to 31. For other periods use MM-DD.</div>
                            @error('recurringDate')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    @endif
                </div>
            @endif

            <div class="grid">
                <div class="field">
                    <label for="reminder-before">Reminder days before due (optional)</label>
                    <input id="reminder-before" type="number" min="0" max="3650" wire:model="reminderDaysBeforeDue">
                    @error('reminderDaysBeforeDue')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="reminder-after">Reminder days after due (optional)</label>
                    <input id="reminder-after" type="number" min="0" max="3650" wire:model="reminderDaysAfterDue">
                    @error('reminderDaysAfterDue')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            @if (!$editingId)
                <hr>
                <h3>Initial rate</h3>
                <div class="grid">
                    <div class="field">
                        <label for="payment-amount">Amount</label>
                        <input id="payment-amount" type="number" min="0" step="0.01" wire:model="amount" required>
                        @error('amount')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="payment-currency">Currency (ISO 4217)</label>
                        <input id="payment-currency" type="text" minlength="3" maxlength="3" wire:model="currency" required>
                        @error('currency')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="payment-effective-from">Effective from</label>
                        <input id="payment-effective-from" type="date" wire:model="effectiveFrom" required>
                        @error('effectiveFrom')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="payment-effective-to">Effective to (optional)</label>
                        <input id="payment-effective-to" type="date" wire:model="effectiveTo">
                        @error('effectiveTo')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <h4>Allocation of collected amount</h4>
                <p class="muted">The organization and unit shares must total 100%. Set a share to zero if that scope receives no allocation.</p>
                <div class="grid">
                    <div class="field">
                        <label for="organization-share">Organization share (%)</label>
                        <input id="organization-share" type="number" min="0" max="100" step="0.0001" wire:model="organizationShare" required>
                        @error('organizationShare')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="unit-share">Member's unit share (%)</label>
                        <input id="unit-share" type="number" min="0" max="100" step="0.0001" wire:model="unitShare" required>
                        @error('unitShare')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
            @else
                <p class="muted">Editing category details does not change existing rates. Use “Add rate” in the list below to introduce a new rate with its own effective period.</p>
            @endif

            <div class="actions">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create payment category' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                @if ($editingId)
                    <button class="btn secondary" type="button" wire:click="resetForm">Cancel edit</button>
                @endif
            </div>
        </form>
    </section>

    @if ($rateItemId !== null)
        <section class="card">
            <h2>Add rate</h2>
            <p class="muted">This adds a new effective-dated rate. Existing payment history is retained.</p>
            <form wire:submit="saveRate">
                <div class="grid">
                    <div class="field">
                        <label for="rate-amount">Amount</label>
                        <input id="rate-amount" type="number" min="0" step="0.01" wire:model="rateAmount" required>
                        @error('rateAmount')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="rate-currency">Currency</label>
                        <input id="rate-currency" type="text" minlength="3" maxlength="3" wire:model="rateCurrency" required>
                        @error('rateCurrency')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="rate-effective-from">Effective from</label>
                        <input id="rate-effective-from" type="date" wire:model="rateEffectiveFrom" required>
                        @error('rateEffectiveFrom')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="rate-effective-to">Effective to (optional)</label>
                        <input id="rate-effective-to" type="date" wire:model="rateEffectiveTo">
                        @error('rateEffectiveTo')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <h4>Allocation of collected amount</h4>
                <div class="grid">
                    <div class="field">
                        <label for="rate-organization-share">Organization share (%)</label>
                        <input id="rate-organization-share" type="number" min="0" max="100" step="0.0001" wire:model="rateOrganizationShare" required>
                        @error('rateOrganizationShare')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="rate-unit-share">Member's unit share (%)</label>
                        <input id="rate-unit-share" type="number" min="0" max="100" step="0.0001" wire:model="rateUnitShare" required>
                        @error('rateUnitShare')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveRate">Save rate</button>
                    <button class="btn secondary" type="button" wire:click="cancelAddRate">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>Configured payment categories</h2>
        @if ($items->isEmpty())
            <p class="muted">No payment categories have been configured for this organization yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Category</th><th>Applies to</th><th>Current/latest rate</th><th>Frequency</th><th>Required</th><th>Family policy</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php($latestRate = $item->rates->sortByDesc('effective_from')->first())
                            <tr>
                                <td>
                                    <strong>{{ $item->title }}</strong>
                                    <div class="muted">{{ $item->slug }}</div>
                                    @if ($item->description)<div class="muted">{{ $item->description }}</div>@endif
                                </td>
                                <td>{{ $item->membershipType?->name ?? 'All membership types' }}</td>
                                <td>
                                    @if ($latestRate)
                                        {{ number_format((float) $latestRate->amount, 2) }} {{ $latestRate->currency }}
                                        <div class="muted">From {{ $latestRate->effective_from?->format('Y-m-d') }}</div>
                                        @foreach ($latestRate->allocations as $allocation)
                                            <div class="muted">{{ $allocation->scope_type === 'organization' ? 'Organization' : 'Unit' }}: {{ number_format((float) $allocation->percentage, 2) }}%</div>
                                        @endforeach
                                    @else
                                        <span class="muted">No rate</span>
                                    @endif
                                    <div class="muted">{{ $item->rates->count() }} rate(s)</div>
                                </td>
                                <td>
                                    @if ($item->is_recurring)
                                        {{ str_replace('_', ' ', ucfirst($item->recurring_period)) }}
                                        <div class="muted">{{ $item->recurring_by === 'join_date' ? 'From join date' : 'Specific date' }}</div>
                                    @else
                                        One-time
                                    @endif
                                </td>
                                <td>{{ $item->is_mandatory ? 'Yes' : 'No' }}</td>
                                <td>{{ ($item->family_payment_policy ?? 'individual') === 'mutual_spouse' ? 'Mutual spouse coverage' : 'Individual' }}</td>
                                <td><span class="pill {{ $item->is_active ? 'ok' : 'off' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn secondary" type="button" wire:click="edit({{ $item->id }})">Edit</button>
                                        <button class="btn secondary" type="button" wire:click="showAddRate({{ $item->id }})">Add rate</button>
                                        <button class="btn {{ $item->is_active ? 'danger' : '' }}" type="button" wire:click="toggleActive({{ $item->id }})">{{ $item->is_active ? 'Deactivate' : 'Activate' }}</button>
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
