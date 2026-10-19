<?php
/**
 * Nfelia Ops, the monitor: errors recorded where they happen (the real app,
 * broken on purpose by tests/ops/boom.php), grouped, coded, private; the
 * team told on their phones and bells; came back, spiking, ignored; browser
 * errors; the server log; the cron's heartbeat and the watchdog; health;
 * failed sends; the morning summary; the Ops pages for all of it.
 *   bash tests/notifications/run.sh ops/monitor
 */
require __DIR__ . '/lib.php';
require_once dirname(__DIR__, 2) . '/app/ops/monitor.php';
t_guard();
fixture_reset();
monitor_slate();

const GRACE = 1, SAM = 2, LOLA = 3;
$grace_phone = ops_phone(GRACE);
$sam_phone   = ops_phone(SAM);
$lola_phone  = ops_phone(LOLA);

/* ── Recording ──────────────────────────────────────────────── */

section('A server error is recorded, with a code the person can quote');

$h = boom('exception');
eq($h->status, 500, 'the page answers 500');
$ref = $h->headers['x-nfelia-error'] ?? '';
ok((bool)preg_match('/^E[A-Z2-9]{7}$/', $ref), 'with its error code in a header: ' . $ref);
ok(str_contains($h->body, 'Something went wrong on our side') && str_contains($h->body, $ref), 'and on the page, in plain words, with the code');
ok(!str_contains($h->body, 'boom.php') && !str_contains($h->body, 'RuntimeException'), 'the page shows nothing of the code behind it');
$g = group_of($ref);
ok($g !== null, 'the problem is recorded');
eq([$g['kind'] ?? null, $g['level'] ?? null, $g['file'] ?? null], ['php', 'error', 'tests/ops/boom.php'], 'as a server error, with the file inside the app (no server path)');
ok((int)($g['line'] ?? 0) > 0, 'and the line');
ok(str_contains($g['title'] ?? '', '<email>') && str_contains($g['title'] ?? '', '<phone>') && !str_contains($g['title'] ?? '', 'ada@example.test'), 'the email and phone number in the message are masked');
$ev = rows("SELECT * FROM error_events WHERE ref = ?", [$ref])[0] ?? [];
eq($ev['path'] ?? null, '/__boom/exception', 'the page it happened on');
eq($ev['source'] ?? null, 'web', 'from the web');
ok(str_contains((string)($ev['trace'] ?? ''), 'boom_throw()') && !str_contains((string)($ev['trace'] ?? ''), BASE_PATH), 'with a stack trace, short paths, no arguments');

section('The Ops team is told: Admin and Support, on their phones and bells');

eq(count(ops_bell(GRACE)), 1, 'Grace (Admin) has it in her bell');
eq(count(ops_bell(SAM)), 1, 'Sam (Support) too');
eq(count(ops_bell(LOLA)), 0, 'not Lola (Sales)');
eq(ops_bell(GRACE)[0]['title'] ?? '', 'New: Error', 'titled "New: Error"');
eq(ops_bell(GRACE)[0]['url'] ?? '', 'errors/' . $g['id'] . '?ref=' . $ref, 'linking to the problem, on this occurrence');
$push = pushes_to($grace_phone);
eq(count($push), 1, 'Grace\'s phone gets it');
eq($push[0]['title'] ?? '', 'New: Error', 'with the same title');
ok(str_starts_with($push[0]['url'] ?? '', ops_origin() . '/alerts/open/'), 'and a link that opens the alert in Ops');
eq((int)($push[0]['badge'] ?? -1), 1, 'and her unread count for the badge');
eq(count(pushes_to($lola_phone)), 0, 'Lola\'s phone gets nothing');

section('The same problem again is counted, not announced again');

