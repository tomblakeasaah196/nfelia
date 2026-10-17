<?php
/**
 * Nfelia — Invoice PDF Template (dompdf)
 * Variables: $business, $customer, $invoice, $items, $bank, $payments
 */
$currency = $business['currency_symbol'] ?? '₦';
$primary  = $business['primary_color']   ?? '#0F172A';

$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $ext  = strtolower(pathinfo($business['logo_path'], PATHINFO_EXTENSION));
    $mime = in_array($ext, ['svg']) ? 'image/svg+xml' : "image/{$ext}";
    $logo_src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(BASE_PATH . '/' . $business['logo_path']));
}

$status_label = match($invoice['status'] ?? 'draft') {
    'paid'           => 'PAID',
    'partially_paid' => 'PARTIAL',
    'overdue'        => 'OVERDUE',
    'cancelled'      => 'CANCELLED',
    'sent'           => 'UNPAID',
    default          => strtoupper($invoice['status'] ?? 'DRAFT'),
};
$status_color = match($invoice['status'] ?? '') {
    'paid'    => '#16a34a',
    'overdue' => '#dc2626',
    default   => '#64748b',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice <?= e($invoice['doc_number']) ?></title>
    <style>
        @page { margin: 40px 50px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.5; }
        .header-bar { width: 100%; border-bottom: 4px solid <?= $primary ?>; padding-bottom: 20px; margin-bottom: 28px; }
        .header-bar table { width: 100%; }
        .logo { max-height: 56px; max-width: 150px; }
        .biz-name { font-size: 20px; font-weight: bold; color: <?= $primary ?>; margin: 0 0 4px; }
        .biz-detail { font-size: 9.5px; color: #64748b; line-height: 1.6; }
        .doc-title { font-size: 26px; font-weight: bold; text-align: right; color: #0f172a; text-transform: uppercase; margin: 0 0 6px; }
        .doc-meta { text-align: right; font-size: 10px; color: #475569; }
        .status-stamp {
            display: inline-block; padding: 3px 10px;
            border: 2px solid <?= $status_color ?>; color: <?= $status_color ?>;
            font-size: 10px; font-weight: bold; letter-spacing: 0.1em; text-transform: uppercase;
        }
        .bill-to { background: #f8fafc; padding: 14px; border-left: 3px solid <?= $primary ?>; margin-bottom: 28px; width: 48%; }
        .bill-label { font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.1em; margin-bottom: 5px; }
        .bill-name { font-size: 14px; font-weight: bold; color: #0f172a; margin-bottom: 3px; }
        .bill-detail { font-size: 10px; color: #475569; line-height: 1.6; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table thead th { background: <?= $primary ?>; color: #fff; padding: 10px 12px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; }
        .items-table tbody td { padding: 11px 12px; border-bottom: 1px solid #e2e8f0; font-size: 11px; vertical-align: top; }
        .items-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .text-right { text-align: right; }
        .totals-block { width: 42%; float: right; margin-bottom: 32px; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 6px 0; font-size: 11px; color: #334155; }
        .totals-table .grand td { font-size: 14px; font-weight: bold; color: <?= $primary ?>; border-top: 2px solid <?= $primary ?>; padding-top: 10px; }
        .totals-table .balance td { font-size: 13px; font-weight: bold; color: <?= $status_color ?>; padding-top: 6px; }
        .section-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.1em; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 10px; }
        .bank-box { float: left; width: 50%; font-size: 10px; line-height: 1.7; color: #334155; }
        .notes-box { clear: both; padding-top: 16px; font-size: 10px; color: #475569; line-height: 1.7; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 6px 0; }
    </style>
</head>
<body>
    <div class="header-bar">
        <table>
            <tr>
                <td style="width:50%;vertical-align:top;">
                    <?php if ($logo_src): ?>
                        <img src="<?= $logo_src ?>" class="logo"><br>
                    <?php else: ?>
                        <div class="biz-name"><?= e($business['name']) ?></div>
                    <?php endif; ?>
                    <div class="biz-detail">
                        <?= nl2br(e($business['address'] ?? '')) ?><br>
                        <?= e($business['phone'] ?? '') ?><?= ($business['phone'] && $business['email']) ? ' · ' : '' ?><?= e($business['email'] ?? '') ?>
                    </div>
                </td>
                <td style="width:50%;vertical-align:top;">
                    <div class="doc-title">Invoice</div>
                    <div class="doc-meta">
                        <span class="status-stamp"><?= e($status_label) ?></span><br><br>
                        <strong>Ref:</strong> <?= e($invoice['doc_number']) ?><br>
                        <strong>Issue Date:</strong> <?= date('d M Y', strtotime($invoice['issue_date'])) ?><br>
                        <?php if ($invoice['due_date']): ?>
                            <strong>Due Date:</strong> <?= date('d M Y', strtotime($invoice['due_date'])) ?>
                        <?php endif; ?>
                        <?php if ($delivery_line = planner_delivery_line($invoice)): [$dl_label, $dl_value] = explode(':', $delivery_line, 2); ?>
                            <br><strong><?= e($dl_label) ?>:</strong> <?= e(trim($dl_value)) ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($customer): ?>
    <div class="bill-to">
        <div class="bill-label">Bill To</div>
        <div class="bill-name"><?= e($customer['full_name']) ?></div>
        <div class="bill-detail">
            <?php if ($customer['business_name']): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
            <?= e($customer['email'] ?? '') ?><br>
            <?= e($customer['phone'] ?? '') ?>
        </div>
    </div>
    <?php endif; ?>

    <table class="items-table" style="clear:both;">
        <thead>
            <tr>
                <th style="width:45%;">Description</th>
                <th class="text-right" style="width:12%;">Qty</th>
                <th class="text-right" style="width:18%;">Unit Price</th>
                <th class="text-right" style="width:10%;">Disc %</th>
                <th class="text-right" style="width:15%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['description']) ?><?php if (!empty($it['product_sku'])): ?><br><span style="font-size:8.5px;color:#94a3b8;">SKU: <?= e($it['product_sku']) ?></span><?php endif; ?></td>
                <td class="text-right"><?= number_format((float)$it['quantity'], 2) ?></td>
                <td class="text-right"><?= money((float)$it['unit_price'], $currency) ?></td>
                <td class="text-right"><?= $it['discount_percent'] > 0 ? number_format((float)$it['discount_percent'], 1).'%' : '—' ?></td>
                <td class="text-right"><strong><?= money((float)$it['subtotal'], $currency) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-block">
        <table class="totals-table">
            <tr><td>Subtotal</td><td class="text-right"><?= money((float)$invoice['subtotal'], $currency) ?></td></tr>
            <?php if ((float)$invoice['discount_amount'] > 0): ?>
            <tr><td>Discount</td><td class="text-right" style="color:#dc2626;">−<?= money((float)$invoice['discount_amount'], $currency) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)$invoice['tax_rate'] > 0): ?>
            <tr><td>Tax (<?= number_format((float)$invoice['tax_rate'], 1) ?>%)</td><td class="text-right"><?= money((float)$invoice['tax_amount'], $currency) ?></td></tr>
            <?php endif; ?>
            <tr class="grand"><td><strong>Total</strong></td><td class="text-right"><strong><?= money((float)$invoice['total'], $currency) ?></strong></td></tr>
            <?php if ((float)$invoice['amount_paid'] > 0): ?>
            <tr><td>Amount Paid</td><td class="text-right" style="color:#16a34a;"><?= money((float)$invoice['amount_paid'], $currency) ?></td></tr>
            <tr class="balance"><td>Balance Due</td><td class="text-right"><?= money((float)$invoice['balance_due'], $currency) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="bank-box" style="clear:both;padding-top:10px;">
        <?php if ($bank): ?>
        <div class="section-label">Payment Details</div>
        <strong>Bank:</strong> <?= e($bank['bank_name']) ?><br>
        <strong>Account Name:</strong> <?= e($bank['account_name']) ?><br>
        <strong>Account No:</strong> <?= e($bank['account_number']) ?>
        <?php endif; ?>
    </div>

    <?php if ($invoice['notes']): ?>
    <div class="notes-box">
        <div class="section-label">Terms & Notes</div>
        <?= nl2br(e($invoice['notes'])) ?>
    </div>
    <?php endif; ?>

    <?php if ($invoice['payment_terms']): ?>
    <div class="notes-box" style="margin-top:8px;">
        <div class="section-label">Payment Terms</div>
        <?= nl2br(e($invoice['payment_terms'])) ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <?= e($business['name']) ?> · Powered by Nfelia · nfelia.com
    </div>
</body>
</html>
