<?php
/**
 * The notification engine itself (app/helpers/notify.php), run in-process:
 * the catalogue, who hears, what they chose, quiet hours, dedupe, and the
 * outbox delivering to stand-in phones and a stand-in mail server, failing,
 * retrying and giving up.   php tests/notifications/engine_test.php
 */
require __DIR__ . '/lib.php';
t_guard();
fixture_reset();

/* ── The catalogue ─────────────────────────────────────────── */

section('Every event is complete, renders for every reader, and links inside the app');

// One set of sample values covering every key any event reads.
$sample = [
    'invoice_id' => 400, 'invoice_number' => 'ADA-INV-0400', 'customer' => 'Ngozi Eze', 'amount' => 35000, 'method' => 'bank transfer',
    'balance' => 5000, 'receipt_id' => 1, 'receipt_number' => 'ADA-REC-0001', 'payer' => 'Ngozi Eze', 'order_id' => 700,
    'order_number' => 'ORD-0700', 'debt_id' => 800, 'total' => 65000, 'items' => 2, 'channel' => 'cart_pop', 'fulfillment' => 'delivery',
    'phone' => '08031110000', 'address' => '12 Allen Avenue', 'note' => 'Call first', 'request_id' => 1, 'name' => 'Bola Ade',
    'email' => 'bola@example.test', 'message' => 'Price for 20 bundles?', 'booking_id' => 1, 'booking_number' => 'BKG-0001',
    'item' => 'Braids', 'when' => 'Sat 24 Oct, 10:00am', 'deposit' => 5000, 'caution' => 50000, 'quotation_id' => 500, 'quotation_number' => 'ADA-QUO-0500',
    'reason' => 'Too pricey', 'comment' => 'Can you do it for less?', 'delivery_id' => 600, 'delivery_number' => 'ADA-DN-0600',
    'signer' => 'Ngozi Eze', 'rating' => 4, 'who' => 'Ngozi E.', 'days' => 3, 'expiry_date' => date('Y-m-d'), 'title' => 'Restock closures',
    'body' => '2 items are running low.', 'task_id' => 900, 'line' => 'Today, 2:00pm', 'from' => 'Ada', 'by' => 'Ada', 'to_name' => 'Bisi',
    'amount_text' => '₦10,000', 'period_end' => date('Y-m-d'), 'access_until' => date('Y-m-d'), 'trial_ends' => date('Y-m-d'),
    'grace_ends' => date('Y-m-d H:i:s'), 'host' => 'adas-hair.nfelia.com', 'changed' => true, 'has_current' => false, 'ticket_id' => 1,
    'subject' => 'Cannot print', 'reply' => 'Fixed now.', 'referred' => 'Kola Prints', 'reward' => 2500, 'role' => 'an admin',
    'tries' => 10, 'minutes' => 15,
    'device' => 'Chrome on Android', 'staff' => 'Sam from Nfelia', 'new_email' => 'new@example.test', 'business' => "Ada's Hair & Co", 'payment_id' => 1,
    'contact' => '08030000000', 'count' => 4, 'book_id' => 1, 'what' => 'Invoice ADA-INV-0400',
    'user_id' => 22, 'kind' => 'order', 'number' => 'ORD-0700',
];
$money = fn($a) => notify_money_text((float)$a, '₦');
set_error_handler(function ($no, $str) { throw new ErrorException($str, 0, $no); });
$problems = [];
foreach (notify_events() as $key => $def) {
    $where = "{$key}: ";
    if ($def['topic'] !== null && !isset(NOTIFY_TOPICS[$def['topic']])) $problems[] = $where . 'unknown topic';
    if (!in_array($def['to'], ['owner', 'boss', 'team', 'user'], true)) $problems[] = $where . 'unknown audience';
    if (empty($def['private'])) $problems[] = $where . 'no lock-screen title';
    if (!is_file(BASE_PATH . '/views/emails/' . ($def['template'] ?? 'notification') . '.php')) $problems[] = $where . 'email template missing';
    try {
        $url = notify_safe_path(($def['url'])($sample));
        if ($url === '') $problems[] = $where . 'link is not a path inside the app';
        $readers = $def['to'] === 'boss' || $def['to'] === 'owner' ? [$money] : [$money, null];
        foreach ($readers as $m) {
            [$title, $body] = ($def['text'])($sample, $m);
            if (trim($title) === '' || trim($body) === '') $problems[] = $where . 'empty title or body' . ($m ? '' : ' for staff');
            // (The Nfelia team's own staff do see what a customer paid Nfelia: they check it.)
            if ($m === null && preg_match('/₦\d/u', $title . $body)) $problems[] = $where . 'shows an amount to staff';
        }
        if (isset($def['lines'])) ($def['lines'])($sample, $money);
        if (isset($def['tag'])) ($def['tag'])($sample);
    } catch (Throwable $e) {
        $problems[] = $where . $e->getMessage();
    }
}
restore_error_handler();
eq($problems, [], count(notify_events()) . ' events: topic, audience, lock-screen title, template, text for owners and staff, safe link');

