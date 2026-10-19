<?php
/**
 * Nfelia — Receipt PDF Template (dompdf)
 * Variables: $business, $customer, $receipt, $items, $invoice, $sign (doc_sign_state)
 */
$currency = $business['currency_symbol'] ?? '₦';
$primary  = $business['primary_color']   ?? '#0F172A';

$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $ext  = strtolower(pathinfo($business['logo_path'], PATHINFO_EXTENSION));
    $mime = in_array($ext, ['svg']) ? 'image/svg+xml' : "image/{$ext}";
    $logo_src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(BASE_PATH . '/' . $business['logo_path']));
}

$method_label = match($receipt['method'] ?? 'cash') {
    'bank_transfer' => 'Bank Transfer',
    'pos'           => 'POS',
    'mobile_money'  => 'Mobile Money',
    'cheque'        => 'Cheque',
    'other'         => 'Other',
    default         => 'Cash',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt <?= e($receipt['doc_number']) ?></title>
    <style>
        @page { margin: 40px 50px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.5; }
        .header { width: 100%; border-bottom: 4px solid <?= $primary ?>; padding-bottom: 20px; margin-bottom: 28px; }
        .header table { width: 100%; }
        .logo { max-height: 56px; max-width: 150px; }
        .biz-name { font-size: 20px; font-weight: bold; color: <?= $primary ?>; margin: 0 0 4px; }
        .biz-detail { font-size: 9.5px; color: #64748b; line-height: 1.6; }
        .doc-title { font-size: 26px; font-weight: bold; text-align: right; color: #0f172a; text-transform: uppercase; margin: 0 0 6px; }
        .paid-stamp { font-size: 16px; font-weight: bold; color: #16a34a; border: 3px solid #16a34a; padding: 4px 14px; display: inline-block; letter-spacing: 0.15em; }
        .doc-meta { text-align: right; font-size: 10px; color: #475569; margin-top: 6px; }
        .client-box { background: #f0fdf4; border-left: 3px solid #16a34a; padding: 14px; margin-bottom: 28px; width: 48%; }
        .label { font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.1em; margin-bottom: 5px; }
        .client-name { font-size: 14px; font-weight: bold; color: #0f172a; margin-bottom: 3px; }
        .client-detail { font-size: 10px; color: #475569; line-height: 1.6; }
        .amount-block { text-align: center; margin: 24px 0; padding: 24px; background: <?= $primary ?>; color: #fff; }
        .amount-label { font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; opacity: 0.75; margin-bottom: 8px; }
        .amount-value { font-size: 36px; font-weight: bold; }
        .amount-method { font-size: 10px; opacity: 0.75; margin-top: 6px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table thead th { background: #f1f5f9; padding: 9px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 2px solid #cbd5e1; }
        .items-table tbody td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .text-right { text-align: right; }
        .linked-inv { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; margin-bottom: 20px; font-size: 10px; color: #475569; }
        .note-box { margin-top: 16px; font-size: 10px; color: #475569; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 6px 0; }
    </style>
</head>
<body>
    <div class="header">
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
                    <div class="doc-title">Receipt</div>
                    <div style="text-align:right;">
                        <span class="paid-stamp">PAID</span>
                    </div>
                    <div class="doc-meta">
                        <strong>Ref:</strong> <?= e($receipt['doc_number']) ?><br>
                        <?php if (!empty($order_number)): ?><strong>Order:</strong> <?= e($order_number) ?><br><?php endif; ?>
                        <strong>Date:</strong> <?= date('d M Y', strtotime($receipt['issue_date'])) ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($customer): ?>
    <div class="client-box">
        <div class="label">Received From</div>
        <div class="client-name"><?= e($customer['full_name']) ?></div>
        <div class="client-detail">
            <?php if ($customer['business_name']): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
            <?= e($customer['email'] ?? '') ?><br>
            <?= e($customer['phone'] ?? '') ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="amount-block" style="clear:both;">
        <div class="amount-label">Total Amount Received</div>
        <div class="amount-value"><?= money((float)$receipt['amount'], $currency) ?></div>
        <div class="amount-method">Payment Method: <?= e($method_label) ?></div>
        <?php if ((float)$receipt['balance_remaining'] > 0): ?>
        <div class="amount-method" style="margin-top:6px;opacity:0.9;">Balance Remaining: <?= money((float)$receipt['balance_remaining'], $currency) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($invoice): ?>
    <div class="linked-inv">
        <strong>Payment towards Invoice:</strong> <?= e($invoice['doc_number']) ?>
        — Total: <?= money((float)$invoice['total'], $currency) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
    <div class="label">Items</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['description']) ?></td>
                <td class="text-right"><?= number_format((float)$it['quantity'], 2) ?></td>
                <td class="text-right"><?= money((float)$it['unit_price'], $currency) ?></td>
                <td class="text-right"><strong><?= money((float)$it['subtotal'], $currency) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($receipt['note']): ?>
    <div class="note-box">
        <div class="label">Note</div>
        <?= nl2br(e($receipt['note'])) ?>
    </div>
    <?php endif; ?>

    <?php require BASE_PATH . '/views/partials/doc_signs_pdf.php'; ?>

    <div class="footer">
        <?= e($business['name']) ?> · Powered by Nfelia · nfelia.com
    </div>
</body>
</html>
