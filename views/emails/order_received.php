<?php /* views/emails/order_received.php — to a shopper after they order on a shop.
   Variables: $first_name, $shop_name, $order_number, $order_url, $lines (name, quantity, total), $total, $is_whatsapp,
   $details (the whole order with photos, order_mail_details(); older queued emails have only $lines) */ ?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;">
    Thank you, <?= htmlspecialchars($first_name, ENT_QUOTES) ?>.
</h2>
<p style="margin:0 0 16px;color:#3a2f2b;">
    <?= htmlspecialchars($shop_name, ENT_QUOTES) ?> has your order <strong><?= htmlspecialchars($order_number, ENT_QUOTES) ?></strong>.
    <?= $is_whatsapp
        ? 'If you haven\'t sent it on WhatsApp yet, do that so they can confirm it with you.'
        : 'They will check your payment and get it ready.' ?>
    You can follow every step on your order's page.
</p>
<?php if (!empty($details)): ?>
<?php require __DIR__ . '/_order_details.php'; ?>
<?php else: ?>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;border-collapse:collapse;">
    <?php foreach ($lines as $line): ?>
    <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee4dc;color:#3a2f2b;font-size:14px;">
            <?= htmlspecialchars($line['name'], ENT_QUOTES) ?> × <?= (int)$line['quantity'] ?>
        </td>
        <td align="right" style="padding:8px 0;border-bottom:1px solid #eee4dc;color:#1B0A11;font-size:14px;font-weight:700;white-space:nowrap;">
            <?= htmlspecialchars($line['total'], ENT_QUOTES) ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td style="padding:12px 0 0;color:#1B0A11;font-size:15px;font-weight:700;">Total</td>
        <td align="right" style="padding:12px 0 0;color:#1B0A11;font-size:15px;font-weight:700;white-space:nowrap;"><?= htmlspecialchars($total, ENT_QUOTES) ?></td>
    </tr>
</table>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= htmlspecialchars($biz_color, ENT_QUOTES) ?>;">
            <a href="<?= htmlspecialchars($order_url, ENT_QUOTES) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:<?= htmlspecialchars($biz_on, ENT_QUOTES) ?>;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                Follow your order →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:11px;color:#9a8f8a;">
    Anyone with this link can see your order, so share it only with people you trust.
</p>
<p style="margin:0;font-size:11px;color:#9a8f8a;">
    Link: <a href="<?= htmlspecialchars($order_url, ENT_QUOTES) ?>" style="color:<?= htmlspecialchars($biz_link, ENT_QUOTES) ?>;word-break:break-all;"><?= htmlspecialchars($order_url, ENT_QUOTES) ?></a>
</p>
