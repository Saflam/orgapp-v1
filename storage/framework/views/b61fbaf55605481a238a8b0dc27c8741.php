<div class="finance-trial-balance">
    <header class="finance-trial-balance__header">
        <div>
            <p class="finance-trial-balance__eyebrow">FINANCE REPORTS</p>
            <h1>Trial Balance</h1>
            <p class="finance-trial-balance__description">
                Cumulative posted account balances through the selected date.
            </p>
        </div>
    </header>

    <section class="finance-trial-balance__filter-card" aria-label="Report filters">
        <div class="finance-trial-balance__filter-grid">
            <div class="finance-trial-balance__field">
                <label for="periodId">Financial period</label>
                <select id="periodId" wire:model.live="periodId">
                    <option value="">Custom as-of date</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($period->id); ?>"><?php echo e($period->name); ?> (<?php echo e($period->code); ?>)</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>

            <div class="finance-trial-balance__field">
                <label for="asOfDate">As of date</label>
                <input id="asOfDate" type="date" wire:model.live="asOfDate">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['asOfDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <small class="finance-trial-balance__error"><?php echo e($message); ?></small>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="finance-trial-balance__field">
                <label for="costCenterType">Cost center scope</label>
                <select id="costCenterType" wire:model.live="costCenterType">
                    <option value="all">All Scopes (All Cost Centres)</option>
                    <option value="organization">Central / Organization</option>
                    <option value="unit">Individual Units</option>
                    <option value="committee">Individual Committees</option>
                </select>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($costCenterType, ['unit', 'committee'], true)): ?>
                <div class="finance-trial-balance__field">
                    <label for="costCenterOwnerId"><?php echo e($costCenterType === 'unit' ? 'Unit' : 'Committee'); ?></label>
                    <select id="costCenterOwnerId" wire:model.live="costCenterOwnerId">
                        <option value="">Select <?php echo e($costCenterType === 'unit' ? 'unit' : 'committee'); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($costCenterType === 'unit'): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($unit->id); ?>"><?php echo e($unit->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php else: ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $committees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $committee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($committee->id); ?>"><?php echo e($committee->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterRequired): ?>
        <div class="finance-trial-balance__filter-card" role="status">
            Select a <?php echo e($costCenterType === 'unit' ? 'unit' : 'committee'); ?> to generate this report.
        </div>
    <?php else: ?>
    <section class="finance-trial-balance__report-card">
        <div class="finance-trial-balance__report-header">
            <div>
                <h2>Account balances</h2>
                <p>As of <?php echo e(\Illuminate\Support\Carbon::parse($report['as_of_date'])->format('d M Y')); ?></p>
            </div>
            <div class="finance-trial-balance__report-actions">
                <span class="finance-trial-balance__report-tag">CUMULATIVE</span>
                <a
                    class="finance-trial-balance__export-button"
                    href="<?php echo e(route('finance.reports.trial-balance.pdf', array_filter([
                        'as_of_date' => $report['as_of_date'],
                        'cost_center_type' => $this->costCenterType,
                        'cost_center_owner_id' => $this->costCenterOwnerId,
                        // Retain the original query parameter for older custom integrations.
                        'cost_center_id' => $this->costCenterOwnerId,
                    ], static fn ($value) => $value !== null))); ?>"
                >
                    <span aria-hidden="true">↓</span>
                    Export PDF
                </a>
            </div>
        </div>

        <div class="finance-trial-balance__table-wrap">
            <table class="finance-trial-balance__table">
                <thead>
                    <tr>
                        <th scope="col">Account code</th>
                        <th scope="col">Account name</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="is-numeric">Debit</th>
                        <th scope="col" class="is-numeric">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report['accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr>
                            <td><span class="finance-trial-balance__account-code"><?php echo e($account['code']); ?></span></td>
                            <td class="finance-trial-balance__account-name"><?php echo e($account['name']); ?></td>
                            <td><span class="finance-trial-balance__type"><?php echo e(\Illuminate\Support\Str::headline($account['account_type'])); ?></span></td>
                            <td class="is-numeric finance-trial-balance__amount"><?php echo e($account['debit']); ?></td>
                            <td class="is-numeric finance-trial-balance__amount"><?php echo e($account['credit']); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="5" class="finance-trial-balance__empty">
                                No accounts are available for this report.
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" scope="row">Debit total</th>
                        <td class="is-numeric finance-trial-balance__total"><?php echo e($report['debit_total']); ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <th colspan="3" scope="row">Credit total</th>
                        <td></td>
                        <td class="is-numeric finance-trial-balance__total"><?php echo e($report['credit_total']); ?></td>
                    </tr>
                    <tr class="finance-trial-balance__difference-row">
                        <th colspan="3" scope="row">Difference</th>
                        <td colspan="2" class="is-numeric finance-trial-balance__difference <?php echo e($report['difference'] !== '0.0000' ? 'has-difference' : 'is-balanced'); ?>">
                            <?php echo e($report['difference']); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report['difference'] === '0.0000'): ?>
                                <span>Balanced</span>
                            <?php else: ?>
                                <span>Out of balance</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<style>
    .finance-trial-balance {
        --ftb-line: var(--line, #e3e8f0);
        --ftb-panel: var(--panel, #fff);
        --ftb-canvas: var(--canvas, #f6f8fc);
        --ftb-ink: var(--ink, #172033);
        --ftb-muted: var(--muted, #68758b);
        --ftb-brand: var(--brand, #4776e6);
        display: grid;
        gap: 20px;
        min-width: 0;
        color: var(--ftb-ink);
    }

    .finance-trial-balance__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .finance-trial-balance__eyebrow {
        margin: 0 0 6px;
        color: var(--ftb-brand);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .1em;
    }

    .finance-trial-balance__header h1 {
        margin: 0;
    }

    .finance-trial-balance__description {
        margin: 7px 0 0;
        color: var(--ftb-muted);
        font-size: 13px;
    }

    .finance-trial-balance__filter-card,
    .finance-trial-balance__report-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ftb-line);
        border-radius: 14px;
        background: var(--ftb-panel);
        box-shadow: 0 4px 18px rgb(22 37 66 / 3%);
    }

    .finance-trial-balance__filter-card {
        padding: 20px;
    }

    .finance-trial-balance__filter-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .finance-trial-balance__field {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 7px;
    }

    .finance-trial-balance__field label {
        color: var(--ftb-ink);
        font-size: 12px;
        font-weight: 750;
    }

    .finance-trial-balance__field input,
    .finance-trial-balance__field select {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 9px 12px;
        border: 1px solid var(--ftb-line);
        border-radius: 9px;
        background: var(--ftb-panel);
        color: var(--ftb-ink);
        font: inherit;
        font-size: 13px;
    }

    .finance-trial-balance__field input:focus,
    .finance-trial-balance__field select:focus {
        border-color: var(--ftb-brand);
        outline: 3px solid color-mix(in srgb, var(--ftb-brand) 15%, transparent);
        outline-offset: 1px;
    }

    .finance-trial-balance__error {
        color: #b42318;
        font-size: 12px;
    }

    .finance-trial-balance__report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid var(--ftb-line);
    }

    .finance-trial-balance__report-header h2 {
        margin: 0;
        color: var(--ftb-ink);
        font-size: 17px;
        font-weight: 750;
    }

    .finance-trial-balance__report-header p {
        margin: 5px 0 0;
        color: var(--ftb-muted);
        font-size: 12px;
    }

    .finance-trial-balance__report-tag {
        flex: 0 0 auto;
        padding: 5px 9px;
        border: 1px solid var(--ftb-line);
        border-radius: 999px;
        background: var(--ftb-canvas);
        color: var(--ftb-muted);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
    }

    .finance-trial-balance__report-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .finance-trial-balance__export-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 36px;
        padding: 8px 12px;
        border: 1px solid var(--ftb-brand);
        border-radius: 8px;
        background: var(--ftb-brand);
        color: #fff;
        font-size: 12px;
        font-weight: 750;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: filter .15s ease, transform .15s ease;
    }

    .finance-trial-balance__export-button:hover {
        color: #fff;
        filter: brightness(.94);
        transform: translateY(-1px);
    }

    .finance-trial-balance__export-button:focus-visible {
        outline: 3px solid color-mix(in srgb, var(--ftb-brand) 25%, transparent);
        outline-offset: 2px;
    }

    .finance-trial-balance__table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .finance-trial-balance__table {
        width: 100%;
        min-width: 680px;
        border-collapse: collapse;
        font-size: 13px;
    }

    .finance-trial-balance__table thead {
        background: var(--ftb-canvas);
    }

    .finance-trial-balance__table th {
        padding: 13px 18px;
        border-bottom: 1px solid var(--ftb-line);
        color: var(--ftb-muted);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .07em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .finance-trial-balance__table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--ftb-line);
        color: var(--ftb-muted);
        vertical-align: middle;
    }

    .finance-trial-balance__table tbody tr:last-child td {
        border-bottom: 1px solid var(--ftb-line);
    }

    .finance-trial-balance__table tbody tr:hover {
        background: color-mix(in srgb, var(--ftb-canvas) 65%, transparent);
    }

    .finance-trial-balance__table .is-numeric {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .finance-trial-balance__account-code {
        color: var(--ftb-muted);
        font-variant-numeric: tabular-nums;
    }

    .finance-trial-balance__account-name {
        color: var(--ftb-ink) !important;
        font-weight: 700;
    }

    .finance-trial-balance__type {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 6px;
        background: var(--ftb-canvas);
        color: var(--ftb-muted);
        font-size: 11px;
        font-weight: 650;
    }

    .finance-trial-balance__amount {
        color: var(--ftb-ink) !important;
        font-variant-numeric: tabular-nums;
    }

    .finance-trial-balance__table tfoot {
        background: var(--ftb-canvas);
    }

    .finance-trial-balance__table tfoot th,
    .finance-trial-balance__table tfoot td {
        padding-top: 13px;
        padding-bottom: 13px;
        border-bottom: 1px solid var(--ftb-line);
        color: var(--ftb-ink);
        font-size: 11px;
        font-weight: 800;
    }

    .finance-trial-balance__table tfoot .finance-trial-balance__total {
        font-size: 13px;
    }

    .finance-trial-balance__difference-row th,
    .finance-trial-balance__difference-row td {
        border-top: 1px solid var(--ftb-line);
    }

    .finance-trial-balance__difference {
        font-size: 13px !important;
    }

    .finance-trial-balance__difference span {
        display: inline-block;
        margin-left: 8px;
        font-size: 10px;
        font-weight: 750;
    }

    .finance-trial-balance__difference.is-balanced,
    .finance-trial-balance__difference.is-balanced span {
        color: #16734b !important;
    }

    .finance-trial-balance__difference.has-difference,
    .finance-trial-balance__difference.has-difference span {
        color: #b42318 !important;
    }

    .finance-trial-balance__empty {
        padding: 36px 18px !important;
        color: var(--ftb-muted) !important;
        text-align: center;
    }

    @media (max-width: 760px) {
        .finance-trial-balance__filter-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .finance-trial-balance__filter-card {
            padding: 16px;
        }

        .finance-trial-balance__report-header {
            align-items: flex-start;
            padding: 17px 16px;
        }

        .finance-trial-balance__report-actions {
            align-items: flex-end;
            flex-direction: column;
        }

        .finance-trial-balance__table th,
        .finance-trial-balance__table td {
            padding-right: 13px;
            padding-left: 13px;
        }
    }
</style>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/reports/trial-balance.blade.php ENDPATH**/ ?>