$h2 = boom('exception/' . str_repeat('a1', 15) . '?token=secret&email=x');
$ref2 = $h2->headers['x-nfelia-error'] ?? '';
eq(group_of($ref2)['id'] ?? null, $g['id'], 'the same group');
eq((int)q("SELECT events FROM error_groups WHERE id = ?", [$g['id']]), 2, 'counted twice');
eq(count(ops_bell(GRACE)), 1, 'no second alert');
eq((string)q("SELECT path FROM error_events WHERE ref = ?", [$ref2]), '/__boom/exception/:token?token=…&email=…', 'tokens in the address and every query value are left out');

section('From a script, the answer is JSON with the code');

$j = boom('exception', null, ['X-Requested-With: XMLHttpRequest', 'Accept: application/json']);
$data = json_decode($j->body, true) ?: [];
ok($j->status === 500 && isset($data['error_code']) && str_contains($data['message'] ?? '', $data['error_code']), 'JSON with the code, and a message that says it');

section('A warning is recorded and the page carries on');

$w = boom('warning');
eq([$w->status, trim($w->body)], [200, 'carried on'], 'the page still works');
$wg = rows("SELECT * FROM error_groups WHERE level = 'warning' AND kind = 'php'")[0] ?? null;
ok($wg && str_contains($wg['title'], 'Undefined array key "missing_key"'), 'the warning is recorded: ' . ($wg['title'] ?? 'none'));
ok(str_contains(ops_bell(GRACE)[1]['title'] ?? '', 'New: Warning'), 'Grace\'s bell has it');
eq(count(pushes_to($grace_phone)), 1, 'but no phone buzz for a warning');

section('A crash (out of memory) is recorded too');

$f = boom('fatal');
eq($f->status, 500, 'the page answers 500');
$fref = $f->headers['x-nfelia-error'] ?? (preg_match('/id="ref">(E[A-Z0-9]{7})/', $f->body, $m) ? $m[1] : '');
ok(preg_match('/E[A-Z2-9]{7}/', $f->body) === 1, 'the error page shows a code');
$fg = rows("SELECT * FROM error_groups WHERE level = 'fatal'")[0] ?? null;
ok($fg && str_contains($fg['title'], 'Allowed memory size'), 'recorded as a crash: ' . ($fg['title'] ?? 'none'));
eq(count(pushes_to($grace_phone)), 2, 'and phones are told');
ok(str_contains(pushes_to($grace_phone)[1]['title'] ?? '', 'New: Crash'), 'as a crash');

section('An error inside a save that is rolled back is still recorded');

$r = boom('rollback');
$rref = $r->headers['x-nfelia-error'] ?? '';
ok($rref !== '' && group_of($rref) !== null, 'recorded on its own connection, not lost with the rollback');

section('Who it happened to');

$ada = signin('ada@adashair.test');
$s = boom('signed-in', $ada);
$sref = $s->headers['x-nfelia-error'] ?? '';
$sev = rows("SELECT business_id, user_id FROM error_events WHERE ref = ?", [$sref])[0] ?? [];
eq([(int)($sev['business_id'] ?? 0), (int)($sev['user_id'] ?? 0)], [2, 20], 'the client (Ada\'s Hair) and the person (Ada) are on it');

section('A slow page is recorded, grouped by page');

$slow = boom('slow');
$sg = rows("SELECT g.*, e.duration_ms FROM error_groups g JOIN error_events e ON e.group_id = g.id WHERE g.kind = 'slow'")[0] ?? null;
ok($sg && (int)$sg['duration_ms'] >= 3000, 'with how long it took: ' . (($sg['duration_ms'] ?? 0) / 1000) . ' s');
eq($sg['title'] ?? '', 'Slow page: GET __boom/slow', 'titled by the page');
ok(str_contains(last_bell(GRACE)['title'] ?? '', 'Slow page'), 'in the bell');

/* ── Status ─────────────────────────────────────────────────── */

section('Marked fixed, then it happens again: open again, and the team is told');

