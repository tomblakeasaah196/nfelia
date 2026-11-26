<?php
/**
 * A fresh start for one client (Admins only): $b, $counts (wipe_counts()),
 * $phrase (what to type to confirm), $past (wipe_history()), $old (the ticks
 * and reason, when the form came back with a problem).
 */
$day    = fn(?string $d) => $d ? date('j M Y', strtotime($d)) : '—';
$ticked = $old['parts'] ?? [];
$limit  = min(array_filter([upload_limit_mb(), max(1, (int)($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 10))]));
?>
<p class="ops-back"><a href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>">← <?= e($b['name']) ?></a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><span class="ops-chip ops-chip-bad">Admins only</span><span class="ops-chip ops-chip-muted">#<?= (int)$b['id'] ?></span></div>
        <h1 class="ops-break">Fresh start for <?= e($b['name']) ?></h1>
        <p>When they ask, wipe what they pick so they can start their books again. It goes from their Nfelia at once and for good.
           A copy is kept here for <?= WIPE_KEEP_DAYS ?> days, for Admins only, and the owner is emailed.</p>
    </div>
</div>

<form method="post" action="<?= e(ops_url('clients/' . (int)$b['id'] . '/wipe')) ?>" enctype="multipart/form-data" class="ops-card ops-danger" id="wipe"
      data-ops-confirm="Wipe what you ticked from <?= e($b['name']) ?> now? It goes from their Nfelia straight away.">
<div class="ops-card-body">
    <?= ops_csrf_field() ?>

    <fieldset class="ops-fieldset">
        <legend class="ops-label">What to wipe</legend>
        <ul class="ops-checks">
        <?php foreach (WIPE_PARTS as $key => $part): $c = $counts[$key]; ?>
            <li><label class="ops-check"><input type="checkbox" name="parts[]" value="<?= e($key) ?>"<?= in_array($key, $ticked, true) ? ' checked' : '' ?>>
                <span><?= e($part['label']) ?>: <?= e(wipe_what_text($c['what'], $c['total'])) ?>
                    <small><?= e($part['hint']) ?></small></span></label></li>
        <?php endforeach; ?>
        </ul>
        <p class="hint" style="margin-top:10px">Always stays: their sign-in and team, their Nfelia plan and its payments, the shop's link, look and posts, bank accounts, and Records Books.</p>
    </fieldset>

    <div class="ops-field">
        <label for="w-why">Why, and who asked</label>
        <textarea class="ops-textarea" id="w-why" name="reason" required minlength="10" maxlength="1000" rows="3"
                  placeholder="e.g. The owner asked on WhatsApp on 27 Oct: done testing, wants a clean start but to keep her customers"><?= e($old['reason'] ?? '') ?></textarea>
        <span class="hint">It stays in the audit log for the team. The owner's email doesn't show it.</span>
    </div>
    <div class="ops-field">
        <label for="w-proof">Their written request <span class="muted">(if you have it)</span></label>
        <input class="ops-input" id="w-proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
        <span class="hint">A screenshot of their email or WhatsApp asking for it, or a PDF, up to <?= (int)$limit ?> MB. Kept with the wipe, for Admins.</span>
    </div>

    <fieldset class="ops-fieldset">
        <legend class="ops-label">Confirm it is you, and this client</legend>
        <div class="ops-row">
            <div class="ops-field"><label for="w-pass">Your Ops password</label>
                <input class="ops-input" id="w-pass" name="password" type="password" required autocomplete="current-password"></div>
            <div class="ops-field"><label for="w-phrase">Type <b><?= e($phrase) ?></b></label>
                <input class="ops-input" id="w-phrase" name="phrase" required autocomplete="off" autocapitalize="characters" spellcheck="false"></div>
        </div>
    </fieldset>

    <div class="ops-actions" style="margin-top:16px">
        <button class="ops-btn ops-btn-danger" type="submit">Wipe what is ticked</button>
        <a class="ops-btn ops-btn-ghost" href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>">Cancel</a>
    </div>
</div>
</form>

<section class="ops-card" id="past"><div class="ops-card-body">
    <h2>Past wipes</h2>
    <?php if ($past): ?>
    <ul class="ops-plain">
    <?php foreach ($past as $w): $gone = json_decode((string)$w['counts'], true) ?: []; ?>
        <li><span><b><?= e($day($w['created_at'])) ?></b>: <?= e($w['full_name'] ?? 'Someone') ?> wiped <?= e(wipe_parts_text(explode(',', (string)$w['parts']))) ?>
                (<?= e(mb_strtolower(wipe_what_text(wipe_counts_what($gone), (int)array_sum($gone)))) ?>).
                <?= $w['owner_told_at'] ? 'Owner emailed.' : 'Owner not told.' ?>
                <span class="small muted" style="display:block"><?= e($w['reason']) ?></span></span>
            <span class="small" style="text-align:right;flex:none">
                <?php if ($w['copy_path']): ?><a href="<?= e(ops_url('wipes/' . (int)$w['id'] . '/copy')) ?>"><?= ops_icon('download') ?> Copy</a><span class="muted" style="display:block">until <?= e($day($w['copy_expires_at'])) ?></span>
                <?php else: ?><span class="muted">Copy deleted</span><?php endif; ?>
                <?php if ($w['proof_path']): ?><a style="display:block" href="<?= e(ops_url('wipes/' . (int)$w['id'] . '/proof')) ?>" target="_blank" rel="noopener">Their request</a><?php endif; ?>
            </span></li>
    <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="muted">None yet.</p><?php endif; ?>
</div></section>
