<?php
/** Shop names waiting for a look (inbox): $rows. */
?>
<div class="ops-head">
    <div>
        <h1>Shop names</h1>
        <p>Names that look like a bank, a telco, a government body or an official account wait here instead of going live. Approve it if the business is who it says, or turn it down: they choose another.</p>
    </div>
</div>
<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>Nothing waiting</h3><p>When a business asks for a name that needs a look, it shows here.</p></div>
<?php else: ?>
    <ul class="ops-queue">
        <?php foreach ($rows as $b): $host = $b['subdomain_requested'] . '.' . shop_base_host(); ?>
            <li>
                <div class="what">
                    <b class="ops-break"><?= e($host) ?></b> for <a href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>"><?= e($b['name']) ?></a>
                    <div class="meta"><?= $b['subdomain'] ? 'Now ' . e($b['subdomain'] . '.' . shop_base_host()) . ' · ' : 'First shop link · ' ?><?= e($b['category'] ?? '') ?> · <?= e($b['email'] ?? '') ?> · asked <?= e(ops_ago($b['subdomain_requested_at'])) ?></div>
                </div>
                <div class="ops-queue-actions">
                    <form method="post" action="<?= e(ops_url('shop-names/' . (int)$b['id'])) ?>">
                        <?= ops_csrf_field() ?><input type="hidden" name="name" value="<?= e($b['subdomain_requested']) ?>">
                        <button class="ops-btn ops-btn-primary ops-btn-sm" type="submit" name="decision" value="approve">Approve</button>
                        <button class="ops-btn ops-btn-danger ops-btn-sm" type="submit" name="decision" value="reject">Turn down</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
