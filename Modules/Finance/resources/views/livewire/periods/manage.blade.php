<div class="finance-period-management">
    <div class="page-head">
        <div>
            <h1>Financial Periods</h1>
            <p class="muted">Configure this organization's recurring financial-year dates. Generated periods remain drafts until an authorized administrator opens them.</p>
        </div>
    </div>

    @if ($successMessage !== '')
        <div class="notice" role="status">{{ $successMessage }}</div>
    @endif
    @error('period') <div class="error" role="alert">{{ $message }}</div> @enderror

    @if ($canManage)
        <style>
            .finance-period-management .financial-year-pattern-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
                align-items: end;
                gap: 1rem;
            }

            .finance-period-management .financial-year-generate-grid {
                display: grid;
                grid-template-columns: minmax(12rem, 22rem) auto;
                align-items: end;
                gap: 1rem;
            }

            .finance-period-management .inline-form-action {
                display: flex;
                align-items: end;
                padding-bottom: 0.15rem;
            }

            @media (max-width: 900px) {
                .finance-period-management .financial-year-pattern-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .finance-period-management .financial-year-generate-grid {
                    grid-template-columns: minmax(0, 1fr) auto;
                }
            }

            @media (max-width: 560px) {
                .finance-period-management .financial-year-pattern-grid,
                .finance-period-management .financial-year-generate-grid {
                    grid-template-columns: minmax(0, 1fr);
                }
            }
        </style>

        <section class="card">
            <h2>Financial-year pattern</h2>
            <p class="muted">Set the recurring start and end dates for this organization. The default is 1 April through 31 March. Changes apply to periods generated in the future; existing financial periods and posted transactions are not changed.</p>
            <form wire:submit="savePattern">
                <div class="financial-year-pattern-grid">
                    <div class="field">
                        <label for="fy-start-month">Start month</label>
                        <select id="fy-start-month" wire:model.number="startMonth" required>
                            @foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $month => $label)
                                <option value="{{ $month }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('startMonth') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="fy-start-day">Start day</label>
                        <input id="fy-start-day" type="number" min="1" max="31" wire:model.number="startDay" required>
                        @error('startDay') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="fy-end-month">End month</label>
                        <select id="fy-end-month" wire:model.number="endMonth" required>
                            @foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $month => $label)
                                <option value="{{ $month }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('endMonth') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="fy-end-day">End day</label>
                        <input id="fy-end-day" type="number" min="1" max="31" wire:model.number="endDay" required>
                        @error('endDay') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="inline-form-action">
                        <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="savePattern">Save Pattern</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Generate a financial year</h2>
            <p class="muted">Saflivo calculates the dates and code from the pattern above. The generated period is a draft and does not accept transactions until you open it.</p>
            <form wire:submit="generatePeriod">
                <div class="financial-year-generate-grid">
                    <div class="field">
                        <label for="fy-start-year">Financial year start year</label>
                        <input id="fy-start-year" type="number" min="2000" max="2200" wire:model.number="generateStartYear" required>
                        @error('generateStartYear') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="inline-form-action">
                        <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="generatePeriod">
                            <span wire:loading.remove wire:target="generatePeriod">Generate Draft Period</span>
                            <span wire:loading wire:target="generatePeriod">Generating…</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>

        @if ($editingId)
            <section class="card">
                <h2>Edit Financial Period</h2>
                <p class="muted">A period with posted journals cannot be edited. Closed periods are read-only.</p>
                <form wire:submit="save">
                    <div class="grid">
                        <div class="field">
                            <label for="period-name">Period name</label>
                            <input id="period-name" type="text" wire:model="name" required maxlength="255">
                            @error('name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="period-code">Period code</label>
                            <input id="period-code" type="text" wire:model="code" required maxlength="50">
                            @error('code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="period-starts-on">Start date</label>
                            <input id="period-starts-on" type="date" wire:model="startsOn" required>
                            @error('startsOn') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="period-ends-on">End date</label>
                            <input id="period-ends-on" type="date" wire:model="endsOn" required>
                            @error('endsOn') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="actions">
                        <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">Save Changes</button>
                        <button class="btn secondary" type="button" wire:click="resetForm">Cancel</button>
                    </div>
                </form>
            </section>
        @endif
    @endif

    <section class="card">
        <h2>Configured Periods</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Code</th>
                        <th>Date range</th>
                        <th>Status</th>
                        <th>Posted journals</th>
                        @if ($canManage)<th>Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr wire:key="finance-period-{{ $period->id }}">
                            <td>{{ $period->name }}</td>
                            <td>{{ $period->code }}</td>
                            <td>{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }}</td>
                            <td><span class="pill {{ $period->isOpen() ? 'ok' : 'off' }}">{{ ucfirst($period->status->value) }}</span></td>
                            <td>{{ $period->journals_count }}</td>
                            @if ($canManage)
                                <td>
                                    <div class="actions">
                                        @if ($period->status->value === 'draft')
                                            <button class="btn" type="button" wire:click="openPeriod({{ $period->id }})" wire:confirm="Open this financial period? Transactions dated within it will be allowed.">Open Period</button>
                                        @elseif ($period->isOpen())
                                            @if ($period->journals_count === 0)
                                                <button class="btn secondary" type="button" wire:click="edit({{ $period->id }})">Edit</button>
                                            @endif
                                            <button class="btn danger" type="button" wire:click="close({{ $period->id }})" wire:confirm="Close this financial period? New transactions dated within it will be rejected.">Close Period</button>
                                        @else
                                            <span class="muted">No actions</span>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="empty">No financial periods have been configured for this organization.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
