<div class="finance-income-statement">
    <header class="finance-income-statement__header">
        <div>
            <p class="finance-income-statement__eyebrow">FINANCIAL STATEMENTS</p>
            <h1>Income Statement</h1>
            <p class="finance-income-statement__description">
                Income earned and expenses incurred during the selected period.
            </p>
        </div>
    </header>

    <section class="finance-income-statement__filter-card" aria-label="Report filters">
        <div class="finance-income-statement__filter-grid">
            <div class="finance-income-statement__field">
                <label for="periodId">Financial period</label>
                <select id="periodId" wire:model.live="periodId">
                    <option value="">Custom date range</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($period->id); ?>"><?php echo e($period->name); ?> (<?php echo e($period->code); ?>)</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>

            <div class="finance-income-statement__field">
                <label for="startDate">Start date</label>
                <input id="startDate" type="date" wire:model.live="startDate">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['startDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <small class="finance-income-statement__error"><?php echo e($message); ?></small>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="finance-income-statement__field">
                <label for="endDate">End date</label>
                <input id="endDate" type="date" wire:model.live="endDate">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['endDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <small class="finance-income-statement__error"><?php echo e($message); ?></small>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="finance-income-statement__field">
                <label for="unitId">Unit</label>
                <select id="unitId" wire:model.live="unitId">
                    <option value="">All Units</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($unit->id); ?>"><?php echo e($unit->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="finance-income-statement__report-card">
        <div class="finance-income-statement__report-header">
            <div>
                <h2>Statement of income and expenses</h2>
                <p>
                    <?php echo e(\Illuminate\Support\Carbon::parse($report['start_date'])->format('d M Y')); ?>

                    – <?php echo e(\Illuminate\Support\Carbon::parse($report['end_date'])->format('d M Y')); ?>

                </p>
            </div>
            <span class="finance-income-statement__report-tag">PERIOD ACTIVITY</span>
        </div>

        <div class="finance-income-statement__sections">
            <section class="finance-income-statement__account-section" aria-labelledby="income-heading">
                <div class="finance-income-statement__section-heading">
                    <h3 id="income-heading">Income</h3>
                    <span><?php echo e(count($report['income_accounts'])); ?> accounts</span>
                </div>
                <div class="finance-income-statement__table-wrap">
                    <table class="finance-income-statement__table">
                        <thead>
                            <tr>
                                <th scope="col">Account code</th>
                                <th scope="col">Account name</th>
                                <th scope="col" class="is-numeric">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report['income_accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td><span class="finance-income-statement__account-code"><?php echo e($account['code']); ?></span></td>
                                    <td class="finance-income-statement__account-name"><?php echo e($account['name']); ?></td>
                                    <td class="is-numeric finance-income-statement__amount"><?php echo e($account['amount']); ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr><td colspan="3" class="finance-income-statement__empty">No income activity for this period.</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" scope="row">Total Income</th>
                                <td class="is-numeric finance-income-statement__total"><?php echo e($report['total_income']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="finance-income-statement__account-section" aria-labelledby="expenses-heading">
                <div class="finance-income-statement__section-heading">
                    <h3 id="expenses-heading">Expenses</h3>
                    <span><?php echo e(count($report['expense_accounts'])); ?> accounts</span>
                </div>
                <div class="finance-income-statement__table-wrap">
                    <table class="finance-income-statement__table">
                        <thead>
                            <tr>
                                <th scope="col">Account code</th>
                                <th scope="col">Account name</th>
                                <th scope="col" class="is-numeric">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report['expense_accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td><span class="finance-income-statement__account-code"><?php echo e($account['code']); ?></span></td>
                                    <td class="finance-income-statement__account-name"><?php echo e($account['name']); ?></td>
                                    <td class="is-numeric finance-income-statement__amount"><?php echo e($account['amount']); ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr><td colspan="3" class="finance-income-statement__empty">No expense activity for this period.</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" scope="row">Total Expenses</th>
                                <td class="is-numeric finance-income-statement__total"><?php echo e($report['total_expenses']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <div class="finance-income-statement__net-row">
            <div>
                <strong><?php echo e(str_starts_with($report['net_surplus'], '-') ? 'Net Deficit' : 'Net Surplus'); ?></strong>
                <span>Total income less total expenses</span>
            </div>
            <strong class="finance-income-statement__net-amount <?php echo e(str_starts_with($report['net_surplus'], '-') ? 'is-negative' : 'is-positive'); ?>">
                <?php echo e($report['net_surplus']); ?>

            </strong>
        </div>
    </section>
</div>

<style>
    .finance-income-statement {
        --fis-line: var(--line, #e3e8f0);
        --fis-panel: var(--panel, #fff);
        --fis-canvas: var(--canvas, #f6f8fc);
        --fis-ink: var(--ink, #172033);
        --fis-muted: var(--muted, #68758b);
        --fis-brand: var(--brand, #4776e6);
        display: grid;
        gap: 20px;
        min-width: 0;
        color: var(--fis-ink);
    }
    .finance-income-statement__eyebrow {
        margin: 0 0 6px;
        color: var(--fis-brand);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .1em;
    }
    .finance-income-statement__header h1 { margin: 0; }
    .finance-income-statement__description {
        margin: 7px 0 0;
        color: var(--fis-muted);
        font-size: 13px;
    }
    .finance-income-statement__filter-card,
    .finance-income-statement__report-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--fis-line);
        border-radius: 14px;
        background: var(--fis-panel);
        box-shadow: 0 4px 18px rgb(22 37 66 / 3%);
    }
    .finance-income-statement__filter-card { padding: 20px; }
    .finance-income-statement__filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .finance-income-statement__field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
    .finance-income-statement__field label {
        color: var(--fis-ink);
        font-size: 12px;
        font-weight: 750;
    }
    .finance-income-statement__field input,
    .finance-income-statement__field select {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 9px 12px;
        border: 1px solid var(--fis-line);
        border-radius: 9px;
        background: var(--fis-panel);
        color: var(--fis-ink);
        font: inherit;
        font-size: 13px;
    }
    .finance-income-statement__field input:focus,
    .finance-income-statement__field select:focus {
        border-color: var(--fis-brand);
        outline: 3px solid color-mix(in srgb, var(--fis-brand) 15%, transparent);
        outline-offset: 1px;
    }
    .finance-income-statement__error { color: #b42318; font-size: 12px; }
    .finance-income-statement__report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid var(--fis-line);
    }
    .finance-income-statement__report-header h2 {
        margin: 0;
        color: var(--fis-ink);
        font-size: 17px;
        font-weight: 750;
    }
    .finance-income-statement__report-header p {
        margin: 5px 0 0;
        color: var(--fis-muted);
        font-size: 12px;
    }
    .finance-income-statement__report-tag {
        padding: 5px 9px;
        border: 1px solid var(--fis-line);
        border-radius: 999px;
        background: var(--fis-canvas);
        color: var(--fis-muted);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        white-space: nowrap;
    }
    .finance-income-statement__sections { display: grid; gap: 22px; padding: 22px; }
    .finance-income-statement__account-section { min-width: 0; }
    .finance-income-statement__section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }
    .finance-income-statement__section-heading h3 { margin: 0; font-size: 14px; font-weight: 800; }
    .finance-income-statement__section-heading span { color: var(--fis-muted); font-size: 11px; }
    .finance-income-statement__table-wrap { width: 100%; overflow-x: auto; }
    .finance-income-statement__table { width: 100%; min-width: 520px; border-collapse: collapse; font-size: 13px; }
    .finance-income-statement__table thead { background: var(--fis-canvas); }
    .finance-income-statement__table th {
        padding: 12px 14px;
        border-bottom: 1px solid var(--fis-line);
        color: var(--fis-muted);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .07em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .finance-income-statement__table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--fis-line);
        color: var(--fis-muted);
    }
    .finance-income-statement__table .is-numeric {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .finance-income-statement__account-code { color: var(--fis-muted); font-variant-numeric: tabular-nums; }
    .finance-income-statement__account-name { color: var(--fis-ink) !important; font-weight: 650; }
    .finance-income-statement__amount { color: var(--fis-ink) !important; }
    .finance-income-statement__table tfoot { background: var(--fis-canvas); }
    .finance-income-statement__table tfoot th,
    .finance-income-statement__table tfoot td {
        border-top: 1px solid var(--fis-line);
        border-bottom: 0;
        color: var(--fis-ink);
        font-size: 12px;
        font-weight: 800;
    }
    .finance-income-statement__table tfoot .finance-income-statement__total { font-size: 14px; }
    .finance-income-statement__empty {
        padding: 25px 14px !important;
        color: var(--fis-muted) !important;
        text-align: center;
    }
    .finance-income-statement__net-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-top: 1px solid var(--fis-line);
        background: var(--fis-canvas);
    }
    .finance-income-statement__net-row > div { display: grid; gap: 4px; }
    .finance-income-statement__net-row > div strong { font-size: 14px; }
    .finance-income-statement__net-row > div span { color: var(--fis-muted); font-size: 11px; }
    .finance-income-statement__net-amount { font-size: 20px; font-variant-numeric: tabular-nums; }
    .finance-income-statement__net-amount.is-positive { color: #16734b; }
    .finance-income-statement__net-amount.is-negative { color: #b42318; }
    @media (max-width: 900px) {
        .finance-income-statement__filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 600px) {
        .finance-income-statement__filter-grid { grid-template-columns: 1fr; }
        .finance-income-statement__filter-card { padding: 16px; }
        .finance-income-statement__report-header { align-items: flex-start; padding: 17px 16px; }
        .finance-income-statement__sections { padding: 16px; }
        .finance-income-statement__net-row { padding: 17px 16px; }
        .finance-income-statement__net-amount { font-size: 17px; }
    }
</style>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/reports/income-statement.blade.php ENDPATH**/ ?>