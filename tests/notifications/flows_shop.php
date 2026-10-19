<?php
/**
 * The second half of flows_test.php: the shop and its shoppers, orders,
 * bookings and reviews, sales on the spot, deeds, deliveries, mobile money
 * and shop names. Shop pages are served on the shop's own host.
 */

$shop_host = 'adas-hair.' . parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : '');
$shopper = (new Http())->on($shop_host);

/* ══ The shop ════════════════════════════════════════════════ */

section('A shopper orders on the shop');
clean_slate();
$chidi_phone = phone(CHIDI);
$shopper->post('checkout', [
    'customer_name' => 'Bola Ade', 'customer_phone' => '08061234567', 'customer_email' => 'bola@example.test',
    'items' => json_encode([['product_id' => 300, 'quantity' => 2]]), 'channel' => 'cart_pop', 'fulfillment_type' => 'delivery',
    'delivery_address' => '5 Bode Thomas, Surulere', 'payment_method' => 'none',
]);
$num = $shopper->data()['order_number'] ?? '';
ok($num !== '', 'the order goes through: ' . $num);
$oid = (int)q("SELECT id FROM orders WHERE order_number = ? AND business_id = 2", [$num]);
deliver();
expect('order.new', [ADA, BISI, CHIDI], "orders/view/{$oid}");
eq(latest(ADA, 'order.new')['title'] ?? null, 'New order: ₦130,000', 'the owner reads the amount');
eq(latest(CHIDI, 'order.new')['title'] ?? null, 'New order ' . $num, 'staff read the order number');
eq(latest(CHIDI, 'order.new')['message'] ?? null, "Bola Ade ordered 2 items on your shop for delivery · {$num}", 'who, how many, delivery or pickup');
eq(pushes_to($chidi_phone)[0]['title'] ?? null, 'New order ' . $num, 'Chidi\'s phone buzzes, without the amount');
$mail = mails_to(EMAIL[ADA]);
ok(count($mail) === 1 && str_contains($mail[0]['text'], '5 Bode Thomas'), 'Ada gets it by email, with the address to deliver to');
ok((bool)mails_to('bola@example.test'), 'and the shopper gets their own confirmation');
opens(CHIDI, 'order.new', "orders/view/{$oid}", $num);
opens(ADA, 'order.new', "orders/view/{$oid}", $num);

section('A shopper asks for a quote');
clean_slate();
$shopper->post('quote-request', ['name' => 'Tayo Events', 'phone' => '08077776666', 'email' => 'tayo@example.test', 'message' => '30 bridesmaids\' wigs for December']);
eq($shopper->data()['success'] ?? null, true, 'sent from the shop');
$rid = (int)q("SELECT id FROM quote_requests WHERE name = 'Tayo Events'");
expect('quote_request.new', [ADA, BISI, CHIDI], "quotations/requests/{$rid}");
eq(latest(ADA, 'quote_request.new')['title'] ?? null, 'Quote request from Tayo Events', 'saying who');
deliver();
ok((bool)array_filter(mails_to(EMAIL[BISI]), fn($m) => str_contains($m['text'], '30 bridesmaids')), 'by email, with the whole message');
opens(BISI, 'quote_request.new', "quotations/requests/{$rid}", '30 bridesmaids');
eq((string)q("SELECT status FROM quote_requests WHERE id = ?", [$rid]), 'reviewed', 'opened: no longer new');

section('A shopper books');
clean_slate();
$date = date('Y-m-d', strtotime('+3 days'));
$shopper->post('book/302', ['customer_name' => 'Kemi Bride', 'customer_phone' => '08055554444', 'customer_email' => 'kemi.bride@example.test', 'date' => $date]);
$bnum = $shopper->data()['booking_number'] ?? '';
ok($bnum !== '', 'the booking goes through: ' . $bnum . ' ' . ($shopper->data()['error'] ?? ''));
$bid = (int)q("SELECT id FROM bookings WHERE booking_number = ? AND business_id = 2", [$bnum]);
expect('booking.new', [ADA, BISI, CHIDI], "bookings/view/{$bid}");
ok(str_contains(latest(ADA, 'booking.new')['message'] ?? '', 'Deposit ₦20,000'), 'the owner sees the deposit');
ok(!str_contains(latest(CHIDI, 'booking.new')['message'] ?? '₦', '₦'), 'staff don\'t');
deliver();
ok((bool)mails_to('kemi.bride@example.test'), 'the shopper gets "we have your booking"');
opens(ADA, 'booking.new', "bookings/view/{$bid}", $bnum);

