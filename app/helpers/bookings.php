<?php
/**
 * Nfelia — bookings with deposits
 *
 * A product can be booked instead of bought (products.booking_mode):
 *   slot    a time on a day, lasting booking_minutes (braids, a fitting, a repair)
 *   nights  a stay from one date to another, priced per night (shortlets)
 *   day     a whole day (an event package), booking_capacity per day
 *
 * Free times come from the shop's opening hours (businesses.booking_settings)
 * less what is already taken: time slots are shared by the whole shop
 * ("at once" people at a time) and also skip the owner's own appointments in
 * Tasks & Calendar; nights and days are per product. A request holds its time
 * while the owner checks the deposit; confirming it puts it on the calendar
 * (a tasks appointment), declining frees it.
 *
 * Times are the shop's local time (planner_tz), like the calendar.
 */

const BOOKING_DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
                      'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
const BOOKING_MODES = ['none', 'slot', 'nights', 'day'];
const BOOKING_HOLDING = ['pending', 'confirmed'];   // statuses that take up a time
const BOOKING_MAX_NIGHTS = 30;

/* ──────────────────────────────────────────
 *  SETTINGS
 * ────────────────────────────────────────── */

/**
 * A shop's booking settings, cleaned, with defaults:
 *   hours     per day ['open' => '09:00', 'close' => '18:00'], or null when closed
 *   notice    hours ahead a booking must be made
 *   ahead     days ahead people can book
 *   step      minutes between start times
 *   at_once   how many time-slot bookings can overlap
 *   checkin / checkout  times for stays
 */
function booking_settings(array $business): array {
    $raw = json_decode((string)($business['booking_settings'] ?? ''), true);
    return booking_clean_settings(is_array($raw) ? $raw : []);
}

function booking_clean_settings(array $raw): array {
    $defaults = ['mon' => ['09:00', '18:00'], 'tue' => ['09:00', '18:00'], 'wed' => ['09:00', '18:00'], 'thu' => ['09:00', '18:00'],
                 'fri' => ['09:00', '18:00'], 'sat' => ['10:00', '16:00'], 'sun' => null];
    $hours = [];
    foreach (BOOKING_DAYS as $key => $_) {
        if (array_key_exists('hours', $raw) && is_array($raw['hours'])) {
            $day = $raw['hours'][$key] ?? null;
            $open  = is_array($day) ? booking_clean_time($day['open'] ?? null) : null;
            $close = is_array($day) ? booking_clean_time($day['close'] ?? null) : null;
            $hours[$key] = ($open && $close && $open < $close) ? ['open' => $open, 'close' => $close] : null;
        } else {
            $hours[$key] = $defaults[$key] ? ['open' => $defaults[$key][0], 'close' => $defaults[$key][1]] : null;
        }
    }
    $pick = fn($value, array $allowed, $default) => in_array((int)$value, $allowed, true) ? (int)$value : $default;
    return [
        'hours'    => $hours,
        'notice'   => $pick($raw['notice'] ?? null, [0, 1, 2, 4, 12, 24, 48, 72], 12),
        'ahead'    => $pick($raw['ahead'] ?? null, [7, 14, 30, 60, 90, 180, 365], 60),
        'step'     => $pick($raw['step'] ?? null, [15, 30, 60], 30),
        'at_once'  => max(1, min(20, (int)($raw['at_once'] ?? 1))),
        'checkin'  => booking_clean_time($raw['checkin'] ?? null) ?? '14:00',
        'checkout' => booking_clean_time($raw['checkout'] ?? null) ?? '12:00',
    ];
}

function booking_clean_time(mixed $value): ?string {
    return is_string($value) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value) ? $value : null;
}

/**
 * How a product is booked, or null when it is bought:
 *   mode, minutes (slots), capacity (days), deposit_type, deposit_value
 */