// Every link's page exists: the controller is routed and has that method.
$index = file_get_contents(BASE_PATH . '/index.php');
preg_match('/function dispatch_app.*?\$map = \[(.*?)\];.*?\$controller_default_methods = \[(.*?)\];/s', $index, $m);
preg_match_all("/'([a-z-]+)'\s*=>\s*'(\w+)'/", $m[1], $routes, PREG_SET_ORDER);
preg_match_all("/'([a-z-]+)'\s*=>\s*'(\w+)'/", $m[2], $defaults, PREG_SET_ORDER);
$routes = array_column($routes, 2, 1);
$defaults = array_column($defaults, 2, 1);
$dead = [];
foreach (notify_events() as $key => $def) {
    $path = strtok(strtok(notify_safe_path(($def['url'])($sample)), '?'), '#');
    $seg = explode('/', $path);
    $class = $routes[$seg[0]] ?? null;
    $method = str_replace('-', '_', $seg[1] ?? ($defaults[$seg[0]] ?? 'index'));
    if (!$class) { $dead[] = "{$key}: no route for {$seg[0]}"; continue; }
    require_once BASE_PATH . "/app/controllers/{$class}.php";
    $ref = new ReflectionClass($class);
    if (!$ref->hasMethod($method) || !$ref->getMethod($method)->isPublic()) $dead[] = "{$key}: {$class}::{$method} missing";
}
eq($dead, [], 'every link leads to a page that exists');

/* ── Who hears ─────────────────────────────────────────────── */

section('Who hears: by role, never whoever did it, never someone who left');
clean_slate();
notify('order.new', 2, $sample);
eq(told('order.new'), [20, 21, 22], 'a shop order: the whole team (owner, admin, staff), not Dayo who left');
notify('payment.invoice', 2, $sample);
eq(told('payment.invoice'), [20, 21], 'money in: owner and admin only, never staff');
notify('billing.failed', 2, []);
eq(told('billing.failed'), [20], 'billing: the owner only');
notify('task.assigned', 2, $sample, ['users' => [22, 30, 23]]);
eq(told('task.assigned'), [22], 'named people: only active members of this business (not Kola from another one, not Dayo)');
ok(!array_filter(array_keys(notify_events()), fn($k) => str_starts_with($k, 'platform.')), 'no Nfelia-team alerts in a business\'s bell: they go to Ops (app/ops/alerts.php)');
clean_slate();
notify('payment.invoice', 2, $sample, ['actor' => 21]);
eq(told('payment.invoice'), [20], 'whoever recorded it is left out');
notify('billing.momo_submitted', 2, $sample, ['actor' => 20, 'self' => true]);
eq(told('billing.momo_submitted'), [20], 'unless they asked to be told too');
eq(notify('no.such.event', 2, []), [], 'an unknown event makes nothing (and never throws)');

section('What each person reads');
clean_slate();
notify('order.new', 2, $sample);
$boss = bell(20, 'order.new')[0];
$staff = bell(22, 'order.new')[0];
eq($boss['title'], 'New order: ₦65,000', 'the owner sees the amount');
eq($staff['title'], 'New order ORD-0700', 'staff see the order number instead');
ok(!str_contains($staff['message'], '₦'), 'and no amount anywhere');
eq($boss['action_url'], 'orders/view/700', 'the bell row keeps the exact page');
eq(json_decode($boss['data'], true)['order_id'] ?? null, 700, 'and what happened, for later');
exec_sql("UPDATE businesses SET currency_symbol = 'GH₵' WHERE id = 3");
clean_slate();
notify('payment.invoice', 3, $sample);
ok(str_starts_with(bell(30, 'payment.invoice')[0]['title'] ?? '', 'GH₵35,000'), 'amounts are in the business\'s own currency');

section('Each happening is told once');
clean_slate();
$first = notify('order.new', 2, $sample, ['dedupe' => '700']);
$again = notify('order.new', 2, $sample, ['dedupe' => '700']);
eq(count($first), 3, 'the first time: one row per person');
eq($again, [], 'the same happening again (a retry, a double tap): nothing');
eq(count(outbox('order.new')), 6, 'and nothing more queued (push and email for three people)');

/* ── What people chose ─────────────────────────────────────── */

section('Choices: phone and email per topic, billing and security always by email');
clean_slate();
$p = notify_prefs(22);
eq(array_keys($p['topics']), ['orders', 'bookings', 'stock', 'reminders', 'morning', 'tasks', 'security'], 'staff choose only what reaches staff');
eq(array_keys(notify_prefs(20)['topics']), ['orders', 'bookings', 'money', 'customers', 'overdue', 'stock', 'reminders', 'morning', 'tasks', 'billing', 'account', 'team', 'security'],
   'the owner chooses everything (and no Nfelia-team topic outside business 1)');
