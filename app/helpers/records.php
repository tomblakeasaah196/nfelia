<?php
/**
 * The Records Book: a business's records as one branded Excel workbook
 * (app/helpers/records_book.php writes it; app/helpers/records_sheets.php
 * says what can be in it), the free plain copy (CSV files in a ZIP), and
 * the codes that let anyone check a book at nfelia.com/verify.
 *
 * Who gets what (Tom, October 2026):
 *   - a business inside a paid month: one Records Book free in that month
 *   - anyone else, or another book in the same month: the Records Book
 *     price for their country (Nigeria ₦7,500; Ops → Prices)
 *   - the plain copy: free for everyone, always, whatever their plan (the
 *     data protection copy)
 *   - Nfelia Ops builds one for a client when asked: Support sends it to the
 *     owner, an Admin may also download it. The owner is told either way.
 *
 * A build is queued, made straight after the page that asked for it has
 * answered (after_response), and by the cron if that didn't happen or
 * failed (three tries). Files are kept in storage/private/records/{business}/,
 * closed to the web: books for RECORDS_KEEP_DAYS, plain copies for
 * RECORDS_RAW_DAYS. Each book's ledger (the facts of every record that has
 * a verify code) is kept beside it, so a record can be checked as it was
 * when the book was made.
 */

require_once __DIR__ . '/../../vendor/autoload.php';   // PhpSpreadsheet and the QR codes; the cron doesn't load it
require_once __DIR__ . '/records_sheets.php';
require_once __DIR__ . '/records_book.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/money.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/prices.php';

const RECORDS_KEEP_DAYS = 365;     // a book, and what it takes to check it
const RECORDS_RAW_DAYS  = 7;       // a plain copy
const RECORDS_TRIES     = 3;
const RECORDS_ALPHABET  = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';   // Crockford's: no I, L, O or U to misread

/** The most cells one book may hold: past this, a period has to be chosen (about 700 bytes of memory each). */
function records_max_cells(): int
{
    return max(50000, (int)($_ENV['RECORDS_MAX_CELLS'] ?? 600000));
}

/* ═══════════════════════════════════════════════════════════════════
 *  CODES
 * ═══════════════════════════════════════════════════════════════════ */

/** A new book code: 10 characters, about a quadrillion possibilities. */
function records_new_code(PDO $db): string
{
    $check = $db->prepare("SELECT 1 FROM records_books WHERE code = ?");
    do {
        $code = '';
        foreach (str_split(random_bytes(10)) as $byte) {
            $code .= RECORDS_ALPHABET[ord($byte) & 31];
        }
        $check->execute([$code]);
    } while ($check->fetchColumn());
    return $code;
}

/** A record's code in a book: the book's code, its sheet's letter and its number ("K7Q2MXA9WT-I3G"). */
function records_row_code(string $book_code, string $letter, int $id): string
{
    return $book_code . '-' . $letter . records_base32($id);
}

/** "K7Q2M-XA9WT": a book's code as people read it. */
function records_code_display(string $code): string
{
    return strlen($code) === 10 ? substr($code, 0, 5) . '-' . substr($code, 5) : $code;
}

function records_verify_url(string $code): string
{
    return APP_URL . '/verify/' . rawurlencode($code);
}

function records_base32(int $n): string
{
    $out = '';
    do {
        $out = RECORDS_ALPHABET[$n % 32] . $out;
        $n = intdiv($n, 32);
    } while ($n > 0);
    return $out;
}

/**
 * Read a typed or scanned code, however it was written ("k7q2m-xa9wt",
 * "K7Q2MXA9WT-I3G", a link): ['book' => code, 'letter' => null|'I', 'id' => null|int].
 * Null when it can't be a code.
 */
function records_parse_code(string $input): ?array
{
    $input = trim($input);
    if (preg_match('#/verify/([^/?\#]+)#i', $input, $m)) {
        $input = rawurldecode($m[1]);
    }
    $plain = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    if (strlen($plain) < 10 || strlen($plain) > 24) return null;
    $fix = fn(string $s) => strtr($s, ['O' => '0', 'I' => '1', 'L' => '1', 'U' => 'V']);
    $book = $fix(substr($plain, 0, 10));
    if (strspn($book, RECORDS_ALPHABET) !== 10) return null;
    $rest = substr($plain, 10);
    if ($rest === '') return ['book' => $book, 'letter' => null, 'id' => null];
    $letter = $rest[0];
    $digits = $fix(substr($rest, 1));
    if (!isset(RECORDS_VERIFY[$letter]) || $digits === '' || strspn($digits, RECORDS_ALPHABET) !== strlen($digits) || strlen($digits) > 8) return null;
    $id = 0;
    foreach (str_split($digits) as $c) {
        $id = $id * 32 + strpos(RECORDS_ALPHABET, $c);
    }
    return ['book' => $book, 'letter' => $letter, 'id' => $id];
}

