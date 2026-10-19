<?php
/**
 * Someone asked for a record (RecordsController::request): who, why and
 * what, and Approve or Decline. From the bell, a phone or the email; GET
 * only shows it, so a mail scanner opening the link decides nothing.
 * Variables: $r (record_request()), $roles, $answer ('approve' or
 * 'decline' when the email's button was used).
 */
$csrf = e($_SESSION['csrf_token'] ?? '');
$who  = $r['organisation'] ? $r['organisation'] . ' (' . $r['name'] . ')' : $r['name'];
$open = $r['status'] === 'asked';
?>
<div class="nf-page">
<p style="margin:0 0 8px"><a class="nf-link" href="<?= APP_URL ?>/records#requests">← Your records</a></p>
<div class="nf-head">
    <div>
        <h1 class="nf-large-title"><?= e($who) ?> asked for <?= e($r['label']) ?></h1>
        <p>On <?= e(date('j M Y, g:ia', strtotime($r['created_at']))) ?>, after checking its code at nfelia.com/verify.</p>
    </div>
</div>

<section class="nf-card">
    <dl class="nf-kv">
        <div><dt>Who</dt><dd><?= e($r['name']) ?></dd></div>
        <?php if ($r['organisation']): ?><div><dt>Organisation</dt><dd><?= e($r['organisation']) ?></dd></div><?php endif; ?>
        <div><dt>They are</dt><dd><?= e($roles[$r['role']] ?? 'Someone else') ?></dd></div>
        <div><dt>Email</dt><dd><?= e($r['email']) ?></dd></div>
        <?php if ($r['phone']): ?><div><dt>Phone</dt><dd><?= e($r['phone']) ?></dd></div><?php endif; ?>
        <div><dt>They want</dt><dd><?= e($r['label']) ?></dd></div>
    </dl>
    <h2 style="margin:18px 0 6px;font:600 1rem/1.3 var(--font-heading)">Why</h2>
    <p class="nf-pre"><?= e($r['reason']) ?></p>
</section>

<?php if ($open): ?>
<section class="nf-card">
    <p style="margin:0 0 12px">If you say yes, Nfelia emails them a link to download it for the next <?= RECORD_REQUEST_LINK_DAYS ?> days<?= $r['record_type'] === null ? '' : ', with the record as it is today' ?>. If you say no, they are told politely. Either way, it is your choice.</p>
    <form method="POST" action="<?= APP_URL ?>/records/answer/<?= (int)$r['id'] ?>" class="nf-form" style="padding:0">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <label>A note for them <small>(optional)</small><textarea name="note" maxlength="500" placeholder="e.g. Happy to help. Call me if you need more."></textarea></label>
        <div class="nf-actions-flush" style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" name="answer" value="approve" class="nf-btn nf-btn-in"<?= $answer === 'approve' ? ' autofocus' : '' ?>>Approve and share it</button>
            <button type="submit" name="answer" value="decline" class="nf-btn nf-btn-ghost"<?= $answer === 'decline' ? ' autofocus' : '' ?>>Decline</button>
        </div>
    </form>
</section>
<?php else: ?>
<section class="nf-card">
    <p style="margin:0">
        <?= match ($r['status']) {
            'approved' => 'You shared it on ' . e(date('j M Y', strtotime((string)$r['decided_at']))) . '.' . ((int)$r['downloads'] ? ' They downloaded it ' . (int)$r['downloads'] . ' time' . ((int)$r['downloads'] === 1 ? '' : 's') . '.' : ' They haven\'t downloaded it yet.'),
            'declined' => 'Declined on ' . e(date('j M Y', strtotime((string)$r['decided_at']))) . '.',
            default    => 'This request was closed after ' . RECORD_REQUEST_ANSWER_DAYS . ' days without an answer.',
        } ?>
    </p>
</section>
<?php endif; ?>
</div>
