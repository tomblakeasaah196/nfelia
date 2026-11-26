<?php
/** One meeting: $m, $history (Ops audit), $others (their other meetings). */
$wa = $m['phone'] ? preg_replace('/\D/', '', $m['phone']) : '';
if (str_starts_with($wa, '0') && strlen($wa) === 11) $wa = '234' . substr($wa, 1);   // a Nigerian number written locally
$open    = in_array($m['status'], MEETING_HOLDING, true);
$started = meeting_ts($m['starts_at']) <= time();
?>
<p class="ops-back"><a href="<?= e(ops_url('meetings')) ?>">← Meetings</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><?= meeting_status_chip($m['status']) ?> <span class="ops-chip"><?= e($m['type_name']) ?></span></div>
        <h1 class="ops-break"><?= e(meeting_who($m)) ?></h1>
        <p><b><?= e(meeting_when($m)) ?></b>. Booked <?= e(ops_ago($m['created_at'])) ?>.</p>
    </div>
    <div class="ops-head-actions">
        <?php if ($m['phone']): ?><a class="ops-btn ops-btn-secondary" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
        <a class="ops-btn ops-btn-secondary" href="mailto:<?= e($m['email']) ?>">Email</a>
        <?php if ($m['lead_id']): ?><a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('leads/' . (int)$m['lead_id'])) ?>">Lead</a><?php endif; ?>
    </div>
</div>

<div class="ops-detail is-woven">
    <div class="ops-detail-main">
        <?php if (in_array($m['status'], ['booked', 'confirmed'], true)): ?>
        <section class="ops-card is-first" id="confirm"><div class="ops-card-body">
            <?php if ($m['status'] === 'booked'): ?>
                <h2>Send the Google Meet link</h2>
                <ol class="ops-plain" style="margin-bottom:12px">
                    <li><span>1. Make the event in Google Calendar<?= $m['meet_url'] ? ' (or move the one you made before to this time)' : '' ?>: copy the title, time, guest and description from <b>Event details</b>.</span></li>
                    <li><span>2. Add Google Meet video conferencing, save it, and copy the Meet link.</span></li>
                    <li><span>3. Paste the link here and confirm. They get it by email now, then reminders a day and an hour before.</span></li>
                </ol>
                <p style="margin-bottom:16px"><button type="button" class="ops-btn ops-btn-secondary" data-ops-dialog="event-details"><?= ops_icon('copy') ?> Event details</button></p>
            <?php else: ?>
                <h2>Meet link</h2>
                <p style="margin-bottom:12px"><a class="ops-break" href="<?= e($m['meet_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['meet_url']) ?></a>
                   <span class="muted small">· sent <?= e(ops_ago($m['confirmed_at'])) ?></span></p>
                <p class="small muted" style="margin-bottom:12px">Changed the link? Paste the new one and send it again.
                    <button type="button" class="ops-btn ops-btn-ghost ops-btn-sm" data-ops-dialog="event-details"><?= ops_icon('copy') ?> Event details</button></p>
            <?php endif; ?>
            <form method="post" action="<?= e(ops_url('meetings/' . (int)$m['id'] . '/confirm')) ?>">
                <?= ops_csrf_field() ?>
                <div class="ops-field"><label for="meet-url">Google Meet link</label>
                    <input class="ops-input" id="meet-url" name="meet_url" type="url" required maxlength="255" placeholder="https://meet.google.com/abc-defg-hij" value="<?= e($m['meet_url'] ?? '') ?>" autocomplete="off"></div>
                <button class="ops-btn ops-btn-primary" type="submit"><?= $m['status'] === 'booked' ? 'Confirm and email the link' : 'Send the new link' ?></button>
            </form>
        </div></section>
        <?php elseif ($m['status'] === 'reschedule'): ?>
        <section class="ops-card is-first"><div class="ops-card-body">
            <h2>Waiting for them to pick a new time</h2>
            <p>We emailed <?= e($m['email']) ?> a link to pick a new time<?= $m['move_note'] ? ', saying: “' . e($m['move_note']) . '”' : '' ?>. Their current time stays held until they do. When they pick one, it comes back here to confirm with the Meet link.</p>
        </div></section>
        <?php endif; ?>

        <?php if ($m['note']): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>What they want to cover</h2>
            <p class="ops-break"><?= nl2br(e($m['note'])) ?></p>
        </div></section>
        <?php endif; ?>

        <?php if ($open && $started && $m['status'] !== 'reschedule'): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>How did it go?</h2>
            <div class="ops-actions">
                <form method="post" action="<?= e(ops_url('meetings/' . (int)$m['id'] . '/outcome')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="outcome" value="done">
                    <button class="ops-btn ops-btn-primary" type="submit">We had the meeting</button></form>
                <form method="post" action="<?= e(ops_url('meetings/' . (int)$m['id'] . '/outcome')) ?>"><?= ops_csrf_field() ?><input type="hidden" name="outcome" value="no_show">
                    <button class="ops-btn ops-btn-secondary" type="submit">They did not come</button></form>
            </div>
        </div></section>
        <?php endif; ?>

        <?php if ($m['status'] === 'cancelled' && $m['cancel_reason']): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Cancelled</h2>
            <p class="ops-break"><?= e($m['cancel_reason']) ?></p>
        </div></section>
        <?php endif; ?>
    </div>

    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <h2>Details</h2>
            <dl class="ops-facts">
                <dt>Name</dt><dd><?= e($m['name']) ?></dd>
                <?php if ($m['business_name']): ?><dt>Business</dt><dd><?= e($m['business_name']) ?></dd><?php endif; ?>
                <dt>Email</dt><dd class="ops-break"><?= e($m['email']) ?></dd>
                <?php if ($m['phone']): ?><dt>WhatsApp</dt><dd><?= e($m['phone']) ?></dd><?php endif; ?>
                <dt>Length</dt><dd><?= e(meeting_length_text((int)$m['minutes'])) ?></dd>
            </dl>
        </div></section>

        <?php if ($open && !$started): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Change of plan</h2>
            <details class="ops-more"><summary>Ask them to pick a new time</summary>
                <form method="post" action="<?= e(ops_url('meetings/' . (int)$m['id'] . '/reschedule')) ?>" style="margin-top:10px">
                    <?= ops_csrf_field() ?>
                    <div class="ops-field"><label for="mv-note">A line for them <span class="muted">(optional)</span></label>
                        <textarea class="ops-textarea" id="mv-note" name="note" maxlength="500" placeholder="e.g. Our team is at a market event that day. Sorry for the change."><?= e($m['move_note'] ?? '') ?></textarea></div>
                    <button class="ops-btn ops-btn-secondary ops-btn-block" type="submit"><?= $m['status'] === 'reschedule' ? 'Send the link again' : 'Email them the link' ?></button>
                </form>
            </details>
            <details class="ops-more" id="cancel"><summary>Cancel the meeting</summary>
                <form method="post" action="<?= e(ops_url('meetings/' . (int)$m['id'] . '/cancel')) ?>" style="margin-top:10px">
                    <?= ops_csrf_field() ?>
                    <div class="ops-field"><label for="cx-why">Why (they see this)</label>
                        <textarea class="ops-textarea" id="cx-why" name="reason" required minlength="3" maxlength="500" placeholder="e.g. We spoke on WhatsApp and set you up already."></textarea></div>
                    <button class="ops-btn ops-btn-danger ops-btn-block" type="submit">Cancel the meeting with <?= e(preg_split('/\s+/u', trim($m['name']))[0]) ?></button>
                </form>
            </details>
        </div></section>
        <?php endif; ?>

        <?php if ($others): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>Their other meetings</h2>
            <ul class="ops-plain"><?php foreach ($others as $o): ?><li><a href="<?= e(ops_url('meetings/' . (int)$o['id'])) ?>"><?= e($o['type_name']) ?></a> <span class="muted"><?= e(meeting_short_when($o)) ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>

        <?php if ($history): ?>
        <section class="ops-card"><div class="ops-card-body">
            <h2>History</h2>
            <ul class="ops-plain"><?php foreach ($history as $h): ?><li><span><?= e(($h['full_name'] ?? 'Nfelia') . ': ' . $h['summary']) ?></span> <span class="muted"><?= e(ops_ago($h['created_at'])) ?></span></li><?php endforeach; ?></ul>
        </div></section>
        <?php endif; ?>
    </aside>