$grace = as_ops('grace@nfelia.test');
$grace->get(o('errors/' . $g['id']));
$grace->post(o('errors/' . $g['id'] . '/status'), ['status' => 'fixed']);
eq((string)q("SELECT status FROM error_groups WHERE id = ?", [$g['id']]), 'fixed', 'Grace marks it fixed');
ok((bool)rows("SELECT 1 FROM ops_audit WHERE action = 'error.fixed' AND target_id = ?", [$g['id']]), 'in the audit log');
$before = count(pushes_to($grace_phone));
exec_sql("UPDATE error_groups SET status_changed_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?", [$g['id']]);
boom('again');
$back = rows("SELECT * FROM error_groups WHERE id = ?", [$g['id']])[0];
eq([$back['status'], (int)$back['regressions']], ['open', 1], 'it is open again, and counted as having come back');
ok(str_contains(pushes_to($grace_phone)[$before]['title'] ?? '', 'Came back'), 'Grace\'s phone says it came back');

section('Ignored: counted, never announced');

$grace->post(o('errors/' . $g['id'] . '/status'), ['status' => 'ignored']);
$bell_before = count(ops_bell(SAM));
exec_sql("UPDATE error_groups SET status_changed_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?", [$g['id']]);
boom('again');
$ig = rows("SELECT * FROM error_groups WHERE id = ?", [$g['id']])[0];
eq($ig['status'], 'ignored', 'it stays ignored');
eq(count(ops_bell(SAM)), $bell_before, 'nobody is told');
$grace->post(o('errors/' . $g['id'] . '/status'), ['status' => 'open']);

section('"I\'m on it" takes it, and giving it to someone tells them');

$grace->post(o('errors/' . $fg['id'] . '/status'), ['status' => 'handling']);
$fg2 = rows("SELECT * FROM error_groups WHERE id = ?", [$fg['id']])[0];
eq([$fg2['status'], (int)$fg2['assigned_to']], ['handling', GRACE], 'being handled, by Grace');
$grace->post(o('errors/' . $fg['id'] . '/assign'), ['staff_id' => SAM]);
eq((int)q("SELECT assigned_to FROM error_groups WHERE id = ?", [$fg['id']]), SAM, 'given to Sam');
ok(str_contains(last_bell(SAM)['title'] ?? '', 'Grace Admin gave you an error'), 'Sam is told');
$grace->post(o('errors/' . $fg['id'] . '/assign'), ['staff_id' => LOLA]);
eq((int)q("SELECT assigned_to FROM error_groups WHERE id = ?", [$fg['id']]), SAM, 'it cannot go to someone whose role has no errors (Lola, Sales)');
$grace->post(o('errors/' . $fg['id'] . '/notes'), ['body' => 'Raised the limit on the export; watching it.']);
eq((string)q("SELECT body FROM error_notes WHERE group_id = ? AND staff_id = ?", [$fg['id'], GRACE]), 'Raised the limit on the export; watching it.', 'a note is kept for the team');

section('Spiking: many more than usual in an hour');

$sp_before = count(pushes_to($sam_phone));
for ($i = 0; $i < 24; $i++) {
    errors_capture(['bulk' => true, 'kind' => 'job', 'level' => 'error', 'message' => 'Spike test: queue full', 'file' => 'app/x.php', 'line' => 1]);
}
$spike = rows("SELECT * FROM error_groups WHERE title = 'Spike test: queue full'")[0] ?? null;
ok($spike && $spike['spiked_at'] !== null, 'marked as spiking');
deliver();
$sam_push = pushes_to($sam_phone);
ok((bool)array_filter(array_slice($sam_push, $sp_before), fn($p) => str_starts_with($p['title'] ?? '', 'Spiking')), 'phones are told it is spiking');
ok(str_contains((string)q("SELECT body FROM error_notes WHERE group_id = ?", [$spike['id']]), 'Spiking:'), 'with a note on the problem');

/* ── Browser errors ─────────────────────────────────────────── */

section('A script error in someone\'s browser');

