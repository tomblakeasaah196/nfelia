<?php
/* views/emails/morning_plan.php — Variables: $first_name, $day (late / today / soon planner items), $summary, $open_url, $prefs_url */
$h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$on_brand = planner_text_on($biz_color);
$chip     = [
    'late'  => 'background-color:#FEE4E2;color:#B42318;',
    'today' => 'background-color:#FEE4E2;color:#B42318;',
    'soon'  => 'background-color:#FEF0C7;color:#93370D;',
    'later' => 'background-color:#F2EFE7;color:#5B4650;',
];
$sections = [
    'late'  => ['Late', '#B42318'],
    'today' => ['Today', '#1F0A14'],
    'soon'  => ['Deliveries in the next 2 days', '#1F0A14'],
];
$max = 8;   // a longer list belongs in the app, not an email
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">
    Good morning<?= $first_name !== '' ? ', ' . $h($first_name) : '' ?>.
</h2>
<p style="margin:0 0 8px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?= $h($summary) ?>.
</p>

<?php foreach ($sections as $key => [$label, $color]): if (!$day[$key]) continue; ?>
<h3 style="margin:24px 0 4px;font-size:16px;line-height:1.25;font-weight:600;color:<?= $color ?>;">
    <?= $h($label) ?>
</h3>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <?php foreach (array_slice($day[$key], 0, $max) as $it): ?>
    <tr>
        <td style="padding:12px 0;border-top:1px solid #E8E4DA;font-size:14px;line-height:1.5;color:#5B4650;">
            <?php if ($it['countdown']): ?>
                <span style="display:inline-block;margin:0 8px 4px 0;padding:4px 8px;font-size:12px;line-height:1.4;font-weight:600;<?= $chip[$it['countdown']['tone']] ?>"><?= $h($it['countdown']['label']) ?></span>
            <?php elseif ($it['time']): ?>
                <span style="display:inline-block;margin:0 8px 4px 0;font-size:14px;font-weight:600;color:#5B4650;"><?= $h(planner_fmt_time($it['time'])) ?></span>
            <?php endif; ?>
            <a href="<?= $h(APP_URL . '/' . ($it['url'] ?: 'tasks')) ?>"
               style="font-size:16px;line-height:1.4;font-weight:600;color:#1F0A14;text-decoration:none;"><?= $h($it['title']) ?></a>
            <?php if ($it['subtitle']): ?><br><?= $h($it['subtitle']) ?><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php if (count($day[$key]) > $max): ?>
<p style="margin:0;padding-top:8px;border-top:1px solid #E8E4DA;font-size:14px;line-height:1.5;color:#5B4650;">
    And <?= count($day[$key]) - $max ?> more in Nfelia.
</p>
<?php endif; ?>
<?php endforeach; ?>

<table cellpadding="0" cellspacing="0" border="0" style="margin:32px 0 24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($open_url) ?>"
               style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:<?= $on_brand ?>;text-decoration:none;">
                Open my day
            </a>
        </td>
    </tr>
</table>

<p style="margin:0;font-size:12px;line-height:1.4;color:#5B4650;">
    Nfelia sends this on mornings when you have something planned.
    <a href="<?= $h($prefs_url ?? $open_url . '?reminders=1') ?>" style="color:#5B4650;">Turn off morning emails</a>
</p>
