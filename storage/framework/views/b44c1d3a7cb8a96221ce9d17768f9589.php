<?php $__env->startSection('title', 'Member Profile — ' . $organization->name); ?>
<?php $__env->startPush('head'); ?>
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-slate-50" style="--org-primary: <?php echo e($primaryColor); ?>">
    <?php echo $__env->make('organization.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="mx-auto max-w-5xl px-4 py-6 sm:px-8 sm:py-10">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?> <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><?php echo e(session('status')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?> <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"><p class="font-semibold">Please correct the following:</p><ul class="mt-1 list-disc pl-5"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="p-5 sm:p-7" style="background: color-mix(in srgb, var(--org-primary) 8%, white)">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <div class="relative h-20 w-20 shrink-0 overflow-visible">
                            <div class="h-20 w-20 overflow-hidden rounded-full border-4 border-white bg-white shadow-sm">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->getFirstMedia('profile-photo')): ?> <img src="<?php echo e(route('organization.member.profile.photo.show')); ?>" alt="Profile photo" class="h-full w-full object-cover"> <?php else: ?> <div class="flex h-full w-full items-center justify-center text-2xl font-bold" style="color:var(--org-primary)"><?php echo e(mb_substr($user->name, 0, 1)); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <form class="absolute -bottom-1 left-1/2 -translate-x-1/2" method="post" enctype="multipart/form-data" action="<?php echo e(route('organization.member.profile.photo')); ?>" id="profile-photo-form"><?php echo csrf_field(); ?>
                                <label title="Change profile photo" aria-label="Change profile photo" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-white text-slate-700 shadow hover:bg-slate-50"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg><input id="profile-photo-input" class="sr-only" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
                            </form>
                        </div>
                        <div class="min-w-0"><h1 class="truncate text-2xl font-bold text-slate-950"><?php echo e($user->name); ?></h1><p class="mt-1 text-sm text-slate-600">Membership no. <?php echo e($member->membership_number ?: 'Not assigned'); ?> · <?php echo e($currentUnit?->name ?: 'No unit assigned'); ?></p></div>
                    </div>
                    <button type="button" data-id-card-url="<?php echo e(route('organization.member.id-card', ['modal' => 1])); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl px-4 py-2 text-sm font-bold text-white shadow-sm sm:self-center" style="background:var(--org-primary)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M6 15c1-2 4-2 5 0M14 9h4M14 13h4"/></svg>View ID Card</button>
                </div>
            </div>
        </section>
        <div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-7"><nav class="mx-auto flex max-w-5xl gap-5 overflow-x-auto" aria-label="Member sections"><a href="#profile" class="whitespace-nowrap border-b-2 py-4 text-sm font-semibold" style="border-color:var(--org-primary);color:var(--org-primary)">Profile</a><a href="#family" class="whitespace-nowrap border-b-2 border-transparent py-4 text-sm font-semibold text-slate-600">Family</a><a href="#payments" class="whitespace-nowrap border-b-2 border-transparent py-4 text-sm font-semibold text-slate-600">Payments</a><a href="#membership-details" class="whitespace-nowrap border-b-2 border-transparent py-4 text-sm font-semibold text-slate-600">Membership</a><a href="#documents" class="whitespace-nowrap border-b-2 border-transparent py-4 text-sm font-semibold text-slate-600">Documents</a></nav></div>
        <section id="profile" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950">Personal information</h2>
                    <p class="mt-1 text-sm text-slate-500">Your personal and contact details.</p>
                </div>
                <button type="button" onclick="toggleProfileEdit('personal-edit', this)" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Edit personal information" title="Edit">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
                </button>
            </div>

            <dl id="personal-summary" class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900"><?php echo e($user->name ?: 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900"><?php echo e($user->email ?: 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of birth</dt><dd class="mt-1 text-sm font-medium text-slate-900"><?php echo e($user->details?->date_of_birth?->format('d M Y') ?? 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</dt><dd class="mt-1 text-sm font-medium text-slate-900"><?php echo e(filled($user->details?->gender) ? str($user->details->gender)->replace('_', ' ')->title() : 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Primary phone</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900"><?php echo e($user->phone ?: 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">WhatsApp</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900"><?php echo e($user->details?->whatsapp ? trim(($user->details?->whatsapp_calling_code ?? '').' '.$user->details->whatsapp) : 'Not recorded'); ?></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Blood group</dt><dd class="mt-1 text-sm font-medium text-slate-900"><?php echo e($user->details?->blood_group ?: 'Not recorded'); ?></dd></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->details?->profession): ?><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profession</dt><dd class="mt-1 text-sm font-medium text-slate-900"><?php echo e($user->details->profession); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->details?->company): ?><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Company</dt><dd class="mt-1 text-sm font-medium text-slate-900"><?php echo e($user->details->company); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </dl>

            <form id="personal-edit" hidden method="post" action="<?php echo e(route('organization.member.profile.update')); ?>" class="mt-6 space-y-5 border-t border-slate-100 pt-5">
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="name" class="mb-1 block text-sm font-semibold text-slate-700">Full name</label><input id="name" name="name" required maxlength="255" value="<?php echo e(old('name', $user->name)); ?>" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-[var(--org-primary)] focus:outline-none focus:ring-2 focus:ring-slate-200"></div>
                    <div><label for="email" class="mb-1 block text-sm font-semibold text-slate-700">Email (cannot be changed here)</label><input id="email" type="email" value="<?php echo e($user->email); ?>" readonly aria-readonly="true" class="min-h-11 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-500"></div>
                    <div><label for="date_of_birth" class="mb-1 block text-sm font-semibold text-slate-700">Date of birth</label><input id="date_of_birth" type="date" name="date_of_birth" value="<?php echo e(old('date_of_birth', optional($user->details?->date_of_birth)->format('Y-m-d'))); ?>" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"></div>
                    <div>
                        <label for="phone" class="mb-1 block text-sm font-semibold text-slate-700">Primary phone</label>
                        <div class="grid grid-cols-[minmax(7rem,0.8fr)_minmax(0,1.2fr)] gap-2">
                            <select name="phone_country_iso2" aria-label="Primary phone country code" class="min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-2 py-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $phoneCountries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($country['iso2']); ?>" <?php if(old('phone_country_iso2', $primaryPhoneCountryIso2) === $country['iso2']): echo 'selected'; endif; ?>><?php echo e($country['iso2']); ?> (+<?php echo e($country['calling_code']); ?>)</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                            <input id="phone" name="phone" inputmode="tel" maxlength="50" value="<?php echo e(old('phone', $primaryPhoneNationalNumber)); ?>" class="min-h-11 min-w-0 w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Phone number">
                        </div>
                    </div>
                    <div>
                        <label for="whatsapp" class="mb-1 block text-sm font-semibold text-slate-700">WhatsApp</label>
                        <div class="grid grid-cols-[minmax(7rem,0.8fr)_minmax(0,1.2fr)] gap-2">
                            <select name="whatsapp_country_iso2" aria-label="WhatsApp country code" class="min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-2 py-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $phoneCountries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($country['iso2']); ?>" <?php if(old('whatsapp_country_iso2', $whatsappCountryIso2) === $country['iso2']): echo 'selected'; endif; ?>><?php echo e($country['iso2']); ?> (+<?php echo e($country['calling_code']); ?>)</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                            <input id="whatsapp" name="whatsapp" inputmode="tel" maxlength="50" value="<?php echo e(old('whatsapp', $user->details?->whatsapp)); ?>" class="min-h-11 min-w-0 w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="WhatsApp number">
                        </div>
                    </div>
                    <div><label for="blood_group" class="mb-1 block text-sm font-semibold text-slate-700">Blood group</label><select id="blood_group" name="blood_group" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2"><option value="">Not specified</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($group); ?>" <?php if(old('blood_group', $user->details?->blood_group) === $group): echo 'selected'; endif; ?>><?php echo e($group); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                </div>
                <div class="border-t border-slate-100 pt-4"><h3 class="font-semibold text-slate-900">Emergency contacts</h3><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $user->emergencyContacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contact): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><div class="mt-2 flex flex-wrap justify-between gap-2 rounded-xl bg-slate-50 p-3 text-sm"><span class="font-semibold text-slate-800"><?php echo e($contact->contact_name); ?> <span class="font-normal text-slate-500"><?php echo e($contact->relationship); ?></span></span><span class="text-slate-600"><?php echo e($contact->phone); ?></span></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><p class="mt-2 text-sm text-slate-500">No emergency contact has been recorded.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="flex justify-end"><button type="submit" class="min-h-11 rounded-xl px-5 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Save profile</button></div>
            </form>
        </section>
        <section id="addresses" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950">Address</h2>
                    <p class="mt-1 text-sm text-slate-500">Permanent and residential addresses are required. You can add other address types as needed.</p>
                </div>
                <button type="button" onclick="toggleProfileEdit('address-edit', this)" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Edit addresses" title="Edit">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
                </button>
            </div>
            <div id="address-summary" class="grid gap-3 sm:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $user->addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="rounded-xl border border-slate-200 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            <?php echo e(filled($address->address_type) ? str($address->address_type)->replace('_', ' ')->title() : 'Address'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($address->is_primary): ?>
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold normal-case tracking-normal text-slate-700">Primary</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(strtolower(str_replace([' ', '-'], '_', (string) $address->address_type)), ['permanent', 'permanent_address', 'local', 'local_address', 'residential', 'residential_address'], true)): ?>
                                <span class="ml-1 rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold normal-case tracking-normal text-amber-800">Required</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-800">
                            <?php echo e(collect([$address->address_line_1, $address->address_line_2, $address->locality, $address->city, $address->state, $address->postal_code, $address->country])->filter(fn ($part) => filled($part))->implode(', ') ?: 'No address details recorded.'); ?>

                        </p>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-slate-500">No addresses have been recorded.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <form id="address-edit" hidden method="post" action="<?php echo e(route('organization.member.profile.update')); ?>" class="mt-6 space-y-4 border-t border-slate-100 pt-5">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <div id="address-rows" class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $user->addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $normalizedAddressType = strtolower(str_replace([' ', '-'], '_', (string) $address->address_type));
                            $isRequiredAddress = in_array($normalizedAddressType, ['permanent', 'permanent_address', 'local', 'local_address', 'residential', 'residential_address'], true);
                        ?>
                        <div class="rounded-xl border border-slate-200 p-4" data-address-row>
                            <input type="hidden" name="addresses[<?php echo e($i); ?>][id]" value="<?php echo e($address->id); ?>">
                            <input type="hidden" name="addresses[<?php echo e($i); ?>][address_type]" value="<?php echo e($address->address_type); ?>">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-slate-800"><?php echo e(str($address->address_type)->replace('_', ' ')->title()); ?></h3>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isRequiredAddress): ?>
                                    <span class="text-xs font-semibold text-amber-700">Required · type cannot be changed</span>
                                <?php else: ?>
                                    <label class="flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="addresses[<?php echo e($i); ?>][_delete]" value="1"> Delete address</label>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input name="addresses[<?php echo e($i); ?>][address_line_1]" value="<?php echo e($address->address_line_1); ?>" required placeholder="Address line 1" aria-label="Address line 1" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][address_line_2]" value="<?php echo e($address->address_line_2); ?>" placeholder="Address line 2" aria-label="Address line 2" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][locality]" value="<?php echo e($address->locality); ?>" placeholder="Locality" aria-label="Locality" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][city]" value="<?php echo e($address->city); ?>" placeholder="City" aria-label="City" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][state]" value="<?php echo e($address->state); ?>" placeholder="State" aria-label="State" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][postal_code]" value="<?php echo e($address->postal_code); ?>" placeholder="Postal code" aria-label="Postal code" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <input name="addresses[<?php echo e($i); ?>][country]" value="<?php echo e($address->country); ?>" placeholder="Country" aria-label="Country" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="addresses[<?php echo e($i); ?>][is_primary]" value="0"><input type="checkbox" name="addresses[<?php echo e($i); ?>][is_primary]" value="1" <?php if($address->is_primary): echo 'checked'; endif; ?>> Primary address</label>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <h3 class="mb-3 text-sm font-bold text-slate-800">Add another address</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select id="new-address-type" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2" aria-label="New address type">
                            <option value="">Select address type</option>
                            <option value="work">Work</option>
                            <option value="mailing">Mailing</option>
                            <option value="temporary">Temporary</option>
                            <option value="other">Other</option>
                        </select>
                        <button type="button" onclick="addAddressRow()" class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">+ Add selected address</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Permanent and Residential/Local are required address types and cannot be added as additional addresses.</p>
                </div>
                <template id="address-template">
                    <div class="rounded-xl border border-slate-200 p-4" data-address-row>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-slate-800" data-address-title></h3>
                            <button type="button" onclick="this.closest('[data-address-row]').remove()" class="text-sm font-semibold text-red-700">Remove</button>
                        </div>
                        <input type="hidden" data-address-type-input>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input data-field="address_line_1" required placeholder="Address line 1" aria-label="Address line 1" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="address_line_2" placeholder="Address line 2" aria-label="Address line 2" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="locality" placeholder="Locality" aria-label="Locality" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="city" placeholder="City" aria-label="City" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="state" placeholder="State" aria-label="State" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="postal_code" placeholder="Postal code" aria-label="Postal code" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <input data-field="country" placeholder="Country" aria-label="Country" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2">
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" data-field="is_primary_hidden" value="0"><input data-field="is_primary" type="checkbox" value="1"> Primary address</label>
                        </div>
                    </div>
                </template>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="toggleProfileEdit('address-edit', document.querySelector('[aria-label=&quot;Edit addresses&quot;]'))" class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-xl px-5 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Save addresses</button>
                </div>
            </form>
        </section>
        <section id="family" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-bold text-slate-950">Family and dependants</h2><p class="mt-1 text-sm text-slate-500">Manage your spouse and dependants linked to your membership.</p></div>
        <button type="button" id="open-dependant-modal" class="rounded-xl px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">+ Add dependant</button>
    </div>

    <div id="dependant-create-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/70 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="dependant-modal-title">
        <div class="flex max-h-[94vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div><h3 id="dependant-modal-title" class="text-lg font-bold text-slate-900">Add dependant</h3><p class="mt-1 text-sm text-slate-500">Complete both steps to save the dependant and their documents.</p></div>
                <button type="button" id="close-dependant-modal" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dependant form">✕</button>
            </header>
            <div class="grid grid-cols-2 gap-2 px-5 pt-4 sm:px-6" aria-label="Dependant form steps">
                <div id="dependant-step-indicator-1" class="rounded-lg border px-3 py-2 text-sm font-bold" style="border-color:var(--org-primary);background:color-mix(in srgb,var(--org-primary) 8%,white);color:var(--org-primary)">1. Dependant details</div>
                <div id="dependant-step-indicator-2" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-500">2. Documents</div>
            </div>
            <form id="dependant-create-form" method="POST" action="<?php echo e(route('organization.member.dependants.store')); ?>" enctype="multipart/form-data" class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                <?php echo csrf_field(); ?>
                <div id="dependant-step-1" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-[150px_minmax(0,1fr)]">
                        <div>
                            <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                <img id="dependant-photo-thumbnail" class="hidden h-full w-full object-cover" alt="Dependant profile photo preview">
                                <span id="dependant-photo-placeholder" class="px-3 text-center text-xs text-slate-500">Profile photo optional</span>
                            </div>
                            <label class="mt-2 block text-sm font-semibold">Profile photo
                                <input id="dependant-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-crop-input data-thumbnail="dependant-photo-thumbnail" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-normal file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-semibold">
                            </label>
                            <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP; up to 5 MB. Crop before saving.</p>
                        </div>
                        <div class="grid content-start gap-3 sm:grid-cols-2">
                            <label class="text-sm font-semibold">First name *<input required name="first_name" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                            <label class="text-sm font-semibold">Middle name<input name="middle_name" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                            <label class="text-sm font-semibold">Last name<input name="last_name" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                            <label class="text-sm font-semibold">Gender *<select required name="gender" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><option value="">Select gender</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option><option value="prefer_not_to_say">Prefer not to say</option></select></label>
                            <label class="text-sm font-semibold sm:col-span-2">Date of birth *<input required type="date" name="date_of_birth" max="<?php echo e(now()->toDateString()); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                        </div>
                    </div>
                    <label class="block text-sm font-semibold">Relationship to member *
                        <select required name="relationship_type" class="mt-1 w-full rounded-lg border border-slate-300 p-2 sm:max-w-xs"><option value="child">Child</option><option value="parent">Parent</option><option value="sibling">Sibling</option><option value="other">Other</option></select>
                    </label>
                </div>
                <div id="dependant-step-2" class="hidden space-y-4">
                    <p class="text-sm text-slate-600">Add the identification documents applicable to this organization. You can enter a document number, expiry date, and upload a proof image or PDF. Images can be cropped and previewed before saving.</p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dependantIdentificationTypes) && $dependantIdentificationTypes->isNotEmpty()): ?>
                        <div id="dependant-document-rows" class="space-y-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $dependantIdentificationTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $configuration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <section class="rounded-xl border border-slate-200 p-4">
                                    <input type="hidden" name="documents[<?php echo e($index); ?>][identification_type_id]" value="<?php echo e($configuration->identification_type_id); ?>">
                                    <h4 class="font-bold text-slate-900"><?php echo e($configuration->identificationType?->name ?? 'Identification'); ?></h4>
                                    <p class="mt-1 text-xs text-slate-500">Configured for this organization<?php echo e($configuration->is_required ? ' · Required' : ''); ?><?php echo e($configuration->requires_document ? ' · Proof required' : ''); ?></p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <label class="text-sm font-semibold">Document number<input name="documents[<?php echo e($index); ?>][identification_number]" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                                        <label class="text-sm font-semibold">Expiry date<input type="date" name="documents[<?php echo e($index); ?>][expires_at]" class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>
                                        <?php
                                            $documentSides = $configuration->document_requirements ?: ($configuration->requires_document ? ['document'] : []);
                                        ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documentSides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php
                                                $label = ['document' => 'Document proof', 'front' => 'Front side', 'back' => 'Back side'][$side] ?? ucfirst($side);
                                            ?>
                                            <div class="min-w-0">
                                                <label class="text-sm font-semibold"><?php echo e($label); ?><?php echo e($configuration->requires_document ? ' *' : ''); ?>

                                                    <input type="file" name="documents[<?php echo e($index); ?>][<?php echo e($side); ?>]" accept=".pdf,image/jpeg,image/png,image/webp" data-crop-input data-thumbnail="dependant-doc-<?php echo e($index); ?>-<?php echo e($side); ?>-thumbnail" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-normal file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-semibold">
                                                </label>
                                                <div class="mt-2 flex min-h-12 items-center gap-2">
                                                    <img id="dependant-doc-<?php echo e($index); ?>-<?php echo e($side); ?>-thumbnail" class="hidden h-16 w-24 rounded-lg border border-slate-200 object-cover" alt="<?php echo e($label); ?> preview">
                                                    <span id="dependant-doc-<?php echo e($index); ?>-<?php echo e($side); ?>-filename" class="text-xs text-slate-500">No file selected</span>
                                                </div>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                </section>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">No identification types are enabled for this organization. You can save the dependant without documents.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </form>
            <footer class="flex flex-wrap justify-between gap-2 border-t border-slate-200 px-5 py-4 sm:px-6">
                <button type="button" id="dependant-step-back" class="hidden rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Back</button>
                <div class="ml-auto flex gap-2">
                    <button type="button" id="cancel-dependant-create" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button>
                    <button type="button" id="dependant-step-next" class="rounded-xl px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Continue to documents →</button>
                    <button type="submit" form="dependant-create-form" id="dependant-submit" class="hidden rounded-xl px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Save dependant</button>
                </div>
            </footer>
        </div>
    </div>

    <div id="dependant-crop-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="dependant-crop-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
            <div class="flex items-center justify-between gap-3"><h3 id="dependant-crop-title" class="text-lg font-bold text-slate-900">Crop image</h3><button type="button" id="dependant-crop-close" class="rounded-lg p-2 text-slate-500" aria-label="Cancel image crop">✕</button></div>
            <p class="mt-1 text-sm text-slate-500">Drag to position the image and adjust zoom. This crop tool applies to profile photos and image-based document proofs.</p>
            <div class="mx-auto mt-4 w-full max-w-xl overflow-hidden rounded-xl bg-slate-100"><canvas id="dependant-crop-canvas" width="480" height="320" class="h-full w-full touch-none cursor-move"></canvas></div>
            <label class="mt-4 block text-sm font-medium text-slate-700" for="dependant-crop-zoom">Zoom</label><input id="dependant-crop-zoom" type="range" min="1" max="3" step="0.05" value="1" class="mt-2 w-full">
            <div class="mt-5 flex justify-end gap-2"><button type="button" id="dependant-crop-cancel" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel crop</button><button type="button" id="dependant-crop-save" class="rounded-xl px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Use cropped image</button></div>
        </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $spouseRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $spouseRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $requestedMemberData = data_get($spouseRequest->application?->data, 'member', []);
            $requestedByName = trim(implode(' ', array_filter([
                data_get($requestedMemberData, 'first_name'),
                data_get($requestedMemberData, 'middle_name'),
                data_get($requestedMemberData, 'last_name'),
            ]))) ?: ($spouseRequest->requesterMember?->user?->name ?? $spouseRequest->application?->user?->name ?? 'Membership applicant');
        ?>
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="font-semibold text-amber-950"><?php echo e($requestedByName); ?> has requested to identify you as their spouse.</p>
            <p class="mt-1 text-sm text-amber-900">Accept only if this relationship is correct. No spouse relationship is created unless you accept and the applicant becomes a member.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="post" action="<?php echo e(route('organization.member.spouse-requests.respond', $spouseRequest->id)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="decision" value="accept"><button class="rounded-lg px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Accept request</button></form>
                <form method="post" action="<?php echo e(route('organization.member.spouse-requests.respond', $spouseRequest->id)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="decision" value="decline"><button class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-bold text-amber-950">Decline</button></form>
            </div>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $outgoingSpouseRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $outgoingRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
            <p class="text-sm text-slate-700">Spouse request to <strong><?php echo e($outgoingRequest->targetMember?->user?->name ?? 'selected member'); ?></strong> is pending.</p>
            <form method="post" action="<?php echo e(route('organization.member.spouse-requests.cancel', $outgoingRequest->id)); ?>" onsubmit="return confirm('Cancel this spouse request?')"><?php echo csrf_field(); ?><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Cancel request</button></form>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <div class="mt-4 grid grid-cols-1 items-stretch gap-4 md:grid-cols-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasAvailableSpouseMembers): ?>
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20v-1.5A5.5 5.5 0 0 1 8 13h2a5.5 5.5 0 0 1 5.5 5.5V20"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 13.5a5.5 5.5 0 0 1 3.5 5V20"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Existing member</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Find your spouse in the member directory and send an acceptance request.</p>
                </div>
            </div>
        <form method="post" action="<?php echo e(route('organization.member.spouse-requests.store')); ?>" class="flex flex-1 flex-col" id="spouse-member-search-form">
            <?php echo csrf_field(); ?>
            <label for="spouse-member-search" class="block text-sm font-semibold text-slate-800">Request to connect an existing member as your spouse</label>
            <p class="mt-1 text-xs text-slate-500">Search by name, email, phone number or membership number.</p>
            <div class="relative mt-2">
                <input
                    type="search"
                    id="spouse-member-search"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="spouse-member-search-results"
                    aria-expanded="false"
                    placeholder="Type at least 2 characters to search"
                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2"
                >
                <input type="hidden" id="spouse-member-id" name="member_id" required>
                <div id="spouse-member-search-results" role="listbox" class="absolute z-30 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"></div>
            </div>
            <div id="spouse-member-selected" class="mt-2 hidden rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900" aria-live="polite"></div>
            <p id="spouse-member-search-status" class="mt-2 text-xs text-slate-500" role="status" aria-live="polite"></p>
            <p class="mt-3 text-xs leading-5 text-slate-500">The other member must accept before the relationship is created.</p>
            <div class="mt-auto pt-4">
                <button class="min-h-11 w-full rounded-xl px-4 py-2 text-sm font-bold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2" style="background:var(--org-primary)">Send spouse request</button>
            </div>
        </form>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($spouseSourceApplication): ?>
        <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20.5s-8-4.5-8-10.2A4.3 4.3 0 0 1 12 7.7a4.3 4.3 0 0 1 8 2.6c0 5.7-8 10.2-8 10.2Z"/><path d="M12 10v6M9 13h6"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">New spouse application</h3>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Your spouse is not a member yet? Start a separate membership application for them.</p>
                </div>
            </div>
            <div class="flex flex-1 flex-col">
                <div class="rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-600">
                    Their membership application will be reviewed through the normal process. The spouse relationship is handled separately and is not created automatically.
                </div>
                <div class="mt-auto pt-4">
                    <a class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-800 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2" href="<?php echo e(route('organization.membership.apply.spouse', ['source_application' => $spouseSourceApplication->id])); ?>">
                        Start spouse application
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </div>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $spouses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $spouse): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
            <div class="flex items-center gap-3"><div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 font-bold" style="color:var(--org-primary)"><?php echo e(mb_substr($spouse->user?->name ?? "S",0,1)); ?></div><div><p class="font-semibold"><?php echo e($spouse->user?->name ?? "Spouse"); ?></p><p class="text-sm text-slate-500">Spouse</p></div></div>
            <form method="post" action="<?php echo e(route('organization.member.spouses.disconnect', $spouse->id)); ?>" onsubmit="return confirm('Disconnect this spouse relationship?')"><?php echo csrf_field(); ?><button class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Disconnect</button></form>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $dependants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dependant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <article class="mt-3 rounded-xl border border-slate-200 p-3 sm:p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dependant->getFirstMedia('profile-photo')): ?><img src="<?php echo e(route('organization.member.dependants.photo', $dependant->id)); ?>" class="h-14 w-14 rounded-full object-cover" alt="Dependant photo"><?php else: ?><div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-lg font-bold" style="color:var(--org-primary)"><?php echo e(mb_substr($dependant->first_name,0,1)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div><p class="font-semibold text-slate-800"><?php echo e(trim($dependant->first_name.' '.$dependant->middle_name.' '.$dependant->last_name)); ?></p><p class="text-sm text-slate-500"><?php echo e(ucfirst(str_replace('_',' ',$dependant->gender ?? ''))); ?> · <?php echo e($dependant->date_of_birth?->format('d M Y')); ?></p><p class="text-xs text-slate-500"><?php echo e(ucfirst(str_replace('_',' ',$dependant->pivot->relationship_type ?? 'dependant'))); ?></p></div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" data-id-card-url="<?php echo e(route('organization.member.dependants.id-card', ['dependant' => $dependant->id, 'modal' => 1])); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" title="ID card" aria-label="View dependant ID card"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M5.5 15c.7-2 4.3-2 5 0M13 9h5M13 13h5"/></svg></button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id): ?>
                    <button type="button" onclick="document.getElementById('dependant-edit-<?php echo e($dependant->id); ?>').classList.toggle('hidden')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" title="Edit dependant" aria-label="Edit dependant"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5 15.5 5 4 16.5Z"/><path d="m13.8 6.7 3.5 3.5"/></svg></button>
                    <form method="POST" action="<?php echo e(route('organization.member.dependants.destroy', $dependant->id)); ?>" onsubmit="return confirm('Remove this dependant from your active family list?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 text-red-700 hover:bg-red-50" title="Remove dependant" aria-label="Remove dependant"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg></button></form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id): ?>
            <form id="dependant-edit-<?php echo e($dependant->id); ?>" method="POST" action="<?php echo e(route('organization.member.dependants.update', $dependant->id)); ?>" enctype="multipart/form-data" class="hidden mt-4 border-t border-slate-100 pt-4">
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold">First name *<input required name="first_name" value="<?php echo e($dependant->first_name); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
                    <label class="text-sm font-semibold">Middle name<input name="middle_name" value="<?php echo e($dependant->middle_name); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
                    <label class="text-sm font-semibold">Last name<input name="last_name" value="<?php echo e($dependant->last_name); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
                    <label class="text-sm font-semibold">Gender *<select required name="gender" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><option value="female" <?php if($dependant->gender==='female'): echo 'selected'; endif; ?>>Female</option><option value="male" <?php if($dependant->gender==='male'): echo 'selected'; endif; ?>>Male</option><option value="other" <?php if($dependant->gender==='other'): echo 'selected'; endif; ?>>Other</option><option value="prefer_not_to_say" <?php if($dependant->gender==='prefer_not_to_say'): echo 'selected'; endif; ?>>Prefer not to say</option></select></label>
                    <label class="text-sm font-semibold">Date of birth *<input required type="date" name="date_of_birth" value="<?php echo e($dependant->date_of_birth?->format('Y-m-d')); ?>" max="<?php echo e(now()->toDateString()); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2" /></label>
                    <label class="text-sm font-semibold">Relationship *<select required name="relationship_type" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['child'=>'Child','parent'=>'Parent','sibling'=>'Sibling','other'=>'Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($key); ?>" <?php if(($dependant->pivot->relationship_type ?? '')===$key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></label>
                    <label class="text-sm font-semibold sm:col-span-2">Replace profile photo (optional)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-semibold" /></label>
                </div>
                <div class="mt-4 flex justify-end"><button type="submit" class="rounded-lg px-4 py-2 text-sm font-bold text-white" style="background:var(--org-primary)">Save changes</button></div>
            </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="mt-3 border-t border-slate-100 pt-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">Identification documents</p>
                    <?php
                    $existingTypeIds = $dependant->identifications->pluck('identification_type_id')->map(fn ($id) => (int) $id)->all();
                ?>
                    <?php
                    $availableDocumentTypes = $dependantIdentificationTypes->reject(fn ($type) => in_array((int) $type->identification_type_id, $existingTypeIds, true));
                ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id && $availableDocumentTypes->isNotEmpty()): ?>
                        <button type="button" onclick="document.getElementById('dependant-add-document-<?php echo e($dependant->id); ?>').classList.toggle('hidden')" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" title="Add identification document"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> Add document</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $dependant->identifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $docConfiguration = $dependantIdentificationTypes->firstWhere('identification_type_id', $doc->identification_type_id);
                    ?>
                    <div class="mt-2 rounded-lg border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><p class="text-sm font-semibold text-slate-800"><?php echo e($doc->identificationType?->name ?? 'Identification'); ?></p><p class="mt-1 text-xs text-slate-600"><?php echo e($doc->identification_number ?: 'No number recorded'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc->expires_at): ?> · Expires <?php echo e($doc->expires_at->format('d M Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                <div class="mt-2 flex flex-wrap gap-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['document'=>'Proof','front'=>'Front','back'=>'Back']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc->document($side)): ?><a href="<?php echo e(route('organization.member.dependants.document', [$dependant->id, $doc->id, $side])); ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-800 underline">View <?php echo e($label); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id): ?>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" onclick="document.getElementById('dependant-document-edit-<?php echo e($doc->id); ?>').classList.toggle('hidden')" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50" title="Edit document" aria-label="Edit <?php echo e($doc->identificationType?->name ?? 'document'); ?>"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5 15.5 5 4 16.5Z"/><path d="m13.8 6.7 3.5 3.5"/></svg></button>
                                    <form method="POST" action="<?php echo e(route('organization.member.dependants.documents.destroy', [$dependant->id, $doc->id])); ?>" onsubmit="return confirm('Remove this identification document?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 text-red-700 hover:bg-red-50" title="Remove document" aria-label="Remove <?php echo e($doc->identificationType?->name ?? 'document'); ?>"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 13h11l1-13M9 7V4h6v3"/></svg></button></form>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id): ?>
                            <form id="dependant-document-edit-<?php echo e($doc->id); ?>" method="POST" enctype="multipart/form-data" action="<?php echo e(route('organization.member.dependants.documents.update', [$dependant->id, $doc->id])); ?>" class="hidden mt-3 border-t border-slate-100 pt-3">
                                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                                <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold">Document number<input name="identification_number" value="<?php echo e($doc->identification_number); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label><label class="text-xs font-semibold">Expiry date<input type="date" name="expires_at" value="<?php echo e($doc->expires_at?->format('Y-m-d')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
                                <?php
                                $docSides = $docConfiguration?->document_requirements ?: ($docConfiguration?->requires_document ? ['document'] : []);
                            ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $docSides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                    $sideLabel = ['document' => 'Document proof', 'front' => 'Front side', 'back' => 'Back side'][$side] ?? ucfirst($side);
                                ?>
                                    <label class="text-xs font-semibold">Replace <?php echo e($sideLabel); ?><input type="file" name="<?php echo e($side); ?>" accept=".pdf,image/jpeg,image/png,image/webp" data-crop-input data-thumbnail="dependant-edit-doc-<?php echo e($doc->id); ?>-<?php echo e($side); ?>-thumbnail" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs font-normal file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1"></label>
                                    <img id="dependant-edit-doc-<?php echo e($doc->id); ?>-<?php echo e($side); ?>-thumbnail" class="hidden h-16 w-24 rounded-lg border border-slate-200 object-cover" alt="<?php echo e($sideLabel); ?> preview">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div><div class="mt-3 flex justify-end"><button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold text-white" style="background:var(--org-primary)">Save document</button></div>
                            </form>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="mt-2 text-xs text-slate-500">No identification documents recorded.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$dependant->converted_member_id && $availableDocumentTypes->isNotEmpty()): ?>
                    <form id="dependant-add-document-<?php echo e($dependant->id); ?>" method="POST" enctype="multipart/form-data" action="<?php echo e(route('organization.member.dependants.documents.store', $dependant->id)); ?>" class="hidden mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <?php echo csrf_field(); ?>
                        <label class="block text-xs font-semibold">Document type<select name="identification_type_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" onchange="this.form.querySelectorAll('[data-doc-type-fields]').forEach(el => { const active = el.dataset.docTypeFields === this.value; el.classList.toggle('hidden', !active); el.querySelectorAll('input').forEach(input => input.disabled = !active); })"><option value="">Select document type</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availableDocumentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($type->identification_type_id); ?>"><?php echo e($type->identificationType?->name ?? 'Identification'); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availableDocumentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $addSides = $type->document_requirements ?: ($type->requires_document ? ['document'] : []);
                            ?>
                            <div data-doc-type-fields="<?php echo e($type->identification_type_id); ?>" class="hidden mt-3 grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold">Document number<input name="identification_number" maxlength="255" disabled class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type->has_expiry): ?><label class="text-xs font-semibold">Expiry date<input type="date" name="expires_at" disabled class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $addSides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                    $sideLabel = ['document' => 'Document proof', 'front' => 'Front side', 'back' => 'Back side'][$side] ?? ucfirst($side);
                                ?>
                                    <label class="text-xs font-semibold"><?php echo e($sideLabel); ?><?php echo e($type->requires_document ? ' *' : ''); ?><input type="file" name="<?php echo e($side); ?>" disabled accept=".pdf,image/jpeg,image/png,image/webp" data-crop-input data-thumbnail="dependant-add-<?php echo e($dependant->id); ?>-<?php echo e($type->identification_type_id); ?>-<?php echo e($side); ?>-thumbnail" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs font-normal file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1"></label><img id="dependant-add-<?php echo e($dependant->id); ?>-<?php echo e($type->identification_type_id); ?>-<?php echo e($side); ?>-thumbnail" class="hidden h-16 w-24 rounded-lg border border-slate-200 object-cover" alt="<?php echo e($sideLabel); ?> preview">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="mt-3 flex justify-end"><button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold text-white" style="background:var(--org-primary)">Save document</button></div>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </article>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No dependants are linked to your profile yet. Use “Add dependant” to add one.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<section id="payments" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-bold text-slate-950">Payments</h2><p class="mt-1 text-sm text-slate-500">All payment obligations and their current status.</p><div class="mt-4 max-h-[28rem] overflow-auto rounded-xl border border-slate-200"><table class="w-full min-w-[640px] text-left text-sm"><thead class="sticky top-0 z-10 bg-slate-50"><tr class="border-b border-slate-100 text-slate-600"><th class="px-3 py-3 font-semibold">Payment</th><th class="px-3 py-3 font-semibold">Due date</th><th class="px-3 py-3 font-semibold">Amount</th><th class="px-3 py-3 font-semibold">Paid</th><th class="px-3 py-3 font-semibold">Status</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $obligations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $obligation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/70"><td class="px-3 py-3 font-medium text-slate-900"><?php echo e($obligation->item_title ?? ('Payment #'.$obligation->id)); ?></td><td class="px-3 py-3 text-slate-600"><?php echo e($obligation->due_at?->format('d M Y') ?? '—'); ?></td><td class="px-3 py-3"><?php echo e($obligation->currency); ?> <?php echo e(number_format((float)$obligation->payable_amount, 2)); ?></td><td class="px-3 py-3"><?php echo e($obligation->currency); ?> <?php echo e(number_format((float)$obligation->paid_amount, 2)); ?></td><td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold"><?php echo e(ucfirst(str_replace('_',' ',(string)($obligation->status?->value ?? $obligation->status ?? 'pending')))); ?></span></td></tr><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No payment obligations found.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div></section>
        <section id="membership-details" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <div class="flex items-start justify-between gap-3">
        <div><h2 class="text-lg font-bold text-slate-950">Membership details</h2><p class="mt-1 text-sm text-slate-500">Your membership status, dates, and membership category.</p></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentMembership): ?>
            <span class="rounded-full px-3 py-1 text-xs font-bold capitalize" style="background:color-mix(in srgb, var(--org-primary) 10%, white); color:var(--org-primary)"><?php echo e(str_replace('_', ' ', $currentMembership->status instanceof \BackedEnum ? $currentMembership->status->value : $currentMembership->status)); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentMembership): ?>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Membership type</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($currentMembership->membershipType?->name ?? 'Not assigned'); ?></dd></div>
            <div class="rounded-xl border border-slate-200 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($member->joined_at?->format('d M Y') ?? $currentMembership->starts_at?->format('d M Y') ?? 'Not recorded'); ?></dd></div>
            <div class="rounded-xl border border-slate-200 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current term starts</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($currentMembership->starts_at?->format('d M Y') ?? 'Not recorded'); ?></dd></div>
            <div class="rounded-xl border border-slate-200 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next renewal / expiry</dt><dd class="mt-1 font-semibold text-slate-900"><?php echo e($currentMembership->ends_at?->format('d M Y') ?? data_get($currentMembership->metadata, 'next_renewal_date') ?? 'Not scheduled'); ?></dd></div>
        </dl>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($membershipRecords->count() > 1): ?>
            <details class="mt-4 rounded-xl border border-slate-200 p-3">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Previous membership records (<?php echo e($membershipRecords->count()); ?>)</summary>
                <div class="mt-3 space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $membershipRecords->skip(1); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $membershipRecord): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-sm">
                            <span class="font-medium text-slate-800"><?php echo e($membershipRecord->membershipType?->name ?? 'Membership'); ?></span>
                            <span class="text-slate-600"><?php echo e($membershipRecord->starts_at?->format('d M Y') ?? '—'); ?> – <?php echo e($membershipRecord->ends_at?->format('d M Y') ?? 'Ongoing'); ?></span>
                            <span class="capitalize text-slate-500"><?php echo e(str_replace('_', ' ', $membershipRecord->status instanceof \BackedEnum ? $membershipRecord->status->value : $membershipRecord->status)); ?></span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </details>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
        <p class="mt-3 text-sm text-slate-500">Membership details have not been recorded yet.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($moduleProfileSections)): ?>
    <section id="organization-module-details" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-bold text-slate-950">Organization-specific details</h2>
        <p class="mt-1 text-sm text-slate-500">Additional information provided by modules enabled for your organization.</p>
        <div class="mt-4 space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $moduleProfileSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profileSection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <article class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-base font-bold text-slate-900"><?php echo e($profileSection['title']); ?></h3>
                    <div class="mt-3">
                        <?php echo $__env->make($profileSection['view'], $profileSection['data'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php echo $__env->make($profileSection['edit_view'], array_merge($profileSection['data'], ['module' => $profileSection['module']]), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<section id="documents" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <h2 class="text-lg font-bold text-slate-950">Identification documents</h2>
    <p class="mt-1 text-sm text-slate-500">Expiry reminders, secure proof viewing, and document updates.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $identifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $identification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $daysLeft = $identification->expires_at ? now()->startOfDay()->diffInDays($identification->expires_at, false) : null;
            $documentConfiguration = $identificationConfigurations->get($identification->identification_type_id);
            $documentSides = $documentConfiguration?->document_requirements ?: ($documentConfiguration?->requires_document ? ['document'] : []);
        ?>
        <div class="mt-3 rounded-xl border border-slate-200 p-3 sm:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900"><?php echo e($identification->identificationType?->name ?? 'Identification document'); ?></p>
                    <p class="mt-1 text-sm text-slate-600">Number: <?php echo e($identification->identification_number ?: 'Not recorded'); ?></p>
                    <p class="mt-1 text-sm text-slate-500">Expires: <?php echo e($identification->expires_at?->format('d M Y') ?? 'Not recorded'); ?></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daysLeft !== null && $daysLeft < 0): ?>
                        <p class="mt-2 text-sm font-semibold text-red-700">Expired <?php echo e(abs($daysLeft)); ?> day(s) ago. Please renew this document.</p>
                    <?php elseif($daysLeft !== null && $daysLeft <= 60): ?>
                        <p class="mt-2 text-sm font-semibold text-amber-700">Expiring in <?php echo e($daysLeft); ?> day(s). Please renew soon.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="flex shrink-0 flex-wrap gap-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['document'=>'View','front'=>'Front','back'=>'Back']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identification->document($side)): ?>
                            <a target="_blank" rel="noopener" href="<?php echo e(route('organization.member.profile.document', [$identification->id, $side])); ?>" class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700" aria-label="View <?php echo e($label); ?> of <?php echo e($identification->identificationType?->name ?? 'document'); ?>"><?php echo e($side === 'document' ? 'View proof' : $label); ?></a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documentConfiguration): ?>
                        <button type="button"
                            class="rounded-lg px-3 py-2 text-xs font-bold text-white"
                            style="background:var(--org-primary)"
                            data-edit-profile-document
                            data-action="<?php echo e(route('organization.member.profile.document.update', $identification->id)); ?>"
                            data-number="<?php echo e($identification->identification_number); ?>"
                            data-expires="<?php echo e($identification->expires_at?->format('Y-m-d')); ?>"
                            data-type="<?php echo e($identification->identificationType?->name ?? 'Identification document'); ?>"
                            data-sides="<?php echo e(implode(',', $documentSides)); ?>"
                            data-has-expiry="<?php echo e($documentConfiguration->has_expiry ? '1' : '0'); ?>"
                            data-required="<?php echo e($documentConfiguration->is_required ? '1' : '0'); ?>">Edit</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <p class="mt-3 text-sm text-slate-500">No identification documents have been recorded.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
    </main>
</div>


<div id="profile-document-edit-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-slate-950/60 p-3 sm:p-6" style="--org-primary: <?php echo e($primaryColor ?? '#174b3d'); ?>;" role="dialog" aria-modal="true" aria-labelledby="profile-document-edit-title">
    <div class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div><h2 id="profile-document-edit-title" class="text-lg font-bold text-slate-900">Edit identification document</h2><p id="profile-document-edit-type" class="mt-1 text-sm text-slate-500"></p></div>
            <button type="button" id="profile-document-edit-close" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close edit document modal">✕</button>
        </header>
        <form id="profile-document-edit-form" method="POST" enctype="multipart/form-data" class="min-h-0 space-y-4 overflow-y-auto p-5">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <label class="block text-sm font-semibold text-slate-700">Document number
                <input id="profile-document-number" name="identification_number" maxlength="255" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
            </label>
            <label id="profile-document-expiry-wrap" class="block text-sm font-semibold text-slate-700">Expiry date
                <input id="profile-document-expiry" type="date" name="expires_at" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
            </label>
            <p class="text-xs text-slate-500">Leave a proof upload empty to keep the current file. Uploading a replacement replaces only that proof side.</p>
            <div id="profile-document-proof-fields" class="grid gap-3 sm:grid-cols-2"></div>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" id="profile-document-edit-cancel" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
                <button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-bold text-white" style="background:var(--org-primary)">Save changes</button>
            </div>
        </form>
    </div>
</div>

<div id="photo-crop-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="photo-crop-title">
  <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-2xl sm:p-6">
    <div class="flex items-center justify-between gap-3"><h2 id="photo-crop-title" class="text-lg font-bold text-slate-900">Crop profile photo</h2><button type="button" id="photo-crop-close" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Cancel crop">✕</button></div>
    <p class="mt-1 text-sm text-slate-500">Move the image within the square and adjust zoom before saving.</p>
    <div class="mx-auto mt-4 h-64 w-64 max-w-full overflow-hidden rounded-xl bg-slate-100"><canvas id="photo-crop-canvas" width="320" height="320" class="h-full w-full touch-none cursor-move"></canvas></div>
    <label class="mt-4 block text-sm font-medium text-slate-700" for="photo-crop-zoom">Zoom</label><input id="photo-crop-zoom" type="range" min="1" max="3" step="0.05" value="1" class="mt-2 w-full accent-[var(--org-primary)]">
    <div class="mt-5 flex justify-end gap-2"><button type="button" id="photo-crop-cancel" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button><button type="button" id="photo-crop-save" class="rounded-xl px-4 py-2 text-sm font-bold text-white shadow-sm" style="background-color: '<?php echo e($primaryColor); ?>' !important; color: #fff !important; border: 0; display: inline-flex; align-items: center; justify-content: center; min-height: 40px; position: relative; z-index: 10;">Save cropped photo</button></div>
  </div>
</div>


<div id="id-card-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 p-2 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="id-card-modal-title">
    <div class="flex h-[98dvh] max-h-[98dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:h-[96dvh] sm:max-h-[96dvh]">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 id="id-card-modal-title" class="font-bold text-slate-900">ID Card</h2>
            <button type="button" id="id-card-modal-close" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" aria-label="Close ID card">Close</button>
        </div>
        <iframe id="id-card-modal-frame" title="ID card preview" class="min-h-0 w-full flex-1 bg-slate-100" style="height:calc(98dvh - 64px)" src="about:blank"></iframe>
        <div class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500">Download a JPEG directly, or use Print / Save PDF for a PDF copy.</div>
    </div>
</div>
<script>
(() => {
    const modal = document.getElementById('id-card-modal');
    const frame = document.getElementById('id-card-modal-frame');
    const closeButton = document.getElementById('id-card-modal-close');
    if (!modal || !frame || !closeButton) return;
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        frame.src = 'about:blank';
    };
    document.querySelectorAll('[data-id-card-url]').forEach(button => {
        button.addEventListener('click', () => {
            frame.src = button.dataset.idCardUrl;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            closeButton.focus();
        });
    });
    closeButton.addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();
</script>

<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const form = document.getElementById('spouse-member-search-form');
    const input = document.getElementById('spouse-member-search');
    const memberId = document.getElementById('spouse-member-id');
    const results = document.getElementById('spouse-member-search-results');
    const selected = document.getElementById('spouse-member-selected');
    const status = document.getElementById('spouse-member-search-status');
    if (!form || !input || !memberId || !results || !selected || !status) return;

    const searchUrl = <?php echo json_encode(route('organization.membership.apply.spouse-search'), 15, 512) ?>;
    let debounceHandle = null;
    let activeController = null;
    let requestSequence = 0;

    function closeResults() {
        results.classList.add('hidden');
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
    }

    function clearSelection() {
        memberId.value = '';
        selected.classList.add('hidden');
        selected.replaceChildren();
    }

    function makeResult(member) {
        const button = document.createElement('button');
        button.type = 'button';
        button.role = 'option';
        button.className = 'block w-full border-b border-slate-100 px-3 py-3 text-left last:border-b-0 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
        const row = document.createElement('span');
        row.className = 'flex items-center gap-3';

        if (member.photo_url) {
            const photo = document.createElement('img');
            photo.src = member.photo_url;
            photo.alt = '';
            photo.loading = 'lazy';
            photo.className = 'h-10 w-10 shrink-0 rounded-full object-cover bg-slate-100';
            photo.addEventListener('error', () => photo.remove(), { once: true });
            row.appendChild(photo);
        } else {
            const placeholder = document.createElement('span');
            placeholder.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600';
            placeholder.textContent = (member.name || '?').trim().charAt(0).toUpperCase();
            row.appendChild(placeholder);
        }

        const text = document.createElement('span');
        text.className = 'min-w-0 flex-1';
        const name = document.createElement('span');
        name.className = 'block text-sm font-semibold text-slate-900';
        name.textContent = member.name || 'Unnamed member';
        text.appendChild(name);

        const details = [member.membership_number ? `Membership no. ${member.membership_number}` : null, member.email, member.phone].filter(Boolean);
        if (details.length) {
            const detail = document.createElement('span');
            detail.className = 'mt-1 block break-words text-xs text-slate-600';
            detail.textContent = details.join(' · ');
            text.appendChild(detail);
        }
        row.appendChild(text);
        button.appendChild(row);

        button.addEventListener('click', () => {
            memberId.value = String(member.id);
            input.value = member.name || '';
            selected.replaceChildren();
            const label = document.createElement('span');
            label.className = 'font-semibold';
            label.textContent = `Selected: ${member.name || 'Unnamed member'}`;
            selected.appendChild(label);
            const detailText = details.join(' · ');
            if (detailText) {
                const detail = document.createElement('span');
                detail.className = 'mt-1 block text-xs';
                detail.textContent = detailText;
                selected.appendChild(detail);
            }
            selected.classList.remove('hidden');
            status.textContent = 'Confirm the selected person before sending the request.';
            closeResults();
        });
        return button;
    }

    async function searchMembers(query) {
        const sequence = ++requestSequence;
        if (activeController) activeController.abort();
        activeController = new AbortController();
        status.textContent = 'Searching…';
        results.replaceChildren();
        results.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: activeController.signal
            });
            if (!response.ok) throw new Error('Search request failed');
            const payload = await response.json();
            if (sequence !== requestSequence) return;
            const members = Array.isArray(payload.data) ? payload.data : [];
            results.replaceChildren();
            if (!members.length) {
                status.textContent = 'No eligible members found. Try another name, email, phone or membership number.';
                closeResults();
                return;
            }
            members.forEach(member => results.appendChild(makeResult(member)));
            status.textContent = `${members.length} result${members.length === 1 ? '' : 's'} found. Select the correct person.`;
            results.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = 'Member search is temporarily unavailable. Please try again.';
            closeResults();
        }
    }

    input.addEventListener('input', () => {
        clearSelection();
        const query = input.value.trim();
        if (debounceHandle) window.clearTimeout(debounceHandle);
        if (activeController) activeController.abort();
        if (query.length < 2) {
            closeResults();
            status.textContent = query.length ? 'Type at least 2 characters to search.' : '';
            return;
        }
        debounceHandle = window.setTimeout(() => searchMembers(query), 250);
    });

    form.addEventListener('submit', event => {
        if (!memberId.value) {
            event.preventDefault();
            status.textContent = 'Search for a member and select the correct person before sending the request.';
            input.focus();
        }
    });

    document.addEventListener('click', event => {
        if (!form.contains(event.target)) closeResults();
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeResults();
    });
})();
</script>

