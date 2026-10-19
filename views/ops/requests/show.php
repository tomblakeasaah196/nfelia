<?php
/** One document request: $r (record_request()), $roles, $asked_by, $declined_by. */
$who = $r['organisation'] ? $r['organisation'] . ' (' . $r['name'] . ')' : $r['name'];
$day = fn(?string $d) => $d ? date('j M Y, g:ia', strtotime($d)) : '—';
?>
<p class="ops-back"><a href="<?= e(ops_url('requests')) ?>">← Document requests</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <h1 class="ops-break"><?= e($who) ?> wants <?= e($r['label']) ?></h1>
        <p>From <?= e($r['business_name']) ?>. Confirmed their email <?= e(ops_ago($r['confirmed_at'])) ?>.</p>
    </div>
    <?php if ($r['status'] === 'new'): ?>
    <div class="ops-head-actions">
        <form method="post" action="<?= e(ops_url('requests/' . (int)$r['id'] . '/ask')) ?>" data-ops-confirm="Ask <?= e($r['business_name']) ?>? Their owner gets it in the app, on their phone and by email, with Approve and Decline.">
            <?= ops_csrf_field() ?><button class="ops-btn ops-btn-primary" type="submit">Ask <?= e($r['business_name']) ?></button>
        </form>
        <button type="button" class="ops-btn ops-btn-secondary" data-ops-dialog="decline">Turn it down</button>
    </div>
    <?php endif; ?>
</div>

<div class="ops-detail">
    <div class="ops-detail-main">
        <section class="ops-card"><div class="ops-card-body">
            <h2>Why they want it</h2>
            <p class="ops-pre"><?= e($r['reason']) ?></p>
            <dl class="ops-facts" style="margin-top:14px">
                <dt>Name</dt><dd><?= e($r['name']) ?></dd>
                <?php if ($r['organisation']): ?><dt>Organisation</dt><dd><?= e($r['organisation']) ?></dd><?php endif; ?>
                <dt>They are</dt><dd><?= e($roles[$r['role']] ?? '—') ?></dd>
                <dt>Email</dt><dd class="ops-break"><?= e($r['email']) ?></dd>
                <?php if ($r['phone']): ?><dt>Phone</dt><dd><?= e($r['phone']) ?></dd><?php endif; ?>
                <dt>The code</dt><dd><a href="<?= e(records_verify_url($r['code'])) ?>" target="_blank" rel="noopener"><?= e($r['code']) ?></a></dd>
            </dl>
        </div></section>
    </div>
    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <h2>What happened</h2>
            <ul class="ops-plain">
                <li><span>Asked at nfelia.com/verify</span><span class="muted"><?= e($day($r['created_at'])) ?></span></li>
                <?php if ($r['confirmed_at']): ?><li><span>Confirmed their email</span><span class="muted"><?= e($day($r['confirmed_at'])) ?></span></li><?php endif; ?>
                <?php if ($r['asked_at']): ?><li><span>Client asked<?= $asked_by ? ' by ' . e($asked_by) : '' ?></span><span class="muted"><?= e($day($r['asked_at'])) ?></span></li><?php endif; ?>
                <?php if ($r['status'] === 'approved'): ?><li><span>The client said yes</span><span class="muted"><?= e($day($r['decided_at'])) ?></span></li>
                    <li><span>Downloaded <?= (int)$r['downloads'] ?> time<?= (int)$r['downloads'] === 1 ? '' : 's' ?></span><span class="muted"><?= e($day($r['last_download_at'])) ?></span></li>
                <?php elseif ($r['status'] === 'declined'): ?><li><span><?= $declined_by ? 'Turned down by ' . e($declined_by) : 'The client said no' ?></span><span class="muted"><?= e($day($r['decided_at'])) ?></span></li>
                <?php elseif ($r['status'] === 'expired'): ?><li><span>Closed: no answer in <?= RECORD_REQUEST_ANSWER_DAYS ?> days</span><span class="muted"><?= e($day($r['decided_at'])) ?></span></li><?php endif; ?>
            </ul>
            <?php if ($r['decision_note']): ?><p class="small" style="margin-top:10px">Note to them: “<?= e($r['decision_note']) ?>”</p><?php endif; ?>
            <p class="small" style="margin-top:12px"><a href="<?= e(ops_url('clients/' . (int)$r['business_id'])) ?>">Open <?= e($r['business_name']) ?></a></p>
        </div></section>
    </aside>
</div>

<?php if ($r['status'] === 'new'): ?>
<dialog class="ops-dialog" id="decline" aria-labelledby="decline-title">
    <form method="post" action="<?= e(ops_url('requests/' . (int)$r['id'] . '/decline')) ?>">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head"><h2 id="decline-title">Turn it down</h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
        <div class="ops-dialog-body">
            <p style="margin-bottom:14px">For junk or a request that makes no sense. <?= e($r['business_name']) ?> is not asked or told; <?= e($r['name']) ?> gets your note by email.</p>
            <div class="ops-field"><label for="decline-note">Note to them</label>
                <input class="ops-input" id="decline-note" name="note" required minlength="5" maxlength="500" placeholder="e.g. Please ask the business directly."></div>
        </div>
        <div class="ops-dialog-foot"><button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button><button type="submit" class="ops-btn ops-btn-danger">Turn it down</button></div>
    </form>
</dialog>
<?php endif; ?>
