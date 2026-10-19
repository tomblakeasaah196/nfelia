<?php
/**
 * Make a Records Book, step 2, for one client: what goes in, which records,
 * why, and who gets it. $b (records_business()), $sheets, $counts (rows in
 * each, all time), $sub, $can_download (an Admin: may keep it to themselves).
 */
$groups = [];
foreach ($sheets as $key => $s) {
    if (!isset($s['line_of'])) $groups[$s['group']][$key] = $s;
}
?>
<p class="ops-back"><a href="<?= e(ops_url('records/new')) ?>">← Find another client</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= ops_plan_chip($sub ? ['status' => $sub['status']] + $sub : null) ?><span class="ops-chip ops-chip-muted">#<?= (int)$b['id'] ?></span></div>
        <h1 class="ops-break">Records Book for <?= e($b['name']) ?></h1>
        <p><?= e($b['owner_name'] ?? '') ?><?= $b['email'] ? ' · ' . e($b['email']) : '' ?>. With Nfelia since <?= e(date('j M Y', strtotime((string)$b['created_at']))) ?>.</p>
    </div>
    <div class="ops-head-actions"><a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>">Open the client</a></div>
</div>

<form method="post" action="<?= e(ops_url('records')) ?>" class="ops-card" data-ops-confirm="Make <?= e($b['name']) ?>'s Records Book? The owner is told it was you, and why.">
<div class="ops-card-body">
    <?= ops_csrf_field() ?>
    <input type="hidden" name="client" value="<?= (int)$b['id'] ?>">

    <fieldset class="ops-fieldset">
        <legend class="ops-label">What goes in</legend>
        <p class="hint" style="margin-bottom:10px">Lines come with their documents (an invoice's lines with Invoices). Kinds with nothing in them are left out of the book by themselves.</p>
        <div class="ops-checks-grid">
            <?php foreach (RECORDS_GROUPS as $g => $glabel): if (empty($groups[$g])) continue; ?>
            <div><b class="small"><?= e($glabel) ?></b>
                <?php foreach ($groups[$g] as $key => $s): ?>
                    <label class="ops-check"><input type="checkbox" name="sheets[]" value="<?= e($key) ?>"<?= $counts[$key] ? ' checked' : '' ?>>
                        <span><?= e($s['title']) ?> <small><?= number_format($counts[$key]) ?> in all</small></span></label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="ops-fieldset">
        <legend class="ops-label">Which records</legend>
        <div class="ops-row">
            <div class="ops-field"><label for="r-period">Period</label>
                <select class="ops-select" id="r-period" name="period">
                    <option value="all">All time</option><option value="this_year">This year</option><option value="12_months">The last 12 months</option>
                    <option value="last_year">Last year</option><option value="custom">From and to these dates</option>
                </select></div>
            <div class="ops-field"><label for="r-from">From <span class="muted">(for dates you choose)</span></label><input class="ops-input" id="r-from" name="from" type="date"></div>
            <div class="ops-field"><label for="r-to">To</label><input class="ops-input" id="r-to" name="to" type="date"></div>
        </div>
    </fieldset>

    <div class="ops-field">
        <label for="r-why">Why you are making it</label>
        <input class="ops-input" id="r-why" name="reason" required minlength="5" maxlength="255" placeholder="e.g. they asked on WhatsApp for their records to apply for a loan">
        <span class="hint"><?= e($b['owner_name'] ?? 'The owner') ?> sees this, with your name, and it stays in the audit log.</span>
    </div>

    <?php if ($can_download): ?>
        <label class="ops-check" style="margin:4px 0 0"><input type="checkbox" name="send_to_owner" value="1" checked>
            <span>Send it to the owner too <small>It appears in their Your records, and they are emailed. Untick to keep it for the team (they are still told it was made).</small></span></label>
    <?php else: ?>
        <p class="hint">It goes to the owner: it appears in their Your records and they are emailed. Only an Admin can download it here.</p>
    <?php endif; ?>

    <div class="ops-actions" style="margin-top:16px">
        <button class="ops-btn ops-btn-primary" type="submit">Make the Records Book</button>
        <a class="ops-btn ops-btn-ghost" href="<?= e(ops_url('records')) ?>">Cancel</a>
    </div>
</div>
</form>
