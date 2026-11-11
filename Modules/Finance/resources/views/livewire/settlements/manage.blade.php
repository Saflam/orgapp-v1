<div class="finance-settlements">
    <header class="settlements-header">
        <div>
            <p class="eyebrow">FINANCE · INTER-SCOPE LEDGER</p>
            <h1>Central / Unit Settlements</h1>
            <p>Track amounts collected on behalf of other scopes, record transfers and confirm receipt.</p>
        </div>
        <div class="scope-filter">
            <label for="settlementScope">View scope</label>
            <select id="settlementScope" wire:model.live="scopeUnitId">
                <option value="">All authorized scopes</option>
                @foreach ($units as $unit)
                    @if ($visibleScopes->contains(fn ($scope) => $scope['type'] === 'unit' && $scope['id'] === $unit->id))
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </header>

    @if (session()->has('settlement-status'))
        <div class="notice" role="status">{{ session('settlement-status') }}</div>
    @endif

    <section class="panel">
        <div class="panel-heading">
            <div><h2>Outstanding obligations</h2><p>Only obligations involving scopes you are authorized to view are shown.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Debtor</th><th>Creditor</th><th>Source</th><th>Original</th><th>Outstanding</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($obligations as $obligation)
                    <tr>
                        <td>{{ $obligation->transaction_date->format('d M Y') }}</td>
                        <td>{{ $obligation->debtor_scope_type === 'organization' ? 'Central' : ($units->firstWhere('id', $obligation->debtor_scope_id)?->name ?? 'Unit') }}</td>
                        <td>{{ $obligation->creditor_scope_type === 'organization' ? 'Central' : ($units->firstWhere('id', $obligation->creditor_scope_id)?->name ?? 'Unit') }}</td>
                        <td>{{ $obligation->payment?->receipt_number ?? 'Manual' }}</td>
                        <td>{{ $obligation->currency }} {{ number_format((float) $obligation->original_amount, 2) }}</td>
                        <td><strong>{{ $obligation->currency }} {{ number_format((float) $obligation->outstanding_amount, 2) }}</strong></td>
                        <td>
                            @if (in_array($obligation->id, $manageableObligationIds, true))
                                <button type="button" class="action" wire:click="openSettlementForm({{ $obligation->id }})">Record transfer</button>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @if ($obligationId === $obligation->id)
                        <tr><td colspan="7">
                            <form wire:submit="recordSettlement" class="settlement-form">
                                <div><label>Amount</label><input type="number" min="0.01" step="0.01" wire:model="amount"></div>
                                <div><label>Transfer date</label><input type="date" wire:model="transactionDate"></div>
                                <div><label>Paid from</label><select wire:model="payerCashAccountId"><option value="">Select account</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
                                <div><label>Received into</label><select wire:model="payeeCashAccountId"><option value="">Select account</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
                                <div><label>Reference</label><input type="text" wire:model="reference" maxlength="150"></div>
                                <div class="form-actions"><button class="action primary" type="submit">Post settlement</button><button class="action" type="button" wire:click="$set('obligationId', null)">Cancel</button></div>
                                @error('amount')<small class="error">{{ $message }}</small>@enderror
                                @error('transactionDate')<small class="error">{{ $message }}</small>@enderror
                                @error('payerCashAccountId')<small class="error">{{ $message }}</small>@enderror
                                @error('payeeCashAccountId')<small class="error">{{ $message }}</small>@enderror
                            </form>
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="empty">No outstanding settlement obligations were found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading"><div><h2>Incoming transfers awaiting confirmation</h2><p>Confirm only after the funds have actually been received.</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Transfer</th><th>Debtor</th><th>Amount</th><th>Date</th><th>Finance journal</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($pendingSettlements as $settlement)
                    <tr>
                        <td>#{{ $settlement->id }}</td>
                        <td>{{ $settlement->obligation->debtor_scope_type === 'organization' ? 'Central' : ($units->firstWhere('id', $settlement->obligation->debtor_scope_id)?->name ?? 'Unit') }}</td>
                        <td>{{ $settlement->obligation->currency }} {{ number_format((float) $settlement->amount, 2) }}</td>
                        <td>{{ $settlement->transaction_date->format('d M Y') }}</td>
                        <td>{{ $settlement->journal?->journal_number ?? '—' }}</td>
                        <td class="actions">
                            @if (in_array($settlement->id, $confirmableSettlementIds, true))
                                <button class="action primary" wire:click="confirmSettlement({{ $settlement->id }})">Confirm receipt</button>
                                <button class="action danger" wire:click="$set('rejectSettlementId', {{ $settlement->id }})">Reject</button>
                            @else
                                <span class="muted">Awaiting authorized confirmation</span>
                            @endif
                        </td>
                    </tr>
                    @if ($rejectSettlementId === $settlement->id && in_array($settlement->id, $confirmableSettlementIds, true))
                        <tr><td colspan="6">
                            <form wire:submit="rejectSettlement({{ $settlement->id }})" class="settlement-form">
                                <div class="wide"><label>Reason for rejection</label><textarea wire:model="rejectionReason" rows="2"></textarea></div>
                                <div class="form-actions"><button class="action danger" type="submit">Reject and reverse journal</button><button class="action" type="button" wire:click="$set('rejectSettlementId', null)">Cancel</button></div>
                                @error('rejectionReason')<small class="error">{{ $message }}</small>@enderror
                            </form>
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="empty">No incoming transfers are awaiting confirmation.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="footnote">Recording a transfer posts a balanced Finance journal between the selected cash/bank accounts. The obligation is reduced only after the creditor confirms receipt. Rejected transfers are reversed in an open financial period.</p>
</div>

