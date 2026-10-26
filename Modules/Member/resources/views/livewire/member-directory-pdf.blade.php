<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Members</title><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#172033}h1{font-size:20px;margin:0 0 5px}p{color:#64748b;margin:0 0 16px}table{width:100%;border-collapse:collapse}th{background:#f1f5f9;text-align:left;font-size:9px;text-transform:uppercase}th,td{padding:8px;border:1px solid #dbe2ea}tr{page-break-inside:avoid}
</style></head><body>
<h1>{{ $organization->name ?? 'Organization' }} — Members</h1><p>{{ $members->count() }} matching members · Generated {{ now()->format('d M Y H:i') }}</p>
<table><thead><tr><th>Member</th><th>Email</th><th>Phone</th><th>Membership ID</th><th>Unit</th><th>Status</th></tr></thead><tbody>
@foreach($members as $member) @php($membership = $member->memberships->sortByDesc('starts_at')->first()) @php($status = $membership?->status ?? 'unknown') @if($status instanceof \BackedEnum) @php($status = $status->value) @endif
<tr><td>{{ $member->user?->name ?? 'Unnamed member' }}</td><td>{{ $member->user?->email ?? '—' }}</td><td>{{ $member->user?->phone ?? '—' }}</td><td>{{ $member->membership_number ?? '—' }}</td><td>{{ $member->unit?->name ?? 'Organization-wide' }}</td><td>{{ \Illuminate\Support\Str::headline((string) $status) }}</td></tr>
@endforeach
</tbody></table></body></html>
