<?php
/*
 * views/emails/notification.php — the email side of any notification
 * (app/helpers/notify.php). Variables: $headline, $message, $lines
 * (label => value), $button, $open_url (notifications/open/{id}: marks it
 * read and opens the exact page, through sign-in when needed), $prefs_url,
 * $topic_label, $always (true for billing and security, which can't be
 * switched off).
 */
$h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$on_brand = planner_text_on($biz_color);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    <?= $h($headline) ?>
</h2>
<p style="margin:0 0 24px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?= nl2br($h($message)) ?>
</p>

<?php if (!empty($lines)): ?>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border:1px solid #E8E4DA;">
    <?php foreach ($lines as $label => $value): ?>
    <tr>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:13px;line-height:1.4;color:#5B4650;width:35%;vertical-align:top;">
            <?= $h($label) ?>
        </td>
        <td style="padding:10px 16px;border-bottom:1px solid #E8E4DA;font-size:14px;line-height:1.4;color:#1F0A14;font-weight:600;vertical-align:top;">
            <?= nl2br($h($value)) ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($open_url) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:<?= $on_brand ?>;text-decoration:none;">
                <?= $h($button) ?>
            </a>
        </td>
    </tr>
</table>

<p style="margin:0;font-size:12px;line-height:1.4;color:#5B4650;">
    <?php if (!empty($always)): ?>
        Nfelia always emails you about this.
    <?php elseif (!empty($topic_label)): ?>
        You get this because “<?= $h($topic_label) ?>” emails are on.
        <a href="<?= $h($prefs_url) ?>" style="color:#5B4650;">Change what you get</a>
    <?php endif; ?>
</p>