$send = function (array $report, ?Http $as = null): Http {
    $h = $as ?? new Http();
    $h->send('POST', 'api/errors', json_encode($report), ['Content-Type: text/plain;charset=UTF-8']);
    return $h;
};
$page = APP_URL . '/dashboard';
$b = $send(['kind' => 'error', 'message' => 'TypeError: Cannot read properties of null (reading \'value\')', 'file' => APP_URL . '/assets/js/bell.js?v=1', 'line' => 42, 'col' => 7, 'stack' => 'at nfBell (bell.js:42:7)', 'page' => $page], $ada);
eq($b->status, 204, 'is taken in (204, the page never waits)');
$jg = rows("SELECT * FROM error_groups WHERE kind = 'js'")[0] ?? null;
ok($jg && $jg['file'] === '/assets/js/bell.js' && (int)$jg['line'] === 42, 'recorded as a browser error in bell.js:42');
$jev = rows("SELECT * FROM error_events WHERE group_id = ?", [$jg['id'] ?? 0])[0] ?? [];
eq([(int)($jev['business_id'] ?? 0), (int)($jev['user_id'] ?? 0), $jev['path'] ?? ''], [2, 20, '/dashboard'], 'for Ada at Ada\'s Hair, on the dashboard');
$send(['message' => 'Script error.', 'file' => '', 'page' => $page]);
$send(['message' => 'oops', 'file' => 'chrome-extension://abc/content.js', 'page' => $page]);
$send(['message' => 'oops', 'file' => 'https://evil.example/x.js', 'page' => $page]);
$send(['message' => 'oops', 'file' => '', 'page' => 'https://evil.example/']);
eq((int)q("SELECT COUNT(*) FROM error_groups WHERE kind = 'js'"), 1, '"Script error.", extensions and other sites\' scripts or pages are dropped');
$shop = new Http();
$send(['message' => 'ReferenceError: cart is not defined', 'file' => 'http://adas-hair.' . shop_base_host() . ':' . parse_url(APP_URL, PHP_URL_PORT) . '/assets/js/storefront.js', 'line' => 9,
       'page' => 'http://adas-hair.' . shop_base_host() . ':' . parse_url(APP_URL, PHP_URL_PORT) . '/'], $shop);
$sjg = rows("SELECT e.* FROM error_events e JOIN error_groups g ON g.id = e.group_id WHERE g.title LIKE 'ReferenceError: cart%'")[0] ?? [];
eq([$sjg['source'] ?? null, (int)($sjg['business_id'] ?? 0)], ['shop', 2], 'an error on Ada\'s shop is put down to her shop');
for ($i = 0; $i < 70; $i++) $send(['message' => 'Flood ' . $i, 'file' => '', 'page' => $page]);
ok((int)q("SELECT COUNT(*) FROM error_groups WHERE title LIKE 'Flood%'") <= 60, 'one network can send at most 60 in ten minutes');
exec_sql("DELETE FROM auth_throttle");

/* ── The server log ─────────────────────────────────────────── */

section('The server log is read into Ops, once, without the ones already recorded');

