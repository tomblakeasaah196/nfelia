<?php /* views/emails/booking_update.php — to a shopper about their booking.
   Variables: $state ('received', 'confirmed', 'declined', 'cancelled' or 'caution_refunded'), $first_name,
   $shop_name, $booking_number, $service, $when, $booking_url, $total, $deposit ('' when none),
   $caution (a stay's refundable caution fee, '' when none), $message (from the shop) */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$caution = $caution ?? '';
$headline = match ($state) {
    'confirmed' => 'You\'re booked, ' . $first_name . '.',
    'declined'  => 'About your booking, ' . $first_name . '.',
    'cancelled' => 'Your booking was cancelled.',
    'caution_refunded' => 'Your caution fee is on its way back.',
    default     => 'Thank you, ' . $first_name . '.',
};
$text = match ($state) {
    'confirmed' => $shop_name . ' confirmed your booking. See you then.',
    'declined'  => $shop_name . ' couldn\'t take this booking.',
    'cancelled' => $shop_name . ' cancelled this booking.',
    'caution_refunded' => $shop_name . ' gave back your caution fee of ' . $caution . '. Thank you for staying with them.',
    default     => $shop_name . ' has your booking request' . ($caution !== '' ? ' and will confirm it once they see your payment.' : ($deposit !== '' ? ' and will confirm it once they see your deposit.' : ' and will confirm it on the number you gave.')),
};
?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;"><?= $h($headline) ?></h2>
<p style="margin:0 0 16px;color:#3a2f2b;"><?= $h($text) ?></p>
<?php if ($message !== ''): ?>
<p style="margin:0 0 16px;padding:12px 14px;background:#f6f1ec;color:#3a2f2b;"><strong><?= $h($shop_name) ?>:</strong> <?= $h($message) ?></p>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;border-collapse:collapse;font-size:14px;color:#3a2f2b;">
    <tr><td style="padding:6px 0;color:#9a8f8a;width:120px;">Booking</td><td style="padding:6px 0;font-weight:700;color:#1B0A11;"><?= $h($booking_number) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;">For</td><td style="padding:6px 0;"><?= $h($service) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;">When</td><td style="padding:6px 0;"><?= $h($when) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;">Total</td><td style="padding:6px 0;"><?= $h($total) ?></td></tr>
    <?php if ($deposit !== ''): ?>
    <tr><td style="padding:6px 0;color:#9a8f8a;">Deposit</td><td style="padding:6px 0;"><?= $h($deposit) ?></td></tr>
    <?php endif; ?>
    <?php if ($caution !== ''): ?>
    <tr><td style="padding:6px 0;color:#9a8f8a;">Caution fee</td><td style="padding:6px 0;"><?= $h($caution) ?><?= $state === 'caution_refunded' ? ', given back' : ', refundable: paid with the booking, given back after you check out' ?></td></tr>
    <?php endif; ?>
</table>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($booking_url) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:<?= $h($biz_on) ?>;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                See your booking →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:11px;color:#9a8f8a;">
    Link: <a href="<?= $h($booking_url) ?>" style="color:<?= $h($biz_link) ?>;word-break:break-all;"><?= $h($booking_url) ?></a>
</p>
