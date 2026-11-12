<?php $__env->startComponent('core::layouts.organization-admin', ['layoutOrganization' => $organization, 'pageTitle' => 'Active Trustees']); ?>
<div class="committee-page">
    <header class="committee-page-header flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="committee-eyebrow">Committee management</p>
            <h1>Active Trustees</h1>
            <p class="committee-page-description">Current trustees of <?php echo e($organization->name); ?>.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo e(route('committee.trustees.history')); ?>" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">Former &amp; Inactive Trustees</a>
            <a href="<?php echo e(route('committee.trustees.export', request()->query())); ?>" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Export PDF</a>
        </div>
    </header>

    <section class="committee-form-card mt-5">
        <form method="GET" action="<?php echo e(route('committee.trustees.index')); ?>" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] flex-1">
                <label for="trustee-search" class="mb-1 block text-sm font-medium">Search trustees</label>
                <input id="trustee-search" type="search" name="search" value="<?php echo e($search); ?>" placeholder="Trustee ID, member name or membership number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== ''): ?>
                <a href="<?php echo e(route('committee.trustees.index')); ?>" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Clear</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </section>

    <section class="committee-table-card mt-5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr><th class="px-4 py-3">Trustee ID</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Membership number</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Appointment date</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $trustees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trustee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php ($appointment = $trustee->appointments->first(fn ($item) => $item->status->value === 'active')); ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold"><?php echo e($trustee->trustee_id); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->user?->name ?: 'Unnamed member'); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->membership_number ?: '—'); ?></td>
                            <td class="px-4 py-3"><?php echo e($trustee->member?->unit?->name ?: '—'); ?></td>
                            <td class="px-4 py-3"><?php echo e($appointment?->appointed_at?->format('d M Y') ?: '—'); ?></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Active</span></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No active trustees found.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trustees->hasPages()): ?><div class="border-t border-gray-200 px-4 py-3"><?php echo e($trustees->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
</div>

<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Committee\resources/views/organization/trustees/active.blade.php ENDPATH**/ ?>