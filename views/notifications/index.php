<?php
/**
 * Notifications, all of them (NotificationsController::index). The bell
 * shows the newest few; this is the whole list. Each row opens
 * notifications/open/{id}, which marks it read and goes to the exact page.
 * Variables: $notifications, $unread, $unread_only, $more
 */
$ago = function (string $at): string {
    $then = new DateTimeImmutable($at, planner_tz());
    $secs = planner_now()->getTimestamp() - $then->getTimestamp();
    if ($secs < 60)    return 'Just now';
    if ($secs < 3600)  return intdiv($secs, 60) . ' min ago';
    if ($secs < 86400) return intdiv($secs, 3600) . ' h ago';
    $days = planner_days_between($then->format('Y-m-d'), planner_today());
    if ($days === 1)   return 'Yesterday, ' . $then->format('g:ia');
    return $then->format($days < 300 ? 'j M, g:ia' : 'j M Y');
};
$base = APP_URL . '/notifications' . ($unread_only ? '?unread=1&' : '?');
?>
<div class="nf-head">
    <div>
        <h1 class="nf-large-title">Notifications</h1>
        <p><?= $unread ? $unread . ' unread' : 'You’re all caught up' ?></p>
    </div>
    <?php if ($unread): ?>
    <form action="<?= APP_URL ?>/notifications/read-all" method="POST" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <button type="submit" class="nf-btn nf-btn-ghost">Mark all read</button>
    </form>
    <?php endif; ?>
</div>

<nav class="nf-segs" aria-label="Show">
    <a href="<?= APP_URL ?>/notifications"<?= $unread_only ? '' : ' aria-current="page"' ?>>All</a>
    <a href="<?= APP_URL ?>/notifications?unread=1"<?= $unread_only ? ' aria-current="page"' : '' ?>>Unread</a>
</nav>

<?php if ($notifications): ?>
<div class="nf-list nf-inbox">
    <?php foreach ($notifications as $n): ?>
    <a class="nf-note<?= (int)$n['is_read'] ? '' : ' is-new' ?>" href="<?= APP_URL ?>/notifications/open/<?= (int)$n['id'] ?>">
        <i></i>
        <span style="min-width:0">
            <b><?= e($n['title']) ?></b>
            <p><?= e($n['message']) ?></p>
            <small><?= e($ago($n['created_at'])) ?><?= (int)$n['is_read'] ? '' : '<span class="sr-only">, unread</span>' ?></small>
        </span>
    </a>
    <?php endforeach; ?>
</div>
<?php if ($more): ?>
<p style="text-align:center;margin:16px 0 0">
    <a class="nf-btn nf-btn-ghost" href="<?= $base ?>before=<?= (int)end($notifications)['id'] ?>">Show older</a>
</p>
<?php endif; ?>
<?php else: ?>
<div class="nf-list" style="padding:32px 20px;text-align:center">
    <p style="font-weight:600;margin:0 0 4px"><?= $unread_only ? 'Nothing unread' : 'Nothing yet' ?></p>
    <p style="color:var(--ink-2);font-size:.875rem;margin:0">
        <?= $unread_only ? 'Everything here has been read.' : 'Orders, payments, bookings and reminders show up here.' ?>
    </p>
    <p style="margin:16px 0 0"><a class="nf-btn nf-btn-ghost" href="<?= APP_URL ?>/more#notifications">Choose what you get</a></p>
</div>
<?php endif; ?>
