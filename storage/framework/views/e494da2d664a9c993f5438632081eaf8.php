<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div>
            <h1>Edit designation</h1>
            <p class="committee-page-description">Update the designation details and configure the permissions assigned to holders of this position.</p>
        </div>
        <div class="committee-page-actions">
            <a href="<?php echo e(route('committee.designations')); ?>" class="committee-button committee-button-secondary">← Back to designations</a>
        </div>
    </header>

    <form wire:submit="save" class="committee-form-grid">
        <section class="committee-form-card committee-form-field-full">
            <h2>Designation details</h2>
            <div class="committee-form-grid">
                <div class="committee-form-field">
                    <label for="designation-name">Designation name</label>
                    <input id="designation-name" wire:model="name" class="committee-search-input" required>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="committee-form-field">
                    <label for="designation-scope">Scope</label>
                    <select id="designation-scope" wire:model="designationScope" class="committee-select">
                        <option value="central">Central</option><option value="unit">Unit</option>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['designationScope'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="committee-form-field">
                    <label for="designation-sort-order">Display order</label>
                    <input id="designation-sort-order" type="number" min="1" max="9999" wire:model="sortOrder" class="committee-search-input" required>
                    <small class="committee-muted">Lower numbers appear first in committee member lists.</small>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['sortOrder'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="committee-form-field">
                    <label class="committee-check-label" for="designation-key-position"><input id="designation-key-position" type="checkbox" wire:model="isKeyPosition"> Key position</label>
                    <small class="committee-muted">Highlight important offices such as President or Secretary.</small>
                </div>
                <div class="committee-form-field committee-form-field-full">
                    <label for="designation-description">Description (optional)</label>
                    <textarea id="designation-description" wire:model="description" rows="3" class="committee-search-input"></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManagePermissions): ?>
            <section class="committee-form-card committee-form-field-full">
                <div class="committee-page-header-row" style="align-items:flex-start;gap:1rem">
                    <div>
                        <h2>Designation permissions</h2>
                        <p class="committee-muted">Permissions are grouped by module. Expand a module to review its permissions. Only enabled modules, Core, and Member are included.</p>
                    </div>
                    <div style="min-width:min(100%,280px)">
                        <label class="committee-sr-only" for="permission-search">Search permissions</label>
                        <input id="permission-search" type="search" wire:model.live.debounce.250ms="permissionSearch" placeholder="Search name, code, or module…" class="committee-search-input">
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($permissionsByModule->isEmpty()): ?>
                    <p class="committee-muted">No permissions match your search, or no permissions are available from enabled modules.</p>
                <?php else: ?>
                    <div style="display:grid;gap:.75rem;margin-top:1rem">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissionsByModule; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $modulePermissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <details open style="border:1px solid var(--committee-border,#ddd);border-radius:.65rem;overflow:hidden">
                                <summary style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;background:var(--committee-surface,#f8fafc);font-weight:700">
                                    <span><?php echo e($module); ?></span>
                                    <span class="committee-muted" style="font-size:.85rem;font-weight:500"><?php echo e($modulePermissions->count()); ?> permissions · <?php echo e($modulePermissions->whereIn('id', $selectedPermissionIds)->count()); ?> selected</span>
                                </summary>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:.6rem;padding:.85rem">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $modulePermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <label class="committee-check-label" style="align-items:flex-start;border:1px solid var(--committee-border,#ddd);border-radius:.5rem;padding:.7rem;gap:.55rem">
                                            <input type="checkbox" wire:model="selectedPermissionIds" value="<?php echo e($permission->id); ?>">
                                            <span>
                                                <strong><?php echo e($permission->name); ?></strong><br>
                                                <small class="committee-muted"><?php echo e($permission->code); ?></small>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($permission->description): ?><br><small><?php echo e($permission->description); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </span>
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedPermissionIds.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="committee-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
        <?php else: ?>
            <section class="committee-form-card committee-form-field-full">
                <h2>Designation permissions</h2>
                <p class="committee-muted">You can edit designation details, but you do not have permission to change its permission assignments.</p>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="committee-form-actions committee-form-field-full">
            <button type="submit" class="committee-button committee-button-primary">Save changes</button>
            <a href="<?php echo e(route('committee.designations')); ?>" class="committee-button committee-button-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Committee\resources/views/livewire/designation-edit.blade.php ENDPATH**/ ?>