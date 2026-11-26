<?php
/**
 * Meetings with the Nfelia team: anyone books a call at nfelia.com/book
 * (app/controllers/MeetingBookController.php) and Ops looks after it under
 * Sales → Meetings (OpsMeetingsController). Not the shops' bookings
 * (app/helpers/bookings.php): those belong to one business each, these to
 * Nfelia itself.
 *
 * One shared calendar. A time is free when it is inside the opening hours
 * (meeting_settings), at least `notice` hours away, no more than `ahead`
 * days away, not on a closed date, and `gap` minutes clear of every meeting
 * that holds its time (MEETING_HOLDING). Booking takes the time at once.
 *
 *   booked      the time is theirs. Ops makes the Google Calendar event with
 *               them as a guest (so the Meet transcript reaches them),
 *               pastes its Meet link and confirms: the link is emailed
 *   confirmed   reminders go a day and an hour before (meetings_cron)
 *   reschedule  Ops asked them to pick another time by email link; the old
 *               time stays theirs until they do
 *   cancelled, done, no_show
 *
 * Every booking is also a lead at stage Demo (ops_lead_match, app/ops/sales.php),
 * and sales@nfelia.com hears of each booking, new time and cancellation.
 * Times are Lagos time, like the database (config/database.php).
 */

require_once __DIR__ . '/sales.php';

const MEETING_DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
                      'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
const MEETING_HOLDING = ['booked', 'confirmed', 'reschedule'];   // statuses that take up a time
const MEETING_STATUSES = [
    'booked'     => ['label' => 'Needs a Meet link', 'tone' => 'warn'],
    'confirmed'  => ['label' => 'Confirmed',         'tone' => 'ok'],
    'reschedule' => ['label' => 'Picking a new time', 'tone' => 'accent'],
    'cancelled'  => ['label' => 'Cancelled',         'tone' => 'muted'],
    'done'       => ['label' => 'Done',              'tone' => 'ok'],
    'no_show'    => ['label' => 'Did not come',      'tone' => 'bad'],
];
const MEETING_STEPS = [15, 20, 30, 45, 60];
const MEETING_RESERVED_SLUGS = ['index', 'move', 'thanks'];   // paths under /book that are not types

/** Who hears of every booking, new time and cancellation. */
function meeting_team_email(): string
{
    $to = trim((string)($_ENV['MEETINGS_NOTIFY_EMAIL'] ?? ''));
    return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : 'sales@nfelia.com';
}

/* ═══════════════════════════════════════════════════════════════════
 *  SETTINGS
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * The calendar's settings, cleaned, with defaults:
 *   hours   per day ['open' => '09:00', 'close' => '17:00'], or null when closed
 *   notice  hours ahead a meeting must be booked
 *   ahead   days ahead people can book
 *   gap     minutes kept free between meetings
 *   step    minutes between start times
 *   closed  dates nobody can book (holidays), Y-m-d
 */
function meeting_settings(PDO $db): array
{
    $raw = $db->query("SELECT settings FROM meeting_settings WHERE id = 1")->fetchColumn();
    $raw = $raw !== false ? json_decode((string)$raw, true) : null;
    return meeting_clean_settings(is_array($raw) ? $raw : []);
}

function meeting_clean_settings(array $raw): array
{
    $hours = [];
    foreach (MEETING_DAYS as $key => $_) {
        if (array_key_exists('hours', $raw) && is_array($raw['hours'])) {
            $day   = $raw['hours'][$key] ?? null;
            $open  = is_array($day) ? meeting_clean_time($day['open'] ?? null) : null;
            $close = is_array($day) ? meeting_clean_time($day['close'] ?? null) : null;
            $hours[$key] = ($open && $close && $open < $close) ? ['open' => $open, 'close' => $close] : null;
        } else {
            $hours[$key] = $key === 'sun' ? null : ['open' => '09:00', 'close' => '17:00'];
        }
    }
    $closed = [];
    foreach ((array)($raw['closed'] ?? []) as $date) {
        if (meeting_valid_date((string)$date)) $closed[] = (string)$date;
    }
    $closed = array_values(array_unique($closed));
    sort($closed);
    $step = (int)($raw['step'] ?? 30);
    return [
        'hours'  => $hours,
        'notice' => max(0, min(168, (int)($raw['notice'] ?? 4))),
        'ahead'  => max(1, min(90, (int)($raw['ahead'] ?? 30))),
        'gap'    => max(0, min(120, (int)($raw['gap'] ?? 15))),
        'step'   => in_array($step, MEETING_STEPS, true) ? $step : 30,
        'closed' => array_slice($closed, -60),
    ];
}