ok(!isset(notify_prefs(10)['topics']['platform']), 'the Nfelia business is an ordinary business: no Nfelia-team topic');
eq([$p['topics']['orders']['push'], $p['topics']['orders']['email']], [true, true], 'orders default to phone and email');
eq([$p['topics']['stock']['push'], $p['topics']['stock']['email']], [true, false], 'everything else defaults to phone (and the bell)');
notify_set_pref(get_db(), 21, 'money', false, true);
notify_set_pref(get_db(), 20, 'billing', true, false);
notify('payment.invoice', 2, $sample);
notify('billing.failed', 2, []);
$off = outbox('payment.invoice', 'push', 21);
eq([count($off), $off[0]['status'] ?? null, $off[0]['last_error'] ?? null], [1, 'skipped', 'phone alerts for money in are off'],
   'Bisi turned money alerts off her phone: no push, and the reason is written down for her');
eq(count(outbox('payment.invoice', 'email', 21)), 1, 'and on by email: an email');
eq(count(bell(21, 'payment.invoice')), 1, 'the bell always has it');
eq(count(outbox('billing.failed', 'email', 20)), 1, 'billing emails can\'t be switched off');
notify_set_pref(get_db(), 21, 'money', true, false);
eq((int)q("SELECT COUNT(*) FROM notification_prefs WHERE user_id = 21 AND topic = 'money'"), 0, 'a choice put back to the default is not kept');

section('Quiet hours, and amounts hidden from the lock screen');
clean_slate();
$now = planner_now();
exec_sql("UPDATE users SET quiet_hours = 1, quiet_from = ?, quiet_to = ? WHERE id = 21",
         [$now->modify('-1 hour')->format('H:i:00'), $now->modify('+1 hour')->format('H:i:00')]);
exec_sql("UPDATE users SET hide_amounts = 1 WHERE id = 20");
notify('payment.invoice', 2, $sample);
$quiet = outbox('payment.invoice', 'push', 21)[0] ?? [];
eq([$quiet['status'] ?? null, $quiet['last_error'] ?? null], ['pending', 'held for quiet hours'], 'Bisi\'s quiet hours: her phone stays silent for now (the reason is kept)');
eq(substr((string)($quiet['next_attempt_at'] ?? ''), 11, 5), $now->modify('+1 hour')->format('H:i'), 'and the push waits for the end of her quiet hours');
eq(count(bell(21, 'payment.invoice')), 1, 'it still waits in her bell');
$bisi = phone(21);
eq(notify_flush()['sent'], 0, 'nothing goes before then');
exec_sql("UPDATE notify_outbox SET next_attempt_at = NOW() WHERE id = ?", [$quiet['id']]);   // her quiet hours end
deliver();
eq([outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, count(pushes_to($bisi))], ['sent', 1], 'when they end, her phone gets it');
exec_sql("DELETE FROM push_subscriptions");
notify('system.test', 2, [], ['users' => [21], 'self' => true, 'dedupe' => 'quiet-short', 'channels' => ['push']]);   // a 10-minute push can't wait an hour
eq([outbox('system.test', 'push', 21)[0]['status'] ?? null, outbox('system.test', 'push', 21)[0]['last_error'] ?? null], ['skipped', 'quiet hours'],
   'a push that would be too late by the end of quiet hours is not sent at all');
exec_sql("DELETE FROM notify_outbox WHERE event = 'system.test'");
exec_sql("DELETE FROM notifications WHERE event = 'system.test'");
$hidden = json_decode(outbox('payment.invoice', 'push', 20)[0]['payload'] ?? '{}', true);
eq([$hidden['title'] ?? null, $hidden['body'] ?? null], ['Payment received', 'Tap to see it in Nfelia.'], 'Ada hides amounts: her phone says only "Payment received"');
ok(str_contains(bell(20, 'payment.invoice')[0]['title'], '₦35,000'), 'her bell keeps the detail');
notify('system.test', 2, [], ['users' => [21], 'self' => true, 'loud' => true, 'channels' => ['push']]);
eq(outbox('system.test', 'push', 21)[0]['status'] ?? null, 'pending', 'a test she asks for comes through quiet hours');
exec_sql("UPDATE users SET quiet_hours = 0, hide_amounts = 0");

/* ── Delivery ──────────────────────────────────────────────── */

section('Phones: delivered encrypted, the right link, the unread count');
clean_slate();
$ada = phone(20);
$ids = notify('order.new', 2, $sample, ['dedupe' => 'p1']);
$sent = deliver();
$got = pushes_to($ada);
eq(count($got), 1, 'Ada\'s phone got one notification');
$n = (int)q("SELECT id FROM notifications WHERE user_id = 20 AND event = 'order.new'");
$msg = $got[0] ?? [];
eq([$msg['title'] ?? null, $msg['url'] ?? null, $msg['tag'] ?? null, $msg['id'] ?? null],
   ['New order: ₦65,000', 'notifications/open/' . $n, 'order-700', $n], 'it decrypts, with the title, the link that opens the order, a tag and its id');