$log = sys_get_temp_dir() . '/nf-ops-log-' . getmypid() . '.log';
$utc = fn(string $mod = 'now') => (new DateTimeImmutable($mod, new DateTimeZone('UTC')))->format('d-M-Y H:i:s') . ' UTC';
file_put_contents($log, implode("\n", [
    '[' . $utc() . '] PHP Parse error:  syntax error, unexpected token "}" in /home/x/public_html/app/controllers/BrokenController.php on line 88',
    '[' . $utc() . '] Nfelia notify: gave up on email #12 (order.new): the mail server did not accept it',
    '[' . $utc() . '] Nfelia error [EABCDEFG]: already recorded when it happened',
    '[' . $utc() . '] PHP Fatal error:  Uncaught Exception: kaboom in /home/x/app/y.php:12',
    'Stack trace:',
    '#0 {main}',
    '  thrown in /home/x/app/y.php on line 12',
    '',
]));
$n = ops_log_import(get_db(), $log);
eq($n, 3, 'three entries recorded (the one already recorded is skipped)');
$parse = rows("SELECT * FROM error_groups WHERE title LIKE 'syntax error%'")[0] ?? null;
eq([$parse['kind'] ?? null, $parse['level'] ?? null, $parse['line'] ?? null], ['php', 'fatal', 88], 'a parse error (from before the app starts) is a crash at its line');
ok(rows("SELECT 1 FROM error_groups WHERE kind = 'log' AND level = 'error' AND title LIKE 'Nfelia notify: gave up on email%'") !== [], 'an error_log() line the code wrote is recorded as a server-log error');
$uncaught = rows("SELECT * FROM error_groups WHERE title LIKE 'Uncaught Exception: kaboom%'")[0] ?? null;
eq([$uncaught['file'] ?? null, (int)($uncaught['line'] ?? 0)], ['/home/x/app/y.php', 12], 'a stack trace stays with its entry, and its "thrown in" line gives the place');
eq(ops_log_import(get_db(), $log), 0, 'read again, nothing new');
file_put_contents($log, '[' . $utc() . '] PHP Warning:  Something new in /home/x/app/z.php on line 3' . "\n", FILE_APPEND);
eq(ops_log_import(get_db(), $log), 1, 'a new line is read on the next run');
file_put_contents($log, '[' . $utc() . '] PHP Warning:  After rotation in /home/x/app/z.php on line 4' . "\n");
eq(ops_log_import(get_db(), $log), 1, 'a log that was emptied is read from its start');
$old = sys_get_temp_dir() . '/nf-ops-old-' . getmypid() . '.log';
file_put_contents($old, '[' . $utc('-3 hours') . '] PHP Warning:  Long ago in /home/x/app/old.php on line 1' . "\n");
exec_sql("DELETE FROM ops_settings WHERE name LIKE 'log:%'");
$bell = count(ops_bell(GRACE));
ops_log_import(get_db(), $old);
eq(count(ops_bell(GRACE)), $bell, 'old lines are listed, not announced');
@unlink($log); @unlink($old);

/* ── Background jobs and health ─────────────────────────────── */

section('The cron beats each run, and the watchdog notices when it stops');

$out = cron_at('10:00');
$beat = rows("SELECT * FROM ops_heartbeats WHERE name = 'planner'")[0] ?? null;
ok($beat && $beat['finished_at'] !== null && (int)$beat['runs'] === 1, 'a run is recorded: ' . trim(substr($out, 0, 80)));
$health = ops_health(get_db());
eq(array_column($health, 'state', 'key')['cron'] ?? null, 'ok', 'Health says the cron is fine');
exec_sql("UPDATE ops_heartbeats SET finished_at = NOW() - INTERVAL 45 MINUTE, started_at = NOW() - INTERVAL 46 MINUTE WHERE name = 'planner'");
eq(array_column(ops_health(get_db()), 'state', 'key')['cron'] ?? null, 'bad', 'after 45 minutes without a run, Health says it needs attention');
$bell = count(ops_bell(SAM));
ops_watchdog();
ok(str_contains(last_bell(SAM)['title'] ?? '', 'The cron has stopped'), 'the watchdog tells the team');
ops_watchdog();
eq(count(ops_bell(SAM)), $bell + 1, 'once, not on every page');

section('Failed sends: listed, and tried again from Ops');

exec_sql("INSERT INTO notify_outbox (business_id, user_id, event, channel, recipient, recipient_name, payload, status, attempts, last_error)
          VALUES (2, 20, 'order.new', 'email', 'ada@adashair.test', 'Ada Obi', ?, 'failed', 6, 'the mail server did not accept it')",
         [json_encode(['subject' => 'New order', 'template' => 'notification', 'vars' => ['headline' => 'New order', 'message' => 'x', 'button' => 'Open', 'open_url' => APP_URL]])]);
