<div class="finance-journal-detail">
    <div class="page-head">
        <div>
            <h1>Journal {{ $journal->journal_number }}</h1>
            <p class="muted">Read-only journal details from the organization-wide Finance ledger.</p>
        </div>
        <a class="btn secondary" href="{{ route('finance.journals') }}">Back to journal register</a>
    </div>

    <section class="card">
        <h2>Journal information</h2>
        <div class="grid">
            <div class="field">
                <label>Journal number</label>
                <div>{{ $journal->journal_number }}</div>
            </div>
            <div class="field">
                <label>Transaction date</label>
                <div>{{ $journal->transaction_date->format('d M Y') }}</div>
            </div>
            <div class="field">
                <label>Financial period</label>
                <div>{{ $journal->period?->code ?? '—' }}</div>
            </div>
            <div class="field">
                <label>Unit attribution</label>
                <div>{{ $journal->unit?->name ?? 'Organization-wide' }}</div>
            </div>
            <div class="field">
                <label>Status</label>
                <div><span class="pill ok">{{ ucfirst($journal->status->value) }}</span></div>
            </div>
            <div class="field">
                <label>Source</label>
                <div>{{ $journal->source_type ? class_basename($journal->source_type) . ' #' . ($journal->source_id ?? '—') : 'Manual journal' }}</div>
            </div>
            <div class="field">
                <label>Description</label>
                <div>{{ $journal->description ?: '—' }}</div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Journal lines</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Account code</th>
                        <th>Account name</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($journal->lines as $line)
                        <tr wire:key="journal-detail-line-{{ $line->id }}">
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $line->account?->code ?? '—' }}</td>
                            <td>{{ $line->account?->name ?? '—' }}</td>
                            <td>{{ $line->description ?: '—' }}</td>
                            <td>{{ number_format((float) $line->debit, 4) }}</td>
                            <td>{{ number_format((float) $line->credit, 4) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th colspan="4">Totals</th>
                        <th>{{ number_format($debitTotal, 4) }}</th>
                        <th>{{ number_format($creditTotal, 4) }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($journal->isReversal() || $journal->reversal)
        <section class="card">
            <h2>Reversal relationship</h2>
            @if ($journal->isReversal())
                <p>This journal reverses <a href="{{ route('finance.journals.show', $journal->originalJournal->id) }}">{{ $journal->originalJournal->journal_number }}</a>.</p>
                <p><strong>Reason:</strong> {{ $journal->reversal_reason ?: '—' }}</p>
            @else
                <p>This journal was reversed by <a href="{{ route('finance.journals.show', $journal->reversal->id) }}">{{ $journal->reversal->journal_number }}</a>.</p>
                <p><strong>Reversal reason:</strong> {{ $journal->reversal->reversal_reason ?: '—' }}</p>
            @endif
        </section>
    @endif
</div>