eq($msg['badge'] ?? null, 1, 'and the number for the app icon');
eq([$msg['_headers']['urgency'] ?? null, $msg['_headers']['content-encoding'] ?? null], ['high', 'aes128gcm'], 'sent urgent, encrypted (RFC 8291)');
ok(str_starts_with($msg['_headers']['authorization'] ?? '', 'vapid t='), 'signed with Nfelia\'s VAPID key (RFC 8292)');
eq(outbox('order.new', 'push', 20)[0]['status'] ?? null, 'sent', 'the outbox says sent');
eq(outbox('order.new', 'push', 22)[0]['last_error'] ?? null, 'no phone has notifications on', 'Chidi has no phone on: skipped, and why');
// Every push is urgent, whatever the event, and no event may say otherwise:
// Android holds a normal-priority push while the phone sleeps and hands it
// over when Nfelia is next opened, which the owner saw as "notifications
// only come when I open the app" (21 Oct 2026).
notify('task.assigned', 2, $sample, ['users' => [20]]);
deliver();
$task = array_values(array_filter(pushes_to($ada), fn($m) => ($m['tag'] ?? '') === 'task-900'))[0] ?? [];
eq([$task['title'] ?? null, $task['_headers']['urgency'] ?? null], ['New task for you', 'high'],
   'a task handed over is sent urgent like everything else (anything less waits on the phone until the app is opened)');
eq(array_keys(array_filter(notify_events(), fn($e) => array_key_exists('urgency', $e))), [], 'no event carries an urgency of its own (the key would do nothing)');

section('Phones that are gone, busy or refusing');
clean_slate();
$gone = phone(21, 'gone');
notify('payment.invoice', 2, $sample);
deliver();
eq((int)q("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = ?", [$gone['endpoint']]), 0, 'a phone that unsubscribed (410) is forgotten');
eq((int)q("SELECT COUNT(*) FROM push_dead WHERE endpoint_hash = ?", [hash('sha256', $gone['endpoint'])]), 1, 'and remembered as dead, so the phone makes a new subscription instead of saving it again');
eq([outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, outbox('payment.invoice', 'push', 21)[0]['last_error'] ?? null],
   ['skipped', 'no phone has notifications on'], 'the send waits for a phone, like one that never had any');
eq(bell(21, 'push.lost')[0]['title'] ?? null, 'Phone alerts stopped on A phone', 'Bisi is told her phone stopped getting alerts (the bell now, an email tomorrow if no phone is back)');
$lost_mail = outbox('push.lost', 'email', 21)[0] ?? [];
ok(($lost_mail['status'] ?? '') === 'pending' && (int)q("SELECT next_attempt_at > NOW() + INTERVAL 23 HOUR FROM notify_outbox WHERE id = ?", [$lost_mail['id'] ?? 0]) === 1, 'the email waits a day');
$back = phone(21);
notify_push_revive(get_db(), 21);
deliver();
eq([outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, count(pushes_to($back))], ['sent', 1], 'a phone comes back in time: the waiting push goes to it');
exec_sql("UPDATE notify_outbox SET next_attempt_at = NOW() WHERE id = ?", [$lost_mail['id'] ?? 0]);
deliver();
eq(outbox('push.lost', 'email', 21)[0]['status'] ?? null, 'skipped', 'and the email about the lost phone is not sent after all');
exec_sql("DELETE FROM push_subscriptions");
clean_slate();
$busy = phone(21, 'busy');
notify('payment.invoice', 2, $sample);
deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq([$row['status'], (int)$row['attempts']], ['pending', 1], 'a busy push service (503): waiting to try again');
ok((int)q("SELECT next_attempt_at > NOW() FROM notify_outbox WHERE id = ?", [$row['id']]) === 1, 'later, not straight away');
for ($i = 0; $i < 5; $i++) deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq([$row['status'], (int)$row['attempts'] > NOTIFY_MAX_ATTEMPTS['push']], ['pending', true], 'it keeps trying past ' . NOTIFY_MAX_ATTEMPTS['push'] . ' tries while the push is still worth sending (a day for money in)');
exec_sql("UPDATE notify_outbox SET expires_at = NOW() + INTERVAL 1 MINUTE WHERE id = ?", [$row['id']]);
deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq($row['status'], 'failed', 'with its time nearly up, it gives up, keeping why: ' . $row['last_error']);
exec_sql("DELETE FROM push_subscriptions");
clean_slate();
$both = [phone(21, 'busy'), phone(21)];
notify('payment.invoice', 2, $sample);
deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq([$row['status'], substr((string)$row['last_error'], 0, 23)], ['pending', 'sent to 1 of 2 phones; '], 'two phones, one busy: the one that got it is done, the busy one is tried again');
for ($i = 0; $i < 3; $i++) deliver();
eq(count(pushes_to($both[1])), 1, 'the phone that took it is never sent it again');
exec_sql("UPDATE notify_outbox SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE channel = 'push'");
deliver();
eq(outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, 'sent', 'the busy phone never takes it before the time runs out: counted as sent, since a phone got it');
exec_sql("DELETE FROM push_subscriptions");
clean_slate();
$bad = phone(21, 'bad');
notify('payment.invoice', 2, $sample);
deliver();
eq((int)q("SELECT failures FROM push_subscriptions WHERE endpoint = ?", [$bad['endpoint']]), 1, 'a refused push (400) counts against that phone');
exec_sql("DELETE FROM push_subscriptions");

