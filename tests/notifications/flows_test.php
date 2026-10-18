<?php
/**
 * Every notification, made the way people make it: over HTTP against the
 * running app, as the shopper, customer, staff member, owner or Nfelia team
 * member who does the thing. For each: who hears, what it says, what reaches
 * their phone and inbox, and that opening it lands on the exact page, through
 * sign-in when they are signed out.   php tests/notifications/flows_test.php
 */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/../ops/lib.php';   // the Nfelia team works in Ops (tests/ops)
t_guard();
fixture_reset();

const ADA = 20, BISI = 21, CHIDI = 22, KOLA = 30, TOLU = 10, SADE = 11;
const EMAIL = [ADA => 'ada@adashair.test', BISI => 'bisi@adashair.test', CHIDI => 'chidi@adashair.test',
               KOLA => 'kola@kolaprints.test', TOLU => 'tolu@nfelia.test', SADE => 'sade@nfelia.test'];

/** Signed in, once per person per run (each one its own browser). */
function as_(int $who): Http
{
    static $sessions = [];
    return $sessions[$who] ??= signin(EMAIL[$who]);
}

/** The newest bell row of an event for a person. */
function latest(int $who, string $event): array
{
    $b = bell($who, $event);
    return $b ? end($b) : [];
}

/**
 * The alert opens the exact page for the person it is for: notifications/open/{id}
 * goes to its link, the page loads, and the alert is marked read.
 */
function opens(int $who, string $event, string $want, ?string $page_has = null): void
{
    $n = latest($who, $event);
    if (!ok($n !== [], "{$event}: {$who} has it")) return;
    $h = as_($who);
    $h->get('notifications/open/' . $n['id']);
    eq($h->to(), $want, "{$event}: opening it goes to {$want}");
    $page = strtok($want, '#');
    $h->get($page);
    ok($h->status === 200 && !str_contains($h->body, 'Fatal error'), "{$event}: and that page opens for them (" . $h->status . ($h->location ? ' → ' . $h->to() : '') . ')');
    if ($page_has !== null) ok(str_contains($h->body, $page_has), "{$event}: showing " . $page_has);
    eq((int)q("SELECT is_read FROM notifications WHERE id = ?", [$n['id']]), 1, "{$event}: and it is marked read");
}

/** One rule for the whole trigger: these people, this link, nobody else. */
function expect(string $event, array $who, string $url): void
{
    $got = told($event);
    sort($who);
    eq($got, $who, "{$event}: told " . implode(', ', $who));
    foreach (rows("SELECT action_url FROM notifications WHERE event = ?", [$event]) as $r) {
        if ($r['action_url'] !== $url) { eq($r['action_url'], $url, "{$event}: every copy links to {$url}"); return; }
    }
    ok(true, "{$event}: every copy links to {$url}");
}

/* ══ Opening alerts ═══════════════════════════════════════════ */

section('Signed out: an alert goes through sign-in and straight to its page');
clean_slate();
notify('order.new', 2, ['order_id' => 700, 'order_number' => 'ORD-0700', 'customer' => 'Ngozi Eze', 'items' => 1, 'total' => 65000,
                        'channel' => 'cart_pop', 'fulfillment' => 'delivery'], ['dedupe' => 'open-test']);
$nid = (int)latest(ADA, 'order.new')['id'];
$anon = new Http();
$anon->get("notifications/open/{$nid}");
eq($anon->to(), 'login?next=' . rawurlencode("notifications/open/{$nid}"), 'signed out: sent to sign in, carrying where to go');
$anon->get($anon->location);
ok(str_contains($anon->body, 'Sign in to open that.'), 'the sign-in page says why');
ok(str_contains($anon->body, 'name="next" value="notifications/open/' . $nid . '"'), 'and keeps the destination in the form');
$anon->post('login', ['email' => EMAIL[ADA], 'password' => T_PASSWORD, 'next' => "notifications/open/{$nid}"]);
eq($anon->to(), "notifications/open/{$nid}", 'signed in: straight back to the alert');
$anon->get($anon->location);
eq($anon->to(), 'orders/view/700', 'which opens the order');
$anon->get($anon->location);
ok($anon->status === 200 && str_contains($anon->body, 'ORD-0700'), 'the order itself, ready to act on');
eq((int)q("SELECT is_read FROM notifications WHERE id = ?", [$nid]), 1, 'and the alert is read');

