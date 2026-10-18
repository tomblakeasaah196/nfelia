<?php
/*
 * views/emails/ops_digest.php — the Ops team's 8am summary (app/ops/monitor.php).
 * Variables: $name, $day, $new, $back, $top (each: id, kind, level, title, events/day_events),
 * $open (count), $problems (health checks not ok).
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$link = fn(int $id) => ops_url('errors/' . $id);
$list = function (array $rows, string $count_key) use ($h, $link) {
    $out = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;border:1px solid #E8E4DA;">';
    foreach ($rows as $r) {
        $out .= '<tr><td style="padding:10px 14px;border-bottom:1px solid #E8E4DA;font-size:14px;line-height:1.4;">'
              . '<a href="' . $h($link((int)$r['id'])) . '" style="color:#8B004A;text-decoration:none;font-weight:600;">' . $h(mb_strimwidth($r['title'], 0, 110, '…')) . '</a>'
              . '<div style="color:#7D6C73;font-size:12px;">' . $h($r['level']) . ' · ' . $h($r['kind']) . ' · ' . (int)$r[$count_key] . '×</div></td></tr>';
    }
    return $out . '</table>';
};
$first = preg_split('/\s+/u', trim((string)$name))[0] ?? '';
?>
<h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:600;color:#1F0A14;">Good morning<?= $first !== '' ? ', ' . $h($first) : '' ?></h2>
<p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:#5B4650;">
    <?= $h($day) ?> in Nfelia Ops: <?= count($new) ?> new problem<?= count($new) === 1 ? '' : 's' ?>, <?= count($back) ?> came back, <?= (int)$open ?> open in all.
</p>

<?php if ($problems): ?>
<h3 style="margin:0 0 8px;font-size:16px;color:#B42318;">Not working right</h3>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;border:1px solid #F2C3BD;background:#FDECEA;">
    <?php foreach ($problems as $c): ?>
    <tr><td style="padding:10px 14px;border-bottom:1px solid #F2C3BD;font-size:14px;line-height:1.4;color:#1F0A14;">
        <b><?= $h($c['label']) ?></b><div style="color:#5B4650;font-size:13px;"><?= $h($c['detail']) ?></div></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($back): ?><h3 style="margin:0 0 8px;font-size:16px;color:#1F0A14;">Came back after being fixed</h3><?= $list($back, 'events') ?><?php endif; ?>
<?php if ($new): ?><h3 style="margin:0 0 8px;font-size:16px;color:#1F0A14;">New yesterday</h3><?= $list($new, 'events') ?><?php endif; ?>
<?php if ($top): ?><h3 style="margin:0 0 8px;font-size:16px;color:#1F0A14;">Most frequent yesterday, still open</h3><?= $list($top, 'day_events') ?><?php endif; ?>
<?php if (!$problems && !$back && !$new && !$top): ?>
<p style="margin:0 0 20px;font-size:15px;color:#00764A;">A quiet day: nothing new, nothing came back, everything working.</p>
<?php endif; ?>

<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
    <tr><td style="background-color:#1B0A11;">
        <a href="<?= $h(ops_url('errors')) ?>" style="display:inline-block;padding:12px 24px;font-size:16px;line-height:1.25;font-weight:600;color:#FFFFFF;text-decoration:none;">Open Nfelia Ops</a>
    </td></tr>
</table>
<p style="margin:0;font-size:13px;line-height:1.5;color:#8A7A80;">You get this because the morning summary is on in your Ops account. Turn it off under Your account → Alerts.</p>
