<div class="finance-journal-detail">
    <div class="page-head">
        <div>
            <h1>Journal <?php echo e($journal->journal_number); ?></h1>
            <p class="muted">Read-only journal details from the organization-wide Finance ledger.</p>
        </div>
        <a class="btn secondary" href="<?php echo e(route('finance.journals')); ?>">Back to journal register</a>
    </div>

    <section class="card">
        <h2>Journal information</h2>
        <div class="grid">
            <div class="field">
                <label>Journal number</label>
                <div><?php echo e($journal->journal_number); ?></div>
            </div>
            <div class="field">
                <label>Transaction date</label>
                <div><?php echo e($journal->transaction_date->format('d M Y')); ?></div>
            </div>
            <div class="field">
                <label>Financial period</label>
                <div><?php echo e($journal->period?->code ?? '—'); ?></div>
            </div>
            <div class="field">
                <label>Unit attribution</label>
                <div><?php echo e($journal->unit?->name ?? 'Organization-wide'); ?></div>
            </div>
            <div class="field">
                <label>Status</label>
                <div><span class="pill ok"><?php echo e(ucfirst($journal->status->value)); ?></span></div>
            </div>
            <div class="field">
                <label>Source</label>
                <div><?php echo e($journal->source_type ? class_basename($journal->source_type) . ' #' . ($journal->source_id ?? '—') : 'Manual journal'); ?></div>
            </div>
            <div class="field">
                <label>Description</label>
                <div><?php echo e($journal->description ?: '—'); ?></div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Journal lines</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Account code</th>
                        <th>Account name</th>
                        <th>Cost centre</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $journal->lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'journal-detail-line-'.e($line->id).''; ?>wire:key="journal-detail-line-<?php echo e($line->id); ?>">
                            <td><?php echo e($loop->iteration); ?></td>
                            <td><?php echo e($line->account?->code ?? '—'); ?></td>
                            <td><?php echo e($line->account?->name ?? '—'); ?></td>
                            <td><?php echo e($line->costCenter?->name ?? '—'); ?></td>
                            <td><?php echo e($line->description ?: '—'); ?></td>
                            <td><?php echo e(number_format((float) $line->debit, 4)); ?></td>
                            <td><?php echo e(number_format((float) $line->credit, 4)); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <th colspan="5">Totals</th>
                        <th><?php echo e(number_format($debitTotal, 4)); ?></th>
                        <th><?php echo e(number_format($creditTotal, 4)); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journal->isReversal() || $journal->reversal): ?>
        <section class="card">
            <h2>Reversal relationship</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journal->isReversal()): ?>
                <p>This journal reverses <a href="<?php echo e(route('finance.journals.show', $journal->originalJournal->id)); ?>"><?php echo e($journal->originalJournal->journal_number); ?></a>.</p>
                <p><strong>Reason:</strong> <?php echo e($journal->reversal_reason ?: '—'); ?></p>
            <?php else: ?>
                <p>This journal was reversed by <a href="<?php echo e(route('finance.journals.show', $journal->reversal->id)); ?>"><?php echo e($journal->reversal->journal_number); ?></a>.</p>
                <p><strong>Reversal reason:</strong> <?php echo e($journal->reversal->reversal_reason ?: '—'); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/journals/detail.blade.php ENDPATH**/ ?>