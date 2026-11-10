<div class="finance-journal-management">
    <div class="page-head">
        <div>
            <h1>Journal Entries</h1>
            <p class="muted">Post manual double-entry journals to the organization-wide ledger. Posted journals are permanent accounting records.</p>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage !== ''): ?>
        <div class="notice" role="status"><?php echo e($successMessage); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['journal'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="error" role="alert"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['lines'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="error" role="alert"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canPost): ?>
        <section class="card">
            <h2>Post a journal</h2>
            <p class="muted">Enter at least two lines. Each line must be either a debit or a credit, and total debits must equal total credits.</p>

            <form wire:submit="postJournal">
                <div class="grid journal-entry-fields">
                    <div class="field">
                        <label for="journal-date">Transaction date</label>
                        <input id="journal-date" type="date" wire:model="transactionDate" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['transactionDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="journal-period">Financial period</label>
                        <select id="journal-period" wire:model="periodId" required>
                            <option value="">Select an open period</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $openPeriods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($period->id); ?>"><?php echo e($period->code); ?> — <?php echo e($period->starts_on->format('d M Y')); ?> to <?php echo e($period->ends_on->format('d M Y')); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['periodId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="journal-unit">Unit attribution (optional)</label>
                        <select id="journal-unit" wire:model="unitId">
                            <option value="">Organization-wide</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($unit->id); ?>"><?php echo e($unit->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['unitId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="journal-description">Journal description</label>
                        <input id="journal-description" type="text" wire:model="description" maxlength="1000" placeholder="Reason for this journal">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <h3>Journal lines</h3>
                <div class="table-wrap journal-entry-lines">
                    <table>
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'journal-line-'.e($index).''; ?>wire:key="journal-line-<?php echo e($index); ?>">
                                    <td>
                                        <select wire:model="lines.<?php echo e($index); ?>.finance_account_id" required>
                                            <option value="">Select account</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($account->id); ?>"><?php echo e($account->code); ?> — <?php echo e($account->name); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["lines.$index.finance_account_id"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="text" wire:model="lines.<?php echo e($index); ?>.description" maxlength="500" placeholder="Optional line description">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["lines.$index.description"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" wire:model.live="lines.<?php echo e($index); ?>.debit" required>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["lines.$index.debit"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" wire:model.live="lines.<?php echo e($index); ?>.credit" required>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["lines.$index.credit"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn danger" type="button" wire:click="removeLine(<?php echo e($index); ?>)" <?php if(count($lines) <= 2): echo 'disabled'; endif; ?>>Remove</button>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <th colspan="2">Totals</th>
                                <th><?php echo e(number_format($draftDebitTotal, 4)); ?></th>
                                <th><?php echo e(number_format($draftCreditTotal, 4)); ?></th>
                                <th></th>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="actions">
                    <button class="btn secondary" type="button" wire:click="addLine" <?php if(count($lines) >= 50): echo 'disabled'; endif; ?>>Add line</button>
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="postJournal">
                        <span wire:loading.remove wire:target="postJournal">Post Journal</span>
                        <span wire:loading wire:target="postJournal">Posting…</span>
                    </button>
                    <button class="btn secondary" type="button" wire:click="resetEntryForm">Reset</button>
                </div>
            </form>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <section class="card">
        <h2>Journal register</h2>
        <div class="grid journal-register-filters">
            <div class="field">
                <label for="journal-search">Search</label>
                <input id="journal-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Journal number or description">
            </div>
            <div class="field">
                <label for="journal-filter-period">Financial period</label>
                <select id="journal-filter-period" wire:model.live="filterPeriodId">
                    <option value="">All periods</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allPeriods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($period->id); ?>"><?php echo e($period->code); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label for="journal-filter-unit">Unit</label>
                <select id="journal-filter-unit" wire:model.live="filterUnitId">
                    <option value="">All units</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($unit->id); ?>"><?php echo e($unit->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label for="journal-from-date">From date</label>
                <input id="journal-from-date" type="date" wire:model.live="fromDate">
            </div>
            <div class="field">
                <label for="journal-to-date">To date</label>
                <input id="journal-to-date" type="date" wire:model.live="toDate">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Journal number</th>
                        <th>Date</th>
                        <th>Period</th>
                        <th>Unit</th>
                        <th>Description</th>
                        <th>Lines</th>
                        <th>Total debit</th>
                        <th>Total credit</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'finance-journal-'.e($journal->id).''; ?>wire:key="finance-journal-<?php echo e($journal->id); ?>">
                            <td>
                                <?php echo e($journal->journal_number); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journal->isReversal()): ?>
                                    <div class="muted">Reversal of <?php echo e($journal->originalJournal?->journal_number ?? 'journal'); ?></div>
                                <?php elseif($journal->reversal): ?>
                                    <div class="muted">Reversed by <?php echo e($journal->reversal->journal_number); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e($journal->transaction_date->format('d M Y')); ?></td>
                            <td><?php echo e($journal->period?->code ?? '—'); ?></td>
                            <td><?php echo e($journal->unit?->name ?? 'Organization'); ?></td>
                            <td><?php echo e($journal->description ?: '—'); ?></td>
                            <td><?php echo e($journal->lines_count); ?></td>
                            <td><?php echo e(number_format((float) $journal->lines->sum('debit'), 4)); ?></td>
                            <td><?php echo e(number_format((float) $journal->lines->sum('credit'), 4)); ?></td>
                            <td><span class="pill ok"><?php echo e(ucfirst($journal->status->value)); ?></span></td>
                            <td class="journal-actions">
                                <a class="journal-icon-btn" href="<?php echo e(route('finance.journals.show', $journal->id)); ?>"
                                   title="View journal" aria-label="View journal <?php echo e($journal->journal_number); ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path>
                                        <circle cx="12" cy="12" r="2.7"></circle>
                                    </svg>
                                </a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReverse && $journal->isPosted() && ! $journal->isReversal() && ! $journal->reversal): ?>
                                    <button class="journal-icon-btn" type="button"
                                            wire:click="prepareReversal(<?php echo e($journal->id); ?>)"
                                            title="Reverse journal" aria-label="Reverse journal <?php echo e($journal->journal_number); ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M4 7h12a4 4 0 0 1 0 8h-1"></path>
                                            <path d="m8 3-4 4 4 4"></path>
                                            <path d="M20 17H8a4 4 0 0 1 0-8h1"></path>
                                            <path d="m16 21 4-4-4-4"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="10" class="empty">No journal entries match the selected filters.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="journal-pagination">
            <?php echo e($journalEntries->links()); ?>

        </div>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reversalJournalId): ?>
        <div class="journal-modal-backdrop" role="presentation">
            <section class="journal-modal" role="dialog" aria-modal="true" aria-labelledby="reverse-journal-heading" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'journal-reversal-modal'; ?>wire:key="journal-reversal-modal">

            <h2 id="reverse-journal-heading">Reverse journal</h2>
            <p class="muted">A new posted journal will be created with the original debit and credit amounts swapped. The original journal will remain unchanged.</p>
            <form wire:submit="reverseJournal">
                <div class="grid">
                    <div class="field">
                        <label for="reversal-date">Reversal date</label>
                        <input id="reversal-date" type="date" wire:model="reversalDate" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reversalDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="reversal-period">Open financial period</label>
                        <select id="reversal-period" wire:model="reversalPeriodId" required>
                            <option value="">Select an open period</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $openPeriods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($period->id); ?>"><?php echo e($period->code); ?> — <?php echo e($period->starts_on->format('d M Y')); ?> to <?php echo e($period->ends_on->format('d M Y')); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reversalPeriodId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="reversal-reason">Reason for reversal</label>
                        <textarea id="reversal-reason" wire:model="reversalReason" rows="3" maxlength="1000" required></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reversalReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="error"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="reverseJournal">Confirm reversal</button>
                    <button class="btn secondary" type="button" wire:click="cancelReversal">Cancel</button>
                </div>
            </form>
            </section>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<style>
    .finance-journal-management .journal-entry-fields,
    .finance-journal-management .journal-register-filters {
        display: grid;
        gap: 12px 16px;
    }

    .finance-journal-management .journal-entry-fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-journal-management .journal-register-filters {
        grid-template-columns: minmax(260px, 2.2fr) repeat(4, minmax(130px, 1fr));
        align-items: end;
    }

    .finance-journal-management .card {
        padding: 16px 20px;
    }

    .finance-journal-management .card h2 {
        margin-top: 0;
        margin-bottom: 8px;
    }

    .finance-journal-management .card > .muted {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .finance-journal-management .field {
        margin-bottom: 0;
    }

    .finance-journal-management .field label {
        margin-bottom: 5px;
    }

    .finance-journal-management input,
    .finance-journal-management select,
    .finance-journal-management textarea {
        min-height: 38px;
        padding-top: 7px;
        padding-bottom: 7px;
    }

    .finance-journal-management .journal-entry-lines {
        overflow-x: auto;
    }

    .finance-journal-management .journal-entry-lines table {
        width: 100%;
        min-width: 850px;
        table-layout: fixed;
    }

    .finance-journal-management .journal-entry-lines th,
    .finance-journal-management .journal-entry-lines td {
        padding: 7px 9px;
        vertical-align: middle;
    }

    .finance-journal-management .journal-entry-lines th:nth-child(1) { width: 25%; }
    .finance-journal-management .journal-entry-lines th:nth-child(2) { width: 21%; }
    .finance-journal-management .journal-entry-lines th:nth-child(3),
    .finance-journal-management .journal-entry-lines th:nth-child(4) { width: 19%; }
    .finance-journal-management .journal-entry-lines th:nth-child(5) { width: 16%; }

    .finance-journal-management .journal-entry-lines input,
    .finance-journal-management .journal-entry-lines select {
        width: 100%;
        min-width: 0;
    }

    .finance-journal-management .journal-entry-lines .btn,
    .finance-journal-management .actions .btn {
        min-height: 36px;
        padding: 7px 12px;
    }

    .finance-journal-management h3 {
        margin-top: 12px;
        margin-bottom: 6px;
    }

    .finance-journal-management .actions {
        margin-top: 10px;
        gap: 8px;
    }

    .finance-journal-management .journal-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .finance-journal-management .journal-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        min-width: 34px;
        padding: 0;
        border: 1px solid var(--border-color, #d1d5db);
        border-radius: 6px;
        background: transparent;
        color: inherit;
        cursor: pointer;
        text-decoration: none;
    }

    .finance-journal-management .journal-icon-btn:hover {
        background: var(--surface-muted, #f3f4f6);
    }

    .finance-journal-management .journal-icon-btn:focus-visible {
        outline: 2px solid var(--primary, #2563eb);
        outline-offset: 2px;
    }

    .finance-journal-management .journal-icon-btn svg {
        width: 17px;
        height: 17px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .finance-journal-management .journal-pagination {
        margin-top: 14px;
    }

    .finance-journal-management .journal-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow-y: auto;
        background: rgba(15, 23, 42, .58);
    }

    .finance-journal-management .journal-modal {
        width: min(620px, 100%);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        padding: 22px;
        border-radius: 12px;
        background: var(--card-background, #fff);
        box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
    }

    .finance-journal-management .journal-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .finance-journal-management .journal-modal h2 {
        margin-top: 0;
    }

    .finance-journal-management .journal-modal-close {
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: inherit;
        font-size: 25px;
        line-height: 1;
        cursor: pointer;
    }

    .finance-journal-management .journal-modal-close:hover {
        background: var(--surface-muted, #f3f4f6);
    }

    @media (max-width: 1100px) {
        .finance-journal-management .journal-register-filters {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .finance-journal-management .journal-register-filters .field:first-child {
            grid-column: span 2;
        }
    }

    @media (max-width: 700px) {
        .finance-journal-management .card {
            padding: 14px;
        }

        .finance-journal-management .journal-entry-fields,
        .finance-journal-management .journal-register-filters {
            grid-template-columns: minmax(0, 1fr);
        }

        .finance-journal-management .journal-register-filters .field:first-child {
            grid-column: auto;
        }
    }
</style>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/journals/manage.blade.php ENDPATH**/ ?>