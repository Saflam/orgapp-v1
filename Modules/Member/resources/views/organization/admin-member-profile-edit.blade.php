@component('core::layouts.organization-admin', ['layoutOrganization' => $organization])
@php
    $user = $memberRecord->user;
    $details = $user?->details;
    $addresses = old('addresses', $user?->addresses?->toArray() ?? []);
    if (count($addresses) === 0) {
        $addresses = [['address_type' => 'home', 'address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '', 'is_primary' => true]];
    }
    $phoneNumbers = old('phone_numbers', $user?->phoneNumbers?->map(fn ($phone) => [
        'id' => $phone->id, 'type' => $phone->type, 'label' => $phone->label,
        'phone' => $phone->phone_e164, 'country_iso2' => $phone->country_iso2 ?: 'IN',
        'extension' => $phone->extension, 'is_primary' => $phone->is_primary,
    ])->toArray() ?? []);
    $emergencyContacts = old('emergency_contacts', $user?->emergencyContacts?->map(fn ($contact) => [
        'id' => $contact->id, 'contact_name' => $contact->contact_name, 'relationship' => $contact->relationship,
        'phone' => $contact->phone_e164, 'country_iso2' => $contact->country_iso2 ?: 'IN',
        'extension' => $contact->extension, 'is_primary' => $contact->is_primary, 'notes' => $contact->notes,
    ])->toArray() ?? []);
    $countries = ['IN'=>'India','US'=>'United States','GB'=>'United Kingdom','AE'=>'United Arab Emirates','SA'=>'Saudi Arabia','QA'=>'Qatar','OM'=>'Oman','KW'=>'Kuwait','BH'=>'Bahrain','SG'=>'Singapore','MY'=>'Malaysia','LK'=>'Sri Lanka','BD'=>'Bangladesh','NP'=>'Nepal','PK'=>'Pakistan','AU'=>'Australia','CA'=>'Canada','DE'=>'Germany','FR'=>'France','ZA'=>'South Africa'];
    $callingCodes = ['IN'=>'91','US'=>'1','GB'=>'44','AE'=>'971','SA'=>'966','QA'=>'974','OM'=>'968','KW'=>'965','BH'=>'973','SG'=>'65','MY'=>'60','LK'=>'94','BD'=>'880','NP'=>'977','PK'=>'92','AU'=>'61','CA'=>'1','DE'=>'49','FR'=>'33','ZA'=>'27'];
    $nationalNumber = static function ($number, $iso) use ($callingCodes): string {
        $number = trim((string) $number);
        $callingCode = (string) ($callingCodes[strtoupper((string) $iso)] ?? '');
        if ($number !== '' && str_starts_with($number, '+') && $callingCode !== '') {
            $digits = preg_replace('/\\D+/', '', $number);
            if (str_starts_with($digits, $callingCode)) {
                return substr($digits, strlen($callingCode));
            }
        }

        return $number;
    };
    $phoneCountry = old('phone_country_iso2', $user?->phoneNumbers?->firstWhere('is_primary', true)?->country_iso2 ?: 'IN');
    $whatsappCountry = old('whatsapp_country_iso2', $phoneCountry);
@endphp
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('member.profile', $memberRecord->id) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">&larr; Back to member profile</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Edit member profile</h1>
        <p class="mt-1 text-sm text-slate-500">Update the member’s core personal and contact details. Membership, dependant and Saradhi details are managed separately.</p>
    </div>

    @if (session('status'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('member.profile.update', $memberRecord->id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Profile photo</h2>
            <div class="mt-4 flex flex-wrap items-center gap-5">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                    <img id="profilePhotoThumbnail" src="{{ $profilePhotoUrl ?: '' }}" alt="Profile photo preview" class="h-full w-full object-cover {{ $profilePhotoUrl ? '' : 'hidden' }}">
                    <span id="profilePhotoInitial" class="text-2xl font-bold text-slate-500 {{ $profilePhotoUrl ? 'hidden' : '' }}">{{ strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="photo" class="block text-sm font-medium text-slate-700">Choose a new photo</label>
                    <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP; maximum 5 MB. You can crop the photo before saving. Leave empty to keep the current photo.</p>
                    @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p id="photoCropError" class="mt-2 hidden text-sm text-red-600" role="alert"></p>
                </div>
            </div>
        </section>

        <div id="photoCropModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="photoCropTitle">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="photoCropTitle" class="text-lg font-bold text-slate-900">Crop profile photo</h2>
                        <p class="mt-1 text-sm text-slate-500">Drag the image to reposition it, then adjust zoom.</p>
                    </div>
                    <button type="button" id="photoCropCancelTop" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cancel crop">✕</button>
                </div>
                <div class="mt-4 flex justify-center">
                    <canvas id="photoCropCanvas" width="280" height="280" class="h-[280px] w-[280px] max-w-full touch-none cursor-move rounded-full bg-slate-100"></canvas>
                </div>
                <div class="mt-4">
                    <label for="photoCropZoom" class="flex items-center justify-between text-sm font-medium text-slate-700">
                        <span>Zoom</span><span id="photoCropZoomValue">1.0×</span>
                    </label>
                    <input id="photoCropZoom" type="range" min="1" max="3" step="0.05" value="1" class="mt-2 w-full">
                </div>
                <p class="mt-2 text-xs text-slate-500">The circular area shows how the photo will appear in the profile.</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" id="photoCropCancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="button" id="photoCropApply" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Use cropped photo</button>
                </div>
            </div>
        </div>

        <script>
        (() => {
            const input = document.getElementById('photo');
            const modal = document.getElementById('photoCropModal');
            const canvas = document.getElementById('photoCropCanvas');
            const ctx = canvas.getContext('2d');
            const zoomInput = document.getElementById('photoCropZoom');
            const zoomValue = document.getElementById('photoCropZoomValue');
            const applyButton = document.getElementById('photoCropApply');
            const cancelButtons = [
                document.getElementById('photoCropCancel'),
                document.getElementById('photoCropCancelTop')
            ];
            const thumbnail = document.getElementById('profilePhotoThumbnail');
            const initial = document.getElementById('profilePhotoInitial');
            const error = document.getElementById('photoCropError');
            const form = input?.form;
            if (!input || !modal || !canvas || !ctx || !form) return;

            const size = canvas.width;
            let sourceImage = null;
            let sourceFile = null;
            let scale = 1;
            let offsetX = 0;
            let offsetY = 0;
            let baseScale = 1;
            let drag = null;
            let previousObjectUrl = null;
            let thumbnailObjectUrl = null;

            function clampOffsets() {
                const width = sourceImage.naturalWidth * scale;
                const height = sourceImage.naturalHeight * scale;
                offsetX = Math.min(0, Math.max(size - width, offsetX));
                offsetY = Math.min(0, Math.max(size - height, offsetY));
            }

            function drawCrop() {
                if (!sourceImage) return;
                clampOffsets();
                ctx.clearRect(0, 0, size, size);
                ctx.fillStyle = '#f1f5f9';
                ctx.fillRect(0, 0, size, size);
                ctx.drawImage(sourceImage, offsetX, offsetY,
                    sourceImage.naturalWidth * scale,
                    sourceImage.naturalHeight * scale);
                zoomValue.textContent = `${Number(zoomInput.value).toFixed(2).replace(/0$/, '').replace(/\.$/, '')}×`;
            }

            function openCrop(file) {
                sourceFile = file;
                if (previousObjectUrl) URL.revokeObjectURL(previousObjectUrl);
                previousObjectUrl = URL.createObjectURL(file);
                const image = new Image();
                image.onload = () => {
                    sourceImage = image;
                    baseScale = Math.max(size / image.naturalWidth, size / image.naturalHeight);
                    zoomInput.value = '1';
                    scale = baseScale;
                    offsetX = (size - image.naturalWidth * scale) / 2;
                    offsetY = (size - image.naturalHeight * scale) / 2;
                    error.classList.add('hidden');
                    drawCrop();
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                };
                image.onerror = () => {
                    error.textContent = 'This image could not be opened. Please choose another image.';
                    error.classList.remove('hidden');
                    input.value = '';
                };
                image.src = previousObjectUrl;
            }

            function closeCrop() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                sourceImage = null;
                drag = null;
            }

            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                    error.textContent = 'Choose a JPG, PNG or WebP image up to 5 MB.';
                    error.classList.remove('hidden');
                    input.value = '';
                    return;
                }
                openCrop(file);
            });

            zoomInput.addEventListener('input', () => {
                if (!sourceImage) return;
                const oldScale = scale;
                const centerX = size / 2;
                const centerY = size / 2;
                const ratio = Number(zoomInput.value) / (oldScale / baseScale);
                scale = baseScale * Number(zoomInput.value);
                offsetX = centerX - (centerX - offsetX) * (scale / oldScale);
                offsetY = centerY - (centerY - offsetY) * (scale / oldScale);
                drawCrop();
            });

            canvas.addEventListener('pointerdown', (event) => {
                if (!sourceImage) return;
                canvas.setPointerCapture(event.pointerId);
                drag = { x: event.clientX, y: event.clientY, offsetX, offsetY };
            });
            canvas.addEventListener('pointermove', (event) => {
                if (!drag) return;
                offsetX = drag.offsetX + event.clientX - drag.x;
                offsetY = drag.offsetY + event.clientY - drag.y;
                drawCrop();
            });
            canvas.addEventListener('pointerup', () => { drag = null; });
            canvas.addEventListener('pointercancel', () => { drag = null; });

            cancelButtons.forEach(button => button?.addEventListener('click', () => {
                closeCrop();
                input.value = '';
            }));

            applyButton.addEventListener('click', () => {
                if (!sourceImage || !sourceFile) return;
                const outputSize = 512;
                const output = document.createElement('canvas');
                output.width = outputSize;
                output.height = outputSize;
                const outputContext = output.getContext('2d');
                const factor = outputSize / size;
                outputContext.drawImage(
                    sourceImage,
                    offsetX * factor,
                    offsetY * factor,
                    sourceImage.naturalWidth * scale * factor,
                    sourceImage.naturalHeight * scale * factor
                );
                output.toBlob((blob) => {
                    if (!blob) {
                        error.textContent = 'Unable to crop this image. Please try another photo.';
                        error.classList.remove('hidden');
                        return;
                    }
                    const croppedFile = new File([blob], (sourceFile.name.replace(/\.[^.]+$/, '') || 'profile-photo') + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    input.files = transfer.files;

                    if (thumbnailObjectUrl) URL.revokeObjectURL(thumbnailObjectUrl);
                    thumbnailObjectUrl = URL.createObjectURL(blob);
                    thumbnail.src = thumbnailObjectUrl;
                    thumbnail.classList.remove('hidden');
                    initial.classList.add('hidden');
                    closeCrop();
                }, 'image/jpeg', 0.92);
            });

            form.addEventListener('submit', (event) => {
                if (modal && !modal.classList.contains('hidden')) {
                    event.preventDefault();
                }
            });
        })();
        </script>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Personal details</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div><label for="name" class="block text-sm font-medium text-slate-700">Full name *</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $user?->name) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="email" class="block text-sm font-medium text-slate-700">Email *</label><input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $user?->email) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="first_name" class="block text-sm font-medium text-slate-700">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $details?->first_name) }}" maxlength="255" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label for="middle_name" class="block text-sm font-medium text-slate-700">Middle name</label><input id="middle_name" name="middle_name" value="{{ old('middle_name', $details?->middle_name) }}" maxlength="255" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label for="last_name" class="block text-sm font-medium text-slate-700">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $details?->last_name) }}" maxlength="255" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label for="gender" class="block text-sm font-medium text-slate-700">Gender</label><select id="gender" name="gender" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Not provided</option>@foreach (['female'=>'Female','male'=>'Male','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $value=>$label)<option value="{{ $value }}" @selected(old('gender', $details?->gender) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="date_of_birth" class="block text-sm font-medium text-slate-700">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth', $details?->date_of_birth?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="blood_group" class="block text-sm font-medium text-slate-700">Blood group</label><select id="blood_group" name="blood_group" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Not provided</option>@foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $group)<option value="{{ $group }}" @selected(old('blood_group', $details?->blood_group) === $group)>{{ $group }}</option>@endforeach</select></div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Contact details</h2>
            <div class="space-y-5">
                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700">Phone *</label>
                    <div class="mt-1 flex gap-2">
                        <select id="phone_country_iso2" name="phone_country_iso2" aria-label="Phone country code" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 10rem; min-width: 10rem; max-width: 10rem; flex: 0 0 10rem; box-sizing: border-box;">
                            @foreach($countries as $iso=>$country)<option value="{{ $iso }}" @selected(strtoupper((string)$phoneCountry) === $iso)>+{{ $callingCodes[$iso] ?? '' }}</option>@endforeach
                        </select>
                        <input id="phone" name="phone" type="tel" required maxlength="50" value="{{ $nationalNumber(old('phone', $user?->phone), $phoneCountry) }}" placeholder="Phone number" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 0; min-width: 0; flex: 1 1 0%; box-sizing: border-box;">
                    </div>
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-slate-700">WhatsApp</label>
                    <div class="mt-1 flex gap-2">
                        <select id="whatsapp_country_iso2" name="whatsapp_country_iso2" aria-label="WhatsApp country code" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 10rem; min-width: 10rem; max-width: 10rem; flex: 0 0 10rem; box-sizing: border-box;">
                            @foreach($countries as $iso=>$country)<option value="{{ $iso }}" @selected(strtoupper((string)$whatsappCountry) === $iso)>+{{ $callingCodes[$iso] ?? '' }}</option>@endforeach
                        </select>
                        <input id="whatsapp" name="whatsapp" type="tel" maxlength="50" value="{{ $nationalNumber(old('whatsapp', $details?->whatsapp), $whatsappCountry) }}" placeholder="WhatsApp number" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 0; min-width: 0; flex: 1 1 0%; box-sizing: border-box;">
                    </div>
                    @error('whatsapp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="max-w-md">
                    <label for="home_contact" class="block text-sm font-medium text-slate-700">Home contact</label>
                    <input id="home_contact" name="home_contact" type="tel" maxlength="50" value="{{ old('home_contact', $details?->home_contact) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('home_contact')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Profession and company</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div><label for="profession" class="block text-sm font-medium text-slate-700">Profession</label><input id="profession" name="profession" maxlength="255" value="{{ old('profession', $details?->profession) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label for="company" class="block text-sm font-medium text-slate-700">Company</label><input id="company" name="company" maxlength="255" value="{{ old('company', $details?->company) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div class="md:col-span-2"><label for="company_address" class="block text-sm font-medium text-slate-700">Company address</label><textarea id="company_address" name="company_address" rows="3" maxlength="5000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('company_address', $details?->company_address) }}</textarea></div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-bold text-slate-900">Addresses</h2><p class="mt-1 text-sm text-slate-500">Add more than one address and choose one primary address.</p></div><button type="button" id="add-address" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add address</button></div>
            <div id="addresses-list" class="space-y-4">
                @foreach($addresses as $i=>$address)
                    @php
                        // Determine required address types from the persisted record, not old input,
                        // so a failed submission cannot make a mandatory type editable.
                        $persistedAddress = !empty($address['id'])
                            ? $user?->addresses?->firstWhere('id', (int) $address['id'])
                            : null;
                        $storedAddressType = (string) ($persistedAddress?->address_type ?? $address['address_type'] ?? 'home');
                        $normalizedAddressType = strtolower(str_replace([' ', '-'], '_', trim($storedAddressType)));
                        $isPermanentAddress = in_array($normalizedAddressType, ['permanent', 'permanent_address'], true);
                        $isLocalAddress = in_array($normalizedAddressType, ['local', 'local_address', 'residential', 'residential_address', 'current', 'current_address'], true);
                        $isRequiredAddress = $isPermanentAddress || $isLocalAddress;
                        $fixedAddressType = $isPermanentAddress ? 'permanent' : ($isLocalAddress ? 'local' : $storedAddressType);
                        $fixedAddressLabel = $isPermanentAddress ? 'Permanent' : 'Local';
                    @endphp
                    <div class="address-row rounded-xl border border-slate-200 p-4">
                        @if(!empty($address['id']))<input type="hidden" name="addresses[{{ $i }}][id]" value="{{ $address['id'] }}">@endif
                        <input type="hidden" name="addresses[{{ $i }}][_delete]" value="0" class="delete-flag">
                        <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold text-slate-800">Address <span class="address-number">{{ $loop->iteration }}</span></h3>@if($isRequiredAddress)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Required</span>@else<button type="button" class="remove-row text-sm font-medium text-red-600">Remove</button>@endif</div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div><label class="block text-sm font-medium text-slate-700">Address type *</label>@if($isRequiredAddress)<input type="hidden" name="addresses[{{ $i }}][address_type]" value="{{ $storedAddressType }}"><div class="mt-1 flex min-h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $fixedAddressLabel }} address <span class="ml-2 text-xs text-slate-500">(cannot be changed)</span></div>@else<select name="addresses[{{ $i }}][address_type]" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach(['home'=>'Home','local'=>'Local','permanent'=>'Permanent','work'=>'Work','other'=>'Other'] as $v=>$l)<option value="{{ $v }}" @selected(($address['address_type'] ?? 'home') === $v)>{{ $l }}</option>@endforeach</select>@endif</div>
                            <div><label class="block text-sm font-medium text-slate-700">Address line 1 *</label><input name="addresses[{{ $i }}][address_line_1]" required maxlength="255" value="{{ $address['address_line_1'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Address line 2</label><input name="addresses[{{ $i }}][address_line_2]" maxlength="255" value="{{ $address['address_line_2'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Locality</label><input name="addresses[{{ $i }}][locality]" maxlength="255" value="{{ $address['locality'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">City</label><input name="addresses[{{ $i }}][city]" maxlength="100" value="{{ $address['city'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">State</label><input name="addresses[{{ $i }}][state]" maxlength="100" value="{{ $address['state'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Postal code</label><input name="addresses[{{ $i }}][postal_code]" maxlength="20" value="{{ $address['postal_code'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Country</label><input name="addresses[{{ $i }}][country]" maxlength="100" value="{{ $address['country'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="addresses[{{ $i }}][is_primary]" value="1" @checked(filter_var($address['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)) class="rounded border-slate-300"> Primary address</label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('addresses')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-bold text-slate-900">Emergency contacts</h2><p class="mt-1 text-sm text-slate-500">Add or update emergency contacts.</p></div><button type="button" id="add-emergency" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add emergency contact</button></div>
            <div id="emergency-list" class="space-y-4">
                @foreach($emergencyContacts as $i=>$contact)
                    <div class="emergency-row rounded-xl border border-slate-200 p-4">
                        @if(!empty($contact['id']))<input type="hidden" name="emergency_contacts[{{ $i }}][id]" value="{{ $contact['id'] }}">@endif
                        <input type="hidden" name="emergency_contacts[{{ $i }}][_delete]" value="0" class="delete-flag">
                        <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold text-slate-800">Emergency contact <span class="contact-number">{{ $loop->iteration }}</span></h3></div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div><label class="block text-sm font-medium text-slate-700">Name *</label><input name="emergency_contacts[{{ $i }}][contact_name]" required maxlength="255" value="{{ $contact['contact_name'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Relationship</label><input name="emergency_contacts[{{ $i }}][relationship]" maxlength="100" value="{{ $contact['relationship'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Phone *</label><div class="mt-1 flex gap-2"><select name="emergency_contacts[{{ $i }}][country_iso2]" aria-label="Emergency contact country code" required class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 10rem; min-width: 10rem; max-width: 10rem; flex: 0 0 10rem; box-sizing: border-box;">@foreach($countries as $iso=>$country)<option value="{{ $iso }}" @selected(strtoupper((string)($contact['country_iso2'] ?? 'IN')) === $iso)>+{{ $callingCodes[$iso] ?? '' }}</option>@endforeach</select><input name="emergency_contacts[{{ $i }}][phone]" type="tel" required maxlength="50" value="{{ $nationalNumber($contact['phone'] ?? '', $contact['country_iso2'] ?? 'IN') }}" placeholder="Phone number" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="width: 0; min-width: 0; flex: 1 1 0%; box-sizing: border-box;"></div></div>
                            <div><label class="block text-sm font-medium text-slate-700">Extension</label><input name="emergency_contacts[{{ $i }}][extension]" maxlength="20" value="{{ $contact['extension'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-700">Notes</label><input name="emergency_contacts[{{ $i }}][notes]" maxlength="2000" value="{{ $contact['notes'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="emergency_contacts[{{ $i }}][is_primary]" value="1" @checked(filter_var($contact['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)) class="rounded border-slate-300"> Primary emergency contact</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <a href="{{ route('member.profile', $memberRecord->id) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save changes</button>
        </div>
    </form>
</div>
<script>
(() => {
    const addressList = document.getElementById('addresses-list');
    const emergencyList = document.getElementById('emergency-list');
    const countryOptions = @json($countries);\n    const callingCodes = @json($callingCodes);
    const renumber = (list, rowSelector, prefix, numberClass) => {
        [...list.querySelectorAll(rowSelector)].forEach((row, index) => {
            row.querySelector(`.${numberClass}`).textContent = index + 1;
        });
    };
    const markRemove = (row) => {
        const flag = row.querySelector('.delete-flag');
        if (flag) {
            flag.value = '1';
            row.querySelectorAll('input,select,textarea').forEach((field) => {
                if (field !== flag && field.type !== 'hidden') field.disabled = true;
            });
            row.classList.add('hidden');
        } else {
            row.remove();
        }
    };
    document.querySelectorAll('.remove-row').forEach(button => button.addEventListener('click', () => markRemove(button.closest('.address-row, .emergency-row'))));
    document.getElementById('add-address').addEventListener('click', () => {
        const index = Math.max(-1, ...[...addressList.querySelectorAll('[name^="addresses["]')].map(el => Number((el.name.match(/^addresses\[(\d+)\]/)||[])[1] ?? -1))) + 1;
        const row = document.createElement('div');
        row.className = 'address-row rounded-xl border border-slate-200 p-4';
        row.innerHTML = `<input type="hidden" name="addresses[${index}][_delete]" value="0" class="delete-flag"><div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Address <span class="address-number"></span></h3><button type="button" class="remove-row text-sm font-medium text-red-600">Remove</button></div><div class="grid gap-4 md:grid-cols-2"><div><label class="block text-sm font-medium">Address type *</label><select name="addresses[${index}][address_type]" required class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"><option value="home">Home</option><option value="local">Local</option><option value="permanent">Permanent</option><option value="work">Work</option><option value="other">Other</option></select></div>${[['address_line_1','Address line 1 *',255],['address_line_2','Address line 2',255],['locality','Locality',255],['city','City',100],['state','State',100],['postal_code','Postal code',20],['country','Country',100]].map(([name,label,max])=>`<div><label class="block text-sm font-medium">${label}</label><input name="addresses[${index}][${name}]" maxlength="${max}" ${name==='address_line_1'?'required':''} class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"></div>`).join('')}<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="addresses[${index}][is_primary]" value="1"> Primary address</label></div>`;
        row.querySelector('.remove-row').addEventListener('click', () => markRemove(row));
        addressList.appendChild(row); renumber(addressList,'.address-row','addresses','address-number');
    });
    document.getElementById('add-emergency').addEventListener('click', () => {
        const index = Math.max(-1, ...[...emergencyList.querySelectorAll('[name^="emergency_contacts["]')].map(el => Number((el.name.match(/^emergency_contacts\[(\d+)\]/)||[])[1] ?? -1))) + 1;
        const options = Object.entries(countryOptions).map(([iso,name])=>`<option value="${iso}" ${iso==='IN'?'selected':''}>+${callingCodes[iso] ?? ''}</option>`).join('');
        const row = document.createElement('div'); row.className='emergency-row rounded-xl border border-slate-200 p-4';
        row.innerHTML = `<input type="hidden" name="emergency_contacts[${index}][_delete]" value="0" class="delete-flag"><div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Emergency contact <span class="contact-number"></span></h3></div><div class="grid gap-4 md:grid-cols-2"><div><label class="block text-sm font-medium">Name *</label><input name="emergency_contacts[${index}][contact_name]" required maxlength="255" class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"></div><div><label class="block text-sm font-medium">Relationship</label><input name="emergency_contacts[${index}][relationship]" maxlength="100" class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"></div><div><label class="block text-sm font-medium">Phone *</label><div class="mt-1 flex gap-2"><select name="emergency_contacts[${index}][country_iso2]" aria-label="Emergency contact country code" required class="shrink-0 rounded-lg border px-3 py-2 text-sm" style="width: 10rem; min-width: 10rem; max-width: 10rem; flex: 0 0 10rem; box-sizing: border-box;">${options}</select><input name="emergency_contacts[${index}][phone]" type="tel" required maxlength="50" placeholder="Phone number" class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm" style="width: 0; min-width: 0; flex: 1 1 0%; box-sizing: border-box;"></div></div><div><label class="block text-sm font-medium">Extension</label><input name="emergency_contacts[${index}][extension]" maxlength="20" class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"></div><div><label class="block text-sm font-medium">Notes</label><input name="emergency_contacts[${index}][notes]" maxlength="2000" class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"></div><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="emergency_contacts[${index}][is_primary]" value="1"> Primary emergency contact</label></div>`;
        emergencyList.appendChild(row); renumber(emergencyList,'.emergency-row','emergency_contacts','contact-number');
    });
})();
</script>
@endcomponent
