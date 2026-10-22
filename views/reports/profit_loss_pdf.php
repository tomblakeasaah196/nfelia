<?php
/**
 * Nfelia — Profit & Loss PDF Template (dompdf)
 * Variables: $business, $month_label, $revenue, $expenses_total, $gross_profit,
 *            $expense_categories, $currency, $primary, $primary_text, $logo_src, $period_start, $period_end
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
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value kpi-green"><?= money($revenue, $currency) ?></div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;">Payments received</div>
            </td>
            <td>
                <div class="kpi-label">Total Expenses</div>
                <div class="kpi-value kpi-red"><?= money($expenses_total, $currency) ?></div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;">All recorded expenses</div>
            </td>
            <td style="background:<?= $gross_profit >= 0 ? '#f0fdf4' : '#fff1f2' ?>;">
                <div class="kpi-label">Net <?= $gross_profit >= 0 ? 'Profit' : 'Loss' ?></div>
                <div class="kpi-value <?= $gross_profit >= 0 ? 'kpi-green' : 'kpi-red' ?>">
                    <?= money(abs($gross_profit), $currency) ?>
                </div>
                <div style="font-size:9px;color:#94a3b8;margin-top:4px;">Revenue − Expenses</div>
            </td>
        </tr>
    </table>

    <!-- Revenue line -->
    <div class="section-label">Revenue</div>
    <table class="line-table">
        <tr><td>Payments Received (<?= e($month_label) ?>)</td><td><?= money($revenue, $currency) ?></td></tr>
        <tr class="total-row"><td>Total Revenue</td><td><?= money($revenue, $currency) ?></td></tr>
    </table>

    <!-- Expenses breakdown -->
    <div class="section-label">Expenses by Category</div>
    <table class="line-table">
        <?php if (empty($expense_categories)): ?>
        <tr><td colspan="2" style="color:#94a3b8;font-style:italic;">No expenses recorded this period.</td></tr>
        <?php else: ?>
            <?php foreach ($expense_categories as $cat): ?>
            <tr>
                <td><?= e(ucwords(str_replace('_', ' ', $cat['category']))) ?></td>
                <td><?= money((float)$cat['total'], $currency) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr class="total-row"><td>Total Expenses</td><td><?= money($expenses_total, $currency) ?></td></tr>
    </table>

    <!-- Net -->
    <div class="section-label">Summary</div>
    <table class="line-table">
        <tr><td>Revenue</td><td><?= money($revenue, $currency) ?></td></tr>
        <tr><td>Expenses</td><td style="color:#dc2626;">−<?= money($expenses_total, $currency) ?></td></tr>
        <tr class="total-row">
            <td>Net <?= $gross_profit >= 0 ? 'Profit' : 'Loss' ?></td>
            <td style="color:<?= $gross_profit >= 0 ? '#16a34a' : '#dc2626' ?>;">
                <?= money(abs($gross_profit), $currency) ?>
            </td>
        </tr>
    </table>

    <div class="footer">
        <?= e($business['name']) ?> · P&L Statement · <?= e($month_label) ?> · Powered by Nfelia
    </div>
</body>
</html>
