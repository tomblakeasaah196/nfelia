<?php
/**
 * Email someone at a client (OpsClientsController::email_form): $b, $people
 * (who can be written to), $person (picked, or the owner), $draft (to,
 * subject, body, button), $vars (ops_email_vars() for $person, or null),
 * $preview (the email as it will arrive, or null), $suggestion (a polished
 * subject and body, or null), $polish_ready, $from, $reply_to, $copies,
 * $sent (earlier emails, from the notes).
 */
$base    = 'clients/' . (int)$b['id'] . '/email';
$buttons = ops_email_buttons();
?>
<p class="ops-back"><a href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>">← <?= e($b['name']) ?></a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= ops_plan_chip($b) ?><span class="ops-chip ops-chip-muted">#<?= (int)$b['id'] ?></span></div>
        <h1 class="ops-break">Email <?= e($b['name']) ?></h1>
        <p>Write it in your own words: it goes out in Nfelia's design, from <b><?= e($from) ?></b>. Replies come to <b><?= e($reply_to) ?></b><?php if ($copies): ?>,
           and <?= e(implode(', ', $copies)) ?> gets a copy they don't see<?php endif; ?>. It is kept in the client's notes.</p>
    </div>
</div>

<?php if (!$people): ?>
<section class="ops-card"><div class="ops-card-body">
    <p>Nobody at <?= e($b['name']) ?> has an email address to write to.<?php if ($b['phone']): ?> Try WhatsApp from their page.<?php endif; ?></p>
</div></section>
<?php else: ?>

<p class="small muted" style="margin:0 0 12px">Start from:
    <?php foreach (OPS_EMAIL_STARTS as $key => $s): ?>
        <a class="ops-btn ops-btn-ghost ops-btn-sm" href="<?= e(ops_url($base . '?start=' . $key)) ?>"><?= e($s['label']) ?></a>
    <?php endforeach; ?>
    <span style="display:block;margin-top:4px">{first_name} and {business} are filled in for whoever it goes to.</span>
</p>

<div class="ops-detail">
    <div class="ops-detail-main">
        <form method="post" action="<?= e(ops_url($base)) ?>" class="ops-card" id="compose"><div class="ops-card-body">
            <?= ops_csrf_field() ?>
            <div class="ops-field">
                <label for="m-to">To</label>
                <select class="ops-select" id="m-to" name="to">
                    <?php foreach ($people as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"<?= $person && (int)$person['id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['full_name']) ?> (<?= e($p['role']) ?>) · <?= e($p['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="m-subject">Subject</label>
                <input class="ops-input" id="m-subject" name="subject" maxlength="150" value="<?= e($draft['subject']) ?>" placeholder="e.g. How is Nfelia going for {business}?">
            </div>
            <div class="ops-field">
                <label for="m-body">Message</label>
                <textarea class="ops-textarea" id="m-body" name="body" rows="12" maxlength="6000" placeholder="Hi {first_name},&#10;&#10;Write as you would say it. A blank line starts a new paragraph."><?= e($draft['body']) ?></textarea>
                <span class="hint">Your name and Nfelia are added under it, with a line saying how to reply.</span>
            </div>
            <div class="ops-field">
                <label for="m-button">Button at the end</label>
                <select class="ops-select" id="m-button" name="button">
                    <?php foreach ($buttons as $key => $btn): ?>
                        <option value="<?= e($key) ?>"<?= $draft['button'] === $key ? ' selected' : '' ?>><?= e($btn['label']) ?><?= $btn['url'] ? ' → ' . e(preg_replace('#^https?://#', '', $btn['url'])) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ops-actions" style="margin-top:16px;flex-wrap:wrap">
                <button class="ops-btn ops-btn-secondary" type="submit" name="do" value="preview">Update the preview</button>
                <?php if ($polish_ready): ?>
                    <button class="ops-btn ops-btn-secondary" type="submit" name="do" value="polish">Polish the wording</button>
                <?php endif; ?>
                <button class="ops-btn ops-btn-primary" type="submit" name="do" value="send">Send<?= $person ? ' to ' . e(preg_split('/\s+/u', trim((string)$person['full_name']))[0] ?? '') : '' ?></button>
            </div>
            <?php if (!$polish_ready): ?><p class="hint" style="margin-top:8px">Polish needs GEMINI_API_KEY on the server.</p><?php endif; ?>
        </div></form>

        <?php if ($suggestion): ?>
        <section class="ops-card" id="polish"><div class="ops-card-body">
            <h2>A polished version</h2>
            <p class="small muted">Gemini's suggestion. Nothing is sent until you press Send.</p>
            <p><b><?= e($suggestion['subject']) ?></b></p>
            <div style="white-space:pre-wrap;line-height:1.6;margin:0 0 16px"><?= e($suggestion['body']) ?></div>
            <form method="post" action="<?= e(ops_url($base)) ?>" class="ops-actions">
                <?= ops_csrf_field() ?>
                <input type="hidden" name="to" value="<?= (int)($person['id'] ?? 0) ?>">
                <input type="hidden" name="button" value="<?= e($draft['button']) ?>">
                <input type="hidden" name="subject" value="<?= e($draft['subject']) ?>">
                <input type="hidden" name="body" value="<?= e($draft['body']) ?>">
                <button class="ops-btn ops-btn-primary" type="submit" name="do" value="use">Use this version</button>
                <button class="ops-btn ops-btn-ghost" type="submit" name="do" value="preview">Keep mine</button>
            </form>
        </div></section>
        <?php endif; ?>
    </div>

    <aside class="ops-detail-side">
        <section class="ops-card" id="preview"><div class="ops-card-body">
            <h2>As it will arrive</h2>
            <?php if ($preview): ?>
                <p class="small muted" style="margin:0 0 8px"><b><?= e($vars['subject'] ?: '(no subject yet)') ?></b><br>
                    From <?= e($from) ?> · to <?= e($person['email']) ?></p>
                <iframe title="Preview of the email" sandbox="" srcdoc="<?= e($preview) ?>"
                        style="width:100%;height:640px;border:1px solid var(--ops-line, #E8E4DA);border-radius:8px;background:#F2EFE7"></iframe>
            <?php else: ?>
                <p class="muted">Write a message and press Update the preview to see it in Nfelia's design.</p>
            <?php endif; ?>
        </div></section>

        <section class="ops-card"><div class="ops-card-body">
            <h2>Emails sent before</h2>
            <?php if ($sent): ?>
                <ul class="ops-notes"><?php foreach ($sent as $n): ?>
                    <li><b><?= e($n['full_name'] ?? 'Nfelia') ?></b> <span class="muted small"><?= e(ops_ago($n['created_at'])) ?></span>
                        <p><?= e(mb_strimwidth(strtok((string)$n['body'], "\n"), 0, 140, '…')) ?></p></li>
                <?php endforeach; ?></ul>
            <?php else: ?><p class="muted">None yet.</p><?php endif; ?>
        </div></section>
    </aside>
</div>
<?php endif; ?>
