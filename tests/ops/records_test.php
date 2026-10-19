<?php
/**
 * The Records Book (docs/handoff/Records Book.md): Your records in the app
 * (a book free once in a paid month, paid for otherwise; the plain copy
 * free for everyone), Records Books in Ops (Support makes one and it goes
 * to the owner; only Admins download), nfelia.com/verify and asking for a
 * document (Admins in Ops ask, the owner decides), and prices changed in
 * Ops with notice, everywhere at once.
 *   bash tests/notifications/run.sh ops/records
 */
require __DIR__ . '/lib.php';
require_once BASE_PATH . '/app/helpers/records.php';
require_once BASE_PATH . '/app/helpers/record_requests.php';
t_guard();
fixture_reset();
clean_slate();

use PhpOffice\PhpSpreadsheet\IOFactory;

const ADA = 20, CHIDI = 22, KOLA = 30;
$db    = get_db();
$grace = as_ops('grace@nfelia.test');
$sam   = as_ops('sam@nfelia.test');
$lola  = as_ops('lola@nfelia.test');
$all   = array_keys(array_filter(records_sheets(), fn($s) => !isset($s['line_of'])));

/** A downloaded workbook, opened: [sheet title => all its cells as text]. */
function workbook(string $bytes): array
{
    $file = tempnam(sys_get_temp_dir(), 'nfbook') . '.xlsx';
    file_put_contents($file, $bytes);
    $out = [];
    foreach (IOFactory::load($file)->getAllSheets() as $ws) {
        $text = [];
        foreach ($ws->toArray(null, false, false, false) as $row) $text[] = implode(' | ', array_map('strval', $row));
        $out[$ws->getTitle()] = implode("\n", $text);
    }
    @unlink($file);
    return $out;
}

/** A downloaded ZIP: [file name => contents]. */
function zip_files(string $bytes): array
{
    $file = tempnam(sys_get_temp_dir(), 'nfzip');
    file_put_contents($file, $bytes);
    $zip = new ZipArchive();
    $zip->open($file);
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) $out[$zip->getNameIndex($i)] = (string)$zip->getFromIndex($i);
    $zip->close();
    @unlink($file);
    return $out;
}

function book_row(int $id): array
{
    return rows("SELECT * FROM records_books WHERE id = ?", [$id])[0] ?? [];
}

function newest_book(int $business, string $kind = 'book'): array
{
    return rows("SELECT * FROM records_books WHERE business_id = ? AND kind = ? ORDER BY id DESC LIMIT 1", [$business, $kind])[0] ?? [];
}

/**
 * A book once it is made. The page answers first and makes the book just
 * after (after_response), as the live server does, so this waits until the
 * build is over and, when it worked, the owner has been told.
 */
function built(array $book): array
{
    $id = (int)($book['id'] ?? 0);
    $until = microtime(true) + 60;
    do {
        $b = book_row($id);
        $over = $b && !in_array($b['status'], ['queued', 'building'], true);
        if ($over && ($b['status'] !== 'ready' || (int)q("SELECT COUNT(*) FROM notifications WHERE dedupe_key LIKE ?", ['%:book:' . $id]))) {
            return $b;
        }
        usleep(100000);
    } while (microtime(true) < $until);
    fwrite(STDERR, "Book {$id} is still " . ($b['status'] ?? 'missing') . ' after a minute (error: ' . ($b['error'] ?? '') . '). Last recorded: '
                   . json_encode(rows("SELECT level, message, file, line FROM error_groups ORDER BY id DESC LIMIT 3"), JSON_UNESCAPED_SLASHES) . "\n");
    return $b;
}

function last_mail(string $address): array
{
    $all = mails_to($address);
    return $all ? $all[count($all) - 1] : [];
}

function is_xlsx(Http $h): bool
{
    return $h->status === 200 && str_contains($h->headers['content-type'] ?? '', 'spreadsheetml') && str_starts_with($h->body, 'PK');
}

/* ── Your records, in the app ───────────────────────────────── */

section('Your records: the owner\'s Records Book, free once in a paid month');

