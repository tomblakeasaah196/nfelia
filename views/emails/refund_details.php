<?php
/*
 * views/emails/refund_details.php — to a customer the shop is giving money
 * back to: where should it go? (refund_ask() in app/helpers/orders.php)
 * Variables: $first_name, $shop_name, $order_number, $amount (already
 * written with its currency), $refund_url (the customer's own refund page)
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    Where should we send your <?= $h($amount) ?>?
</h2>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">
    Hello <?= $h($first_name) ?>. <?= $h($shop_name) ?> is giving you <?= $h($amount) ?> back for order <?= $h($order_number) ?>.
    Tell them the bank account or mobile money number it should go to.
</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($refund_url) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:<?= planner_text_on($biz_color) ?>;text-decoration:none;">
                Send your account details
            </a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:12px;line-height:1.4;color:#5B4650;">
    Order <?= $h($order_number) ?>. Only use this link if you were expecting money back from <?= $h($shop_name) ?>.
</p>
