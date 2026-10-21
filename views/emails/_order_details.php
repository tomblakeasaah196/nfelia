<?php
/*
 * views/emails/_order_details.php — the order in an email: each line with its
 * photo, the money, and how and when it reaches the customer. Included by
 * order_update.php and order_received.php.
 * Expects $details from order_mail_details() (app/helpers/shop_orders.php).
 */
$dh = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$d_row = 'padding:6px 0;font-size:14px;line-height:1.4;color:#5B4650;';
$d_val = 'padding:6px 0;font-size:14px;line-height:1.4;color:#1F0A14;font-weight:600;text-align:right;white-space:nowrap;';
?>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 8px;border-collapse:collapse;">
    <?php foreach ($details['lines'] ?? [] as $dl): ?>
    <tr>
        <td width="64" valign="top" style="padding:10px 12px 10px 0;border-bottom:1px solid #EFE6EA;">
            <?php if (!empty($dl['photo'])): ?>
                <img src="<?= $dh($dl['photo']) ?>" width="56" height="56" alt="" style="display:block;width:56px;height:56px;border-radius:8px;object-fit:cover;border:0;">
            <?php else: ?>
                <div style="width:56px;height:56px;border-radius:8px;background-color:#F8EEF3;"></div>
            <?php endif; ?>
        </td>
        <td valign="top" style="padding:10px 0;border-bottom:1px solid #EFE6EA;font-size:14px;line-height:1.4;color:#1F0A14;">
            <strong><?= $dh($dl['name']) ?></strong>
            <?php if (!empty($dl['options'])): ?><br><span style="color:#5B4650;"><?= $dh($dl['options']) ?></span><?php endif; ?>
            <br><span style="color:#5B4650;"><?= $dh($dl['each']) ?></span>
        </td>
        <td valign="top" align="right" style="padding:10px 0 10px 12px;border-bottom:1px solid #EFE6EA;font-size:14px;line-height:1.4;color:#1F0A14;font-weight:700;white-space:nowrap;">
            <?= $dh($dl['total']) ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;border-collapse:collapse;">
    <?php if (!empty($details['discount'])): ?>
    <tr><td style="<?= $d_row ?>">Discount</td><td style="<?= $d_val ?>"><?= $dh($details['discount']) ?></td></tr>
    <?php endif; ?>
    <tr><td style="<?= $d_row ?>color:#1F0A14;font-weight:700;font-size:16px;">Total</td><td style="<?= $d_val ?>font-size:16px;font-weight:700;"><?= $dh($details['total'] ?? '') ?></td></tr>
    <?php if (!empty($details['paid'])): ?>
    <tr><td style="<?= $d_row ?>">Paid</td><td style="<?= $d_val ?>"><?= $dh($details['paid']) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($details['balance'])): ?>
    <tr><td style="<?= $d_row ?>">Balance<?= !empty($details['due_by']) ? ', due ' . $dh($details['due_by']) : '' ?></td><td style="<?= $d_val ?>color:#B42318;"><?= $dh($details['balance']) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($details['how'])): ?>
    <tr><td style="<?= $d_row ?>"><?= $dh($details['how']) ?></td><td style="<?= $d_val ?>"><?= $dh($details['when'] ?? '') ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($details['address'])): ?>
    <tr><td colspan="2" style="<?= $d_row ?>">To: <?= nl2br($dh($details['address'])) ?></td></tr>
    <?php endif; ?>
</table>