section('A push too late to be useful is dropped');
clean_slate();
phone(22);
notify('task.reminder', 2, $sample, ['users' => [22]]);
exec_sql("UPDATE notify_outbox SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE channel = 'push'");
deliver();
eq(outbox('task.reminder', 'push', 22)[0]['last_error'] ?? null, 'too late to be useful', 'an expired reminder is skipped, not sent hours late');
exec_sql("DELETE FROM push_subscriptions");

section('Email: sent, retried while the mail server is down, then given up on');
clean_slate();
notify('order.new', 2, $sample, ['dedupe' => 'mail']);
deliver();
$m = mails_to('ada@adashair.test');
eq(count($m), 1, 'Ada got the email');
$n = (int)q("SELECT id FROM notifications WHERE user_id = 20 AND event = 'order.new'");
eq($m[0]['subject'] ?? null, 'New order: ₦65,000', 'its subject is the alert');
ok(str_contains($m[0]['html'] ?? '', APP_URL . '/notifications/open/' . $n), 'its button opens the order through notifications/open');
ok(str_contains($m[0]['text'] ?? '', 'Order ORD-0700') && str_contains($m[0]['text'] ?? '', 'Ngozi Eze'), 'it lists the order and the customer');
ok(str_contains($m[0]['html'] ?? '', '/more#notifications'), 'and says how to change what comes by email');
$staff_mail = mails_to('chidi@adashair.test')[0]['text'] ?? '';
ok($staff_mail !== '' && !str_contains($staff_mail, '₦'), 'Chidi\'s copy has no amounts');
clean_slate();
touch(T_MAIL_DIR . '/REFUSE');
notify('billing.failed', 2, []);
deliver();
$row = outbox('billing.failed', 'email', 20)[0];
eq([$row['status'], (int)$row['attempts']], ['pending', 1], 'the mail server turns it away: kept to try again');
unlink(T_MAIL_DIR . '/REFUSE');
deliver();
eq(outbox('billing.failed', 'email', 20)[0]['status'] ?? null, 'sent', 'the next try, once it is back: sent');
eq(count(mails_to('ada@adashair.test')), 1, 'exactly one email arrived');
clean_slate();
touch(T_MAIL_DIR . '/REFUSE');
notify('billing.failed', 2, [], ['dedupe' => 'down']);
for ($i = 0; $i < 7; $i++) deliver();
eq(outbox('billing.failed', 'email', 20)[0]['status'] ?? null, 'failed', 'down for good: given up after ' . NOTIFY_MAX_ATTEMPTS['email'] . ' tries');
unlink(T_MAIL_DIR . '/REFUSE');

section('An address that doesn\'t exist: given up at once, and whoever can fix it is told (#863)');
clean_slate();
exec_sql("DELETE FROM error_events");
exec_sql("DELETE FROM ops_alerts");
exec_sql("DELETE FROM error_groups");
exec_sql("UPDATE users SET email = 'chidi@gone.invalid' WHERE id = 22");
$log = sys_get_temp_dir() . '/nf_bounce_' . getmypid() . '.log';
@unlink($log);
ini_set('error_log', $log);
notify('order.new', 2, $sample, ['dedupe' => 'bounce']);
deliver();
deliver();
ini_restore('error_log');
$row = outbox('order.new', 'email', 22)[0] ?? [];
eq([$row['status'] ?? null, (int)($row['attempts'] ?? 0)], ['failed', 1], 'the mail server says Chidi\'s address doesn\'t exist: given up after one try, never retried');
ok(str_starts_with((string)($row['last_error'] ?? ''), 'bounced: 550 5.1.1 '), 'what it said is kept: ' . ($row['last_error'] ?? ''));
eq(count(mails_to('ada@adashair.test')), 1, 'Ada\'s own email about the order still went');
eq(told('email.bounced'), [20], 'the owner is told, and only her');
$note = bell(20, 'email.bounced')[0] ?? [];
eq($note['title'] ?? '', 'Emails to Chidi Staff don’t arrive', 'naming the person');
ok(str_contains($note['message'] ?? '', 'chidi@gone.invalid') && str_contains($note['message'] ?? '', 'under Team'), 'the address, and where to change it: ' . ($note['message'] ?? ''));
eq($note['action_url'] ?? '', 'settings?tab=staff', 'it opens Settings, Team');
eq(outbox('email.bounced', 'email'), [], 'by bell and phone, not email');
$groups = rows("SELECT kind, level, title FROM error_groups");
eq(array_map(fn($g) => [$g['kind'], $g['level']], $groups), [['job', 'notice']], 'Ops → Errors notes it once, as a notice');
ok(str_starts_with($groups[0]['title'] ?? '', 'Email bounced: ') && str_contains($groups[0]['title'] ?? '', '<email>') && !str_contains($groups[0]['title'] ?? '', 'gone.invalid'),
   'with the address masked: ' . ($groups[0]['title'] ?? ''));
