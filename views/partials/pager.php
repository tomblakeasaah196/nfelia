<?php
/**
 * Previous and next pages of a list, keeping its filters.
 * Expects $pager = ['path' => 'invoices', 'params' => [filters], 'page' => 2, 'pages' => 5].
 */
if ((int)$pager['pages'] <= 1) return;
$pager_page = (int)$pager['page'];
?>
<nav class="nf-pager" aria-label="Pages">
    <?php if ($pager_page > 1): ?>
        <a href="<?= e(screen_url($pager['path'], ['page' => $pager_page - 1] + $pager['params'])) ?>" rel="prev"><?= nav_icon('back') ?>Previous</a>
    <?php else: ?>
        <i aria-hidden="true"></i>
    <?php endif; ?>
    <span>Page <?= $pager_page ?> of <?= (int)$pager['pages'] ?></span>
    <?php if ($pager_page < (int)$pager['pages']): ?>
        <a href="<?= e(screen_url($pager['path'], ['page' => $pager_page + 1] + $pager['params'])) ?>" rel="next">Next<?= nav_icon('chevron') ?></a>
    <?php else: ?>
        <i aria-hidden="true"></i>
    <?php endif; ?>
</nav>