<script>

const photoInput = document.getElementById('profile-photo-input');
const photoForm = document.getElementById('profile-photo-form');
const cropModal = document.getElementById('photo-crop-modal');
const cropCanvas = document.getElementById('photo-crop-canvas');
const cropCtx = cropCanvas.getContext('2d');
const zoomInput = document.getElementById('photo-crop-zoom');
let cropImage = null, cropZoom = 1, cropX = 0, cropY = 0, dragStart = null;
function drawProfileCrop() {
  if (!cropImage) return;
  const size = cropCanvas.width, scale = Math.max(size / cropImage.width, size / cropImage.height) * cropZoom;
  const w = cropImage.width * scale, h = cropImage.height * scale;
  cropCtx.clearRect(0, 0, size, size); cropCtx.fillStyle = '#f1f5f9'; cropCtx.fillRect(0, 0, size, size);
  cropCtx.drawImage(cropImage, (size-w)/2+cropX, (size-h)/2+cropY, w, h);
}
photoInput?.addEventListener('change', () => {
  const file = photoInput.files?.[0]; if (!file) return;
  if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5*1024*1024) { alert('Choose a JPG, PNG, or WebP image up to 5 MB.'); photoInput.value=''; return; }
  const url = URL.createObjectURL(file); cropImage = new Image();
  cropImage.onload = () => { cropZoom=1; cropX=0; cropY=0; zoomInput.value='1'; drawProfileCrop(); cropModal.classList.remove('hidden'); cropModal.classList.add('flex'); URL.revokeObjectURL(url); };
  cropImage.onerror = () => { URL.revokeObjectURL(url); alert('This image could not be opened. Please choose another file.'); photoInput.value=''; };
  cropImage.src = url;
});
zoomInput?.addEventListener('input', () => { cropZoom=Number(zoomInput.value); drawProfileCrop(); });
cropCanvas?.addEventListener('pointerdown', e => { dragStart={x:e.clientX,y:e.clientY,cropX,cropY}; cropCanvas.setPointerCapture(e.pointerId); });
cropCanvas?.addEventListener('pointermove', e => { if (!dragStart) return; cropX=dragStart.cropX+(e.clientX-dragStart.x)*cropCanvas.width/cropCanvas.clientWidth; cropY=dragStart.cropY+(e.clientY-dragStart.y)*cropCanvas.height/cropCanvas.clientHeight; drawProfileCrop(); });
['pointerup','pointercancel','lostpointercapture'].forEach(evt => cropCanvas?.addEventListener(evt,()=>dragStart=null));
function closePhotoCrop(){ cropModal.classList.add('hidden'); cropModal.classList.remove('flex'); cropImage=null; photoInput.value=''; }
document.getElementById('photo-crop-close')?.addEventListener('click',closePhotoCrop);
document.getElementById('photo-crop-cancel')?.addEventListener('click',closePhotoCrop);
document.getElementById('photo-crop-save')?.addEventListener('click',()=>{
  if (!cropImage) return;
  cropCanvas.toBlob(blob=>{
    if (!blob) { alert('Could not crop this image. Please try another image.'); return; }
    const croppedFile = new File([blob], 'profile-photo.jpg', {type:'image/jpeg'});
    const transfer = new DataTransfer(); transfer.items.add(croppedFile); photoInput.files=transfer.files;
    cropModal.classList.add('hidden'); cropModal.classList.remove('flex'); photoForm.requestSubmit();
  },'image/jpeg',0.92);
});

