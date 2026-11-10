<div>
    <div class="page-head">
        <div><h1>Failed WhatsApp deliveries</h1><div class="muted">Review delivery failures across organizations. Recipient phone numbers are masked.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        <?php echo $__env->make('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'failed'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="long-page-content">
            <section class="card">
                <h2>Failed deliveries</h2>
                <div class="grid">
                    <div class="field"><label for="failed-organization">Organization</label><select id="failed-organization" wire:model.live="organizationId"><option value="">All organizations</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($organization->id); ?>"><?php echo e($organization->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="failed-from">From</label><input id="failed-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="failed-to">To</label><input id="failed-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
                <div class="table-wrap"><table><thead><tr><th>Date</th><th>Organization</th><th>Recipient</th><th>Message</th><th>Provider message ID</th><th>Failure reason</th></tr></thead><tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $failedDeliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $failed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-failed-'.e($failed->id).''; ?>wire:key="wa-failed-<?php echo e($failed->id); ?>"><td><?php echo e($failed->created_at); ?></td><td><?php echo e($failed->organization_name ?? 'Unattributed / central'); ?></td><td><?php echo e($failed->recipient_masked); ?></td><td><?php echo e($failed->title); ?></td><td><?php echo e($failed->provider_message_id ?: '—'); ?></td><td><?php echo e($failed->error ?: 'No failure details available'); ?></td></tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="6" class="empty">No failed WhatsApp deliveries match these filters.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody></table></div>
                <div class="pagination-wrap"><?php echo e($failedDeliveries->links()); ?></div>
            </section>
        </div>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/system/whatsapp-failed-deliveries.blade.php ENDPATH**/ ?>