<?php
/**
 * Nfelia — Profit & Loss Report View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $currency, $period (app/helpers/report_period.php),
 *   $period_start, $period_end, $month_label (the period in words),
 *   $revenue (money in), $money_out (money paid out), $stock_out (the part
 *   of it spent on stock), $left (in less out), $left_reads (money_left_reads():
 *   up, stock or down), $prev_revenue, $prev_out, $prev_left (the period before),
 *   $expense_categories (money out by category), $pending_revenue, $pending_count,
 *   $debts_receivable, $debts_receivable_count, $collectable_extra,
 *   $owe_total, $owe_unpaid, $owe_unpaid_count, $owe_debts, $owe_debts_count,
 *   $trend (the period by day or by month), $invoices_issued,
 *   $available_months, $available_years
 */
// Up is good news, stock is money put into stock (not lost), down is a real gap
$tone = [
    'up'    => ['card' => 'bg-green-50/50 border-green-200', 'icon' => 'bg-green-100', 'ink' => 'text-green-600', 'value' => 'text-green-700'],
    'stock' => ['card' => 'bg-amber-50 border-amber-200',    'icon' => 'bg-amber-100', 'ink' => 'text-amber-700', 'value' => 'text-amber-700'],
    'down'  => ['card' => 'bg-red-50/50 border-red-200',     'icon' => 'bg-red-100',   'ink' => 'text-red-600',   'value' => 'text-red-700'],
][$left_reads];
$kept_per_100 = $revenue > 0 ? round($left / $revenue * 100) : 0;
$out_per_100  = $revenue > 0 ? round($money_out / $revenue * 100) : 0;
$stock_text   = $currency . number_format($stock_out, 0);

/**
 * "▲ 12% vs September 2026" under a card. $good says which way is good news:
 * more money in is, more money out isn't.
 */
