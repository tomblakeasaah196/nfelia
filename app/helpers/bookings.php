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
const BOOKING_HOLD_HOURS = 24;   // an unconfirmed request holds its time this long (Tom, October 2026)
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
 *   rules     house rules for stays, a line each (up to 8), shown before
 *             paying and on Haven's first screen
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
        'rules'    => booking_clean_rules($raw['rules'] ?? []),
    ];
}

/**
 * House rules: a list or text with a line each, each line short and once,
 * up to eight ("No parties", "Check-in with a photo ID").
 */
function booking_clean_rules(mixed $value): array {
    $lines = is_array($value) ? $value : preg_split('/\R/u', (string)$value);
    $rules = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/^[\s\-*•·\d.)]+/u', '', preg_replace('/\s+/u', ' ', is_string($line) ? $line : '')));
        if ($line !== '' && !in_array(mb_strtolower($line), array_map('mb_strtolower', $rules), true)) {
            $rules[] = mb_substr($line, 0, 120);
        }
        if (count($rules) >= 8) {
            break;
        }
    }
    return $rules;
}

function booking_clean_time(mixed $value): ?string {
    return is_string($value) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value) ? $value : null;
}

/**
 * How a product is booked, or null when it is bought:
 *   mode, minutes (slots), capacity (days), deposit_type, deposit_value,
 *   and for stays: caution (a refundable fee paid with the booking, 0 for
 *   none) and rates (cheaper for longer stays: [[from nights, price a
 *   night], …], each cheaper than the last and than the nightly price).
 */
function booking_info(array $product): ?array {
    $mode = (string)($product['booking_mode'] ?? 'none');
    if (!in_array($mode, ['slot', 'nights', 'day'], true)) {
        return null;
    }
    $stay = $mode === 'nights';
    return [
        'mode'          => $mode,
        'minutes'       => $mode === 'slot' ? max(15, min(720, (int)($product['booking_minutes'] ?? 60) ?: 60)) : 0,
        'capacity'      => max(1, (int)($product['booking_capacity'] ?? 1)),
        'deposit_type'  => in_array($product['deposit_type'] ?? '', ['percent', 'fixed'], true) ? $product['deposit_type'] : 'none',
        'deposit_value' => max(0, (float)($product['deposit_value'] ?? 0)),
        'caution'       => $stay ? max(0.0, round((float)($product['caution_fee'] ?? 0), 2)) : 0.0,
        'rates'         => $stay ? booking_clean_rates($product['stay_rates'] ?? null, (float)($product['sell_price'] ?? 0)) : [],
    ];
}

/**
 * Cheaper for longer stays, from the JSON in products.stay_rates (or the
 * product form's rows): up to three [from nights, price a night], from 2 to
 * BOOKING_MAX_NIGHTS nights, fewest first. As a ladder (the default), each
 * is cheaper than the one before and, when the nightly price is known, than
 * it: a step that isn't is dropped, so the price a stay shows is the price
 * it pays. The product form keeps the owner's steps as typed ($ladder
 * false), so lowering the nightly price later never loses them.
 */
function booking_clean_rates(mixed $value, float $price = 0.0, bool $ladder = true): array {
    $rows = is_string($value) ? json_decode($value, true) : $value;
    $by_from = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $from = is_array($row) ? filter_var($row[0] ?? $row['from'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => BOOKING_MAX_NIGHTS]]) : false;
        $each = is_array($row) && is_numeric($row[1] ?? $row['price'] ?? null) ? round((float)($row[1] ?? $row['price']), 2) : 0.0;
        if ($from !== false && $each > 0 && $each <= 1000000000 && !isset($by_from[$from])) {
            $by_from[$from] = $each;
        }
    }
    ksort($by_from);
    $rates = [];
    $below = $price > 0 ? $price : PHP_FLOAT_MAX;
    foreach ($by_from as $from => $each) {
        if ((!$ladder || $each < $below) && count($rates) < 3) {
            $rates[] = [$from, $each];
            $below = $each;
        }
    }
    return $rates;
}

/**
 * What a night costs on a stay this long: the cheapest step it reaches
 * (sf_price_at), never more than the nightly price. As nightPrice() in
 * storefront.js; place_booking charges this.
 */
function booking_night_price(array $info, float $price, int $nights): float {
    return $info['mode'] === 'nights' ? sf_price_at($price, $info['rates'], $nights) : $price;
}

/**
 * The deposit for a booking of this total: a share of it, a fixed amount
 * (never more than a priced total; all of it when the price is 0, as the
 * product page says), or nothing.
 */
function booking_deposit(array $info, float $total): float {
    return match ($info['deposit_type']) {
        'percent' => round($total * min(100, $info['deposit_value']) / 100, 2),
        'fixed'   => round($total > 0 ? min($info['deposit_value'], $total) : $info['deposit_value'], 2),
        default   => 0.0,
    };
}

/* ──────────────────────────────────────────
 *  SEVERAL SERVICES IN ONE APPOINTMENT
 *  A time-slot booking can take other time-slot services with it (Acrylic
 *  and Gel toes, one visit). It stays one booking: one time, as long as
 *  the services added up (one after another, so one person is never booked
 *  twice), one total, and the highest of their deposits (one deposit, as
 *  the salon that asked for it wanted). Each service is a booking_items row.
 * ────────────────────────────────────────── */

const BOOKING_MAX_EXTRAS = 4;      // up to 5 services in one appointment
const BOOKING_MAX_MINUTES = 720;   // as long as a single service can be

