<div>
    <div class="page-head">
        <div><h1>Organizations</h1><div class="muted">Create and manage organizations across Saflivo.</div></div>
        <a class="btn" href="<?php echo e(route('core.organizations.create')); ?>">+ Add organization</a>
    </div>
    <section class="card">
        <div class="grid">
            <div class="field"><label for="org-search">Search</label><input id="org-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Name, code or slug"></div>
            <div class="field"><label for="org-status">Status</label><select id="org-status" wire:model.live="status"><option value="all">All organizations</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Organization</th><th>Code</th><th>Slug</th><th>Status</th><th>Enabled modules</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr>
                        <td><strong><?php echo e($organization->name); ?></strong></td><td><?php echo e($organization->code); ?></td><td><?php echo e($organization->slug); ?></td>
                        <td><span class="pill <?php echo e($organization->is_active ? 'ok' : 'off'); ?>"><?php echo e($organization->is_active ? 'Active' : 'Inactive'); ?></span></td>
                        <td><?php echo e($organization->enabled_modules_count); ?></td>
                        <td><div class="actions"><a class="btn secondary" href="<?php echo e(route('core.organizations.manage', $organization)); ?>">Manage</a><button class="btn secondary" wire:click="toggleStatus(<?php echo e($organization->id); ?>)" wire:confirm="<?php echo e($organization->is_active ? 'Deactivate' : 'Activate'); ?> this organization?"><?php echo e($organization->is_active ? 'Deactivate' : 'Activate'); ?></button></div></td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="6" class="empty">No organizations match these filters.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination"><?php echo e($organizations->links()); ?></div>
    </section>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Core\resources/views/livewire/organizations/index.blade.php ENDPATH**/ ?>