<?php
/**
 * Quote requests from the shop's "Ask for a quote" (QuotationController::requests).
 * One request, opened from its notification, with what to do next; then the list.
 * Variables: $request (or null), $customer_id, $requests, $show, $business
 */
$wa_text = fn($r) => 'Hello ' . planner_first_name($r['name']) . ', thank you for your quote request to ' . $business['name'] . '. ';
?>
<div class="nf-page">
<div class="nf-head">
    <div>
        <h1 class="nf-large-title">Quote requests</h1>
        <p>People who asked for a price on your shop.</p>
    </div>
</div>

<?php if ($request): ?>
<section class="nf-card" aria-labelledby="qr-h">
    <div class="nf-card-h">
        <div>
            <h2 id="qr-h"><?= e($request['name']) ?></h2>
            <p><?= e(screen_date($request['created_at'])) ?> · <?= e(date('g:ia', strtotime($request['created_at']))) ?>
               <?= $request['status'] === 'converted' ? ' · Quoted' : ($request['status'] === 'archived' ? ' · Put away' : '') ?></p>
        </div>
    </div>
    <p class="nf-pre"><?= e($request['message']) ?></p>
    <p style="margin:12px 0 0;font-size:.9375rem;color:var(--ink-2)">
        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $request['phone'])) ?>" style="color:var(--accent-text);font-weight:600"><?= e($request['phone']) ?></a>
        <?php if (!empty($request['email'])): ?> · <a href="mailto:<?= e($request['email']) ?>" style="color:var(--accent-text);font-weight:600"><?= e($request['email']) ?></a><?php endif; ?>
    </p>
    <div class="nf-actions" style="padding:16px 0 0">
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/quotations/create<?= $customer_id ? '?customer=' . (int)$customer_id : '' ?>">Write a quote</a>
        <a class="nf-btn nf-btn-ghost" href="<?= e(planner_wa_link($request['phone'], $wa_text($request))) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a>
    </div>
    <form method="POST" action="<?= APP_URL ?>/quotations/request-status/<?= (int)$request['id'] ?>" class="nf-actions" style="padding:8px 0 0">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <?php if (in_array($request['status'], ['new', 'reviewed'], true)): ?>
            <button class="nf-btn nf-btn-ghost" name="status" value="converted">Mark as quoted</button>
            <button class="nf-btn nf-btn-ghost" name="status" value="archived">Put away</button>
        <?php else: ?>
            <button class="nf-btn nf-btn-ghost" name="status" value="reviewed">Move back to open</button>
        <?php endif; ?>
    </form>
</section>
<?php endif; ?>

<nav class="nf-segs" aria-label="Show">
    <a href="<?= APP_URL ?>/quotations/requests"<?= $show === 'open' ? ' aria-current="page"' : '' ?>>Open</a>
    <a href="<?= APP_URL ?>/quotations/requests?show=done"<?= $show === 'done' ? ' aria-current="page"' : '' ?>>Done</a>
</nav>

<?php if ($requests): ?>
<div class="nf-card is-flush"><div class="nf-items">
    <?php foreach ($requests as $r): ?>
    <a class="nf-item" href="<?= APP_URL ?>/quotations/requests/<?= (int)$r['id'] ?><?= $show === 'done' ? '?show=done' : '' ?>"<?= $request && (int)$request['id'] === (int)$r['id'] ? ' aria-current="true"' : '' ?>>
        <span class="nf-item-main">
            <b><?= e($r['name']) ?></b>
            <small><?= e(notify_excerpt($r['message'], 90)) ?></small>
        </span>
        <span class="nf-item-end">
            <small><?= e(screen_date($r['created_at'])) ?></small>
            <?php if ($r['status'] === 'new'): ?><span class="nf-tag is-accent">New</span><?php endif; ?>
        </span>
    </a>
    <?php endforeach; ?>
</div></div>
<?php else: ?>
<div class="nf-empty">
    <span class="nf-tile-ic"><?= nav_icon('chat') ?></span>
    <h2><?= $show === 'done' ? 'Nothing here yet' : 'No requests waiting' ?></h2>
    <p><?= $show === 'done' ? 'Requests you quote or put away move here.' : 'When someone asks for a price on your shop, it shows here and you get a notification.' ?></p>
</div>
<?php endif; ?>
</div>