section('Every way of signing in comes back to it');
$b = new Http();
$b->get("notifications/open/{$nid}")->follow();
$b->post('login', ['email' => EMAIL[ADA], 'password' => T_PASSWORD]);   // a form without the field: the session kept it
eq($b->to(), "notifications/open/{$nid}", 'password, even without the form field (the session kept it)');
$c = new Http();
$c->get("notifications/open/{$nid}")->follow();
$c->get('login-link?next=' . rawurlencode("notifications/open/{$nid}"));
ok(str_contains($c->body, 'name="next" value="notifications/open/' . $nid . '"'), 'the emailed-link page carries it');
foreach (glob(T_MAIL_DIR . '/*.eml') ?: [] as $f) unlink($f);
$c->post('login-link', ['email' => EMAIL[ADA], 'next' => "notifications/open/{$nid}"]);
$m = mails_to(EMAIL[ADA]);
ok(preg_match('#(' . preg_quote(APP_URL, '#') . '/signin/[a-f0-9]{64}\?next=[^"\s<]+)#', $m[0]['html'] ?? '', $link), 'the emailed sign-in link carries it too');
$phone = new Http();   // the link is often opened in another browser
$phone->get(html_entity_decode($link[1] ?? ''));
ok(str_contains($phone->body, 'name="next" value="notifications/open/' . $nid . '"'), 'its page keeps it');
preg_match('/name="token" value="([a-f0-9]{64})"/', $phone->body, $t);
$phone->post('signin', ['token' => $t[1] ?? '', 'next' => "notifications/open/{$nid}"]);
eq($phone->to(), "notifications/open/{$nid}", 'and signing in with it comes back to the alert');
$r = new Http();
$r->get('login');
$r->post('login', ['email' => EMAIL[ADA], 'password' => T_PASSWORD, 'remember_me' => '1']);
$r->close_browser();
$r->get("notifications/open/{$nid}");
eq($r->to(), 'orders/view/700', '"keep me signed in": the browser was closed, the alert still opens straight on the order');
$d = as_(ADA);
$d->get('login?next=' . rawurlencode('orders/view/700'));
eq($d->to(), 'orders/view/700', 'already signed in: a sign-in link with a destination just goes there');
$e = new Http();
$e->get('login');
$e->post('login', ['email' => EMAIL[ADA], 'password' => T_PASSWORD, 'next' => 'https://evil.test/steal']);
ok(str_starts_with($e->location, APP_URL . '/'), 'a destination on another site is never followed: ' . $e->location);
$e->post('logout', []);
$e->get('login');
$e->post('login', ['email' => EMAIL[ADA], 'password' => 'wrong', 'next' => 'orders/view/700']);
eq($e->to(), 'login?next=' . rawurlencode('orders/view/700'), 'a wrong password keeps the destination for the next try');

section('An alert opened by the wrong person');
clean_slate();
notify('order.new', 2, ['order_id' => 700, 'order_number' => 'ORD-0700', 'customer' => 'Ngozi', 'items' => 1, 'total' => 1, 'channel' => 'cart_pop', 'fulfillment' => 'pickup']);
$nid = (int)latest(ADA, 'order.new')['id'];
$h = as_(CHIDI);
$h->get("notifications/open/{$nid}");
eq($h->to(), 'notifications', 'Chidi opens Ada\'s alert (a shared phone): his own list instead');
$h->get('notifications');
ok(str_contains($h->body, 'That alert was for Ada. You are signed in as Chidi.'), 'told whose it was');
eq((int)q("SELECT is_read FROM notifications WHERE id = ?", [$nid]), 0, 'and Ada\'s stays unread');
$k = as_(KOLA);
$k->get("notifications/open/{$nid}")->follow();
ok(str_contains($k->body, 'belongs to another Nfelia account'), 'someone from another business: told to sign in to that account');

/* ══ The bell ════════════════════════════════════════════════ */

section('The bell: its own rows, raw text, live count, marking read');
clean_slate();
notify('order.new', 2, ['order_id' => 700, 'order_number' => 'ORD-0700', 'customer' => 'Femi & Sons "Wholesale"', 'items' => 1, 'total' => 65000,
                        'channel' => 'cart_pop', 'fulfillment' => 'pickup']);