$failed_id = (int)q("SELECT MAX(id) FROM notify_outbox");
$checks = array_column(ops_health(get_db()), null, 'key');
eq($checks['outbox_email']['state'] ?? null, 'warn', 'Health shows emails have failed');
$grace->get(o('health'));
ok($grace->status === 200 && str_contains($grace->body, 'Emails') && str_contains($grace->body, '1 failed'), 'the Health page says so');
$grace->get(o('health/sends'));
ok(str_contains($grace->body, 'order.new') && str_contains($grace->body, 'the mail server did not accept it') && str_contains($grace->body, 'Ada&#039;s Hair'), 'Failed sends lists it: what, why, which client');
$grace->post(o('health/sends/' . $failed_id . '/retry'));
eq((string)q("SELECT status FROM notify_outbox WHERE id = ?", [$failed_id]), 'sent', '"Try again" sends it now');
eq(count(array_filter(mails_to('ada@adashair.test'), fn($m) => $m['subject'] === 'New order')), 1, 'and it arrives');

section('Paystack webhooks: a refused one is recorded');

$wh = new Http();
$wh->send('POST', 'api/webhooks', json_encode(['event' => 'charge.success', 'data' => []]), ['Content-Type: application/json', 'X-Paystack-Signature: forged']);
eq($wh->status, 401, 'a forged webhook is refused');
ok(rows("SELECT 1 FROM error_groups WHERE kind = 'job' AND title LIKE 'Paystack webhook refused%'") !== [], 'and recorded as a background job problem');
eq((int)q("SELECT failures FROM ops_heartbeats WHERE name = 'paystack_webhook'"), 1, 'Health counts it');

/* ── The morning summary ────────────────────────────────────── */

section('The 8am summary');

clean_slate();
exec_sql("UPDATE error_groups SET first_seen_at = NOW() - INTERVAL 1 DAY WHERE id = ?", [$fg['id']]);
exec_sql("UPDATE ops_staff SET digest_email = 0 WHERE id = ?", [SAM]);
$now = new DateTimeImmutable('today 08:05', new DateTimeZone(APP_TIMEZONE));
exec_sql("DELETE FROM ops_settings WHERE name LIKE 'digest:%'");   // the 10:00 cron run above already sent today's
$queued = ops_digest(get_db(), $now);
deliver();
eq($queued, 1, 'queued for Grace (Admin); Sam turned it off; Lola\'s role does not get it');
$mail = mails_to('grace@nfelia.test')[0] ?? ['subject' => '', 'text' => ''];
eq($mail['subject'], 'Nfelia Ops: yesterday, and what needs a look', 'titled');
ok(str_contains($mail['text'], 'Allowed memory size') && str_contains($mail['text'], 'Open Nfelia Ops'), 'listing yesterday\'s new problems, with a link');
eq(ops_digest(get_db(), $now), 0, 'once a day');
exec_sql("UPDATE ops_staff SET digest_email = 1 WHERE id = ?", [SAM]);

/* ── The Ops pages ──────────────────────────────────────────── */

section('The errors list');

$grace->get(o('errors'));
eq($grace->status, 200, 'opens');
ok(str_contains($grace->body, 'Allowed memory size') && str_contains($grace->body, 'Undefined array key'), 'listing open problems');
$grace->get(o('errors?kind=js'));
ok(str_contains($grace->body, 'Cannot read properties of null') && !str_contains($grace->body, 'Allowed memory size'), 'filtered to browser errors');
$grace->get(o('errors?q=memory'));
ok(str_contains($grace->body, 'Allowed memory size') && !str_contains($grace->body, 'Undefined array key'), 'searched');
$grace->get(o('errors?q=' . strtolower($sref)));
eq(ops_to($grace), 'errors/' . group_of($sref)['id'] . '?ref=' . $sref . '#' . $sref, 'an error code in the search opens its problem, on that occurrence');
$grace->get(o('errors/code/EZZZZZZZ'));
eq(ops_to($grace), 'errors', 'an unknown code goes back to the list');
$grace->get(o('errors'));
ok(str_contains($grace->body, 'No error has the code EZZZZZZZ'), 'saying so');

section('One problem');

