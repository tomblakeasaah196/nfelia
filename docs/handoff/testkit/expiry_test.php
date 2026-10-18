<?php
/**
 * Tests the two jobs cron/planner.php took over: quotes turning Expired after
 * their date, and the bell note three days before a free trial ends. Also the
 * trial script run by hand, and that the web can no longer start either job.
 *
 *   php expiry_test.php APPDIR BASEURL
 *
 * APPDIR is the local copy of the app (its .env points at the local database,
 * with every migration applied); BASEURL serves that copy, for example
 *   php -S 127.0.0.1:8099 -t APPDIR APPDIR/docs/handoff/testkit/router.php
 * The planner runs under libfaketime. The database is put back as it was.
 */
[, $APP, $BASE] = $argv + [null, null, null];
if (!$APP || !$BASE) { fwrite(STDERR, "usage: php expiry_test.php APPDIR BASEURL\n"); exit(2); }
$BASE = rtrim($BASE, '/');

$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec("SET time_zone = '+01:00'");

$pass = $fail = 0;
function ok($cond, string $msg): void {
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $msg . "\n";
}
function q(string $sql, array $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function run(string $sql, array $p = []): void { global $db; $db->prepare($sql)->execute($p); }

/** Runs the planner at a Lagos time ("2026-11-10 23:30"); returns its counts, plus the raw line. */
function planner(string $at): array {
    global $APP;
    $out = trim((string)shell_exec('cd ' . escapeshellarg($APP) . " && TZ=Africa/Lagos faketime '{$at}:00' php cron/planner.php 2>&1"));
    preg_match_all('/(\w+)=(\d+)/', $out, $m);
    return array_map('intval', array_combine($m[1], $m[2])) + ['line' => $out];
}
function trials_by_hand(): string {
    global $APP;
    return trim((string)shell_exec('cd ' . escapeshellarg($APP) . ' && php cron/check_trials.php 2>&1'));
}
/** GET a local page: [status, body]. */
function get(string $path): array {
    global $BASE;
    $ch = curl_init($BASE . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}
function status(int $id): string { return (string)q("SELECT status FROM quotations WHERE id = ?", [$id]); }
function trial_notes(): array {
    global $db;
    return $db->query("SELECT business_id, title, message, action_url FROM notifications WHERE type = 'trial_expiring_soon' ORDER BY id")->fetchAll();
}

$snapshot = tempnam(sys_get_temp_dir(), 'nfelia-expiry-');
shell_exec('mysqldump --single-transaction --routines smartqaq_nfelia_core > ' . escapeshellarg($snapshot));
if (filesize($snapshot) < 10000) { fwrite(STDERR, "could not snapshot the database\n"); exit(2); }

try {
    echo "The notification type column\n";
    ok(q("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'type'") === 'varchar',
       'notifications.type is a plain string');
    try {
        run("INSERT INTO notifications (business_id, type, title, message) VALUES (1, 'referral_rewarded', 'Referral Reward Earned!', 'test')");
        ok(q("SELECT COUNT(*) FROM notifications WHERE type = 'referral_rewarded'") == 1, 'a referral reward note saves (the old list refused it)');
    } catch (PDOException $e) {
        ok(false, 'a referral reward note saves: ' . $e->getMessage());
    }

    // Every reminder already sent, so the planner's other jobs stay quiet at
    // 23:30 and only the two under test act.
    run("UPDATE tasks SET reminded_at = NOW() WHERE reminded_at IS NULL");
    run("UPDATE subscriptions SET status = 'active', trial_ends_at = NULL");
    run("DELETE FROM notifications WHERE type = 'trial_expiring_soon'");

    echo "Quotes past their date\n";
    $base_quote = (int)q("SELECT id FROM quotations ORDER BY id LIMIT 1");
    if (!$base_quote) throw new RuntimeException('the seed has no quote to copy');
    $quote = function (int $business, string $status, ?string $expiry, string $tag) use ($db, $base_quote): int {
        $db->prepare("
            INSERT INTO quotations (business_id, customer_id, user_id, doc_number, status, issue_date, expiry_date, subtotal, total, token)
            SELECT ?, customer_id, user_id, ?, ?, '2026-11-01', ?, subtotal, total, ? FROM quotations WHERE id = ?")
           ->execute([$business, 'TEST-' . $tag, $status, $expiry, bin2hex(random_bytes(16)), $base_quote]);
        return (int)$db->lastInsertId();
    };
    $q_yesterday = $quote(1, 'sent', '2026-11-09', 'Y');
    $q_today     = $quote(1, 'sent', '2026-11-10', 'T');
    $q_no_date   = $quote(1, 'sent', null, 'N');
    $q_talking   = $quote(1, 'in_negotiation', '2026-11-02', 'I');
    $q_signing   = $quote(1, 'pending_signature', '2026-11-02', 'P');
    $q_draft     = $quote(1, 'draft', '2026-11-02', 'D');
    $q_accepted  = $quote(1, 'accepted', '2026-11-02', 'A');
    $q_other_biz = $quote(2, 'sent', '2026-11-05', 'O');

    // Ours, plus any sent quote in the seed whose date has passed by then.
    $due = (int)q("SELECT COUNT(*) FROM quotations WHERE status = 'sent' AND expiry_date < '2026-11-10'");
    $r = planner('2026-11-10 23:30');
    ok($due >= 2 && ($r['quotes_expired'] ?? -1) === $due && ($r['errors'] ?? -1) === 0,
       "the run at 23:30 on 10 Nov expires the {$due} sent quotes past their date, no errors: " . $r['line']);
    ok(status($q_yesterday) === 'expired', 'a sent quote valid until 9 Nov is Expired');
    ok(status($q_other_biz) === 'expired', "another business's sent quote past its date is Expired too");
    ok(status($q_today) === 'sent', 'a quote valid until 10 Nov is still sent on 10 Nov');
    ok(status($q_no_date) === 'sent', 'a quote with no expiry date is left alone');
    ok(status($q_talking) === 'in_negotiation' && status($q_signing) === 'pending_signature',
       'a quote the customer replied to, or is signing, is left alone');
    ok(status($q_draft) === 'draft' && status($q_accepted) === 'accepted', 'drafts and accepted quotes are left alone');

    $r = planner('2026-11-10 23:35');
    ok(($r['quotes_expired'] ?? -1) === 0, 'the next run changes nothing');

    $r = planner('2026-11-11 00:05');
    ok(($r['quotes_expired'] ?? -1) === 1 && status($q_today) === 'expired', 'just after midnight, the quote valid until 10 Nov is Expired');

    echo "Free trials about to end\n";
    run("UPDATE subscriptions SET status = 'trial', trial_ends_at = DATE_ADD(NOW(), INTERVAL 2 DAY) WHERE business_id = 1");
    run("UPDATE subscriptions SET status = 'trial', trial_ends_at = DATE_ADD(NOW(), INTERVAL 5 DAY) WHERE business_id = 2");
    preg_match('/^APP_URL=["\']?([^"\'\r\n]*)/m', (string)file_get_contents("$APP/.env"), $env_url);
    $app_url = rtrim($env_url[1] ?? '', '/');

    $r = planner('2026-11-11 00:10');
    $notes = trial_notes();
    ok(($r['trial_notes'] ?? -1) === 1 && ($r['errors'] ?? -1) === 0, 'the planner saves one trial note: ' . $r['line']);
    ok(count($notes) === 1 && (int)$notes[0]['business_id'] === 1, 'for the business whose trial ends in two days, not the one with five');
    ok(($notes[0]['title'] ?? '') === 'Trial Ending Soon'
       && ($notes[0]['message'] ?? '') === 'Your free trial ends in 2 days. Subscribe to keep your business running on Nfelia.',
       'it reads: ' . ($notes[0]['message'] ?? '(none)'));
    ok(($notes[0]['action_url'] ?? '') === $app_url . '/settings?tab=billing', 'and opens Settings, Billing');

    $r = planner('2026-11-11 00:15');
    ok(($r['trial_notes'] ?? -1) === 0 && count(trial_notes()) === 1, 'the next run does not repeat it');
    $out = trials_by_hand();
    ok($out === 'Trial check complete: 0 notes saved.' && count(trial_notes()) === 1, "nor does the script run by hand: \"{$out}\"");

    // trial_ends_at is a date: the window is the trials ending tomorrow up to
    // three days from today.
    run("DELETE FROM notifications WHERE type = 'trial_expiring_soon'");
    run("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 1 DAY WHERE business_id = 1");
    $out = trials_by_hand();
    $notes = trial_notes();
    ok($out === 'Trial check complete: 1 note saved.', "by hand, it saves a note on its own: \"{$out}\"");
    ok(($notes[0]['message'] ?? '') === 'Your free trial ends tomorrow. Subscribe to keep your business running on Nfelia.',
       'a trial ending tomorrow: ' . ($notes[0]['message'] ?? '(none)'));

    run("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 3 DAY WHERE business_id = 2");
    $r = planner('2026-11-11 00:20');
    $notes = trial_notes();
    ok(($r['trial_notes'] ?? -1) === 1 && count($notes) === 2, 'the planner then tells the second business');
    ok(($notes[1]['message'] ?? '') === 'Your free trial ends in 3 days. Subscribe to keep your business running on Nfelia.',
       'a trial ending in three days: ' . ($notes[1]['message'] ?? '(none)'));

    run("DELETE FROM notifications WHERE type = 'trial_expiring_soon'");
    run("UPDATE subscriptions SET trial_ends_at = CURDATE() WHERE business_id = 1");
    run("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 4 DAY WHERE business_id = 2");
    $r = planner('2026-11-11 00:22');
    ok(($r['trial_notes'] ?? -1) === 0, 'a trial ending today, or in four days, is outside the window');
    run("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 1 DAY WHERE business_id = 1");
    run("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 1 DAY WHERE business_id = 2");

    run("UPDATE subscriptions SET status = 'active' WHERE business_id = 1");
    run("DELETE FROM notifications WHERE type = 'trial_expiring_soon'");
    $r = planner('2026-11-11 00:25');
    ok(($r['trial_notes'] ?? -1) === 1 && (int)(trial_notes()[0]['business_id'] ?? 0) === 2, 'a business that has subscribed gets no trial note');

    echo "The web cannot start either job\n";
    run("DELETE FROM notifications WHERE type = 'trial_expiring_soon'");
    $q_web = $quote(1, 'sent', '2026-10-01', 'W');
    [$code] = get('/cron/check_trials.php');
    ok($code === 404 && count(trial_notes()) === 0, "opening /cron/check_trials.php answers {$code} and saves no note");
    [$code] = get('/cron/planner.php');
    ok($code === 404, "opening /cron/planner.php answers {$code}");
    [$code, $body] = get('/cron/expire/' . bin2hex(random_bytes(16)));
    [$never]       = get('/cron/never-existed');
    ok($code === $never && stripos($body, 'cron secret') === false && status($q_web) === 'sent',
       "the old /cron/expire/… address answers like one that never existed ({$code}) and expires nothing");
    [$code] = get('/login');
    ok($code === 200, "other pages still open (/login answers {$code})");
} catch (Throwable $e) {
    ok(false, 'the test stopped: ' . $e->getMessage());
} finally {
    shell_exec('mysql smartqaq_nfelia_core < ' . escapeshellarg($snapshot));
    unlink($snapshot);
}

echo "RESULT: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