function meeting_save_settings(PDO $db, array $settings, int $staff_id): void
{
    $db->prepare("INSERT INTO meeting_settings (id, settings, updated_by) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_by = VALUES(updated_by)")
       ->execute([json_encode(meeting_clean_settings($settings)), $staff_id]);
}

/** "9:30" or "09:30" as "09:30", on the quarter hour; null otherwise. */
function meeting_clean_time(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
        return null;
    }
    $h = (int)$m[1];
    $i = (int)$m[2];
    return ($h < 24 && $i < 60 && $i % 5 === 0) ? sprintf('%02d:%02d', $h, $i) : null;
}

function meeting_valid_date(?string $date): bool
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
}

/* ═══════════════════════════════════════════════════════════════════
 *  MEETING TYPES
 * ═══════════════════════════════════════════════════════════════════ */

/** Types not removed, in their order; only those shown on the booking page with $active_only. */
function meeting_types(PDO $db, bool $active_only = false): array
{
    return $db->query("SELECT * FROM meeting_types WHERE deleted_at IS NULL" . ($active_only ? " AND is_active = 1" : "") . " ORDER BY sort_order, name")->fetchAll();
}

/** A type people can book, by its address (/book/{slug}). */
function meeting_type_by_slug(PDO $db, string $slug): ?array
{
    $stmt = $db->prepare("SELECT * FROM meeting_types WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** An address for a type's name, not taken by another type (removed ones included: old links keep failing cleanly). */
function meeting_type_slug(PDO $db, string $name, int $except = 0): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)), '-');
    $base = substr($base !== '' ? $base : 'meeting', 0, 50);
    $slug = in_array($base, MEETING_RESERVED_SLUGS, true) ? $base . '-call' : $base;
    $stmt = $db->prepare("SELECT COUNT(*) FROM meeting_types WHERE slug = ? AND id <> ?");
    for ($n = 2; ; $n++) {
        $stmt->execute([$slug, $except]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n;
    }
}

/** "30 minutes", "1 hour", "1 hour 30 minutes". */
function meeting_length_text(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $parts = [];
    if ($h) $parts[] = $h . ' hour' . ($h === 1 ? '' : 's');
    if ($m) $parts[] = $m . ' minutes';
    return implode(' ', $parts) ?: '0 minutes';
}

/* ═══════════════════════════════════════════════════════════════════
 *  FREE TIMES
 * ═══════════════════════════════════════════════════════════════════ */

function meeting_tz(): DateTimeZone
{
    return new DateTimeZone(APP_TIMEZONE);
}

function meeting_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', meeting_tz());
}

/** A database (Lagos) time as a timestamp. */
function meeting_ts(string $db_time): int
{
    return (new DateTimeImmutable($db_time, meeting_tz()))->getTimestamp();
}

/** The first and last day people can book today. */
function meeting_window(array $settings): array
{
    $today = meeting_now()->setTime(0, 0);
    return [$today->format('Y-m-d'), $today->modify('+' . $settings['ahead'] . ' days')->format('Y-m-d')];
}

/**
 * Times taken between two days (inclusive), as [start, end] timestamps:
 * every meeting holding its time, except $except (the meeting being moved).
 */
function meeting_busy(PDO $db, string $from_date, string $to_date, ?int $except = null): array
{
    $in   = implode(',', array_fill(0, count(MEETING_HOLDING), '?'));
    $stmt = $db->prepare("SELECT starts_at, ends_at FROM meetings WHERE status IN ({$in}) AND starts_at < ? + INTERVAL 2 DAY AND ends_at > ? - INTERVAL 1 DAY AND id <> ?");
    $stmt->execute(array_merge(MEETING_HOLDING, [$to_date, $from_date, (int)$except]));
    return array_map(fn($r) => [meeting_ts($r['starts_at']), meeting_ts($r['ends_at'])], $stmt->fetchAll());
}