notify('payment.invoice', 2, ['invoice_id' => 400, 'invoice_number' => 'ADA-INV-0400', 'customer' => 'Ngozi', 'amount' => 1000, 'method' => 'cash', 'balance' => 0]);
$a = as_(ADA);
$a->get('api/notifications?action=count');
eq($a->data()['count'] ?? null, 2, 'Ada: 2 unread');
$a->get('api/notifications?action=list');
$list = $a->data()['notifications'] ?? [];
eq($list[1]['message'] ?? null, 'Femi & Sons "Wholesale" ordered 1 item on your shop for pickup · ORD-0700', 'text comes raw (the page escapes it once, not twice)');
eq($list[0]['url'] ?? null, 'notifications/open/' . $list[0]['id'], 'each row opens through notifications/open');
$c = as_(CHIDI);
$c->get('api/notifications?action=list');
eq(count($c->data()['notifications'] ?? []), 1, 'Chidi sees only his own (the order, not the money)');
$a->json('api/notifications?action=mark_read', ['id' => $list[0]['id']]);
$a->get('api/notifications?action=count');
eq($a->data()['count'] ?? null, 1, 'one marked read');
$c->get('api/notifications?action=count');
eq($c->data()['count'] ?? null, 1, 'Chidi\'s own is untouched: everyone has their own read and unread');
$a->json('api/notifications?action=mark_read', ['id' => 'all'], ['X-CSRF-Token: wrong']);
eq($a->status, 403, 'marking read needs the page\'s token');
$a->json('api/notifications?action=mark_read', ['id' => 'all']);
$a->get('api/notifications?action=count');
eq($a->data()['count'] ?? null, 0, 'mark all read');
$x = new Http();
$x->get('api/notifications?action=count');
eq($x->status, 401, 'signed out: the API says so');

