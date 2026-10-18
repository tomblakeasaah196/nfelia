<?php
// SUPERSEDED by tests/notifications/cron_test.php (see docs/handoff/Notifications.md).
// Kept for reference: it checks the cron as it was before the notification rebuild.
/**
 * Tests cron/planner.php (reminders, morning plan, phone push) against the
 * local copy of the app, with a fake clock (libfaketime) and local stand-ins
 * for the mail server and the push service.
 *
 *   php cron_test.php APPDIR MAILDIR PUSHDIR
 *
 * Before running:
 *   python3 smtp_sink.py MAILDIR sink.crt sink.key &               (127.0.0.1:2525)
 *   PUSH_DIR=PUSHDIR php -S 127.0.0.1:8098 push_endpoint.php &
 *   the demo seed (seed.php) loaded; planner_seed.sql.txt is reloaded here.
 * The app's .env is restored at the end; the two test staff users are removed.
 */
[, $APP, $MAIL, $PUSH] = $argv + [null, null, null, null];
if (!$APP || !$MAIL || !$PUSH) { fwrite(STDERR, "usage: php cron_test.php APPDIR MAILDIR PUSHDIR\n"); exit(2); }
$KIT = __DIR__;
require $APP . '/app/helpers/webpush.php';   // b64url_*, webpush_encrypt, webpush_public_pem

$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec("SET time_zone = '+01:00'");
$TODAY = $db->query("SELECT CURDATE()")->fetchColumn();

$pass = $fail = 0;
function ok($cond, string $msg): void {
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $msg . "\n";
}
function q(string $sql, array $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); }

/** Runs the cron at a Lagos time today ("13:05") or at a full "Y-m-d H:i". */
function cron(string $at): string {
    global $APP, $TODAY;
    $stamp = strlen($at) === 5 ? "$TODAY $at" : $at;
    return trim((string)shell_exec('cd ' . escapeshellarg($APP) . " && TZ=Africa/Lagos faketime '{$stamp}:00' php cron/planner.php 2>&1"));
}

/** Every captured email: to, subject, html. */
function mails(): array {
    global $MAIL;
    $out = [];
    $files = glob($MAIL . '/*.eml') ?: [];
    natsort($files);
    foreach ($files as $f) {
        $raw = file_get_contents($f);
        preg_match('/^To: (.*)$/mi', $raw, $to);
        preg_match('/^Subject: (.*)$/mi', $raw, $sub);
        $html = '';
        if (preg_match('/Content-Type: text\/html;.*?\n(?:Content-Transfer-Encoding: (\S+)\n)?\n(.*?)\n--/s', $raw, $m)) {
            $html = strtolower($m[1] ?? '') === 'base64' ? base64_decode($m[2])
                  : (strtolower($m[1] ?? '') === 'quoted-printable' ? quoted_printable_decode($m[2]) : $m[2]);
        }
        $out[] = ['to' => trim($to[1] ?? ''), 'subject' => mb_decode_mimeheader(trim($sub[1] ?? '')), 'html' => $html];
    }
    return $out;
}
function clear_dir(string $dir, string $glob): void { foreach (glob("$dir/$glob") ?: [] as $f) unlink($f); }

function reset_state(): void {
    global $db, $KIT, $MAIL, $PUSH;
    shell_exec('mysql smartqaq_nfelia_core < ' . escapeshellarg("$KIT/planner_seed.sql.txt"));
    $db->exec("DELETE FROM planner_sends");
    $db->exec("DELETE FROM notifications WHERE type = 'general' AND user_id IS NOT NULL");
    $db->exec("DELETE FROM push_subscriptions");
    $db->exec("UPDATE users SET reminder_email = 1, morning_email = 1, is_active = 1 WHERE id IN (1, 2, 3)");
    $db->exec("UPDATE subscriptions SET status = 'active', trial_ends_at = NULL WHERE business_id IN (1, 2)");
    clear_dir($MAIL, '*.eml');
    clear_dir($PUSH, '*.json');
}
function bell(int $user_id): array {
    global $db;
    $s = $db->prepare("SELECT title, message, action_url FROM notifications WHERE type = 'general' AND user_id = ? ORDER BY id");
    $s->execute([$user_id]);
    return $s->fetchAll();
}
function task_id(string $title): int { return (int)q("SELECT id FROM tasks WHERE title = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1", [$title]); }

