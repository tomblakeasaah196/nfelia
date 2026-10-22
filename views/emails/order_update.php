<?php
/*
 * views/emails/order_update.php — to a shopper as their order moves along
 * (shop_order_tell() in app/helpers/shop_orders.php).
 * Variables: $state (confirmed | preparing | ready | on_the_way | delivered |
 * collected | cancelled | turned_down), $first_name, $shop_name,
 * $order_number, $order_url, $review_url, $message (the shop's own words),
 * $details (confirmed, delivered and collected: the whole order, see
 * order_mail_details())
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
[$headline, $line] = match ($state) {
    'confirmed'   => ['Your order is confirmed', $shop_name . ' has confirmed your order ' . $order_number . ' and will get it ready now.'],
    'preparing'   => ['Your order is being prepared', $shop_name . ' is getting your order ' . $order_number . ' ready.'],
    'ready'       => ['Your order is ready for pickup', $shop_name . ' has your order ' . $order_number . ' ready. Pick it up when it suits you.'],
    'on_the_way'  => ['Your order is on its way', $shop_name . ' has sent out your order ' . $order_number . '.'],
    'delivered'   => ['Your order has been delivered', 'Thank you for shopping with ' . $shop_name . '.'],
    'collected'   => ['Thank you for collecting your order', 'We hope you love it. Thank you for shopping with ' . $shop_name . '.'],
    'cancelled'   => ['Your order was cancelled', $shop_name . ' cancelled your order ' . $order_number . '. Contact them if you have any questions.'],
    default       => [$shop_name . ' couldn’t take your order', 'Your order ' . $order_number . ' wasn’t accepted. Contact ' . $shop_name . ' if you have any questions.'],
};
$button = !empty($review_url) ? ['Rate your order', $review_url] : (!empty($order_url) ? ['Follow your order', $order_url] : null);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    <?= $h($headline) ?>
</h2>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">
    Hello <?= $h($first_name) ?>. <?= $h($line) ?>
</p>
<?php if (!empty($message)): ?>
<p style="margin:0 0 24px;padding:12px 16px;background-color:<?= $h($biz_soft) ?>;font-size:14px;line-height:1.5;color:#1F0A14;">
    <?= nl2br($h($message)) ?>
</p>
<?php endif; ?>
<?php if (!empty($details)) { require __DIR__ . '/_order_details.php'; } ?>
<?php if ($button): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($button[1]) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:<?= planner_text_on($biz_color) ?>;text-decoration:none;">
                <?= $h($button[0]) ?>
            </a>
        </td>
    </tr>
</table>
<?php endif; ?>
<p style="margin:0;font-size:12px;line-height:1.4;color:#5B4650;">
    Order <?= $h($order_number) ?>. Anyone with its link can see it, so share it only with people you trust.
</p>