$ada = signin('ada@adashair.test');
$ada->get('records');
eq($ada->status, 200, 'Your records opens for the owner');
ok(str_contains($ada->body, 'Free this month'), 'a paying business is offered this month\'s book free');
$chidi = signin('chidi@adashair.test');
$chidi->get('records');
eq($chidi->to(), 'dashboard', 'staff can\'t take the business\'s records out');

$ada->post('records/make', ['sheets' => $all, 'period' => 'all']);
$book = built(newest_book(2));
eq([$book['status'] ?? null, $book['charge'] ?? null], ['ready', 'free'], 'the book is made at once, free with the plan');
ok(($file = records_file($book)) && hash_file('sha256', $file) === $book['sha256'], 'the file is kept, with the fingerprint nfelia.com/verify checks');
eq(told('records.ready'), [ADA], 'the owner is told it is ready');

$ada->get('records/download/' . $book['id']);
ok(is_xlsx($ada), 'it downloads as an Excel workbook');
$sheets = workbook($ada->body);
ok(isset($sheets['Cover'], $sheets['Contents'], $sheets['Customers'], $sheets['Invoices']), 'with a cover, contents, and a sheet for each kind of record');
ok(str_contains($sheets['Customers'], 'Ngozi Eze') && str_contains($sheets['Invoices'], 'ADA-INV-0400'), 'holding Ada\'s customers and invoices');
ok(!str_contains(implode("\n", $sheets), 'Kola Customer') && !str_contains(implode("\n", $sheets), 'Kola Prints'), 'and nothing of any other business');
ok(str_contains($sheets['Cover'], records_code_display($book['code'])), 'the cover carries the book\'s code');
$invoices = IOFactory::load(records_file($book))->getSheetByName('Invoices');
$heads = [];
foreach (range(1, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($invoices->getHighestColumn())) as $col) {
    $heads[(string)$invoices->getCell([$col, 4])->getValue()] = $invoices->getStyle([$col, 4])->getFont()->getColor()->getRGB();
}
ok(isset($heads['Open'], $heads['Verify']) && count(array_unique($heads)) === 1, 'every column title shows on the header, the Open and Verify ones too');
eq((int)q("SELECT downloads FROM records_books WHERE id = ?", [$book['id']]), 1, 'downloads are counted');

$offer = records_offer($db, 2);
eq([$offer['free'], $offer['price']], [false, 7500.0], 'a second book in the same month is ₦7,500');
$ada->get('records');
ok(str_contains($ada->body, 'You made this month\'s free book'), 'and the page says so');
$kola = signin('kola@kolaprints.test');
$kola->get('records/download/' . $book['id']);
eq($kola->status, 404, 'another business can\'t download it');
eq([records_offer($db, 3)['free'], records_offer($db, 3)['access']], [false, 'trial'], 'on a free trial, a book is paid for');

section('Paid for by Mobile Money, made once Ops approves');

exec_sql("UPDATE businesses SET country_id = 2 WHERE id = 3");
exec_sql("UPDATE subscriptions SET country_id = 2, payment_method = 'mobile_money' WHERE business_id = 3");
$kola->get('records');
ok(str_contains($kola->body, 'GH₵110'), 'Kola, now in Ghana, sees Ghana\'s price');
$kola->post('records/make', ['sheets' => ['customers', 'invoices'], 'period' => 'all']);
$paid = newest_book(3);
eq([$paid['status'] ?? null, $kola->to()], ['awaiting_payment', 'momo/checkout?kind=book&book=' . ($paid['id'] ?? 0)], 'the book waits for payment, and Kola is sent to pay by Mobile Money');
$kola->get($kola->to());
ok($kola->status === 200 && str_contains($kola->body, '110'), 'the checkout asks for the book\'s price');
$png = tempnam(sys_get_temp_dir(), 'nfrcpt') . '.png';
$img = imagecreatetruecolor(4, 4);
imagepng($img, $png);
$kola->post('momo/submit', ['kind' => 'book', 'book' => $paid['id'], 'reference_number' => 'GH-BOOK-1', 'receipt' => new CURLFile($png, 'image/png', 'receipt.png')]);
$receipt = rows("SELECT * FROM subscription_payments WHERE business_id = 3 ORDER BY id DESC LIMIT 1")[0] ?? [];
eq([$receipt['kind'] ?? null, (int)($receipt['book_id'] ?? 0), (float)($receipt['amount'] ?? 0)], ['book', (int)$paid['id'], 110.0], 'the receipt is for the book, at its price');
$grace->get(o('payments'));
ok(str_contains($grace->body, 'Records Book'), 'Ops sees it is for a Records Book');
$grace->post(o('payments/' . $receipt['id'] . '/approve'));
$paid = built($paid);
eq([$paid['status'], $paid['charge'], (float)$paid['amount']], ['ready', 'paid', 110.0], 'approved: the book is made, as bought');
ok(in_array(KOLA, told('records.paid'), true) && in_array(KOLA, told('records.ready'), true), 'Kola hears the payment came in, then that it is ready');
exec_sql("UPDATE businesses SET country_id = 1 WHERE id = 3");
exec_sql("UPDATE subscriptions SET country_id = 1, payment_method = NULL WHERE business_id = 3");

