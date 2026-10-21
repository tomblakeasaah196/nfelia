<?php
/**
 * Announcement bar: one line across the top of every page of the shop. It
 * hides itself after its end date; Shop Studio's preview still shows it,
 * marked as ended, so the owner sees why shoppers don't.
 *
 * From shell.php: $announcement, $announce_ended.
 */
$announce_note = $mode !== 'live' && $announce_ended
    ? 'Ended on ' . date('j F', strtotime($announcement['until'])) . '. Shoppers no longer see it.'
    : '';
?>
<div class="sf-announce<?= $announce_ended ? ' is-ended' : '' ?>" id="sf-announcement" role="note">
    <p class="sf-wrap">
        <?php if ($announcement['text'] !== ''): ?>
            <span><?= e($announcement['text']) ?></span>
        <?php else: ?>
            <span class="sf-gap-inline">Tap here to write your announcement</span>
        <?php endif; ?>
        <?php if ($announce_note !== ''): ?><span class="sf-announce__note"><?= e($announce_note) ?></span><?php endif; ?>
    </p>
</div>
