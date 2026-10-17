<?php
/**
 * Nfelia — Profit & Loss Report View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $currency, $month, $year, $month_label,
 *   $revenue, $expenses_total, $gross_profit,
 *   $expense_categories, $pending_revenue, $pending_count,
 *   $debts_receivable, $debts_receivable_count,
 *   $debts_payable, $debts_payable_count,
 *   $trend (array of 6 months), $invoices_issued,
 *   $available_months
 */
$profit_positive = $gross_profit >= 0;
$margin_pct = $revenue > 0 ? round(($gross_profit / $revenue) * 100) : 0;

// Category labels
$cat_labels = [
    'stock_purchase'  => 'Stock Purchase',
    'rent'            => 'Rent',
    'utilities'       => 'Utilities',
    'transport'       => 'Transport',
    'salary'          => 'Salary / Wages',
    'marketing'       => 'Marketing',
    'maintenance'     => 'Maintenance',
    'office_supplies' => 'Office Supplies',
    'miscellaneous'   => 'Miscellaneous',
];
?>

<!-- Page Header + Period Selector -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Profit & Loss</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Your business performance for <strong class="text-brand-plum"><?= $month_label ?></strong></p>
    </div>
    <div class="flex items-center gap-2">
        <!-- Period Selector -->
        <form method="GET" action="<?= APP_URL ?>/reports/profit-loss" class="flex items-center gap-2">
            <select name="month" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold">
                <?php foreach ($available_months as $m => $label): ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold">
                <?php for ($y = (int)date('Y'); $y >= 2022; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Go</button>
        </form>
        <!-- PDF Download -->
        <button onclick="downloadReport()" class="flex items-center gap-2 bg-brand-white border border-brand-sand px-4 py-2.5 rounded-lg hover:border-brand-gold transition-colors">
            <svg class="w-3.5 h-3.5 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">PDF</span>
        </button>
    </div>
</div>

<div id="report-content">

<!-- The Big 3: Revenue, Expenses, Profit -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <!-- Revenue -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Money In</div>
        </div>
        <div class="font-heading font-extrabold text-2xl text-green-600"><?= $currency ?><?= number_format($revenue, 0) ?></div>
        <p class="text-[10px] text-brand-plum/40 mt-1">Payments received in <?= date('F', mktime(0,0,0,$month,1)) ?></p>
    </div>

    <!-- Expenses -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Money Out</div>
        </div>
        <div class="font-heading font-extrabold text-2xl text-red-600"><?= $currency ?><?= number_format($expenses_total, 0) ?></div>
        <p class="text-[10px] text-brand-plum/40 mt-1">Total spent in <?= date('F', mktime(0,0,0,$month,1)) ?></p>
    </div>

    <!-- Profit -->
    <div class="border-2 rounded-xl p-5 shadow-sm <?= $profit_positive ? 'bg-green-50/50 border-green-200' : 'bg-red-50/50 border-red-200' ?>">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg <?= $profit_positive ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center">
                <svg class="w-4 h-4 <?= $profit_positive ? 'text-green-600' : 'text-red-600' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest"><?= $profit_positive ? 'Profit' : 'Loss' ?></div>
        </div>
        <div class="font-heading font-extrabold text-2xl <?= $profit_positive ? 'text-green-700' : 'text-red-700' ?>">
            <?= $profit_positive ? '' : '-' ?><?= $currency ?><?= number_format(abs($gross_profit), 0) ?>
        </div>
        <p class="text-[10px] <?= $profit_positive ? 'text-green-600' : 'text-red-600' ?> mt-1 font-medium">
            <?php if ($revenue > 0): ?>
                <?= abs($margin_pct) ?>% margin &middot; For every <?= $currency ?>100 earned, you <?= $profit_positive ? 'kept' : 'lost' ?> <?= $currency ?><?= abs($margin_pct) ?>
            <?php else: ?>
                No revenue recorded this month
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Plain-Language Summary -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-lg bg-brand-cream flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="text-xs text-brand-plum/70 leading-relaxed">
            <?php if ($revenue == 0 && $expenses_total == 0): ?>
                <strong class="text-brand-plum">No activity recorded for <?= $month_label ?>.</strong> Revenue and expenses will appear here as you create invoices, record payments, and log expenses.
            <?php elseif ($profit_positive): ?>
                <strong class="text-brand-plum">Good news!</strong> In <?= $month_label ?>, your business received <strong class="text-green-600"><?= $currency ?><?= number_format($revenue, 0) ?></strong> in payments and spent <strong class="text-red-600"><?= $currency ?><?= number_format($expenses_total, 0) ?></strong>. That means you made a profit of <strong class="text-green-600"><?= $currency ?><?= number_format($gross_profit, 0) ?></strong>.
                <?php if ($margin_pct >= 50): ?> Your profit margin is strong.<?php elseif ($margin_pct >= 20): ?> Your margin is healthy — keep an eye on costs.<?php else: ?> Your margin is thin — consider reviewing your biggest expenses.<?php endif; ?>
            <?php else: ?>
                <strong class="text-brand-plum">Heads up.</strong> In <?= $month_label ?>, you spent more (<?= $currency ?><?= number_format($expenses_total, 0) ?>) than you received (<?= $currency ?><?= number_format($revenue, 0) ?>). The gap is <strong class="text-red-600"><?= $currency ?><?= number_format(abs($gross_profit), 0) ?></strong>.
                <?php if ($pending_revenue > 0): ?> However, you have <?= $currency ?><?= number_format($pending_revenue, 0) ?> in unpaid invoices — collecting those would help.<?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 6-Month Trend Chart -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">6-Month Trend</h2>
    <div style="height: 240px; position: relative;">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<!-- Two Column: Expenses Breakdown + Outstanding -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Expense Breakdown -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Where the Money Went</h2>
        <?php if (empty($expense_categories)): ?>
            <p class="text-xs text-brand-plum/40">No expenses recorded this month.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($expense_categories as $cat): ?>
                    <?php
                        $pct = $expenses_total > 0 ? round(($cat['total'] / $expenses_total) * 100) : 0;
                        $label = $cat_labels[$cat['category']] ?? ucfirst(str_replace('_', ' ', $cat['category']));
                    ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-brand-plum"><?= $label ?></span>
                            <span class="data-text text-xs font-bold text-brand-plum"><?= $currency ?><?= number_format((float)$cat['total'], 0) ?></span>
                        </div>
                        <div class="w-full h-2 bg-brand-cream rounded-full overflow-hidden">
                            <div class="h-full bg-red-400 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="text-[9px] text-brand-plum/30 mt-0.5"><?= $pct ?>% of total spend</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Outstanding Money -->
    <div class="space-y-4">
        <!-- Pending Revenue -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Money Still Owed to You</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Unpaid Invoices</div>
                    <div class="font-heading font-extrabold text-xl text-amber-600"><?= $currency ?><?= number_format($pending_revenue, 0) ?></div>
                    <div class="text-[10px] text-brand-plum/40"><?= $pending_count ?> invoice<?= $pending_count !== 1 ? 's' : '' ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Active Debts (Receivable)</div>
                    <div class="font-heading font-extrabold text-xl text-amber-600"><?= $currency ?><?= number_format($debts_receivable, 0) ?></div>
                    <div class="text-[10px] text-brand-plum/40"><?= $debts_receivable_count ?> debt<?= $debts_receivable_count !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($pending_revenue > 0 || $debts_receivable > 0): ?>
                <p class="text-[10px] text-brand-plum/50 mt-3 leading-relaxed border-t border-brand-sand/50 pt-3">
                    If you collect everything, that's an extra <strong class="text-green-600"><?= $currency ?><?= number_format($pending_revenue + $debts_receivable, 0) ?></strong> coming in.
                </p>
            <?php endif; ?>
        </div>

        <!-- Payables -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Money You Owe</h2>
            <div>
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Active Debts (Payable)</div>
                <div class="font-heading font-extrabold text-xl text-red-600"><?= $currency ?><?= number_format($debts_payable, 0) ?></div>
                <div class="text-[10px] text-brand-plum/40"><?= $debts_payable_count ?> debt<?= $debts_payable_count !== 1 ? 's' : '' ?> to suppliers</div>
            </div>
        </div>

        <!-- Invoices Issued -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Invoices Issued This Month</h2>
            <div class="flex items-baseline gap-3">
                <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)$invoices_issued['count'] ?></div>
                <div class="text-xs text-brand-plum/50">totalling <?= $currency ?><?= number_format((float)$invoices_issued['total'], 0) ?></div>
            </div>
        </div>
    </div>
</div>

</div><!-- #report-content -->

<!-- Chart.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
// Trend Chart
const trend = <?= json_encode($trend) ?>;
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: trend.map(t => t.label),
        datasets: [
            {
                label: 'Revenue',
                data: trend.map(t => t.revenue),
                backgroundColor: 'rgba(34, 197, 94, 0.7)',
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
            },
            {
                label: 'Expenses',
                data: trend.map(t => t.expenses),
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, pointStyle: 'rectRounded', padding: 20, font: { size: 11, weight: '600' } }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': <?= $currency ?>' + ctx.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: {
                    callback: v => '<?= $currency ?>' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v),
                    font: { size: 10 }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '600' } }
            }
        }
    }
});

// PDF Download
function downloadReport() {
    const el = document.getElementById('report-content');
    const opt = {
        margin: [10, 10, 10, 10],
        filename: 'PnL_<?= date('F_Y', strtotime($period_start)) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $business['name'] ?? 'Report') ?>.pdf',
        image: { type: 'jpeg', quality: 0.95 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save();
}
</script>