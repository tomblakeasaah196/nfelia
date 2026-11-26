<?php
/** When people can book: $s (meeting_settings()). */
?>
<p class="ops-back"><a href="<?= e(ops_url('meetings')) ?>">← Meetings</a></p>
<div class="ops-head">
    <div>
        <h1>Meeting hours</h1>
        <p>When people can book a call at nfelia.com/book. All times are Lagos time. Changes apply to new bookings; meetings already booked keep their times.</p>
    </div>
</div>

<form method="post" action="<?= e(ops_url('meetings/settings')) ?>">
    <?= ops_csrf_field() ?>
    <section class="ops-card"><div class="ops-card-body">
        <h2>Open days and hours</h2>
        <?php foreach (MEETING_DAYS as $key => $label): $h = $s['hours'][$key]; ?>
            <div class="ops-row" style="align-items:end">
                <div class="ops-field"><label class="ops-check"><input type="checkbox" name="open_<?= $key ?>" value="1"<?= $h ? ' checked' : '' ?>><span><?= e($label) ?></span></label></div>
                <div class="ops-field"><label for="from-<?= $key ?>">From</label><input class="ops-input" id="from-<?= $key ?>" name="from_<?= $key ?>" type="time" step="300" value="<?= e($h['open'] ?? '09:00') ?>"></div>
                <div class="ops-field"><label for="to-<?= $key ?>">To</label><input class="ops-input" id="to-<?= $key ?>" name="to_<?= $key ?>" type="time" step="300" value="<?= e($h['close'] ?? '17:00') ?>"></div>
            </div>
        <?php endforeach; ?>
    </div></section>

    <section class="ops-card"><div class="ops-card-body">
        <h2>Rules</h2>
        <div class="ops-row">
            <div class="ops-field"><label for="s-notice">Book at least (hours ahead)</label><input class="ops-input" id="s-notice" name="notice" type="number" min="0" max="168" value="<?= (int)$s['notice'] ?>"></div>
            <div class="ops-field"><label for="s-ahead">Book up to (days ahead)</label><input class="ops-input" id="s-ahead" name="ahead" type="number" min="1" max="90" value="<?= (int)$s['ahead'] ?>"></div>
        </div>
        <div class="ops-row">
            <div class="ops-field"><label for="s-gap">Free time between meetings (minutes)</label><input class="ops-input" id="s-gap" name="gap" type="number" min="0" max="120" step="5" value="<?= (int)$s['gap'] ?>"></div>
            <div class="ops-field"><label for="s-step">A start time every</label>
                <select class="ops-select" id="s-step" name="step"><?php foreach (MEETING_STEPS as $n): ?><option value="<?= $n ?>"<?= $s['step'] === $n ? ' selected' : '' ?>><?= $n ?> minutes</option><?php endforeach; ?></select></div>
        </div>
        <div class="ops-field"><label for="s-closed">Closed dates <span class="muted">(holidays, team days: one per line, like 2026-12-25)</span></label>
            <textarea class="ops-textarea" id="s-closed" name="closed" placeholder="2026-12-25&#10;2026-12-26"><?= e(implode("\n", $s['closed'])) ?></textarea></div>
    </div></section>

    <div class="ops-actions"><button class="ops-btn ops-btn-primary" type="submit">Save</button></div>
</form>
