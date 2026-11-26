<?php
/**
 * Nfelia Ops, clients: the list and each client's whole picture, plan
 * changes, notes, "view as" (read-only, timed, the owner told), payments,
 * shop names, support, reviews, feedback, finance, countries, who may do
 * what, and the old /admin pages sending people to Ops.
 *   bash tests/notifications/run.sh ops/clients
 */
require __DIR__ . '/lib.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, CHIDI = 22, KOLA = 30, TOLU = 10, SADE = 11;
$grace = as_ops('grace@nfelia.test');
$sam   = as_ops('sam@nfelia.test');
$lola  = as_ops('lola@nfelia.test');

/* ── The list ───────────────────────────────────────────────── */

section('Clients: every business');

$grace->get(o('clients'));
eq($grace->status, 200, 'the list opens');
ok(str_contains($grace->body, 'Ada&#039;s Hair &amp; Co') && str_contains($grace->body, 'Kola Prints') && str_contains($grace->body, 'Nfelia'), 'with every business, the Nfelia one among them');
$grace->get(o('clients?q=chidi'));
ok(str_contains($grace->body, 'Ada&#039;s Hair') && !str_contains($grace->body, 'Kola Prints'), 'searching finds a business by one of its people');
$grace->get(o('clients?q=kola-prints'));
ok(str_contains($grace->body, 'Kola Prints') && !str_contains($grace->body, 'Ada&#039;s Hair'), 'or by its shop link');
$grace->get(o('clients?plan=trial'));
ok(str_contains($grace->body, 'Kola Prints') && !str_contains($grace->body, 'Ada&#039;s Hair'), 'filtered by plan');
exec_sql("UPDATE subscriptions SET trial_ends_at = CURDATE() + INTERVAL 2 DAY WHERE business_id = 3");
$grace->get(o('clients?plan=attention'));
ok(str_contains($grace->body, 'Kola Prints'), '"Needs attention" shows a trial ending within 3 days');
$lola->get(o('clients'));
eq($lola->status, 200, 'Sales can see clients too (to follow them up)');

section('One client: the whole picture');

exec_sql("INSERT INTO support_tickets (business_id, user_id, subject, message, status) VALUES (2, 20, 'PDF blank', 'Help', 'open')");
exec_sql("INSERT INTO subscription_history (business_id, subscription_id, event_type, amount, paystack_reference, status) VALUES (2, 0, 'charge.success', 1000000, 'ref-1', 'success')");
errors_capture(['bulk' => true, 'kind' => 'php', 'level' => 'error', 'message' => 'Ada hit this', 'file' => 'app/x.php', 'line' => 9, 'business_id' => 2]);
$grace->get(o('clients/2'));
eq($grace->status, 200, 'Ada\'s Hair opens');
foreach (['Ada Obi' => 'its people', 'Paying' => 'its plan', 'Card payment' => 'its payments', 'Using Nfelia' => 'how much it uses Nfelia',
          'PDF blank' => 'its support tickets', 'Ada hit this' => 'the errors it hit', 'adas-hair.' . shop_base_host() => 'its shop'] as $text => $what) {
    ok(str_contains($grace->body, e($text)), 'shows ' . $what);
}
$lola->get(o('clients/2'));
ok(!str_contains($lola->body, 'Ada hit this') && !str_contains($lola->body, 'PDF blank') && !str_contains($lola->body, 'Change the plan'), 'Sales sees the client, but not its errors, tickets or plan buttons');
$grace->get(o('clients/999'));
eq($grace->status, 404, 'an unknown client is a 404');

section('Notes on a client');

$lola->get(o('clients/3'));
$lola->post(o('clients/3/notes'), ['body' => 'Called Kola: wants a demo on Friday.']);
$grace->get(o('clients/3'));
ok(str_contains($grace->body, 'Called Kola: wants a demo on Friday.') && str_contains($grace->body, 'Lola Sales'), 'a note from Sales shows to the team, with who wrote it');