section('The shop answers the booking: the shopper hears (this email never sent before)');
clean_slate();
$a = as_(ADA);
$a->get("bookings/view/{$bid}");
$a->post("bookings/confirm/{$bid}", []);
$a->post("bookings/confirm/{$bid}", []);   // a double tap
deliver();
$m = mails_to('kemi.bride@example.test');
eq(count($m), 1, 'confirmed: one email, however many taps');
ok(str_contains($m[0]['subject'] ?? '', $bnum), 'about that booking');
eq((int)q("SELECT COUNT(*) FROM tasks WHERE title LIKE 'Bridal styling%'"), 1, 'and one appointment, not two');
$a->post("bookings/cancel/{$bid}", ['message' => 'The stylist is unwell, sorry.']);
deliver();
$kb = mails_to('kemi.bride@example.test');
ok(str_contains(end($kb)['text'] ?? '', 'The stylist is unwell'), 'cancelled: told, in the shop\'s words');

section('Orders move on: the shopper hears each step once');
clean_slate();
$b = as_(BISI);
$b->get('orders/view/700');
$b->post('orders/approve/700', ['deliver_mode' => 'immediate']);
$b->post('orders/approve/700', ['deliver_mode' => 'immediate']);   // a double tap
deliver();
eq((int)q("SELECT COUNT(*) FROM invoices WHERE order_id = 700"), 1, 'approved once: one invoice, not two');
expect('payment.order', [ADA], 'orders/view/700');
eq(latest(ADA, 'payment.order')['message'] ?? null, 'Shop order ORD-0700 from Ngozi Eze, paid and approved.', 'paid with proof: money in for Ada (Bisi approved it)');
$ng = mails_to('ngozi@example.test');
eq(count($ng), 1, 'the shopper: "confirmed", once');
ok(str_contains($ng[0]['subject'] ?? '', 'is confirmed'), $ng[0]['subject'] ?? '');
ok(str_contains($ng[0]['html'] ?? '', '/order/'), 'with the link to follow it');
$b->post('orders/update_status/703', ['status' => 'processing']);
$b->post('orders/update_status/703', ['status' => 'fulfilled']);
deliver();
$subjects = array_column(mails_to('ngozi@example.test'), 'subject');
ok((bool)array_filter($subjects, fn($s) => str_contains($s, 'ORD-0703 is being prepared')), 'being prepared');
ok((bool)array_filter($subjects, fn($s) => str_contains($s, 'Thank you for collecting order ORD-0703')), 'a pickup order done: "thank you for collecting"');
$ngm = mails_to('ngozi@example.test');
ok(str_contains(end($ngm)['html'] ?? '', '/review/'), 'with the link to rate it');
$b->post('orders/reject/701', ['reason' => 'Out of stock until next week.']);
deliver();
ok((bool)array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['text'], 'Out of stock until next week.')), 'not accepted: told why');
$b->get('orders/view/703');
ok(str_contains($b->body, 'orders/wa/703?m=thanks') && str_contains($b->body, 'Say thank you'),
   'the order page offers a thank-you, ready to send on WhatsApp');
$b->get('orders/wa/703?m=thanks');
ok(str_starts_with($b->to(), 'https://wa.me/') && str_contains(rawurldecode($b->to()), 'thank you for your order ORD-0703'),
   'it opens WhatsApp with the order number in the message');
ok((int)q("SELECT COUNT(*) FROM order_events WHERE order_id = 703 AND kind = 'whatsapp'") === 1, 'and the message goes on the order\'s Log');

section('A delivery goes out, and arrives');
clean_slate();
exec_sql("UPDATE orders SET delivery_id = 601, status = 'approved' WHERE id = 702");
$b->get('deliveries/view/601');
$b->post('deliveries/update_status/601', ['status' => 'dispatched']);
eq((string)q("SELECT status FROM deliveries WHERE id = 601"), 'dispatched', 'marked dispatched (this always failed before: the SQL was broken)');
$b->post('deliveries/update_status/601', ['status' => 'in_transit']);
deliver();
$ways = array_filter(array_column(mails_to('ngozi@example.test'), 'subject'), fn($s) => str_contains($s, 'is on its way'));
eq(count($ways), 1, 'the shopper hears "on its way" once (dispatched, then in transit)');
$b->post('deliveries/update_status/601', ['status' => 'delivered']);
deliver();
ok((bool)array_filter(array_column(mails_to('ngozi@example.test'), 'subject'), fn($s) => str_contains($s, 'has been delivered')), 'and "delivered"');

