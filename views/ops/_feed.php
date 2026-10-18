<?php
/** Audit rows as a feed (home, the audit log, a client's history). $rows. */
?>
<ul class="ops-feed">
    <?php foreach ($rows as $r): ?>
        <li>
            <?php if ($r['full_name']): ?>
                <span class="ops-avatar" aria-hidden="true"><?= e(ops_initials($r['full_name'])) ?></span>
            <?php else: ?>
                <span class="ops-avatar is-system" aria-hidden="true">N</span>
            <?php endif; ?>
            <div class="what">
                <b><?= e($r['full_name'] ?? 'Nfelia') ?></b> · <?= e($r['summary']) ?>
                <div class="meta">
                    <?= e($r['action']) ?>
                    <?php if (!empty($r['business_name'])): ?> · <a href="<?= e(ops_url('audit?business=' . (int)$r['business_id'])) ?>"><?= e($r['business_name']) ?></a><?php endif; ?>
                    <?php if ($r['ip']): ?> · <?= e($r['ip']) ?><?php endif; ?>
                </div>
            </div>
            <time class="when" datetime="<?= e((new DateTimeImmutable($r['created_at'], new DateTimeZone(APP_TIMEZONE)))->format('c')) ?>" title="<?= e(date('j M Y, H:i', strtotime($r['created_at']))) ?>"><?= e(ops_ago($r['created_at'])) ?></time>
        </li>
    <?php endforeach; ?>
</ul>
