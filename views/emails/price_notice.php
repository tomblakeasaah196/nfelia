<?php
/*
 * views/emails/price_notice.php — a client told about a price change
 * (Ops → Prices → Tell clients; prices_notice_vars() in app/helpers/prices.php).
 *
 * Written to keep them: it starts with what they have built with Nfelia,
 * says plainly what changes and from when, gives them time (any month that
 * starts before the date stays at today's price), says why, and asks them to
 * reply if it is hard. Good news is said as good news. Paying early is only
 * offered when it really keeps today's price ($hold).
 * Variables: $first_name, $business, $since, $stats, $received, $lines
 * (what, old, new), $from, $up, $down, $running (a paid month is running),
 * $next (when the month they would pay for starts), $hold, $reason,
 * $pay_url, $reply.
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#1F0A14;">Hello <?= $h($first_name) ?>,</p>

<?php if ($stats || $received): ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">
    Since <?= $h($since) ?>, <?= $h($business) ?> has <?= $stats ? 'made ' . $h(implode(', ', $stats)) : 'kept its records' ?> with Nfelia<?= $received ? ', and recorded ' . $h($received) . ' coming in' : '' ?>.
    Thank you for building your business with us.
</p>
<?php endif; ?>

<?php if ($down): ?>
<p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:#5B4650;">
    Good news: from <strong style="color:#1F0A14;"><?= $h($from) ?></strong>, Nfelia costs you less.
</p>
<?php else: ?>
<p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:#5B4650;">
    We are writing early, so nothing comes as a surprise: from <strong style="color:#1F0A14;"><?= $h($from) ?></strong>, some of our prices change.
</p>
<?php endif; ?>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;border:1px solid #E8E4DA;">
    <tr>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:12px;color:#5B4650;">What</td>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:12px;color:#5B4650;" align="right">Now</td>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:12px;color:#5B4650;" align="right">From <?= $h($from) ?></td>
    </tr>
    <?php foreach ($lines as $l): ?>
    <tr>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:14px;color:#1F0A14;"><?= $h($l['what']) ?></td>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:14px;color:#5B4650;" align="right"><?= $h($l['old']) ?></td>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:14px;color:#1F0A14;font-weight:600;" align="right"><?= $h($l['new']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($reason !== ''): ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;"><strong style="color:#1F0A14;">Why:</strong> <?= nl2br($h($reason)) ?></p>
<?php endif; ?>

<?php if ($up): ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?php if ($running && $hold): ?>
        Nothing changes before then. Your next month starts on <?= $h($next) ?>, before that date, so if you pay for it now, it stays at today's price.
    <?php elseif ($running): ?>
        Nothing changes before then: the month you have now is at today's price, and the new price starts with your month from <?= $h($next) ?>.
    <?php else: ?>
        Nothing changes before then. Pay for a month before <?= $h($from) ?> and it is at today's price.
    <?php endif; ?>
</p>
<?php if ($hold): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:#8B004A;">
            <a href="<?= $h($pay_url) ?>" style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;">
                <?= $running ? 'Pay for my next month' : 'Pay for a month' ?>
            </a>
        </td>
    </tr>
</table>
<?php endif; ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">
    Your records are yours, whatever you decide, and your plain copy is free to download from Your records. If this is hard for your business right now, reply to this email and tell us. We read every reply.
</p>
<?php else: ?>
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#5B4650;">There is nothing for you to do. Questions? Reply to this email.</p>
<?php endif; ?>

<p style="margin:0;font-size:16px;line-height:1.5;color:#1F0A14;">The Nfelia team</p>
<p style="margin:16px 0 0;font-size:12px;line-height:1.4;color:#5B4650;">This is about your account, so it comes even if you turned off our news emails. Write to <?= $h($reply) ?> any time.</p>