section('A shopper reviews their order');
clean_slate();
exec_sql("UPDATE orders SET delivery_id = 600, status = 'fulfilled' WHERE id = 702");
$review = (string)q("SELECT token FROM reviews WHERE id = 1");
$shopper->post("review/{$review}", ['rating' => '4', 'comment' => 'Lovely hair, came fast']);
expect('review.new', [ADA, BISI], 'orders/view/702#review');
eq(latest(ADA, 'review.new')['message'] ?? null, 'Ngozi E. on order ORD-0702: “Lovely hair, came fast”', 'what they said');
opens(ADA, 'review.new', 'orders/view/702#review', 'id="review"');
$shopper->post("review/{$review}", ['rating' => '1', 'comment' => 'again']);
eq(count(rows("SELECT id FROM notifications WHERE event = 'review.new'")), 2, 'a second post changes nothing and tells nobody');

/* ══ In the shop, by the team ════════════════════════════════ */

section('A sale on the spot');
clean_slate();
$b->get('receipts/create');
eq($b->to(), 'orders/quick', 'the old receipt page opens a quick sale: every sale is an order');
$b->get('orders/quick');
$b->post('orders/save', ['mode' => 'quick', 'customer_id' => '', 'pay' => 'now', 'method' => 'cash',
                         'items' => [['product_id' => '', 'name' => 'Edge control', 'quantity' => '2', 'unit_price' => '3500']]]);
$oid = (int)q("SELECT id FROM orders WHERE business_id = 2 AND channel = 'quick' ORDER BY id DESC LIMIT 1");
$rid = (int)q("SELECT id FROM receipts WHERE business_id = 2 ORDER BY id DESC LIMIT 1");
ok($oid > 0 && (int)q("SELECT order_id FROM receipts WHERE id = {$rid}") === $oid, 'Bisi sells it to a walk-in customer: an order, with its receipt');
expect('payment.invoice', [ADA], "orders/view/{$oid}#money");
$inv = (string)q("SELECT doc_number FROM invoices WHERE order_id = {$oid}");
$ord = (string)q("SELECT order_number FROM orders WHERE id = {$oid}");
eq(latest(ADA, 'payment.invoice')['title'] ?? null, "₦7,000 received · {$inv}", 'Ada hears ₦7,000 came in');
eq(latest(ADA, 'payment.invoice')['message'] ?? null, "Walk-in customer paid by cash for order {$ord}. Paid in full.", 'for which order');
opens(ADA, 'payment.invoice', "orders/view/{$oid}#money", $ord);

section('A deed of acquisition');
clean_slate();
$a->get('invoices/view/400');
$a->post('deed/create', ['source' => 'invoice', 'source_id' => 400, 'provenance_text' => 'Imported from Brazil']);
deliver();
ok((bool)array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['subject'], 'Deed of Acquisition')), 'the customer gets their deed by email');
exec_sql("UPDATE customers SET email = NULL WHERE id = 201");
exec_sql("UPDATE invoices SET status = 'paid' WHERE id = 402");
$a->post('deed/create', ['source' => 'invoice', 'source_id' => 402]);
$a->get('dashboard');   // the message shows on the next page of the app
ok(str_contains($a->body, 'has no email address'), 'a customer with no email: the owner is told to share the link instead (it used to say "notified by email")');

/* ══ Mobile money and shop names (the Nfelia team) ═══════════ */