section('The plain copy: free for everyone, always');

exec_sql("UPDATE subscriptions SET trial_ends_at = CURDATE() - INTERVAL 20 DAY WHERE business_id = 3");
$kola->get('records');
eq($kola->status, 200, 'Your records stays open after the trial ends');
$kola->post('records/plain');
$raw = built(newest_book(3, 'raw'));
eq([$raw['status'] ?? null, $raw['charge'] ?? null], ['ready', 'raw'], 'the plain copy is made at once, free');
$kola->get('records/download/' . $raw['id']);
$files = zip_files($kola->body);
ok(isset($files['customers.csv'], $files['READ ME.txt']) && str_contains($files['customers.csv'], 'Kola Customer'), 'a ZIP of CSV files, one per table, with a READ ME');
ok(!str_contains(implode("\n", $files), 'Ngozi Eze'), 'only Kola\'s own records');
ok(!str_contains($files['users.csv'] ?? '', 'password_hash') && !str_contains($files['users.csv'] ?? '', '$2y$'), 'no password hashes');
$ada->post('records/plain');
$files = zip_files(($ada->get('records/download/' . built(newest_book(2, 'raw'))['id']))->body);
ok(!str_contains($files['invoices.csv'] ?? '', (string)q("SELECT token FROM invoices WHERE id = 400")), 'nor the secret links of documents');
exec_sql("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 10 DAY WHERE business_id = 3");
ok(str_contains($files['invoices.csv'] ?? '', 'ADA-INV-0400') && str_contains($files['invoices.csv'] ?? '', 'currency') === str_contains((string)q("SHOW COLUMNS FROM invoices LIKE 'currency%'"), 'currency'),
   'but every ordinary column');

/* ── Records Books in Ops ───────────────────────────────────── */

section('Records Books in Ops: Support makes one, it goes to the owner');

$lola->get(o('records'));
eq($lola->status, 403, 'Sales can\'t make Records Books');
$sam->get(o('records/new?q=ada'));
ok($sam->status === 200 && str_contains($sam->body, 'Ada&#039;s Hair'), 'Support finds a client');
$sam->get(o('records/new?client=2'));
ok(str_contains($sam->body, 'Customers') && !str_contains($sam->body, 'send_to_owner'), 'the wizard lists the kinds of records; Support can\'t keep it from the owner');
$before = (int)q("SELECT COUNT(*) FROM records_books");
$sam->post(o('records'), ['client' => 2, 'sheets' => $all, 'reason' => '']);
eq((int)q("SELECT COUNT(*) FROM records_books"), $before, 'a reason is needed (the owner is told it)');
clean_slate();
$sam->post(o('records'), ['client' => 2, 'sheets' => $all, 'period' => 'all', 'reason' => 'Ada asked on WhatsApp, for a bank loan']);
$team = built(newest_book(2));
eq([$team['status'], $team['charge'], (int)$team['staff_id'], (int)$team['send_to_owner'], ops_to($sam)], ['ready', 'team', 2, 1, 'records/' . $team['id']], 'made by the team, sent to the owner');
$bell = bell(ADA, 'records.team_built')[0] ?? [];
ok($bell && str_contains($bell['message'], 'Sam Support') && str_contains($bell['message'], 'bank loan'), 'Ada is told who made it and why');
eq(count(audit_rows('records.made')), 1, 'it is in the audit log');
$sam->get(o('records/' . $team['id']));
ok($sam->status === 200 && !str_contains($sam->body, '/download'), 'Support sees it, with no download');
$sam->get(o('records/' . $team['id'] . '/download'));
eq($sam->status, 403, 'and can\'t download it');
$grace->get(o('records/' . $team['id'] . '/download'));
ok(is_xlsx($grace), 'an Admin can');
eq(count(audit_rows('records.downloaded')), 1, 'and that is in the audit log too');
$grace->post(o('records'), ['client' => 3, 'sheets' => ['customers'], 'period' => 'all', 'reason' => 'Checking a support ticket']);
eq((int)built(newest_book(3))['send_to_owner'], 0, 'an Admin may make one without sending it');
$sam->get(o('clients/2'));
ok(str_contains($sam->body, 'Make a Records Book'), 'the client\'s page links to it');
$sam->get(o(''));
ok(str_contains($sam->body, 'Records Books') && !str_contains($sam->body, 'Document requests') && !str_contains($sam->body, '>Prices<'), 'Support\'s menu has Records Books, not requests or prices');
$grace->get(o(''));
ok(str_contains($grace->body, 'Document requests') && str_contains($grace->body, '>Prices<'), 'an Admin\'s has all three');

