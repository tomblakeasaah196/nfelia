<?php
/** One lead (detail): $l, $notes, $history, $client, $campaigns, $sales. */
$wa = $l['phone'] ? preg_replace('/\D/', '', $l['phone']) : '';
if (str_starts_with($wa, '0') && strlen($wa) === 11) $wa = '234' . substr($wa, 1);   // a Nigerian number written locally
$fu = $l['next_follow_up_at'] ? date('Y-m-d\TH:i', strtotime($l['next_follow_up_at'])) : '';
?>
<p class="ops-back"><a href="<?= e(ops_url('leads')) ?>">← Leads</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= ops_lead_stage_chip($l['stage']) ?> <span class="ops-chip"><?= e(LEAD_SOURCES[$l['source']] ?? $l['source']) ?></span></div>
        <h1 class="ops-break"><?= e($l['business_name'] ?: $l['name']) ?></h1>
        <p><?= e($l['business_name'] ? $l['name'] : '') ?><?= $l['phone'] ? ($l['business_name'] ? ' · ' : '') . e($l['phone']) : '' ?><?= $l['email'] ? ' · ' . e($l['email']) : '' ?>.
           Added <?= e(ops_ago($l['created_at'])) ?><?= $l['last_contact_at'] ? ', last contact ' . e(ops_ago($l['last_contact_at'])) : ', not contacted yet' ?>.</p>
    </div>
    <div class="ops-head-actions">
        <?php if ($l['phone']): ?><a class="ops-btn ops-btn-secondary" href="tel:<?= e(preg_replace('/[^\d+]/', '', $l['phone'])) ?>">Call</a>
            <a class="ops-btn ops-btn-secondary" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
        <?php if ($l['email']): ?><a class="ops-btn ops-btn-secondary" href="mailto:<?= e($l['email']) ?>">Email</a><?php endif; ?>
    </div>
</div>

