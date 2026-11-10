<div>
    <div class="page-head"><div><h1>WhatsApp usage</h1><div class="muted">Saflivo's tenant-attributed WhatsApp send ledger.</div></div><a class="btn secondary" href="/system/whatsapp-settings">WhatsApp settings</a></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitActionMessage !== ''): ?><div class="notice" role="status"><?php echo e($limitActionMessage); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="long-page-layout">
        <?php echo $__env->make('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'usage'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="long-page-content">
    <div class="actions whatsapp-section-jumps" aria-label="WhatsApp usage page sections">
        <a class="btn secondary" href="#wa-monthly-limits">Organization limits</a>
        <a class="btn secondary" href="#wa-pending-requests">Pending requests</a>
        <a class="btn secondary" href="#wa-limit-audit">Limit audit history</a>
        <a class="btn secondary" href="#wa-usage-notes">How to read usage</a>
    </div>
    <section class="card" id="wa-monthly-limits">
        <h2>Organization monthly limits</h2>
        <p class="muted">Limits apply across Saflivo-default and organization-owned WhatsApp channels. Organizations without a configured limit remain unlimited.</p>
        <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Monthly limit</th><th>Warning</th><th>Action</th></tr></thead><tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-limit-'.e($organization->id).''; ?>wire:key="wa-limit-<?php echo e($organization->id); ?>">
                    <td><?php echo e($organization->name); ?></td>
                    <td>
                        <label><input type="checkbox" wire:model.live="limits.<?php echo e($organization->id); ?>.unlimited"> Unlimited</label>
                        <input type="number" min="1" max="10000000" wire:model="limits.<?php echo e($organization->id); ?>.monthly_limit" <?php if($limits[$organization->id]['unlimited']): echo 'disabled'; endif; ?> aria-label="Monthly WhatsApp limit for <?php echo e($organization->name); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['limits.' . $organization->id . '.monthly_limit'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td><select wire:model="limits.<?php echo e($organization->id); ?>.warning_threshold" aria-label="Warning threshold for <?php echo e($organization->name); ?>"><option value="70">70%</option><option value="80">80%</option><option value="90">90%</option></select></td>
                    <td><button class="btn secondary" type="button" wire:click="saveOrganizationLimit(<?php echo e($organization->id); ?>)" wire:loading.attr="disabled">Save limit</button></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="card" id="wa-pending-requests">
        <h2>Pending usage-limit increase requests</h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pendingLimitRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="card" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-limit-request-'.e($request->id).''; ?>wire:key="wa-limit-request-<?php echo e($request->id); ?>">
                <h3><?php echo e($request->organization->name); ?> — <?php echo e(number_format($request->current_limit)); ?> to <?php echo e(number_format($request->requested_limit)); ?> messages/month</h3>
                <p><strong>Requested by:</strong> <?php echo e($request->requester->name ?? $request->requester->email ?? 'User'); ?> · <?php echo e($request->created_at?->format('Y-m-d H:i')); ?></p>
                <p><?php echo e($request->reason); ?></p>
                <div class="grid">
                    <div class="field"><label>Granted monthly limit (leave blank to grant requested amount)</label><input type="number" min="<?php echo e($request->current_limit + 1); ?>" max="10000000" wire:model="requestDecisions.<?php echo e($request->id); ?>.granted_limit"></div>
                    <div class="field"><label>Decision note</label><textarea wire:model="requestDecisions.<?php echo e($request->id); ?>.review_note" maxlength="2000"></textarea></div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['requestDecisions.' . $request->id . '.granted_limit'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="actions"><button class="btn" type="button" wire:click="reviewLimitRequest(<?php echo e($request->id); ?>, 'approve')">Approve increase</button><button class="btn secondary" type="button" wire:click="reviewLimitRequest(<?php echo e($request->id); ?>, 'reject')">Reject request</button></div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="muted">There are no pending usage-limit increase requests.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
    <section class="card" id="wa-limit-audit">
        <h2>Usage-limit audit history</h2>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Organization</th><th>Previous limit</th><th>New limit</th><th>Action</th><th>Changed by</th><th>Reason / note</th></tr></thead><tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $limitAudits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr><td><?php echo e($audit->created_at); ?></td><td><?php echo e($audit->organization_name); ?></td><td><?php echo e($audit->previous_limit === null ? 'Unlimited / not set' : number_format($audit->previous_limit)); ?></td><td><?php echo e($audit->new_limit === null ? 'Unlimited' : number_format($audit->new_limit)); ?></td><td><?php echo e(str_replace('_', ' ', ucfirst($audit->action))); ?></td><td><?php echo e($audit->changed_by_name ?? 'System'); ?></td><td><?php echo e($audit->reason ?: '—'); ?></td></tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="7" class="empty">No limit changes recorded yet.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="card" id="wa-usage-notes"><h2>How to interpret these figures</h2><ul><li>Saflivo records each accepted Meta send request with the organization, service category and source channel.</li><li>OTP, Utility and Marketing usage are separated to support service-level charging.</li><li>Failed API attempts are recorded separately and should not be treated as delivered/billable messages without reconciliation.</li><li>Meta does not return a per-Saflivo-organization invoice when multiple tenants share a WABA or phone number. These are Saflivo-attributed counts, not Meta's exact per-tenant costs.</li><li>Delivery/read/failure status is updated from verified Meta message-status webhooks. To reconcile delivered/billable messages and actual Meta pricing, compare WABA-level billing/analytics. The exact per-tenant charge must be calculated from Saflivo's own usage ledger and agreed rates.</li></ul></section>
        </div>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/system/whatsapp-usage.blade.php ENDPATH**/ ?>