// Two staff for Ivie's shop: Tunde (active) and a former employee (inactive).
$hash = password_hash('DemoPass1', PASSWORD_BCRYPT);
$db->prepare("INSERT IGNORE INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active)
              VALUES (3, 1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', ?, 1, 1), (4, 1, 'staff', 'Old Staff', 'old@demo.nfelia', ?, 1, 0)")
   ->execute([$hash, $hash]);
$env_before = file_get_contents("$APP/.env");

try {
    echo "Due reminder\n";
    reset_state();
    $out = cron('13:05');
    $wig = task_id('Wig install');
    ok(str_contains($out, 'reminders=1'), "cron reports one reminder: $out");
    ok(q("SELECT reminded_at IS NOT NULL FROM tasks WHERE id = ?", [$wig]) == 1, 'reminded_at stamped');
    $b = bell(1);
    ok(count($b) === 1 && $b[0]['title'] === 'Wig install', 'bell row for Ivie (user_id 1) titled with the task');
    ok(($b[0]['message'] ?? '') === 'Today, 2:00pm to 4:00pm · Lara Coker', 'bell says when and who: ' . ($b[0]['message'] ?? ''));
    ok(($b[0]['action_url'] ?? '') === 'tasks', 'bell opens Tasks');
    $m = mails();
    ok(count($m) === 1 && str_contains($m[0]['to'], 'ivie@demo.nfelia'), 'one email, to Ivie');
    ok(($m[0]['subject'] ?? '') === 'Reminder: Wig install', 'subject: ' . ($m[0]['subject'] ?? ''));
    ok(str_contains($m[0]['html'] ?? '', 'https://wa.me/2349035550199?text=') && str_contains($m[0]['html'] ?? '', 'Remind Lara on WhatsApp'), 'email offers the WhatsApp nudge to Lara');
    ok(str_contains($m[0]['html'] ?? '', 'Frontal, 20 inches'), 'email carries the notes');
    ok(str_contains($m[0]['html'] ?? '', '/tasks?reminders=1'), 'email links to turn reminder emails off');
    $out = cron('13:10');
    ok(count(bell(1)) === 1 && count(mails()) === 1, 'the next run sends nothing again');
    ok(q("SELECT reminded_at IS NULL FROM tasks WHERE title = 'Post new arrivals on Instagram'") == 1, 'the 6pm reminder waits for its time');
    cron('18:00');
    ok(count(bell(1)) === 2 && count(mails()) === 2, 'and goes out at 6pm');

    echo "Stale reminder\n";
    reset_state();
    $db->prepare("UPDATE tasks SET remind_at = ? WHERE title = 'Revamp pickup'")->execute([date('Y-m-d', strtotime("$TODAY -2 days")) . ' 10:00:00']);
    $out = cron('13:05');
    ok(str_contains($out, 'stale=1'), "a two-day-old reminder counts as stale: $out");
    ok(q("SELECT reminded_at IS NOT NULL FROM tasks WHERE title = 'Revamp pickup'") == 1, 'it is stamped so it never comes back');
    ok(!array_filter(bell(1), fn($r) => $r['title'] === 'Revamp pickup'), 'no bell for it');
    ok(!array_filter(mails(), fn($x) => str_contains($x['subject'], 'Revamp')), 'no email for it');

    echo "Who gets it\n";
    reset_state();
    $at = "$TODAY 12:58:00";
    $db->prepare("INSERT INTO tasks (business_id, kind, title, due_date, due_time, remind_before, remind_at, assigned_to, created_by) VALUES
        (1, 'task', 'Fold the new stock', ?, '13:30:00', 15, ?, 3, 1),
        (1, 'task', 'Count the till', ?, '13:30:00', 15, ?, 4, 1)")->execute([$TODAY, $at, $TODAY, $at]);
    $db->exec("UPDATE users SET reminder_email = 0 WHERE id = 1");
    cron('13:05');
    $t = bell(3);
    ok(count($t) === 1 && $t[0]['title'] === 'Fold the new stock', 'assigned task: the bell goes to Tunde, not the owner');
    ok(!array_filter(bell(1), fn($r) => $r['title'] === 'Fold the new stock'), 'Ivie does not get Tunde\'s reminder');
    $to = array_column(mails(), 'to');
    ok(count(array_filter($to, fn($x) => str_contains($x, 'tunde@demo.nfelia'))) === 1, 'Tunde gets the email');
    ok(!array_filter($to, fn($x) => str_contains($x, 'ivie@demo.nfelia')), 'Ivie turned reminder emails off: bell only, no email');
    ok(count(bell(1)) === 1, 'her bell still rings for her own Wig install');
    ok(q("SELECT reminded_at IS NOT NULL FROM tasks WHERE title = 'Count the till'") == 1 && !bell(4), 'a task for someone who has left is stamped and skipped');

    echo "Businesses that cannot use the app\n";
    foreach ([['suspended', null, false], ['cancelled', null, false], ['trial', '-1 day', false], ['trial', '+3 days', true], ['active', null, true]] as [$status, $ends, $sends]) {
        reset_state();
        $db->prepare("UPDATE subscriptions SET status = ?, trial_ends_at = ? WHERE business_id = 2")
           ->execute([$status, $ends ? date('Y-m-d', strtotime("$TODAY $ends")) : null]);
        $db->prepare("UPDATE tasks SET remind_at = ? WHERE title = 'Venue visit with Ngozi'")->execute(["$TODAY 13:00:00"]);
        cron('13:05');
        $label = $status . ($ends ? " (trial ends $ends)" : '');
        ok((count(bell(2)) === 1) === $sends, "$label: " . ($sends ? 'reminder sent' : 'nothing sent'));
        ok(q("SELECT reminded_at IS NOT NULL FROM tasks WHERE title = 'Venue visit with Ngozi'") == 1, "$label: stamped either way");
    }

    echo "Morning plan\n";
    reset_state();
    $db->prepare("INSERT INTO tasks (business_id, kind, title, due_date, assigned_to, created_by) VALUES (1, 'task', 'Fold the new stock', ?, 3, 1)")->execute([$TODAY]);
    $out = cron('06:59');
    ok(str_contains($out, 'mornings=0') && (int)q("SELECT COUNT(*) FROM planner_sends") === 0, 'nothing before 7am');
    $out = cron('07:00');
    ok(str_contains($out, 'mornings=3'), "7am: Ivie, Kemi and Tunde get their plan: $out");
    ok((int)q("SELECT COUNT(*) FROM planner_sends WHERE kind = 'morning' AND send_date = ?", [$TODAY]) === 3, 'one send row for each active person');
    $m = mails();
    $byTo = [];
    foreach ($m as $x) { foreach (['ivie', 'kemi', 'tunde'] as $who) if (str_contains($x['to'], "$who@")) $byTo[$who] = $x; }
    ok(count($m) === 3 && isset($byTo['ivie'], $byTo['kemi'], $byTo['tunde']), 'three emails: Ivie, Kemi, Tunde');
    ok(preg_match('/^Your day: \d+ things? today$/', $byTo['ivie']['subject'] ?? ''), 'subject: ' . ($byTo['ivie']['subject'] ?? ''));
    $iv = $byTo['ivie']['html'] ?? '';
    ok(str_contains($iv, 'Good morning, Ivie.') && str_contains($iv, 'Open my day'), 'greets Ivie and offers "Open my day"');
    ok(str_contains($iv, '>Late<') || preg_match('/>\s*Late\s*</', $iv), 'has a Late section');
    ok(str_contains($iv, 'Deliver to Blessing Etim') && str_contains($iv, '1 day late'), 'late delivery with its countdown');
    ok(str_contains($iv, 'Deliver to Adaeze Nwosu') && str_contains($iv, 'Deliver today'), 'today\'s delivery');
    ok(str_contains($iv, 'Deliver to Mrs Folake') && str_contains($iv, '2 days left'), 'job-plan delivery two days out is nudged');
    ok(str_contains($iv, '2:00pm') && str_contains($iv, 'Wig install'), 'today\'s appointment with its time');
    ok(str_contains($iv, 'due from'), 'the owner sees money due');
    ok(!str_contains($iv, 'Fold the new stock'), 'the owner\'s plan leaves out Tunde\'s task');
    ok(str_contains($iv, '/tasks?reminders=1'), 'links to turn morning emails off');
    $tu = $byTo['tunde']['html'] ?? '';
    ok(str_contains($tu, 'Fold the new stock'), 'Tunde\'s plan has his task');
    ok(!str_contains($tu, 'due from') && !str_contains($tu, '₦') && !str_contains($tu, 'Restock') && !str_contains($tu, 'Wig install'), 'and nothing else: no money, no shop items, not Ivie\'s tasks');
    $b = bell(1);
    ok(count($b) === 1 && preg_match('/^Today: \d+ tasks?.*, \d+ deliver(y|ies), ₦[\d,]+ due$/u', $b[0]['message']), 'Ivie\'s bell: ' . ($b[0]['message'] ?? ''));
    ok(count(bell(3)) === 1 && bell(3)[0]['message'] === 'Today: 1 task', 'Tunde\'s bell: ' . (bell(3)[0]['message'] ?? ''));
    $out = cron('07:05');
    ok(count(mails()) === 3 && count(bell(1)) === 1, "the next run sends nothing again: $out");

    reset_state();
    // A brand-new shop with nothing in it yet, and Tunde with no task today.
    $db->exec("INSERT INTO businesses (id, name, slug, doc_prefix, currency_symbol, is_active) VALUES (3, 'Quiet Shop', 'quiet-shop', 'QSH', '₦', 1)");
    $db->exec("INSERT INTO subscriptions (business_id, plan, status) VALUES (3, 'standard', 'active')");
    $db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (5, 3, 'owner', 'Quiet Owner', 'quiet@demo.nfelia', ?, 1, 1)")->execute([$hash]);
    $db->exec("UPDATE users SET morning_email = 0 WHERE id = 2");
    $out = cron('10:59');
    ok(str_contains($out, 'mornings=2') && str_contains($out, 'quiet=2'), "10:59 still counts; a day with nothing in it sends nothing (Tunde, the new shop): $out");
    ok((int)q("SELECT COUNT(*) FROM planner_sends WHERE user_id IN (3, 5)") === 2 && !bell(3) && !bell(5), 'but is still marked as done for the day');
    ok(!array_filter(mails(), fn($x) => str_contains($x['to'], 'quiet@') || str_contains($x['to'], 'tunde@')), 'no empty emails');
    ok(count(bell(2)) === 1 && !array_filter(mails(), fn($x) => str_contains($x['to'], 'kemi@')), 'Kemi turned morning emails off: bell only');
    $db->exec("DELETE FROM businesses WHERE id = 3");
    reset_state();
    $out = cron('11:00');
    ok(str_contains($out, 'mornings=0') && (int)q("SELECT COUNT(*) FROM planner_sends") === 0, 'nothing from 11am');

    echo "One run at a time\n";
    reset_state();
    ok((int)$db->query("SELECT GET_LOCK('nfelia_planner', 0)")->fetchColumn() === 1, 'test holds the lock');
    $out = cron('13:05');
    ok(str_contains($out, 'another run is busy'), "a second run steps aside: $out");
    ok(q("SELECT reminded_at IS NULL FROM tasks WHERE title = 'Wig install'") == 1 && !bell(1), 'and touches nothing');
    $db->query("SELECT RELEASE_LOCK('nfelia_planner')");

    echo "Phone push\n";
    reset_state();
    // RFC 8291, appendix A: the published example must come out byte for byte.
    $body = webpush_encrypt(
        b64url_decode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24'),
        b64url_decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
        b64url_decode('BTBZMqHH6r4Tts7J_aSIgg'),
        ['d' => b64url_decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'),
         'public' => b64url_decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')],
        b64url_decode('DGv6ra1nlYgDCS1FRnbzlw'));
    ok(b64url_encode($body) === 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
       'encryption matches the RFC 8291 test vector');

    // Turn push on for the copy, the way Tom will on the server.
    $keys = shell_exec('php ' . escapeshellarg("$APP/bin/vapid-keys.php"));
    file_put_contents("$APP/.env", $env_before . "\n" . $keys);
    preg_match('/^VAPID_PUBLIC_KEY=(\S+)$/m', $keys, $pk);

    // A phone: its own key pair and auth secret, as the browser makes them.
    $phone = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $ec = openssl_pkey_get_details($phone)['ec'];
    $phone_pub = "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    $secret = random_bytes(16);
    $sub = $db->prepare("INSERT INTO push_subscriptions (business_id, user_id, endpoint, endpoint_hash, p256dh, auth) VALUES (1, 1, ?, ?, ?, ?)");
    foreach (['http://127.0.0.1:8098/ok/1', 'http://127.0.0.1:8098/gone/2', 'http://127.0.0.1:8098/err/3', 'http://127.0.0.1:9/nobody-home'] as $ep) {
        $sub->execute([$ep, hash('sha256', $ep), b64url_encode($phone_pub), b64url_encode($secret)]);
    }
    cron('13:05');
    $state = $db->query("SELECT endpoint, failures, last_sent_at FROM push_subscriptions ORDER BY id")->fetchAll(PDO::FETCH_UNIQUE);
    ok(isset($state['http://127.0.0.1:8098/ok/1']) && $state['http://127.0.0.1:8098/ok/1']['last_sent_at'] !== null && (int)$state['http://127.0.0.1:8098/ok/1']['failures'] === 0, '201: delivered, last_sent_at set');
    ok(!isset($state['http://127.0.0.1:8098/gone/2']), '410: the phone unsubscribed, so its row is deleted');
    ok((int)($state['http://127.0.0.1:8098/err/3']['failures'] ?? -1) === 1, '500: counted as one failure');
    ok((int)($state['http://127.0.0.1:9/nobody-home']['failures'] ?? -1) === 0, 'never left the server (status 0): kept and not counted against the phone');

    $files = glob("$PUSH/*.json") ?: [];
    natsort($files);
    $req = null;
    foreach ($files as $f) { $r = json_decode(file_get_contents($f), true); if (str_starts_with($r['path'], '/ok')) $req = $r; }
    ok($req !== null, 'the push service received the notification');
    $h = $req['headers'] ?? [];
    ok(($h['content-encoding'] ?? '') === 'aes128gcm' && ($h['urgency'] ?? '') === 'high' && ($h['ttl'] ?? '') === '86400', 'headers: aes128gcm, Urgency high, TTL one day');

    // VAPID: a JWT for this push service, signed with the server's key.
    preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(\S+)$/', $h['authorization'] ?? '', $v);
    $claims = json_decode(b64url_decode($v[2] ?? ''), true) ?: [];
    ok(($v[4] ?? '') === ($pk[1] ?? '?') && ($claims['aud'] ?? '') === 'http://127.0.0.1:8098' && str_starts_with($claims['sub'] ?? '', 'mailto:'), 'VAPID token names this push service and carries the public key');
    $raw = b64url_decode($v[3] ?? '');
    $int = function (string $i) { $i = ltrim($i, "\0"); if ($i === '' || ord($i[0]) & 0x80) $i = "\0" . $i; return "\x02" . chr(strlen($i)) . $i; };
    $der = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
    ok(openssl_verify(($v[1] ?? '') . '.' . ($v[2] ?? ''), "\x30" . chr(strlen($der)) . $der, webpush_public_pem(b64url_decode($v[4] ?? '')), OPENSSL_ALGO_SHA256) === 1, 'VAPID signature verifies');

    // Decrypt as the phone would (RFC 8291, receiving side).
    $b = base64_decode($req['body'] ?? '');
    $server_pub = substr($b, 21, ord($b[20] ?? "\0"));
    $ikm   = hash_hkdf('sha256', (string)openssl_pkey_derive(webpush_public_pem($server_pub), $phone, 32), 32, "WebPush: info\0" . $phone_pub . $server_pub, $secret);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", substr($b, 0, 16));
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", substr($b, 0, 16));
    $ct    = substr($b, 21 + strlen($server_pub));
    $plain = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    $msg   = $plain === false ? null : json_decode(substr(rtrim($plain, "\0"), 0, -1), true);
    ok(is_array($msg), 'the phone can decrypt it');
    ok(($msg['title'] ?? '') === 'Wig install' && ($msg['body'] ?? '') === 'Today, 2:00pm to 4:00pm · Lara Coker' && ($msg['url'] ?? '') === 'tasks' && ($msg['tag'] ?? '') === 'task-' . task_id('Wig install'),
       'and reads: ' . json_encode($msg, JSON_UNESCAPED_UNICODE));
} finally {
    file_put_contents("$APP/.env", $env_before);
    $db->exec("DELETE FROM businesses WHERE id = 3");
    $db->exec("DELETE FROM users WHERE id IN (3, 4, 5)");
    reset_state();
}

echo "RESULT: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
