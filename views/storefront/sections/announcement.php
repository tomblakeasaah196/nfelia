<?php
/**
 * Announcement bar: one line across the top of the shop.
 */
$announcement = $sections['announcement'];
?>
<div class="sf-announce" id="sf-announcement" role="note">
    <p class="sf-wrap"><?= $announcement['text'] !== '' ? e($announcement['text']) : '<span class="sf-gap-inline">Your announcement goes here</span>' ?></p>
</div>
