<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">MEMBER MANAGEMENT</p>
            <h1 class="text-2xl font-bold text-gray-900">Unit Transfers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage member transfers between units with outgoing and incoming unit approvals.</p>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSubmit): ?>
            <button type="button" wire:click="openCreateModal" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">+ New transfer request</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </header>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage): ?>
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"><?php echo e($successMessage); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['action'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-semibold text-gray-900">Transfer register</h2><p class="text-xs text-gray-500">Requests visible to your organization or assigned unit permissions</p></div>
            <span class="text-sm text-gray-500"><?php echo e($transfers->total()); ?> records</span>
        </div>
        <div class="mb-4 grid w-full grid-cols-12 items-center gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search member, membership number or unit" class="col-span-8 block w-full min-w-0 rounded-lg border-gray-300 text-sm">
            <select wire:model.live="statusFilter" class="col-span-4 block w-full min-w-0 rounded-lg border-gray-300 text-sm">
                <option value="all">All statuses</option>
                <option value="submitted">Pending approvals</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-3">Member</th><th class="px-3 py-3">From unit</th><th class="px-3 py-3">To unit</th><th class="px-3 py-3">Submitted</th><th class="px-3 py-3">Approvals</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $transfers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transfer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $transferStatus = $transfer->status->value;
                        $outgoingApproval = $transfer->approvals->first(fn ($approval) => $approval->side->value === 'outgoing');
                        $incomingApproval = $transfer->approvals->first(fn ($approval) => $approval->side->value === 'incoming');
                        $statusClasses = match ($transferStatus) {
                            'completed' => 'bg-green-100 text-green-800',
                            'approved' => 'bg-blue-100 text-blue-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            'cancelled' => 'bg-gray-100 text-gray-700',
                            default => 'bg-amber-100 text-amber-800',
                        };
                    ?>
                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'unit-transfer-'.e($transfer->id).''; ?>wire:key="unit-transfer-<?php echo e($transfer->id); ?>" class="align-top">
                        <td class="px-3 py-3">
                            <div class="font-semibold text-gray-900"><?php echo e($transfer->member?->user?->name ?? 'Member #' . $transfer->member_id); ?></div>
                            <div class="text-xs text-gray-500"><?php echo e($transfer->member?->membership_number ?? '—'); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transfer->spouse_included && $transfer->spouse): ?><div class="mt-1 text-xs text-indigo-600">Spouse: <?php echo e($transfer->spouse->user?->name ?? 'Member #' . $transfer->spouse_member_id); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-3 py-3"><?php echo e($transfer->fromUnit?->name ?? '—'); ?></td>
                        <td class="px-3 py-3"><?php echo e($transfer->toUnit?->name ?? '—'); ?></td>
                        <td class="px-3 py-3 whitespace-nowrap"><?php echo e($transfer->submitted_at?->format('d M Y') ?? '—'); ?></td>
                        <td class="px-3 py-3 space-y-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [$outgoingApproval, $incomingApproval]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $approval): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($approval): ?>
                                    <div class="text-xs"><span class="text-gray-500"><?php echo e(ucfirst($approval->side->value)); ?>:</span>
                                        <span class="font-medium <?php echo e($approval->status->value === 'approved' ? 'text-green-700' : ($approval->status->value === 'rejected' ? 'text-red-700' : 'text-amber-700')); ?>"><?php echo e(ucfirst($approval->status->value)); ?></span>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </td>
                        <td class="px-3 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($statusClasses); ?>"><?php echo e(ucfirst($transferStatus)); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transfer->rejection_reason): ?><p class="mt-1 max-w-48 text-xs text-red-600"><?php echo e($transfer->rejection_reason); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex min-w-36 flex-col items-end gap-1.5">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'submitted' && $outgoingApproval?->status->value === 'pending' && $this->canDecideTransfer($transfer, 'member.unit-transfer.approve', 'outgoing')): ?>
                                    <button type="button" wire:click="approve(<?php echo e($transfer->id); ?>, 'outgoing')" class="text-xs font-semibold text-green-700 hover:underline">Approve outgoing</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'submitted' && $incomingApproval?->status->value === 'pending' && $this->canDecideTransfer($transfer, 'member.unit-transfer.approve', 'incoming')): ?>
                                    <button type="button" wire:click="approve(<?php echo e($transfer->id); ?>, 'incoming')" class="text-xs font-semibold text-green-700 hover:underline">Approve incoming</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'submitted' && $outgoingApproval?->status->value === 'pending' && $this->canDecideTransfer($transfer, 'member.unit-transfer.reject', 'outgoing')): ?>
                                    <button type="button" wire:click="openRejectModal(<?php echo e($transfer->id); ?>, 'outgoing')" class="text-xs font-semibold text-red-700 hover:underline">Reject outgoing</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'submitted' && $incomingApproval?->status->value === 'pending' && $this->canDecideTransfer($transfer, 'member.unit-transfer.reject', 'incoming')): ?>
                                    <button type="button" wire:click="openRejectModal(<?php echo e($transfer->id); ?>, 'incoming')" class="text-xs font-semibold text-red-700 hover:underline">Reject incoming</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'approved' && $this->canCompleteTransfer($transfer)): ?>
                                    <button type="button" wire:click="complete(<?php echo e($transfer->id); ?>)" wire:confirm="Complete this transfer and update the member's current unit?" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Complete transfer</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transferStatus === 'rejected' && $transfer->rejection_reason): ?>
                                    <span class="text-xs text-gray-400">No further actions</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500">No unit transfer requests found.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><?php echo e($transfers->links()); ?></div>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateModal): ?>
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4" wire:click.self="$set('showCreateModal', false)">
            <section class="w-full max-w-xl rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="create-transfer-title">
                <div class="mb-5 flex items-center justify-between"><h2 id="create-transfer-title" class="text-lg font-bold text-gray-900">New unit transfer</h2><button type="button" wire:click="$set('showCreateModal', false)" class="text-2xl leading-none text-gray-400">&times;</button></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['transfer'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="space-y-4">
                    <div class="relative">
                        <label for="unit-transfer-member-search" class="block text-sm font-medium text-gray-700">Search member</label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedMember): ?>
                            <div class="mt-1 flex items-center justify-between rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900"><?php echo e($selectedMember->user?->name); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo e($selectedMember->membership_number ?: 'No membership number'); ?> · <?php echo e($selectedMember->user?->email); ?> · Current unit: <?php echo e($selectedMember->unit?->name ?? 'Not assigned'); ?></p>
                                </div>
                                <button type="button" wire:click="$set('memberId', null); $set('memberSearch', '')" class="ml-3 text-sm font-medium text-indigo-700">Change</button>
                            </div>
                        <?php else: ?>
                            <input id="unit-transfer-member-search" type="search" wire:model.live.debounce.300ms="memberSearch" autocomplete="off" placeholder="Search by name, email or membership number" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strlen(trim($memberSearch)) >= 2): ?>
                                <div class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $memberResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <button type="button" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'transfer-member-result-'.e($result->id).''; ?>wire:key="transfer-member-result-<?php echo e($result->id); ?>" wire:click="selectMember(<?php echo e($result->id); ?>)" class="block w-full border-b border-gray-100 px-3 py-2 text-left hover:bg-indigo-50">
                                            <span class="block text-sm font-semibold text-gray-900"><?php echo e($result->user?->name); ?></span>
                                            <span class="block text-xs text-gray-500"><?php echo e($result->membership_number ?: 'No membership number'); ?> · <?php echo e($result->user?->email); ?> · <?php echo e($result->unit?->name ?? 'No unit'); ?></span>
                                        </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <p class="px-3 py-3 text-sm text-gray-500">No matching members found.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($memberResults->count() === 10): ?>
                                        <p class="px-3 py-2 text-xs text-gray-500">Showing first 10 matches. Refine your search.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['memberId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-1 block text-xs text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Destination unit
                        <select wire:model="targetUnitId" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="">Select destination unit</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($unit->id); ?>"><?php echo e($unit->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['targetUnitId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-1 block text-xs text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="spouseIncluded" class="mt-0.5 rounded border-gray-300 text-indigo-600"><span>Include eligible spouse in this transfer</span></label>
                    <p class="text-xs text-gray-500">The request will be sent to both the current (outgoing) unit and destination (incoming) unit for approval. The member's unit changes only after both approvals and completion.</p>
                </div>
                <div class="mt-6 flex justify-end gap-2"><button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Cancel</button><button type="button" wire:click="submitTransfer" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Submit request</button></div>
            </section>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRejectModal): ?>
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4" wire:click.self="$set('showRejectModal', false)">
            <section class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="reject-transfer-title">
                <h2 id="reject-transfer-title" class="text-lg font-bold text-gray-900">Reject transfer approval</h2>
                <p class="mt-1 text-sm text-gray-500">A reason is required and will be recorded in the transfer history.</p>
                <label class="mt-4 block text-sm font-medium text-gray-700">Rejection reason<textarea wire:model="rejectionReason" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></textarea></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['rejectionReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-1 block text-xs text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reject'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-1 block text-xs text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="$set('showRejectModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Cancel</button><button type="button" wire:click="reject" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Confirm rejection</button></div>
            </section>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Member\resources/views/livewire/unit-transfer-management.blade.php ENDPATH**/ ?>