$grace->get(o('errors/' . $fg['id']));
eq($grace->status, 200, 'opens');
ok(str_contains($grace->body, 'The page ran out of memory'), 'saying what it means in plain words');
ok(str_contains($grace->body, 'Raised the limit on the export'), 'with the team\'s notes');
ok(str_contains($grace->body, 'Copy for AI') && str_contains($grace->body, 'Stack trace:') === false || str_contains($grace->body, 'copy-text'), 'with "Copy for AI"');
$grace->get(o('errors/' . group_of($sref)['id'] . '?ref=' . $sref));
ok(str_contains($grace->body, 'Ada&#039;s Hair &amp; Co') && str_contains($grace->body, 'id="' . $sref . '" class="is-focus"'), 'the clients it happened to, and the quoted occurrence open');

section('Marking several fixed at once');

$ids = array_map('intval', array_column(rows("SELECT id FROM error_groups WHERE kind = 'log'"), 'id'));
$grace->get(o('errors'));
$grace->post(o('errors/bulk'), ['ids' => $ids, 'status' => 'fixed']);
eq((int)q("SELECT COUNT(*) FROM error_groups WHERE kind = 'log' AND status = 'fixed'"), count($ids), count($ids) . ' marked fixed together');

section('Downloading several for an AI, and the deploy marking them fixed');

$xref = errors_capture(['bulk' => true, 'kind' => 'php', 'level' => 'error', 'message' => 'Export test: nothing handled it', 'file' => 'app/y.php', 'line' => 7,
                       'at' => date('Y-m-d H:i:s', time() - 7200)]);   // two hours old: listed, nobody told
$xg = group_of($xref);
$grace->get(o('errors'));
ok(str_contains($grace->body, 'Download for AI'), 'the ticked bar offers a download');
$grace->post(o('errors/export'), ['ids' => [$fg['id'], group_of($sref)['id'], $xg['id']]]);
ok(str_starts_with($grace->headers['content-type'] ?? '', 'text/markdown') && str_contains($grace->headers['content-disposition'] ?? '', '.md"'), 'a Markdown file to save');
ok(str_contains($grace->body, '# Nfelia errors to fix: 3 problems') && str_contains($grace->body, 'AskUserQuestion') && str_contains($grace->body, 'Fixes-Errors: #12, #15'), 'with the brief for the AI');
ok(str_contains($grace->body, '### #' . $xg['id'] . ' ') && str_contains($grace->body, 'Allowed memory size') && str_contains($grace->body, $sref) && str_contains($grace->body, 'Raised the limit on the export'), 'each problem by its number, its error codes and the team\'s notes');
ok(!str_contains($grace->body, 'Ada\'s Hair'), 'but no client names');
$xg = group_of($xref);
ok($xg['status'] === 'handling' && (int)$xg['assigned_to'] === GRACE, 'an open one becomes being handled, by whoever downloaded it');

require_once dirname(__DIR__, 2) . '/bin/errors_fixed.php';
$done = errors_fixed_apply(get_db(), [['sha' => 'abc1234def', 'subject' => 'Handle the export test', 'body' => "Handle the export test\n\nFixes-Errors: #{$xg['id']}, {$sref}, #999999"]], 'abc1234');
eq(group_of($xref)['status'], 'fixed', 'a deployed commit saying "Fixes-Errors: #' . $xg['id'] . '" marks it fixed');
ok(str_contains((string)q("SELECT body FROM error_notes WHERE group_id = ? ORDER BY id DESC LIMIT 1", [$xg['id']]), 'abc1234'), 'with a note naming the commit');
eq(count($done), group_of($sref)['status'] === 'fixed' ? 2 : 1, 'an error code works too, and an unknown number is passed over');

section('Health, alerts, and who sees them');

$grace->get(o('health'));
ok(str_contains($grace->body, 'The 5-minute cron') && str_contains($grace->body, 'Needs attention'), 'Health lists every check, with what needs attention');
$grace->get(o());
ok(str_contains($grace->body, 'Open errors') && str_contains($grace->body, 'Health'), 'Home shows errors and health to an admin');
$lola = as_ops('lola@nfelia.test');
$lola->get(o('errors'));
eq($lola->status, 403, 'Sales cannot open Errors');
$lola->get(o('health'));
eq($lola->status, 403, 'nor Health');
$lola->get(o());
ok(!str_contains($lola->body, 'Open errors'), 'and Home shows Lola none of it');

