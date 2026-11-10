<div>
    <div class="page-head">
        <div><h1>WhatsApp usage records</h1><div class="muted">Accepted sends and delivery outcomes, filterable by organization and period.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        <?php echo $__env->make('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'records'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="long-page-content">
            <section class="card">
                <h2>Accepted sends in selected period</h2>
                <p class="muted"><?php echo e(number_format($totalAccepted)); ?> accepted send records match the selected filters.</p>
                <div class="grid">
                    <div class="field"><label for="usage-organization">Organization</label><select id="usage-organization" wire:model.live="organizationId"><option value="">All organizations</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($organization->id); ?>"><?php echo e($organization->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="usage-from">From</label><input id="usage-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="usage-to">To</label><input id="usage-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
                <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Service</th><th>Channel source</th><th>API status</th><th>Delivery status</th><th>Messages</th></tr></thead><tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'wa-usage-row-'.e(md5(($row->organization_id ?? 'central').'-'.$row->service_category.'-'.$row->channel_source.'-'.$row->status.'-'.($row->delivery_status ?? 'unknown'))).''; ?>wire:key="wa-usage-row-<?php echo e(md5(($row->organization_id ?? 'central').'-'.$row->service_category.'-'.$row->channel_source.'-'.$row->status.'-'.($row->delivery_status ?? 'unknown'))); ?>"><td><?php echo e($row->organization_name ?? 'Unattributed / central'); ?></td><td><?php echo e(strtoupper($row->service_category)); ?></td><td><?php echo e(ucfirst($row->channel_source)); ?></td><td><?php echo e(ucfirst($row->status)); ?></td><td><?php echo e($row->delivery_status ? ucfirst($row->delivery_status) : 'Awaiting webhook'); ?></td><td><?php echo e(number_format($row->message_count)); ?></td></tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="6" class="empty">No WhatsApp usage records match these filters.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody></table></div>
                <div class="pagination-wrap"><?php echo e($rows->links()); ?></div>
            </section>
        </div>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/system/whatsapp-usage-records.blade.php ENDPATH**/ ?>