<?php
/** One ticket (detail): $t, $others. */
?>
<p class="ops-back"><a href="<?= e(ops_url('support')) ?>">← Support</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><span class="ops-chip <?= $t['status'] === 'open' ? 'ops-chip-accent' : ($t['status'] === 'replied' ? 'ops-chip-ok' : 'ops-chip-muted') ?>"><?= e(ucfirst($t['status'])) ?></span> <span class="ops-chip ops-chip-muted">#<?= (int)$t['id'] ?></span></div>
        <h1 class="ops-break"><?= e($t['subject']) ?></h1>
        <p>From <?= e($t['user_name'] ?? 'someone') ?> at <a href="<?= e(ops_url('clients/' . (int)$t['business_id'])) ?>"><?= e($t['business_name'] ?? 'a client') ?></a>, <?= e(ops_ago($t['created_at'])) ?>.</p>
    </div>
</div>
<div class="ops-detail">
    <div class="ops-detail-main">
        <section class="ops-card"><div class="ops-card-body">
            <h2>Their message</h2>
            <p class="ops-break"><?= nl2br(e($t['message'])) ?></p>
            <?php if ($t['admin_reply']): ?>
                <h3 style="margin-top:18px">Our last reply<?= $t['replied_at'] ? ', ' . e(ops_ago($t['replied_at'])) : '' ?></h3>
                <p class="ops-break"><?= nl2br(e($t['admin_reply'])) ?></p>
            <?php endif; ?>
        </div></section>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Reply</h2>
            <form method="post" action="<?= e(ops_url('support/' . (int)$t['id'] . '/reply')) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="reply">Your reply</label><textarea class="ops-textarea" id="reply" name="reply" rows="6"></textarea>
                    <span class="hint">They see it in Nfelia (the bell) and by email.</span></div>
                <div class="ops-field"><label for="status">Then the ticket is</label>
                    <select class="ops-select" id="status" name="status">
                        <option value="replied">Replied (waiting on them)</option>
                        <option value="closed">Closed (done)</option>
                        <option value="open">Still open</option>
                    </select></div>
                <button class="ops-btn ops-btn-primary" type="submit">Send</button>
            </form>
        </div></section>
    </div>
    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <h2>Who</h2>
            <dl class="ops-facts">
                <dt>Name</dt><dd><?= e($t['user_name'] ?? '—') ?></dd>
                <dt>Email</dt><dd class="ops-break"><?= e($t['user_email'] ?? '—') ?></dd>
                <?php if ($t['user_phone']): ?><dt>Phone</dt><dd><?= e($t['user_phone']) ?></dd><?php endif; ?>
                <dt>Plan</dt><dd><?= ops_plan_chip($t['plan_status']) ?></dd>
            </dl>
        </div></section>
        <?php if ($others): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Their other tickets</h2>
            <ul class="ops-plain"><?php foreach ($others as $o): ?><li><a href="<?= e(ops_url('support/' . (int)$o['id'])) ?>"><?= e($o['subject']) ?></a> <span class="muted"><?= e($o['status']) ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>
    </aside>
</div>
