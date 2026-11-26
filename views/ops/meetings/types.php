<?php
/** The kinds of meeting people can book: $types (not removed, in order), $coming [type id => meetings coming up]. */
?>
<p class="ops-back"><a href="<?= e(ops_url('meetings')) ?>">← Meetings</a></p>
<div class="ops-head">
    <div>
        <h1>Meeting types</h1>
        <p>What people can book at <a href="<?= e(APP_URL . '/book') ?>" target="_blank" rel="noopener">nfelia.com/book</a>, in this order. Hide one to take it off the page for a while; remove it to take it off for good. Meetings already booked go ahead either way.</p>
    </div>
    <div class="ops-head-actions">
        <button type="button" class="ops-btn ops-btn-primary" data-ops-dialog="add-type"><?= ops_icon('plus') ?> Add a meeting type</button>
    </div>
</div>

<?php if (!$types): ?>
    <div class="ops-card ops-empty"><h3>No meeting types</h3><p>Add one so people can book a call.</p></div>
<?php endif; ?>
<?php foreach ($types as $t): $n = (int)($coming[$t['id']] ?? 0); ?>
    <section class="ops-card" id="type-<?= (int)$t['id'] ?>"><div class="ops-card-body">
        <div class="ops-chips-row" style="margin-bottom:8px">
            <?= (int)$t['is_active'] ? '<span class="ops-chip ops-chip-ok">On the booking page</span>' : '<span class="ops-chip ops-chip-muted">Hidden</span>' ?>
            <?php if ($n): ?><span class="ops-chip"><?= $n ?> coming up</span><?php endif; ?>
            <a class="small" href="<?= e(APP_URL . '/book/' . $t['slug']) ?>" target="_blank" rel="noopener">nfelia.com/book/<?= e($t['slug']) ?></a>
        </div>
        <form method="post" action="<?= e(ops_url('meetings/types/' . (int)$t['id'])) ?>">
            <?= ops_csrf_field() ?>
            <div class="ops-row">
                <div class="ops-field"><label for="t-name-<?= (int)$t['id'] ?>">Name</label><input class="ops-input" id="t-name-<?= (int)$t['id'] ?>" name="name" required maxlength="120" value="<?= e($t['name']) ?>"></div>
                <div class="ops-field"><label for="t-min-<?= (int)$t['id'] ?>">Minutes</label><input class="ops-input" id="t-min-<?= (int)$t['id'] ?>" name="minutes" type="number" min="10" max="240" step="5" required value="<?= (int)$t['minutes'] ?>"></div>
            </div>
            <div class="ops-field"><label for="t-desc-<?= (int)$t['id'] ?>">What it's for <span class="muted">(shown on the booking page)</span></label>
                <input class="ops-input" id="t-desc-<?= (int)$t['id'] ?>" name="description" maxlength="255" value="<?= e($t['description'] ?? '') ?>"></div>
            <div class="ops-row">
                <div class="ops-field"><label for="t-sort-<?= (int)$t['id'] ?>">Order <span class="muted">(lower comes first)</span></label><input class="ops-input" id="t-sort-<?= (int)$t['id'] ?>" name="sort_order" type="number" value="<?= (int)$t['sort_order'] ?>"></div>
                <div class="ops-field" style="align-self:end"><label class="ops-check"><input type="checkbox" name="is_active" value="1"<?= (int)$t['is_active'] ? ' checked' : '' ?>><span>Show on the booking page</span></label></div>
            </div>
            <div class="ops-actions">
                <button class="ops-btn ops-btn-secondary" type="submit">Save</button>
            </div>
        </form>
        <details class="ops-more"><summary>Remove this meeting type</summary>
            <form method="post" action="<?= e(ops_url('meetings/types/' . (int)$t['id'] . '/remove')) ?>" style="margin-top:10px">
                <?= ops_csrf_field() ?>
                <p class="small muted" style="margin-bottom:10px">It comes off the booking page for good, and its link stops working.<?= $n ? ' The ' . $n . ' meeting' . ($n === 1 ? '' : 's') . ' already booked with it go ahead.' : '' ?></p>
                <button class="ops-btn ops-btn-danger" type="submit">Remove “<?= e($t['name']) ?>”</button>
            </form>
        </details>
    </div></section>
<?php endforeach; ?>

<dialog class="ops-dialog" id="add-type" aria-labelledby="add-type-title">
    <form method="post" action="<?= e(ops_url('meetings/types')) ?>">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head"><h2 id="add-type-title">Add a meeting type</h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
        <div class="ops-dialog-body">
            <div class="ops-row">
                <div class="ops-field"><label for="nt-name">Name</label><input class="ops-input" id="nt-name" name="name" required maxlength="120" placeholder="e.g. Training for your staff" autocomplete="off"></div>
                <div class="ops-field"><label for="nt-min">Minutes</label><input class="ops-input" id="nt-min" name="minutes" type="number" min="10" max="240" step="5" value="30" required></div>
            </div>
            <div class="ops-field"><label for="nt-desc">What it's for <span class="muted">(shown on the booking page)</span></label><input class="ops-input" id="nt-desc" name="description" maxlength="255"></div>
        </div>
        <div class="ops-dialog-foot"><button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button><button type="submit" class="ops-btn ops-btn-primary">Add the meeting type</button></div>
    </form>
</dialog>