/* ── nfelia.com/verify ──────────────────────────────────────── */

section('nfelia.com/verify: anyone checks a code');

$code = $book['code'];
$v = new Http();
$v->get('verify/' . $code);
ok($v->status === 200 && str_contains($v->body, 'Genuine') && str_contains($v->body, 'Ada&#039;s Hair'), 'a book\'s code shows it is genuine, and whose');
$inv = records_row_code($code, 'I', 400);
$v->get('verify/' . $inv);
ok($v->status === 200 && str_contains($v->body, 'ADA-INV-0400') && str_contains($v->body, 'Nothing about it has changed'), 'one invoice\'s code shows its facts');
ok(!str_contains($v->body, '08031110000') && !str_contains($v->body, 'ngozi@example.test') && !str_contains($v->body, 'Allen Avenue'), 'never the customer\'s phone, email or address');
exec_sql("UPDATE invoices SET total = 120000, balance_due = 120000 WHERE id = 400");
$v->get('verify/' . $inv);
ok(str_contains($v->body, 'Changed since the book was made'), 'a record changed since says so');
$v->post('verify', ['code' => strtolower(substr($code, 0, 5) . ' ' . substr($code, 5))]);
eq([$v->status, $v->location], [303, records_verify_url($code)], 'a code typed however is found');
$v->get('verify/ZZZZZZZZZZ');
eq($v->status, 404, 'an unknown code says so');
$v->get('verify');
$copy = tempnam(sys_get_temp_dir(), 'nfchk') . '.xlsx';
copy(records_file($book), $copy);
$v->post('verify/check', ['book' => new CURLFile($copy, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'book.xlsx')]);
ok(str_contains($v->body, 'This file is exactly as Nfelia made it'), 'the file itself checks out, unchanged');
file_put_contents($copy, 'x', FILE_APPEND);
$v->post('verify/check', ['book' => new CURLFile($copy, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'book.xlsx')]);
ok(str_contains($v->body, 'We can\'t match this file'), 'a changed file does not');

section('Asking for the full document: Admins ask, the owner decides');

