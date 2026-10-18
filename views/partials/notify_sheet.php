<?php
/**
 * More > Notifications: what makes this person's phone buzz, and what comes
 * by email. Rendered inside views/more/index.php; assets/js/notify.js saves
 * each change (api/ajax/notifications.php?action=prefs) and turns this
 * phone's notifications on or off (assets/js/push.js).
 * Money in reaches the owner and admins only, so only they see that switch.
 */
$np = notify_prefs((int)$user['id']);
$switch = function (string $name, string $title, string $hint) use ($np): string {
    return '<label class="nf-switch-row"><span class="txt"><b>' . e($title) . '</b><small>' . e($hint) . '</small></span>'
        . '<input type="checkbox" role="switch" name="' . $name . '" data-pref' . (!empty($np[$name]) ? ' checked' : '') . '></label>';
};
?>
<dialog class="nf-sheet" id="nf-notify" aria-labelledby="nf-notify-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-notify-h">Notifications</h2>
        <span class="nf-saved" data-saved role="status" aria-live="polite"></span>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <div class="nf-sheet-body nf-notify">
        <div class="nf-phone" data-push data-key="<?= e(VAPID_PUBLIC_KEY) ?>" data-enabled="<?= push_enabled() ? '1' : '0' ?>">
            <span class="nf-tile-ic"><?= nav_icon('phone') ?></span>
            <span class="txt"><b>This phone</b><small data-push-note>Checking this phone…</small></span>
            <button type="button" class="nf-btn nf-btn-primary" data-push-on hidden>Turn on</button>
        </div>
        <div class="nf-phone-on" data-push-controls hidden>
            <button type="button" class="nf-btn nf-btn-ghost" data-push-test>Send a test</button>
            <button type="button" class="nf-btn nf-btn-ghost" data-push-off>Turn off</button>
        </div>

        <h3 class="nf-notify-h">What buzzes your phone</h3>
        <?php if ($is_boss): ?>
            <?= $switch('notify_money', 'Money in', 'Payments recorded and new shop orders, as they happen.') ?>
        <?php endif; ?>
        <?= $switch('notify_reminders', 'Reminders and morning plan', 'Your tasks at the time you set, and your day at 7am.') ?>
        <?= $switch('notify_low_stock', 'Low stock', 'Once a day, when something is running low.') ?>
        <?= $switch('quiet_hours', 'Quiet hours', 'No buzzing at night. Everything still waits in the bell.') ?>
        <div class="nf-quiet" data-quiet<?= $np['quiet_hours'] ? '' : ' hidden' ?>>
            <label>From <input type="time" name="quiet_from" value="<?= e($np['quiet_from']) ?>" data-pref></label>
            <label>to <input type="time" name="quiet_to" value="<?= e($np['quiet_to']) ?>" data-pref></label>
        </div>

        <h3 class="nf-notify-h">By email</h3>
        <?= $switch('morning_email', 'Morning plan', 'At 7am, on days with something planned.') ?>
        <?= $switch('reminder_email', 'Reminders', 'At the time you set on a task or appointment.') ?>
    </div>
</dialog>