function booking_info(array $product): ?array {
    $mode = (string)($product['booking_mode'] ?? 'none');
    if (!in_array($mode, ['slot', 'nights', 'day'], true)) {
        return null;
    }
    return [
        'mode'          => $mode,
        'minutes'       => $mode === 'slot' ? max(15, min(720, (int)($product['booking_minutes'] ?? 60) ?: 60)) : 0,
        'capacity'      => max(1, (int)($product['booking_capacity'] ?? 1)),
        'deposit_type'  => in_array($product['deposit_type'] ?? '', ['percent', 'fixed'], true) ? $product['deposit_type'] : 'none',
        'deposit_value' => max(0, (float)($product['deposit_value'] ?? 0)),
    ];
}

/**
 * The deposit for a booking of this total: a share of it, a fixed amount
 * (never more than the total), or nothing.
 */
function booking_deposit(array $info, float $total): float {
    return match ($info['deposit_type']) {
        'percent' => round($total * min(100, $info['deposit_value']) / 100, 2),
        'fixed'   => round(min($info['deposit_value'], $total), 2),
        default   => 0.0,
    };
}

/**
 * "2 hours", "45 minutes", "1 hour 30 minutes".
 */
function booking_duration_text(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $parts = [];
    if ($h) {
        $parts[] = $h . ' hour' . ($h === 1 ? '' : 's');
    }
    if ($m) {
        $parts[] = $m . ' minutes';
    }
    return implode(' ', $parts) ?: '0 minutes';
}

/**
 * One line about how a product is booked, for cards and product pages:
 * "2 hours · ₦5,000 deposit to book", "Whole day · No deposit". Stays say
 * only the deposit: their price already reads "a night".
 */
function booking_summary(array $info, float $price, string $symbol): string {
    $bits = match ($info['mode']) {
        'slot'   => [booking_duration_text($info['minutes'])],
        'day'    => ['Whole day'],
        default  => [],
    };
    if ($info['deposit_type'] === 'percent' && $info['deposit_value'] > 0) {
        $bits[] = rtrim(rtrim(number_format(min(100, $info['deposit_value']), 1), '0'), '.') . '% deposit to book';
    } elseif ($info['deposit_type'] === 'fixed' && $info['deposit_value'] > 0) {
        $fixed = $info['mode'] === 'nights' || $price <= 0 ? $info['deposit_value'] : min($info['deposit_value'], $price);
        $bits[] = sf_money($fixed, $symbol) . ' deposit to book';
    } else {
        $bits[] = 'No deposit';
    }
    return implode(' · ', $bits);
}

/* ──────────────────────────────────────────
 *  FREE TIMES
 * ────────────────────────────────────────── */

/**
 * The first and last dates people can book now.
 */
function booking_window(array $settings): array {
    $now   = planner_now();
    $first = $now->modify('+' . $settings['notice'] . ' hours')->format('Y-m-d');
    $last  = $now->modify('+' . $settings['ahead'] . ' days')->format('Y-m-d');
    return [$first, $last];
}

function booking_valid_date(?string $date): bool {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, planner_tz());
    return $d && $d->format('Y-m-d') === $date;
}

function booking_weekday(string $date): string {
    return strtolower((new DateTimeImmutable($date, planner_tz()))->format('D'));
}

/**
 * Time-slot bookings and the owner's timed appointments between two
 * moments, as [start, end] DateTimeImmutable pairs. Appointments that came
 * from a booking are counted once, as the booking.
 */