clean_slate();
$ask = ['name' => 'Funmi Bello', 'email' => 'loans@bank.test', 'organisation' => 'Zenith Loans', 'role' => 'bank', 'reason' => 'Loan application LA-2291 for this business'];
$v->get('verify/' . $inv);
$v->post('verify/' . $inv . '/ask', $ask);
eq([$v->status, (int)q("SELECT COUNT(*) FROM record_requests")], [422, 0], 'they must agree the business sees who asked');
$v->post('verify/' . $inv . '/ask', $ask + ['agree' => '1', 'website' => 'spam.example']);
eq((int)q("SELECT COUNT(*) FROM record_requests"), 0, 'robots are quietly turned away');
$v->post('verify/' . $inv . '/ask', $ask + ['agree' => '1']);
$rq = rows("SELECT * FROM record_requests ORDER BY id DESC LIMIT 1")[0] ?? [];
eq([$v->status, $rq['status'] ?? null, $rq['label'] ?? null], [303, 'unconfirmed', 'Invoice ADA-INV-0400'], 'a request waits until they confirm their email');
$confirm = link_in_mail('loans@bank.test', '#https?://[^"\s<]+/verify/confirm/[A-Za-z0-9_-]+#');
ok($confirm !== '', 'they get a link to confirm');
$v->get($confirm);
ok($v->status === 200 && str_contains($v->body, 'your request is in'), 'confirmed');
eq([(string)q("SELECT status FROM record_requests WHERE id = ?", [$rq['id']]), ops_told('records.request')], ['new', [1]], 'and only the Admins in Ops are told');

$sam->get(o('requests'));
eq($sam->status, 403, 'Support can\'t open requests');
$grace->get(o('requests'));
ok(str_contains($grace->body, 'Zenith Loans'), 'an Admin sees it');
$grace->get(o('requests/' . $rq['id']));
ok(str_contains($grace->body, 'Loan application LA-2291'), 'with why they want it');
$grace->post(o('requests/' . $rq['id'] . '/ask'));
eq([(string)q("SELECT status FROM record_requests WHERE id = ?", [$rq['id']]), (int)q("SELECT asked_by FROM record_requests WHERE id = ?", [$rq['id']])], ['asked', 1], 'one click asks the client');
eq(told('records.request'), [ADA], 'the owner has it in the app');
ok(count(outbox('records.request', 'email')) === 1, 'and by email');
eq(count(audit_rows('records.request_asked')), 1, 'it is in the audit log');

$chidi->get('records/request/' . $rq['id']);
eq($chidi->to(), 'dashboard', 'staff can\'t answer it');
$kola->get('records/request/' . $rq['id']);
eq($kola->status, 404, 'nor can another business');
$ada->get('records/request/' . $rq['id']);
ok($ada->status === 200 && str_contains($ada->body, 'Approve and share it') && str_contains($ada->body, 'Zenith Loans'), 'the owner sees who, why, and the two buttons');
$ada->post('records/answer/' . $rq['id'], ['answer' => 'approve', 'note' => 'Happy to help']);
$rq = rows("SELECT * FROM record_requests WHERE id = ?", [$rq['id']])[0];
eq([$rq['status'], (int)$rq['decided_by_user']], ['approved', ADA], 'approved, and the record shows the owner did it');
$file_link = link_in_mail('loans@bank.test', '#https?://[^"\s<]+/verify/file/[A-Za-z0-9_-]+#');
ok($file_link !== '' && str_contains(last_mail('loans@bank.test')['text'] ?? '', 'Happy to help'), 'they get a download link, with the owner\'s note');
$v->get($file_link);
ok(is_xlsx($v), 'the link downloads it');
$one = workbook($v->body);
ok(str_contains(implode("\n", $one), 'ADA-INV-0400') && !str_contains(implode("\n", $one), 'ADA-INV-0401'), 'that one invoice, and no other record');
eq((int)q("SELECT downloads FROM record_requests WHERE id = ?", [$rq['id']]), 1, 'the download is recorded');
$ada->post('records/answer/' . $rq['id'], ['answer' => 'decline']);
eq((string)q("SELECT status FROM record_requests WHERE id = ?", [$rq['id']]), 'approved', 'an answer is given once');

$v->get('verify/' . $code);
$v->post('verify/' . $code . '/ask', ['name' => 'Junk Mail', 'email' => 'junk@spam.test', 'role' => 'other', 'reason' => 'Send me everything you have please', 'agree' => '1']);
$v->get(link_in_mail('junk@spam.test', '#https?://[^"\s<]+/verify/confirm/[A-Za-z0-9_-]+#'));
$junk = (int)q("SELECT id FROM record_requests WHERE email = 'junk@spam.test'");
$grace->post(o('requests/' . $junk . '/decline'), ['note' => '']);
eq((string)q("SELECT status FROM record_requests WHERE id = ?", [$junk]), 'new', 'turning one down needs a note');
$grace->post(o('requests/' . $junk . '/decline'), ['note' => 'We could not confirm who you are.']);
eq([(string)q("SELECT status FROM record_requests WHERE id = ?", [$junk]), (int)q("SELECT decided_by_staff FROM record_requests WHERE id = ?", [$junk])], ['declined', 1], 'Ops turns down junk without bothering the owner');
ok(str_contains(last_mail('junk@spam.test')['text'] ?? '', 'We could not confirm who you are'), 'and they are told why');
eq(count(bell(ADA, 'records.request')), 1, 'Ada was never asked about it');

