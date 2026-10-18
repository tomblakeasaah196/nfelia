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
    'item' => 'Braids', 'when' => 'Sat 24 Oct, 10:00am', 'deposit' => 5000, 'quotation_id' => 500, 'quotation_number' => 'ADA-QUO-0500',
    'reason' => 'Too pricey', 'comment' => 'Can you do it for less?', 'delivery_id' => 600, 'delivery_number' => 'ADA-DN-0600',
    'signer' => 'Ngozi Eze', 'rating' => 4, 'who' => 'Ngozi E.', 'days' => 3, 'expiry_date' => date('Y-m-d'), 'title' => 'Restock closures',
    'body' => '2 items are running low.', 'task_id' => 900, 'line' => 'Today, 2:00pm', 'from' => 'Ada', 'by' => 'Ada', 'to_name' => 'Bisi',
    'amount_text' => '₦10,000', 'period_end' => date('Y-m-d'), 'access_until' => date('Y-m-d'), 'trial_ends' => date('Y-m-d'),
    'grace_ends' => date('Y-m-d H:i:s'), 'host' => 'adas-hair.nfelia.com', 'changed' => true, 'has_current' => false, 'ticket_id' => 1,
    'subject' => 'Cannot print', 'reply' => 'Fixed now.', 'referred' => 'Kola Prints', 'reward' => 2500, 'role' => 'an admin',
    'device' => 'Chrome on Android', 'staff' => 'Sam from Nfelia', 'new_email' => 'new@example.test', 'business' => "Ada's Hair & Co", 'payment_id' => 1,
    'contact' => '08030000000', 'count' => 4,
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
eq(count(outbox('payment.invoice', 'push', 21)), 0, 'Bisi turned money alerts off her phone: no push');
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
eq([$quiet['status'] ?? null, $quiet['last_error'] ?? null], ['skipped', 'quiet hours'], 'Bisi\'s quiet hours: her phone stays silent (the reason is kept)');
eq(count(bell(21, 'payment.invoice')), 1, 'it still waits in her bell');
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

section('Phones that are gone, busy or refusing');
clean_slate();
$gone = phone(21, 'gone');
notify('payment.invoice', 2, $sample);
deliver();
eq((int)q("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = ?", [$gone['endpoint']]), 0, 'a phone that unsubscribed (410) is forgotten');
eq(outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, 'failed', 'and the send is marked failed, not retried');
clean_slate();
$busy = phone(21, 'busy');
notify('payment.invoice', 2, $sample);
deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq([$row['status'], (int)$row['attempts']], ['pending', 1], 'a busy push service (503): waiting to try again');
ok((int)q("SELECT next_attempt_at > NOW() FROM notify_outbox WHERE id = ?", [$row['id']]) === 1, 'later, not straight away');
for ($i = 0; $i < 5; $i++) deliver();
$row = outbox('payment.invoice', 'push', 21)[0];
eq([$row['status'], (int)$row['attempts']], ['failed', NOTIFY_MAX_ATTEMPTS['push']], 'it gives up after ' . NOTIFY_MAX_ATTEMPTS['push'] . ' tries, keeping why');
exec_sql("DELETE FROM push_subscriptions");
clean_slate();
$both = [phone(21, 'busy'), phone(21)];
notify('payment.invoice', 2, $sample);
deliver();
eq(outbox('payment.invoice', 'push', 21)[0]['status'] ?? null, 'sent', 'two phones, one busy: sent, because one got it');
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

done();