function booking_busy_slots(PDO $db, int $business_id, string $from, string $to, ?int $except = null): array {
    $tz = planner_tz();
    $out = [];
    $stmt = $db->prepare("
        SELECT starts_at, ends_at FROM bookings
        WHERE business_id = ? AND kind = 'slot' AND status IN ('pending', 'confirmed')
          AND starts_at < ? AND ends_at > ? AND id <> ?
    ");
    $stmt->execute([$business_id, $to, $from, (int)$except]);
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [new DateTimeImmutable($row['starts_at'], $tz), new DateTimeImmutable($row['ends_at'], $tz)];
    }
    $stmt = $db->prepare("
        SELECT t.due_date, t.due_time, t.end_time FROM tasks t
        WHERE t.business_id = ? AND t.kind = 'appointment' AND t.deleted_at IS NULL AND t.done_at IS NULL
          AND t.due_time IS NOT NULL AND t.due_date BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.task_id = t.id)
    ");
    $stmt->execute([$business_id, substr($from, 0, 10), substr($to, 0, 10)]);
    foreach ($stmt->fetchAll() as $row) {
        $start = new DateTimeImmutable($row['due_date'] . ' ' . $row['due_time'], $tz);
        $end   = $row['end_time'] && $row['end_time'] > $row['due_time']
            ? new DateTimeImmutable($row['due_date'] . ' ' . $row['end_time'], $tz)
            : $start->modify('+60 minutes');
        $out[] = [$start, $end];
    }
    return $out;
}

/**
 * Free start times ("09:30") for a time-slot product on a date.
 */
function booking_slots(PDO $db, array $business, array $info, string $date, ?array $busy = null): array {
    $settings = booking_settings($business);
    [$first, $last] = booking_window($settings);
    if (!booking_valid_date($date) || $date < $first || $date > $last) {
        return [];
    }
    $hours = $settings['hours'][booking_weekday($date)] ?? null;
    if (!$hours) {
        return [];
    }
    $tz       = planner_tz();
    $earliest = planner_now()->modify('+' . $settings['notice'] . ' hours');
    $close    = new DateTimeImmutable($date . ' ' . $hours['close'], $tz);
    $busy   ??= booking_busy_slots($db, (int)$business['id'], $date . ' 00:00:00', $date . ' 23:59:59');
    $out = [];
    for ($t = new DateTimeImmutable($date . ' ' . $hours['open'], $tz); ; $t = $t->modify('+' . $settings['step'] . ' minutes')) {
        $end = $t->modify('+' . $info['minutes'] . ' minutes');
        if ($end > $close) {
            break;
        }
        if ($t < $earliest) {
            continue;
        }
        $overlap = 0;
        foreach ($busy as [$bs, $be]) {
            if ($bs < $end && $be > $t) {
                $overlap++;
            }
        }
        if ($overlap < $settings['at_once']) {
            $out[] = $t->format('H:i');
        }
    }
    return $out;
}

/**
 * Nights (as the dates they start) already taken for a stay product, from
 * $from to $to inclusive: ['2026-10-20' => true, …].
 */
function booking_taken_nights(PDO $db, int $product_id, string $from, string $to, ?int $except = null): array {
    $stmt = $db->prepare("
        SELECT starts_at, ends_at FROM bookings
        WHERE product_id = ? AND kind = 'nights' AND status IN ('pending', 'confirmed')
          AND starts_at < ? AND ends_at > ? AND id <> ?
    ");
    $stmt->execute([$product_id, $to . ' 23:59:59', $from . ' 00:00:00', (int)$except]);
    $taken = [];
    foreach ($stmt->fetchAll() as $row) {
        for ($d = substr($row['starts_at'], 0, 10); $d < substr($row['ends_at'], 0, 10); $d = planner_add_days($d, 1)) {
            if ($d >= $from && $d <= $to) {
                $taken[$d] = true;
            }
        }
    }
    return $taken;
}

/**
 * How many bookings a whole-day product already has on each date.
 */
function booking_day_counts(PDO $db, int $product_id, string $from, string $to, ?int $except = null): array {
    $stmt = $db->prepare("
        SELECT DATE(starts_at) AS d, COUNT(*) AS n FROM bookings
        WHERE product_id = ? AND kind = 'day' AND status IN ('pending', 'confirmed')
          AND starts_at BETWEEN ? AND ? AND id <> ?
        GROUP BY DATE(starts_at)
    ");
    $stmt->execute([$product_id, $from . ' 00:00:00', $to . ' 23:59:59', (int)$except]);
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['d']] = (int)$row['n'];
    }
    return $counts;
}

/**
 * Each date of a month for the booking calendar: 'open', 'full' (all
 * taken) or 'closed' (outside the window, or the shop is shut). For stays a
 * date is 'full' when its night is taken; it can still be a check-out day.
 */