section('Paying by mobile money: checked by the Nfelia team');
clean_slate();
exec_sql("UPDATE businesses SET country_id = 2 WHERE id = 3");
$k = as_(KOLA);
$k->get('momo/checkout');
$png = tempnam(sys_get_temp_dir(), 'rcpt') . '.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$k->post('momo/submit', ['receipt' => new CURLFile($png, 'image/png', 'receipt.png'), 'extra_seats' => '0', 'reference_number' => 'MTN-123']);
$pay = (int)q("SELECT id FROM subscription_payments WHERE business_id = 3 ORDER BY id DESC LIMIT 1");
ok($pay > 0, 'Kola sends his receipt');
eq(ops_told('payment'), [1, 2], 'the Nfelia team is told in Ops: Admin and Support');
eq((string)q("SELECT body FROM ops_alerts WHERE kind = 'payment' AND staff_id = 2"), 'Kola Prints says they sent GH₵150 GHS by Mobile Money (reference MTN-123). Check it and approve or turn it down.', 'the team sees who, how much and the reference');
deliver();
ok((bool)array_filter(mails_to('sam@nfelia.test'), fn($m) => $m['subject'] === 'Urgent: Mobile Money payment from Kola Prints'), 'and by email, marked urgent (every transfer is)');
ok(str_starts_with(latest(KOLA, 'billing.momo_submitted')['message'] ?? '', 'We got your GH₵150 GHS mobile money payment'), 'Kola gets a confirmation');
eq((string)q("SELECT url FROM ops_alerts WHERE kind = 'payment' AND staff_id = 2"), "payments#payment-{$pay}", 'opening that payment in Ops');
$t = as_ops('sam@nfelia.test');
$t->get(o('payments'));
ok(str_contains($t->body, "id=\"payment-{$pay}\""), 'Support sees it waiting');
$t->post(o("payments/{$pay}/reject"), ['reason' => 'The amount on the receipt is GH₵100.']);
ok(str_contains(latest(KOLA, 'billing.momo_rejected')['message'] ?? '', 'The amount on the receipt is GH₵100.'), 'rejected: Kola is told why');
opens(KOLA, 'billing.momo_rejected', 'momo/checkout');
$t->post(o("payments/{$pay}/reject"), ['reason' => 'again, twice']);
eq(count(bell(KOLA, 'billing.momo_rejected')), 1, 'a second review of the same payment does nothing');
$k->get('momo/checkout');
$k->post('momo/submit', ['receipt' => new CURLFile($png, 'image/png', 'receipt.png'), 'extra_seats' => '0']);
$pay2 = (int)q("SELECT id FROM subscription_payments WHERE business_id = 3 ORDER BY id DESC LIMIT 1");
exec_sql("INSERT INTO referrals (referrer_business_id, referred_business_id, referral_code, status, discount_amount) VALUES (2, 3, 'ADA2', 'converted', 250000)");
$t->post(o("payments/{$pay2}/approve"));
ok(str_starts_with(latest(KOLA, 'billing.paid')['message'] ?? '', 'GH₵150 GHS received. You’re covered until'), 'approved: Kola is told until when');
ok(latest(ADA, 'referral.rewarded') !== [], 'and Ada, who invited him, earns her reward (mobile money payers never did)');
deliver();
eq(count(mails_to(EMAIL[KOLA])), 4, 'each step by email (sent, rejected, sent again, approved)');

section('A shop name that needs a look');
clean_slate();
exec_sql("UPDATE businesses SET subdomain = NULL, subdomain_status = 'none' WHERE id = 3");
fixture_subscription(3, ['status' => 'active', 'current_period_end' => date('Y-m-d', strtotime('+20 days'))]);
$k->get('shop-setup');
$k->post('shop-setup/save', ['look' => 'glow', 'shop_name' => 'kola-promo']);
eq(ops_told('shop_name'), [1, 2], 'the Nfelia team is told in Ops');
eq((string)q("SELECT body FROM ops_alerts WHERE kind = 'shop_name' AND staff_id = 1"), 'Kola Prints asked for kola-promo.' . shop_base_host() . '.', 'the team sees the name');
$t->get(o('shop-names'));
$t->post(o('shop-names/3'), ['name' => 'kola-promo', 'decision' => 'reject']);
expect('shop.link_refused', [KOLA], 'shop-setup');
eq(latest(KOLA, 'shop.link_refused')['title'] ?? null, 'Please choose another shop name', 'Kola is told to choose another');
$k->post('shop-setup/save', ['look' => 'glow', 'shop_name' => 'kola-official']);
$t->post(o('shop-names/3'), ['name' => 'kola-official', 'decision' => 'approve']);
expect('shop.link_live', [KOLA], 'landing-page');
eq(latest(KOLA, 'shop.link_live')['message'] ?? null, 'kola-official.' . shop_base_host() . ' is approved. Share it with your customers.', 'and when it is live');
opens(KOLA, 'shop.link_live', 'landing-page');