</div>

<?php if (in_array($m['status'], ['booked', 'confirmed'], true)): $ev = meeting_event($m); ?>
<dialog class="ops-dialog" id="event-details" aria-labelledby="event-details-title">
    <div class="ops-dialog-head"><h2 id="event-details-title">Event details</h2><button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button></div>
    <div class="ops-dialog-body">
        <p class="small muted" style="margin-bottom:16px">Copy each one into the new Google Calendar event. Dates and times are written the way Google Calendar writes them, in Lagos time.</p>
        <?php foreach ([['title', 'Title'], ['date', 'Date'], ['start', 'Starts'], ['end', 'Ends'], ['guest', 'Guest (add under Guests)']] as [$k, $label]): ?>
            <div class="ops-field"><label for="ev-<?= $k ?>"><?= e($label) ?></label>
                <div class="ops-inline-form">
                    <input class="ops-input" id="ev-<?= $k ?>" value="<?= e($ev[$k]) ?>" readonly style="flex:1;min-width:0">
                    <button type="button" class="ops-btn ops-btn-secondary" data-ops-copy="#ev-<?= $k ?>" aria-label="Copy the <?= e(strtolower(strtok($label, ' '))) ?>"><?= ops_icon('copy') ?> <span>Copy</span></button>
                </div></div>
        <?php endforeach; ?>
        <div class="ops-field"><label for="ev-description">Description</label>
            <textarea class="ops-textarea" id="ev-description" readonly rows="9"><?= e($ev['description']) ?></textarea>
            <button type="button" class="ops-btn ops-btn-secondary" data-ops-copy="#ev-description" style="margin-top:8px" aria-label="Copy the description"><?= ops_icon('copy') ?> <span>Copy</span></button></div>
    </div>
    <div class="ops-dialog-foot">
        <a class="ops-btn ops-btn-secondary" href="<?= e(meeting_google_calendar_url($m)) ?>" target="_blank" rel="noopener noreferrer">Open Google Calendar</a>
        <button type="button" class="ops-btn ops-btn-primary" data-ops-close>Done</button>
    </div>
</dialog>
<?php endif; ?>
