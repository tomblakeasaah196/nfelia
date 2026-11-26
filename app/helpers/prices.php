<?php
/**
 * Everything Nfelia charges, per country, and when a new price starts.
 *
 *   month   a month of Nfelia
 *   seat    each extra person, a month
 *   book    one Records Book (app/helpers/records.php)
 *
 * countries.monthly_price, extra_seat_price and book_price are a country's
 * prices before any change. A change (Ops → Prices, the price_changes table)
 * gives new prices from two dates:
 *   new_from       new sign-ups, and the website, pay it from then
 *   existing_from  businesses that joined before new_from pay it for what
 *                  starts on or after that day. A month that starts before
 *                  it keeps the old price (Tom: 30 days' notice by default),
 *                  so paying early holds today's price for the month already
 *                  running and the next, never more.
 * The newest change that applies wins. prices_for() is the one answer:
 * billing_prices() (Billing, Paystack, Mobile Money, Ops) and the website
 * ask it, so a price changed in Ops is the price everywhere.
 *
 * Telling clients: Ops → Prices → Tell clients lists who it reaches
 * (price_notices) and the cron hands them to the outbox a few at a time,
 * like campaigns. It is a billing email, so it goes to people who
 * unsubscribed from campaigns too.
 */

require_once __DIR__ . '/money.php';

const PRICE_ITEMS = [
    'month' => ['label' => 'A month of Nfelia', 'short' => 'a month',            'column' => 'monthly_price',    'change' => 'month_price', 'old' => 'old_month'],
    'seat'  => ['label' => 'Each extra person', 'short' => 'each extra person',  'column' => 'extra_seat_price', 'change' => 'seat_price',  'old' => 'old_seat'],
    'book'  => ['label' => 'A Records Book',    'short' => 'a Records Book',     'column' => 'book_price',       'change' => 'book_price',  'old' => 'old_book'],
];

/** Before a country has its own: Nigeria's launch prices. */
function prices_fallback(string $item): float
{
    return match ($item) {
        'month' => PLAN_BASE_PRICE / 100,
        'seat'  => PLAN_EXTRA_SEAT_PRICE / 100,
        default => 7500.0,
    };
}

/** A country's changes, newest first (not the called-off ones). Cached for the request. */
function prices_changes(PDO $db, int $country_id): array
{
    static $cache = [];
    if (!isset($cache[$country_id]) || defined('NFELIA_TESTING')) {
        try {
            $stmt = $db->prepare("SELECT * FROM price_changes WHERE country_id = ? AND cancelled_at IS NULL ORDER BY new_from DESC, id DESC");
            $stmt->execute([$country_id]);
            $cache[$country_id] = $stmt->fetchAll();
        } catch (PDOException $e) {
            // A deploy stopped before its migration ran: the prices as they
            // were, so Billing and paying never break over it.
            error_log('Nfelia prices_changes: ' . $e->getMessage());
            $cache[$country_id] = [];
        }
    }
    return $cache[$country_id];
}

/**
 * What $item costs in $country for a business that joined at $joined (a
 * date-time; null for someone signing up now, or the website), for
 * something that starts on $on (Y-m-d; today when null).
 */
function prices_for(PDO $db, ?array $country, string $item, ?string $joined, ?string $on = null): float
{
    $def = PRICE_ITEMS[$item] ?? null;
    if (!$def) {
        throw new InvalidArgumentException("no price called {$item}");
    }
    if (!$country) {
        return prices_fallback($item);
    }
    $on ??= date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    foreach (prices_changes($db, (int)$country['id']) as $c) {
        if ($c[$def['change']] === null) continue;
        $applies = match (true) {
            $joined === null          => $now >= $c['new_from'],          // the website, a sign-up now
            $joined >= $c['new_from'] => true,                            // joined at the new price
            default                   => $on >= $c['existing_from'],      // was here before: after the notice
        };
        if ($applies) {
            return (float)$c[$def['change']];
        }
    }
    $base = (float)($country[$def['column']] ?? 0);
    if ($base <= 0 && $item === 'book' && (float)($country['monthly_price'] ?? 0) > 0) {
        $base = round((float)$country['monthly_price'] * 0.75);   // as the migration set it
    }
    return $base > 0 ? $base : prices_fallback($item);
}