$alert = last_bell(GRACE);
$grace->get(o('alerts'));
ok(str_contains($grace->body, 'is-new') && str_contains($grace->body, e($alert['title'])), 'Grace\'s alerts, the unread marked');
$grace->get(o('alerts/open/' . $alert['id']));
eq(ops_to($grace), $alert['url'], 'opening one goes to its page');
ok(q("SELECT read_at FROM ops_alerts WHERE id = ?", [$alert['id']]) !== null, 'and marks it read');
$sam = as_ops('sam@nfelia.test');
$sam->get(o('alerts/open/' . $alert['id']));
eq($sam->status, 403, 'someone else\'s alert does not open');
$out = ops_browser();
$out->get(o('alerts/open/' . $alert['id']));
eq(ops_to($out), 'signin', 'signed out, an alert asks for sign-in first');
$out->get(o('signin'));
$out->post(o('signin'), ['email' => 'grace@nfelia.test', 'password' => T_OPS_PASSWORD]);
eq(ops_to($out), 'alerts/open/' . $alert['id'], 'then opens it');

section('Phone alerts: switched on per device, and per person');

$grace->get(o('account'));
$sub = 'http://127.0.0.1:' . (parse_url(T_PUSH_URL, PHP_URL_PORT) ?: 8098) . '/ok-device-' . bin2hex(random_bytes(3));
$pair = webpush_new_keypair();
$grace->json(o('alerts/subscribe'), ['endpoint' => $sub, 'keys' => ['p256dh' => b64url_encode($pair['public']), 'auth' => b64url_encode(random_bytes(16))]]);
ok(($grace->data()['ok'] ?? false) && (int)q("SELECT staff_id FROM ops_push_subscriptions WHERE endpoint = ?", [$sub]) === GRACE, 'a device is added from the account page');
$grace->json(o('alerts/subscribe'), ['endpoint' => 'http://evil.example/push', 'keys' => ['p256dh' => 'x', 'auth' => 'y']]);
eq($grace->status, 400, 'an address that is not a push service is refused');
$grace->post(o('alerts/prefs'), ['digest_email' => '1']);
eq((int)q("SELECT alert_push FROM ops_staff WHERE id = ?", [GRACE]), 0, 'Grace turns phone alerts off');
$queued_before = (int)q("SELECT COUNT(*) FROM notify_outbox WHERE staff_id = ?", [GRACE]);
errors_capture(['bulk' => true, 'kind' => 'php', 'level' => 'error', 'message' => 'After phone alerts were turned off', 'file' => 'app/z.php', 'line' => 2]);
eq((int)q("SELECT COUNT(*) FROM notify_outbox WHERE staff_id = ?", [GRACE]), $queued_before, 'no phone alert is queued for her');
ok(str_contains(last_bell(GRACE)['title'] ?? '', 'New: Error'), 'the bell still has it');

/* ── Clearing up ────────────────────────────────────────────── */

section('At 3am, old occurrences go, problems stay');

exec_sql("UPDATE error_events SET created_at = NOW() - INTERVAL 31 DAY WHERE group_id = ?", [$fg['id']]);
ops_monitor_cleanup(get_db());
eq((int)q("SELECT COUNT(*) FROM error_events WHERE group_id = ?", [$fg['id']]), 0, 'occurrences older than 30 days are gone');
eq((int)q("SELECT COUNT(*) FROM error_groups WHERE id = ?", [$fg['id']]), 1, 'the open problem stays');
exec_sql("UPDATE error_groups SET status = 'fixed', last_seen_at = NOW() - INTERVAL 91 DAY WHERE id = ?", [$fg['id']]);
ops_monitor_cleanup(get_db());
eq((int)q("SELECT COUNT(*) FROM error_groups WHERE id = ?", [$fg['id']]), 0, 'a fixed one unseen for 90 days goes too');

done();
