<div>
    <div class="page-head"><div><h1><?php echo e($organization->name); ?></h1><div class="muted"><?php echo e($organization->code); ?> · <?php echo e($organization->slug); ?></div></div><div class="actions"><a class="btn secondary" href="<?php echo e(route('core.organizations.identifications', $organization)); ?>">Identification requirements</a><a class="btn secondary" href="<?php echo e(route('core.organizations.units', $organization)); ?>">Manage units</a><a class="btn secondary" href="<?php echo e(route('core.organizations.index')); ?>">← Organizations</a><button class="btn <?php echo e($organization->is_active ? 'danger' : ''); ?>" wire:click="toggleOrganizationStatus" wire:confirm="<?php echo e($organization->is_active ? 'Deactivate' : 'Activate'); ?> this organization?"><?php echo e($organization->is_active ? 'Deactivate organization' : 'Activate organization'); ?></button></div></div>
    <section class="card">
        <h2>General settings</h2>
        <p class="muted">Edit the organization identity and regional preferences.</p>
        <form wire:submit="saveOrganizationDetails">
            <div class="grid">
                <div class="field"><label for="organization-name">Organization name</label><input id="organization-name" type="text" wire:model="name" required maxlength="255"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="field"><label for="organization-timezone">Time zone</label><input id="organization-timezone" type="text" wire:model="timezone" required placeholder="Asia/Kolkata"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['timezone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="field"><label for="organization-currency">Currency (ISO 4217)</label><input id="organization-currency" type="text" wire:model="currency" required minlength="3" maxlength="3" placeholder="INR"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['currency'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div><div class="muted">Status</div><span class="pill <?php echo e($organization->is_active ? 'ok' : 'off'); ?>"><?php echo e($organization->is_active ? 'Active' : 'Inactive'); ?></span></div>
            </div>
            <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveOrganizationDetails"><span wire:loading.remove wire:target="saveOrganizationDetails">Save organization details</span><span wire:loading wire:target="saveOrganizationDetails">Saving…</span></button></div>
        </form>
    </section>

    <section class="card">
        <h2>Organization UI theme</h2>
        <p class="muted">Choose the appearance used by the shared admin interface for this organization.</p>
        <form wire:submit="saveTheme">
            <div class="field">
                <label for="organization-theme">Preferred theme</label>
                <select id="organization-theme" wire:model="theme">
                    <option value="system">Use device preference</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['theme'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveTheme">
                <span wire:loading.remove wire:target="saveTheme">Save theme</span>
                <span wire:loading wire:target="saveTheme">Saving…</span>
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Organization branding</h2>
        <p class="muted">Upload a logo and favicon for organization-specific branding. PNG, JPG, JPEG or WebP logo (up to 2 MB); PNG or ICO favicon (up to 1 MB).</p>
        <form wire:submit="saveBranding">
            <div class="grid">
                <div class="field"><label for="organization-logo">Logo</label><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?><img src="<?php echo e($logoUrl); ?>" alt="Current organization logo" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><input id="organization-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logo && method_exists($logo, 'temporaryUrl')): ?><img src="<?php echo e($logo->temporaryUrl()); ?>" alt="Logo preview" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="field"><label for="organization-favicon">Favicon</label><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($faviconUrl): ?><img src="<?php echo e($faviconUrl); ?>" alt="Current organization favicon" style="width:40px;height:40px;object-fit:contain"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><input id="organization-favicon" type="file" wire:model="favicon" accept="image/png,.ico"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['favicon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            </div>
            <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveBranding,logo,favicon"><span wire:loading.remove wire:target="saveBranding">Save branding</span><span wire:loading wire:target="saveBranding">Uploading…</span></button><span class="muted" wire:loading wire:target="logo,favicon">Preparing upload…</span></div>
        </form>
    </section>

    <section class="card">
        <h2>Organization Super Admin</h2>
        <p class="muted">Update the existing assigned Super Admin account. This does not replace the user or change their role assignment.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSuperAdmin): ?>
            <form wire:submit="saveSuperAdminDetails">
                <div class="grid">
                    <div class="field"><label for="super-admin-name">Full name</label><input id="super-admin-name" type="text" wire:model="superAdminName" required maxlength="255"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['superAdminName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="field"><label for="super-admin-email">Email address</label><input id="super-admin-email" type="email" wire:model="superAdminEmail" required maxlength="255"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['superAdminEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                </div>
                <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveSuperAdminDetails"><span wire:loading.remove wire:target="saveSuperAdminDetails">Save Super Admin details</span><span wire:loading wire:target="saveSuperAdminDetails">Saving…</span></button></div>
            </form>
        <?php else: ?>
            <p class="muted">No active Super Admin assignment was found for this organization.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    <section class="card"><h2>Module management</h2><p class="muted">This section is System Admin-only. Required modules remain locked.</p><div class="table-wrap"><table><thead><tr><th>Module</th><th>Requirement</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><tr><td><strong><?php echo e($module['module']); ?></strong></td><td><?php echo e($module['is_required'] ? 'Required' : 'Optional'); ?></td><td><span class="pill <?php echo e($module['is_enabled'] ? 'ok' : 'off'); ?>"><?php echo e($module['is_enabled'] ? 'Enabled' : 'Disabled'); ?></span></td><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($module['is_required']): ?><span class="muted">Locked</span><?php else: ?><button class="btn secondary" wire:click="toggleModule('<?php echo e($module['module']); ?>')" wire:confirm="Change the <?php echo e($module['module']); ?> module state?"><?php echo e($module['is_enabled'] ? 'Disable' : 'Enable'); ?></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </tbody></table></div></section>
    <section class="card"><h2>System-default notification access</h2><p class="muted">Only System Admin can grant this access. It does not expose or display provider credentials.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['sms' => 'SMS', 'whatsapp' => 'WhatsApp']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php ($allowed = (bool) ($channels->get($channel)?->use_system_default ?? false)); ?>
            <div class="checkrow"><div style="flex:1"><strong><?php echo e($label); ?></strong><div class="muted"><?php echo e($allowed ? 'System-default access allowed' : 'System-default access not allowed'); ?></div></div><button class="btn secondary" wire:click="setSystemDefaultAccess('<?php echo e($channel); ?>', <?php echo e($allowed ? 'false' : 'true'); ?>)"><?php echo e($allowed ? 'Revoke access' : 'Grant access'); ?></button></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/organizations/manage.blade.php ENDPATH**/ ?>