/** What someone signing up now pays in $country: ['month' => …, 'seat' => …, 'book' => …]. */
function prices_now(PDO $db, ?array $country): array
{
    $out = [];
    foreach (array_keys(PRICE_ITEMS) as $item) {
        $out[$item] = prices_for($db, $country, $item, null);
    }
    return $out;
}

/**
 * The newest price of each item in $country, scheduled or not: where a new
 * change starts from. (Someone joining after every change pays it.)
 */
function prices_latest(PDO $db, ?array $country): array
{
    $out = [];
    foreach (array_keys(PRICE_ITEMS) as $item) {
        $out[$item] = prices_for($db, $country, $item, '9999-12-31 23:59:59');
    }
    return $out;
}

/**
 * Where a change stands: 'cancelled'; 'scheduled' (nobody pays it yet);
 * 'new_only' (new sign-ups and the website pay it, clients already here
 * from existing_from); 'live' (everyone, for what starts from then).
 */
function prices_change_state(array $change): string
{
    return match (true) {
        $change['cancelled_at'] !== null              => 'cancelled',
        date('Y-m-d H:i:s') < $change['new_from']     => 'scheduled',
        date('Y-m-d') < $change['existing_from']      => 'new_only',
        default                                       => 'live',
    };
}

/**
 * A price change still to come for a business that joined at $joined:
 * [item => ['amount' => new, 'now' => today's, 'from' => Y-m-d]], or [].
 * Billing shows it, so nobody is surprised.
 */
function prices_coming(PDO $db, ?array $country, string $joined): array
{
    if (!$country) return [];
    $today = date('Y-m-d');
    $out = [];
    foreach (prices_changes($db, (int)$country['id']) as $c) {
        $new_client = $joined >= $c['new_from'];
        $from = $new_client ? substr($c['new_from'], 0, 10) : $c['existing_from'];
        if ($from <= $today) continue;
        foreach (PRICE_ITEMS as $item => $def) {
            if ($c[$def['change']] === null || isset($out[$item])) continue;
            $now = prices_for($db, $country, $item, $joined, $today);
            if ((float)$c[$def['change']] !== $now) {
                $out[$item] = ['amount' => (float)$c[$def['change']], 'now' => $now, 'from' => $from];
            }
        }
    }
    return $out;
}

/**
 * What the website and help pages show: someone in Nigeria signing up
 * today. Never throws (a page never breaks over a price).
 */
function prices_site(): array
{
    static $site = null;
    if ($site === null || defined('NFELIA_TESTING')) {
        $site = ['month' => prices_fallback('month'), 'seat' => prices_fallback('seat'), 'book' => prices_fallback('book')];
        try {
            $db = get_db();
            $site = prices_now($db, prices_country($db, 'NG'));
        } catch (Throwable $e) {
            error_log('Nfelia prices_site: ' . $e->getMessage());
        }
    }
    return $site;
}

/** A country row by its ISO code (the website's prices are Nigeria's). */
function prices_country(PDO $db, string $iso = 'NG'): ?array
{
    $stmt = $db->prepare("SELECT * FROM countries WHERE iso_code = ? LIMIT 1");
    $stmt->execute([$iso]);
    return $stmt->fetch() ?: null;
}

/** "₦10,000", "GH₵100", "KSh 1,500", "15,000 FCFA", in a country's money, as people there write it. */
function prices_money(float $amount, ?array $country): string
{
    $symbol = (string)($country['currency_symbol'] ?? '₦');
    $n = number_format($amount, fmod($amount, 1.0) != 0.0 ? 2 : 0);
    if (in_array(strtoupper($symbol), ['FCFA', 'CFA', 'XAF', 'XOF'], true)) {
        return $n . ' ' . $symbol;   // CFA francs come after the amount
    }
    return preg_match('/^\p{L}{2,}$/u', $symbol) ? $symbol . ' ' . $n : $symbol . $n;
}

