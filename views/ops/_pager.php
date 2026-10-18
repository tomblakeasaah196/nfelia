<?php
/** Pagination under a list, always shown: $total, $page, $pages, $url(['page' => n]). */
$noun = $noun ?? ['result', 'results'];
?>
<nav class="ops-pager" aria-label="Pages">
    <span class="num"><?= number_format($total) ?> <?= $total === 1 ? $noun[0] : $noun[1] ?><?= $pages > 1 ? ' · page ' . $page . ' of ' . $pages : '' ?></span>
    <span class="ops-actions">
        <?php if ($page > 1): ?>
            <a class="ops-btn ops-btn-secondary ops-btn-sm" href="<?= e($url(['page' => $page - 1])) ?>">Previous</a>
        <?php else: ?>
            <span class="ops-btn ops-btn-secondary ops-btn-sm" aria-disabled="true">Previous</span>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
            <a class="ops-btn ops-btn-secondary ops-btn-sm" href="<?= e($url(['page' => $page + 1])) ?>">Next</a>
        <?php else: ?>
            <span class="ops-btn ops-btn-secondary ops-btn-sm" aria-disabled="true">Next</span>
        <?php endif; ?>
    </span>
</nav>
