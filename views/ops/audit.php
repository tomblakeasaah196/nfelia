<?php
/** The audit log (activity feed): $rows, $more, $team, $kinds, $filter. */
$query = fn(array $over) => ops_url('audit?' . http_build_query(array_filter($over + $filter)));
?>
<div class="ops-head">
    <div>
        <h1>Audit log</h1>
        <p>Everything done in Ops: who, what, to which client, and when. Nothing here can be changed or deleted.</p>
    </div>
</div>

<form class="ops-toolbar" method="get" action="<?= e(ops_url('audit')) ?>">
    <label class="sr-only" for="f-staff">Person</label>
    <select class="ops-select" id="f-staff" name="staff" style="max-width:220px" data-ops-autosubmit>
        <option value="">Everyone</option>
        <?php foreach ($team as $t): ?>
            <option value="<?= (int)$t['id'] ?>"<?= $filter['staff'] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['full_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="f-kind">Kind</label>
    <select class="ops-select" id="f-kind" name="kind" style="max-width:200px" data-ops-autosubmit>
        <option value="">Every kind</option>
        <?php foreach ($kinds as $k): ?>
            <option value="<?= e($k) ?>"<?= $filter['kind'] === $k ? ' selected' : '' ?>><?= e(ucfirst($k)) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($filter['business']): ?>
        <input type="hidden" name="business" value="<?= (int)$filter['business'] ?>">
        <span class="ops-chip ops-chip-accent">Client #<?= (int)$filter['business'] ?></span>
    <?php endif; ?>
    <noscript><button class="ops-btn ops-btn-secondary" type="submit">Show</button></noscript>
    <?php if (array_filter($filter)): ?><a class="ops-btn ops-btn-ghost" href="<?= e(ops_url('audit')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>Nothing yet</h3><p>Nothing matches these filters.</p></div>
<?php else: ?>
    <?php require __DIR__ . '/_feed.php'; ?>
    <?php if ($more): ?>
        <p style="margin-top:16px;text-align:center">
            <a class="ops-btn ops-btn-secondary" href="<?= e($query(['before' => (int)end($rows)['id']])) ?>">Load older</a>
        </p>
    <?php endif; ?>
<?php endif; ?>