<div class="ops-detail is-woven">
    <div class="ops-detail-main">
        <section class="ops-card is-early" id="stage"><div class="ops-card-body">
            <h2>Where it is</h2>
            <ol class="ops-stepper">
                <?php foreach (LEAD_STAGES as $k => $s): if ($k === 'lost') continue; ?>
                    <li class="<?= $l['stage'] === $k ? 'is-current' : '' ?>">
                        <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/stage')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="stage" value="<?= e($k) ?>">
                            <button type="submit"<?= $l['stage'] === $k ? ' aria-current="step" disabled' : '' ?> title="<?= e($s['hint']) ?>"><?= e($s['label']) ?></button></form>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($l['stage'] === 'lost'): ?>
                <p class="ops-flash ops-flash-info" style="margin:12px 0 0">Lost: <?= e($l['lost_reason'] ?? '') ?></p>
            <?php else: ?>
                <details class="ops-more"><summary>Mark it lost</summary>
                    <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/stage')) ?>" style="margin-top:10px">
                        <?= ops_csrf_field() ?><input type="hidden" name="stage" value="lost">
                        <div class="ops-field"><label for="lost-why">Why</label><input class="ops-input" id="lost-why" name="reason" required minlength="3" placeholder="e.g. too dear for now, uses a notebook, no smartphone"></div>
                        <button class="ops-btn ops-btn-danger" type="submit">Mark it lost</button>
                    </form>
                </details>
            <?php endif; ?>
        </div></section>

        <section class="ops-card is-early" id="notes"><div class="ops-card-body">
            <h2>Notes</h2>
            <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/notes')) ?>" style="margin-bottom:16px">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="lead-note">What happened</label><textarea class="ops-textarea" id="lead-note" name="body" required placeholder="Called: sells shoes in Balogun, wants to see invoices on WhatsApp"></textarea></div>
                <div class="ops-actions">
                    <label class="ops-check"><input type="checkbox" name="contacted" value="1" checked><span>I spoke to them</span></label>
                    <button class="ops-btn ops-btn-secondary" type="submit">Save the note</button>
                </div>
            </form>
            <?php if ($notes): ?>
                <ul class="ops-notes"><?php foreach ($notes as $n): ?><li><b><?= e($n['full_name'] ?? 'Nfelia') ?></b> <span class="muted small"><?= e(ops_ago($n['created_at'])) ?></span><p class="ops-break"><?= nl2br(e($n['body'])) ?></p></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div></section>
    </div>

    <aside class="ops-detail-side">
        <section class="ops-card is-first" id="follow-up"><div class="ops-card-body">
            <h2>Follow up</h2>
            <?php if ($l['next_follow_up_at']): ?>
                <p class="<?= strtotime($l['next_follow_up_at']) <= time() + ops_tz_shift() ? 'ops-due' : '' ?>"><b><?= e(date('D j M, g:ia', strtotime($l['next_follow_up_at']))) ?></b><?= $l['follow_up_note'] ? ': ' . e($l['follow_up_note']) : '' ?></p>
                <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/follow-up')) ?>" style="margin:10px 0 16px"><?= ops_csrf_field() ?><input type="hidden" name="done" value="1">
                    <button class="ops-btn ops-btn-primary ops-btn-block" type="submit">Done</button></form>
            <?php else: ?>
                <p class="muted" style="margin-bottom:12px">None set. <?= in_array($l['stage'], LEAD_OPEN, true) ? 'When should someone get back to them?' : '' ?></p>
            <?php endif; ?>
            <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/follow-up')) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="fu-when"><?= $l['next_follow_up_at'] ? 'Move it to' : 'When' ?></label><input class="ops-input" id="fu-when" name="follow_up" type="datetime-local" value="<?= e($fu) ?>" required></div>
                <div class="ops-field"><label for="fu-about">About <span class="muted">(optional)</span></label><input class="ops-input" id="fu-about" name="follow_up_note" value="<?= e($l['follow_up_note'] ?? '') ?>"></div>
                <button class="ops-btn ops-btn-secondary ops-btn-block" type="submit">Remind <?= $l['owner_name'] ? e(preg_split('/\s+/', $l['owner_name'])[0]) : 'me' ?></button>
            </form>
        </div></section>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Whose</h2>
            <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'] . '/owner')) ?>">
                <?= ops_csrf_field() ?>
                <label class="sr-only" for="lead-owner">Who follows it up</label>
                <select class="ops-select" id="lead-owner" name="owner_id" data-ops-autosubmit>
                    <option value="0">Nobody yet (all of Sales is reminded)</option>
                    <?php foreach ($sales as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)$l['owner_id'] === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['full_name']) ?></option><?php endforeach; ?>
                </select>
            </form>
        </div></section>

        <?php if ($client): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Their Nfelia</h2>
            <p><a href="<?= e(ops_url('clients/' . (int)$client['id'])) ?>"><?= e($client['name']) ?></a> <?= ops_plan_chip($client) ?></p>
            <?php if ($client['plan_status'] === 'trial' && $client['trial_ends_at']): ?><p class="small muted">Trial ends <?= e(date('j M', strtotime($client['trial_ends_at']))) ?>.</p><?php endif; ?>
        </div></section>
        <?php endif; ?>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Details</h2>
            <form method="post" action="<?= e(ops_url('leads/' . (int)$l['id'])) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="d-name">Name</label><input class="ops-input" id="d-name" name="name" value="<?= e($l['name']) ?>" required></div>
                <div class="ops-field"><label for="d-biz">Business</label><input class="ops-input" id="d-biz" name="business_name" value="<?= e($l['business_name'] ?? '') ?>"></div>
                <div class="ops-field"><label for="d-phone">Phone</label><input class="ops-input" id="d-phone" name="phone" type="tel" value="<?= e($l['phone'] ?? '') ?>"></div>
                <div class="ops-field"><label for="d-email">Email</label><input class="ops-input" id="d-email" name="email" type="email" value="<?= e($l['email'] ?? '') ?>"></div>
                <div class="ops-field"><label for="d-source">Came from</label>
                    <select class="ops-select" id="d-source" name="source"><?php foreach (LEAD_SOURCES as $k => $label): ?><option value="<?= e($k) ?>"<?= $l['source'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <button class="ops-btn ops-btn-secondary" type="submit">Save</button>
            </form>
        </div></section>

        <?php if ($campaigns): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Campaigns</h2>
            <ul class="ops-plain"><?php foreach ($campaigns as $c): ?><li><span><?= e($c['name']) ?></span> <span class="muted"><?= e(['sent' => 'delivered', 'failed' => 'failed', 'skipped' => 'unsubscribed'][$c['status'] ?? ''] ?? 'on its way') ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>

        <?php if ($history): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>History</h2>
            <ul class="ops-plain"><?php foreach ($history as $h): ?><li><span><?= e(($h['full_name'] ?? 'Nfelia') . ': ' . $h['summary']) ?></span> <span class="muted"><?= e(ops_ago($h['created_at'])) ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>
    </aside>
</div>
