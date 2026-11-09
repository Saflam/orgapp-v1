<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Member Profile</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; line-height: 1.4; }
        h1 { margin: 0; font-size: 20px; color: #111827; }
        h2 { margin: 18px 0 7px; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; font-size: 12px; color: #111827; page-break-after: avoid; }
        h3 { margin: 10px 0 5px; font-size: 10px; color: #374151; page-break-after: avoid; }
        .organization { margin-bottom: 4px; font-size: 11px; color: #4b5563; }
        .muted { color: #6b7280; }
        .header { padding-bottom: 10px; border-bottom: 2px solid #047857; }
        .meta { margin-top: 7px; }
        .profile-photo { float: right; width: 76px; height: 76px; object-fit: cover; border: 1px solid #d1d5db; border-radius: 5px; margin-left: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        td, th { padding: 5px 6px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; overflow-wrap: anywhere; }
        th { background: #f3f4f6; font-weight: bold; }
        .label { width: 27%; color: #6b7280; }
        .subtle { color: #6b7280; font-size: 8px; }
        .block { margin: 5px 0 9px; padding: 7px; border: 1px solid #e5e7eb; page-break-inside: avoid; }
        .footer { margin-top: 18px; padding-top: 7px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8px; }
        .break { overflow-wrap: anywhere; white-space: pre-line; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>
@php
    $formatDate = static function ($value, string $format = 'd M Y'): string {
        if (!$value) return 'Not recorded';
        try { return \Illuminate\Support\Carbon::parse($value)->format($format); }
        catch (\Throwable $exception) { return (string) $value; }
    };
    $displayStatus = static function ($value): string {
        if (is_object($value) && isset($value->value)) $value = $value->value;
        return $value ? ucfirst(str_replace('_', ' ', (string) $value)) : 'Not recorded';
    };
    $details = $memberRecord->user?->details;
    $user = $memberRecord->user;
    $primaryPhone = $user?->phoneNumbers?->firstWhere('is_primary', true);
    $flatten = static function (array $values, string $prefix = '') use (&$flatten): array {
        $flat = [];
        $ignoredKeys = ['id', 'member_id', 'metadata', 'created_at', 'updated_at', 'profile', 'units'];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $ignoredKeys, true)) {
                continue;
            }

            // Preserve explicit labels from module-defined field rows.
            if (is_array($value) && array_key_exists('label', $value) && array_key_exists('value', $value)) {
                $fieldLabel = trim((string) $value['label']);
                $fieldValue = $value['value'];
                if ($fieldLabel !== '' && (is_scalar($fieldValue) || $fieldValue instanceof \Stringable) && $fieldValue !== '') {
                    $flat[$fieldLabel] = (string) $fieldValue;
                }
                continue;
            }

            $label = trim($prefix . ' ' . ucwords(str_replace(['_', '-'], ' ', (string) $key)));
            if (is_array($value)) {
                $flat += $flatten($value, $label);
            } elseif ((is_scalar($value) || $value instanceof \Stringable) && $value !== '' && $value !== null) {
                $flat[$label] = (string) $value;
            }
        }

        return $flat;
    };
@endphp
<div class="header">
    @if($profilePhotoData)<img class="profile-photo" src="{{ $profilePhotoData }}" alt="Profile photo">@endif
    <div class="organization">{{ $organization->name }}</div>
    <h1>Member Profile</h1>
    <div class="meta">
        <strong>{{ $user?->name ?? 'Unnamed member' }}</strong><br>
        <span class="muted">Membership number:</span> {{ $memberRecord->membership_number ?: 'Not assigned' }}
        &nbsp; | &nbsp; <span class="muted">Unit:</span> {{ $memberRecord->unit?->name ?? 'No unit assigned' }}<br>
        <span class="muted">Date joined:</span> {{ $formatDate($memberRecord->joined_at) }}
    </div>
</div>

<h2>Personal details</h2>
<table>
    <tr><td class="label">Full name</td><td>{{ $user?->name ?: 'Not provided' }}</td></tr>
    <tr><td class="label">First name</td><td>{{ $details?->first_name ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Middle name</td><td>{{ $details?->middle_name ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Last name</td><td>{{ $details?->last_name ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Email</td><td>{{ $user?->email ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Date of birth</td><td>{{ $formatDate($details?->date_of_birth) }}</td></tr>
    <tr><td class="label">Gender</td><td>{{ $details?->gender ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Blood group</td><td>{{ $details?->blood_group ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Phone</td><td>{{ $primaryPhone?->phone_e164 ?: ($user?->phone ?: 'Not provided') }}</td></tr>
    <tr><td class="label">WhatsApp</td><td>{{ trim(($details?->whatsapp_calling_code ?? '').' '.($details?->whatsapp ?? '')) ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Home contact</td><td>{{ $details?->home_contact ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Profession</td><td>{{ $details?->profession ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Company</td><td>{{ $details?->company ?: 'Not provided' }}</td></tr>
    <tr><td class="label">Company address</td><td class="break">{{ $details?->company_address ?: 'Not provided' }}</td></tr>
</table>

<h2>Contact numbers</h2>
@if($user?->phoneNumbers?->isNotEmpty())
    <table><thead><tr><th>Type</th><th>Label</th><th>Number</th><th>Primary</th></tr></thead><tbody>
    @foreach($user->phoneNumbers as $phone)
        <tr><td>{{ $phone->type ?: 'Phone' }}</td><td>{{ $phone->label ?: '—' }}</td><td>{{ $phone->phone_e164 ?: '—' }}{{ $phone->extension ? ' ext. '.$phone->extension : '' }}</td><td>{{ $phone->is_primary ? 'Yes' : 'No' }}</td></tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No additional contact numbers recorded.</p>
@endif

<h2>Addresses</h2>
@if(($user?->addresses ?? collect())->isNotEmpty())
    @foreach($user->addresses as $address)
        <div class="block">
            <strong>{{ ucwords(str_replace('_', ' ', $address->address_type ?? 'Address')) }}</strong><br>
            {{ collect([$address->address_line_1, $address->address_line_2, $address->locality, $address->city, $address->state, $address->postal_code, $address->country])->filter()->implode(', ') ?: 'No address details' }}
            @if($address->is_primary)<br><span class="subtle">Primary address</span>@endif
        </div>
    @endforeach
@else
    <p class="muted">No addresses recorded.</p>
@endif

<h2>Emergency contacts</h2>
@if($user?->emergencyContacts?->isNotEmpty())
    <table><thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Extension</th><th>Primary</th><th>Notes</th></tr></thead><tbody>
    @foreach($user->emergencyContacts as $contact)
        <tr><td>{{ $contact->contact_name ?: '—' }}</td><td>{{ $contact->relationship ? str_replace('_', ' ', $contact->relationship) : '—' }}</td><td>{{ $contact->phone_e164 ?: '—' }}</td><td>{{ $contact->extension ?: '—' }}</td><td>{{ $contact->is_primary ? 'Yes' : 'No' }}</td><td class="break">{{ $contact->notes ?: '—' }}</td></tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No emergency contacts recorded.</p>
@endif

<h2>Membership details</h2>
<table>
    <tr><td class="label">Membership number</td><td>{{ $memberRecord->membership_number ?: 'Not assigned' }}</td></tr>
    <tr><td class="label">Current status</td><td>{{ $activeMembership ? $displayStatus($activeMembership->status) : 'No active membership' }}</td></tr>
    <tr><td class="label">Membership type</td><td>{{ $activeMembership?->membershipType?->name ?? 'Not available' }}</td></tr>
    <tr><td class="label">Start date</td><td>{{ $formatDate($activeMembership?->starts_at) }}</td></tr>
    <tr><td class="label">End date</td><td>{{ $formatDate($activeMembership?->ends_at) }}</td></tr>
    @if($activeMembership?->status_reason)<tr><td class="label">Status reason</td><td class="break">{{ $activeMembership->status_reason }}</td></tr>@endif
</table>

<h2>Membership history</h2>
@if($memberRecord->memberships->isNotEmpty())
    <table><thead><tr><th>Membership type</th><th>Status</th><th>Reason</th><th>Start date</th><th>End date</th></tr></thead><tbody>
    @foreach($memberRecord->memberships->sortByDesc('starts_at') as $membership)
        <tr>
            <td>{{ $membership->membershipType?->name ?? 'Membership' }}</td>
            <td>{{ $displayStatus($membership->status) }}</td>
            <td class="break">{{ $membership->status_reason ?: '—' }}</td>
            <td>{{ $formatDate($membership->starts_at) }}</td>
            <td>{{ $formatDate($membership->ends_at) }}</td>
        </tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No membership records found.</p>
@endif

<h2>Family / dependants</h2>
@if($memberRecord->dependants->isNotEmpty())
    <table><thead><tr><th>Name</th><th>Relationship</th><th>Date of birth</th><th>Gender</th></tr></thead><tbody>
    @foreach($memberRecord->dependants as $dependant)
        <tr>
            <td>{{ trim(($dependant->first_name ?? '').' '.($dependant->middle_name ?? '').' '.($dependant->last_name ?? '')) ?: 'Unnamed dependant' }}</td>
            <td>{{ $dependant->pivot?->relationship_type ? ucfirst($dependant->pivot->relationship_type) : '—' }}</td>
            <td>{{ $formatDate($dependant->date_of_birth) }}</td>
            <td>{{ $dependant->gender ?: '—' }}</td>
        </tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No active family members recorded.</p>
@endif

<h2>Identity document details</h2>
<p class="subtle">Document metadata is included for reference. Uploaded identification proofs are not embedded in this PDF.</p>
@if($identificationDocuments->isNotEmpty())
    <table><thead><tr><th>Document type</th><th>Identification number</th><th>Expiry date</th><th>Proof uploaded</th></tr></thead><tbody>
    @foreach($identificationDocuments as $identification)
        <tr>
            <td>{{ $identification->identificationType?->name ?? 'Identification document' }}</td>
            <td>{{ $identification->identification_number ?: 'Not provided' }}</td>
            <td>{{ $formatDate($identification->expires_at) }}</td>
            <td>{{ $identification->getMedia('identification-documents')->isNotEmpty() ? 'Yes' : 'No' }}</td>
        </tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No identity documents recorded.</p>
@endif

<h2>Payments</h2>
@if($paymentObligations->isNotEmpty())
    <table><thead><tr><th>Payment item</th><th>Due date</th><th>Payable</th><th>Paid</th><th>Status</th></tr></thead><tbody>
    @foreach($paymentObligations as $obligation)
        <tr>
            <td>{{ $obligation->item_title ?: 'Membership payment' }}</td>
            <td>{{ $formatDate($obligation->due_at) }}</td>
            <td>{{ $obligation->currency }} {{ number_format((float) $obligation->payable_amount, 2) }}</td>
            <td>{{ $obligation->currency }} {{ number_format((float) $obligation->paid_amount, 2) }}</td>
            <td>{{ $displayStatus($obligation->status) }}</td>
        </tr>
    @endforeach
    </tbody></table>
@else
    <p class="muted">No payment obligations recorded.</p>
@endif

<h2>Membership applications and review history</h2>
@if($applications->isNotEmpty())
    @foreach($applications as $application)
        <div class="block">
            <strong>{{ $application->membershipType?->name ?? 'Membership application' }}</strong><br>
            <span class="muted">Status:</span> {{ $displayStatus($application->status) }}
            &nbsp; | &nbsp; <span class="muted">Submitted:</span> {{ $formatDate($application->submitted_at, 'd M Y, h:i A') }}
            @if($application->statusHistories->isNotEmpty())
                <table style="margin-top:6px"><thead><tr><th>From</th><th>To</th><th>Reviewed by</th><th>Notes</th></tr></thead><tbody>
                @foreach($application->statusHistories as $history)
                    <tr>
                        <td>{{ $displayStatus($history->from_status) }}</td>
                        <td>{{ $displayStatus($history->to_status) }}</td>
                        <td>{{ $history->actor?->name ?? 'System / unavailable' }}</td>
                        <td class="break">{{ $history->notes ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody></table>
            @endif
        </div>
    @endforeach
@else
    <p class="muted">No membership applications recorded.</p>
@endif

@if(!empty($moduleSections))
    <h2>Organization module details</h2>
    @foreach($moduleSections as $section)
        <h3>{{ $section['title'] ?? 'Module details' }}</h3>
        @php $moduleValues = $flatten($section['data'] ?? []); @endphp
        @if(count($moduleValues))
            <table><tbody>
            @foreach($moduleValues as $label => $value)
                <tr><td class="label">{{ $label }}</td><td class="break">{{ $value }}</td></tr>
            @endforeach
            </tbody></table>
        @else
            <p class="muted">No additional details recorded.</p>
        @endif
    @endforeach
@endif

<div class="footer">
    Generated {{ now()->format('d M Y, h:i A') }}. This document contains member profile details, family relationships, membership records and payment/application summaries.
    Internal administrator notes and uploaded identification proof files are intentionally excluded.
</div>
</body>
</html>
