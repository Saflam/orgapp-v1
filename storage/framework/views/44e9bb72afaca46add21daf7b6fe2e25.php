<?php $__env->startComponent('core::layouts.organization-admin', ['layoutOrganization' => $organization, 'pageTitle' => 'Former & Inactive Trustees']); ?>
<div class="committee-page">
    <header class="committee-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="committee-eyebrow">Committee management</p>
            <h1>Former &amp; Inactive Trustees</h1>
            <p class="committee-page-description">Historical trustee records, including removals and automatic deactivation after a member status change.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo e(route('committee.trustees.index')); ?>" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">Active Trustees</a>
            <a href="<?php echo e(route('committee.trustees.history.export', request()->query())); ?>" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Export PDF</a>
        </div>
    </header>

    <nav class="mt-5 flex flex-wrap gap-2" aria-label="Trustee history filters">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['all' => 'All history', 'removed' => 'Former trustees', 'inactive' => 'Inactive trustees']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <a href="<?php echo e(route('committee.trustees.history', array_merge(request()->query(), ['status' => $value, 'page' => null]))); ?>" class="rounded-full border px-4 py-2 text-sm font-medium <?php echo e($historyStatus === $value ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700'); ?>"><?php echo e($label); ?></a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </nav>

    <section class="committee-form-card mt-5">
        <form method="GET" action="<?php echo e(route('committee.trustees.history')); ?>" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="status" value="<?php echo e($historyStatus); ?>">
            <div class="min-w-[240px] flex-1">
                <label for="trustee-history-search" class="mb-1 block text-sm font-medium">Search trustees</label>
                <input id="trustee-history-search" type="search" name="search" value="<?php echo e($search); ?>" placeholder="Trustee ID, member name or membership number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== ''): ?><a href="<?php echo e(route('committee.trustees.history', ['status' => $historyStatus])); ?>" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Clear</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </section>

    <section class="committee-table-card mt-5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr><th class="px-4 py-3">Trustee ID</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Membership number</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Last appointment</th><th class="px-4 py-3">End date</th><th class="px-4 py-3">Record status</th><th class="px-4 py-3">Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $trustees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trustee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php ($appointment = $trustee->appointments->first()); ?>
                        <?php ($appointmentStatus = $appointment?->status?->value ?? 'inactive'); ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold"><?php echo e($trustee->trustee_id); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->user?->name ?: 'Unnamed member'); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->membership_number ?: '—'); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->unit?->name ?: '—'); ?></td>
                            <td class="px-4 py-3"><?php echo e($appointment?->appointed_at?->format('d M Y') ?: '—'); ?></td>
                            <td class="px-4 py-3"><?php echo e($appointment?->ended_at?->format('d M Y') ?: '—'); ?></td>
                            <td class="px-4 py-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($appointmentStatus === 'removed'): ?><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Former</span>
                                <?php else: ?><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Inactive</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="max-w-sm whitespace-normal px-4 py-3"><?php echo e($appointment?->end_reason ?: '—'); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No former or inactive trustees found.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trustees->hasPages()): ?><div class="border-t border-gray-200 px-4 py-3"><?php echo e($trustees->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
</div>

<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Committee\resources/views/organization/trustees/history.blade.php ENDPATH**/ ?>