/** Free start times ('H:i') on one day for a meeting of $minutes, given what is $busy. */
function meeting_slots(array $settings, int $minutes, string $date, array $busy): array
{
    if (!meeting_valid_date($date) || in_array($date, $settings['closed'], true)) {
        return [];
    }
    [$first, $last] = meeting_window($settings);
    if ($date < $first || $date > $last) {
        return [];
    }
    $tz    = meeting_tz();
    $day   = strtolower((new DateTimeImmutable($date, $tz))->format('D'));
    $hours = $settings['hours'][$day] ?? null;
    if (!$hours) {
        return [];
    }
    $close    = (new DateTimeImmutable($date . ' ' . $hours['close'], $tz))->getTimestamp();
    $earliest = meeting_now()->getTimestamp() + $settings['notice'] * 3600;
    $gap      = $settings['gap'] * 60;
    $out = [];
    for ($t = new DateTimeImmutable($date . ' ' . $hours['open'], $tz); ; $t = $t->modify('+' . $settings['step'] . ' minutes')) {
        $s = $t->getTimestamp();
        $e = $s + $minutes * 60;
        if ($e > $close || $t->format('Y-m-d') !== $date) break;
        if ($s < $earliest) continue;
        foreach ($busy as [$bs, $be]) {
            if ($s < $be + $gap && $e + $gap > $bs) continue 2;
        }
        $out[] = $t->format('H:i');
    }
    return $out;
}

/** Every day in the window with at least one free time: [Y-m-d => number of free times]. */
function meeting_open_days(PDO $db, array $settings, int $minutes, ?int $except = null): array
{
    [$first, $last] = meeting_window($settings);
    $busy = meeting_busy($db, $first, $last, $except);
    $days = [];
    for ($d = new DateTimeImmutable($first, meeting_tz()); $d->format('Y-m-d') <= $last; $d = $d->modify('+1 day')) {
        $n = count(meeting_slots($settings, $minutes, $d->format('Y-m-d'), $busy));
        if ($n) $days[$d->format('Y-m-d')] = $n;
    }
    return $days;
}

/** One booking at a time takes a time: two people can never get the same one. */
function meeting_lock(PDO $db): bool
{
    return (int)$db->query("SELECT GET_LOCK('nfelia_meetings', 8)")->fetchColumn() === 1;
}

function meeting_unlock(PDO $db): void
{
    try { $db->query("SELECT RELEASE_LOCK('nfelia_meetings')"); } catch (Throwable $e) {}
}

/**
 * Whether $date $time is free for $minutes, checked inside the lock:
 * [starts_at, ends_at] in database time, or a reason it isn't.
 */
