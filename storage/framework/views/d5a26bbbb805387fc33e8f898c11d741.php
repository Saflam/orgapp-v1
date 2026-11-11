<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">FINANCE DIRECTORY</p>
            <h1 class="text-2xl font-bold text-gray-900">Customers &amp; Suppliers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage customer and supplier records used by invoices, bills and payment workflows.</p>
        </div>
        <a href="<?php echo e(route('finance.receivables-payables')); ?>" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">← Receivables &amp; Payables</a>
    </header>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage): ?>
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"><?php echo e($successMessage); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewCustomers): ?>
            <section class="min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Customers</h2>
                        <p class="text-sm text-gray-500">Member and external customer records</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"><?php echo e($customers->total()); ?> records</span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageCustomers): ?>
                    <details class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">+ Add external customer</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-sm sm:col-span-2">Name<input wire:model="customerName" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Customer name"></label>
                            <label class="text-sm">Email<input type="email" wire:model="customerEmail" class="mt-1 w-full rounded-lg border-gray-300" placeholder="name@example.com"></label>
                            <label class="text-sm">Phone<input wire:model="customerPhone" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Phone number"></label>
                        </div>
                        <button wire:click="createCustomer" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save customer</button>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:col-span-2">Search customers
                        <input type="search" wire:model.live.debounce.300ms="customerSearch" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case" placeholder="Name, email or phone">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Customer type
                        <select wire:model.live="customerType" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="all">All types</option><option value="member">Members</option><option value="external">External</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                        <select wire:model.live="customerStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All statuses</option>
                        </select>
                    </label>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-3">Customer</th><th class="px-3 py-3">Contact</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td class="px-3 py-3"><div class="font-semibold text-gray-900"><?php echo e($customer->name); ?></div><div class="text-xs text-gray-500"><?php echo e($customer->user_id ? 'Member account' : 'External record'); ?></div></td>
                                    <td class="px-3 py-3"><div><?php echo e($customer->email ?: '—'); ?></div><div class="text-xs text-gray-500"><?php echo e($customer->phone ?: '—'); ?></div></td>
                                    <td class="px-3 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700"><?php echo e(ucfirst($customer->customer_type?->value ?? (string) $customer->customer_type)); ?></span></td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?php echo e($customer->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'); ?>"><?php echo e($customer->is_active ? 'Active' : 'Inactive'); ?></span></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">No customers match these filters.</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div><?php echo e($customers->links()); ?></div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewSuppliers): ?>
            <section class="min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Suppliers</h2>
                        <p class="text-sm text-gray-500">Supplier records and default payment terms</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"><?php echo e($suppliers->total()); ?> records</span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageSuppliers): ?>
                    <details class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">+ Add supplier</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-sm">Supplier code (optional)<input wire:model="supplierCode" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Auto-generated if blank"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['supplierCode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="text-xs text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                            <label class="text-sm">Name<input wire:model="supplierName" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Supplier name"></label>
                            <label class="text-sm">Email<input type="email" wire:model="supplierEmail" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Phone<input wire:model="supplierPhone" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Tax number<input wire:model="supplierTaxNumber" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Payment terms (days)<input type="number" min="0" max="365" wire:model="supplierPaymentTerms" class="mt-1 w-full rounded-lg border-gray-300"></label>
                        </div>
                        <button wire:click="createSupplier" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save supplier</button>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:col-span-2">Search suppliers
                        <input type="search" wire:model.live.debounce.300ms="supplierSearch" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case" placeholder="Code, name, email, phone or tax number">
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                        <select wire:model.live="supplierStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm normal-case">
                            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All statuses</option>
                        </select>
                    </label>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Contact</th><th class="px-3 py-3">Terms</th><th class="px-3 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td class="px-3 py-3"><div class="font-semibold text-gray-900"><?php echo e($supplier->name); ?></div><div class="text-xs text-gray-500"><?php echo e($supplier->code); ?></div></td>
                                    <td class="px-3 py-3"><div><?php echo e($supplier->email ?: '—'); ?></div><div class="text-xs text-gray-500"><?php echo e($supplier->phone ?: '—'); ?></div></td>
                                    <td class="px-3 py-3"><?php echo e($supplier->payment_terms_days); ?> days</td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?php echo e($supplier->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'); ?>"><?php echo e($supplier->is_active ? 'Active' : 'Inactive'); ?></span></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">No suppliers match these filters.</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div><?php echo e($suppliers->links()); ?></div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/parties/manage.blade.php ENDPATH**/ ?>