$v->post('verify/' . $code . '/ask', ['name' => 'Slow Bank', 'email' => 'slow@bank.test', 'role' => 'bank', 'reason' => 'Account review for this business', 'agree' => '1']);
$v->get(link_in_mail('slow@bank.test', '#https?://[^"\s<]+/verify/confirm/[A-Za-z0-9_-]+#'));
exec_sql("UPDATE record_requests SET confirmed_at = NOW() - INTERVAL 15 DAY WHERE email = 'slow@bank.test'");
records_cron($db);
eq((string)q("SELECT status FROM record_requests WHERE email = 'slow@bank.test'"), 'expired', 'a request nobody answers closes after two weeks');

/* ── Prices ─────────────────────────────────────────────────── */

section('Prices: changed in one place, everywhere, with notice');

$sam->get(o('prices'));
eq($sam->status, 403, 'only Admins change prices');
$grace->get(o('prices'));
ok($grace->status === 200 && str_contains($grace->body, '₦10,000') && str_contains($grace->body, '₦7,500'), 'today\'s prices, the Records Book among them');
$changes = fn() => (int)q("SELECT COUNT(*) FROM price_changes");
$grace->post(o('prices'), ['country' => 1, 'month' => '10000', 'seat' => '5000', 'book' => '7500']);
eq($changes(), 0, 'nothing changes when nothing is different');
$grace->post(o('prices'), ['country' => 1, 'month' => '12000', 'seat' => '5000', 'book' => '7500', 'new_from' => date('Y-m-d'), 'existing_from' => date('Y-m-d', strtotime('-1 day'))]);
eq($changes(), 0, 'clients already here can\'t get less notice than nothing');
$grace->post(o('prices'), ['country' => 1, 'month' => '12,000', 'seat' => '5000', 'book' => '7500', 'new_from' => date('Y-m-d'),
                           'existing_from' => date('Y-m-d', strtotime('+30 days')), 'reason' => 'To add delivery tracking and more ways to get paid.']);
$change = rows("SELECT * FROM price_changes ORDER BY id DESC LIMIT 1")[0] ?? [];
eq([(float)$change['month_price'], $change['seat_price'], $change['book_price'], (float)$change['old_month'], ops_to($grace)],
   [12000.0, null, null, 10000.0, 'prices/' . $change['id']], 'a month goes to ₦12,000; the rest is unchanged');
eq(count(audit_rows('prices.changed')), 1, 'it is in the audit log');

$visitor = new Http();
$visitor->get('');
ok(str_contains($visitor->body, '₦12,000') && !str_contains($visitor->body, '₦10,000'), 'the website shows the new price at once');
$nigeria = prices_country($db, 'NG');
eq(prices_for($db, $nigeria, 'month', null), 12000.0, 'and someone signing up now pays it');
$p = billing_prices($db, 2);
eq([$p['monthly'], $p['coming']['month']['amount'] ?? null], [10000.0, 12000.0], 'Ada, already here, keeps ₦10,000 for her next month, and sees what is coming');
$ada->get('settings?tab=billing');
ok(str_contains($ada->body, 'Our prices are changing'), 'Billing tells her');
fixture_subscription(2, ['current_period_end' => date('Y-m-d', strtotime('+40 days'))]);
eq(billing_prices($db, 2)['monthly'], 12000.0, 'a month that starts after the notice date is at the new price');
fixture_subscription(2, ['current_period_end' => date('Y-m-d', strtotime('+20 days'))]);

