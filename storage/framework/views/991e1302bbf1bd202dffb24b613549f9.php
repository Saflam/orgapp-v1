<div>
    <div class="page-head"><div><h1>Add organization</h1><div class="muted">Create the organization and its initial administrator.</div></div><a href="<?php echo e(route('core.organizations.index')); ?>">← Back to organizations</a></div>
    <ol class="steps"><li class="<?php echo e($step === 1 ? 'current' : ''); ?>">1. Details</li><li class="<?php echo e($step === 2 ? 'current' : ''); ?>">2. Super Admin</li><li class="<?php echo e($step === 3 ? 'current' : ''); ?>">3. Modules & access</li><li class="<?php echo e($step === 4 ? 'current' : ''); ?>">4. Review</li></ol>
    <form wire:submit="<?php echo e($step === 4 ? 'create' : 'next'); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 1): ?>
            <section class="card"><h2>Organization details</h2>
                <div class="field"><label>Organization name</label><input wire:model="organization.name" autocomplete="organization"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['organization.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
                <div class="grid"><div class="field"><label>Organization code</label><input wire:model="organization.code" autocomplete="off"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['organization.code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div><div class="field"><label>Timezone</label><input wire:model="organization.timezone" placeholder="Asia/Kolkata"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['organization.timezone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div></div>
                <div class="field"><label>Currency (ISO 4217)</label><input wire:model="organization.currency" maxlength="3" placeholder="INR"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['organization.currency'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
            </section>
        <?php elseif($step === 2): ?>
            <section class="card"><h2>Initial Organization Super Admin</h2><p class="muted">The initial administrator will be provisioned for this organization.</p>
                <div class="field"><label>Full name</label><input wire:model="super_admin.name" autocomplete="name"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['super_admin.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
                <div class="grid"><div class="field"><label>Email</label><input type="email" wire:model="super_admin.email" autocomplete="email"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['super_admin.email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div><div class="field"><label>Phone (optional)</label><input wire:model="super_admin.phone" autocomplete="tel"><div class="error"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['super_admin.phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><?php echo e($message); ?><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div></div>
            </section>
        <?php elseif($step === 3): ?>
            <section class="card"><h2>Module activation</h2><p class="muted">Core and Member are mandatory and cannot be disabled. Optional modules start disabled unless selected here.</p>
                <div class="checkrow"><input type="checkbox" checked disabled id="module-core"><label for="module-core"><strong>Core</strong> — Required module</label></div>
                <div class="checkrow"><input type="checkbox" checked disabled id="module-member"><label for="module-member"><strong>Member</strong> — Required module</label></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $availableModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="checkrow"><input type="checkbox" id="module-<?php echo e($module); ?>" value="<?php echo e($module); ?>" wire:model="modules"><label for="module-<?php echo e($module); ?>"><?php echo e($module); ?></label></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> <p class="muted">No optional modules are currently available.</p> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
            <section class="card"><h2>System-default notification access</h2><p class="muted">Only the System Admin can grant or revoke an organization's access to system-configured SMS and WhatsApp channels. Organization Super Admins cannot change these access flags.</p>
                <div class="checkrow"><input id="access-sms" type="checkbox" wire:model="notification_channels.sms.use_system_default"><label for="access-sms">Allow system-default SMS for this organization</label></div>
                <div class="checkrow"><input id="access-whatsapp" type="checkbox" wire:model="notification_channels.whatsapp.use_system_default"><label for="access-whatsapp">Allow system-default WhatsApp for this organization</label></div>
            </section>
        <?php else: ?>
            <section class="card"><h2>Review organization</h2>
                <h3>Organization</h3><p><strong><?php echo e($organization['name']); ?></strong> · <?php echo e($organization['code']); ?></p><p class="muted"><?php echo e($organization['timezone']); ?> · <?php echo e(strtoupper($organization['currency'])); ?></p>
                <h3>Initial Super Admin</h3><p><?php echo e($super_admin['name']); ?> · <?php echo e($super_admin['email']); ?></p>
                <h3>Modules</h3><p>Core (required), Member (required)<?php echo e(count($modules) ? ', ' . implode(', ', $modules) : ''); ?></p>
                <h3>System-default notification access</h3><p>SMS: <?php echo e($notification_channels['sms']['use_system_default'] ? 'Allowed' : 'Not allowed'); ?> · WhatsApp: <?php echo e($notification_channels['whatsapp']['use_system_default'] ? 'Allowed' : 'Not allowed'); ?></p>
                <p class="muted">Organization creation provisions the Super Admin and initializes modules and notification access in a database transaction.</p>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="actions"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step > 1): ?><button class="btn secondary" type="button" wire:click="back">Back</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step < 4): ?><button class="btn" type="submit">Continue</button><?php else: ?><button class="btn" type="submit" wire:loading.attr="disabled">Create organization</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <span wire:loading class="muted">Processing…</span></div>
    </form>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/organizations/wizard.blade.php ENDPATH**/ ?>