/** A stable fingerprint of a record's facts, to tell later whether it changed. */
function records_facts_hash(array $facts): string
{
    $norm = array_map(fn($v) => is_numeric($v) ? number_format((float)$v, 2, '.', '') : (string)$v, $facts);
    return sha1(json_encode($norm, JSON_UNESCAPED_UNICODE));
}

/* ═══════════════════════════════════════════════════════════════════
 *  WHAT A BUSINESS MAY HAVE, AND AT WHAT PRICE
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * What Your records offers this business now:
 *   access     billing_access() (trial, paid, grace, locked)
 *   free       true when its free book for this paid month is still unused
 *   free_used  when this month's free book was made, if it was
 *   next_free  when the next paid month starts (a new free book), if known
 *   price      one book, in its currency, as prices_for() says
 *   symbol, currency, method (paystack, mobile_money, manual)
 */
function records_offer(PDO $db, int $business_id): array
{
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $sub = $stmt->fetch() ?: null;
    $access = billing_access($sub);
    $prices = billing_prices($db, $business_id);

    $free = false;
    $used = null;
    if ($access === 'paid') {
        // The paid month this is in. One that never ends (left as it was when
        // months began to expire) counts by calendar month.
        $start = !empty($sub['current_period_start']) && empty($sub['expiry_exempt']) ? substr((string)$sub['current_period_start'], 0, 10) : date('Y-m-01');
        $stmt = $db->prepare("SELECT MAX(created_at) FROM records_books
                              WHERE business_id = ? AND kind = 'book' AND charge = 'free' AND status NOT IN ('failed', 'cancelled') AND created_at >= ?");
        $stmt->execute([$business_id, $start]);
        $used = $stmt->fetchColumn() ?: null;
        $free = $used === null;
    }
    return [
        'access'    => $access,
        'free'      => $free,
        'free_used' => $used,
        'next_free' => $access === 'paid' && !$free && !empty($sub['current_period_end']) && empty($sub['expiry_exempt']) ? substr((string)$sub['current_period_end'], 0, 10) : null,
        'price'     => (float)$prices['book'],
        'symbol'    => $prices['symbol'],
        'currency'  => $prices['currency'],
        'method'    => $prices['method'],
        'country'   => $prices['country'],
    ];
}

/** "₦7,500", "15,000 FCFA". */
function records_money(float $amount, string $symbol): string
{
    $n = number_format($amount, fmod($amount, 1.0) != 0.0 ? 2 : 0);
    return preg_match('/^\p{L}{2,}$/u', $symbol) ? $n . ' ' . $symbol : $symbol . $n;
}

/* ═══════════════════════════════════════════════════════════════════
 *  ASKING FOR ONE
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Put a book (or a plain copy) in the queue. Returns its id.
 *   kind       'book' or 'raw'
 *   charge     'free', 'paid', 'team' or 'raw'
 *   sheets     the sheet keys (a plain copy takes everything)
 *   from, to   the period (Y-m-d), or null
 *   status     'queued', or 'awaiting_payment' until it is paid for
 *   requested_by, staff_id, reason, send_to_owner, amount, currency, payment_ref
 */
function records_queue(PDO $db, int $business_id, array $o): int
{
    $kind = ($o['kind'] ?? 'book') === 'raw' ? 'raw' : 'book';
    $all = array_keys(array_filter(records_sheets(), fn($s) => !isset($s['line_of'])));
    $sheets = $kind === 'raw' ? $all : array_values(array_intersect($all, (array)($o['sheets'] ?? $all)));
    $db->prepare("INSERT INTO records_books (business_id, kind, code, status, charge, amount, currency_code, payment_ref, sheets, date_from, date_to,
                                             requested_by, staff_id, reason, send_to_owner)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([
           $business_id, $kind, $kind === 'book' ? records_new_code($db) : null, $o['status'] ?? 'queued',
           $kind === 'raw' ? 'raw' : ($o['charge'] ?? 'free'), $o['amount'] ?? null, $o['currency'] ?? null, $o['payment_ref'] ?? null,
           json_encode($sheets), $o['from'] ?? null, $o['to'] ?? null,
           $o['requested_by'] ?? null, $o['staff_id'] ?? null, isset($o['reason']) ? mb_substr((string)$o['reason'], 0, 255) : null, !empty($o['send_to_owner']) ? 1 : 0,
       ]);
    return (int)$db->lastInsertId();
}

/** Make a queued build straight after this page has answered (the cron does it otherwise). */
function records_build_later(int $id): void
{
    if (PHP_SAPI === 'cli' || !function_exists('after_response')) {
        return;
    }
    after_response(function () use ($id) {
        try {
            records_build(get_db(), $id);
        } catch (Throwable $e) {
            errors_report_throwable($e, ['job' => 'Records Book']);
        }
    });
}

/** A period from a form: [from, to] as Y-m-d or null, never the wrong way round, never in the future. */
function records_period_from(array $in): array
{
    $day = function ($v) {
        $v = trim((string)$v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
    };
    $today = date('Y-m-d');
    switch ((string)($in['period'] ?? 'all')) {
        case 'this_year': return [date('Y-01-01'), null];
        case 'last_year': return [(date('Y') - 1) . '-01-01', (date('Y') - 1) . '-12-31'];
        case '12_months': return [date('Y-m-d', strtotime('-12 months +1 day')), null];
        case 'custom':
            $from = $day($in['from'] ?? '');
            $to = $day($in['to'] ?? '');
            if ($to !== null && $to > $today) $to = null;
            if ($from !== null && $to !== null && $from > $to) [$from, $to] = [$to, $from];
            return [$from, $to];
        default: return [null, null];
    }
}

/**
 * Roughly how many cells a book would hold, to refuse one too big before
 * building it (the sheets' rows times their columns).
 */
function records_estimate_cells(PDO $db, int $business_id, array $sheets, ?string $from, ?string $to): int
{
    $all = records_sheets();
    $cells = 0;
    foreach (records_book_sheet_keys($sheets) as $key) {
        $cells += records_sheet_count($db, $all[$key], $business_id, $from, $to) * (count($all[$key]['cols']) + 2);
    }
    return $cells;
}

/** How many rows a sheet would have for a business and period. */
function records_sheet_count(PDO $db, array $sheet, int $business_id, ?string $from, ?string $to): int
{
    [$sql, $args] = records_sheet_sql($sheet, $business_id, $from, $to);
    $stmt = $db->prepare(records_count_sql($sql));
    $stmt->execute($args);
    return (int)$stmt->fetchColumn();
}

/**
 * A sheet's SQL as a count of its rows: what it selects, and how it is
 * sorted, swapped out where they stand at the top level, never inside a
 * subquery (an order's receipts are listed in ORDER BY, from their own FROM).
 */
function records_count_sql(string $sql): string
{
    $depth = 0;
    $quote = null;
    $from = $order = null;
    for ($i = 0, $n = strlen($sql); $i < $n; $i++) {
        $c = $sql[$i];
        if ($quote !== null) {
            if ($c === $quote) $quote = null;
        } elseif ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
        } elseif ($depth === 0 && ctype_space($c)) {
            if ($from === null && strncasecmp(substr($sql, $i + 1, 5), 'FROM ', 5) === 0) $from = $i;
            elseif (strncasecmp(substr($sql, $i + 1, 9), 'ORDER BY ', 9) === 0) $order = $i;
        }
    }
    if ($from === null) throw new LogicException('a sheet\'s SQL has no FROM');
    return 'SELECT COUNT(*)' . substr($sql, $from, ($order ?? $n) - $from);
}

/* ═══════════════════════════════════════════════════════════════════
 *  MAKING ONE
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Build one queued book or plain copy. Claims it first, so the page's own
 * build and the cron never both make it. True when it is ready.
 */
function records_build(PDO $db, int $id): bool
{
    $claim = $db->prepare("UPDATE records_books SET status = 'building', started_at = NOW(), attempts = attempts + 1, error = NULL WHERE id = ? AND status = 'queued'");
    $claim->execute([$id]);
    if ($claim->rowCount() !== 1) {
        return false;
    }
    $book = $db->query("SELECT * FROM records_books WHERE id = " . (int)$id)->fetch();
    $business = records_business($db, (int)$book['business_id']);
    $dir = private_file_dir('records', (int)$book['business_id']);
    $made = null;
    try {
        if (!$business) {
            throw new RuntimeException('the business no longer exists');
        }
        @set_time_limit(900);
        records_raise_memory();
        if ($book['kind'] === 'raw') {
            $made = records_write_raw($db, $book, $business, $dir);
        } else {
            $cells = records_estimate_cells($db, (int)$book['business_id'], json_decode((string)$book['sheets'], true) ?: [], $book['date_from'], $book['date_to']);
            if ($cells > records_max_cells()) {
                throw new LengthException('too big: about ' . number_format($cells) . ' cells; choose a shorter period');
            }
            $made = records_write_book($db, $book, $business, $dir);
        }
    } catch (Throwable $e) {
        foreach (glob($dir . '/.building-' . $id . '*') ?: [] as $left) @unlink($left);
        $last = (int)$book['attempts'] >= RECORDS_TRIES || $e instanceof LengthException;
        $db->prepare("UPDATE records_books SET status = ?, error = ? WHERE id = ?")
           ->execute([$last ? 'failed' : 'queued', mb_substr($e->getMessage(), 0, 255), $id]);
        errors_report_throwable($e, ['job' => 'Records Book ' . $id, 'business_id' => (int)$book['business_id']]);
        if ($last) {
            records_failed($db, $book, $business, $e);
        }
        return false;
    }

    $db->prepare("UPDATE records_books SET status = 'ready', file_path = ?, file_name = ?, file_size = ?, sha256 = ?, counts = ?, facts = ?,
                         ready_at = NOW(), expires_at = NOW() + INTERVAL ? DAY WHERE id = ?")
       ->execute([$made['path'], $made['name'], $made['size'], $made['sha256'], json_encode($made['counts']), json_encode($made['facts'], JSON_UNESCAPED_UNICODE),
                  $book['kind'] === 'raw' ? RECORDS_RAW_DAYS : RECORDS_KEEP_DAYS, $id]);
    records_tell_ready($db, $book, $business);
    return true;
}

/**
 * A build gets 1 GB (a book at the cell limit needs about 420 MB): more than
 * most hosts give a page, and a ceiling where they give none, so a build that
 * went wrong stops with an error saying where, not killed by the system.
 */
function records_raise_memory(): void
{
    @ini_set('memory_limit', '1024M');
}

/** A business, with what its book shows about it: owner, country, currency, shop link. */
function records_business(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT b.*, c.name AS country, c.currency_code,
                                 (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' ORDER BY u.is_active DESC, u.id LIMIT 1) AS owner_name
                          FROM businesses b LEFT JOIN countries c ON c.id = b.country_id WHERE b.id = ?");
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) return null;
    $b['shop_url'] = !empty($b['subdomain']) && ($b['subdomain_status'] ?? '') === 'active' ? shop_url((string)$b['subdomain']) : null;
    return $b;
}

/** Write the workbook into $dir. Returns what records_build() saves. */
function records_write_book(PDO $db, array $book, array $business, string $dir): array
{
    $facts = records_facts($db, (int)$business['id'], $book['date_from'], $book['date_to']);
    $tmp = $dir . '/.building-' . (int)$book['id'] . '.xlsx';
    $out = (new RecordsBook($db, $book, $business, $facts))->write($tmp);
    $final = $dir . '/' . (int)$book['id'] . '-' . $book['code'] . '.xlsx';
    if (!rename($tmp, $final)) {
        throw new RuntimeException('could not move the book into place');
    }
    file_put_contents($dir . '/' . (int)$book['id'] . '.ledger.json.gz', gzencode(json_encode($out['ledger'], JSON_UNESCAPED_UNICODE), 6));
    unset($facts['months'], $facts['top_customers'], $facts['top_items'], $facts['owing']);
    return [
        'path'   => records_rel_path($final),
        'name'   => slugify((string)$business['name']) . '-records-book-' . date('Y-m-d', strtotime((string)$book['created_at'])) . '.xlsx',
        'size'   => filesize($final),
        'sha256' => hash_file('sha256', $final),
        'counts' => $out['counts'],
        'facts'  => $facts + ['period' => records_period_text($book), 'sheets' => count(array_filter($out['counts']))],
    ];
}

/** A path under the app, as stored ("storage/private/records/12/40-K7Q2….xlsx"). */
function records_rel_path(string $full): string
{
    return ltrim(substr($full, strlen(BASE_PATH)), '/');
}

/** The file of a ready book or copy, or null when it is gone. */
function records_file(array $book): ?string
{
    if (empty($book['file_path']) || $book['status'] !== 'ready') return null;
    $full = realpath(BASE_PATH . '/' . $book['file_path']);
    $root = realpath(BASE_PATH . '/storage/private/records');
    return $full && $root && str_starts_with($full, $root . '/') && is_file($full) ? $full : null;
}

/** Send a ready file to the browser, count the download, and stop. */
function records_send(PDO $db, array $book): never
{
    $file = records_file($book);
    if (!$file) {
        http_response_code(404);
        exit('That file is no longer kept.');
    }
    $db->prepare("UPDATE records_books SET downloads = downloads + 1, last_download_at = NOW() WHERE id = ?")->execute([$book['id']]);
    records_stream($file, (string)$book['file_name'], $book['kind'] === 'raw' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}

/** Stream a file as a download, and stop. */
function records_stream(string $file, string $name, string $type): never
{
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

/** The facts of every verifiable record in a book, as it was made ([] when gone). */
function records_ledger(array $book): array
{
    $file = BASE_PATH . '/storage/private/records/' . (int)$book['business_id'] . '/' . (int)$book['id'] . '.ledger.json.gz';
    if (!is_file($file)) return [];
    return json_decode((string)gzdecode((string)file_get_contents($file)), true) ?: [];
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE FIGURES ON THE COVER AND AT A GLANCE
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * The figures for a period ($from, $to inclusive; null for no limit), worked
 * out the way Nfelia's own screens do: money in is money_in() (invoice
 * payments and sales paid on the spot; money kept for a customer's next
 * order counts once, when it came in), sales invoiced leave out drafts and
 * cancelled invoices, spending is expenses not deleted, orders leave out
 * those turned down or cancelled, and the hidden walk-in customer of quick
 * sales is no customer.
 */
function records_facts(PDO $db, int $biz, ?string $from, ?string $to): array
{
    $to_day = $to ?? date('Y-m-d');
    $from_day = $from ?? '2000-01-01';
    $one = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchColumn(); };
    $all = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_NUM); };

    $facts = [
        'customers'   => (int)$one("SELECT COUNT(*) FROM customers WHERE business_id = ? AND deleted_at IS NULL AND is_walk_in = 0", [$biz]),
        'invoices'    => (int)$one("SELECT COUNT(*) FROM invoices WHERE business_id = ? AND status NOT IN ('draft', 'cancelled') AND issue_date BETWEEN ? AND ?", [$biz, $from_day, $to_day]),
        'invoiced'    => (float)$one("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE business_id = ? AND status NOT IN ('draft', 'cancelled') AND issue_date BETWEEN ? AND ?", [$biz, $from_day, $to_day]),
        'received'    => money_in($db, $biz, $from_day, $to_day),
        'spent'       => (float)$one("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE business_id = ? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ?", [$biz, $from_day, $to_day]),
        'orders'      => (int)$one("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('rejected', 'cancelled') AND DATE(created_at) BETWEEN ? AND ?", [$biz, $from_day, $to_day]),
        'products'    => (int)$one("SELECT COUNT(*) FROM products WHERE business_id = ? AND deleted_at IS NULL", [$biz]),
        'owed_to_you' => (float)$one("SELECT COALESCE(SUM(outstanding_balance), 0) FROM customers WHERE business_id = ? AND deleted_at IS NULL AND outstanding_balance > 0", [$biz]),
    ];

    // Month by month: the last 24 months of the period at most
    $first = $one("SELECT MIN(d) FROM (
                       SELECT MIN(issue_date) AS d FROM invoices WHERE business_id = ? AND status NOT IN ('draft', 'cancelled') AND issue_date BETWEEN ? AND ?
                       UNION ALL SELECT MIN(payment_date) FROM payments WHERE business_id = ? AND method <> 'kept' AND payment_date BETWEEN ? AND ?
                       UNION ALL SELECT MIN(issue_date) FROM receipts WHERE business_id = ? AND invoice_id IS NULL AND issue_date BETWEEN ? AND ?
                       UNION ALL SELECT MIN(expense_date) FROM expenses WHERE business_id = ? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ?) f",
                  [$biz, $from_day, $to_day, $biz, $from_day, $to_day, $biz, $from_day, $to_day, $biz, $from_day, $to_day]);
    $months = [];
    if ($first) {
        $start = max(substr((string)$first, 0, 7) . '-01', date('Y-m-01', strtotime(substr($to_day, 0, 7) . '-01 -23 months')));
        for ($m = $start; $m <= $to_day; $m = date('Y-m-d', strtotime($m . ' +1 month'))) {
            $months[substr($m, 0, 7)] = ['label' => date('M Y', strtotime($m)), 'invoiced' => 0.0, 'received' => 0.0, 'spent' => 0.0];
        }
        $range = [max($from_day, $start), $to_day];
        foreach ($all("SELECT DATE_FORMAT(issue_date, '%Y-%m'), SUM(total) FROM invoices WHERE business_id = ? AND status NOT IN ('draft', 'cancelled')
                       AND issue_date BETWEEN ? AND ? GROUP BY 1", [$biz, ...$range]) as [$ym, $v]) {
            if (isset($months[$ym])) $months[$ym]['invoiced'] = (float)$v;
        }
        foreach (money_in_by_month($db, $biz, ...$range) as $ym => $v) {
            if (isset($months[$ym])) $months[$ym]['received'] = (float)$v;
        }
        foreach ($all("SELECT DATE_FORMAT(expense_date, '%Y-%m'), SUM(amount) FROM expenses WHERE business_id = ? AND deleted_at IS NULL
                       AND expense_date BETWEEN ? AND ? GROUP BY 1", [$biz, ...$range]) as [$ym, $v]) {
            if (isset($months[$ym])) $months[$ym]['spent'] = (float)$v;
        }
    }
    $facts['months'] = $months;

    $facts['top_customers'] = $all("SELECT c.full_name, SUM(i.total), MAX(c.outstanding_balance) FROM invoices i JOIN customers c ON c.id = i.customer_id
                                    WHERE i.business_id = ? AND c.is_walk_in = 0 AND i.status NOT IN ('draft', 'cancelled') AND i.issue_date BETWEEN ? AND ?
                                    GROUP BY c.id ORDER BY 2 DESC LIMIT 8", [$biz, $from_day, $to_day]);
    $facts['top_items'] = $all("SELECT name, SUM(qty), SUM(amount) FROM (
                                    SELECT COALESCE(p.name, ii.description) AS name, ii.quantity AS qty, ii.subtotal AS amount
                                    FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id LEFT JOIN products p ON p.id = ii.product_id
                                    WHERE i.business_id = ? AND i.status NOT IN ('draft', 'cancelled') AND i.issue_date BETWEEN ? AND ?
                                    UNION ALL
                                    SELECT COALESCE(p.name, ri.description), ri.quantity, ri.subtotal
                                    FROM receipt_items ri JOIN receipts r ON r.id = ri.receipt_id LEFT JOIN products p ON p.id = ri.product_id
                                    WHERE r.business_id = ? AND r.invoice_id IS NULL AND r.issue_date BETWEEN ? AND ?) sold
                                GROUP BY name ORDER BY 3 DESC LIMIT 8", [$biz, $from_day, $to_day, $biz, $from_day, $to_day]);
    $facts['owing'] = array_map(fn($r) => [$r[0], $r[1], $r[2] ? date('j M Y', strtotime((string)$r[2])) : ''],
        $all("SELECT c.full_name, c.outstanding_balance,
                     (SELECT MIN(i.issue_date) FROM invoices i WHERE i.customer_id = c.id AND i.business_id = c.business_id AND i.status IN ('sent', 'partially_paid', 'overdue'))
              FROM customers c WHERE c.business_id = ? AND c.deleted_at IS NULL AND c.outstanding_balance > 0 ORDER BY c.outstanding_balance DESC LIMIT 8", [$biz]));
    return $facts;
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE PLAIN COPY
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Every table of the business's own, as CSV files in a ZIP: the copy anyone
 * can have, free, whatever their plan. The tables as they are kept, with
 * their ID numbers, and nothing that would let someone else in: no links,
 * passwords, tokens, payment codes, server paths or shoppers' IP addresses.
 */
const RECORDS_RAW_TABLES = [
    'businesses'              => 'id = ?',
    'users'                   => 'business_id = ?',
    'customers'               => 'business_id = ?',
    'suppliers'               => 'business_id = ?',
    'products'                => 'business_id = ?',
    'product_variant_groups'  => 'business_id = ?',
    'product_variant_options' => 'variant_group_id IN (SELECT id FROM product_variant_groups WHERE business_id = ?)',
    'stock_movements'         => 'business_id = ?',
    'quotations'              => 'business_id = ?',
    'quotation_items'         => 'quotation_id IN (SELECT id FROM quotations WHERE business_id = ?)',
    'quotation_activities'    => 'business_id = ?',
    'invoices'                => 'business_id = ?',
    'invoice_items'           => 'invoice_id IN (SELECT id FROM invoices WHERE business_id = ?)',
    'payments'                => 'business_id = ?',
    'receipts'                => 'business_id = ?',
    'receipt_items'           => 'receipt_id IN (SELECT id FROM receipts WHERE business_id = ?)',
    'orders'                  => 'business_id = ?',
    'order_items'             => 'order_id IN (SELECT id FROM orders WHERE business_id = ?)',
    'order_events'            => 'business_id = ?',
    'refunds'                 => 'business_id = ?',
    'kept_money'              => 'business_id = ?',
    'bookings'                => 'business_id = ?',
    'deliveries'              => 'business_id = ?',
    'delivery_items'          => 'delivery_id IN (SELECT id FROM deliveries WHERE business_id = ?)',
    'deeds'                   => 'business_id = ?',
    'expenses'                => 'business_id = ?',
    'expense_items'           => 'expense_id IN (SELECT id FROM expenses WHERE business_id = ?)',
    'debts'                   => 'business_id = ?',
    'debt_settlements'        => 'business_id = ?',
    'bank_accounts'           => 'business_id = ?',
    'tasks'                   => 'business_id = ?',
    'reviews'                 => 'business_id = ?',
    'quote_requests'          => 'business_id = ?',
    'shop_posts'              => 'business_id = ?',
    'subscriptions'           => 'business_id = ?',
    'subscription_payments'   => 'business_id = ?',
    'support_tickets'         => 'business_id = ?',
];

/** Columns never in the plain copy: links, passwords, payment codes, server paths, shoppers' IP addresses. */
function records_raw_secret(string $column): bool
{
    return (bool)preg_match('/(^|_)(token|hash|password|ip|user_agent|path)$|^paystack_|signature_data$|^meta_data$/', $column);
}

function records_write_raw(PDO $db, array $book, array $business, string $dir): array
{
    $tmp = $dir . '/.building-' . (int)$book['id'] . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('could not start the ZIP');
    }
    $counts = [];
    $files = [];
    foreach (RECORDS_RAW_TABLES as $table => $where) {
        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE {$where} ORDER BY 1");
        $stmt->execute([(int)$business['id']]);
        $csv = tempnam(sys_get_temp_dir(), 'nfraw');
        $fh = fopen($csv, 'wb');
        fwrite($fh, "\xEF\xBB\xBF");   // so Excel reads ₦ and accents as they are
        $n = 0;
        $keep = null;
        while ($row = $stmt->fetch()) {
            if ($keep === null) {
                $keep = array_values(array_filter(array_keys($row), fn($c) => !records_raw_secret($c)));
                fputcsv($fh, $keep, ',', '"', '');
            }
            fputcsv($fh, array_map(fn($c) => records_csv_safe($row[$c]), $keep), ',', '"', '');
            $n++;
        }
        fclose($fh);
        $counts[$table] = $n;
        $files[] = $csv;
        if ($n > 0) {
            $zip->addFile($csv, $table . '.csv');
        }
    }
    $zip->addFromString('READ ME.txt', records_raw_readme($business, $book, $counts));
    $zip->close();
    foreach ($files as $f) @unlink($f);
    $final = $dir . '/' . (int)$book['id'] . '-plain-copy.zip';
    if (!rename($tmp, $final)) {
        throw new RuntimeException('could not move the plain copy into place');
    }
    return [
        'path'   => records_rel_path($final),
        'name'   => slugify((string)$business['name']) . '-plain-copy-' . date('Y-m-d', strtotime((string)$book['created_at'])) . '.zip',
        'size'   => filesize($final),
        'sha256' => hash_file('sha256', $final),
        'counts' => $counts,
        'facts'  => [],
    ];
}

/** A value for the plain copy. Text a spreadsheet would run as a formula gets a ' in front. */
function records_csv_safe(mixed $value): string
{
    $v = (string)($value ?? '');
    if ($v !== '' && preg_match('/^[=+@\t\r]|^-[^0-9.]/', $v)) {
        return "'" . $v;
    }
    return $v;
}

function records_raw_readme(array $business, array $book, array $counts): string
{
    $lines = [
        $business['name'] . ' - your plain copy from Nfelia',
        'Made on ' . records_when($book['created_at']) . ' (Lagos time).',
        '',
        'These are your records as Nfelia keeps them: one CSV file for each kind of record, with',
        'the ID numbers that join them (invoice_items.invoice_id is invoices.id, and so on).',
        'Any spreadsheet app opens them. Money is in ' . (($business['currency_code'] ?? '') ?: 'your currency') . '. Dates are Lagos time.',
        'Text that starts with =, +, - or @ has a \' in front, so spreadsheet apps never run it.',
        'Links, passwords and payment codes are left out, so this copy can\'t be used to get into anything.',
        '',
        'What is in it:',
    ];
    foreach ($counts as $table => $n) {
        if ($n > 0) $lines[] = '  ' . str_pad($table . '.csv', 30) . number_format($n) . ' row' . ($n === 1 ? '' : 's');
    }
    $lines[] = '';
    $lines[] = 'Want it organised? Your Records Book puts all of this in one Excel workbook in your';
    $lines[] = 'colours: names instead of numbers, totals, charts, a link to every document, and a';
    $lines[] = 'code anyone can check at nfelia.com/verify. Get it in Nfelia, under More > Your records.';
    $lines[] = '';
    $lines[] = 'Questions: support@nfelia.com';
    return implode("\r\n", $lines) . "\r\n";
}

/* ═══════════════════════════════════════════════════════════════════
 *  TELLING PEOPLE
 * ═══════════════════════════════════════════════════════════════════ */

/** A book is ready: the owner hears (and, when the team made it, why). */
function records_tell_ready(PDO $db, array $book, array $business): void
{
    $biz = (int)$book['business_id'];
    if ($book['charge'] === 'team') {
        $staff = $book['staff_id'] ? ops_staff_by_id((int)$book['staff_id']) : null;
        notify('records.team_built', $biz, [
            'book_id' => (int)$book['id'], 'staff' => $staff['full_name'] ?? 'The Nfelia team', 'reason' => (string)$book['reason'], 'sent' => (int)$book['send_to_owner'],
        ], ['dedupe' => 'book:' . $book['id']]);
        return;
    }
    notify($book['kind'] === 'raw' ? 'records.raw_ready' : 'records.ready', $biz, ['book_id' => (int)$book['id']],
           ['dedupe' => 'book:' . $book['id']]);
}

/** A build that gave up: the owner hears the team is on it, and the team is told. */
function records_failed(PDO $db, array $book, ?array $business, Throwable $e): void
{
    $name = $business['name'] ?? 'A client';
    $big = $e instanceof LengthException;
    if ($business && $book['charge'] !== 'team') {
        notify('records.failed', (int)$book['business_id'], ['book_id' => (int)$book['id'], 'big' => $big], ['dedupe' => 'book-failed:' . $book['id']]);
    }
    require_once BASE_PATH . '/app/ops/alerts.php';   // the cron doesn't load it
    ops_alert($db, 'records.failed', ($big ? 'Records Book too big: ' : 'Records Book failed: ') . $name,
              ($big ? 'Ask them to choose a shorter period. ' : '') . 'Book #' . (int)$book['id'] . ' (' . $book['charge'] . '): ' . mb_substr($e->getMessage(), 0, 200),
              'records/' . (int)$book['id'], 'records-failed:' . $book['id'], ['permission' => 'exports']);
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE CRON
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Every five minutes (cron/planner.php): builds waiting longer than a
 * minute (their page's own build died or never ran), builds stuck for 20
 * minutes tried again, files past their date deleted, and requests that
 * went unanswered closed (app/helpers/record_requests.php).
 */
function records_cron(PDO $db): array
{
    $done = ['records_built' => 0, 'records_expired' => 0];
    $db->exec("UPDATE records_books SET status = IF(attempts >= " . RECORDS_TRIES . ", 'failed', 'queued'), error = 'stopped while building'
               WHERE status = 'building' AND started_at < NOW() - INTERVAL 20 MINUTE");
    $ids = $db->query("SELECT id FROM records_books WHERE status = 'queued' AND COALESCE(started_at, created_at) < NOW() - INTERVAL 1 MINUTE
                       ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if (records_build($db, (int)$id)) $done['records_built']++;
    }
    foreach ($db->query("SELECT * FROM records_books WHERE status = 'ready' AND expires_at < NOW() LIMIT 200")->fetchAll() as $book) {
        if ($file = records_file($book)) @unlink($file);
        @unlink(BASE_PATH . '/storage/private/records/' . (int)$book['business_id'] . '/' . (int)$book['id'] . '.ledger.json.gz');
        $db->prepare("UPDATE records_books SET status = 'expired', file_path = NULL WHERE id = ?")->execute([$book['id']]);
        $done['records_expired']++;
    }
    // Paid books nobody finished paying for, after a day
    $db->exec("UPDATE records_books SET status = 'cancelled' WHERE status = 'awaiting_payment' AND created_at < NOW() - INTERVAL 1 DAY
               AND NOT EXISTS (SELECT 1 FROM subscription_payments sp WHERE sp.book_id = records_books.id AND sp.status = 'pending_verification')");
    require_once __DIR__ . '/record_requests.php';
    $done += record_requests_cron($db);
    return $done;
}

/* ═══════════════════════════════════════════════════════════════════
 *  CHECKING A CODE (nfelia.com/verify)
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * What a code is, for the verify page, or null when it is no code of ours
 * (or a record that isn't in that book):
 *   code       the code, written the standard way
 *   book       the records_books row; business, the business
 *   kept       false once the book's file and ledger are deleted (after a year)
 *   sheet_key  null for the whole book, else the sheet of the record
 *   record_id, label ("Invoice ADA-INV-0400", "Records Book of 19 Oct 2026")
 *   facts      what the book says (a record's facts, or the book's figures)
 *   now        a record's facts today (null when it was removed since)
 *   changes    [field => [then, now]] that differ since the book was made
 */
function records_verify_lookup(PDO $db, string $input): ?array
{
    $p = records_parse_code($input);
    if (!$p) return null;
    $stmt = $db->prepare("SELECT * FROM records_books WHERE code = ? AND kind = 'book' AND status IN ('ready', 'expired')");
    $stmt->execute([$p['book']]);
    $book = $stmt->fetch();
    if (!$book) return null;
    $business = records_business($db, (int)$book['business_id']);
    if (!$business) return null;
    $out = ['code' => $p['book'], 'book' => $book, 'business' => $business, 'kept' => $book['status'] === 'ready',
            'sheet_key' => null, 'record_id' => null, 'label' => 'Records Book of ' . date('j M Y', strtotime((string)$book['created_at'])),
            'facts' => json_decode((string)$book['facts'], true) ?: [], 'now' => null, 'changes' => []];
    if ($p['letter'] === null) {
        return $out;
    }
    $key = RECORDS_VERIFY[$p['letter']];
    $sheet = records_sheets()[$key];
    $entry = records_ledger($book)[$p['letter'] . $p['id']] ?? null;
    if (!$entry) {
        return $out['kept'] ? null : $out + ['gone_record' => true];
    }
    $now = records_record_now($db, $book, $key, (int)$p['id']);
    $changes = [];
    if ($now !== null && records_facts_hash($now) !== $entry['h']) {
        foreach ($entry['f'] as $field => $then) {
            if (records_facts_hash([$then]) !== records_facts_hash([$now[$field] ?? null])) {
                $changes[$field] = [$then, $now[$field] ?? null];
            }
        }
    }
    $number = $entry['f']['Number'] ?? $entry['f']['Invoice'] ?? '';
    return array_merge($out, [
        'code' => records_row_code($p['book'], $p['letter'], (int)$p['id']), 'sheet_key' => $key, 'record_id' => (int)$p['id'],
        'label' => trim($sheet['label'] . ' ' . $number), 'facts' => $entry['f'], 'now' => $now, 'changes' => $changes, 'removed' => $now === null,
    ]);
}

/** A record's facts as they are now, or null when it no longer exists. */
function records_record_now(PDO $db, array $book, string $key, int $id): ?array
{
    $sheet = records_sheets()[$key];
    [$sql, $args] = records_sheet_sql_one($sheet, (int)$book['business_id'], $id);
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    $row = $stmt->fetch();
    return $row ? ($sheet['facts'])($row) : null;
}

/**
 * One record, with its lines, as a small branded workbook for someone the
 * business approved (app/helpers/record_requests.php). False when the
 * record no longer exists.
 */
function records_extract_write(PDO $db, array $book, array $business, string $key, int $id, array $request, string $path): bool
{
    return (new RecordsBook($db, $book, $business, []))->extract($key, $id, $request, $path);
}
