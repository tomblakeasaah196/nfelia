<?php
/* views/emails/task_reminder.php — Variables: $item (a planner item), $when ("Today at 2:00pm"), $open_url */
$h       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$is_late = $item['date'] && $item['date'] < planner_today();
$on_brand = planner_text_on($biz_color);
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    <?= $h($item['title']) ?>
</h2>
<p style="margin:0 0 24px;font-size:16px;line-height:1.5;color:<?= $is_late ? '#B42318' : '#5B4650' ?>;">
    <?= $h($when) ?><?php if ($item['subtitle']): ?> · <?= $h($item['subtitle']) ?><?php endif; ?>
</p>

<?php if ($item['notes'] !== ''): ?>
<p style="margin:0 0 24px;padding:12px 16px;background-color:#F8EEF3;font-size:14px;line-height:1.5;color:#1F0A14;">
    <?= nl2br($h($item['notes'])) ?>
</p>
<?php endif; ?>

<?php if ($item['wa']): ?>
<!-- The appointment is with a customer: the useful next step is a nudge to them. -->
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
    <tr>
        <td style="background-color:#0E7A3F;">
            <a href="<?= $h($item['wa']) ?>"
               style="display:inline-block;padding:14px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;">
                <?= $h($item['wa_label']) ?> on WhatsApp
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 24px;font-size:14px;line-height:1.5;">
    <a href="<?= $h($open_url) ?>" style="color:#1F0A14;font-weight:600;">Open my tasks</a>
</p>
<?php else: ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($open_url) ?>"
               style="display:inline-block;padding:14px 24px;font-size:16px;line-height:1.25;font-weight:600;color:<?= $on_brand ?>;text-decoration:none;">
                Open my tasks
            </a>
        </td>
    </tr>
</table>
<?php endif; ?>

<p style="margin:0;font-size:12px;line-height:1.4;color:#5B4650;">
    A reminder from your Tasks &amp; Calendar.
    <a href="<?= $h($open_url) ?>?reminders=1" style="color:#5B4650;">Turn off reminder emails</a>
</p>
