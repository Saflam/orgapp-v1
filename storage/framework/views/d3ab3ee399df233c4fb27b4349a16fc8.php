<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Income Statement</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; }
        .heading { border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 15px; }
        h1 { font-size: 19px; margin: 0 0 5px; }
        .subtitle { color: #64748b; font-size: 10px; margin: 0; }
        .meta { width: 100%; margin: 12px 0 18px; }
        .meta td { padding: 4px 8px 4px 0; color: #475569; }
        .meta strong { color: #172033; }
        h2 { margin: 0 0 8px; font-size: 12px; color: #172033; }
        table.report { width: 100%; border-collapse: collapse; margin: 0 0 17px; }
        .report th { background: #eef2f7; color: #475569; font-size: 8px; text-transform: uppercase; text-align: left; }
        .report th, .report td { border: 1px solid #dbe2ea; padding: 7px 8px; }
        .numeric { text-align: right !important; white-space: nowrap; }
        .report tbody tr { page-break-inside: avoid; }
        .report tfoot th, .report tfoot td { background: #f8fafc; color: #172033; font-weight: bold; }
        .empty { color: #64748b; text-align: center; }
        .net { width: 100%; margin-top: 5px; border-collapse: collapse; }
        .net th, .net td { border-top: 2px solid #94a3b8; border-bottom: 2px solid #94a3b8; padding: 11px 9px; background: #f8fafc; font-size: 11px; font-weight: bold; }
        .net .numeric { font-size: 13px; }
        .footer { margin-top: 18px; color: #64748b; font-size: 8px; text-align: right; }
    </style>
</head>
<body>
    <div class="heading">
        <h1><?php echo e($organization->name); ?> — Income Statement</h1>
        <p class="subtitle">Income earned and expenses incurred during the selected period</p>
    </div>

    <table class="meta">
        <tr>
            <td><strong>From:</strong> <?php echo e(\Illuminate\Support\Carbon::parse($report['start_date'])->format('d M Y')); ?></td>
            <td><strong>To:</strong> <?php echo e(\Illuminate\Support\Carbon::parse($report['end_date'])->format('d M Y')); ?></td>
            <td><strong>Unit:</strong> <?php echo e($unitName); ?></td>
            <td><strong>Generated:</strong> <?php echo e($generatedAt->format('d M Y H:i')); ?></td>
        </tr>
    </table>

    <h2>Income</h2>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 20%">Account code</th>
                <th>Account name</th>
                <th class="numeric" style="width: 25%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report['income_accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr>
                    <td><?php echo e($account['code']); ?></td>
                    <td><?php echo e($account['name']); ?></td>
                    <td class="numeric"><?php echo e($account['amount']); ?></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="3" class="empty">No income activity for this period.</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total Income</th>
                <td class="numeric"><?php echo e($report['total_income']); ?></td>
            </tr>
        </tfoot>
    </table>

    <h2>Expenses</h2>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 20%">Account code</th>
                <th>Account name</th>
                <th class="numeric" style="width: 25%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $report['expense_accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr>
                    <td><?php echo e($account['code']); ?></td>
                    <td><?php echo e($account['name']); ?></td>
                    <td class="numeric"><?php echo e($account['amount']); ?></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="3" class="empty">No expense activity for this period.</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total Expenses</th>
                <td class="numeric"><?php echo e($report['total_expenses']); ?></td>
            </tr>
        </tfoot>
    </table>

    <table class="net">
        <tr>
            <th><?php echo e(str_starts_with($report['net_surplus'], '-') ? 'Net Deficit' : 'Net Surplus'); ?></th>
            <td class="numeric"><?php echo e($report['net_surplus']); ?></td>
        </tr>
    </table>

    <div class="footer">Generated by Saradhi</div>
</body>
</html>
<?php /**PATH C:\projects\personal\prasanth\Saflivo\Modules/Finance\resources/views/livewire/reports/income-statement-pdf.blade.php ENDPATH**/ ?>