$compare = function (float $now, float $before, string $good) use ($period, $currency): string {
    $vs = 'vs ' . e($period['prev_label']);
    $change = report_period_change($now, $before);
    if ($change === null) {
        return '<span class="text-brand-plum/40">' . ($now == 0 ? 'Nothing' : $currency . '0') . ' in ' . e($period['prev_label']) . '</span>';
    }
    [$dir, $pct] = $change;
    if ($dir === 'flat') {
        return '<span class="text-brand-plum/50">No change ' . $vs . '</span>';
    }
    $is_good = $dir === $good;
    $arrow = $dir === 'up' ? '▲' : '▼';
    $words = $dir === 'up' ? 'Up' : 'Down';
    return '<span class="font-semibold ' . ($is_good ? 'text-green-700' : 'text-red-700') . '">'
         . '<span aria-hidden="true">' . $arrow . '</span><span class="sr-only">' . $words . '</span> ' . $pct
         . '</span> <span class="text-brand-plum/50">' . $vs . '</span>';
};

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
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Money that came in and went out in <strong class="text-brand-plum"><?= $month_label ?></strong></p>
    </div>
    <div class="flex items-center gap-2">
        <!-- Period: one button, presets and a month range in its menu -->
        <div class="relative" x-data="{ open: false, range: <?= $period['key'] === 'custom' ? 'true' : 'false' ?>, fm: '<?= substr($period['from'], 5) ?>', fy: '<?= substr($period['from'], 0, 4) ?>', tm: '<?= substr($period['to'], 5) ?>', ty: '<?= substr($period['to'], 0, 4) ?>' }"
             @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true"
                    class="flex items-center gap-2 text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 hover:border-accent focus:outline-none focus:border-accent-text">
                <svg class="w-3.5 h-3.5 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span><?= e($period['button']) ?></span>
                <svg class="w-3 h-3 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="nf-pop is-period" role="dialog" aria-label="Choose a period">
                <div class="nf-menu" style="padding:4px 0">
                    <?php foreach (REPORT_PERIOD_PRESETS as $key => $name): $on = $period['key'] === $key; ?>
                        <a href="<?= APP_URL ?>/reports/profit-loss?period=<?= $key ?>"<?= $on ? ' aria-current="true"' : '' ?>>
                            <span style="flex:1"><?= e($name) ?></span>
                            <?php if ($on): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="color:var(--accent-text)"><path d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <hr>
                    <button type="button" @click="range = !range" :aria-expanded="range"<?= $period['key'] === 'custom' ? ' aria-current="true"' : '' ?>>
                        <span style="flex:1">Month range</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" :style="range ? 'transform:rotate(180deg)' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>
                <form x-show="range" x-cloak method="GET" action="<?= APP_URL ?>/reports/profit-loss" class="px-4 pb-4 pt-1 space-y-3">
                    <input type="hidden" name="from" :value="fy + '-' + fm">
                    <input type="hidden" name="to" :value="ty + '-' + tm">
                    <?php foreach (['From' => ['fm', 'fy'], 'To' => ['tm', 'ty']] as $word => [$mv, $yv]): ?>
                        <fieldset class="flex items-center gap-2">
                            <legend class="sr-only"><?= $word ?></legend>
                            <span class="w-10 text-xs font-semibold text-brand-plum/60" aria-hidden="true"><?= $word ?></span>
                            <select x-model="<?= $mv ?>" aria-label="<?= $word ?> month" class="flex-1 text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
                                <?php foreach ($available_months as $m => $label): ?>
                                    <option value="<?= sprintf('%02d', $m) ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select x-model="<?= $yv ?>" aria-label="<?= $word ?> year" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
                                <?php foreach ($available_years as $y): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endforeach; ?>
                            </select>
                        </fieldset>
                    <?php endforeach; ?>
                    <button type="submit" class="btn-primary w-full text-white px-4 py-2.5 rounded-lg text-xs font-bold">Show These Months</button>
                </form>
            </div>
        </div>
        <!-- PDF Download -->
        <button onclick="downloadReport()" class="flex items-center gap-2 bg-brand-white border border-brand-sand px-4 py-2.5 rounded-lg hover:border-accent transition-colors">
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
        <p class="text-[10px] text-brand-plum/40 mt-1">Payments received in <?= e($month_label) ?></p>
        <p class="text-[10px] mt-1"><?= $compare($revenue, $prev_revenue, 'up') ?></p>
    </div>

    <!-- Money out: what was paid, when it was paid. Stock bought is part of it, shown on its own line -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Money Out</div>
        </div>
        <div class="font-heading font-extrabold text-2xl text-red-600"><?= $currency ?><?= number_format($money_out, 0) ?></div>
        <p class="text-[10px] text-brand-plum/40 mt-1">Paid out in <?= e($month_label) ?></p>
        <?php if ($stock_out > 0): ?>
            <p class="flex items-baseline justify-between gap-2 text-[10px] mt-2 pt-2 border-t border-brand-sand">
                <span class="font-semibold text-brand-plum/60">Spent on stock</span>
                <span class="font-semibold text-brand-plum"><?= $stock_text ?></span>
            </p>
        <?php endif; ?>
        <p class="text-[10px] mt-1"><?= $compare($money_out, $prev_out, 'down') ?></p>
    </div>

    <!-- Left after spending: in less out, as on Home -->
    <div class="border-2 rounded-xl p-5 shadow-sm <?= $tone['card'] ?>">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg <?= $tone['icon'] ?> flex items-center justify-center">
                <svg class="w-4 h-4 <?= $tone['ink'] ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Left after spending</div>
        </div>
        <div class="font-heading font-extrabold text-2xl <?= $tone['value'] ?>">
            <?= $left < 0 ? '−' : '' ?><?= $currency ?><?= number_format(abs($left), 0) ?>
        </div>
        <p class="text-[10px] <?= $tone['ink'] ?> mt-1 font-medium">
            <?php if ($left_reads === 'stock'): ?>
                <?= $stock_text ?> of it went into stock you can still sell
            <?php elseif ($revenue > 0 && $left_reads === 'up'): ?>
                For every <?= $currency ?>100 that came in, you kept <?= $currency ?><?= $kept_per_100 ?>
            <?php elseif ($revenue > 0): ?>
                For every <?= $currency ?>100 that came in, <?= $currency ?><?= $out_per_100 ?> went out
            <?php elseif ($money_out > 0): ?>
                No money came in this period
            <?php else: ?>
                Nothing came in or went out
            <?php endif; ?>
        </p>
        <p class="text-[10px] mt-1"><?= $compare($left, $prev_left, 'up') ?></p>
    </div>
</div>

