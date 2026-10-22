<?php
/**
 * Dompdf Strict Print Template
 * Expects: $business, $customer, $quotation, $items, $bank, $sign (doc_sign_state)
 */
$currency = $business['currency_symbol'] ?? '₦';
$primary_color = brand_doc_colours($business)['primary'];   // the brand colour, else Nfelia's (app/helpers/brand.php)

// Convert logo to base64 to ensure Dompdf renders it flawlessly
$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $type = pathinfo(BASE_PATH . '/' . $business['logo_path'], PATHINFO_EXTENSION);
    $data = file_get_contents(BASE_PATH . '/' . $business['logo_path']);
    $logo_src = 'data:image/' . $type . ';base64,' . base64_encode($data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quotation <?= e($quotation['doc_number']) ?></title>
    <style>
        @page { margin: 40px 50px; }
        body { 
            font-family: 'DejaVu Sans', sans-serif; 
            color: #333; 
            font-size: 12px; 
            line-height: 1.4;
        }
        .header { width: 100%; border-bottom: 3px solid <?= $primary_color ?>; padding-bottom: 20px; margin-bottom: 30px; }
        .header table { width: 100%; }
        .logo { max-height: 60px; max-width: 150px; }
        .company-title { font-size: 24px; font-weight: bold; color: <?= $primary_color ?>; margin: 0 0 5px 0; }
        .company-details { font-size: 10px; color: #666; }
        .doc-title { font-size: 28px; font-weight: bold; text-align: right; color: #111; margin: 0; text-transform: uppercase; }
        .doc-meta { text-align: right; font-size: 11px; color: #555; }
        
        .client-box { background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; margin-bottom: 30px; width: 50%; }
        .client-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 5px; }
        .client-name { font-size: 16px; font-weight: bold; margin: 0; color: #111; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: #f1f5f9; padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        
        .totals-box { width: 40%; float: right; margin-bottom: 40px; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 6px 0; font-size: 12px; }
        .totals-table .grand-total { font-size: 18px; font-weight: bold; color: <?= $primary_color ?>; border-top: 2px solid <?= $primary_color ?>; padding-top: 10px; }
        
        .footer-blocks { clear: both; margin-top: 30px; width: 100%; }
        .block-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
        .notes-box { margin-bottom: 20px; font-size: 11px; color: #555; }
        
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <?php if ($logo_src): ?>
                        <img src="<?= $logo_src ?>" class="logo">
                    <?php else: ?>
                        <h1 class="company-title"><?= e($business['name']) ?></h1>
                    <?php endif; ?>
                    <div class="company-details">
                        <?= e($business['address']) ?><br>
                        <?= e($business['phone']) ?> | <?= e($business['email']) ?>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <h2 class="doc-title">Quotation</h2>
                    <div class="doc-meta">
                        <strong>Ref:</strong> <?= e($quotation['doc_number']) ?><br>
                        <strong>Date:</strong> <?= date('d M Y', strtotime($quotation['issue_date'])) ?><br>
                        <?php if ($quotation['expiry_date']): ?>
                            <strong>Valid Until:</strong> <?= date('d M Y', strtotime($quotation['expiry_date'])) ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($customer): ?>
        <div class="client-box">
            <div class="client-title">Prepared For</div>
            <p class="client-name"><?= e($customer['full_name']) ?></p>
            <div style="font-size: 11px; color: #555; margin-top: 5px;">
                <?php if ($customer['business_name']): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
                <?= e($customer['email']) ?><br>
                <?= e($customer['phone']) ?>
            </div>
        </div>
    <?php endif; ?>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><strong><?= e($it['description']) ?></strong></td>
                    <td class="text-center"><?= number_format((float)$it['quantity'], 2) ?></td>
                    <td class="text-right"><?= money((float)$it['unit_price'], $currency) ?></td>
                    <td class="text-right font-bold"><?= money((float)$it['subtotal'], $currency) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-box">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right"><?= money((float)$quotation['subtotal'], $currency) ?></td>
            </tr>
            <?php if ((float)$quotation['discount_amount'] > 0): ?>
                <tr>
                    <td>Discount:</td>
                    <td class="text-right" style="color: red;">-<?= money((float)$quotation['discount_amount'], $currency) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ((float)$quotation['tax_rate'] > 0): ?>
                <tr>
                    <td>Tax (<?= number_format((float)$quotation['tax_rate'], 1) ?>%):</td>
                    <td class="text-right"><?= money((float)$quotation['tax_amount'], $currency) ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td class="grand-total">Total:</td>
                <td class="grand-total text-right"><?= money((float)$quotation['total'], $currency) ?></td>
            </tr>
        </table>
    </div>

    <div class="footer-blocks">
        <?php if ($bank): ?>
            <div style="width: 50%; float: left;">
                <div class="block-title">Payment Details</div>
                <div class="notes-box">
                    <strong>Bank:</strong> <?= e($bank['bank_name']) ?><br>
                    <strong>Account Name:</strong> <?= e($bank['account_name']) ?><br>
                    <strong>Account No:</strong> <?= e($bank['account_number']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($quotation['notes']): ?>
            <div style="width: 100%; clear: both; padding-top: 10px;">
                <div class="block-title">Terms & Conditions</div>
                <div class="notes-box"><?= nl2br(e($quotation['notes'])) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <?php require BASE_PATH . '/views/partials/doc_signs_pdf.php'; ?>

</body>
</html>