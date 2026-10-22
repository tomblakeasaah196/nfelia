<?php
/**
 * Nfelia — Profit & Loss PDF Template (dompdf)
 * Variables: $business, $month_label, $revenue (money in), $money_out (money paid
 *            out), $stock_out (the part spent on stock), $left (in less out),
 *            $left_reads (up, stock or down: money_left_reads()), $expense_categories
 *            (money out by category), $currency, $primary, $primary_text, $logo_src,
 *            $period_start, $period_end. Cash in, cash out, as on the screen.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>P&L <?= e($month_label) ?></title>
    <style>
        @page { margin: 40px 50px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.5; }
        .header { border-bottom: 4px solid <?= $primary ?>; padding-bottom: 20px; margin-bottom: 28px; }
        .header table { width: 100%; }
        .logo { max-height: 48px; max-width: 140px; }
        .biz-name { font-size: 18px; font-weight: bold; color: <?= $primary_text ?>; }
        .doc-title { font-size: 22px; font-weight: bold; color: #0f172a; text-align: right; text-transform: uppercase; }
        .period { text-align: right; font-size: 10px; color: #64748b; margin-top: 4px; }
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .kpi-table td { width: 33.3%; padding: 18px 20px; border: 1px solid #e2e8f0; vertical-align: top; }
        .kpi-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 6px; font-weight: bold; }
        .kpi-value { font-size: 22px; font-weight: bold; }
        .kpi-green { color: #16a34a; }
        .kpi-red   { color: #dc2626; }
        .kpi-amber { color: #b45309; }
        .kpi-blue  { color: <?= $primary_text ?>; }
        .section-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.1em; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin: 20px 0 12px; }
        .line-table { width: 100%; border-collapse: collapse; }
        .line-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        .line-table td:last-child { text-align: right; font-weight: bold; }
        .total-row td { border-top: 2px solid <?= $primary ?>; border-bottom: none; font-weight: bold; font-size: 13px; color: <?= $primary_text ?>; padding-top: 10px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 6px 0; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="vertical-align:top;">
                    <?php if ($logo_src): ?>
                        <img src="<?= $logo_src ?>" class="logo"><br>
                    <?php else: ?>
                        <div class="biz-name"><?= e($business['name']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="vertical-align:top;">
                    <div class="doc-title">Profit & Loss Statement</div>
                    <div class="period"><?= e($month_label) ?> · <?= e($business['name']) ?></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- KPI Summary -->
    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Money in</div>
                <div class="kpi-value kpi-green"><?= money($revenue, $currency) ?></div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;">Payments received</div>
            </td>
            <td>
                <div class="kpi-label">Money out</div>
                <div class="kpi-value kpi-red"><?= money($money_out, $currency) ?></div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;">Paid out<?= $stock_out > 0 ? ', ' . e(money($stock_out, $currency)) . ' of it on stock' : '' ?></div>
            </td>
            <td style="background:<?= ['up' => '#f0fdf4', 'stock' => '#fffbeb', 'down' => '#fff1f2'][$left_reads] ?>;">
                <div class="kpi-label">Left after spending</div>
                <div class="kpi-value <?= ['up' => 'kpi-green', 'stock' => 'kpi-amber', 'down' => 'kpi-red'][$left_reads] ?>">
                    <?= $left < 0 ? '−' : '' ?><?= money(abs($left), $currency) ?>
                </div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;"><?= $left_reads === 'stock' ? 'Money in − money out. Stock bought covers the gap' : 'Money in − money out' ?></div>
            </td>
        </tr>
    </table>

    <!-- Money in -->
    <div class="section-label">Money in</div>
    <table class="line-table">
        <tr><td>Payments received (<?= e($month_label) ?>)</td><td><?= money($revenue, $currency) ?></td></tr>
        <tr class="total-row"><td>Money in</td><td><?= money($revenue, $currency) ?></td></tr>
    </table>

    <!-- Money out by category: what was paid, when it was paid -->
    <div class="section-label">Money out by category</div>
    <table class="line-table">
        <?php if (empty($expense_categories)): ?>
        <tr><td colspan="2" style="color:#94a3b8;font-style:italic;">Nothing was paid out this period.</td></tr>
        <?php else: ?>
            <?php foreach ($expense_categories as $cat): ?>
            <tr>
                <td><?= e(ucwords(str_replace('_', ' ', $cat['category']))) ?></td>
                <td><?= money((float)$cat['total'], $currency) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr class="total-row"><td>Money out</td><td><?= money($money_out, $currency) ?></td></tr>
    </table>
    <p style="font-size:9px;color:#94a3b8;margin:6px 0 0;">Expenses not paid yet are not money out: they count on the day they are paid.</p>

    <!-- Net -->
    <div class="section-label">Summary</div>
    <table class="line-table">
        <tr><td>Money in</td><td><?= money($revenue, $currency) ?></td></tr>
        <tr><td>Money out</td><td style="color:#dc2626;">−<?= money($money_out, $currency) ?></td></tr>
        <?php if ($stock_out > 0): ?>
        <tr><td style="padding-left:14px;color:#64748b;">of which spent on stock</td><td style="color:#64748b;font-weight:normal;"><?= money($stock_out, $currency) ?></td></tr>
        <?php endif; ?>
        <tr class="total-row">
            <td>Left after spending</td>
            <td style="color:<?= ['up' => '#16a34a', 'stock' => '#b45309', 'down' => '#dc2626'][$left_reads] ?>;">
                <?= $left < 0 ? '−' : '' ?><?= money(abs($left), $currency) ?>
            </td>
        </tr>
    </table>

    <div class="footer">
        <?= e($business['name']) ?> · P&L Statement · <?= e($month_label) ?> · Powered by Nfelia
    </div>
</body>
</html>
