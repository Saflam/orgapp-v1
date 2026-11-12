<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        h1 { font-size: 19px; margin: 0 0 5px; }
        .meta { color: #6b7280; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ $organization->name }} · Generated {{ now()->format('d M Y, h:i A') }} · {{ $trustees->count() }} record(s)</div>
    <table>
        <thead>
            @if($title === 'Active Trustees')
                <tr><th>Trustee ID</th><th>Member</th><th>Membership number</th><th>Unit</th><th>Appointment date</th><th>Status</th></tr>
            @else
                <tr><th>Trustee ID</th><th>Member</th><th>Membership number</th><th>Unit</th><th>Last appointment</th><th>End date</th><th>Status</th><th>Reason</th></tr>
            @endif
        </thead>
        <tbody>
            @forelse($trustees as $trustee)
                @php($appointment = $title === 'Active Trustees' ? $trustee->appointments->first(fn ($item) => $item->status->value === 'active') : $trustee->appointments->first())
                @php($status = $appointment?->status?->value ?? 'inactive')
                <tr>
                    <td>{{ $trustee->trustee_id }}</td>
                    <td>{{ $trustee->member?->user?->name ?: 'Unnamed member' }}</td>
                    <td>{{ $trustee->member?->membership_number ?: '—' }}</td>
                    <td>{{ $trustee->member?->unit?->name ?: '—' }}</td>
                    <td>{{ $appointment?->appointed_at?->format('d M Y') ?: '—' }}</td>
                    @if($title === 'Active Trustees')
                        <td>Active</td>
                    @else
                        <td>{{ $appointment?->ended_at?->format('d M Y') ?: '—' }}</td>
                        <td>{{ $status === 'removed' ? 'Former' : 'Inactive' }}</td>
                        <td>{{ $appointment?->end_reason ?: '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