<style>
.finance-settlements{display:grid;gap:18px;color:var(--ink,#172033);min-width:0}.settlements-header{display:flex;justify-content:space-between;align-items:end;gap:16px}.settlements-header h1{margin:0}.settlements-header p:not(.eyebrow){color:var(--muted,#68758b);font-size:13px;margin:6px 0 0}.eyebrow{font-size:10px;font-weight:800;letter-spacing:.1em;color:var(--brand,#4776e6);margin:0 0 6px}.scope-filter{display:grid;gap:6px;min-width:210px}.scope-filter label,.settlement-form label{font-size:12px;font-weight:700}.scope-filter select,.settlement-form input,.settlement-form select,.settlement-form textarea{width:100%;min-height:38px;border:1px solid var(--line,#e3e8f0);border-radius:8px;padding:8px;background:var(--panel,#fff);color:inherit;font:inherit;font-size:13px}.panel{border:1px solid var(--line,#e3e8f0);border-radius:13px;background:var(--panel,#fff);overflow:hidden}.panel-heading{padding:18px 20px;border-bottom:1px solid var(--line,#e3e8f0)}.panel-heading h2{margin:0;font-size:16px}.panel-heading p{margin:5px 0 0;color:var(--muted,#68758b);font-size:12px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:800px}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--line,#e3e8f0);font-size:12px;white-space:nowrap}th{background:var(--canvas,#f6f8fc);font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#68758b)}.empty{text-align:center;color:var(--muted,#68758b);padding:28px}.action{border:1px solid var(--line,#d7deea);border-radius:7px;padding:7px 10px;background:transparent;color:inherit;font-size:11px;font-weight:700;cursor:pointer}.primary{background:var(--brand,#4776e6);border-color:var(--brand,#4776e6);color:white}.danger{color:#b42318;border-color:#e8b4b0}.actions{display:flex;gap:6px}.settlement-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:12px;background:var(--canvas,#f6f8fc);white-space:normal}.settlement-form>div{display:grid;gap:5px}.settlement-form .wide{grid-column:1/-1}.form-actions{display:flex!important;align-items:end;gap:8px}.error{color:#b42318}.muted,.footnote{color:var(--muted,#68758b)}.footnote{font-size:11px;line-height:1.6}.notice{padding:12px 15px;border:1px solid var(--line,#e3e8f0);border-radius:9px;background:var(--canvas,#f6f8fc);font-size:13px}@media(max-width:760px){.settlements-header{align-items:stretch;flex-direction:column}.settlement-form{grid-template-columns:1fr}}
</style>
