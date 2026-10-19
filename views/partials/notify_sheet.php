<?php
/**
 * More > Notifications: for each kind of alert this person's role gets,
 * whether it also reaches their phone and their inbox. Everything always
 * lands in the bell. Rendered inside views/more/index.php; assets/js/notify.js
 * saves each change (api/ajax/notifications.php?action=prefs), turns this
 * phone's notifications on or off (assets/js/push.js) and sends tests.
 * The kinds and their defaults: NOTIFY_TOPICS in app/helpers/notify.php.
 */
$np = notify_prefs((int)$user['id']);
$switch = function (string $name, string $title, string $hint, bool $on, string $attr = 'data-pref'): string {
    return '<label class="nf-switch-row"><span class="txt"><b>' . e($title) . '</b><small>' . e($hint) . '</small></span>'
        . '<input type="checkbox" role="switch" name="' . e($name) . '" ' . $attr . ($on ? ' checked' : '') . '></label>';
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
            <button type="button" class="nf-btn nf-btn-ghost" data-test="push">Send a test</button>
            <button type="button" class="nf-btn nf-btn-ghost" data-push-off>Turn off</button>
        </div>
        <!-- Every phone with this person's alerts on, and how the last phone alert went (api/ajax/notifications.php?action=device). -->
        <div class="nf-devices" data-devices hidden>
            <p class="nf-notify-note" data-last-push hidden></p>
            <ul class="nf-device-list" data-device-list aria-label="Phones with alerts on"></ul>
        </div>

        <h3 class="nf-notify-h">What reaches you</h3>
        <p class="nf-notify-note">Everything shows in the bell. Choose what also reaches your phone and your email.</p>
        <div class="nf-topics" role="group" aria-label="What reaches you">
            <div class="nf-topic nf-topic-head" aria-hidden="true"><span></span><span>Phone</span><span>Email</span></div>
            <?php foreach ($np['topics'] as $key => $t): ?>
            <div class="nf-topic" data-topic="<?= e($key) ?>">
                <span class="txt"><b id="nf-topic-<?= e($key) ?>"><?= e($t['label']) ?></b><small><?= e($t['hint']) ?></small></span>
                <span class="sw"><input type="checkbox" role="switch" data-topic-push aria-label="<?= e($t['label']) ?> on your phone"<?= $t['push'] ? ' checked' : '' ?>></span>
                <span class="sw">
                    <?php if ($t['locked']): ?>
                        <small class="nf-always" title="Nfelia always emails you about this">Always</small>
                    <?php else: ?>
                        <input type="checkbox" role="switch" data-topic-email aria-label="<?= e($t['label']) ?> by email"<?= $t['email'] ? ' checked' : '' ?>>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 class="nf-notify-h">On your phone</h3>
        <?= $switch('hide_amounts', 'Hide amounts on the lock screen', 'Phone alerts say “Payment received” instead of the amount and the name. The bell still shows them.', $np['hide_amounts']) ?>
        <?= $switch('quiet_hours', 'Quiet hours', 'No buzzing at night. Everything still waits in the bell.', $np['quiet_hours']) ?>
        <div class="nf-quiet" data-quiet<?= $np['quiet_hours'] ? '' : ' hidden' ?>>
            <label>From <input type="time" name="quiet_from" value="<?= e($np['quiet_from']) ?>" data-pref></label>
            <label>to <input type="time" name="quiet_to" value="<?= e($np['quiet_to']) ?>" data-pref></label>
        </div>

        <h3 class="nf-notify-h">By email</h3>
        <div class="nf-phone-on">
            <button type="button" class="nf-btn nf-btn-ghost" data-test="email">Send me a test email</button>
        </div>
        <p class="nf-notify-note">Emails go to <?= e($user['email'] ?? '') ?>. Sign-in and billing emails always go, so nobody can change them without you knowing.</p>
    </div>
</dialog>