<!-- Plain-Language Summary -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-lg bg-brand-cream flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="text-xs text-brand-plum/70 leading-relaxed">
            <?php $in_when = ($period['months'] === 1 ? 'In ' : 'From ') . e($month_label); ?>
            <?php if ($revenue == 0 && $money_out == 0): ?>
                <strong class="text-brand-plum">Nothing came in or went out <?= lcfirst($in_when) ?>.</strong> Money shows here as customers pay you and as you pay for things.
            <?php elseif ($left_reads === 'up'): ?>
                <strong class="text-brand-plum">Good news!</strong> <?= $in_when ?>, <strong class="text-green-600"><?= $currency ?><?= number_format($revenue, 0) ?></strong> came in and you paid out <strong class="text-red-600"><?= $currency ?><?= number_format($money_out, 0) ?></strong>, so <strong class="text-green-600"><?= $currency ?><?= number_format($left, 0) ?></strong> was left.
                <?php if ($stock_out > 0): ?> <?= $stock_text ?> of what you paid went on stock.<?php endif; ?>
                <?php if ($kept_per_100 >= 50): ?> You kept more than half of what came in.<?php elseif ($kept_per_100 >= 20): ?> A healthy share was left: keep an eye on costs.<?php else: ?> Little was left: look at your biggest costs below.<?php endif; ?>
            <?php elseif ($left_reads === 'stock'): ?>
                <strong class="text-brand-plum">Money into stock.</strong> <?= $in_when ?>, you paid out <?= $currency ?><?= number_format($money_out, 0) ?>, more than the <?= $currency ?><?= number_format($revenue, 0) ?> that came in, but <strong class="text-amber-700"><?= $stock_text ?></strong> of it went on stock, which is still yours to sell.
                Not counting stock, <?= $currency ?><?= number_format($left + $stock_out, 0) ?> was left.
            <?php else: ?>
                <strong class="text-brand-plum">Heads up.</strong> <?= $in_when ?>, you paid out more (<?= $currency ?><?= number_format($money_out, 0) ?>) than came in (<?= $currency ?><?= number_format($revenue, 0) ?>). The gap is <strong class="text-red-600"><?= $currency ?><?= number_format(abs($left), 0) ?></strong>.
                <?php if ($stock_out > 0): ?> <?= $stock_text ?> of what you paid went on stock.<?php endif; ?>
                <?php if ($pending_revenue > 0): ?> You have <?= $currency ?><?= number_format($pending_revenue, 0) ?> in unpaid invoices: collecting those would help.<?php endif; ?>
            <?php endif; ?>
            <?php if ($owe_total > 0): ?> Bills you haven't paid yet (<?= $currency ?><?= number_format($owe_total, 0) ?>) count once you pay them; they are under Money You Owe.<?php endif; ?>
        </div>
    </div>
</div>

<!-- Trend Chart: the period by day (a month or less) or by month -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider"><?= $period['by'] === 'day' ? 'Day by Day' : 'Month by Month' ?></h2>
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
            <p class="text-xs text-brand-plum/40">Nothing was paid out in this period.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($expense_categories as $cat): ?>
                    <?php
                        $pct = $money_out > 0 ? round(($cat['total'] / $money_out) * 100) : 0;
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
                        <div class="text-[9px] text-brand-plum/30 mt-0.5"><?= $pct ?>% of money out</div>
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
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Owed to me</div>
                    <div class="font-heading font-extrabold text-xl text-amber-600"><?= $currency ?><?= number_format($debts_receivable, 0) ?></div>
                    <div class="text-[10px] text-brand-plum/40"><?= $debts_receivable_count ?> debt<?= $debts_receivable_count !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($pending_revenue > 0 || $debts_receivable > 0): ?>
                <p class="text-[10px] text-brand-plum/50 mt-3 leading-relaxed border-t border-brand-sand/50 pt-3">
                    If you collect everything, that's an extra <strong class="text-green-600"><?= $currency ?><?= number_format($pending_revenue + $collectable_extra, 0) ?></strong> coming in.
                </p>
            <?php endif; ?>
        </div>

        <!-- Payables: unpaid expenses and debts typed in. Not money out until paid -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Money You Owe</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Unpaid expenses</div>
                    <div class="font-heading font-extrabold text-xl text-red-600"><?= $currency ?><?= number_format($owe_unpaid, 0) ?></div>
                    <div class="text-[10px] text-brand-plum/40"><?= (int)$owe_unpaid_count ?> expense<?= (int)$owe_unpaid_count !== 1 ? 's' : '' ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Other debts</div>
                    <div class="font-heading font-extrabold text-xl text-red-600"><?= $currency ?><?= number_format($owe_debts, 0) ?></div>
                    <div class="text-[10px] text-brand-plum/40"><?= (int)$owe_debts_count ?> debt<?= (int)$owe_debts_count !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($owe_total > 0): ?>
                <p class="text-[10px] text-brand-plum/50 mt-3 leading-relaxed border-t border-brand-sand/50 pt-3">
                    Not in Money Out until you pay it. Then it counts on the day you pay.
                </p>
            <?php endif; ?>
        </div>

        <!-- Invoices Issued -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Invoices Issued</h2>
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
                label: 'Money in',
                data: trend.map(t => t.revenue),
                backgroundColor: 'rgba(34, 197, 94, 0.7)',
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
            },
            {
                label: 'Money out',
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
        filename: 'PnL_<?= $period['filename'] ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $business['name'] ?? 'Report') ?>.pdf',
        image: { type: 'jpeg', quality: 0.95 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save();
}
</script>
