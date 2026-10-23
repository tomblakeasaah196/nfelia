<?php
/**
 * Nfelia — Tasks & Calendar reminders. cPanel runs it every five minutes
 * with the crontab line below this comment.
 *
 * Everything it tells people goes through notify() (app/helpers/notify.php):
 * the bell always, the phone and email as each person chose, never a buzz in
 * their quiet hours.
 *
 * 1. Reminders that are due. The task row is the queue: reminded_at
 *    is stamped before anything is sent, so a failure never repeats a
 *    reminder or holds up the rest. A reminder more than a day old (the first
 *    run after the cron is set up, or after the server was down) is stamped
 *    and left in the bell only, marked missed: "your 2pm appointment" is no
 *    use buzzing the next afternoon, but the person should see it was missed.
 *
 * 2. The morning plan, once per person per day between 07:00 and 11:00:
 *    late items, today's items and deliveries due in the next two days.
 *    A day with nothing in it sends nothing.
 *
 * 3. Low stock, once a day from 09:00, to the team.
 *
 * 4. Quotes past their date: a sent quote turns Expired the day after its
 *    expiry date, so it stops counting as waiting on an answer, and the
 *    owner hears about it.
 *
 * 4b. Stays that are over with a refundable caution fee still to give
 *    back, from 09:00: the owner hears once (the booking keeps saying so
 *    until they mark it given back).
 *
 * 5. Invoices past their due date, from 08:00: marked overdue, and the
 *    owner hears once a week while they stay unpaid (for eight weeks).
 *
 * 6. Subscriptions: free trials and paid months about to end, in their
 *    grace days, or ended (billing_lifecycle() in
 *    app/helpers/subscription_helpers.php; billing_access() in
 *    app/helpers/billing.php decides what is open).
 *
 * 7. The Nfelia Ops monitor (app/ops/monitor.php): the server log read into
 *    Ops → Errors, health checks, the 8am summary to the Ops team.
 *
 * 8. Records Books waiting to be made, kept files past their year, and
 *    requests for a record nobody answered (app/helpers/records.php); a
 *    price change's notices, with what is left of the hour's mail after
 *    campaigns (app/helpers/prices.php).
 *
 * 9. Phone notifications and emails still waiting, retried (what the
 *    monitor queued goes out in this same run). Reminders and morning plans
 *    don't wait for this: they are sent right after steps 1 and 2.
 *
 * Every time here is Lagos time from planner_now(), the same clock remind_at
 * was worked out with when the task was saved.
 *
 * ea-php83 is the PHP 8.3 bin/deploy.sh uses; /usr/local/bin/php is the
 * server's default PHP, which may be older.
 */
//   */5 * * * * /usr/local/bin/ea-php83 /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1

// cron/ is copied into the public docroot: never run it from the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/mail.php';
require_once __DIR__ . '/../app/helpers/webpush.php';
require_once __DIR__ . '/../app/helpers/planner.php';
require_once __DIR__ . '/../app/helpers/notify.php';
require_once __DIR__ . '/../app/helpers/subscription_helpers.php';   // never in web code: it redeclares is_owner()
require_once __DIR__ . '/../app/helpers/errors.php';
require_once __DIR__ . '/../app/helpers/pdf.php';

// What goes wrong here is written down for the Nfelia team (Ops → Errors),
// and each run's heartbeat tells Ops → Health the cron is alive.
errors_install('cron');

$db = get_db();

// One run at a time, even when one takes longer than five minutes.
if ((int)$db->query("SELECT GET_LOCK('nfelia_planner', 0)")->fetchColumn() !== 1) {
    echo "[planner] another run is busy, skipping\n";
    exit(0);
}
heartbeat_start('planner');
$run_started = microtime(true);

// Whatever stops this run early (a fatal in a later job), what was queued
// still goes: the outbox is sent on the way out.
$GLOBALS['planner_sent_all'] = false;
register_shutdown_function(function () use ($db) {
    if (!empty($GLOBALS['planner_sent_all'])) return;
    try { notify_flush(null, 200, 120); } catch (Throwable $e) { error_log('Nfelia planner shutdown flush: ' . $e->getMessage()); }
    try { $db->query("SELECT RELEASE_LOCK('nfelia_planner')"); } catch (Throwable $e) {}
});

$now   = planner_now();
$now_s = $now->format('Y-m-d H:i:s');
$today = $now->format('Y-m-d');
$done  = ['reminders' => 0, 'stale' => 0, 'skipped' => 0, 'mornings' => 0, 'quiet' => 0, 'low_stock' => 0,
          'quotes_expired' => 0, 'cautions' => 0, 'overdue_notes' => 0, 'errors' => 0];