function booking_month(PDO $db, array $business, array $product, array $info, string $month): array {
    $settings = booking_settings($business);
    [$first, $last] = booking_window($settings);
    $start = $month . '-01';
    if (!booking_valid_date($start)) {
        return [];
    }
    $end = (new DateTimeImmutable($start, planner_tz()))->modify('last day of this month')->format('Y-m-d');

    $taken  = $info['mode'] === 'nights' ? booking_taken_nights($db, (int)$product['id'], $start, $end) : [];
    $counts = $info['mode'] === 'day' ? booking_day_counts($db, (int)$product['id'], $start, $end) : [];
    $busy   = $info['mode'] === 'slot' ? booking_busy_slots($db, (int)$business['id'], $start . ' 00:00:00', $end . ' 23:59:59') : [];
    $today  = planner_today();

    $days = [];
    for ($d = $start; $d <= $end; $d = planner_add_days($d, 1)) {
        $in_window = $info['mode'] === 'nights' ? ($d >= max($first, $today) && $d <= $last) : ($d >= $first && $d <= $last);
        if (!$in_window) {
            $days[$d] = 'closed';
            continue;
        }
        if ($info['mode'] === 'nights') {
            $days[$d] = isset($taken[$d]) ? 'full' : 'open';
        } elseif ($info['mode'] === 'day') {
            $open = $settings['hours'][booking_weekday($d)] ?? null;
            $days[$d] = !$open ? 'closed' : (($counts[$d] ?? 0) >= $info['capacity'] ? 'full' : 'open');
        } else {
            $open = $settings['hours'][booking_weekday($d)] ?? null;
            // No time left: 'full' when bookings took it, 'closed' when the day is over
            $days[$d] = !$open ? 'closed'
                : (booking_slots($db, $business, $info, $d, $busy) ? 'open' : (booking_slots($db, $business, $info, $d, []) ? 'full' : 'closed'));
        }
    }
    return $days;
}

/**
 * Check a requested time and work out its start, end, nights and price, or
 * say what's wrong. $request: date and time (slot), date (day), or
 * checkin and checkout (nights).
 * Returns ['ok' => true, 'starts_at', 'ends_at', 'units'] or ['ok' => false, 'error'].
 */