/* ═══════════════════════════════════════════════════════════════════
 *  TELLING CLIENTS
 * ═══════════════════════════════════════════════════════════════════ */

/** The client groups a notice can go to, as ops_plan_sql() states. */
const PRICE_NOTICE_GROUPS = [
    'paying' => ['label' => 'Paying clients',            'states' => ['active', 'due']],
    'trial'  => ['label' => 'On their free trial',       'states' => ['trial']],
    'lapsed' => ['label' => 'Stopped paying',            'states' => ['paused', 'trial_over', 'cancelled']],
];

/**
 * The businesses a change's notice would reach: in its country, joined
 * before the change, owner active with an email, in the chosen groups.
 * [business_id => ['email', 'name', 'business']]
 */
function prices_notice_people(PDO $db, array $change, array $groups): array
{
    $states = [];
    foreach ($groups as $g) {
        foreach (PRICE_NOTICE_GROUPS[$g]['states'] ?? [] as $s) $states[] = $s;
    }
    if (!$states) return [];
    $where = implode(' OR ', array_map(fn($s) => '(' . ops_plan_sql($s) . ')', array_unique($states)));
    $stmt = $db->prepare("SELECT b.id, b.name, u.full_name, u.email FROM businesses b
                          JOIN users u ON u.id = (SELECT u2.id FROM users u2 WHERE u2.business_id = b.id AND u2.role = 'owner' AND u2.is_active = 1 ORDER BY u2.id LIMIT 1)
                          LEFT JOIN subscriptions s ON s.business_id = b.id
                          WHERE b.is_active = 1 AND b.country_id = ? AND b.created_at < ? AND ({$where})
                          ORDER BY b.id");
    $stmt->execute([(int)$change['country_id'], $change['new_from']]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        if (filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            $out[(int)$r['id']] = ['email' => strtolower($r['email']), 'name' => $r['full_name'], 'business' => $r['name']];
        }
    }
    return $out;
}

/**
 * What one client's notice says (views/emails/price_notice.php): each price
 * that changes for them with its date, whether it is good news, what they
 * have done with Nfelia so far (so the price sits next to what it bought),
 * and whether paying now keeps today's price: only when the month they
 * would pay for starts before the date (hold).
 */
function prices_notice_vars(PDO $db, array $change, array $country, int $business_id, string $first_name): array
{
    require_once __DIR__ . '/billing.php';
    $stmt = $db->prepare("SELECT b.name, b.created_at FROM businesses b WHERE b.id = ?");
    $stmt->execute([$business_id]);
    $b = $stmt->fetch() ?: ['name' => 'your business', 'created_at' => date('Y-m-d')];
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
    $stmt->execute([$business_id]);
    [$next] = billing_next_period($stmt->fetch() ?: null);   // a running month ends then; otherwise today
    $lines = [];
    $up = $down = false;
    foreach (PRICE_ITEMS as $item => $def) {
        if ($change[$def['change']] === null) continue;
        $old = (float)$change[$def['old']];
        $new = (float)$change[$def['change']];
        if ($old === $new) continue;
        $new > $old ? $up = true : $down = true;
        $lines[] = ['what' => ucfirst($def['short']), 'old' => prices_money($old, $country), 'new' => prices_money($new, $country)];
    }
    $from = date('j F Y', strtotime((string)$change['existing_from']));
    $stats = [];
    $count = fn(string $sql) => (int)(($s = $db->prepare($sql)) && $s->execute([$business_id]) ? $s->fetchColumn() : 0);
    $invoices = $count("SELECT COUNT(*) FROM invoices WHERE business_id = ? AND status NOT IN ('draft', 'cancelled')");
    $receipts = $count("SELECT COUNT(*) FROM receipts WHERE business_id = ?");
    $customers = $count("SELECT COUNT(*) FROM customers WHERE business_id = ? AND deleted_at IS NULL");
    $received = money_in($db, $business_id, '2000-01-01', date('Y-m-d'));
    if ($invoices) $stats[] = number_format($invoices) . ' invoice' . ($invoices === 1 ? '' : 's');
    if ($receipts) $stats[] = number_format($receipts) . ' receipt' . ($receipts === 1 ? '' : 's');
    if ($customers) $stats[] = number_format($customers) . ' customer' . ($customers === 1 ? '' : 's') . ' kept in one place';
    return [
        'first_name' => $first_name !== '' ? $first_name : 'there',
        'business'   => $b['name'],
        'since'      => date('F Y', strtotime((string)$b['created_at'])),
        'stats'      => $stats,
        'received'   => $received > 0 ? prices_money($received, $country) : null,
        'lines'      => $lines,
        'from'       => $from,
        'up'         => $up,
        'down'       => $down && !$up,
        'running'    => $next > date('Y-m-d'),
        'next'       => date('j F Y', strtotime($next)),
        'hold'       => $next < $change['existing_from'],
        'reason'     => trim((string)$change['reason']),
        'pay_url'    => APP_URL . '/settings?tab=billing',
        'reply'      => 'support@nfelia.com',
        'subject'    => $up ? 'Your Nfelia price from ' . $from : ($down ? 'Good news: Nfelia costs less from ' . $from : 'Your Nfelia prices from ' . $from),
    ];
}

/**
 * Hand waiting notices to the outbox: what is left of the hour's mail
 * allowance after campaigns (campaign_per_hour()), a twelfth an hour per
 * 5-minute run. Each also leaves a note in the owner's bell.
 */
function prices_notice_drip(PDO $db, int $budget): int
{
    if ($budget < 1) return 0;
    $rows = $db->query("SELECT pn.*, pc.country_id FROM price_notices pn JOIN price_changes pc ON pc.id = pn.change_id
                        WHERE pn.status = 'waiting' ORDER BY pn.id LIMIT " . $budget)->fetchAll();
    $queued = 0;
    foreach ($rows as $r) {
        $change = $db->query("SELECT * FROM price_changes WHERE id = " . (int)$r['change_id'])->fetch();
        $country = $db->query("SELECT * FROM countries WHERE id = " . (int)$r['country_id'])->fetch();
        if (!$change || !$country || $change['cancelled_at'] !== null) {
            $db->prepare("UPDATE price_notices SET status = 'skipped' WHERE id = ?")->execute([$r['id']]);
            continue;
        }
        $first = preg_split('/\s+/u', trim((string)$r['name']))[0] ?? '';
        $vars = prices_notice_vars($db, $change, $country, (int)$r['business_id'], $first);
        $key = 'price-notice:' . $r['change_id'] . ':' . $r['business_id'];
        $id = notify_email(null, $r['email'], (string)$r['name'], $vars['subject'], 'price_notice', $vars, $key, 'billing.price_change');
        if (!$id) {
            $same = $db->prepare("SELECT id FROM notify_outbox WHERE dedupe_key = ? AND channel = 'email' LIMIT 1");
            $same->execute([mb_substr($key, 0, 150)]);
            $id = (int)$same->fetchColumn() ?: null;
        }
        notify('billing.price_change', (int)$r['business_id'], ['from' => $change['existing_from'], 'lines' => $vars['lines'], 'up' => $vars['up']],
               ['channels' => [], 'dedupe' => 'change:' . $r['change_id']]);
        $db->prepare("UPDATE price_notices SET status = ?, outbox_id = ?, queued_at = NOW() WHERE id = ?")->execute([$id ? 'queued' : 'failed', $id, $r['id']]);
        if ($id) $queued++;
    }
    $db->exec("UPDATE price_notices pn JOIN notify_outbox o ON o.id = pn.outbox_id SET pn.status = o.status WHERE pn.status = 'queued' AND o.status IN ('sent', 'failed')");
    return $queued;
}