/* ── Plans ──────────────────────────────────────────────────── */

section('Changing a plan: the owner is told, and it is written down');

$grace->post(o('clients/3/plan'), ['action' => 'extend_trial', 'days' => 14]);
eq((string)q("SELECT trial_ends_at FROM subscriptions WHERE business_id = 3"), date('Y-m-d', strtotime('+16 days')), 'the trial runs 14 days longer');
ok((bool)bell(KOLA, 'billing.trial_extended'), 'Kola is told');
ok(str_contains((string)q("SELECT notes FROM subscription_status_log WHERE business_id = 3 ORDER BY id DESC LIMIT 1"), 'Grace Admin'), 'the plan\'s log says who in Ops');
ok((bool)rows("SELECT 1 FROM ops_audit WHERE action = 'client.plan' AND business_id = 3"), 'and so does the audit log');
$grace->post(o('clients/3/plan'), ['action' => 'suspend', 'reason' => '']);
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 3"), 'trial', 'suspending needs a reason');
$grace->post(o('clients/3/plan'), ['action' => 'suspend', 'reason' => 'Chargeback from the bank']);
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 3"), 'suspended', 'with one, it is suspended');
$grace->post(o('clients/3/plan'), ['action' => 'activate']);
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 3"), 'active', 'activated for a month');
$today = (string)q("SELECT CURDATE()");
eq(substr((string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 3"), 0, 10), billing_add_month($today), 'from today, as it had no paid month');
$grace->post(o('clients/3/plan'), ['action' => 'activate']);
eq(substr((string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 3"), 0, 10), billing_add_month(billing_add_month($today)), 'a second month starts when the first ends, like a payment');

section('Where a client stands comes from the dates (billing_access)');
$keep_end = (string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 3");
fixture_subscription(3, ['current_period_end' => date('Y-m-d', strtotime($today . ' -1 day'))]);
$grace->get(o('clients?plan=due'));
ok(str_contains($grace->body, 'Kola Prints') && str_contains($grace->body, 'Payment due'), 'a month that ended yesterday: "Payment due", though the status is still active');
$grace->get(o('clients/3'));
ok(str_contains($grace->body, '3 extra days to'), 'its page says until when');
fixture_subscription(3, ['current_period_end' => date('Y-m-d', strtotime($today . ' -10 days'))]);
$grace->get(o('clients?plan=paused'));
ok(str_contains($grace->body, 'Kola Prints') && str_contains($grace->body, 'Paused: not paid'), 'ten days on: "Paused: not paid"');
$grace->get(o('clients?plan=active'));
ok(!str_contains($grace->body, 'Kola Prints'), 'and not among the paying');
$grace->get(o('clients?plan=attention'));
ok(str_contains($grace->body, 'Kola Prints'), '"Needs attention" has it');
fixture_subscription(3, ['expiry_exempt' => 1]);
$grace->get(o('clients?plan=active'));
ok(str_contains($grace->body, 'Kola Prints'), 'one left as it was when months began to expire still counts as paying');
fixture_subscription(3, ['expiry_exempt' => 0, 'current_period_end' => $keep_end]);
$lola->post(o('clients/3/plan'), ['action' => 'suspend', 'reason' => 'Sales should not']);
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 3"), 'active', 'Sales cannot change a plan');
$grace->post(o('clients/2/credit'), ['hide' => '1']);
eq((int)q("SELECT hide_nfelia_credit FROM businesses WHERE id = 2"), 1, '"Shop by Nfelia" comes off Ada\'s shop');

/* ── View as ────────────────────────────────────────────────── */

section('View as: a reason is needed, and the owner is told');

clean_slate();
$sam->get(o('clients/2'));
$sam->post(o('clients/2/view-as'), ['reason' => '']);
eq(ops_to($sam), 'clients/2#view-as', 'without a reason, nothing opens');
$sam->post(o('clients/2/view-as'), ['reason' => 'Ticket: the PDF is blank']);
$link = $sam->location;
ok(str_starts_with($link, APP_URL . '/support-view/'), 'with one, Ops sends the browser to the app with a one-time link');
$view = rows("SELECT * FROM ops_view_as ORDER BY id DESC LIMIT 1")[0];
eq([(int)$view['staff_id'], (int)$view['business_id'], (int)$view['user_id']], [2, 2, ADA], 'to see what Ada (the owner) sees');
ok((bool)rows("SELECT 1 FROM ops_audit WHERE action = 'client.view_as' AND business_id = 2 AND summary LIKE '%the PDF is blank%'"), 'the reason is in the audit log');

$browser = new Http();
$browser->get(str_replace(APP_URL . '/', '', $link));
eq($browser->to(), 'dashboard', 'the link opens Ada\'s Nfelia');
$browser->get('dashboard');
ok($browser->status === 200 && str_contains($browser->body, 'Viewing Ada&#039;s Hair &amp; Co as Nfelia support (Sam Support)'), 'with a banner saying whose and who is looking');
eq((string)(latest_bell(ADA, 'security.view_as')['title'] ?? ''), 'Nfelia support looked at your account', 'Ada is told');
ok(str_contains(latest_bell(ADA, 'security.view_as')['message'] ?? '', 'Sam Support') && str_contains(latest_bell(ADA, 'security.view_as')['message'] ?? '', 'the PDF is blank'), 'who looked, and why');
deliver();
ok((bool)array_filter(mails_to('ada@adashair.test'), fn($m) => $m['subject'] === 'Nfelia support looked at your account'), 'by email too (security emails always go)');
eq(q("SELECT last_login_at FROM users WHERE id = ?", [ADA]), null, 'looking leaves no sign-in on Ada\'s account');
$again = new Http();
$again->get(str_replace(APP_URL . '/', '', $link));
eq($again->status, 410, 'the link works once');

section('View as: read-only');

$browser->get('invoices/view/400');
eq($browser->status, 200, 'pages open');
$before = (string)q("SELECT full_name FROM customers WHERE id = 200");
$browser->get('customers/edit/200');
$browser->post('customers/edit/200', ['full_name' => 'Changed By Support', 'phone' => '08031110000']);
eq((string)q("SELECT full_name FROM customers WHERE id = 200"), $before, 'a form changes nothing');
$browser->get('dashboard');
ok(str_contains($browser->body, 'Read-only: you are viewing'), 'and says why');
$browser->json('api/notifications?action=mark_read', ['id' => 1]);
eq([$browser->status, $browser->data()['read_only'] ?? null], [403, true], 'a script that would change something is refused');
$browser->get('logout');
$browser->get('dashboard');
eq($browser->status, 200, 'even "log out" by link does nothing (End is the way out)');

section('View as: ends after 30 minutes, or with End');

$browser->post('support-view/end');
ok(str_starts_with($browser->location, ops_origin() . '/clients/2'), 'End goes back to the client in Ops');
ok(q("SELECT ended_at FROM ops_view_as WHERE id = ?", [$view['id']]) !== null, 'and the view is marked ended');
$browser->get('dashboard');
ok($browser->status === 302 && str_contains($browser->location, 'login'), 'Ada\'s Nfelia is closed in that browser');
$sam->post(o('clients/2/view-as'), ['reason' => 'A second look at the PDF']);
$late = new Http();
$late->get(str_replace(APP_URL . '/', '', $sam->location));
$late->get('dashboard');
eq($late->status, 200, 'a second view opens');
exec_sql("UPDATE ops_view_as SET ends_at = NOW() - INTERVAL 1 SECOND WHERE ended_at IS NULL AND started_at IS NOT NULL");
$late->get('dashboard');
ok(str_starts_with($late->location, ops_origin() . '/clients/2'), 'after its 30 minutes, the next page ends it and goes back to Ops');
$late->get('dashboard');
ok($late->status === 302 && str_contains($late->location, 'login'), 'and it stays closed');
$lola->post(o('clients/2/view-as'), ['reason' => 'Sales wants a look']);
eq($lola->status, 403, 'Sales cannot view as a client');

/* ── Payments, shop names, support ──────────────────────────── */

section('Payments to check: the receipt, approve, turn down');

$dir = BASE_PATH . '/storage/momo_receipts';
@mkdir($dir, 0750, true);
$file = $dir . '/test_receipt_' . getmypid() . '.png';
file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
exec_sql("UPDATE businesses SET country_id = 2 WHERE id = 3");
exec_sql("INSERT INTO subscription_payments (business_id, subscription_id, country_id, amount, currency_code, payment_method, receipt_path, status, submitted_at)
          VALUES (3, 0, 2, 150, 'GHS', 'mobile_money', ?, 'pending_verification', NOW())", [$file]);
$pay = (int)q("SELECT MAX(id) FROM subscription_payments");
$sam->get(o('payments'));
ok(str_contains($sam->body, 'Kola Prints') && str_contains($sam->body, 'GH₵150 GHS'), 'Support sees who sent how much');
$sam->get(o("payments/{$pay}/receipt"));
eq([$sam->status, $sam->headers['content-type'] ?? ''], [200, 'image/png'], 'and the receipt itself');
exec_sql("UPDATE subscription_payments SET receipt_path = '/etc/passwd' WHERE id = ?", [$pay]);
$sam->get(o("payments/{$pay}/receipt"));
eq($sam->status, 404, 'a receipt path outside the receipts folder is never served');
$sam->get(o('payments'));
$sam->post(o("payments/{$pay}/approve"));
eq((string)q("SELECT status FROM subscription_payments WHERE id = ?", [$pay]), 'approved', 'approved');
eq((int)q("SELECT reviewed_by_staff FROM subscription_payments WHERE id = ?", [$pay]), 2, 'by Sam');
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 3"), 'active', 'Kola\'s plan is active');
ok((bool)bell(KOLA, 'billing.paid'), 'Kola is told');

// Main's billing (app/helpers/billing.php): a month starts when the paid one
// ends, staff the owner didn't tick are switched off, seats add to the month.
$ada_end = (string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 2");
$ada_end = substr($ada_end, 0, 10);
$add_payment = function (string $kind, int $seats, ?string $covered) use ($file) {
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    exec_sql("INSERT INTO subscription_payments (business_id, subscription_id, country_id, amount, currency_code, payment_method, kind, extra_seats, covered_user_ids, receipt_path, status, submitted_at)
              VALUES (2, 0, 1, 15000, 'NGN', 'mobile_money', ?, ?, ?, ?, 'pending_verification', NOW())", [$kind, $seats, $covered, $file]);
    return (int)q("SELECT MAX(id) FROM subscription_payments");
};
$month = $add_payment('month', 1, '21');
$sam->get(o('payments'));
ok(str_contains($sam->body, 'for a month for the owner and 1 staff'), 'the list says what it pays for');
$sam->post(o("payments/{$month}/approve"));
$sub = rows("SELECT * FROM subscriptions WHERE business_id = 2")[0];
eq([substr($sub['current_period_start'], 0, 10), substr($sub['current_period_end'], 0, 10)], [$ada_end, billing_add_month($ada_end)], 'approving a month: it starts when the paid month ends');
eq([(int)q("SELECT is_active FROM users WHERE id = 21"), (int)q("SELECT is_active FROM users WHERE id = 22")], [1, 0], 'Bisi, who was ticked, stays; Chidi is switched off');
eq((int)$sub['extra_seats'], 1, 'with the seat it paid for');
$seats = $add_payment('seats', 2, null);
$sam->post(o("payments/{$seats}/approve"));
eq((int)q("SELECT extra_seats FROM subscriptions WHERE business_id = 2"), 3, 'approving seats adds them to the month already paid');
eq(substr((string)q("SELECT current_period_end FROM subscriptions WHERE business_id = 2"), 0, 10), billing_add_month($ada_end), 'and leaves the month as it was');
exec_sql("UPDATE users SET is_active = 1 WHERE id = 22");

$lola->get(o('payments'));
eq($lola->status, 403, 'Sales cannot check payments');
@unlink($file);

section('Shop names');

exec_sql("UPDATE businesses SET subdomain_requested = 'kola-bank', subdomain_status = 'pending', subdomain_requested_at = NOW() WHERE id = 3");
$sam->get(o('shop-names'));
ok(str_contains($sam->body, 'kola-bank.' . shop_base_host()), 'waiting names are listed');
$sam->post(o('shop-names/3'), ['name' => 'kola-bank', 'decision' => 'reject']);
eq((string)q("SELECT subdomain_status FROM businesses WHERE id = 3"), 'active', 'turned down: the shop keeps its live link');
ok((bool)bell(KOLA, 'shop.link_refused'), 'Kola is told');
ok((bool)rows("SELECT 1 FROM ops_audit WHERE action = 'shop_name.rejected' AND staff_id = 2"), 'in the audit log, by Sam');

section('Support, reviews, feedback');

$ticket = (int)q("SELECT id FROM support_tickets WHERE subject = 'PDF blank'");
$sam->get(o('support'));
ok(str_contains($sam->body, 'PDF blank'), 'open tickets are listed');
$sam->get(o("support/{$ticket}"));
$sam->post(o("support/{$ticket}/reply"), ['reply' => '', 'status' => 'closed']);
eq((string)q("SELECT status FROM support_tickets WHERE id = ?", [$ticket]), 'closed', 'a ticket can be closed without a reply');
exec_sql("INSERT INTO reviews (id, business_id, order_id, customer_name, token, status, rating, comment, submitted_at) VALUES (2, 2, 703, 'Troll', ?, 'published', 1, 'Call me on 0803', NOW())", [bin2hex(random_bytes(16))]);
$sam->get(o('reviews?show=low'));
ok(str_contains($sam->body, 'Call me on 0803'), 'low reviews are listed');
$sam->post(o('reviews/2'), ['hide' => '1']);
eq((string)q("SELECT status FROM reviews WHERE id = 2"), 'hidden', 'one that breaks the rules is hidden');
exec_sql("INSERT INTO feedback (business_id, user_id, rating, message) VALUES (2, 20, 2, 'Too slow on my phone')");
$sam->get(o('feedback'));
ok(str_contains($sam->body, 'Too slow on my phone'), 'feedback from inside the app is listed');
$lola->get(o('support'));
eq($lola->status, 403, 'Sales cannot open support');

/* ── Nfelia's own money and settings (Admins) ───────────────── */

section('Finance, referrals and countries: Admins only');

$grace->get(o('finance'));
ok($grace->status === 200 && str_contains($grace->body, 'Monthly recurring'), 'Finance opens for an admin');
$grace->post(o('finance/expenses'), ['category' => 'server', 'amount' => '25000', 'expense_date' => date('Y-m-d'), 'description' => 'Hosting', 'recurring' => '1']);
eq((int)q("SELECT amount FROM admin_expenses WHERE description = 'Hosting'"), 2500000, 'an expense is added (kept in kobo)');
$grace->get(o('finance'));
ok(str_contains($grace->body, 'Break-even at 3 paying'), 'and the model counts it: ₦25,000 a month needs 3 paying');
$x = (int)q("SELECT id FROM admin_expenses WHERE description = 'Hosting'");
$grace->post(o("finance/expenses/{$x}/delete"));
eq((int)q("SELECT COUNT(*) FROM admin_expenses"), 0, 'and removed');
$grace->get(o('referrals'));
eq($grace->status, 200, 'Referrals opens');
$grace->get(o('countries'));
ok(str_contains($grace->body, 'Ghana'), 'Countries lists them');
$grace->post(o('countries/2'), ['monthly_price' => '200', 'extra_seat_price' => '80', 'trial_days' => '21', 'payment_method' => 'mobile_money', 'currency_symbol' => 'GH₵', 'momo_number' => '0240000000', 'payment_instructions' => 'Send to MTN']);
eq([(float)q("SELECT monthly_price FROM countries WHERE id = 2"), (int)q("SELECT trial_days FROM countries WHERE id = 2")], [150.0, 21], 'Ghana\'s trial is changed, not its price: that is under Prices, with notice');
$grace->post(o('countries/1/toggle'));
eq((int)q("SELECT is_active FROM countries WHERE id = 1"), 1, 'Nigeria cannot be switched off');
$kenya = ['name' => 'Kenya', 'iso_code' => 'ke', 'currency_code' => 'kes', 'currency_symbol' => 'KSh', 'monthly_price' => '1500', 'payment_method' => 'mobile_money', 'is_active' => '1'];
$grace->post(o('countries'), $kenya);
eq((int)q("SELECT COUNT(*) FROM countries WHERE iso_code = 'KE'"), 0, 'a country needs all three prices');
$grace->post(o('countries'), $kenya + ['extra_seat_price' => '700', 'book_price' => '1100']);
eq([(string)q("SELECT currency_code FROM countries WHERE iso_code = 'KE'"), (float)q("SELECT book_price FROM countries WHERE iso_code = 'KE'")], ['KES', 1100.0], 'a country is added, with its prices');
foreach (['finance', 'referrals', 'countries'] as $page) {
    $sam->get(o($page));
    eq($sam->status, 403, 'Support cannot open ' . $page);
}

/* ── Email a client ─────────────────────────────────────────── */

section('Email a client: in your own words, in Nfelia\'s design, replies to support');

$grace->get(o('clients/2'));
ok(str_contains($grace->body, '/clients/2/email'), 'a client\'s page has an Email button');
$grace->get(o('clients/2/email?start=check_in'));
eq($grace->status, 200, 'the composer opens');
ok(str_contains($grace->body, 'How is Nfelia going for {business}?') && str_contains($grace->body, 'value="book" selected'),
   'starting from "Checking in" fills the subject, the message and the "Book a session" button');
ok(str_contains($grace->body, 'srcdoc="') && str_contains($grace->body, 'Hi Ada,'), 'and shows the email as it will arrive, filled in for the owner');
ok(str_contains($grace->body, '<b>How is Nfelia going for Ada'), 'with its subject filled in above it');
ok(!str_contains($grace->body, 'value="polish"'), 'Polish is offered only with GEMINI_API_KEY on the server');
$sam->get(o('clients/2/email'));
eq($sam->status, 200, 'Support can email a client');
$lola->get(o('clients/2/email'));
eq($lola->status, 200, 'and Sales');

$grace->post(o('clients/2/email'), ['to' => ADA, 'subject' => '', 'body' => 'Hello there, just checking in.', 'button' => 'none', 'do' => 'send']);
eq(ops_to($grace), 'clients/2/email#preview', 'no subject: nothing is sent, back to the composer');
$grace->get(o('clients/2/email'));
ok(str_contains($grace->body, 'Give the email a subject.') && str_contains($grace->body, 'Hello there, just checking in.'), 'it says why, and keeps what was typed');

$letter = ['to' => ADA, 'subject' => 'Hello from Nfelia', 'body' => "Hi {first_name},\n\nJust checking in on {business}.", 'button' => 'book', 'do' => 'send'];
$grace->post(o('clients/2/email'), $letter);
eq(ops_to($grace), 'clients/2#notes', 'Send goes back to the client\'s page');
$to_ada = array_values(array_filter(mails_to('ada@adashair.test'), fn($m) => $m['subject'] === 'Hello from Nfelia'));
eq(count($to_ada), 1, 'Ada gets it');
$m = $to_ada[0] ?? ['text' => '', 'reply_to' => '', 'from' => '', 'rcpt' => '', 'to' => ''];
ok(str_contains($m['text'], 'Hi Ada,') && str_contains($m['text'], "Just checking in on Ada's Hair & Co.")
   && str_contains($m['text'], 'Book a session with us') && str_contains($m['text'], 'Grace Admin'),
   'with her name, her business, the session button and who wrote it');
ok(str_contains($m['reply_to'], 'support@nfelia.com'), 'a reply goes to support@nfelia.com');
ok(str_contains($m['from'], 'Grace from Nfelia'), 'it is from "Grace from Nfelia"');
ok(str_contains($m['rcpt'], 'praxisjbs@gmail.com') && !str_contains($m['to'], 'praxisjbs'), 'the team gets a copy Ada does not see');
ok((bool)rows("SELECT 1 FROM ops_notes WHERE subject_type = 'business' AND subject_id = 2 AND body LIKE 'Emailed Ada%Hello from Nfelia%'"), 'it is kept in the client\'s notes');
eq(count(audit_rows('client.email')), 1, 'and in the audit log');
$grace->post(o('clients/2/email'), $letter);
eq(count(array_filter(mails_to('ada@adashair.test'), fn($m) => $m['subject'] === 'Hello from Nfelia')), 1, 'pressing Send again by mistake sends nothing more');

exec_sql("INSERT INTO users (business_id, full_name, email, password_hash, role, is_active) VALUES (2, 'Gone Away', 'gone@adashair.invalid', 'x', 'staff', 1)");
$gone = (int)q("SELECT id FROM users WHERE email = 'gone@adashair.invalid'");
$grace->post(o('clients/2/email'), ['to' => $gone, 'subject' => 'Are you there?', 'body' => 'Checking that this address works.', 'button' => 'none', 'do' => 'send']);
eq(ops_to($grace), 'clients/2/email#preview', 'an address that bounces stays on the composer');
$grace->get(o('clients/2/email'));
ok(str_contains($grace->body, 'does not take email'), 'and says the address doesn\'t exist');
ok(!rows("SELECT 1 FROM ops_notes WHERE subject_type = 'business' AND subject_id = 2 AND body LIKE 'Emailed Gone%'"), 'nothing is noted as emailed');
exec_sql("DELETE FROM users WHERE id = ?", [$gone]);

/* ── The old founder admin page ─────────────────────────────── */

section('The old /admin sends the Nfelia team to Ops; the Nfelia business is ordinary');

$tolu = signin('tolu@nfelia.test');
foreach (['admin' => '', 'admin/businesses' => 'clients', 'admin/support' => 'support', 'admin/financials' => 'finance', 'admin/shop-names' => 'shop-names',
          'momo/review' => 'payments', 'countries' => 'countries'] as $old => $new) {
    $tolu->get($old);
    eq([$tolu->status, $tolu->location], [301, ops_url($new)], '/' . $old . ' → ops/' . $new);
}
$ada = signin('ada@adashair.test');
$ada->get('admin');
eq($ada->to(), 'dashboard', 'anyone else\'s /admin goes to their dashboard');
$tolu->get('dashboard');
ok(!str_contains($tolu->body, 'Founder panel') && !str_contains($tolu->body, 'MoMo review'), 'the Nfelia business\'s menu has no founder panel');

done();

function latest_bell(int $user, string $event): array
{
    return rows("SELECT * FROM notifications WHERE user_id = ? AND event = ? ORDER BY id DESC LIMIT 1", [$user, $event])[0] ?? [];
}