(() => {
    const modal = document.getElementById('profile-document-edit-modal');
    const form = document.getElementById('profile-document-edit-form');
    const numberInput = document.getElementById('profile-document-number');
    const expiryInput = document.getElementById('profile-document-expiry');
    const expiryWrap = document.getElementById('profile-document-expiry-wrap');
    const typeLabel = document.getElementById('profile-document-edit-type');
    const proofFields = document.getElementById('profile-document-proof-fields');
    if (!modal || !form || !numberInput || !expiryInput || !proofFields) return;

    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.querySelectorAll('[data-edit-profile-document]').forEach(button => {
        button.addEventListener('click', () => {
            form.action = button.dataset.action;
            numberInput.value = button.dataset.number || '';
            expiryInput.value = button.dataset.expires || '';
            typeLabel.textContent = button.dataset.type || 'Identification document';
            numberInput.required = button.dataset.required === '1';
            expiryWrap.classList.toggle('hidden', button.dataset.hasExpiry !== '1');
            const sides = (button.dataset.sides || '').split(',').filter(Boolean);
            const labels = {document: 'Document proof', front: 'Front side', back: 'Back side'};
            proofFields.innerHTML = '';
            sides.forEach(side => {
                const label = document.createElement('label');
                label.className = 'block min-w-0 text-sm font-semibold text-slate-700';
                label.textContent = 'Replace ' + (labels[side] || side);
                const input = document.createElement('input');
                input.type = 'file';
                input.name = side;
                input.accept = '.pdf,image/jpeg,image/png,image/webp';
                input.className = 'mt-1 block w-full rounded-xl border border-slate-300 p-2 text-sm font-normal';
                label.appendChild(input);
                proofFields.appendChild(label);
            });
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            numberInput.focus();
        });
    });
    document.getElementById('profile-document-edit-close')?.addEventListener('click', close);
    document.getElementById('profile-document-edit-cancel')?.addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();

function toggleProfileEdit(id, button) { const form = document.getElementById(id); if (!form) return; form.hidden = !form.hidden; const summaryId = id === 'personal-edit' ? 'personal-summary' : (id === 'address-edit' ? 'address-summary' : null); const summary = summaryId ? document.getElementById(summaryId) : null; if (summary) summary.hidden = !form.hidden; if (!form.hidden) { const first = form.querySelector('input:not([type=hidden]), select, textarea'); if (first) first.focus({preventScroll:true}); } button.setAttribute('aria-label', form.hidden ? (id === 'address-edit' ? 'Edit addresses' : 'Edit personal information') : 'Close editing'); button.setAttribute('title', form.hidden ? 'Edit' : 'Close'); }
function addAddressRow() { const select = document.getElementById('new-address-type'); const type = select ? select.value : ''; if (!type) { if (select) select.focus(); return; } const rows = document.getElementById('address-rows'); const template = document.getElementById('address-template'); if (!rows || !template) return; const index = Math.max(0, ...Array.from(rows.querySelectorAll('input[name^="addresses["]')).map(input => { const match = input.name.match(/^addresses\[(\d+)\]/); return match ? Number(match[1]) + 1 : 0; })); const fragment = template.content.cloneNode(true); const row = fragment.querySelector('[data-address-row]'); row.querySelector('[data-address-title]').textContent = type.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()); const typeInput = row.querySelector('[data-address-type-input]'); typeInput.name = `addresses[${index}][address_type]`; typeInput.value = type; row.querySelectorAll('[data-field]').forEach(field => { const key = field.dataset.field === 'is_primary_hidden' ? 'is_primary' : field.dataset.field; field.name = `addresses[${index}][${key}]`; }); rows.appendChild(fragment); select.value = ''; }
function addProfileRow(containerId, templateId) {
    const container = document.getElementById(containerId);
    const template = document.getElementById(templateId);
    if (!container || !template) return;
    const index = container.querySelectorAll(':scope > div').length;
    container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
}

// Two-step dependant modal and shared crop/thumbnail workflow.
(() => {
  const modal = document.getElementById('dependant-create-modal');
  const form = document.getElementById('dependant-create-form');
  if (!modal || !form) return;
  const stepOne = document.getElementById('dependant-step-1');
  const stepTwo = document.getElementById('dependant-step-2');
  const next = document.getElementById('dependant-step-next');
  const back = document.getElementById('dependant-step-back');
  const submit = document.getElementById('dependant-submit');
  const indicatorOne = document.getElementById('dependant-step-indicator-1');
  const indicatorTwo = document.getElementById('dependant-step-indicator-2');
  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); showStep(1); };
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
  document.getElementById('open-dependant-modal')?.addEventListener('click', open);
  document.getElementById('close-dependant-modal')?.addEventListener('click', close);
  document.getElementById('cancel-dependant-create')?.addEventListener('click', close);
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  function showStep(step) {
    const first = step === 1;
    stepOne.classList.toggle('hidden', !first);
    stepTwo.classList.toggle('hidden', first);
    back.classList.toggle('hidden', first);
    next.classList.toggle('hidden', !first);
    submit.classList.toggle('hidden', first);
    indicatorOne.className = first ? 'rounded-lg border px-3 py-2 text-sm font-bold' : 'rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-500';
    indicatorTwo.className = !first ? 'rounded-lg border px-3 py-2 text-sm font-bold' : 'rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-500';
    if (first) { indicatorOne.style.borderColor='var(--org-primary)'; indicatorOne.style.background='color-mix(in srgb,var(--org-primary) 8%,white)'; indicatorOne.style.color='var(--org-primary)'; }
    else { indicatorTwo.style.borderColor='var(--org-primary)'; indicatorTwo.style.background='color-mix(in srgb,var(--org-primary) 8%,white)'; indicatorTwo.style.color='var(--org-primary)'; }
  }
  next.addEventListener('click', () => {
    const required = [...stepOne.querySelectorAll('[required]')];
    const invalid = required.find(el => !el.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
    showStep(2);
  });
  back.addEventListener('click', () => showStep(1));

  const cropModal = document.getElementById('dependant-crop-modal');
  const canvas = document.getElementById('dependant-crop-canvas');
  const ctx = canvas.getContext('2d');
  const zoom = document.getElementById('dependant-crop-zoom');
  let activeInput=null, activeThumbnail=null, activeImage=null, zoomValue=1, offsetX=0, offsetY=0, drag=null;
  const imageTypes = ['image/jpeg','image/png','image/webp'];
  function draw() {
    if (!activeImage) return;
    const width=canvas.width, height=canvas.height, scale=Math.max(width/activeImage.width,height/activeImage.height)*zoomValue;
    const w=activeImage.width*scale, h=activeImage.height*scale;
    ctx.clearRect(0,0,width,height); ctx.fillStyle='#f1f5f9'; ctx.fillRect(0,0,width,height);
    ctx.drawImage(activeImage,(width-w)/2+offsetX,(height-h)/2+offsetY,w,h);
  }
  function hideCrop(clear=false) {
    cropModal.classList.add('hidden'); cropModal.classList.remove('flex');
    activeImage=null; activeInput=null; activeThumbnail=null;
    if (clear && activeInput) activeInput.value='';
  }
  document.querySelectorAll('#dependant-create-form [data-crop-input]').forEach(input => {
    input.addEventListener('change', () => {
      const file=input.files?.[0]; if (!file) return;
      const thumbnailId=input.dataset.thumbnail;
      const thumbnail=document.getElementById(thumbnailId);
      const filename=document.getElementById(thumbnailId?.replace('-thumbnail','-filename'));
      if (imageTypes.includes(file.type)) {
        if (file.size > 5*1024*1024) { alert('Choose an image up to 5 MB.'); input.value=''; return; }
        activeInput=input; activeThumbnail=thumbnail; activeImage=new Image();
        const url=URL.createObjectURL(file);
        activeImage.onload=()=>{ zoomValue=1; offsetX=0; offsetY=0; zoom.value='1'; draw(); cropModal.classList.remove('hidden'); cropModal.classList.add('flex'); URL.revokeObjectURL(url); };
        activeImage.onerror=()=>{ URL.revokeObjectURL(url); alert('This image could not be opened.'); input.value=''; };
        activeImage.src=url;
      } else if (file.type==='application/pdf' && input.name.includes('[document]') || file.type==='application/pdf') {
        if (thumbnail) { thumbnail.classList.add('hidden'); thumbnail.removeAttribute('src'); }
        if (filename) filename.textContent=file.name;
        else {
          const label=document.getElementById('dependant-photo-placeholder');
          if (label) label.textContent=file.name;
        }
      } else {
        alert('Choose a JPG, PNG, WebP image or PDF document.');
        input.value='';
      }
    });
  });
  zoom.addEventListener('input',()=>{zoomValue=Number(zoom.value);draw();});
  canvas.addEventListener('pointerdown',e=>{drag={x:e.clientX,y:e.clientY,x0:offsetX,y0:offsetY};canvas.setPointerCapture(e.pointerId);});
  canvas.addEventListener('pointermove',e=>{if(!drag)return;offsetX=drag.x0+(e.clientX-drag.x)*canvas.width/canvas.clientWidth;offsetY=drag.y0+(e.clientY-drag.y)*canvas.height/canvas.clientHeight;draw();});
  ['pointerup','pointercancel','lostpointercapture'].forEach(evt=>canvas.addEventListener(evt,()=>drag=null));
  function cancelCrop(){ cropModal.classList.add('hidden'); cropModal.classList.remove('flex'); activeImage=null; activeInput=null; activeThumbnail=null; }
  document.getElementById('dependant-crop-close')?.addEventListener('click',cancelCrop);
  document.getElementById('dependant-crop-cancel')?.addEventListener('click',cancelCrop);
  document.getElementById('dependant-crop-save')?.addEventListener('click',()=>{
    if(!activeImage||!activeInput)return;
    const input=activeInput, thumbnail=activeThumbnail;
    canvas.toBlob(blob=>{
      if(!blob){alert('Could not crop this image.');return;}
      const cropped=new File([blob],'cropped-image.jpg',{type:'image/jpeg'});
      const transfer=new DataTransfer();transfer.items.add(cropped);input.files=transfer.files;
      if(thumbnail){thumbnail.src=URL.createObjectURL(cropped);thumbnail.classList.remove('hidden');}
      const filename=document.getElementById(input.dataset.thumbnail?.replace('-thumbnail','-filename'));
      if(filename) filename.textContent='Cropped image ready';
      const placeholder=document.getElementById('dependant-photo-placeholder');
      if(input.id==='dependant-photo-input'&&placeholder) placeholder.classList.add('hidden');
      cropModal.classList.add('hidden');cropModal.classList.remove('flex');activeImage=null;activeInput=null;activeThumbnail=null;
    },'image/jpeg',0.92);
  });
})();

</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Member\resources/views/organization/member-profile.blade.php ENDPATH**/ ?>