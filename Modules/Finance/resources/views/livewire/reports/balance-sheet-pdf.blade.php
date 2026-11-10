<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; }
        .heading { border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 15px; }
        h1 { font-size: 19px; margin: 0 0 5px; }
        .subtitle { color: #64748b; font-size: 10px; margin: 0; }
        .meta { width: 100%; margin: 12px 0 16px; }
        .meta td { padding: 4px 8px 4px 0; color: #475569; }
        .meta strong { color: #172033; }
        h2 { margin: 13px 0 7px; font-size: 12px; color: #172033; }
        table.report { width: 100%; border-collapse: collapse; margin: 0 0 8px; }
        .report th { background: #eef2f7; color: #475569; font-size: 8px; text-transform: uppercase; text-align: left; }
        .report th, .report td { border: 1px solid #dbe2ea; padding: 6px 8px; }
        .numeric { text-align: right !important; white-space: nowrap; }
        .report tbody tr { page-break-inside: avoid; }
        .report tfoot th, .report tfoot td { background: #f8fafc; font-weight: bold; }
        .empty { color: #64748b; text-align: center; }
        .summary { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .summary th, .summary td { padding: 9px 10px; border-top: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; }
        .summary .grand th, .summary .grand td { border-top: 2px solid #64748b; font-size: 11px; }
        .difference th, .difference td { border-bottom: 2px solid #64748b; }
        .balanced { color: #166534; }
        .unbalanced { color: #b91c1c; }
        .status { margin-top: 8px; font-size: 9px; font-weight: bold; }
        .footer { margin-top: 15px; color: #64748b; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    <div class="heading">
        <h1>{{ $organization->name }} — Balance Sheet</h1>
        <p class="subtitle">Cumulative financial position from posted transactions through the selected date</p>
    </div>

    <table class="meta">
        <tr>
            <td><strong>As of date:</strong> {{ \Illuminate\Support\Carbon::parse($report['as_of_date'])->format('d M Y') }}</td>
            <td><strong>Unit:</strong> {{ $unitName }}</td>
            <td><strong>Generated:</strong> {{ $generatedAt->format('d M Y H:i') }}</td>
        </tr>
    </table>

    <h2>Assets</h2>
    <table class="report">
        <thead><tr><th style="width: 20%">Account code</th><th>Account name</th><th class="numeric" style="width: 25%">Amount</th></tr></thead>
        <tbody>
            @forelse ($report['asset_accounts'] as $account)
                <tr><td>{{ $account['code'] }}</td><td>{{ $account['name'] }}</td><td class="numeric">{{ $account['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3" class="empty">No asset balances for this date.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="2">Total assets</th><td class="numeric">{{ $report['total_assets'] }}</td></tr></tfoot>
    </table>

    <h2>Liabilities</h2>
    <table class="report">
        <thead><tr><th style="width: 20%">Account code</th><th>Account name</th><th class="numeric" style="width: 25%">Amount</th></tr></thead>
        <tbody>
            @forelse ($report['liability_accounts'] as $account)
                <tr><td>{{ $account['code'] }}</td><td>{{ $account['name'] }}</td><td class="numeric">{{ $account['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3" class="empty">No liability balances for this date.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="2">Total liabilities</th><td class="numeric">{{ $report['total_liabilities'] }}</td></tr></tfoot>
    </table>

    <h2>Equity</h2>
    <table class="report">
        <thead><tr><th style="width: 20%">Account code</th><th>Account name</th><th class="numeric" style="width: 25%">Amount</th></tr></thead>
        <tbody>
            @forelse ($report['equity_accounts'] as $account)
                <tr><td>{{ $account['code'] }}</td><td>{{ $account['name'] }}</td><td class="numeric">{{ $account['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3" class="empty">No equity balances for this date.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="2">Total equity</th><td class="numeric">{{ $report['total_equity'] }}</td></tr></tfoot>
    </table>

    <table class="summary">
        <tr><th>Current earnings</th><td class="numeric">{{ $report['current_earnings']['amount'] }}</td></tr>
        <tr class="grand"><th>Total liabilities and equity</th><td class="numeric">{{ $report['total_liabilities_and_equity'] }}</td></tr>
        <tr class="difference">
            <th>Difference</th>
            <td class="numeric {{ $report['difference'] === '0.0000' ? 'balanced' : 'unbalanced' }}">{{ $report['difference'] }}</td>
        </tr>
    </table>

    <p class="status {{ $report['difference'] === '0.0000' ? 'balanced' : 'unbalanced' }}">
        {{ $report['difference'] === '0.0000' ? 'Balanced — assets equal liabilities and equity.' : 'Out of balance — review the account balances.' }}
    </p>

    <div class="footer">Generated by Saradhi</div>
</body>
</html>