eq((int)q("SELECT COUNT(*) FROM ops_alerts WHERE kind LIKE 'error.%'"), 0, 'which alerts nobody on the Nfelia team');
$lines = array_values(array_filter(array_map('trim', @file($log) ?: [])));
@unlink($log);
ok(count($lines) === 1 && preg_match('/Nfelia notice \[E[A-Z2-9]{7}\]: Email bounced/', $lines[0]),
   'the server log has that one line, which the log reader skips, and no "SMTP failure": ' . implode(' | ', $lines));
notify('order.new', 2, $sample, ['dedupe' => 'bounce-again']);
deliver();
eq(count(bell(20, 'email.bounced')), 1, 'another email bouncing from the same address doesn\'t tell her again');

exec_sql("UPDATE users SET email = 'chidi@shop.nouser' WHERE id = 22");
notify('order.new', 2, $sample, ['dedupe' => 'nouser']);
deliver();
eq(outbox('order.new', 'email', 22)[2]['status'] ?? null, 'failed', 'a mail server that only says "No Such User Here": the same');
clean_slate();
exec_sql("UPDATE users SET email = 'chidi@relay.denied' WHERE id = 22");
notify('order.new', 2, $sample, ['dedupe' => 'denied']);
deliver();
$row = outbox('order.new', 'email', 22)[0] ?? [];
eq([$row['status'] ?? null, $row['last_error'] ?? null], ['pending', 'the mail server did not accept it'],
   'refused for another reason (5.7.1, it won\'t relay): kept to try again, as before');
eq(told('email.bounced'), [], 'and nobody is told the address doesn\'t exist');
exec_sql("UPDATE users SET email = 'chidi@adashair.test' WHERE id = 22");

clean_slate();
exec_sql("UPDATE users SET email = 'ada@gone.invalid' WHERE id = 20");
notify('billing.failed', 2, []);
deliver();
$note = bell(20, 'email.bounced')[0] ?? [];
ok(($note['action_url'] ?? '') === 'settings?tab=profile' && str_contains($note['message'] ?? '', 'under Profile'),
   'the owner\'s own address: she is told to change it in her Profile');
exec_sql("UPDATE users SET email = 'ada@adashair.test' WHERE id = 20");

clean_slate();
exec_sql("DELETE FROM ops_alerts");
exec_sql("UPDATE ops_staff SET email = 'sam@gone.invalid' WHERE id = 2");
notify_email(null, 'sam@gone.invalid', 'Sam Support', 'A test', 'ops_message',
             ['headline' => 'A test', 'message' => 'Hello', 'button' => 'Open Ops', 'url' => APP_URL, 'note' => ''], 'bounce-ops', 'ops.test');
deliver();
eq(array_map('intval', array_column(rows("SELECT staff_id FROM ops_alerts WHERE kind = 'email.bounced' ORDER BY staff_id"), 'staff_id')), [1],
   'someone on the Nfelia team: the Ops admins are told, to change it in Team');
exec_sql("UPDATE ops_staff SET email = 'sam@nfelia.test' WHERE id = 2");

section('A shopper\'s address that doesn\'t exist: the owner asks them for the right one (#863)');
clean_slate();
$vars = ['state' => 'confirmed', 'first_name' => 'Ngozi', 'shop_name' => "Ada's Hair & Co", 'order_number' => 'ORD-0700', 'order_url' => null, 'review_url' => null, 'message' => ''];
notify_email(2, 'ngozi@gone.invalid', 'Ngozi Eze', 'Your order is confirmed', 'order_update', $vars, 'order:700:confirmed', 'shopper.order_confirmed');
deliver();
eq(outbox('shopper.order_confirmed', 'email')[0]['status'] ?? null, 'failed', 'given up at once');
eq(told('email.bounced_shopper'), [20], 'the owner is told');
$note = bell(20, 'email.bounced_shopper')[0] ?? [];
eq($note['title'] ?? '', 'Ask Ngozi Eze for their email', 'to ask the customer for their email');
ok(str_contains($note['message'] ?? '', 'order ORD-0700') && str_contains($note['message'] ?? '', 'ngozi@gone.invalid') && str_contains($note['message'] ?? '', 'WhatsApp'),
   'about which order, and on WhatsApp: ' . ($note['message'] ?? ''));