/** A business that can use the app right now (with its subscription), or null. */
function planner_cron_business(PDO $db, int $id, DateTimeImmutable $now): ?array {
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $stmt = $db->prepare("
            SELECT b.*, s.status AS sub_status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.expiry_exempt
            FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id
            WHERE b.id = ?");
        $stmt->execute([$id]);
        $b = $stmt->fetch() ?: null;
        $cache[$id] = ($b && planner_business_can_use($b, $now)) ? $b : null;
    }
    return $cache[$id];
}

/** An active member of the business, or null. */
function planner_cron_user(PDO $db, int $id, int $biz_id): ?array {
    $stmt = $db->prepare("
        SELECT id, business_id, full_name, email, role
        FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
    $stmt->execute([$id, $biz_id]);
    return $stmt->fetch() ?: null;
}

/* ── 1. Reminders that are due ─────────────────────────────── */

$due = $db->prepare("
    SELECT id, business_id, remind_at, COALESCE(assigned_to, created_by) AS user_id
    FROM tasks
    WHERE remind_at <= ? AND reminded_at IS NULL AND done_at IS NULL AND deleted_at IS NULL
    ORDER BY remind_at, id
    LIMIT 200");
$due->execute([$now_s]);
$stamp = $db->prepare("UPDATE tasks SET reminded_at = ? WHERE id = ? AND reminded_at IS NULL");
$stale_before = $now->modify('-1 day')->format('Y-m-d H:i:s');

foreach ($due->fetchAll() as $row) {
    $stamp->execute([$now_s, $row['id']]);
    if ($stamp->rowCount() !== 1) continue;
    // A reminder more than a day old (the runner was down): no buzz or email
    // hours late, but a bell row, so the person can see what they missed.
    $stale = $row['remind_at'] < $stale_before;
    if ($stale) $done['stale']++;

    try {
        $business = planner_cron_business($db, (int)$row['business_id'], $now);
        $user     = $business ? planner_cron_user($db, (int)$row['user_id'], (int)$business['id']) : null;
        if (!$business) {
            // A business that can't use Nfelia today: the reminder waits
            // (unstamped) and goes if access returns in time, else it lapses
            // as stale above.
            if (!$stale) $db->prepare("UPDATE tasks SET reminded_at = NULL WHERE id = ? AND reminded_at = ?")->execute([$row['id'], $now_s]);
            $done['skipped']++;
            continue;
        }
        if (!$user) { $done['skipped']++; continue; }   // a person who left: nobody to tell
        $task = planner_fetch_tasks($db, (int)$business['id'], ['ids' => [(int)$row['id']]])[0] ?? null;
        if (!$task) { $done['skipped']++; continue; }

        $it   = planner_task_item($task, $today, $business);
        $when = planner_reminder_when($it, $today);
        $line = implode(' · ', array_filter([$stale ? 'Missed reminder' : null, $when, $it['subtitle']]));

        $made = notify('task.reminder', (int)$business['id'], ['task_id' => $it['task_id'], 'title' => $it['title'], 'line' => $line], [
            'users' => [(int)$user['id']], 'business' => $business, 'dedupe' => $row['id'] . ':' . $row['remind_at'],
            'vars'  => ['subject' => 'Reminder: ' . $it['title'], 'item' => $it, 'when' => $when],
        ] + ($stale ? ['channels' => []] : []));
        if (!$made) {
            // Nothing was made and nobody had heard: the stamp comes off so
            // the next run tries again, and the team hears why.
            $had = $db->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND dedupe_key = ? LIMIT 1");
            $had->execute([(int)$user['id'], 'task.reminder:' . $row['id'] . ':' . $row['remind_at']]);
            if (!$had->fetchColumn()) {
                $db->prepare("UPDATE tasks SET reminded_at = NULL WHERE id = ? AND reminded_at = ?")->execute([$row['id'], $now_s]);
                errors_report('job', 'error', 'Cron: reminder for task #' . $row['id'] . ' could not be made; it will be tried again', ['business_id' => (int)$business['id']]);
                $done['errors']++;
            }
            continue;
        }
        if (!$stale) $done['reminders']++;
    } catch (Throwable $e) {
        $done['errors']++;
        errors_report_throwable($e, ['job' => 'Cron: reminder for task #' . $row['id'], 'business_id' => (int)($row['business_id'] ?? 0) ?: null]);
    }
}

// Reminders are for a moment: they go now, not after everything else this
// run does (the monitor, campaigns, a Records Book build can take minutes).
$flushed = notify_flush();

/* ── 2. The morning plan ───────────────────────────────────── */

$hour = (int)$now->format('G');
if ($hour >= 7 && $hour < 11) {
    $people = $db->prepare("
        SELECT id, business_id, full_name, email, role
        FROM users u
        WHERE is_active = 1
          AND NOT EXISTS (SELECT 1 FROM planner_sends s WHERE s.user_id = u.id AND s.kind = 'morning' AND s.send_date = ?)
        ORDER BY id
        LIMIT 500");
    $people->execute([$today]);
    $claim = $db->prepare("INSERT IGNORE INTO planner_sends (user_id, kind, send_date) VALUES (?, 'morning', ?)");

    foreach ($people->fetchAll() as $user) {
        // Claimed first, whatever happens next, so nobody hears twice in a day.
        $claim->execute([$user['id'], $today]);
        if ($claim->rowCount() !== 1) continue;

        try {
            $business = planner_cron_business($db, (int)$user['business_id'], $now);
            if (!$business) { $done['skipped']++; continue; }

            $day     = planner_day_for_user($db, $user, $business, $today);
            $summary = planner_day_summary($day, $business['currency_symbol'] ?? '₦');
            if ($summary['count'] === 0) { $done['quiet']++; continue; }

            $title = 'Your day: ' . $summary['count'] . ' thing' . ($summary['count'] > 1 ? 's' : '') . ' today';
            notify('morning.plan', (int)$business['id'], ['title' => $title, 'line' => $summary['line']], [
                'users' => [(int)$user['id']], 'business' => $business, 'dedupe' => $today,
                'vars'  => ['first_name' => planner_first_name($user['full_name']), 'day' => $day, 'summary' => $summary['line'], 'today' => $today],
            ]);
            $done['mornings']++;
        } catch (Throwable $e) {
            $done['errors']++;
            errors_report_throwable($e, ['job' => 'Cron: morning plan', 'user_id' => (int)$user['id']]);
        }
    }
}

// The morning plans too, before the slower jobs below.
foreach (notify_flush() as $k => $v) $flushed[$k] += $v;

/* ── 3. Low stock ──────────────────────────────────────────── */

// Once a day per business, to the team: the bell row it leaves today is the
// mark that it has been done.
if ($hour >= 9 && $hour < 21) {
    $shops = $db->prepare("
        SELECT DISTINCT v.business_id FROM v_low_stock_products v
        WHERE NOT EXISTS (SELECT 1 FROM notifications n WHERE n.business_id = v.business_id AND n.event = 'stock.low' AND n.created_at >= ?)
        LIMIT 500");
    $shops->execute([$today . ' 00:00:00']);
    // The three most urgent (empty shelves first), with how many in all.
    $low = $db->prepare("
        SELECT name, stock_qty, COUNT(*) OVER () AS n, SUM(stock_qty <= 0) OVER () AS out_n
        FROM v_low_stock_products WHERE business_id = ?
        ORDER BY stock_qty <= 0 DESC, units_needed DESC, name
        LIMIT 3");

    foreach ($shops->fetchAll(PDO::FETCH_COLUMN) as $biz_id) {
        try {
            $business = planner_cron_business($db, (int)$biz_id, $now);
            if (!$business) { $done['skipped']++; continue; }
            $low->execute([$business['id']]);
            $items = $low->fetchAll();
            if (!$items) continue;
            $n     = (int)$items[0]['n'];
            $out   = (int)$items[0]['out_n'];
            $title = $n . ($n === 1 ? ' item ' : ' items ')
                   . ($out === $n ? ($n === 1 ? 'is out of stock' : 'are out of stock')
                   : ($out === 0 ? ($n === 1 ? 'is running low' : 'are running low') : 'need restocking'));
            $names = array_map(fn($p) => $p['name'] . ((float)$p['stock_qty'] <= 0 ? ' (none left)' : ' (' . (float)$p['stock_qty'] . ' left)'), $items);
            $body  = implode(', ', $names) . ($n > 3 ? ', and ' . ($n - 3) . ' more.' : '.');
            notify('stock.low', (int)$business['id'], ['title' => $title, 'body' => $body], ['business' => $business, 'dedupe' => $today]);
            $done['low_stock']++;
        } catch (Throwable $e) {
            $done['errors']++;
            errors_report_throwable($e, ['job' => 'Cron: low stock', 'business_id' => (int)$biz_id]);
        }
    }
}

/* ── 4. Quotes past their date ─────────────────────────────── */

// Every run, so the first run after midnight catches yesterday's; most runs
// change nothing. Only sent quotes: one the customer replied to, or is
// signing, is still being talked over.
try {
    $due_quotes = $db->prepare("
        SELECT q.id, q.business_id, q.doc_number, q.expiry_date, c.full_name AS customer
        FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id
        WHERE q.status = 'sent' AND q.expiry_date IS NOT NULL AND q.expiry_date < ?
        LIMIT 500");
    $due_quotes->execute([$today]);
    $expire = $db->prepare("UPDATE quotations SET status = 'expired' WHERE id = ? AND status = 'sent'");
    foreach ($due_quotes->fetchAll() as $q) {
        $expire->execute([$q['id']]);
        if ($expire->rowCount() !== 1) continue;
        $done['quotes_expired']++;
        if (planner_cron_business($db, (int)$q['business_id'], $now)) {
            notify('quote.expired', (int)$q['business_id'], [
                'quotation_id' => (int)$q['id'], 'quotation_number' => $q['doc_number'],
                'customer' => (string)$q['customer'], 'expiry_date' => $q['expiry_date'],
            ], ['dedupe' => (string)$q['id']]);
        }
    }
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: quote expiry']);
}

/* ── 4b. Caution fees to give back ─────────────────────────── */

// Stays that ended in the last fortnight, once each (dedupe): older ones
// were heard about already, or came before caution fees.
if ((int)$now->format('G') >= 9) {
    try {
        $cautions = $db->prepare("
            SELECT id, business_id, booking_number, product_name, customer_name, caution_fee
            FROM bookings
            WHERE caution_fee > 0 AND caution_refunded_at IS NULL AND status IN ('confirmed', 'completed')
              AND ends_at <= ? AND ends_at > ? - INTERVAL 14 DAY
            LIMIT 500");
        $cautions->execute([$now_s, $now_s]);
        foreach ($cautions->fetchAll() as $c) {
            if (!planner_cron_business($db, (int)$c['business_id'], $now)) continue;
            $made = notify('booking.caution', (int)$c['business_id'], [
                'booking_id' => (int)$c['id'], 'booking_number' => $c['booking_number'], 'item' => $c['product_name'],
                'customer' => $c['customer_name'], 'caution' => (float)$c['caution_fee'],
            ], ['dedupe' => (string)$c['id']]);
            $done['cautions'] += (int)(bool)$made;
        }
    } catch (Throwable $e) {
        $done['errors']++;
        errors_report_throwable($e, ['job' => 'Cron: caution fees']);
    }
}

/* ── 5. Invoices past their due date ───────────────────────── */

// From 08:00, so nobody is woken by it. Marked overdue (the dashboard does
// the same when it opens), and the owner hears once in each week the
// invoice stays unpaid: the first run in week one, week two, and so on, for
// eight weeks. A missed day doesn't lose the week's note.
if ($hour >= 8 && $hour < 20) {
    try {
        $db->prepare("UPDATE invoices SET status = 'overdue' WHERE status IN ('sent','partially_paid') AND due_date < ?")->execute([$today]);
        $overdue = $db->prepare("
            SELECT i.id, i.business_id, i.doc_number, i.balance_due, DATEDIFF(?, i.due_date) AS days, c.full_name AS customer,
                   o.id AS order_id, o.order_number
            FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
            LEFT JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
            WHERE i.status = 'overdue' AND i.balance_due > 0 AND i.due_date < ? AND i.due_date >= ? - INTERVAL 56 DAY
            LIMIT 2000");
        $overdue->execute([$today, $today, $today]);
        $by_business = [];
        foreach ($overdue->fetchAll() as $inv) {
            $by_business[(int)$inv['business_id']][] = $inv;
        }
        $told = $db->prepare("SELECT 1 FROM notifications WHERE business_id = ? AND event = 'invoice.overdue' AND dedupe_key = ? LIMIT 1");
        foreach ($by_business as $biz_id => $invoices) {
            if (!planner_cron_business($db, $biz_id, $now)) continue;
            // Only the ones not yet told about this week.
            $due = array_values(array_filter($invoices, function ($inv) use ($told) {
                $told->execute([$inv['business_id'], 'invoice.overdue:' . $inv['id'] . ':week' . intdiv((int)$inv['days'] - 1, 7)]);
                return !$told->fetchColumn();
            }));
            if (!$due) continue;
            // A few: one alert each. Many at once (the first morning, a busy
            // month): each still lands in the bell, but the phone and inbox
            // get one summary instead of a burst.
            $quietly = count($due) > 3;
            foreach ($due as $inv) {
                $done['overdue_notes'] += (int)(bool)notify('invoice.overdue', $biz_id, [
                    'invoice_id' => (int)$inv['id'], 'invoice_number' => $inv['doc_number'], 'customer' => (string)$inv['customer'],
                    'balance' => (float)$inv['balance_due'], 'days' => (int)$inv['days'],
                    'order_id' => (int)$inv['order_id'] ?: null, 'order_number' => $inv['order_number'],
                ], ['dedupe' => $inv['id'] . ':week' . intdiv((int)$inv['days'] - 1, 7)] + ($quietly ? ['channels' => []] : []));
            }
            if ($quietly) {
                notify('invoice.overdue_many', $biz_id, ['count' => count($due), 'total' => array_sum(array_column($due, 'balance_due'))],
                       ['dedupe' => $today]);
            }
        }
    } catch (Throwable $e) {
        $done['errors']++;
        errors_report_throwable($e, ['job' => 'Cron: overdue invoices']);
    }
}

/* ── 6. Subscriptions ──────────────────────────────────────── */

try {
    foreach (billing_lifecycle($db) as $k => $v) {
        $done['billing_' . $k] = $v;
    }
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: billing']);
}

/* ── 7. The Ops monitor: the server log, health, the morning summary ── */

try {
    require_once __DIR__ . '/../app/ops/monitor.php';
    $done += ops_monitor_run($db, $now);
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: Ops monitor']);
}

// Sales: stalled sign-ups and ending trials become leads, follow-ups
// remind their owner, campaigns go out a few at a time (app/ops/sales.php).
try {
    require_once __DIR__ . '/../app/ops/sales.php';
    $done += ops_sales_cron($db, $now);
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: Ops sales']);
}

/* ── 8. Records Books, and price notices ──────────────────── */

try {
    require_once __DIR__ . '/../app/helpers/records.php';
    $done += records_cron($db);
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: Records Books']);
}
try {
    require_once __DIR__ . '/../app/helpers/prices.php';
    require_once __DIR__ . '/../app/ops/sales.php';
    $share = (int)ceil(campaign_per_hour() / 12) - (int)($done['campaign_queued'] ?? 0);
    $done['price_notices'] = prices_notice_drip($db, max(1, $share));
} catch (Throwable $e) {
    $done['errors']++;
    errors_report_throwable($e, ['job' => 'Cron: price notices']);
}

/* ── 9. Phone notifications and emails still waiting ───────── */

// Most go straight after the page that caused them has answered. This
// sends what this run queued, and tries again what failed (a mail server
// or push service that was down), with longer waits each time
// (app/helpers/notify.php).
// The lock goes first: a slow mail server or push service must not make
// the next run (and the reminders in it) wait. Each outbox row is claimed
// on its own, so two runs sending at once never send one twice.
$db->query("SELECT RELEASE_LOCK('nfelia_planner')");
$GLOBALS['planner_sent_all'] = true;
$sent = notify_flush();
$done['outbox_sent']    = $sent['sent'] + $flushed['sent'];
$done['outbox_retry']   = $sent['retry'] + $flushed['retry'];
$done['outbox_gave_up'] = $sent['failed'] + $flushed['failed'];

// Once a day: read alerts go after six months, everything after a year,
// and the record of sends after a month.
if ($hour === 3 && (int)$now->format('i') < 5) {
    try {
        $db->exec("DELETE FROM notifications WHERE (is_read = 1 AND created_at < NOW() - INTERVAL 6 MONTH) OR created_at < NOW() - INTERVAL 12 MONTH");
        $db->exec("DELETE FROM notify_outbox WHERE status IN ('sent', 'skipped', 'failed') AND created_at < NOW() - INTERVAL 1 MONTH");
        // A row some process claimed and never finished, for a day: given up on, with a word.
        $db->exec("UPDATE notify_outbox SET status = 'failed', last_error = CONCAT('stuck; ', COALESCE(last_error, '')) WHERE status = 'sending' AND next_attempt_at < NOW() - INTERVAL 1 DAY");
        $db->exec("DELETE FROM push_dead WHERE created_at < NOW() - INTERVAL 60 DAY");
        // "Keep me signed in" tokens past their 30 days, and kept PDFs nobody has opened in 30
        $db->exec("DELETE FROM sessions WHERE expires_at < NOW()");
        pdf_cache_sweep(30);
    } catch (Throwable $e) {
        $done['errors']++;
        errors_report_throwable($e, ['job' => 'Cron: notification clean-up']);
    }
}

heartbeat_finish('planner', $done['errors'] === 0, implode(' ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($done), $done)),
                 (int)round((microtime(true) - $run_started) * 1000));

echo '[planner] ' . $now->format('Y-m-d H:i') . ' ' . implode(' ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($done), $done)) . "\n";