section('The notifications page');
clean_slate();
foreach (range(1, 35) as $i) notify('quote.comment', 2, ['quotation_id' => 500, 'quotation_number' => 'Q' . $i, 'customer' => 'Ngozi', 'comment' => "Note {$i}"]);
$a->get('notifications');
ok($a->status === 200 && substr_count($a->body, 'class="nf-note is-new"') === 30, 'the newest 30, unread ones marked');
ok(str_contains($a->body, 'Show older'), 'with a way to the older ones');
ok(str_contains($a->body, '35 unread'), 'and the unread count');
$a->post('notifications/read-all', []);
eq((int)q("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [ADA]), 0, 'Mark all read');
eq((int)q("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [BISI]), 35, 'Bisi\'s are hers to read');

/* ══ Choices and tests ═══════════════════════════════════════ */

section('More > Notifications saves each person\'s choices');
$c = as_(CHIDI);
$c->get('api/notifications?action=prefs');
eq(array_keys($c->data()['prefs']['topics'] ?? []), ['orders', 'bookings', 'stock', 'reminders', 'morning', 'tasks', 'security'], 'Chidi (staff) chooses only what staff get');
$c->json('api/notifications?action=prefs', ['topics' => ['stock' => ['push' => false], 'money' => ['push' => false], 'security' => ['email' => false]],
                                             'quiet_hours' => true, 'quiet_from' => '22:00', 'quiet_to' => '06:30', 'hide_amounts' => true]);
$p = notify_prefs(CHIDI);
eq([$p['topics']['stock']['push'], $p['quiet_hours'], $p['quiet_from'], $p['quiet_to'], $p['hide_amounts']], [false, true, '22:00', '06:30', true], 'saved: low stock off his phone, quiet hours 22:00–06:30, amounts hidden');
eq($p['topics']['security']['email'], true, 'security emails stay on whatever is sent');
eq((int)q("SELECT COUNT(*) FROM notification_prefs WHERE user_id = ? AND topic = 'money'", [CHIDI]), 0, 'a topic staff don\'t get can\'t be set');
$c->json('api/notifications?action=prefs', ['quiet_from' => '25:99']);
eq(notify_prefs(CHIDI)['quiet_from'], '22:00', 'a nonsense time is ignored');
$c->get('more');
ok(str_contains($c->body, 'data-topic="stock"') && !str_contains($c->body, 'data-topic="money"'), 'the sheet shows his topics only');
exec_sql("DELETE FROM notification_prefs WHERE user_id = ?", [CHIDI]);
exec_sql("UPDATE users SET quiet_hours = 0, hide_amounts = 0 WHERE id = ?", [CHIDI]);

section('Send a test, to the phone and to the inbox');
clean_slate();
exec_sql("UPDATE users SET quiet_hours = 0 WHERE id = ?", [CHIDI]);
$c->json('api/notifications?action=test', ['channel' => 'push']);
eq([$c->status, $c->data()['error'] ?? null], [502, 'No phone has alerts on yet. Tap Turn on, on the phone you want them on.'], 'no phone yet: says what to do');
$ph = phone(CHIDI);
$c->json('api/notifications?action=test', ['channel' => 'push']);
eq($c->data()['message'] ?? null, 'Sent. Check your phone.', 'with a phone: sent');
eq(pushes_to($ph)[0]['title'] ?? null, 'Notifications work', 'and it arrives');
$c->json('api/notifications?action=test', ['channel' => 'email']);
eq($c->data()['message'] ?? null, 'Sent to chidi@adashair.test. Check your inbox.', 'the email test');
eq(count(mails_to(EMAIL[CHIDI])), 1, 'arrives');
$c->json('tasks/push-test', []);
eq($c->data()['message'] ?? null, 'Sent. Check your phone.', 'the Tasks page\'s test uses the same path');

section('A phone renews its subscription, and signing out forgets the phone');
$old = (string)q("SELECT endpoint FROM push_subscriptions WHERE user_id = ?", [CHIDI]);
$sw = new Http();   // the service worker: no session, no token
$sw->json('api/notifications?action=resubscribe', ['old_endpoint' => $old, 'subscription' => [
    'endpoint' => 'https://push.example.test/renewed', 'keys' => ['p256dh' => b64url_encode(webpush_new_keypair()['public']), 'auth' => b64url_encode(random_bytes(16))]]]);
eq([$sw->data()['success'] ?? null, (string)q("SELECT endpoint FROM push_subscriptions WHERE user_id = ?", [CHIDI])], [true, 'https://push.example.test/renewed'],
   'the old endpoint proves which row: replaced in place');
$sw->json('api/notifications?action=resubscribe', ['old_endpoint' => 'https://push.example.test/unknown', 'subscription' => [
    'endpoint' => 'https://push.example.test/x', 'keys' => ['p256dh' => b64url_encode(webpush_new_keypair()['public']), 'auth' => b64url_encode(random_bytes(16))]]]);
eq($sw->data()['success'] ?? null, false, 'an endpoint Nfelia never had changes nothing');
$leaving = signin(EMAIL[CHIDI]);   // a session of its own: the others stay signed in
$leaving->post('logout', ['push_endpoint' => 'https://push.example.test/renewed']);
eq((int)q("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?", [CHIDI]), 0, 'signing out with the phone\'s subscription: that phone stops getting Chidi\'s alerts');

section('Tasks open on the task itself');
$a = as_(ADA);
$a->get('tasks?task=900');
ok(str_contains($a->body, '"task":{') && str_contains($a->body, '"task_id":900'), 'tasks?task=900 opens with that task ready');
$k = as_(KOLA);
$k->get('tasks?task=900');
ok(str_contains($k->body, '"task":null'), 'never someone else\'s');

/* ══ Money in ════════════════════════════════════════════════ */

section('A payment on an invoice');
clean_slate();
$ada_phone = phone(ADA);
$b = as_(BISI);
$b->get('invoices/view/400');
$b->post('invoices/record_payment/400', ['amount' => '60000', 'method' => 'bank_transfer', 'payment_date' => date('Y-m-d')], ['X-Requested-With: XMLHttpRequest']);
eq($b->data()['success'] ?? null, true, 'Bisi records ₦60,000');
deliver();
expect('payment.invoice', [ADA], 'invoices/view/400');
eq(latest(ADA, 'payment.invoice')['title'] ?? null, '₦60,000 received · ADA-INV-0400', 'Ada reads how much and which invoice');
eq(latest(ADA, 'payment.invoice')['message'] ?? null, 'Ngozi Eze paid by bank transfer. ₦40,000 still owed.', 'who, how, and what is left');
eq(pushes_to($ada_phone)[0]['title'] ?? null, '₦60,000 received · ADA-INV-0400', 'her phone buzzes with it');
opens(ADA, 'payment.invoice', 'invoices/view/400', 'ADA-INV-0400');
$b->post('invoices/record_payment/400', ['amount' => '60000', 'method' => 'cash', 'payment_date' => date('Y-m-d')], ['X-Requested-With: XMLHttpRequest']);
ok($b->status >= 400, 'a second ₦60,000 (more than is left) is refused');
eq(count(bell(ADA, 'payment.invoice')), 1, 'and tells nobody');

section('A payment towards a debt');
clean_slate();
$b->post('debts/settle/800', ['amount' => '10000', 'method' => 'cash'], ['X-Requested-With: XMLHttpRequest']);
eq($b->data()['success'] ?? null, true, 'Bisi records ₦10,000 from Ngozi');
expect('payment.debt', [ADA], 'debts/view/800');
eq(latest(ADA, 'payment.debt')['message'] ?? null, 'Ngozi Eze paid towards what they owe. ₦20,000 still owed.', 'and what is left');
opens(ADA, 'payment.debt', 'debts/view/800');

/* ══ Customers answering ═════════════════════════════════════ */

$quote_token = fn(int $id) => (string)q("SELECT token FROM quotations WHERE id = ?", [$id]);

section('A customer comments on, accepts and declines quotes');
clean_slate();
$cust = new Http();
$cust->post('doc/' . $quote_token(502), ['action' => 'comment', 'message' => 'Can you do it for ₦200k?']);
eq($cust->data()['success'] ?? null, true, 'a comment from the quote\'s page');
expect('quote.comment', [ADA, BISI], 'quotations/view/502');
eq(latest(ADA, 'quote.comment')['message'] ?? null, 'Ngozi Eze: “Can you do it for ₦200k?”', 'quoting what they said');
opens(BISI, 'quote.comment', 'quotations/view/502', 'ADA-QUO-0502');
$cust->post('doc/' . $quote_token(500), ['action' => 'sign', 'name' => 'Ngozi Eze', 'signature' => 'data:image/png;base64,iVBORw0KGgo=']);
expect('quote.accepted', [ADA, BISI], 'quotations/view/500');
eq(latest(ADA, 'quote.accepted')['message'] ?? null, 'Ngozi Eze signed and accepted it, ₦250,000.', 'a won deal, with its value');
$cust->post('doc/' . $quote_token(501), ['action' => 'decline', 'reason' => 'Too dear']);
expect('quote.declined', [ADA, BISI], 'quotations/view/501');
eq(latest(ADA, 'quote.declined')['message'] ?? null, 'Ngozi Eze declined it. “Too dear”', 'a declined quote now says so, with the reason');
$cust->post('doc/' . $quote_token(504), ['action' => 'sign', 'name' => 'Someone', 'signature' => 'data:image/png;base64,iVBORw0KGgo=']);
eq($cust->status, 409, 'an answered quote can\'t be signed again');
eq(count(rows("SELECT id FROM notifications WHERE event = 'quote.accepted'")), 2, 'and no second "accepted"');

section('A customer signs for a delivery');
clean_slate();
$sign = (string)q("SELECT signoff_token FROM deliveries WHERE id = 600");
$tok = (string)q("SELECT token FROM deliveries WHERE id = 600");
$cust->get("sign/{$sign}");
if ($cust->status !== 200) { $cust->get("sign/{$tok}"); $sign = $tok; }
$cust->post("sign/{$sign}", ['signoff_name' => 'Ngozi Eze', 'signature_data' => 'data:image/png;base64,iVBORw0KGgo=']);
expect('delivery.signed', [ADA, BISI], 'deliveries/view/600');
eq(latest(ADA, 'delivery.signed')['message'] ?? null, 'Received and signed by Ngozi Eze.', 'who signed');
$cust->post("sign/{$sign}", ['signoff_name' => 'Ngozi Eze', 'signature_data' => 'data:image/png;base64,iVBORw0KGgo=']);
eq(count(rows("SELECT id FROM notifications WHERE event = 'delivery.signed'")), 2, 'signing twice tells nobody twice');
opens(ADA, 'delivery.signed', 'deliveries/view/600', 'ADA-DN-0600');

/* ══ Tasks between people ════════════════════════════════════ */

section('Giving a task, finishing it, moving it');
clean_slate();
$a = as_(ADA);
$a->get('tasks');
$a->json('tasks/save', ['kind' => 'task', 'title' => 'Count the closures', 'date' => date('Y-m-d'), 'assigned_to' => CHIDI]);
$tid = (int)q("SELECT id FROM tasks WHERE title = 'Count the closures'");
expect('task.assigned', [CHIDI], "tasks?task={$tid}");
eq(latest(CHIDI, 'task.assigned')['message'] ?? null, 'Ada gave you: Count the closures · Today', 'who gave what, and when');
opens(CHIDI, 'task.assigned', "tasks?task={$tid}", '"task_id":' . $tid);
$c = as_(CHIDI);
$c->get('tasks');
$c->json('tasks/tick', ['id' => $tid, 'done' => true]);
expect('task.done', [ADA], "tasks?task={$tid}");
eq(latest(ADA, 'task.done')['message'] ?? null, 'Chidi finished the task you gave them.', 'Ada hears it is done');
$a->json('tasks/save', ['kind' => 'task', 'title' => 'Ring Ngozi', 'assigned_to' => CHIDI]);
$tid2 = (int)q("SELECT id FROM tasks WHERE title = 'Ring Ngozi'");
$a->json('tasks/save', ['id' => $tid2, 'kind' => 'task', 'title' => 'Ring Ngozi', 'assigned_to' => BISI]);
eq(latest(CHIDI, 'task.unassigned')['message'] ?? null, 'Ada gave it to Bisi instead.', 'Chidi hears it moved to Bisi');
ok(latest(BISI, 'task.assigned') !== [], 'and Bisi that it is hers');
exec_sql("UPDATE users SET quiet_hours = 1, quiet_from = ?, quiet_to = ? WHERE id = ?",
         [planner_now()->modify('-1 hour')->format('H:i:00'), planner_now()->modify('+1 hour')->format('H:i:00'), BISI]);
phone(BISI);
$a->json('tasks/save', ['kind' => 'task', 'title' => 'Quiet one', 'assigned_to' => BISI]);
deliver();
$quiet = outbox('task.assigned', 'push', BISI);
eq(end($quiet)['last_error'] ?? null, 'quiet hours', 'a task given in Bisi\'s quiet hours doesn\'t buzz her (it used to)');
exec_sql("UPDATE users SET quiet_hours = 0 WHERE id = ?", [BISI]);

/* ══ The team and security ═══════════════════════════════════ */

section('Someone joins the team, changes role, leaves');
clean_slate();
$a->get('settings?tab=staff');
$a->post('settings/add_staff', ['staff_name' => 'Efe New', 'staff_email' => 'efe@adashair.test', 'staff_password' => 'Welcome123', 'staff_role' => 'staff']);
$efe = (int)q("SELECT id FROM users WHERE email = 'efe@adashair.test'");
ok($efe > 0, 'Ada adds Efe');
expect('team.joined', [BISI], 'settings?tab=staff');
eq(latest(BISI, 'team.joined')['title'] ?? null, 'Efe New joined your team', 'Bisi (admin) hears; Ada did it herself');
deliver();
$w = mails_to('efe@adashair.test');
ok(count($w) === 1 && str_contains($w[0]['text'], 'added you to their team') && !str_contains($w[0]['text'], 'Welcome123'),
   'Efe gets a welcome with how to sign in, never the password');
$a->post("settings/update_role/{$efe}", ['role' => 'admin']);
expect('security.role_changed', [$efe], 'dashboard');
eq(latest($efe, 'security.role_changed')['message'] ?? null, "Ada made you an admin at Ada's Hair & Co.", 'Efe hears her new role');
phone($efe);
$a->post("settings/remove_staff/{$efe}", []);
expect('team.removed', [BISI], 'settings?tab=staff');
eq((int)q("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?", [$efe]), 0, 'her phones stop getting the business\'s alerts');

section('Security: new device, password, email');
clean_slate();
exec_sql("DELETE FROM user_devices");
$first = signin(EMAIL[CHIDI]);
eq(told('security.new_device'), [], 'the first device Nfelia sees for someone raises nothing');
$again = signin(EMAIL[CHIDI]);
expect('security.new_device', [CHIDI], 'settings?tab=profile');
ok(str_contains(latest(CHIDI, 'security.new_device')['message'] ?? '', 'If this wasn’t you'), 'a second device: told, with what to do');
deliver();
eq(count(mails_to(EMAIL[CHIDI])), 1, 'by email too, always');
$first->get('dashboard');
$first->post('login', ['email' => EMAIL[CHIDI], 'password' => T_PASSWORD]);
eq(count(bell(CHIDI, 'security.new_device')), 1, 'the same device again: nothing');
clean_slate();
$first->get('settings?tab=profile');
$first->post('settings/change_password', ['current_password' => T_PASSWORD, 'new_password' => 'NewPass123', 'confirm_password' => 'NewPass123']);
expect('security.password_changed', [CHIDI], 'settings?tab=profile');
exec_sql("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash(T_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]), CHIDI]);
$first->post('settings/update_profile', ['full_name' => 'Chidi Staff', 'email' => 'chidi.new@adashair.test']);
expect('security.email_changed', [CHIDI], 'settings?tab=profile');
deliver();
$old = mails_to(EMAIL[CHIDI]);
ok((bool)array_filter($old, fn($m) => $m['subject'] === 'Your Nfelia sign-in email was changed'), 'the OLD address is told, so a stolen account is noticed');
ok((bool)mails_to('chidi.new@adashair.test'), 'and the new one');
exec_sql("UPDATE users SET email = ? WHERE id = ?", [EMAIL[CHIDI], CHIDI]);

/* ══ The Nfelia team and the business's own plan ════════════ */

section('Support: a ticket, and the reply');
clean_slate();
$a->get('contact-support');
$a->post('support/submit_ticket', ['subject' => 'Printing invoices', 'message' => 'The PDF is blank.']);
$ticket = (int)q("SELECT id FROM support_tickets WHERE subject = 'Printing invoices'");
eq(ops_told('ticket'), [1, 2], 'the Nfelia team is told in Ops: Admin and Support, not Sales');
eq(told('platform.ticket'), [], 'and nobody in the Nfelia business (it is an ordinary business now)');
eq((string)q("SELECT body FROM ops_alerts WHERE kind = 'ticket' AND staff_id = 1"), "Ada's Hair & Co: Printing invoices", 'who and what');
eq((string)q("SELECT url FROM ops_alerts WHERE kind = 'ticket' AND staff_id = 1"), "support/{$ticket}", 'opening the ticket itself');
$t = as_ops('sam@nfelia.test');
$t->get(o("support/{$ticket}"));
ok(str_contains($t->body, 'The PDF is blank.'), 'Support opens it in Ops');
$t->post(o("support/{$ticket}/reply"), ['reply' => 'Fixed. Try again.', 'status' => 'replied']);
expect('support.reply', [ADA], "contact-support#ticket-{$ticket}");
eq(latest(ADA, 'support.reply')['message'] ?? null, 'About “Printing invoices”: Fixed. Try again.', 'Ada reads the reply');
deliver();
ok((bool)array_filter(mails_to(EMAIL[ADA]), fn($m) => str_contains($m['text'], 'Fixed. Try again.')), 'and gets it by email');
opens(ADA, 'support.reply', "contact-support#ticket-{$ticket}", 'Fixed. Try again.');

section('The Nfelia team manages a plan');
clean_slate();
$t = as_ops('grace@nfelia.test');
$t->get(o('clients/3'));
$t->post(o('clients/3/plan'), ['action' => 'extend_trial', 'days' => 7]);
ok(str_starts_with(latest(KOLA, 'billing.trial_extended')['message'] ?? '', 'We added 7 days. Your trial now ends'), 'Kola hears his trial was extended, and to when');
$t->post(o('clients/3/plan'), ['action' => 'activate']);
ok(str_starts_with(latest(KOLA, 'billing.activated')['message'] ?? '', 'The Nfelia team activated your subscription until'), 'activated, and until when');
$t->post(o('clients/3/plan'), ['action' => 'suspend', 'reason' => 'Testing a suspension']);
eq(latest(KOLA, 'billing.suspended')['title'] ?? null, 'Your account is on hold', 'put on hold');
deliver();
eq(count(mails_to(EMAIL[KOLA])), 3, 'each by email too (billing always emails)');
opens(KOLA, 'billing.suspended', 'settings?tab=billing');

section('A message from the home page');
clean_slate();
$visitor = new Http();
$visitor->get('');
$visitor->post('public-feedback', ['enterprise' => 'Mama Put Kitchen', 'contact' => '08099998888', 'message' => 'How much is it a month?']);
eq(ops_told('lead'), [1, 3], 'the Nfelia team is told in Ops: Admin and Sales');
eq((string)q("SELECT body FROM ops_alerts WHERE kind = 'lead' AND staff_id = 3"), 'Mama Put Kitchen (08099998888): How much is it a month?', 'with the number to reply on');

section('Cancelling keeps what was paid for');
clean_slate();
$a->get('settings?tab=billing');
$a->post('billing/cancel', ['cancel_reason' => 'Testing']);
$until = date('j M Y', strtotime((string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 2")));
eq(latest(ADA, 'billing.cancelled')['message'] ?? null, "You keep full access until {$until}. Resubscribe any time.", 'Ada is told she keeps access until her paid date');
$a->get('dashboard');
eq($a->status, 200, 'and she does');
ok(str_contains($a->body, 'You keep full access until'), 'with a reminder on every page');
fixture_subscription(2, ['status' => 'active', 'cancelled_at' => null]);

section('Paystack: payments, failures and cancellations, each once');
clean_slate();
$secret = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
$hook = function (array $event) use ($secret) {
    $body = json_encode($event);
    $h = new Http();
    $h->send('POST', 'api/webhooks', $body, ['Content-Type: application/json', 'X-Paystack-Signature: ' . hash_hmac('sha512', $body, $secret)]);
    return $h;
};
$paid = ['event' => 'charge.success', 'data' => ['reference' => 'REF-T1', 'amount' => 1000000, 'currency' => 'NGN', 'status' => 'success',
         'metadata' => ['business_id' => 2], 'customer' => ['email' => EMAIL[ADA], 'customer_code' => 'CUS_ada'], 'authorization' => ['authorization_code' => 'AUTH_x']]];
$hook($paid);
$hook($paid);   // Paystack sends again
expect('billing.paid', [ADA], 'settings?tab=billing');
ok(str_starts_with(latest(ADA, 'billing.paid')['message'] ?? '', '₦10,000 received. You’re covered until'), 'Ada reads ₦10,000 (no garbled naira sign) and until when');
eq(count(bell(ADA, 'billing.paid')), 1, 'a resent webhook tells her once');
$failed = ['event' => 'invoice.payment_failed', 'data' => ['invoice_code' => 'INV_T1', 'subscription' => ['subscription_code' => 'SUB_ada'],
           'customer' => ['email' => EMAIL[ADA], 'customer_code' => 'CUS_ada']]];
$hook($failed);
$hook($failed);
eq(count(bell(ADA, 'billing.failed')), 1, 'a failed renewal: told once, however often Paystack retries');
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 2"), 'past_due', 'and the plan has 3 days to be paid');
fixture_subscription(2, ['status' => 'active', 'current_period_end' => date('Y-m-d', strtotime('+5 days'))]);
$hook(['event' => 'subscription.create', 'data' => ['subscription_code' => 'SUB_ada', 'customer' => ['email' => EMAIL[ADA]]]]);
$hook(['event' => 'subscription.not_renew', 'data' => ['subscription_code' => 'SUB_ada', 'customer' => ['email' => EMAIL[ADA]]]]);
ok(str_starts_with(latest(ADA, 'billing.cancelled')['message'] ?? '', 'You keep full access until'), 'a card plan that won\'t renew: told she keeps her paid time (the old code swallowed this)');
exec_sql("INSERT INTO referrals (referrer_business_id, referred_business_id, referral_code, status, discount_amount) VALUES (2, 3, 'ADA1', 'converted', 250000)");
$hook(['event' => 'charge.success', 'data' => ['reference' => 'REF-KOLA', 'amount' => 1000000, 'currency' => 'NGN', 'metadata' => ['business_id' => 3],
       'customer' => ['email' => EMAIL[KOLA]]]]);
eq(latest(ADA, 'referral.rewarded')['message'] ?? null, 'Kola Prints joined and paid. You get ₦2,500 off your next bill.', 'Kola paid: Ada earns her referral reward');
$bad = new Http();
$bad->send('POST', 'api/webhooks', json_encode($paid), ['Content-Type: application/json', 'X-Paystack-Signature: forged']);
eq($bad->status, 401, 'an unsigned webhook is refused');

require __DIR__ . '/flows_shop.php';

done();