eq($note['action_url'] ?? '', 'orders/view/700#email', 'it opens the order, where the message is ready');
notify_email(2, 'ngozi@gone.invalid', 'Ngozi Eze', 'Your order is ready', 'order_update', ['state' => 'ready'] + $vars, 'order:700:ready', 'shopper.order_ready');
deliver();
eq(count(bell(20, 'email.bounced_shopper')), 1, 'once per order, however many of its emails bounce');
ok(bounce_at(get_db(), 2, 'ngozi@gone.invalid') !== null && bounce_at(get_db(), 2, 'ngozi@example.test') === null, 'the order page can tell the address bounced');
exec_sql("INSERT INTO notify_outbox (business_id, event, channel, recipient, payload, status) VALUES (2, 'shopper.order_done', 'email', 'ngozi@gone.invalid', '{}', 'sent')");
eq(bounce_at(get_db(), 2, 'ngozi@gone.invalid'), null, 'an email that went through since clears it');

clean_slate();
exec_sql("INSERT INTO bookings (id, business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, customer_id, customer_name, customer_phone, customer_email, status)
          VALUES (1, 2, 302, 'Bridal styling', 'BKG-0001', ?, 'day', CURDATE() + INTERVAL 3 DAY, CURDATE() + INTERVAL 4 DAY, 200, 'Ngozi Eze', '08031110000', 'ngozi@gone.invalid', 'confirmed')",
         [bin2hex(random_bytes(16))]);
notify_email(2, 'ngozi@gone.invalid', 'Ngozi Eze', 'Your booking', 'booking_update', ['state' => 'confirmed', 'first_name' => 'Ngozi', 'shop_name' => 'Ada',
             'booking_number' => 'BKG-0001', 'service' => 'Bridal styling', 'when' => 'Sat', 'booking_url' => APP_URL, 'total' => '₦80,000', 'deposit' => '', 'message' => ''],
             'booking:1:confirmed', 'shopper.booking');
deliver();
$note = bell(20, 'email.bounced_shopper')[0] ?? [];
ok(($note['action_url'] ?? '') === 'bookings/view/1#email' && str_contains($note['message'] ?? '', 'booking BKG-0001'), 'a booking\'s email: the note opens the booking');
exec_sql("DELETE FROM bookings");

section('Only one sender per message');
clean_slate();
notify('billing.failed', 2, []);
$id = (int)outbox('billing.failed', 'email')[0]['id'];
notify_flush([$id]);
notify_flush([$id]);
eq(count(mails_to('ada@adashair.test')), 1, 'sending the same message twice sends it once');
clean_slate();
notify('billing.failed', 2, []);
$id = (int)outbox('billing.failed', 'email')[0]['id'];
exec_sql("UPDATE notify_outbox SET status = 'sending', next_attempt_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?", [$id]);
notify_flush();
eq(outbox('billing.failed', 'email')[0]['status'] ?? null, 'sent', 'a send whose sender died is picked up again once its claim lapses');

section('Emails to people who are not signed up');
clean_slate();
$a = notify_email(2, 'shopper@example.test', 'Ngozi', 'Your order', 'order_update',
                  ['state' => 'confirmed', 'first_name' => 'Ngozi', 'shop_name' => 'Ada', 'order_number' => 'ORD-1', 'order_url' => null, 'review_url' => null, 'message' => ''], 'fx:once');
$b = notify_email(2, 'shopper@example.test', 'Ngozi', 'Your order', 'order_update', [], 'fx:once');
ok($a !== null && $b === null, 'the same one twice: queued once');
eq(notify_email(2, 'not-an-address', 'X', 'S', 'order_update', []), null, 'no valid address: nothing queued');
deliver();
eq(count(mails_to('shopper@example.test')), 1, 'it arrives, branded for the shop');

/* ── Reading ───────────────────────────────────────────────── */

