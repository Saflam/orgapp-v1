@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('member.directory') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">&larr; Back to members</a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Member profile</h1>
            <p class="mt-1 text-sm text-slate-500">View member information within your authorized organization and unit scope.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('member.profile.pdf', $memberRecord->id) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export PDF</a>
            @if($canEdit)
                <a href="{{ route('member.profile.edit', $memberRecord->id) }}" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold !text-white hover:bg-emerald-800">Edit Profile</a>
            @endif
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center">
            @if($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="Profile photo of {{ $memberRecord->user?->name }}" class="h-24 w-24 rounded-full object-cover ring-4 ring-slate-100">
            @else
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-slate-100 text-2xl font-bold text-slate-500">{{ strtoupper(mb_substr($memberRecord->user?->name ?? '?', 0, 1)) }}</div>
            @endif
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-slate-900">{{ $memberRecord->user?->name ?? 'Unnamed member' }}</h2>
                <p class="mt-1 text-sm text-slate-500">MID: {{ $memberRecord->membership_number ?: 'Not assigned' }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $memberRecord->unit?->name ?? 'No unit assigned' }}</span>
                    <span class="rounded-full {{ $activeMembership ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }} px-3 py-1 font-medium">{{ $activeMembership ? 'Active member' : 'No active membership' }}</span>
                </div>
            </div>
        </div>
        <nav aria-label="Member profile sections" class="flex gap-2 overflow-x-auto border-b border-slate-200 px-4 py-3 sm:px-6">
            @foreach(['overview'=>'Overview','personal-details'=>'Personal Details','documents'=>'Documents','membership'=>'Membership','payments'=>'Payments','family'=>'Family'] as $target=>$label)
                <a href="#{{ $target }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section id="overview" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Overview</h3>
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Membership ID'=>$memberRecord->membership_number ?: 'Not assigned','Unit'=>$memberRecord->unit?->name ?: 'Not assigned','Date joined'=>$memberRecord->joined_at?->format('d M Y') ?: 'Not recorded','Account name'=>$memberRecord->user?->name ?: 'Not recorded','Email'=>$memberRecord->user?->email ?: 'Not provided','Phone'=>$memberRecord->user?->phone ?: 'Not provided'] as $label=>$value)
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words text-sm text-slate-800">{{ $value }}</dd></div>
            @endforeach
        </div>
    </section>

    <section id="personal-details" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Personal Details</h3>
        @php($details = $memberRecord->user?->details)
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'First name'=>$details?->first_name, 'Middle name'=>$details?->middle_name, 'Last name'=>$details?->last_name,
                'Date of birth'=>$details?->date_of_birth?->format('d M Y'), 'Gender'=>$details?->gender,
                'Blood group'=>$details?->blood_group, 'WhatsApp'=>trim(($details?->whatsapp_calling_code ?? '').' '.($details?->whatsapp ?? '')),
                'Home contact'=>$details?->home_contact, 'Profession'=>$details?->profession,
                'Company'=>$details?->company, 'Company address'=>$details?->company_address
            ] as $label=>$value)
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words text-sm text-slate-800">{{ filled($value) ? $value : 'Not provided' }}</dd></div>
            @endforeach
        </div>
        <h4 class="mt-6 text-sm font-bold text-slate-900">Contact information</h4>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-1 break-words text-sm text-slate-800">{{ $memberRecord->user?->email ?: 'Not provided' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</dt><dd class="mt-1 text-sm text-slate-800">{{ $memberRecord->user?->phone ?: 'Not provided' }}</dd></div>
        </div>
        <h4 class="mt-6 text-sm font-bold text-slate-900">Addresses</h4>
        @forelse($memberRecord->user?->addresses ?? collect() as $address)
            <div class="mt-3 border-b border-slate-100 pb-3 last:border-0">
                <p class="text-sm font-semibold capitalize text-slate-800">{{ str_replace('_', ' ', $address->address_type ?? 'Address') }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ collect([$address->address_line_1, $address->address_line_2, $address->locality, $address->city, $address->state, $address->postal_code, $address->country])->filter()->implode(', ') ?: 'No address details' }}</p>
            </div>
        @empty
            <p class="mt-3 text-sm text-slate-500">No addresses recorded.</p>
        @endforelse

        <h4 class="mt-6 text-sm font-bold text-slate-900">Emergency Contact</h4>
        @forelse($emergencyContacts as $contact)
            <div class="mt-3 rounded-xl border border-slate-200 p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Contact name</dt><dd class="mt-1 text-sm text-slate-800">{{ $contact->contact_name ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Relationship</dt><dd class="mt-1 text-sm capitalize text-slate-800">{{ $contact->relationship ? str_replace('_', ' ', $contact->relationship) : 'Not provided' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</dt><dd class="mt-1 text-sm text-slate-800">{{ $contact->phone_e164 ?: 'Not provided' }}{{ $contact->extension ? ' ext. '.$contact->extension : '' }}</dd></div>
                </div>
                @if($contact->is_primary)
                    <span class="mt-3 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Primary contact</span>
                @endif
                @if(filled($contact->notes))
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $contact->notes }}</p>
                @endif
            </div>
        @empty
            <p class="mt-3 text-sm text-slate-500">No emergency contacts recorded.</p>
        @endforelse
    </section>

    <section id="documents" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Identity Documents</h3>
        <p class="mt-1 text-sm text-slate-500">Identification proofs are private and available only to administrators authorized to view this member profile.</p>
        <div class="mt-4 space-y-3">
            @forelse($identificationDocuments as $identification)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Document type</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $identification->identificationType?->name ?? 'Identification document' }}</dd>
                        </div>
                        @if($identification->expires_at)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Expiry date</dt>
                                <dd class="mt-1 text-sm text-slate-800">{{ $identification->expires_at->format('d M Y') }}</dd>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Proof</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @php($documentSides = ['document' => 'View proof', 'front' => 'View front', 'back' => 'View back'])
                            @foreach($documentSides as $side => $sideLabel)
                                @if($identification->document($side))
                                    <a href="{{ route('member.profile.document', [$memberRecord->id, $identification->id, $side]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $sideLabel }}</a>
                                @endif
                            @endforeach
                        </div>
                        @if(!$identification->document('document') && !$identification->document('front') && !$identification->document('back'))
                            <p class="mt-2 text-sm text-slate-500">No proof uploaded.</p>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">No identity documents recorded.</p>
            @endforelse
        </div>
    </section>

    <section id="membership" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Membership Details</h3>
                <p class="mt-1 text-sm text-slate-500">Membership number, current status, and membership history.</p>
            </div>
            @if($canEdit)
                <a href="{{ route('member.profile.membership.edit', $memberRecord->id) }}"
                   class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold !text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Edit Membership
                </a>
            @endif
        </div>
        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Membership number</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $memberRecord->membership_number ?: 'Not assigned' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Membership type</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $membershipForEdit?->membershipType?->name ?? 'Not available' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ ($currentMembershipStatus ?? '') !== '' ? ucfirst(str_replace('_', ' ', (string) ($currentMembershipStatus ?? ''))) : 'No membership record' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Membership start</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $membershipForEdit?->starts_at?->format('d M Y') ?? 'Not recorded' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Membership end</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $membershipForEdit?->ends_at?->format('d M Y') ?? 'Not recorded' }}</dd>
            </div>
            @if($membershipForEdit?->status_reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status reason</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $membershipForEdit->status_reason }}</dd>
                </div>
            @endif
        </div>
        <h4 class="mt-6 text-sm font-bold text-slate-900">Membership history</h4>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-3 pr-4">Type</th><th class="py-3 pr-4">Status</th><th class="py-3 pr-4">Reason</th><th class="py-3 pr-4">Start</th><th class="py-3">End</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @if($memberRecord->memberships->isNotEmpty())
                    @foreach($memberRecord->memberships->sortByDesc('starts_at') as $membership)
                        <tr>
                            <td class="py-3 pr-4">{{ $membership->membershipType?->name ?? 'Membership' }}</td>
                            <td class="py-3 pr-4">{{ ucfirst(str_replace('_', ' ', is_object($membership->status) && property_exists($membership->status, 'value') ? $membership->status->value : (string) $membership->status)) }}</td>
                            <td class="max-w-xs whitespace-pre-line py-3 pr-4">{{ $membership->status_reason ?: '—' }}</td>
                            <td class="py-3 pr-4">{{ $membership->starts_at?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3">{{ $membership->ends_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="5" class="py-4 text-slate-500">No membership records found. You can still assign a membership number; a membership record must exist before its status can be changed.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </section>

    <section id="payments" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Payments</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-3 pr-4">Payment item</th><th class="py-3 pr-4">Due date</th><th class="py-3 pr-4">Payable</th><th class="py-3 pr-4">Paid</th><th class="py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($paymentObligations as $obligation)
                    <tr><td class="py-3 pr-4">{{ $obligation->item_title ?: 'Membership payment' }}</td><td class="py-3 pr-4">{{ $obligation->due_at?->format('d M Y') ?? '—' }}</td><td class="py-3 pr-4">{{ $obligation->currency }} {{ number_format((float) $obligation->payable_amount, 2) }}</td><td class="py-3 pr-4">{{ $obligation->currency }} {{ number_format((float) $obligation->paid_amount, 2) }}</td><td class="py-3">{{ is_object($obligation->status) && property_exists($obligation->status, 'value') ? ucfirst(str_replace('_', ' ', $obligation->status->value)) : ucfirst(str_replace('_', ' ', (string) $obligation->status)) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-slate-500">No payment obligations recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="family" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Family</h3>
                <p class="mt-1 text-sm text-slate-500">Manage active dependant relationships for this member.</p>
            </div>
            @if($canEdit)
                <button type="button" onclick="toggleDependantForm('add-dependant-form')" class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 4a.75.75 0 0 1 .75.75v4.5h4.5a.75.75 0 0 1 0 1.5h-4.5v4.5a.75.75 0 0 1-1.5 0v-4.5h-4.5a.75.75 0 0 1 0-1.5h4.5v-4.5A.75.75 0 0 1 10 4Z"/></svg>
                    Add dependant
                </button>
            @endif
        </div>

        @if($canEdit)
            <div id="add-dependant-form" class="mt-5 hidden rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                <h4 class="text-sm font-bold text-slate-900">Add dependant</h4>
                <form method="POST" enctype="multipart/form-data" action="{{ route('member.profile.dependants.store', $memberRecord->id) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <div class="sm:col-span-2 lg:col-span-3"><label class="mb-1 block text-sm font-medium text-slate-700">Profile photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2" /><p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP; maximum 5 MB.</p></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">First name *</label><input name="first_name" required maxlength="255" class="w-full rounded-lg border-slate-300 text-sm" value="{{ old('first_name') }}"></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Middle name</label><input name="middle_name" maxlength="255" class="w-full rounded-lg border-slate-300 text-sm" value="{{ old('middle_name') }}"></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Last name</label><input name="last_name" maxlength="255" class="w-full rounded-lg border-slate-300 text-sm" value="{{ old('last_name') }}"></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Date of birth</label><input type="date" name="date_of_birth" max="{{ now()->toDateString() }}" class="w-full rounded-lg border-slate-300 text-sm" value="{{ old('date_of_birth') }}"></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Gender</label><select name="gender" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Select gender</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Relationship *</label><select name="relationship_type" required class="w-full rounded-lg border-slate-300 text-sm"><option value="">Select relationship</option><option value="spouse">Spouse</option><option value="child">Child</option><option value="parent">Parent</option><option value="sibling">Sibling</option><option value="guardian">Guardian</option><option value="other">Other</option></select></div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2"><button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save dependant</button><button type="button" onclick="toggleDependantForm('add-dependant-form')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button></div>
                </form>
            </div>
        @endif

        @if($errors->has('family') || $errors->has('relationship_type'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('family') ?: $errors->first('relationship_type') }}</div>
        @endif

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead><tr class="text-xs uppercase tracking-wide text-slate-500"><th class="py-3 pr-4">Photo</th><th class="py-3 pr-4">Name</th><th class="py-3 pr-4">Relationship</th><th class="py-3 pr-4">Date of birth</th><th class="py-3 pr-4">Member status</th>@if($canEdit)<th class="py-3 text-right">Actions</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($memberRecord->dependants as $dependant)
                    <tr>
                        <td class="py-3 pr-4">
                            @if($dependant->getFirstMedia('profile-photo'))
                                <img src="{{ route('member.profile.dependant-photo', ['member' => $memberRecord->id, 'dependant' => $dependant->id]) }}" alt="{{ trim(($dependant->first_name ?? '').' '.($dependant->last_name ?? '')) ?: 'Dependant' }}" class="h-10 w-10 rounded-full border border-slate-200 object-cover" loading="lazy">
                            @else
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500" aria-label="No photo">{{ strtoupper(substr($dependant->first_name ?: 'D', 0, 1)) }}</span>
                            @endif
                        </td>
                        <td class="py-3 pr-4 font-medium text-slate-800">{{ trim(($dependant->first_name ?? '').' '.($dependant->middle_name ?? '').' '.($dependant->last_name ?? '')) ?: 'Unnamed dependant' }}</td>
                        <td class="py-3 pr-4">{{ ucfirst(str_replace('_', ' ', (string) ($dependant->pivot?->relationship_type ?? 'Family'))) }}</td>
                        <td class="py-3 pr-4">{{ $dependant->date_of_birth?->format('d M Y') ?? '—' }}</td>
                        <td class="py-3 pr-4">{{ $dependant->converted_member_id ? 'Member' : 'Dependant' }}</td>
                        @if($canEdit)
                            <td class="py-3 text-right"><div class="inline-flex items-center justify-end gap-1">
                                @if(!$dependant->converted_member_id)
                                    <button type="button" onclick="toggleDependantForm('edit-dependant-{{ $dependant->id }}')" title="Edit dependant" aria-label="Edit {{ $dependant->first_name }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"><svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M13.586 3.586a2 2 0 0 1 2.828 2.828l-8.9 8.9a2 2 0 0 1-.878.513l-3.1.886.886-3.1a2 2 0 0 1 .513-.878l8.651-8.65Z"/><path d="M4 16h12v1H4z"/></svg></button>
                                    <form method="POST" action="{{ route('member.profile.dependants.destroy', ['member' => $memberRecord->id, 'dependant' => $dependant->id]) }}" onsubmit="return confirm('Remove this dependant from the active family list? The relationship history will be preserved.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove dependant" aria-label="Remove {{ $dependant->first_name }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50"><svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M8.5 2.5a1 1 0 0 0-1 1V4h5v-.5a1 1 0 0 0-1-1h-3ZM4 5h12l-.7 11.1A2 2 0 0 1 13.3 18H6.7a2 2 0 0 1-2-1.9L4 5Zm3 3a.75.75 0 0 1 1.5 0v6a.75.75 0 0 1-1.5 0V8Zm4.5 0a.75.75 0 0 1 1.5 0v6a.75.75 0 0 1-1.5 0V8Z" clip-rule="evenodd"/></svg></button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Managed as member</span>
                                @endif
                            </div></td>
                        @endif
                    </tr>
                    @if($canEdit && !$dependant->converted_member_id)
                        <tr id="edit-dependant-{{ $dependant->id }}" class="hidden bg-slate-50">
                            <td colspan="{{ $canEdit ? 6 : 5 }}" class="p-4">
                                <h4 class="text-sm font-bold text-slate-900">Edit {{ $dependant->first_name }}</h4>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('member.profile.dependants.update', ['member' => $memberRecord->id, 'dependant' => $dependant->id]) }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @csrf @method('PUT')
                                    <div class="sm:col-span-2 lg:col-span-3"><label class="mb-1 block text-sm font-medium text-slate-700">Profile photo</label><div class="mb-2 flex items-center gap-3">
                                        @if($dependant->getFirstMedia('profile-photo'))
                                            <img src="{{ route('member.profile.dependant-photo', ['member' => $memberRecord->id, 'dependant' => $dependant->id]) }}" alt="{{ $dependant->first_name }} profile photo" class="h-14 w-14 rounded-full object-cover ring-1 ring-slate-200" />
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-500">{{ strtoupper(substr((string) $dependant->first_name, 0, 1)) }}</div>
                                        @endif
                                        <span class="text-xs text-slate-500">Choose a new photo to replace the current one.</span>
                                    </div><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2" /><p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP; maximum 5 MB.</p></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">First name *</label><input name="first_name" required maxlength="255" value="{{ $dependant->first_name }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Middle name</label><input name="middle_name" maxlength="255" value="{{ $dependant->middle_name }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Last name</label><input name="last_name" maxlength="255" value="{{ $dependant->last_name }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Date of birth</label><input type="date" name="date_of_birth" max="{{ now()->toDateString() }}" value="{{ $dependant->date_of_birth?->format('Y-m-d') }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Gender</label><select name="gender" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Select gender</option><option value="female" @selected($dependant->gender === 'female')>Female</option><option value="male" @selected($dependant->gender === 'male')>Male</option><option value="other" @selected($dependant->gender === 'other')>Other</option></select></div>
                                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Relationship *</label><select name="relationship_type" required class="w-full rounded-lg border-slate-300 text-sm">@foreach(['spouse'=>'Spouse','child'=>'Child','parent'=>'Parent','sibling'=>'Sibling','guardian'=>'Guardian','other'=>'Other'] as $value=>$label)<option value="{{ $value }}" @selected($dependant->pivot?->relationship_type === $value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2"><button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save changes</button><button type="button" onclick="toggleDependantForm('edit-dependant-{{ $dependant->id }}')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button></div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canEdit ? 6 : 5 }}" class="py-4 text-slate-500">No active family members recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        function toggleDependantForm(id) {
            const form = document.getElementById(id);
            if (form) form.classList.toggle('hidden');
        }
    </script>

    <section id="membership-application" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Membership Application</h3>
        @forelse($applications as $application)
            <article class="mt-4 rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <div><h4 class="font-semibold text-slate-800">{{ $application->membershipType?->name ?? 'Membership application' }}</h4><p class="mt-1 text-xs text-slate-500">Submitted {{ $application->submitted_at?->format('d M Y, h:i A') ?? 'date not recorded' }}</p></div>
                    <span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $application->status?->value ?? $application->status ?? 'Unknown' }}</span>
                </div>
                @if($application->statusHistories->isNotEmpty())
                    <div class="mt-5">
                        <h5 class="mb-4 text-sm font-bold text-slate-800">Review timeline</h5>
                        <ol class="space-y-5">
                            @foreach($application->statusHistories->sortBy('created_at') as $history)
                                <li class="relative pl-6">
                                    <span aria-hidden="true" class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full bg-blue-600 ring-4 ring-white"></span>
                                    <span aria-hidden="true" class="absolute left-[4px] top-4 h-[calc(100%+0.75rem)] w-px bg-slate-200"></span>
                                    <p class="text-xs font-semibold leading-5 text-slate-800">{{ ucfirst(str_replace('_', ' ', is_object($history->to_status ?? $history->status ?? null) ? (($history->to_status ?? $history->status)->value ?? 'updated') : ($history->to_status ?? $history->status ?? 'updated'))) }} by {{ $history->actor?->name ?? 'Administrator' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $history->created_at?->format('d M Y \\a\\t h:i A') ?? 'Time not recorded' }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">No review timeline entries recorded.</p>
                @endif
            </article>
        @empty
            <p class="mt-3 text-sm text-slate-500">No membership applications found for this member.</p>
        @endforelse
    </section>

    @foreach($moduleSections as $section)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h3 class="text-base font-bold text-slate-900">{{ $section['title'] }}</h3>
                @if($canEdit && !empty($section['edit_view']))
                    <button type="button"
                            onclick="toggleDependantForm('module-edit-{{ \Illuminate\Support\Str::slug($section['module']) }}')"
                            class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Edit {{ $section['title'] }}
                    </button>
                @endif
            </div>
            <div class="mt-4">
                @include($section['view'], array_merge($section['data'], ['data' => $section['data'], 'member' => $memberRecord, 'organization' => $organization, 'module' => $section['module']]))
            </div>
            @if($canEdit && !empty($section['edit_view']))
                <div id="module-edit-{{ \Illuminate\Support\Str::slug($section['module']) }}" class="mt-5 hidden rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                    <h4 class="text-sm font-bold text-slate-900">Edit {{ $section['title'] }}</h4>
                    <form method="POST"
                          action="{{ route('member.profile.module.update', ['member' => $memberRecord->id, 'module' => $section['module']]) }}"
                          class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        @include($section['edit_view'], array_merge($section['data'], ['data' => $section['data'], 'member' => $memberRecord, 'organization' => $organization, 'module' => $section['module']]))
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="toggleDependantForm('module-edit-{{ \Illuminate\Support\Str::slug($section['module']) }}')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save details</button>
                        </div>
                    </form>
                </div>
            @endif
        </section>
    @endforeach

    <section id="admin-notes" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h3 class="text-base font-bold text-slate-900">Admin Notes</h3><p class="mt-1 text-sm text-slate-500">Internal notes visible to administrators authorized to view this member profile.</p></div>
            @if($canEdit)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Internal · not visible to members</span>@endif
        </div>
        @if($canEdit)
            <form method="POST" action="{{ route('member.profile.notes.store', $memberRecord->id) }}" class="mt-5 space-y-3">
                @csrf
                <label for="admin-note-body" class="block text-sm font-medium text-slate-700">Add a note</label>
                <textarea id="admin-note-body" name="body" rows="3" maxlength="5000" required class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600" placeholder="Enter an internal note about this member..."></textarea>
                @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex justify-end"><button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Add note</button></div>
            </form>
        @endif
        @if(session('status'))<p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>@endif
        <div class="mt-5 space-y-4">
            @forelse($adminNotes as $note)
                <article class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-slate-800">{{ $note->author?->name ?? 'Former administrator' }}</p><time class="text-xs text-slate-500">{{ $note->created_at?->format('d M Y, h:i A') }}</time></div><p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $note->body }}</p></article>
            @empty
                <p class="text-sm text-slate-500">No admin notes have been added.</p>
            @endforelse
        </div>
    </section>
</div>
@endcomponent
