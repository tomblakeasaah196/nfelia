<?php
/**
 * Pick a day, a time, then (booking) your details or (moving) confirm.
 * $type (name, minutes, description), $days [Y-m-d => free times], $date, $times, $time,
 * $form (what they typed before a problem), $form_action, $base (this page's address),
 * $m (the meeting being moved; unset when booking).
 */
$moving = isset($m);
$link = fn(array $q, string $anchor) => $base . '?' . http_build_query($q) . $anchor;
$day_label = fn(string $d) => (new DateTimeImmutable($d, meeting_tz()))->format('l j F');
?>
<?php if (!$moving): ?><a class="bk-back" href="<?= e(APP_URL . '/book') ?>">← All meetings</a><?php endif; ?>
<h1><?= $moving ? 'Pick a new time' : e($type['name']) ?></h1>
<?php if ($moving): ?>
    <p class="bk-lead"><?= e($type['name']) ?> with the Nfelia team. Your time now is <b><?= e(meeting_when($m)) ?></b>, and it stays yours until you pick a new one.</p>
    <?php if ($m['move_note']): ?><p class="bk-flash is-info">From the Nfelia team: <?= e($m['move_note']) ?></p><?php endif; ?>
<?php elseif ($type['description']): ?>
    <p class="bk-lead"><?= e($type['description']) ?></p>
<?php endif; ?>
<ul class="bk-meta">
    <li class="bk-pill"><?= e(meeting_length_text((int)$type['minutes'])) ?></li>
    <li class="bk-pill">Google Meet</li>
    <li class="bk-pill">Lagos time</li>
</ul>

<?php if (!$days): ?>
    <div class="bk-empty">
        <h2>No open times just now</h2>
        <p>Every time is taken for the coming weeks. Write to <a href="mailto:sales@nfelia.com">sales@nfelia.com</a> and we'll make room for you.</p>
    </div>
<?php else: ?>
    <section class="bk-step" aria-labelledby="step-day">
        <h2 id="step-day"><span class="bk-step-n" aria-hidden="true">1</span>Pick a day</h2>
        <ul class="bk-days">
            <?php foreach ($days as $d => $n): $dt = new DateTimeImmutable($d, meeting_tz()); ?>
                <li><a class="bk-day" href="<?= e($link(['date' => $d], '#times')) ?>"<?= $d === $date ? ' aria-current="date"' : '' ?>
                       aria-label="<?= e($dt->format('l j F') . ', ' . $n . ' free time' . ($n === 1 ? '' : 's')) ?>">
                    <small><?= e($dt->format('D')) ?></small><b><?= e($dt->format('j')) ?></b><small><?= e($dt->format('M')) ?></small>
                </a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="bk-step" id="times" aria-labelledby="step-time">
        <h2 id="step-time"><span class="bk-step-n" aria-hidden="true">2</span>Pick a time on <?= e($day_label($date)) ?></h2>
        <ul class="bk-times">
            <?php foreach ($times as $t): ?>
                <li><a class="bk-time" href="<?= e($link(['date' => $date, 'time' => $t], '#details')) ?>"<?= $t === $time ? ' aria-current="time"' : '' ?>><?= e(meeting_time_text($t)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($time): ?>
    <section class="bk-step" id="details" aria-labelledby="step-details">
        <h2 id="step-details"><span class="bk-step-n" aria-hidden="true">3</span><?= $moving ? 'Confirm the new time' : 'Your details' ?></h2>
        <p class="bk-chosen"><?= e($day_label($date)) ?>, <?= e(meeting_time_text($time)) ?> (Lagos time)</p>
        <form method="post" action="<?= e($form_action) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <input type="hidden" name="time" value="<?= e($time) ?>">
            <?php if (!$moving): ?>
                <div class="bk-trap" aria-hidden="true"><label for="bk-website">Leave this empty</label><input id="bk-website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="bk-row">
                    <div class="bk-field"><label for="bk-name">Your name</label>
                        <input class="bk-input" id="bk-name" name="name" required minlength="2" maxlength="120" autocomplete="name" value="<?= e($form['name'] ?? '') ?>"></div>
                    <div class="bk-field"><label for="bk-email">Email</label>
                        <input class="bk-input" id="bk-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($form['email'] ?? '') ?>" aria-describedby="bk-email-help">
                        <p class="bk-help" id="bk-email-help">Your Google Meet link and reminders come here.</p></div>
                </div>
                <div class="bk-row">
                    <div class="bk-field"><label for="bk-phone">WhatsApp number</label>
                        <input class="bk-input" id="bk-phone" name="phone" type="tel" inputmode="tel" required maxlength="40" autocomplete="tel" value="<?= e($form['phone'] ?? '') ?>" aria-describedby="bk-phone-help">
                        <p class="bk-help" id="bk-phone-help">For example 0803 555 0199. Only if we need to reach you quickly.</p></div>
                    <div class="bk-field"><label for="bk-biz">Business name <span>(optional)</span></label>
                        <input class="bk-input" id="bk-biz" name="business_name" maxlength="160" autocomplete="organization" value="<?= e($form['business_name'] ?? '') ?>"></div>
                </div>
                <div class="bk-field"><label for="bk-note">What would you like to cover? <span>(optional)</span></label>
                    <textarea class="bk-textarea" id="bk-note" name="note" maxlength="2000"><?= e($form['note'] ?? '') ?></textarea></div>
                <?= bot_guard_fields('book') ?>
                <button class="bk-btn bk-btn-block" type="submit">Book <?= e(meeting_time_text($time)) ?></button>
                <p class="bk-small" style="margin-top:12px">The time is yours as soon as you book. We'll email your Google Meet link shortly after.</p>
            <?php else: ?>
                <button class="bk-btn bk-btn-block" type="submit">Move my meeting here</button>
            <?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
<?php endif; ?>