function meeting_check(PDO $db, array $settings, int $minutes, string $date, string $time, ?int $except = null): array|string
{
    $time = meeting_clean_time($time);
    if (!meeting_valid_date($date) || !$time) {
        return 'Pick a day and a time.';
    }
    $busy = meeting_busy($db, $date, $date, $except);
    if (!in_array($time, meeting_slots($settings, $minutes, $date, $busy), true)) {
        return 'Someone has just taken that time, or it is no longer open. Pick another.';
    }
    $start = new DateTimeImmutable($date . ' ' . $time, meeting_tz());
    return [$start->format('Y-m-d H:i:s'), $start->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s')];
}

/* ═══════════════════════════════════════════════════════════════════
 *  BOOKING, MOVING
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Book $type at $date $time for the person in $f (name, email, phone,
 * business_name, note). Returns the meeting, or a reason it couldn't be booked.
 * The person, sales@ and Ops hear of it after the page has answered.
 */
function meeting_book(PDO $db, array $type, string $date, string $time, array $f): array|string
{
    $settings = meeting_settings($db);
    $minutes  = (int)$type['minutes'];
    if (!meeting_lock($db)) {
        return 'We are busy taking another booking. Try again in a moment.';
    }
    try {
        $when = meeting_check($db, $settings, $minutes, $date, $time);
        if (is_string($when)) {
            return $when;
        }
        $db->prepare("INSERT INTO meetings (meeting_type_id, type_name, minutes, starts_at, ends_at, status, name, email, phone, business_name, note, ip)
                      VALUES (?, ?, ?, ?, ?, 'booked', ?, ?, ?, ?, ?, ?)")
           ->execute([(int)$type['id'], $type['name'], $minutes, $when[0], $when[1], $f['name'], $f['email'], $f['phone'], $f['business_name'], $f['note'],
                      substr(function_exists('client_ip') ? client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
        $id = (int)$db->lastInsertId();
    } finally {
        meeting_unlock($db);
    }
    $m = meeting_find($db, $id);
    if ($lead = meeting_lead($db, $m)) {
        $db->prepare("UPDATE meetings SET lead_id = ? WHERE id = ?")->execute([$lead, $id]);
        $m['lead_id'] = $lead;
    }
    meeting_tell($m, 'booked');
    meeting_tell_team($db, $m, 'New meeting: ' . meeting_who($m),
        $m['type_name'] . ', ' . meeting_when($m) . '.' . ($m['note'] ? "\n\nThey wrote: " . $m['note'] : '')
        . "\n\nMake the Google Calendar event with them as a guest, then paste its Meet link in Ops to confirm.", 'booked');
    return $m;
}

/**
 * The person moves their meeting to $date $time (from the link Ops sent).
 * It needs a Meet link check again: Ops moves the calendar event and confirms.
 */
function meeting_move(PDO $db, array $m, string $date, string $time): array|string
{
    if (!meeting_lock($db)) {
        return 'We are busy taking another booking. Try again in a moment.';
    }
    try {
        $when = meeting_check($db, meeting_settings($db), (int)$m['minutes'], $date, $time, (int)$m['id']);
        if (is_string($when)) {
            return $when;
        }
        $db->prepare("UPDATE meetings SET starts_at = ?, ends_at = ?, status = 'booked', move_token_hash = NULL, reminded_day_at = NULL, reminded_hour_at = NULL WHERE id = ? AND status = 'reschedule'")
           ->execute([$when[0], $when[1], (int)$m['id']]);
    } finally {
        meeting_unlock($db);
    }
    $was = meeting_when($m);
    $m = meeting_find($db, (int)$m['id']);
    if ($m['lead_id']) {
        $db->prepare("UPDATE leads SET next_follow_up_at = ?, follow_up_reminded_at = NULL WHERE id = ? AND stage IN ('new', 'contacted', 'demo', 'trial')")
           ->execute([$m['starts_at'], (int)$m['lead_id']]);
        meeting_lead_note($db, $m, 'Picked a new time for "' . $m['type_name'] . '": ' . meeting_when($m) . ' (was ' . $was . ').');
    }
    meeting_tell($m, 'moved');
    meeting_tell_team($db, $m, 'New time picked: ' . meeting_who($m),
        $m['type_name'] . ' is now ' . meeting_when($m) . ' (was ' . $was . ")\n\nMove the Google Calendar event to the new time, then confirm it in Ops so they get the link again.", 'moved');
    return $m;
}

function meeting_find(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The meeting a "pick a new time" link is for, while it still waits on one. */
function meeting_by_move_token(PDO $db, string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{20,100}$/', $token)) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM meetings WHERE move_token_hash = ? AND status = 'reschedule'");
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

/** A new "pick a new time" link for a meeting (earlier ones stop working). Returns the token. */
function meeting_move_token(PDO $db, int $id): string
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $db->prepare("UPDATE meetings SET move_token_hash = ? WHERE id = ?")->execute([hash('sha256', $token), $id]);
    return $token;
}

/* ═══════════════════════════════════════════════════════════════════
 *  WORDS
 * ═══════════════════════════════════════════════════════════════════ */

/** "Tuesday 28 October, 10:00am to 10:30am (Lagos time)" */
function meeting_when(array $m): string
{
    $s = new DateTimeImmutable($m['starts_at'], meeting_tz());
    $e = new DateTimeImmutable($m['ends_at'], meeting_tz());
    return $s->format('l j F, g:ia') . ' to ' . $e->format('g:ia') . ' (Lagos time)';
}

/** "Ada Obi (Ada's Fabrics)" */
function meeting_who(array $m): string
{
    return $m['name'] . ($m['business_name'] ? ' (' . $m['business_name'] . ')' : '');
}

/** "10:00am" for a slot's "10:00". */
function meeting_time_text(string $hhmm): string
{
    return (new DateTimeImmutable('2000-01-01 ' . $hhmm))->format('g:ia');
}

function meeting_status_chip(string $status): string
{
    $s = MEETING_STATUSES[$status] ?? ['label' => $status, 'tone' => 'muted'];
    return '<span class="ops-chip ops-chip-' . $s['tone'] . '">' . e($s['label']) . '</span>';
}

/**
 * The Google Calendar event for a meeting, as Ops pastes it in (the copy
 * panel on the meeting's page) and as the guest sees it in their invite:
 *   title        "Nfelia demo with Ada Obi (Ada's Fabrics)"
 *   date         "Oct 27, 2026", start "10:00am", end "10:30am": the way
 *                Google Calendar writes them, so they paste straight in
 *   guest        their email
 *   description  the meeting, who booked, how to reach them, what they want to cover
 */
function meeting_event(array $m): array
{
    $s = new DateTimeImmutable($m['starts_at'], meeting_tz());
    $e = new DateTimeImmutable($m['ends_at'], meeting_tz());
    $type = (string)$m['type_name'];
    $lines = [
        $type . ' with the Nfelia team (' . meeting_length_text((int)$m['minutes']) . ', Lagos time).',
        '',
        'Booked by: ' . meeting_who($m),
        'Email: ' . $m['email'],
    ];
    if ($m['phone']) $lines[] = 'WhatsApp: ' . $m['phone'];
    if ($m['note']) {
        array_push($lines, '', 'What they want to cover:', $m['note']);
    }
    array_push($lines, '', 'We may record and transcribe this call so both of us have the notes.',
               'Questions or a change of plan: sales@nfelia.com');
    return [
        'title'       => (stripos($type, 'nfelia') === false ? 'Nfelia: ' : '') . $type . ' with ' . meeting_who($m),
        'date'        => $s->format('M j, Y'),
        'start'       => $s->format('g:ia'),
        'end'         => $e->format('g:ia'),
        'guest'       => (string)$m['email'],
        'description' => implode("\n", $lines),
    ];
}

/**
 * A Google Calendar page with the meeting filled in (title, time, the person
 * as a guest), so Ops only adds Google Meet, saves, and copies the link.
 * Google may drop what it fills in (another account signed in first, say):
 * the copy panel has every part to paste by hand.
 */
function meeting_google_calendar_url(array $m): string
{
    $fmt = fn(string $t) => (new DateTimeImmutable($t, meeting_tz()))->format('Ymd\THis');
    $ev  = meeting_event($m);
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'  => 'TEMPLATE',
        'text'    => $ev['title'],
        'dates'   => $fmt($m['starts_at']) . '/' . $fmt($m['ends_at']),
        'ctz'     => APP_TIMEZONE,
        'details' => $ev['description'],
        'add'     => $ev['guest'],
    ]);
}

/* ═══════════════════════════════════════════════════════════════════
 *  TELLING PEOPLE
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * An email to the person who booked (views/emails/meeting_update.php):
 *   booked, moved      we have your time; the Meet link follows
 *   confirmed          here is the Meet link
 *   reschedule         please pick a new time ($extra: move_url, message)
 *   cancelled          ($extra: message)
 *   reminder_day, reminder_hour
 */
function meeting_tell(array $m, string $state, array $extra = []): ?int
{
    $first = preg_split('/\s+/u', trim((string)$m['name']))[0] ?? '';
    $subject = match ($state) {
        'confirmed'     => 'Your Nfelia meeting is confirmed: ' . meeting_short_when($m),
        'reschedule'    => 'Please pick a new time for your Nfelia meeting',
        'cancelled'     => 'Your Nfelia meeting was cancelled',
        'reminder_day'  => 'Reminder: your Nfelia meeting, ' . meeting_short_when($m),
        'reminder_hour' => 'Starting soon: your Nfelia meeting at ' . (new DateTimeImmutable($m['starts_at'], meeting_tz()))->format('g:ia'),
        'moved'         => 'We have your new time: ' . meeting_short_when($m),
        default         => 'You\'re booked: ' . meeting_short_when($m),
    };
    // Once per state, time and link: confirming again with a new link (or after a new time) sends again.
    $key = 'meeting:' . $m['id'] . ':' . $state . ':' . md5($m['starts_at'] . '|' . ($m['meet_url'] ?? '') . '|' . ($extra['move_url'] ?? ''));
    return notify_email(null, (string)$m['email'], (string)$m['name'], $subject, 'meeting_update', [
        'state'      => $state,
        'first_name' => $first !== '' ? $first : 'there',
        'type_name'  => (string)$m['type_name'],
        'when'       => meeting_when($m),
        'length'     => meeting_length_text((int)$m['minutes']),
        'meet_url'   => (string)($m['meet_url'] ?? ''),
        'move_url'   => (string)($extra['move_url'] ?? ''),
        'message'    => (string)($extra['message'] ?? ''),
        'book_url'   => APP_URL . '/book',
    ], $key, 'meeting.' . $state);
}

/** "Tue 28 Oct, 10:00am" */
function meeting_short_when(array $m): string
{
    return (new DateTimeImmutable($m['starts_at'], meeting_tz()))->format('D j M, g:ia');
}

/** sales@ by email, and Sales in Ops (bell and phone). Never throws. */
function meeting_tell_team(PDO $db, array $m, string $title, string $text, string $what): void
{
    try {
        notify_email(null, meeting_team_email(), 'Nfelia Sales', $title, 'ops_message', [
            'headline' => $title,
            'message'  => $text . "\n\n" . meeting_who($m) . "\n" . $m['email'] . ($m['phone'] ? "\n" . $m['phone'] : ''),
            'button'   => 'Open it in Nfelia Ops',
            'url'      => ops_url('meetings/' . (int)$m['id']),
            'note'     => 'Sent to ' . meeting_team_email() . ' for every meeting booked at nfelia.com/book.',
        ], 'meeting-team:' . $m['id'] . ':' . $what . ':' . md5($m['starts_at']), 'ops.meeting');
        ops_alert($db, 'meeting.' . $what, $title, $m['type_name'] . ', ' . meeting_short_when($m), 'meetings/' . (int)$m['id'],
                  'meeting-' . $what . ':' . $m['id'] . ':' . md5($m['starts_at']), ['permission' => 'sales']);
    } catch (Throwable $e) {
        errors_report_throwable($e, ['job' => 'Meeting: telling the team']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  LEADS
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * The meeting as a lead at stage Demo, followed up at the meeting's time:
 * the open lead with the same email or phone, or a new one. Returns its id;
 * never throws (a booking never fails because of its lead).
 */
function meeting_lead(PDO $db, array $m): ?int
{
    try {
        $note = 'Booked "' . $m['type_name'] . '" at nfelia.com/book for ' . meeting_when($m) . ($m['note'] ? '. They wrote: ' . $m['note'] : '.');
        $about = mb_substr('Meeting: ' . $m['type_name'], 0, 255);
        if ($lead = ops_lead_match($db, $m['email'], $m['phone'])) {
            // stage_changed_at first: later assignments in one UPDATE see the new stage.
            $db->prepare("UPDATE leads SET stage_changed_at = IF(stage IN ('new', 'contacted'), NOW(), stage_changed_at), stage = IF(stage IN ('new', 'contacted'), 'demo', stage),
                                           email = COALESCE(email, ?), phone = COALESCE(phone, ?), business_name = COALESCE(business_name, ?),
                                           next_follow_up_at = ?, follow_up_note = ?, follow_up_reminded_at = NULL WHERE id = ?")
               ->execute([$m['email'], $m['phone'], $m['business_name'], $m['starts_at'], $about, $lead['id']]);
            $id = (int)$lead['id'];
        } else {
            $db->prepare("INSERT INTO leads (name, business_name, email, phone, source, stage, next_follow_up_at, follow_up_note) VALUES (?, ?, ?, ?, 'booking', 'demo', ?, ?)")
               ->execute([mb_substr($m['name'], 0, 120), $m['business_name'] ? mb_substr($m['business_name'], 0, 160) : null, $m['email'], $m['phone'], $m['starts_at'], $about]);
            $id = (int)$db->lastInsertId();
        }
        ops_lead_note($db, $id, null, $note);
        return $id;
    } catch (Throwable $e) {
        errors_report_throwable($e, ['job' => 'Meeting: lead']);
        return null;
    }
}

/** A note on the meeting's lead, if it has one. Never throws. */
function meeting_lead_note(PDO $db, array $m, string $body, ?int $staff_id = null): void
{
    if (!$m['lead_id']) return;
    try {
        ops_lead_note($db, (int)$m['lead_id'], $staff_id, $body);
    } catch (Throwable $e) {
        error_log('Nfelia meeting_lead_note: ' . $e->getMessage());
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE CRON: REMINDERS
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Every five minutes (cron/planner.php):
 *   - a day before a confirmed meeting, a reminder with the Meet link, unless
 *     it was confirmed less than 20 hours before (the confirmation is fresh)
 *   - an hour before, another, unless it was confirmed in the last 2 hours
 *   - Sales is told when a meeting starts within 3 hours and still has no link
 * Each is stamped before it is sent, so a failure never repeats it.
 */
function meetings_cron(PDO $db, DateTimeImmutable $now): array
{
    $done = ['meeting_reminders' => 0, 'meeting_no_link' => 0];

    $day = $db->query("SELECT * FROM meetings WHERE status = 'confirmed' AND reminded_day_at IS NULL
                       AND starts_at <= NOW() + INTERVAL 24 HOUR LIMIT 100")->fetchAll();
    foreach ($day as $m) {
        $db->prepare("UPDATE meetings SET reminded_day_at = NOW() WHERE id = ?")->execute([$m['id']]);
        $starts = meeting_ts($m['starts_at']);
        $fresh  = $m['confirmed_at'] && meeting_ts($m['confirmed_at']) > $starts - 20 * 3600;
        if ($starts > $now->getTimestamp() + 3600 && !$fresh && meeting_tell($m, 'reminder_day')) {
            $done['meeting_reminders']++;
        }
    }

    $hour = $db->query("SELECT * FROM meetings WHERE status = 'confirmed' AND reminded_hour_at IS NULL
                        AND starts_at <= NOW() + INTERVAL 65 MINUTE LIMIT 100")->fetchAll();
    foreach ($hour as $m) {
        $db->prepare("UPDATE meetings SET reminded_hour_at = NOW() WHERE id = ?")->execute([$m['id']]);
        $starts = meeting_ts($m['starts_at']);
        $fresh  = $m['confirmed_at'] && meeting_ts($m['confirmed_at']) > $starts - 2 * 3600;
        if ($starts > $now->getTimestamp() && !$fresh && meeting_tell($m, 'reminder_hour')) {
            $done['meeting_reminders']++;
        }
    }

    $bare = $db->query("SELECT * FROM meetings WHERE status = 'booked' AND starts_at > NOW() AND starts_at <= NOW() + INTERVAL 3 HOUR LIMIT 50")->fetchAll();
    foreach ($bare as $m) {
        $done['meeting_no_link'] += ops_alert($db, 'meeting.no_link', 'No Meet link yet: ' . meeting_who($m),
            $m['type_name'] . ' starts ' . meeting_short_when($m) . '. Paste the link so they can join.',
            'meetings/' . (int)$m['id'], 'meeting-no-link:' . $m['id'] . ':' . md5($m['starts_at']), ['permission' => 'sales']) ? 1 : 0;
    }
    return $done;
}
