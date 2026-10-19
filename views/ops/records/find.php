<?php
/** Make a Records Book, step 1: find the client. $q, $found. */
?>
<p class="ops-back"><a href="<?= e(ops_url('records')) ?>">← Records Books</a></p>
<div class="ops-head"><div><h1>Make a Records Book</h1><p>Find the client first. Then choose what goes in, and say why: the owner is told who made it and why.</p></div></div>
<form class="ops-toolbar" method="get" action="<?= e(ops_url('records/new')) ?>">
    <div class="ops-search">
        <?= ops_icon('search') ?>
        <label class="sr-only" for="r-q">Find a client</label>
        <input class="ops-input" id="r-q" name="q" type="search" value="<?= e($q) ?>" placeholder="Business, owner, email or shop name" autofocus data-ops-slash>
    </div>
    <button class="ops-btn ops-btn-primary" type="submit">Find</button>
</form>
<?php if ($q !== ''): ?>
<div class="ops-card"><div class="ops-card-body">
    <?php if ($found): ?>
        <ul class="ops-plain">
        <?php foreach ($found as $b): ?>
            <li><a class="ops-break" href="<?= e(ops_url('records/new?client=' . (int)$b['id'])) ?>"><b><?= e($b['name']) ?></b></a>
                <span class="muted"><?= e(trim(($b['owner'] ? $b['owner'] . ' · ' : '') . ($b['email'] ?? '') . ($b['subdomain'] ? ' · ' . $b['subdomain'] : ''))) ?></span></li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="ops-empty"><h3>No client matches “<?= e($q) ?>”</h3><p>Try part of the name, the owner's email, or the shop name.</p></div>
    <?php endif; ?>
</div></div>
<?php endif; ?>
