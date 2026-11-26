<?php
/** Meetings booked at nfelia.com/book: $rows, $tab, $counts. */
$tabs = ['todo' => 'Needs a Meet link', 'upcoming' => 'Coming up', 'past' => 'Past'];
?>
<div class="ops-head">
    <div>
        <h1>Meetings</h1>
        <p>Calls people book at <a href="<?= e(APP_URL . '/book') ?>" target="_blank" rel="noopener">nfelia.com/book</a>. Each one holds its time at once; paste its Google Meet link to confirm it and they get the link by email.</p>
    </div>
    <div class="ops-head-actions">
        <a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('meetings/types')) ?>">Meeting types</a>
        <a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('meetings/settings')) ?>">Hours</a>
    </div>
</div>
<nav class="ops-tabs" aria-label="Show">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(ops_url('meetings' . ($k === 'todo' ? '' : '?show=' . $k))) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if (!empty($counts[$k])): ?> <span class="num">(<?= (int)$counts[$k] ?>)</span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Who</th><th scope="col">When</th><th scope="col">Meeting</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
            <tr class="is-link">
                <td><a class="cell-main" href="<?= e(ops_url('meetings/' . (int)$m['id'])) ?>"><?= e($m['business_name'] ?: $m['name']) ?></a>
                    <span class="cell-sub"><?= e(trim(($m['business_name'] ? $m['name'] . ' · ' : '') . $m['email'])) ?></span></td>
                <td data-label="When"><?= e(meeting_short_when($m)) ?></td>
                <td data-label="Meeting"><?= e($m['type_name']) ?> <span class="cell-sub"><?= (int)$m['minutes'] ?> min</span></td>
                <td data-label="Status"><?= meeting_status_chip($m['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="4"><div class="ops-empty">
                <h3><?= $tab === 'todo' ? 'Nothing waiting for a link' : ($tab === 'upcoming' ? 'No meetings coming up' : 'No past meetings yet') ?></h3>
                <p><?= $tab === 'past' ? 'Meetings move here once their time has passed.' : 'Share nfelia.com/book on WhatsApp, Instagram and in emails so people can book a call.' ?></p>
            </div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
