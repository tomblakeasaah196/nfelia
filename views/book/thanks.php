<?php
/** After booking (or moving): $m, $moved. */
?>
<div class="bk-done" role="status">
    <span class="bk-done-mark" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
    </span>
    <h1><?= $moved ? 'Your new time is booked' : 'You\'re booked' ?></h1>
    <p class="bk-lead" style="margin-bottom:0">We'll email your Google Meet link to <b><?= e($m['email']) ?></b> shortly. Look in your spam folder too if it hasn't arrived within a few hours.</p>
    <dl class="bk-facts">
        <dt>Meeting</dt><dd><?= e($m['type_name']) ?></dd>
        <dt>When</dt><dd><?= e(meeting_when($m)) ?></dd>
        <dt>Where</dt><dd>Google Meet (link coming by email)</dd>
    </dl>
    <div class="bk-actions">
        <a class="bk-btn" href="<?= e(APP_URL) ?>/">Back to Nfelia</a>
        <a class="bk-btn bk-btn-quiet" href="<?= e(APP_URL . '/book') ?>">Book another meeting</a>
    </div>
</div>
