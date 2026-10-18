<?php
/** Your alerts (inbox): $rows, $more, $unread. */
?>
<div class="ops-head">
    <div>
        <h1>Alerts</h1>
        <p>What Ops told you: new and returning errors, health problems, errors given to you. Turn on phone alerts under <a href="<?= e(ops_url('account#alerts')) ?>">Your account</a>.</p>
    </div>
    <?php if ($unread): ?>
    <div class="ops-head-actions">
        <form method="post" action="<?= e(ops_url('alerts/read-all')) ?>"><?= ops_csrf_field() ?><button class="ops-btn ops-btn-secondary" type="submit">Mark all read</button></form>
    </div>
    <?php endif; ?>
</div>
<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>No alerts yet</h3><p>When something breaks or comes back, it shows here and on your phone.</p></div>
<?php else: ?>
    <ul class="ops-inbox">
        <?php foreach ($rows as $a): ?>
            <li class="<?= $a['read_at'] ? '' : 'is-new' ?>">
                <a href="<?= e(ops_url('alerts/open/' . (int)$a['id'])) ?>">
                    <span class="what"><b><?= e($a['title']) ?></b><?php if ($a['body']): ?><span class="ops-break"><?= e($a['body']) ?></span><?php endif; ?></span>
                    <time class="when" title="<?= e($a['created_at']) ?>"><?= e(ops_ago($a['created_at'])) ?></time>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($more): ?><p style="margin-top:16px;text-align:center"><a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('alerts?before=' . (int)end($rows)['id'])) ?>">Load older</a></p><?php endif; ?>
<?php endif; ?>