section('The bell: each person their own, old shared alerts kept for owners');
clean_slate();
exec_sql("INSERT INTO notifications (business_id, user_id, type, title, message) VALUES
    (2, NULL, 'general', 'Old shared alert', 'from before'), (2, 20, 'payment_received', 'Old payment', 'recorded by Ada'),
    (2, 22, 'general', 'Old reminder for Chidi', '')");
notify('order.new', 2, $sample);
$sees = function (int $uid, string $role) {
    [$w, $a] = notify_visible_sql(2, $uid, $role);
    return array_column(rows("SELECT title FROM notifications WHERE {$w} ORDER BY id", $a), 'title');
};
eq($sees(20, 'owner'), ['Old shared alert', 'Old payment', 'New order: ₦65,000'], 'the owner sees old shared alerts and her own');
eq($sees(22, 'staff'), ['Old reminder for Chidi', 'New order ORD-0700'], 'staff see only their own (no old money alerts)');
eq($sees(21, 'admin'), ['Old shared alert', 'Old payment', 'New order: ₦65,000'], 'an admin sees the old shared ones too');
eq(notify_unread_count(get_db(), 20), 1, 'the app-icon number counts new alerts');

section('Links stay inside the app');
foreach ([
    ['orders/view/5', 'orders/view/5'], ['/orders/view/5', 'orders/view/5'], ['https://nfelia.com/invoices/view/3', 'invoices/view/3'],
    ['http://old.example/settings?tab=billing', 'settings?tab=billing'], ['momo/review#payment-4', 'momo/review#payment-4'],
    ['javascript:alert(1)', ''], ['a/../../etc', ''], ['', ''], ['x y', ''], ['//evil.test/x', 'evil.test/x'],
] as [$in, $want]) {
    eq(notify_safe_path($in), $want, var_export($in, true));
}

section('Who may use Nfelia today (one rule for the app, the shop and the cron: billing_access)');
foreach ([
    ['trial, its last day', ['status' => 'trial', 'trial_ends_at' => '2026-10-20'], '2026-10-20', 'trial'],
    ['trial, the day after', ['status' => 'trial', 'trial_ends_at' => '2026-10-19'], '2026-10-20', 'locked'],
    ['a paid month, its last day', ['status' => 'active', 'current_period_end' => '2026-10-20'], '2026-10-20', 'paid'],
    ['a paid month ended, within the grace days', ['status' => 'active', 'current_period_end' => '2026-10-20'], '2026-10-23', 'grace'],
    ['a paid month ended, grace over', ['status' => 'active', 'current_period_end' => '2026-10-20'], '2026-10-24', 'locked'],
    ['already past its date when months began to expire', ['status' => 'active', 'current_period_end' => '2026-09-01', 'expiry_exempt' => 1], '2026-10-24', 'paid'],
    ['cancelled, paid time left', ['status' => 'cancelled', 'current_period_end' => '2026-10-25'], '2026-10-25', 'paid'],
    ['cancelled, paid time used', ['status' => 'cancelled', 'current_period_end' => '2026-10-25'], '2026-10-26', 'locked'],
    ['a failed card payment, within its grace', ['status' => 'past_due', 'grace_ends_at' => '2026-10-23 10:00:00'], '2026-10-23', 'grace'],
    ['a failed card payment, after', ['status' => 'past_due', 'grace_ends_at' => '2026-10-23 10:00:00'], '2026-10-24', 'locked'],
    ['suspended', ['status' => 'suspended'], '2026-10-20', 'locked'],
] as [$name, $sub, $day, $want]) {
    eq(billing_access($sub, $day), $want, $name);
}
eq(shop_visibility(['status' => 'cancelled', 'current_period_end' => date('Y-m-d', strtotime('+3 days'))]), 'live', 'a cancelled shop stays open while paid for');
eq(shop_visibility(['status' => 'cancelled', 'current_period_end' => '2020-01-01']), 'closed', 'and closes after');

section('How many people a business may have (billing_seats): a free trial includes one staff member');
foreach ([
    ['on the trial, a one-seat plan: the owner and one staff member', ['status' => 'trial', 'trial_ends_at' => '2026-10-20', 'user_seats' => 1, 'extra_seats' => 0], '2026-10-20', 2],
    ['the trial over and nothing paid: the plan\'s one seat', ['status' => 'trial', 'trial_ends_at' => '2026-10-19', 'user_seats' => 1, 'extra_seats' => 0], '2026-10-20', 1],
    ['a paid month for the owner alone', ['status' => 'active', 'current_period_end' => '2026-10-30', 'user_seats' => 1, 'extra_seats' => 0], '2026-10-20', 1],
    ['a paid month with two staff seats', ['status' => 'active', 'current_period_end' => '2026-10-30', 'user_seats' => 1, 'extra_seats' => 2], '2026-10-20', 3],
    ['a trial that already has more seats keeps them', ['status' => 'trial', 'trial_ends_at' => '2026-10-20', 'user_seats' => 1, 'extra_seats' => 3], '2026-10-20', 4],
    ['no subscription row: the owner', null, '2026-10-20', 1],
] as [$name, $sub, $day, $want]) {
    eq(billing_seats($sub, $day), $want, $name);
}

section('A form too big for the server is sent back to its page (back_path)');
$_SERVER['HTTP_REFERER'] = APP_URL . '/settings?tab=business';
eq(back_path(), 'settings?tab=business', 'from a page of the app, back to it');
$_SERVER['HTTP_REFERER'] = 'https://evil.test/settings';
eq(back_path(), 'dashboard', 'from anywhere else, Home');
unset($_SERVER['HTTP_REFERER']);
eq(back_path('settings'), 'settings', 'with no page given, where the caller says');

done();
