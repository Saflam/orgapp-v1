<div id="saradhi-profile-edit" class="<?php echo e($errors->any() ? '' : 'hidden'); ?> mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <h4 class="font-semibold text-slate-900">Update Saradhi details</h4>
    <form method="POST" action="<?php echo e(route('organization.member.profile.module.update', ['module' => $module])); ?>" class="mt-3 space-y-4">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700">Governorate
                <input name="governorate" maxlength="255" value="<?php echo e(old('governorate', $profile->governorate)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">SNDP branch
                <input name="sndp_branch" maxlength="255" value="<?php echo e(old('sndp_branch', $profile->sndp_branch)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">SNDP branch number
                <input name="sndp_branch_number" maxlength="255" value="<?php echo e(old('sndp_branch_number', $profile->sndp_branch_number)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">SNDP union
                <input name="sndp_union" maxlength="255" value="<?php echo e(old('sndp_union', $profile->sndp_union)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Introducer name
                <input name="introducer_name" maxlength="255" value="<?php echo e(old('introducer_name', $profile->introducer_name)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Introducer MID
                <input name="introducer_mid" maxlength="255" value="<?php echo e(old('introducer_mid', $profile->introducer_mid)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Introducer calling code
                <input name="introducer_calling_code" maxlength="8" placeholder="+91" value="<?php echo e(old('introducer_calling_code', $profile->introducer_calling_code)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Introducer phone
                <input name="introducer_phone" maxlength="30" value="<?php echo e(old('introducer_phone', $profile->introducer_phone)); ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Introducer unit
                <select name="introducer_unit_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="">No unit selected</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($unit->id); ?>" <?php if((string) old('introducer_unit_id', $profile->introducer_unit_id) === (string) $unit->id): echo 'selected'; endif; ?>><?php echo e($unit->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </label>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" onclick="document.getElementById('saradhi-profile-edit').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
            <button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-bold text-white" style="background:var(--org-primary)">Save changes</button>
        </div>
    </form>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Saradhi\resources/views/organization/member-profile-section-edit.blade.php ENDPATH**/ ?>