@php
    $statusClasses = [
        'draft' => 'ma-status ma-status-draft',
        'submitted' => 'ma-status ma-status-submitted',
        'verified' => 'ma-status ma-status-verified',
        'reviewed' => 'ma-status ma-status-reviewed',
        'approved' => 'ma-status ma-status-approved',
        'pending_payment' => 'ma-status ma-status-pending-payment',
        'pending_confirmation' => 'ma-status ma-status-pending-confirmation',
        'confirmed' => 'ma-status ma-status-confirmed',
        'rejected' => 'ma-status ma-status-rejected',
        'withdrawn' => 'ma-status ma-status-withdrawn',
    ];
@endphp
<div class="membership-admin">
    
    @if ($successMessage !== '')
        <div class="ma-alert" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif
    
    @if ($selectedApplication)
        @php
            $data = $selectedApplication->data ?? [];
            $member = data_get($data, 'member', []);
            $address = data_get($data, 'address', data_get($data, 'addresses', []));
            $name = trim(implode(' ', array_filter([data_get($member, 'first_name'), data_get($member, 'middle_name'), data_get($member, 'last_name')]))) ?: ($selectedApplication->user?->name ?: 'Applicant');
            $status = $selectedApplication->status;
            $photo = $selectedApplication->getFirstMedia('profile-photo');
            $documents = $selectedApplication->getMedia('application-documents');
            $unitId = data_get($member, 'preferred_unit_id') ?: data_get($member, 'unit_id');
            $unitName = $unitNames[$unitId] ?? 'Not assigned';
            $nextAction = match ($status->value) { 'submitted' => 'Verify Application', 'verified' => 'Review Application', 'reviewed' => 'Approve Application', 'pending_confirmation' => 'Confirm Membership', default => 'Application Details' };
        @endphp
        <div class="ma-page-head">
            <div><div class="ma-eyebrow">Membership Applications / Application #{{ $selectedApplication->id }}</div><h1>{{ $nextAction }}</h1><p>Review applicant details, supporting documents and the decision history.</p></div>
            <button type="button" wire:click="clearSelection" wire:loading.attr="disabled" class="ma-btn">← Back to applications</button>
        </div>

        <div class="ma-review-layout">
            <div class="ma-review-main">
                <section class="ma-card">
                    <div class="ma-profile">
                        @if ($photo)
                            <a href="{{ route('member.membership-application.media', ['applicationId' => $selectedApplication->id, 'mediaId' => $photo->id]) }}" target="_blank" rel="noopener" title="Open full-size profile photo" aria-label="Open full-size profile photo of {{ $name }}">
                                <img src="{{ route('member.membership-application.media', ['applicationId' => $selectedApplication->id, 'mediaId' => $photo->id]) }}" alt="Profile photo of {{ $name }} — open for full-size view" class="ma-photo">
                            </a>
                        @else
                            <div class="ma-photo ma-photo-placeholder" aria-label="No profile photo">{{ mb_substr($name, 0, 1) }}</div>
                        @endif
                        <div style="min-width:0;flex:1">
                            <h2>{{ $name }}</h2>
                            <span class="{{ $statusClasses[$status->value] ?? 'ma-status ma-status-withdrawn' }}">{{ str($status->value)->replace('_', ' ')->title() }}</span>
                            <div class="ma-profile-meta">{{ $selectedApplication->user?->email ?: 'No email on account' }}</div>
                            <div class="ma-profile-meta">Unit: <strong>{{ $unitName }}</strong> · Submitted {{ $selectedApplication->submitted_at?->format('d M Y, h:i A') ?? '—' }}</div>
                        </div>
                    </div>
                </section>

                <section class="ma-card">
                    <h2>Personal details</h2>
                    <dl class="ma-details">
                        @foreach ([
                            'Date of birth' => data_get($member, 'date_of_birth'),
                            'Gender' => data_get($member, 'gender'),
                            'Blood group' => data_get($member, 'blood_group'),
                            'Primary phone' => trim((data_get($member, 'primary_phone_calling_code') ?? '') . ' ' . (data_get($member, 'primary_phone') ?? '')),
                            'WhatsApp' => trim((data_get($member, 'whatsapp_calling_code') ?? '') . ' ' . (data_get($member, 'whatsapp') ?? '')),
                            'Home contact' => data_get($member, 'home_contact'),
                            'Profession' => data_get($member, 'profession'),
                            'Company' => data_get($member, 'company'),
                        ] as $label => $value)
                            <div class="ma-detail"><dt>{{ $label }}</dt><dd>{{ is_scalar($value) && trim((string) $value) !== '' ? $value : '—' }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                <section class="ma-card">
                    <h2>Spouse relationship</h2>
                    @php $selectedSpouseRequest = $spouseRequestByApplicationId[$selectedApplication->id] ?? null; @endphp
                    @if (data_get($data, 'spouse.member_id'))
                        <p class="ma-muted" style="font-size:12px;margin-bottom:10px">Applicant selected member #{{ data_get($data, 'spouse.member_id') }} as spouse. Request status: <strong>{{ $selectedSpouseRequest ? str($selectedSpouseRequest->status)->replace('_', ' ')->title() : 'Request not recorded' }}</strong>.</p>
                        @if ($selectedSpouseRequest)
                            <p class="ma-muted" style="font-size:12px;margin-bottom:10px">Selected person: {{ $spouseMemberNames[$selectedApplication->id] ?? 'Member' }}. A relationship is created only after acceptance and membership confirmation.</p>
                        @endif
                    @else
                        <p class="ma-muted" style="font-size:12px;margin-bottom:10px">No existing-member spouse request is attached to this application.</p>
                    @endif
                    @if ($selectedApplication->member_id)
                        @forelse ($selectedSpouses as $connectedSpouse)
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--line,#e3e8f0)">
                                <span>{{ $connectedSpouse->user?->name ?? ('Member #'.$connectedSpouse->id) }}</span>
                                <button type="button" class="ma-btn ma-btn-danger" wire:click="adminDisconnectSpouse({{ $connectedSpouse->id }})" wire:confirm="Disconnect this spouse relationship?">Disconnect</button>
                            </div>
                        @empty
                            <p class="ma-muted" style="font-size:12px">No active spouse relationship.</p>
                        @endforelse
                        <form wire:submit="adminConnectSpouse" style="display:flex;gap:8px;align-items:end;margin-top:14px">
                            <label class="ma-field" style="flex:1"><span>Connect member (administrator)</span>
                                <select wire:model="spouseMemberId">
                                    <option value="">Choose member</option>
                                    @foreach ($organizationMembers as $candidate)
                                        @if ($candidate->id !== $selectedApplication->member_id)
                                            <option value="{{ $candidate->id }}">{{ $candidate->user->name }}{{ $candidate->membership_number ? ' · '.$candidate->membership_number : '' }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="ma-btn ma-btn-primary">Connect</button>
                        </form>
                        <p class="ma-muted" style="font-size:11px;margin-top:7px">Administrative changes are recorded in relationship metadata. Use only after verifying the relationship.</p>
                    @else
                        <p class="ma-muted" style="font-size:12px">Administrator linking is available after the applicant becomes a member.</p>
                    @endif
                </section>

                <section class="ma-card">
                    <h2>Address</h2>
                    <div class="ma-muted" style="font-size:12px;overflow-wrap:anywhere;line-height:1.8">
                        <div>{{ data_get($address, 'address_line_1') ?: data_get($address, 'line_1') ?: '—' }}</div>
                        @if (data_get($address, 'address_line_2') || data_get($address, 'line_2'))<div>{{ data_get($address, 'address_line_2') ?: data_get($address, 'line_2') }}</div>@endif
                        <div>{{ collect([data_get($address, 'locality'), data_get($address, 'city'), data_get($address, 'state'), data_get($address, 'postal_code')])->filter()->implode(', ') ?: '—' }}</div>
                        <div>{{ data_get($address, 'country') ?: '—' }}</div>
                    </div>
                </section>

                <section class="ma-card">
                    <h2>Identity documents</h2>
                    @if ($documents->isEmpty())
                        <p class="ma-muted" style="font-size:12px">No identity documents are attached to this application.</p>
                    @else
                        @php
                            // Map the media's identification_type_id back to the application's
                            // configured identification code. The uploader stores the type ID and
                            // side on media; it does not always store identification_code directly.
                            $identificationCodesById = collect(data_get($data, 'identifications', []))
                                ->mapWithKeys(function ($identification, $code) {
                                    $typeId = data_get($identification, 'identification_type_id');
                                    return $typeId ? [(string) $typeId => strtolower(trim((string) $code))] : [];
                                });
                            $documentBaseCode = function ($document) use ($identificationCodesById): string {
                                $code = strtolower(trim((string) $document->getCustomProperty('identification_code')));
                                if ($code === '') {
                                    $typeId = (string) $document->getCustomProperty('identification_type_id');
                                    $code = (string) ($identificationCodesById[$typeId] ?? '');
                                }
                                if ($code === '') {
                                    $fileName = strtolower((string) ($document->file_name ?? $document->name ?? ''));
                                    foreach (['passport', 'civil_id', 'civil-id', 'civilid', 'aadhar', 'aadhaar', 'national_id', 'national-id'] as $knownCode) {
                                        if (str_contains($fileName, $knownCode)) {
                                            $code = $knownCode;
                                            break;
                                        }
                                    }
                                }
                                $code = preg_replace('/[-_](front|back|document|file|image)$/', '', $code);
                                return $code !== '' ? $code : 'other';
                            };
                            $documentSide = function ($document): string {
                                $side = strtolower(trim((string) $document->getCustomProperty('document_side')));
                                if (in_array($side, ['front', 'back'], true)) {
                                    return $side;
                                }
                                $code = strtolower(trim((string) $document->getCustomProperty('identification_code')));
                                if (preg_match('/[-_](front|back)$/', $code, $matches)) {
                                    return $matches[1];
                                }
                                return '';
                            };
                            $documentGroups = $documents->groupBy($documentBaseCode);
                            $documentLabels = [
                                'passport' => 'Passport',
                                'civil_id' => 'Civil ID',
                                'civil-id' => 'Civil ID',
                                'civilid' => 'Civil ID',
                                'aadhar' => 'Aadhar',
                                'aadhaar' => 'Aadhar',
                                'national_id' => 'National ID',
                                'national-id' => 'National ID',
                            ];
                        @endphp
                        @foreach ($documentGroups as $documentCode => $groupDocuments)
                            @php
                                $documentLabel = $documentLabels[$documentCode] ?? str($documentCode)->replace(['_', '-'], ' ')->title()->toString();
                                $frontDocument = $groupDocuments->first(fn ($document) => $documentSide($document) === 'front');
                                $backDocument = $groupDocuments->first(fn ($document) => $documentSide($document) === 'back');
                                $singleDocument = $groupDocuments->first(fn ($document) => ! in_array($documentSide($document), ['front', 'back'], true));
                                $mediaUrl = fn ($document) => route('member.membership-application.media', ['applicationId' => $selectedApplication->id, 'mediaId' => $document->id]);
                            @endphp
                            <div wire:key="application-document-group-{{ $selectedApplication->id }}-{{ $loop->index }}" class="ma-doc-row">
                                <div style="min-width:0;flex:1"><div style="font-size:13px;font-weight:750;overflow-wrap:anywhere">{{ $documentLabel }}</div><div class="ma-muted" style="font-size:11px;overflow-wrap:anywhere">{{ $groupDocuments->count() === 1 ? $groupDocuments->first()->file_name : $groupDocuments->count() . ' files' }}</div></div>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                                    @if ($frontDocument)
                                        <a class="ma-btn" target="_blank" rel="noopener" href="{{ $mediaUrl($frontDocument) }}">Open Front ↗</a>
                                    @endif
                                    @if ($backDocument)
                                        <a class="ma-btn" target="_blank" rel="noopener" href="{{ $mediaUrl($backDocument) }}">Open Back ↗</a>
                                    @endif
                                    @if (! $frontDocument && ! $backDocument && $singleDocument)
                                        <a class="ma-btn" target="_blank" rel="noopener" href="{{ $mediaUrl($singleDocument) }}">Open ↗</a>
                                    @endif
                                    @if (! $frontDocument && ! $backDocument && ! $singleDocument)
                                        @foreach ($groupDocuments as $document)
                                            <a class="ma-btn" target="_blank" rel="noopener" href="{{ $mediaUrl($document) }}">Open {{ str($documentSide($document) ?: 'document')->title() }} ↗</a>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </section>

                @if (data_get($data, 'extension_data', []))
                    <section class="ma-card">
                        <h2>Additional details</h2>
                        @foreach (data_get($data, 'extension_data', []) as $module => $fields)
                            <h3 style="margin:14px 0 10px">{{ str($module)->replace('_', ' ')->title() }}</h3>
                            <dl class="ma-details">
                                @foreach ((array) $fields as $field => $value)
                                    <div class="ma-detail"><dt>{{ str($field)->replace('_', ' ')->title() }}</dt><dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>
                                @endforeach
                            </dl>
                        @endforeach
                    </section>
                @endif
            </div>

            <aside class="ma-review-side">
                @if (in_array($status->value, ['submitted', 'verified', 'reviewed'], true))
                    <section class="ma-card">
                        <h2>Application decision</h2>
                        <div class="ma-field" style="margin-top:16px"><label for="decision-notes">Comments / notes</label><textarea id="decision-notes" wire:model="decisionNotes" rows="3" maxlength="5000" placeholder="Add a review comment or rejection reason"></textarea>@error('decisionNotes')<p class="ma-error">{{ $message }}</p>@enderror</div>
                        @if ($status->value === 'submitted')<button type="button" wire:click="verify" wire:loading.attr="disabled" class="ma-btn ma-btn-success ma-btn-full" style="margin-top:16px">Verify application</button>
                        @elseif ($status->value === 'verified')<button type="button" wire:click="review" wire:loading.attr="disabled" class="ma-btn ma-btn-review ma-btn-full" style="margin-top:16px">Complete review</button>
                        @elseif ($status->value === 'reviewed')
                            <div class="ma-field" style="margin-top:16px;margin-bottom:18px">
                                <label for="review-membership-type">Membership type (required before payment)</label>
                                <select id="review-membership-type" wire:model="membershipTypeId">
                                    <option value="">Choose membership type</option>
                                    @foreach ($membershipTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                                </select>
                                @error('membershipTypeId')<p class="ma-error">{{ $message }}</p>@enderror
                                <p class="ma-muted" style="font-size:11px;margin:2px 0 0">The selected type is saved when you approve this application and is used to calculate payment obligations.</p>
                            </div>
                            <button type="button" wire:click="approve" wire:loading.attr="disabled" class="ma-btn ma-btn-success ma-btn-full">Approve application</button>
                        @endif
                        <label class="ma-check" style="margin-top:18px"><input type="checkbox" wire:model="allowReapply"><span>Allow applicant to reapply if rejected</span></label>
                        <button type="button" wire:click="reject" wire:confirm="Reject this application? The reason will be recorded in its history." wire:loading.attr="disabled" class="ma-btn ma-btn-danger ma-btn-full" style="margin-top:16px">Reject application</button>
                    </section>
                @endif

                @if (in_array($status->value, ['approved', 'pending_payment'], true) && $canReceivePayment)
                    <section class="ma-card">
                        <h2>Payment</h2>
                        <p class="ma-muted" style="font-size:12px;margin:-5px 0 15px">Open the invoice to review charges, apply any authorized discount, and record the payment after funds have actually been received.</p>
                        <button type="button" wire:click="openPaymentInvoice" wire:loading.attr="disabled" class="ma-btn ma-btn-warning ma-btn-full">Record Payment</button>
                    </section>
                @endif
                @if ($status->value === 'pending_confirmation')
                    <section class="ma-card"><h2>Confirm membership</h2><p class="ma-muted" style="font-size:12px;margin:-5px 0 16px">Confirmation activates the membership after required payments are settled. The membership type was assigned during approval.</p>
                        @if (! $selectedApplication->membership_type_id)
                            <p class="ma-error">This application has no membership type assigned. It must be corrected before confirmation.</p>
                        @else
                            <div class="ma-field" style="margin-bottom:16px"><label>Membership type</label><div class="ma-data-value">{{ $selectedApplication->membershipType?->name ?? 'Unavailable' }}</div></div>
                        @endif
                        @error('membershipTypeId')<p class="ma-error" style="margin:0 0 14px">{{ $message }}</p>@enderror
                        <div class="ma-field"><label for="membership-number">Membership number (optional)</label><input id="membership-number" wire:model="membershipNumber" maxlength="255" placeholder="Allocate automatically if blank">@error('membershipNumber')<p class="ma-error">{{ $message }}</p>@enderror</div>
                        <div class="ma-field" style="margin-top:16px"><label for="membership-starts-at">Membership start date</label><input id="membership-starts-at" type="date" wire:model="startsAt" required>@error('startsAt')<p class="ma-error">{{ $message }}</p>@enderror</div>
                        <div class="ma-field" style="margin-top:16px"><label for="membership-ends-at">Membership expiry date</label><input id="membership-ends-at" type="date" wire:model="endsAt" required>@error('endsAt')<p class="ma-error">{{ $message }}</p>@enderror<div class="ma-muted" style="font-size:11px;margin-top:5px">Defaults to one year from today. Adjust this date before confirming if needed.</div></div>
                        <button type="button" wire:click="confirmMembership" wire:confirm="Confirm and activate this membership?" wire:loading.attr="disabled" class="ma-btn ma-btn-success ma-btn-full" style="margin-top:16px" @disabled(! $selectedApplication->membership_type_id)>Confirm membership</button>
                    </section>
                @endif

                <section class="ma-card">
                    <h2>Review timeline</h2>
                    @if ($selectedApplication->statusHistories->isEmpty())
                        <p class="ma-muted" style="font-size:12px">No status changes have been recorded yet.</p>
                    @else
                        <ol class="ma-timeline">
                            @foreach ($selectedApplication->statusHistories->sortBy('created_at') as $history)
                                @php $toStatus = $history->to_status->value; @endphp
                                <li wire:key="application-history-{{ $history->id }}"><div class="ma-timeline-title">{{ str($toStatus)->replace('_', ' ')->title() }} by {{ $history->actor?->name ?? 'System' }}</div><div class="ma-timeline-date">{{ $history->created_at?->format('d M Y \a\t h:i A') ?? 'Time not recorded' }}</div>@if ($history->notes)<p style="margin:8px 0 0;font-size:12px;white-space:pre-line;overflow-wrap:anywhere">{{ $history->notes }}</p>@endif</li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </aside>
        </div>
    @else
        <div class="ma-page-head"><div><h1>Membership applications</h1><p>Review applications for {{ $organization->name }} and record each decision in the application history.</p></div></div>
        <section class="ma-card">
            <form wire:submit="applyFilters">
                <div class="ma-filter-toolbar">
                    <div class="ma-field ma-search-field"><label for="application-search">Search</label><div class="ma-input-icon"><span aria-hidden="true">⌕</span><input id="application-search" type="search" wire:model="search" placeholder="Name or email"></div></div>
                    <div class="ma-field"><label for="application-status">Status</label><select id="application-status" wire:model="statusFilter"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="ma-field"><label for="application-unit">Unit</label><select id="application-unit" wire:model="unitFilter"><option value="">All units</option>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></div>
                    <div class="ma-filter-actions"><button type="submit" class="ma-btn ma-btn-primary" wire:loading.attr="disabled" title="Apply filters" aria-label="Apply filters"><span aria-hidden="true">⌕</span><span>Apply</span></button><button type="button" class="ma-btn" wire:click="resetFilters" wire:loading.attr="disabled" title="Reset filters" aria-label="Reset filters"><span aria-hidden="true">↺</span><span>Reset</span></button></div>
                </div>
            </form>

            <div class="ma-desktop-list" style="margin-top:20px">
                <div class="ma-table-wrap"><table class="ma-table"><thead><tr><th>Applicant</th><th>Unit</th><th>Membership type</th><th>Submitted</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
                    @forelse ($applications as $application)
                        @php $memberData = data_get($application->data, 'member', []); $applicantName = trim(implode(' ', array_filter([data_get($memberData, 'first_name'), data_get($memberData, 'middle_name'), data_get($memberData, 'last_name')]))) ?: ($application->user?->name ?: 'Applicant #' . $application->user_id); $rowStatus = $application->status->value; $rowUnit = data_get($memberData, 'preferred_unit_id') ?: data_get($memberData, 'unit_id'); @endphp
                        <tr wire:key="membership-application-{{ $application->id }}"><td><div class="ma-applicant-name">{{ $applicantName }}</div><div class="ma-applicant-email">{{ $application->user?->email ?: 'No email on account' }}</div><div class="ma-muted" style="font-size:10px;margin-top:3px">Application #{{ $application->id }}</div>@php $sourceSpouseId = data_get($application->data, 'member.metadata.spouse_of_application_id'); $pairedSpouseId = $spouseApplicationBySourceId[$application->id] ?? null; @endphp @if ($sourceSpouseId)<div class="ma-spouse-pair">Spouse application for #{{ $sourceSpouseId }}</div>@elseif ($pairedSpouseId)<div class="ma-spouse-pair">Spouse application #{{ $pairedSpouseId }}</div>@endif @if (isset($spouseRequestByApplicationId[$application->id])) @php $spouseRequest = $spouseRequestByApplicationId[$application->id]; @endphp <div class="ma-spouse-pair">Selected spouse: {{ $spouseMemberNames[$application->id] ?? 'Member' }} · {{ str($spouseRequest->status)->replace('_', ' ')->title() }}</div>@endif</td><td>{{ $unitNames[$rowUnit] ?? 'Not assigned' }}</td><td>{{ $application->membershipType?->name ?? '—' }}</td><td>{{ $application->submitted_at?->format('d M Y, h:i A') ?? '—' }}</td><td><span class="{{ $statusClasses[$rowStatus] ?? 'ma-status ma-status-withdrawn' }}">{{ str($rowStatus)->replace('_', ' ')->title() }}</span></td><td style="text-align:right"><button type="button" class="ma-btn" wire:click="selectApplication({{ $application->id }})" wire:loading.attr="disabled"><span aria-hidden="true">↗</span> Review</button></td></tr>
                    @empty <tr><td colspan="6" style="padding:28px;text-align:center;color:var(--muted,#68758b)">No membership applications match these filters.</td></tr> @endforelse
                </tbody></table></div>
            </div>

            <div class="ma-mobile-list" style="margin-top:16px">
                @forelse ($applications as $application)
                    @php $memberData = data_get($application->data, 'member', []); $applicantName = trim(implode(' ', array_filter([data_get($memberData, 'first_name'), data_get($memberData, 'middle_name'), data_get($memberData, 'last_name')]))) ?: ($application->user?->name ?: 'Applicant #' . $application->user_id); $rowStatus = $application->status->value; $rowUnit = data_get($memberData, 'preferred_unit_id') ?: data_get($memberData, 'unit_id'); @endphp
                    <article wire:key="mobile-membership-application-{{ $application->id }}" class="ma-mobile-card"><div class="ma-mobile-top"><div style="min-width:0"><div class="ma-applicant-name">{{ $applicantName }}</div><div class="ma-applicant-email">{{ $application->user?->email ?: 'No email on account' }}</div><div class="ma-muted" style="font-size:10px;margin-top:3px">Application #{{ $application->id }}</div>@php $sourceSpouseId = data_get($application->data, 'member.metadata.spouse_of_application_id'); $pairedSpouseId = $spouseApplicationBySourceId[$application->id] ?? null; @endphp @if ($sourceSpouseId)<div class="ma-spouse-pair">Spouse of #{{ $sourceSpouseId }}</div>@elseif ($pairedSpouseId)<div class="ma-spouse-pair">Spouse application #{{ $pairedSpouseId }}</div>@endif</div><span class="{{ $statusClasses[$rowStatus] ?? 'ma-status ma-status-withdrawn' }}">{{ str($rowStatus)->replace('_', ' ')->title() }}</span></div><div class="ma-mobile-data"><div><div class="ma-data-label">Unit</div><div class="ma-data-value">{{ $unitNames[$rowUnit] ?? 'Not assigned' }}</div></div><div><div class="ma-data-label">Membership type</div><div class="ma-data-value">{{ $application->membershipType?->name ?? '—' }}</div></div><div><div class="ma-data-label">Submitted</div><div class="ma-data-value">{{ $application->submitted_at?->format('d M Y, h:i A') ?? '—' }}</div></div></div><button type="button" class="ma-btn ma-btn-primary ma-btn-full" wire:click="selectApplication({{ $application->id }})" wire:loading.attr="disabled"><span aria-hidden="true">↗</span> Review application</button></article>
                @empty <p class="ma-muted" style="padding:24px;text-align:center">No membership applications match these filters.</p> @endforelse
            </div>
            <div class="ma-pagination">{{ $applications->links() }}</div>
        </section>
    @endif

    @if ($paymentInvoiceOpen && $selectedApplication && $canReceivePayment)
        @php
            $invoiceMemberData = data_get($selectedApplication->data, 'member', []);
            $invoiceApplicantName = trim(implode(' ', array_filter([data_get($invoiceMemberData, 'first_name'), data_get($invoiceMemberData, 'middle_name'), data_get($invoiceMemberData, 'last_name')]))) ?: ($selectedApplication->user?->name ?: 'Applicant #' . $selectedApplication->user_id);
            $invoiceCurrency = $invoiceObligations->first()?->currency ?? 'INR';
            $invoiceOriginalTotal = 0.0;
            $invoiceDiscountTotal = 0.0;
            $invoicePaidTotal = 0.0;
            $invoicePayableTotal = 0.0;
            foreach ($invoiceObligations as $invoiceObligation) {
                $invoiceOriginalTotal += (float) $invoiceObligation->amount;
                $persistedDiscount = (float) $invoiceObligation->discount_amount;
                $draftDiscount = $persistedDiscount > 0 ? 0.0 : max(0.0, (float) ($discountAmounts[$invoiceObligation->id] ?? 0));
                $invoiceDiscountTotal += $persistedDiscount + $draftDiscount;
                $invoicePaidTotal += (float) $invoiceObligation->paid_amount;
                $invoicePayableTotal += max(0.0, (float) $invoiceObligation->amount - $persistedDiscount - $draftDiscount - (float) $invoiceObligation->paid_amount);
            }
        @endphp
        <div class="ma-invoice-backdrop" role="presentation">
            <section class="ma-invoice-modal" role="dialog" aria-modal="true" aria-labelledby="membership-invoice-title" wire:key="membership-invoice-{{ $selectedApplication->id }}">
                <header class="ma-invoice-head">
                    <div>
                        <div class="ma-eyebrow">{{ $organization->name }}</div>
                        <h2 id="membership-invoice-title" style="margin:5px 0 4px;font-size:22px">Membership Invoice</h2>
                        <p class="ma-muted" style="margin:0;font-size:12px">Application #{{ $selectedApplication->id }} · {{ now()->format('d M Y') }}</p>
                    </div>
                    <button type="button" class="ma-btn" wire:click="closePaymentInvoice" aria-label="Close invoice">Close</button>
                </header>
                <div class="ma-invoice-body">
                    <div class="ma-invoice-summary">
                        <div><div class="ma-data-label">Applicant</div><div class="ma-data-value" style="font-weight:750">{{ $invoiceApplicantName }}</div><div class="ma-muted" style="font-size:11px;margin-top:3px;overflow-wrap:anywhere">{{ $selectedApplication->user?->email ?? 'No email on account' }}</div></div>
                        <div><div class="ma-data-label">Membership Type</div><div class="ma-data-value" style="font-weight:750">{{ $selectedApplication->membershipType?->name ?? 'Not assigned' }}</div></div>
                        <div><div class="ma-data-label">Collection Status</div><div class="ma-data-value" style="font-weight:750">{{ $selectedApplication->status->value === 'pending_payment' ? 'Awaiting Payment' : 'Approved' }}</div></div>
                    </div>

                    @if ($invoiceObligations->isEmpty())
                        <div class="ma-alert" style="margin-bottom:0">No payment items are currently due for this application. Continue to move the application to membership confirmation without recording a payment.</div>
                    @else
                        <div class="ma-table-wrap">
                            <table class="ma-invoice-table">
                                <thead><tr><th style="width:25%">Payment Item</th><th>Due Date</th><th>Original ({{ $invoiceCurrency }})</th><th>Discount ({{ $invoiceCurrency }})</th><th>Payable ({{ $invoiceCurrency }})</th></tr></thead>
                                <tbody>
                                    @foreach ($invoiceObligations as $invoiceObligation)
                                        @php
                                            $persistedDiscount = (float) $invoiceObligation->discount_amount;
                                            $draftDiscount = $persistedDiscount > 0 ? 0.0 : max(0.0, (float) ($discountAmounts[$invoiceObligation->id] ?? 0));
                                            $rowPayable = max(0.0, (float) $invoiceObligation->amount - $persistedDiscount - $draftDiscount - (float) $invoiceObligation->paid_amount);
                                            $canDiscountThisItem = $canDiscountPayment && $invoiceObligation->isPending() && (float) $invoiceObligation->paid_amount <= 0 && $persistedDiscount <= 0;
                                            $savedDiscountReason = $invoiceObligation->discounts->sortByDesc('id')->first()?->reason;
                                        @endphp
                                        <tr wire:key="invoice-item-{{ $invoiceObligation->id }}">
                                            <td><strong>{{ $invoiceObligation->item_title }}</strong>@if ($invoiceObligation->status->value === 'waived')<div class="ma-muted" style="font-size:10px;margin-top:3px">Waived</div>@endif</td>
                                            <td>{{ $invoiceObligation->due_at?->format('d M Y') ?? '—' }}</td>
                                            <td>{{ number_format((float) $invoiceObligation->amount, 2) }}</td>
                                            <td style="min-width:180px">
                                                @if ($canDiscountThisItem)
                                                    <div class="ma-field"><input aria-label="Discount amount for {{ $invoiceObligation->item_title }}" type="number" min="0" max="{{ max(0, (float) $invoiceObligation->amount) }}" step="0.01" wire:model.live.debounce.250ms="discountAmounts.{{ $invoiceObligation->id }}" placeholder="0.00">@error('discountAmounts.' . $invoiceObligation->id)<p class="ma-error">{{ $message }}</p>@enderror</div>
                                                    @if ((float) ($discountAmounts[$invoiceObligation->id] ?? 0) > 0)
                                                        <div class="ma-field" style="margin-top:7px"><input aria-label="Discount reason for {{ $invoiceObligation->item_title }}" wire:model="discountReasons.{{ $invoiceObligation->id }}" maxlength="2000" placeholder="Reason for discount">@error('discountReasons.' . $invoiceObligation->id)<p class="ma-error">{{ $message }}</p>@enderror</div>
                                                    @endif
                                                @elseif ($persistedDiscount > 0)
                                                    <div>{{ number_format($persistedDiscount, 2) }}</div><div class="ma-muted" style="font-size:10px;margin-top:3px">{{ $savedDiscountReason ?: 'Authorized discount' }}</div>
                                                @else
                                                    <span class="ma-muted">—</span>
                                                @endif
                                            </td>
                                            <td style="font-weight:750">{{ number_format($rowPayable, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (! $canDiscountPayment)
                            <p class="ma-muted" style="font-size:11px;margin:10px 0 0">Your role can collect payments but does not have permission to apply discounts.</p>
                        @endif
                    @endif

                    <div class="ma-invoice-total" style="margin-top:18px">
                        <div class="ma-invoice-total-row"><span>Subtotal</span><span>{{ $invoiceCurrency }} {{ number_format($invoiceOriginalTotal, 2) }}</span></div>
                        <div class="ma-invoice-total-row"><span>Discounts</span><span>− {{ $invoiceCurrency }} {{ number_format($invoiceDiscountTotal, 2) }}</span></div>
                        <div class="ma-invoice-total-row"><span>Already Paid</span><span>− {{ $invoiceCurrency }} {{ number_format($invoicePaidTotal, 2) }}</span></div>
                        <div class="ma-invoice-total-row"><span>Balance Due</span><span>{{ $invoiceCurrency }} {{ number_format($invoicePayableTotal, 2) }}</span></div>
                    </div>

                    <div class="ma-invoice-pay-grid">
                        <div class="ma-field"><label for="invoice-payment-method">Payment Method {{ $invoicePayableTotal > 0 ? '(required)' : '' }}</label><select id="invoice-payment-method" wire:model="paymentMethod"><option value="">Choose payment method</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="upi">UPI</option><option value="online">Online Payment</option><option value="other">Other</option></select>@error('paymentMethod')<p class="ma-error">{{ $message }}</p>@enderror</div>
                        <div class="ma-field"><label for="invoice-payment-reference">Transaction / Receipt Reference</label><input id="invoice-payment-reference" wire:model="paymentReference" maxlength="150" placeholder="Bank reference, cheque number, etc.">@error('paymentReference')<p class="ma-error">{{ $message }}</p>@enderror</div>
                    </div>
                    <div class="ma-field" style="margin-top:14px"><label for="invoice-payment-notes">Collection Notes (optional)</label><textarea id="invoice-payment-notes" wire:model="paymentNotes" rows="2" maxlength="2000" placeholder="Additional notes for the payment record"></textarea>@error('paymentNotes')<p class="ma-error">{{ $message }}</p>@enderror</div>
                    <p class="ma-muted" style="font-size:11px;margin:12px 0 0">Collector: {{ auth()->user()?->name ?? 'Authenticated user' }} · The collection timestamp and receipt number are generated by the system.</p>
                    @error('discounts')<p class="ma-error">{{ $message }}</p>@enderror
                    <div class="ma-invoice-actions">
                        <button type="button" class="ma-btn" wire:click="closePaymentInvoice" wire:loading.attr="disabled">Cancel</button>
                        <button type="button" class="ma-btn ma-btn-warning" wire:click="receivePayment" wire:confirm="Confirm that the displayed amount has actually been received?" wire:loading.attr="disabled">{{ $invoicePayableTotal > 0 ? 'Record Payment & Generate Receipt' : 'Continue Without Payment' }}</button>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
