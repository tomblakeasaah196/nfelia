<?php /* views/emails/meeting_update.php — to someone who booked a meeting at nfelia.com/book (app/ops/meetings.php).
   Variables: $state ('booked', 'moved', 'confirmed', 'reschedule', 'cancelled', 'reminder_day' or 'reminder_hour'),
   $first_name, $type_name, $when, $length, $meet_url ('' until Ops confirms), $move_url (for 'reschedule'),
   $message (a line from the team, '' when none), $book_url */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$headline = match ($state) {
    'confirmed'     => 'You\'re all set, ' . $first_name . '.',
    'reschedule'    => 'Can we find another time, ' . $first_name . '?',
    'cancelled'     => 'Your meeting was cancelled.',
    'reminder_day'  => 'See you soon, ' . $first_name . '.',
    'reminder_hour' => 'Your meeting starts in about an hour.',
    'moved'         => 'Thank you, ' . $first_name . '. We have your new time.',
    default         => 'Thank you, ' . $first_name . '. You\'re booked.',
};
$text = match ($state) {
    'confirmed'     => 'Your meeting with the Nfelia team is confirmed. Join on Google Meet with the button below at the time. We may record and transcribe the call so both of us have the notes afterwards.',
    'reschedule'    => 'Something came up on our side at that time. Please pick another time that suits you: it takes one tap, and your current time stays yours until you choose.',
    'cancelled'     => 'We cancelled your meeting with the Nfelia team. We\'re sorry for the trouble. You can book another time whenever you like.',
    'reminder_day'  => 'This is a reminder of your meeting with the Nfelia team. Join on Google Meet with the button below at the time.',
    'reminder_hour' => 'Join on Google Meet with the button below. If you can\'t make it, write to sales@nfelia.com and we\'ll find another time.',
    'moved'         => 'Your meeting has moved to the time below. We\'ll send your Google Meet link by email shortly.',
    default         => 'The time below is yours. We\'ll send your Google Meet link by email shortly, so you can join from your phone or computer.',
};
[$button, $button_url] = match ($state) {
    'confirmed', 'reminder_day', 'reminder_hour' => $meet_url !== '' ? ['Join on Google Meet', $meet_url] : [null, null],
    'reschedule' => ['Pick a new time', $move_url],
    'cancelled'  => ['Book another time', $book_url],
    default      => [null, null],
};
?>
<h2 style="margin:0 0 16px;font-size:22px;color:#1B0A11;font-weight:700;"><?= $h($headline) ?></h2>
<p style="margin:0 0 16px;color:#3a2f2b;"><?= $h($text) ?></p>
<?php if ($message !== ''): ?>
<p style="margin:0 0 16px;padding:12px 14px;background:#f6f1ec;color:#3a2f2b;"><strong>From the Nfelia team:</strong> <?= nl2br($h($message)) ?></p>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;border-collapse:collapse;font-size:14px;color:#3a2f2b;">
    <tr><td style="padding:6px 0;color:#9a8f8a;width:120px;">Meeting</td><td style="padding:6px 0;font-weight:700;color:#1B0A11;"><?= $h($type_name) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;"><?= $state === 'cancelled' ? 'Was' : 'When' ?></td><td style="padding:6px 0;"><?= $h($when) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;">How long</td><td style="padding:6px 0;"><?= $h($length) ?></td></tr>
    <tr><td style="padding:6px 0;color:#9a8f8a;">Where</td><td style="padding:6px 0;">Google Meet<?= in_array($state, ['booked', 'moved'], true) ? ' (link coming by email)' : '' ?></td></tr>
</table>
<?php if ($button): ?>
<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:<?= $h($biz_color) ?>;">
            <a href="<?= $h($button_url) ?>"
               style="display:inline-block;padding:14px 32px;font-size:11px;font-weight:700;color:<?= $h($biz_on) ?>;text-decoration:none;letter-spacing:0.15em;text-transform:uppercase;">
                <?= $h($button) ?> →
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;font-size:11px;color:#9a8f8a;">
    Link: <a href="<?= $h($button_url) ?>" style="color:<?= $h($biz_link) ?>;word-break:break-all;"><?= $h($button_url) ?></a>
</p>
<?php endif; ?>
<p style="margin:0;font-size:13px;color:#9a8f8a;">
    Questions, or need to change something? Write to sales@nfelia.com.
</p>
