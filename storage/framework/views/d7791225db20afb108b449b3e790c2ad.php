<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Designations</h1><p class="committee-page-description">Committee positions and their assigned permission counts.</p></div>
        <div class="committee-page-actions">
            <a href="<?php echo e(route('committee.dashboard')); ?>" class="committee-button committee-button-secondary"><span aria-hidden="true">←</span> Committee overview</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?><button type="button" wire:click="create" class="committee-button committee-button-primary"><span aria-hidden="true">+</span> Create designation</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </header>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('committee_message')): ?><div class="committee-alert committee-alert-success"><?php echo e(session('committee_message')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateForm): ?>
        <section class="committee-form-card">
            <h2><?php echo e($editingDesignationId ? 'Edit designation' : 'Create designation'); ?></h2>
            <form wire:submit="save" class="committee-form-grid">
                <div class="committee-form-field"><label for="designation-name">Designation name</label><input id="designation-name" wire:model.live="name" class="committee-search-input" required><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="committee-form-field"><label for="designation-scope">Scope</label><select id="designation-scope" wire:model="designationScope" class="committee-select"><option value="central">Central</option><option value="unit">Unit</option></select><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['designationScope'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="committee-form-field"><label for="designation-sort-order">Display order</label><input id="designation-sort-order" type="number" min="1" max="9999" wire:model="sortOrder" class="committee-search-input" required><small class="committee-muted">Lower numbers appear first in committee member lists.</small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['sortOrder'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <div class="committee-form-field"><label class="committee-check-label" for="designation-key-position"><input id="designation-key-position" type="checkbox" wire:model="isKeyPosition"> Key position</label><small class="committee-muted">Highlight important offices such as President or Secretary.</small></div>
                <div class="committee-form-field committee-form-field-full"><label for="designation-description">Description (optional)</label><textarea id="designation-description" wire:model="description" rows="3" class="committee-search-input"></textarea><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManagePermissions): ?>
                    <div class="committee-form-field committee-form-field-full">
                        <label>Designation permissions</label>
                        <p class="committee-muted">Only permissions belonging to modules enabled for this organization are listed. These permissions are granted to members holding this designation on an active committee term, subject to organization and unit scope checks.</p>
                        <div style="max-width:420px;margin:.75rem 0">
                            <label class="committee-sr-only" for="create-permission-search">Search permissions</label>
                            <input id="create-permission-search" type="search" wire:model.live.debounce.250ms="permissionSearch" placeholder="Search name, code, or module…" class="committee-search-input">
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($permissionsByModule->isEmpty()): ?>
                            <p class="committee-muted">No permissions match your search, or no permissions are available from enabled modules.</p>
                        <?php else: ?>
                            <div style="display:grid;gap:.65rem;margin-top:.5rem">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissionsByModule; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $modulePermissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <details open style="border:1px solid var(--committee-border,#ddd);border-radius:.6rem;overflow:hidden">
                                        <summary style="cursor:pointer;display:flex;justify-content:space-between;gap:1rem;padding:.75rem 1rem;background:var(--committee-surface,#f8fafc);font-weight:700">
                                            <span><?php echo e($module); ?></span><span class="committee-muted" style="font-size:.85rem;font-weight:500"><?php echo e($modulePermissions->count()); ?> permissions · <?php echo e($modulePermissions->whereIn('id', $selectedPermissionIds)->count()); ?> selected</span>
                                        </summary>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:.6rem;padding:.75rem">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $modulePermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <label class="committee-check-label" style="align-items:flex-start;border:1px solid var(--committee-border,#ddd);border-radius:.5rem;padding:.65rem;gap:.5rem">
                                                    <input type="checkbox" wire:model="selectedPermissionIds" value="<?php echo e($permission->id); ?>">
                                                    <span><strong><?php echo e($permission->name); ?></strong><br><small class="committee-muted"><?php echo e($permission->code); ?></small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($permission->description): ?><br><small><?php echo e($permission->description); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                                </label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    </details>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedPermissionIds'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php elseif($editingDesignationId): ?>
                    <div class="committee-form-field committee-form-field-full"><p class="committee-muted">You can edit designation details, but you do not have permission to change its permission assignments.</p></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="committee-form-actions"><button type="submit" class="committee-button committee-button-primary"><?php echo e($editingDesignationId ? 'Update designation' : 'Save designation'); ?></button><button type="button" wire:click="cancelCreate" class="committee-button">Cancel</button></div>
            </form>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <section class="committee-filter-card committee-filter-row">
        <div class="committee-filter-field">
            <label class="committee-sr-only" for="designation-search">Search designations</label>
            <input id="designation-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search designations…" class="committee-search-input">
        </div>
        <div class="committee-filter-field committee-scope-field">
            <label class="committee-sr-only" for="designation-scope">Scope</label>
            <select id="designation-scope" wire:model.live="scope" class="committee-select"><option value="all">All scopes</option><option value="central">Central</option><option value="unit">Unit</option></select>
        </div>
    </section>
    <section class="committee-table-card">
        <div class="committee-table-scroll"><table class="committee-table">
            <thead><tr><th scope="col">Order</th><th scope="col">Designation</th><th scope="col">Scope</th><th scope="col">Permissions</th><th scope="col">Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?><th scope="col">Actions</th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $designation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr><td><?php echo e($designation->sort_order); ?></td><td class="committee-primary-cell"><?php echo e($designation->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($designation->is_key_position): ?><span class="committee-status is-key-position">Key position</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td><?php echo e(ucfirst($designation->scope?->value ?? (string) $designation->scope)); ?></td><td><?php echo e($designation->permissions_count); ?></td><td><span class="committee-status <?php echo e($designation->is_active ? 'is-active' : 'is-inactive'); ?>"><?php echo e($designation->is_active ? 'Active' : 'Inactive'); ?></span></td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?><td><a href="<?php echo e(route('committee.designations.edit', $designation->id)); ?>" class="committee-button committee-button-secondary">Edit</a></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="<?php echo e($canManage ? 6 : 5); ?>" class="committee-empty-state">No designations found for this organization.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table></div>
        <div class="committee-pagination"><?php echo e($designations->links()); ?></div>
    </section>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Committee\resources/views/livewire/designations.blade.php ENDPATH**/ ?>