function booking_check(PDO $db, array $business, array $product, array $info, array $request): array {
    $settings = booking_settings($business);
    [$first, $last] = booking_window($settings);
    $tz = planner_tz();

    if ($info['mode'] === 'nights') {
        $in  = (string)($request['checkin'] ?? '');
        $out = (string)($request['checkout'] ?? '');
        if (!booking_valid_date($in) || !booking_valid_date($out) || $out <= $in) {
            return ['ok' => false, 'error' => 'Pick the day you arrive and the day you leave.'];
        }
        $nights = planner_days_between($in, $out);
        if ($in < planner_today() || $in < $first || $in > $last) {
            return ['ok' => false, 'error' => 'That date can\'t be booked. Pick another one.'];
        }
        if ($nights > BOOKING_MAX_NIGHTS) {
            return ['ok' => false, 'error' => 'Stays can be up to ' . BOOKING_MAX_NIGHTS . ' nights. For longer, ask on WhatsApp.'];
        }
        if (booking_taken_nights($db, (int)$product['id'], $in, planner_add_days($out, -1))) {
            return ['ok' => false, 'error' => 'Some of those nights were just booked. Pick other dates.'];
        }
        return ['ok' => true, 'starts_at' => $in . ' ' . $settings['checkin'] . ':00', 'ends_at' => $out . ' ' . $settings['checkout'] . ':00', 'units' => $nights];
    }

    $date = (string)($request['date'] ?? '');
    if (!booking_valid_date($date) || $date < $first || $date > $last) {
        return ['ok' => false, 'error' => 'That day can\'t be booked. Pick another one.'];
    }
    $hours = $settings['hours'][booking_weekday($date)] ?? null;
    if (!$hours) {
        return ['ok' => false, 'error' => $business['name'] . ' is closed that day. Pick another one.'];
    }

    if ($info['mode'] === 'day') {
        if ((booking_day_counts($db, (int)$product['id'], $date, $date)[$date] ?? 0) >= $info['capacity']) {
            return ['ok' => false, 'error' => 'That day was just booked. Pick another one.'];
        }
        return ['ok' => true, 'starts_at' => $date . ' ' . $hours['open'] . ':00', 'ends_at' => $date . ' ' . $hours['close'] . ':00', 'units' => 1];
    }

    $time = booking_clean_time($request['time'] ?? null);
    if ($time === null || !in_array($time, booking_slots($db, $business, $info, $date), true)) {
        return ['ok' => false, 'error' => 'That time was just taken. Pick another one.'];
    }
    $start = new DateTimeImmutable($date . ' ' . $time, $tz);
    return ['ok' => true, 'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $start->modify('+' . $info['minutes'] . ' minutes')->format('Y-m-d H:i:s'), 'units' => 1];
}

/* ──────────────────────────────────────────
 *  WORDS, LINKS, CALENDAR FILE
 * ────────────────────────────────────────── */

function booking_time_text(string $hhmm): string {
    return strtolower(date('g:ia', strtotime('2000-01-01 ' . $hhmm)));
}

/**
 * When a booking is, in words: "Saturday 18 October, 10:00am to 11:00am",
 * "Friday 24 October to Sunday 26 October, 2 nights", "Saturday 1 November".
 */
function booking_when(array $booking): string {
    $s = strtotime((string)$booking['starts_at']);
    $e = strtotime((string)$booking['ends_at']);
    return match ($booking['kind']) {
        'nights' => date('l j F', $s) . ' to ' . date('l j F', $e) . ', ' . (int)$booking['units'] . ' night' . ((int)$booking['units'] === 1 ? '' : 's'),
        'day'    => date('l j F Y', $s),
        default  => date('l j F', $s) . ', ' . strtolower(date('g:ia', $s)) . ' to ' . strtolower(date('g:ia', $e)),
    };
}

function booking_url(array $business, array $booking): ?string {
    return empty($business['subdomain']) ? null : shop_url($business['subdomain'], '/booking/' . $booking['public_token']);
}

function booking_page_path(array $item): string {
    return '/book/' . (int)$item['id'] . '-' . sf_product_slug((string)$item['name']);
}

/**
 * The booking as a calendar file (.ics) the shopper can add to their phone.
 */
function booking_ics(array $booking, array $business): string {
    $tz  = planner_tz();
    $utc = new DateTimeZone('UTC');
    $fmt = fn(string $at) => (new DateTimeImmutable($at, $tz))->setTimezone($utc)->format('Ymd\THis\Z');
    $esc = fn(string $text) => str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $text);
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Nfelia//Bookings//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $booking['public_token'] . '@nfelia',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    ];
    if ($booking['kind'] === 'day') {
        $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime((string)$booking['starts_at']));
        $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime((string)$booking['starts_at'] . ' +1 day'));
    } else {
        $lines[] = 'DTSTART:' . $fmt((string)$booking['starts_at']);
        $lines[] = 'DTEND:' . $fmt((string)$booking['ends_at']);
    }
    $lines[] = 'SUMMARY:' . $esc($booking['product_name'] . ' — ' . $business['name']);
    if (!empty($business['address'])) {
        $lines[] = 'LOCATION:' . $esc(trim((string)$business['address']));
    }
    $url = booking_url($business, $booking);
    $lines[] = 'DESCRIPTION:' . $esc('Booking ' . $booking['booking_number'] . ($url ? '. Details: ' . $url : ''));
    if ($url) {
        $lines[] = 'URL:' . $url;
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';
    // Lines over 75 octets are folded, as the format asks
    return implode("\r\n", array_map(fn($l) => rtrim(chunk_split($l, 74, "\r\n "), "\r\n "), $lines)) . "\r\n";
}