clean_slate();
$grace->get(o('prices/' . $change['id']));
ok(str_contains($grace->body, 'Tell clients') && str_contains($grace->body, 'Paying clients'), 'the change page offers to tell clients');
ok(str_contains($grace->body, 'Subject: <b>Your Nfelia price from'), 'and shows the email\'s subject');
ok(str_contains(html_entity_decode($grace->body), 'Since ') && str_contains(html_entity_decode($grace->body), '₦12,000'), 'the email starts with what they built with Nfelia, then the new price');
$grace->post(o('prices/' . $change['id'] . '/test'));
ok(str_starts_with(last_mail('grace@nfelia.test')['subject'] ?? '', '[Test] Your Nfelia price from'), 'a test comes to the Admin first');
$grace->post(o('prices/' . $change['id'] . '/notify'), ['groups' => ['paying', 'trial']]);
eq(array_map('intval', array_column(rows("SELECT business_id FROM price_notices ORDER BY business_id"), 'business_id')), [1, 2, 3], 'telling clients: every paying business and every trial');
$grace->post(o('prices/' . $change['id'] . '/notify'), ['groups' => ['paying']]);
eq((int)q("SELECT COUNT(*) FROM price_notices"), 3, 'once only');
eq(prices_notice_drip($db, 10), 3, 'the cron hands them to the outbox');
deliver();
$mail = last_mail('ada@adashair.test');
ok(str_contains($mail['subject'] ?? '', 'Your Nfelia price from'), 'Ada gets it');
ok(str_contains($mail['text'] ?? '', '3 invoices') && str_contains($mail['text'] ?? '', 'Pay for my next month'), 'with what she has made, and how to keep today\'s price');
ok(str_contains(last_mail('kola@kolaprints.test')['text'] ?? '', 'Pay for a month before'), 'on a trial: pay for a first month before the date');
fixture_subscription(2, ['current_period_end' => date('Y-m-d', strtotime('+40 days'))]);
$late = prices_notice_vars($db, $change, $nigeria, 2, 'Ada');
$html = render_email('price_notice', $late);
ok(!$late['hold'] && str_contains($html, 'the new price starts with your month from') && !str_contains($html, 'Pay for my next month'),
   'a client whose next month starts after the date is never told paying early keeps the old price');
fixture_subscription(2, ['current_period_end' => date('Y-m-d', strtotime('+20 days'))]);
eq(count(bell(ADA, 'billing.price_change')), 1, 'and a note in the app');
prices_notice_drip($db, 10);
eq((int)q("SELECT COUNT(*) FROM price_notices WHERE status = 'sent'"), 3, 'and on the next run, each notice is marked delivered');

$grace->post(o('prices'), ['country' => 1, 'month' => '12000', 'seat' => '5000', 'book' => '5000', 'new_from' => date('Y-m-d', strtotime('+3 days')), 'existing_from' => date('Y-m-d', strtotime('+3 days'))]);
$cut = rows("SELECT * FROM price_changes ORDER BY id DESC LIMIT 1")[0];
eq(prices_change_state($cut), 'scheduled', 'a change can start later');
ok(str_starts_with(prices_notice_vars($db, $cut, $nigeria, 2, 'Ada')['subject'], 'Good news'), 'a lower price is told as good news');
$grace->post(o('prices/' . $cut['id'] . '/cancel'));
eq([q("SELECT cancelled_at IS NOT NULL FROM price_changes WHERE id = ?", [$cut['id']]), prices_latest($db, $nigeria)['book']], [1, 7500.0], 'a change nobody pays yet can be called off');
$grace->post(o('prices'), ['country' => 1, 'month' => '12000', 'seat' => '6000', 'book' => '7500', 'new_from' => date('Y-m-d'), 'existing_from' => date('Y-m-d')]);
$live = rows("SELECT * FROM price_changes ORDER BY id DESC LIMIT 1")[0];
$grace->post(o('prices/' . $live['id'] . '/cancel'));
eq([prices_change_state($live), (int)q("SELECT cancelled_at IS NULL FROM price_changes WHERE id = ?", [$live['id']])], ['live', 1], 'one clients already pay can\'t: a new change undoes it');
$grace->get(o('countries'));
ok(str_contains($grace->body, 'Change them in Prices') && !str_contains($grace->body, 'name="monthly_price" inputmode="decimal" value='), 'Countries points to Prices instead of its own price boxes');

done();
