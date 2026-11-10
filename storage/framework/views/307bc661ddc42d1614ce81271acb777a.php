<div>
    <div class="page-head">
        <div><h1>WhatsApp settings</h1><div class="muted">Configure Saflivo's default Meta Cloud API channel and approve organization service access.</div></div>
        <div class="actions page-head-actions"><a class="btn secondary" href="/system/whatsapp-message-history">Message history</a><a class="btn secondary" href="/system/whatsapp-templates">WhatsApp template guide</a></div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage !== ''): ?><div class="notice" role="status"><?php echo e($successMessage); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($connectionTestMessage !== ''): ?><div class="<?php echo e($connectionTestStatus === 'success' ? 'notice' : 'error'); ?>" role="status"><strong><?php echo e($connectionTestStatus === 'success' ? 'Connection test passed' : 'Connection test failed'); ?>:</strong> <?php echo e($connectionTestMessage); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($connectionTestPhoneNumber): ?> <div>Meta phone: <?php echo e($connectionTestPhoneNumber); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($connectionTestVerifiedName): ?> · Verified name: <?php echo e($connectionTestVerifiedName); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="long-page-layout">
        <?php echo $__env->make('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'settings'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="long-page-content">
    <section class="card">
        <h2>Saflivo default WhatsApp channel</h2>
        <p class="muted">Credentials are encrypted. The access token is never displayed after saving; leave it blank to retain the stored token.</p>
        <form wire:submit="saveDefaultChannel">
            <div class="checkrow"><input id="wa-default-enabled" type="checkbox" wire:model="isEnabled"><label for="wa-default-enabled">Enable Saflivo's default WhatsApp channel</label></div>
            <div class="grid">
                <div class="field"><label for="wa-phone-id">Phone Number ID</label><input id="wa-phone-id" wire:model="phoneNumberId" autocomplete="off"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['phoneNumberId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="field"><label for="wa-waba-id">WhatsApp Business Account ID (WABA ID)</label><input id="wa-waba-id" wire:model="wabaId" autocomplete="off"></div>
                <div class="field"><label for="wa-token">Permanent/system-user access token</label><input id="wa-token" type="password" wire:model="accessToken" autocomplete="new-password" placeholder="<?php echo e($hasAccessToken ? 'Saved token exists — enter only to replace' : 'Paste Meta access token'); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['accessToken'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="field"><label for="wa-version">Graph API version</label><input id="wa-version" wire:model="apiVersion" placeholder="v23.0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['apiVersion'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            </div>
            <h3>Authentication (OTP) template — required for WhatsApp OTP</h3>
            <div class="grid"><div class="field"><label for="wa-otp-template">Approved Authentication template name</label><input id="wa-otp-template" wire:model="otpTemplateName"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['otpTemplateName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><div class="field"><label for="wa-otp-language">Template language</label><input id="wa-otp-language" wire:model="otpTemplateLanguage"></div></div>
            <h3>Utility template</h3>
            <div class="grid"><div class="field"><label for="wa-utility-template">Approved Utility template name</label><input id="wa-utility-template" wire:model="utilityTemplateName"></div><div class="field"><label for="wa-utility-language">Template language</label><input id="wa-utility-language" wire:model="utilityTemplateLanguage"></div></div>
            <h3>Marketing template</h3>
            <div class="grid"><div class="field"><label for="wa-marketing-template">Approved Marketing template name</label><input id="wa-marketing-template" wire:model="marketingTemplateName" placeholder="Configure when marketing is approved"></div><div class="field"><label for="wa-marketing-language">Template language</label><input id="wa-marketing-language" wire:model="marketingTemplateLanguage"></div></div>
            <div class="actions"><button class="btn secondary" type="button" wire:click="testDefaultConnection" wire:loading.attr="disabled">Test connection</button><button class="btn" type="submit" wire:loading.attr="disabled">Save default channel</button><span class="muted">Token stored: <?php echo e($hasAccessToken ? 'Yes' : 'No'); ?></span></div><p class="muted">Test Connection makes a read-only Meta API request. It does not send a message or consume WhatsApp message quota. Unsaved form values are tested; a blank token uses the saved token.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [''];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </section>
    <section class="card">
        <h2>Organization WhatsApp permissions</h2>
        <p class="muted">Allowing WhatsApp includes OTP. Utility and Marketing remain disabled until separately approved. Saflivo's default channel is only available to an organization when both WhatsApp access and default-channel access are allowed.</p>
        <div class="table-wrap"><table><thead><tr><th>Organization</th><th>WhatsApp feature / OTP</th><th>Saflivo default</th><th>Utility</th><th>Marketing</th><th>Action</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \Modules\Core\Models\Organization::query()->orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'whatsapp-org-'.e($organization->id).''; ?>wire:key="whatsapp-org-<?php echo e($organization->id); ?>"><td><?php echo e($organization->name); ?><div class="muted"><?php echo e($organization->slug); ?></div></td>
                <td><input aria-label="Allow WhatsApp for <?php echo e($organization->name); ?>" type="checkbox" wire:model="permissions.<?php echo e($organization->id); ?>.is_allowed"></td>
                <td><input aria-label="Allow default channel for <?php echo e($organization->name); ?>" type="checkbox" wire:model="permissions.<?php echo e($organization->id); ?>.use_system_default" <?php if(!($permissions[$organization->id]['is_allowed'] ?? false)): echo 'disabled'; endif; ?>></td>
                <td><input aria-label="Approve Utility for <?php echo e($organization->name); ?>" type="checkbox" wire:model="permissions.<?php echo e($organization->id); ?>.utility_allowed" <?php if(!($permissions[$organization->id]['is_allowed'] ?? false)): echo 'disabled'; endif; ?>></td>
                <td><input aria-label="Approve Marketing for <?php echo e($organization->name); ?>" type="checkbox" wire:model="permissions.<?php echo e($organization->id); ?>.marketing_allowed" <?php if(!($permissions[$organization->id]['is_allowed'] ?? false)): echo 'disabled'; endif; ?>></td>
                <td><button class="btn secondary" type="button" wire:click="saveOrganizationPermissions(<?php echo e($organization->id); ?>)">Save</button></td></tr>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\Modules\Core\Models\Organization::query()->count() === 0): ?><tr><td colspan="6" class="empty">No organizations have been created yet.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="card"><h2>Usage and charging</h2><p>Open <a href="/system/whatsapp-usage"><strong>WhatsApp usage</strong></a> to see internally attributed message counts by organization, service and channel source. Saflivo records these sends for service-level charging. This is not a Meta-provided per-tenant bill; Meta account billing must be reconciled separately.</p></section>
        </div>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/system/whatsapp-settings.blade.php ENDPATH**/ ?>