/**
 * What can be added to a booking of $item: the shop's other time-slot
 * services, in the shop's order. [] for stays and whole days.
 */
function booking_extras(array $catalogue, array $item): array {
    if (($item['booking']['mode'] ?? '') !== 'slot') {
        return [];
    }
    return array_values(array_filter($catalogue['bookable'], fn($i) => $i['id'] !== $item['id'] && $i['booking']['mode'] === 'slot'));
}

/**
 * The ids a shopper added ("12,14", [12, 14] or one id), each once, at
 * most BOOKING_MAX_EXTRAS, without $main_id.
 */
function booking_parse_extras(mixed $raw, int $main_id): array {
    $ids = is_array($raw) ? $raw : explode(',', (string)$raw);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0 && $id !== $main_id)));
    return array_slice($ids, 0, BOOKING_MAX_EXTRAS);
}

/**
 * One appointment for $item and the services added to it ($extra_ids, from
 * booking_extras). Returns the booking info to check times with (its
 * minutes added up), the price and deposit to charge, the name it goes by,
 * and its lines; null when an added service can't be booked any more.
 *   ['info', 'price', 'deposit', 'name', 'items' => [[id, name, price, minutes, deposit], …]]
 * The deposit is worked out on the prices; booking_deposit() is not asked
 * again on the total.
 */
function booking_combine(array $catalogue, array $item, array $extra_ids): ?array {
    $can = array_column(booking_extras($catalogue, $item), null, 'id');
    $services = [$item];
    foreach ($extra_ids as $id) {
        if (!isset($can[$id])) {
            return null;
        }
        $services[] = $can[$id];
    }
    $items = array_map(fn($s) => [
        'id'      => (int)$s['id'],
        'name'    => (string)$s['name'],
        'price'   => (float)$s['price'],
        'minutes' => (int)$s['booking']['minutes'],
        'deposit' => booking_deposit($s['booking'], (float)$s['price']),
    ], $services);
    $info = $item['booking'];
    $info['minutes'] = min(BOOKING_MAX_MINUTES, array_sum(array_column($items, 'minutes')));
    return [
        'info'    => $info,
        'price'   => round(array_sum(array_column($items, 'price')), 2),
        'deposit' => max(array_column($items, 'deposit')),
        'name'    => mb_substr(implode(' + ', array_column($items, 'name')), 0, 255),
        'items'   => $items,
    ];
}

/**
 * A booking's services, for the pages that show it: its booking_items, or
 * the one service it was booked for.
 *   [['name', 'price', 'minutes'], …]
 */
function booking_lines(PDO $db, array $booking): array {
    $stmt = $db->prepare("SELECT product_name AS name, unit_price AS price, minutes FROM booking_items WHERE booking_id = ? ORDER BY sort_order, id");
    $stmt->execute([(int)$booking['id']]);
    $rows = $stmt->fetchAll();
    return $rows ?: [['name' => (string)$booking['product_name'], 'price' => (float)$booking['unit_price'], 'minutes' => 0]];
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
    if (($info['caution'] ?? 0) > 0) {
        $bits[] = sf_money($info['caution'], $symbol) . ' caution fee, refunded after';
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
 * SQL for "this booking takes up its time": confirmed, or a request the owner
 * hasn't answered yet that is less than BOOKING_HOLD_HOURS old. Older
 * requests stop blocking the calendar, so nobody can fill a shop's diary with
 * requests that are never meant.
 */
function booking_holding_sql(string $alias = ''): string {
    $a = $alias !== '' ? $alias . '.' : '';
    return "({$a}status = 'confirmed' OR ({$a}status = 'pending' AND {$a}created_at > NOW() - INTERVAL " . BOOKING_HOLD_HOURS . " HOUR))";
}

/**
 * Does a booking's time clash with the shop's other bookings and
 * appointments? Asked before confirming a request that no longer holds its
 * time, in case someone else has taken it since.
 */
function booking_clashes(PDO $db, array $booking): bool {
    $id = (int)$booking['id'];
    if ($booking['kind'] === 'nights') {
        return (bool)booking_taken_nights($db, (int)$booking['product_id'], substr($booking['starts_at'], 0, 10),
            planner_add_days(substr($booking['ends_at'], 0, 10), -1), $id);
    }
    if ($booking['kind'] === 'day') {
        $date = substr($booking['starts_at'], 0, 10);
        $stmt = $db->prepare("SELECT booking_capacity FROM products WHERE id = ?");
        $stmt->execute([(int)$booking['product_id']]);
        return (booking_day_counts($db, (int)$booking['product_id'], $date, $date, $id)[$date] ?? 0) >= max(1, (int)$stmt->fetchColumn());
    }
    $tz    = planner_tz();
    $start = new DateTimeImmutable($booking['starts_at'], $tz);
    $end   = new DateTimeImmutable($booking['ends_at'], $tz);
    foreach (booking_busy_slots($db, (int)$booking['business_id'], $booking['starts_at'], $booking['ends_at'], $id) as [$s, $e]) {
        if ($s < $end && $e > $start) {
            return true;
        }
    }
    return false;
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
        WHERE business_id = ? AND kind = 'slot' AND " . booking_holding_sql() . "
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
        WHERE product_id = ? AND kind = 'nights' AND " . booking_holding_sql() . "
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
        WHERE product_id = ? AND kind = 'day' AND " . booking_holding_sql() . "
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
