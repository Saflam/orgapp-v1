<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Member Profile</title>
    <style>
        @page { margin: 32px 38px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.45; }
        h1 { margin: 0; font-size: 21px; color: #111827; }
        h2 { margin: 22px 0 8px; padding-bottom: 5px; border-bottom: 1px solid #d1d5db; font-size: 13px; color: #111827; }
        .organization { margin-bottom: 4px; font-size: 12px; color: #4b5563; }
        .muted { color: #6b7280; }
        .header { padding-bottom: 14px; border-bottom: 2px solid #047857; }
        .meta { margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 7px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: bold; }
        .label { width: 28%; color: #6b7280; }
        .two-col { width: 100%; }
        .two-col td { width: 50%; border: 0; padding: 0 12px 0 0; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="organization">{{ $organization->name }}</div>
        <h1>Member Profile</h1>
        <div class="meta">
            <strong>{{ $memberRecord->user?->name ?? 'Unnamed member' }}</strong><br>
            <span class="muted">Membership ID:</span> {{ $memberRecord->membership_number ?: 'Not assigned' }}
            &nbsp; | &nbsp;
            <span class="muted">Unit:</span> {{ $memberRecord->unit?->name ?? 'No unit assigned' }}
        </div>
    </div>

    <h2>Contact information</h2>
    <table>
        <tr><td class="label">Email</td><td>{{ $memberRecord->user?->email ?: 'Not provided' }}</td></tr>
        <tr><td class="label">Phone</td><td>{{ $memberRecord->user?->phone ?: 'Not provided' }}</td></tr>
        <tr><td class="label">Date joined</td><td>{{ $memberRecord->joined_at?->format('d M Y') ?? 'Not recorded' }}</td></tr>
    </table>

    <h2>Current membership</h2>
    <table>
        <tr><td class="label">Status</td><td>{{ $activeMembership ? 'Active' : 'No active membership' }}</td></tr>
        <tr><td class="label">Membership type</td><td>{{ $activeMembership?->membershipType?->name ?? 'Not available' }}</td></tr>
        <tr><td class="label">Start date</td><td>{{ $activeMembership?->starts_at?->format('d M Y') ?? 'Not recorded' }}</td></tr>
        <tr><td class="label">End date</td><td>{{ $activeMembership?->ends_at?->format('d M Y') ?? 'Not recorded' }}</td></tr>
    </table>

    <h2>Addresses</h2>
    @if(($memberRecord->user?->addresses ?? collect())->isNotEmpty())
        @foreach($memberRecord->user->addresses as $address)
            <p>
                <strong>{{ ucwords(str_replace('_', ' ', $address->address_type ?? 'Address')) }}</strong><br>
                {{ collect([$address->address_line_1, $address->address_line_2, $address->locality, $address->city, $address->state, $address->postal_code, $address->country])->filter()->implode(', ') ?: 'No address details' }}
            </p>
        @endforeach
    @else
        <p class="muted">No addresses recorded.</p>
    @endif

    <h2>Membership history</h2>
    @if($memberRecord->memberships->isNotEmpty())
        <table>
            <thead><tr><th>Membership type</th><th>Status</th><th>Start date</th><th>End date</th></tr></thead>
            <tbody>
            @foreach($memberRecord->memberships->sortByDesc('starts_at') as $membership)
                <tr>
                    <td>{{ $membership->membershipType?->name ?? 'Membership' }}</td>
                    <td>{{ $membership->status ? ucfirst(str_replace('_', ' ', $membership->status->value)) : 'Unknown' }}</td>
                    <td>{{ $membership->starts_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $membership->ends_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No membership records found.</p>
    @endif

    <div class="footer">
        Generated {{ now()->format('d M Y, h:i A') }}. This document contains member profile information.
        Internal administrator notes and identification documents are not included.
    </div>
</body>
</html>
