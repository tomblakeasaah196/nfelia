<?php
/**
 * The orders hub's core (app/helpers/orders.php, docs/ORDERS_HUB.md),
 * against the database: numbers, every way an order is paid, changing it,
 * kept money, refunds, cancelling, moving it on, and what the shopper and
 * the team hear. Shop checkout goes over HTTP to the running app.
 *   bash tests/notifications/run.sh orders/core
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, BISI = 21, BIZ = 2;
$db    = get_db();
$today = planner_today();
$day   = fn(int $n) => planner_add_days($today, $n);

/** What a person is told when it can't be done ('' when it went through). */
function refused(callable $work): string
{
    try {
        $work();
        return '';
    } catch (DomainException $e) {
        return $e->getMessage();
    }
}

function stock_of(int $product_id): float
{
    return (float)q("SELECT stock_qty FROM products WHERE id = ?", [$product_id]);
}

/** The order's invoice, as it is now. */
function invoice_of(int $order_id): array
{
    return rows("SELECT * FROM invoices WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$order_id])[0] ?? [];
}

function debts_of(int $order_id): array
{
    return rows("SELECT * FROM debts WHERE order_id = ? ORDER BY id", [$order_id]);
}

function receipts_of(int $order_id): array
{
    return rows("SELECT * FROM receipts WHERE order_id = ? ORDER BY id", [$order_id]);
}

/** A new order typed in by Ada, with what most tests don't care about filled in. */
function make_order(array $in): array
{
    return order_create(get_db(), BIZ, $in + ['channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'cash', 'paid_now' => 0], ADA);
}

function money_today(): float
{
    return money_in(get_db(), BIZ, planner_today(), planner_today());
}

function customer(int $id): array
{
    return rows("SELECT * FROM customers WHERE id = ?", [$id])[0] ?? [];
}

/* ══ Numbers ══════════════════════════════════════════════════ */

section('Shop orders carry on from the old numbers');
// A shop whose last order was ORD-0007, from the old counter on the business
exec_sql("DELETE FROM reviews");
exec_sql("DELETE FROM order_items");
exec_sql("DELETE FROM orders");
exec_sql("UPDATE businesses SET order_number_seq = 7, booking_number_seq = 4 WHERE id = ?", [BIZ]);
exec_sql("INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, customer_name, customer_phone, currency_code, subtotal, total, status)
          VALUES (707, 2, 200, 'ORD-0007', ?, 'cart_pop', 'Ngozi Eze', '08031110000', 'NGN', 65000, 65000, 'fulfilled')", [bin2hex(random_bytes(16))]);
eq((int)q("SELECT COUNT(*) FROM document_counters WHERE business_id = ? AND doc_type = 'ORD'", [BIZ]), 0, 'no order counter yet (as on a shop the migration never saw)');
$shop_host = 'adas-hair.' . parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : '');
$shopper = (new Http())->on($shop_host);
$shopper->post('checkout', [
    'customer_name' => 'Bola Ade', 'customer_phone' => '08061234567', 'customer_email' => 'bola@example.test',
    'items' => json_encode([['product_id' => 300, 'quantity' => 1]]), 'channel' => 'cart_pop', 'fulfillment_type' => 'pickup', 'payment_method' => 'none',
]);
eq($shopper->data()['order_number'] ?? null, 'ADA-ORD-008', 'the next shop order is ADA-ORD-008, after ORD-0007');
eq((int)q("SELECT order_number_seq FROM businesses WHERE id = ?", [BIZ]), 7, 'the old counter is no longer moved');
$checkout = (int)q("SELECT id FROM orders WHERE order_number = 'ADA-ORD-008'");
eq((string)q("SELECT status FROM orders WHERE id = ?", [$checkout]), 'pending_verification', 'waiting for the shop to confirm, as before');

// The migration seeds the counter from the old one, so it agrees
exec_sql("DELETE FROM document_counters WHERE business_id = ? AND doc_type IN ('ORD', 'BKG')", [BIZ]);
$seed = array_filter(preg_split('/;\s*\n/', (string)file_get_contents(BASE_PATH . '/db/migrations/20261023090000_orders_hub.sql')),
                     fn($sql) => str_contains($sql, 'INSERT INTO `document_counters`'));
eq(count($seed), 2, 'the migration seeds two counters (orders and bookings)');
foreach ($seed as $sql) {
    $db->exec(preg_replace('/^(\s*--[^\n]*\n)+/', '', $sql));
}
eq((int)q("SELECT last_number FROM document_counters WHERE business_id = ? AND doc_type = 'ORD'", [BIZ]), 8, 'seeded orders: the highest of the old counter and the numbers used');
eq((int)q("SELECT last_number FROM document_counters WHERE business_id = ? AND doc_type = 'BKG'", [BIZ]), 4, 'seeded bookings: from the old booking counter');
foreach ($seed as $sql) {
    $db->exec(preg_replace('/^(\s*--[^\n]*\n)+/', '', $sql));
}
eq((int)q("SELECT last_number FROM document_counters WHERE business_id = ? AND doc_type = 'ORD'", [BIZ]), 8, 'seeding again changes nothing');
eq(generate_doc_number($db, BIZ, 'BKG'), 'ADA-BKG-005', 'bookings carry on too: ADA-BKG-005 after BKG-0004');
eq(generate_doc_number($db, BIZ, 'RFD'), 'ADA-RFD-001', 'refunds start at ADA-RFD-001');
exec_sql("DELETE FROM document_counters WHERE business_id = ? AND doc_type = 'RFD'", [BIZ]);

section('A prefix: what is allowed');
$problems = [];
foreach (['RNA' => null, 'rna' => null, 'AB' => null, 'AB12C' => null, '7UP' => null] as $p => $_) {
    if (doc_prefix_problem($p) !== null) $problems[] = $p . ': ' . doc_prefix_problem($p);
}
eq($problems, [], '2 to 5 letters or digits are fine, small letters too');
foreach (['', ' ', 'A', 'ABCDEF', 'R-N', 'R N', 'ÀBC', 'ORD', 'inv', 'REC', 'DEL', 'QUO', 'EXP', 'DEA', 'BKG', 'RFD'] as $p) {
    if (doc_prefix_problem($p) === null) $problems[] = "'{$p}' was let through";
}
eq($problems, [], 'too short, too long, symbols, accents and the words inside every number are refused');
eq(doc_prefix_problem('ORD'), 'ORD is already in every order number (like ABC-ORD-012), so it would read twice. Choose other letters.', 'in plain words');
eq(doc_prefix_problem('A'), 'Use 2 to 5 letters or digits.', 'too short, in plain words');

section('A prefix made from a business name is always one that is allowed');
$names = ['RN Afriluxe', "Ada's Hair & Co", 'Ọja Ọba', 'Éclat', 'Jo', 'A', 'X Y', '', '!!!', '🙂 Smiles', 'Orders', 'Rec', 'Del Monte',
          'Quo Vadis Ltd', 'Expo', 'Dea', 'Bkg', 'RFD', 'Inv', '123', '1 2 3 4', 'Ìyá Àgbà Foods', str_repeat('Long', 40), 'à', 'ß Shop'];
$bad = [];
foreach ($names as $name) {
    $p = derive_prefix($name);
    if (doc_prefix_problem($p) !== null) $bad[] = "{$name} → {$p}";
}
eq($bad, [], count($names) . ' names, every prefix allowed');
eq([derive_prefix('RN Afriluxe'), derive_prefix('Orders'), derive_prefix('Jo'), derive_prefix(''), derive_prefix('Ọja Ọba')],
   ['RNA', 'ORX', 'JOX', 'NFE', 'OJO'], 'as before for ordinary names; short ones padded with X; ORD changed');

/* ══ Making orders ════════════════════════════════════════════ */

section('An order typed in and paid now');
clean_slate();
$s300 = stock_of(300);
$money_before = money_today();
$o = make_order(['customer_id' => 200, 'items' => [['product_id' => 300, 'name' => '', 'quantity' => 2, 'unit_price' => 65000]],
                 'deliver_by' => $day(2), 'paid_now' => 130000, 'method' => 'cash', 'note' => 'Wants it gift wrapped']);
eq($o['order_number'], 'ADA-ORD-009', 'numbered by the same counter as shop orders');
eq([$o['status'], $o['channel'], (int)$o['created_by'], $o['notes']], ['approved', 'manual', ADA, 'Wants it gift wrapped'], 'confirmed straight away, made by Ada, with her note');
$inv = invoice_of((int)$o['id']);
eq([$inv['status'] ?? null, (float)($inv['amount_paid'] ?? 0), (float)($inv['balance_due'] ?? -1), array_key_exists('notes', $inv) ? $inv['notes'] : 'x'],
   ['paid', 130000.0, 0.0, null], 'its invoice is paid, with no notes');
eq((int)$o['invoice_id'], (int)$inv['id'], 'the order points at its invoice');
eq((string)q("SELECT description FROM invoice_items WHERE invoice_id = ?", [$inv['id']]), 'Body Wave 18in', 'the line takes the product\'s name');
$rec = receipts_of((int)$o['id']);
eq(count($rec), 1, 'one receipt');
eq([(int)$rec[0]['invoice_id'], (float)$rec[0]['amount'], (float)$rec[0]['balance_remaining'], $rec[0]['method']], [(int)$inv['id'], 130000.0, 0.0, 'cash'],
   'the receipt is for the invoice, the full amount, nothing left');
ok((int)$rec[0]['payment_id'] === (int)q("SELECT id FROM payments WHERE invoice_id = ?", [$inv['id']]), 'and for that payment');
eq(debts_of((int)$o['id']), [], 'nothing owed: no debt');
eq(stock_of(300), $s300 - 2, 'two taken off stock');
eq((int)q("SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'invoice' AND reference_id = ?", [$inv['id']]), 1, 'once, under the invoice');
eq((int)q("SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'order'"), 0, 'never under the order');
eq(money_today(), $money_before + 130000, 'money in today goes up by what was paid');
eq(told('payment.invoice'), [BISI], 'Bisi hears about the money (Ada recorded it)');
eq(json_decode((string)q("SELECT data FROM notifications WHERE event = 'payment.invoice'"), true)['order_number'] ?? null, 'ADA-ORD-009', 'with the order number');
eq(array_column(order_events($db, BIZ, (int)$o['id']), 'kind'), ['created', 'confirmed', 'payment'], 'the Log: made, confirmed, paid');
eq(order_events($db, BIZ, (int)$o['id'])[0]['user_name'] ?? null, 'Ada Obi', 'each line says who');

section('Confirming twice does nothing twice');
$again = refused(fn() => order_confirm($db, BIZ, (int)$o['id'], ADA));
eq($again, 'This order has already been dealt with.', 'a second confirm is refused');
eq((int)q("SELECT COUNT(*) FROM invoices WHERE order_id = ?", [$o['id']]), 1, 'still one invoice');
eq(stock_of(300), $s300 - 2, 'stock taken off once');
// A shop order with proof of payment, confirmed by Bisi twice
clean_slate();
exec_sql("INSERT INTO orders (business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone, customer_email,
            delivery_address, payment_method, payment_proof_path, currency_code, subtotal, total, status)
          VALUES (2, 200, 'ORD-0720', ?, 'cart_pop', 'delivery', 'Ngozi Eze', '08031110000', 'ngozi@example.test', '12 Allen Avenue, Ikeja',
            'bank_transfer', 'uploads/order_proofs/2/proof.png', 'NGN', 65000, 65000, 'pending_verification')", [bin2hex(random_bytes(16))]);
$shop = (int)q("SELECT id FROM orders WHERE order_number = 'ORD-0720'");
exec_sql("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, 300, 'Body Wave 18in', 65000, 1, 65000)", [$shop]);
$s300 = stock_of(300);
$made = order_confirm($db, BIZ, $shop, BISI, ['deliver_mode' => 'date', 'deliver_by' => $day(3)]);
eq(refused(fn() => order_confirm($db, BIZ, $shop, BISI, ['deliver_mode' => 'date', 'deliver_by' => $day(3)])), 'This order has already been dealt with.', 'the double tap is refused');
eq([(int)q("SELECT COUNT(*) FROM invoices WHERE order_id = ?", [$shop]), count(receipts_of($shop)), stock_of(300)], [1, 1, $s300 - 1], 'one invoice, one receipt, stock taken off once');
eq((string)invoice_of($shop)['status'], 'paid', 'paid with the proof the shopper sent');
eq(told('payment.order'), [ADA], 'Ada hears "paid and approved" (Bisi did it)');
eq(told('payment.invoice'), [], 'and not a second time as an invoice payment');
$del = rows("SELECT * FROM deliveries WHERE order_id = ?", [$shop])[0] ?? [];
eq([(int)($del['id'] ?? 0), (int)($del['invoice_id'] ?? 0), substr((string)($del['estimated_delivery'] ?? ''), 0, 10), $del['status'] ?? null],
   [(int)$made['delivery_id'], (int)invoice_of($shop)['id'], $day(3), 'pending_dispatch'], 'a delivery note for the delivery, due on the day promised');
eq((int)q("SELECT COUNT(*) FROM delivery_items WHERE delivery_id = ?", [$made['delivery_id']]), 1, 'with the order\'s line');
deliver();
eq(count(mails_to('ngozi@example.test')), 1, 'the shopper hears "confirmed" once');

section('Part payment: the balance is in Debts straight away');
clean_slate();
$o = make_order(['customer_id' => 200, 'items' => [['name' => 'Wig revamp', 'quantity' => 1, 'unit_price' => 100000]], 'fulfillment' => 'delivery',
                 'deliver_by' => $day(5), 'delivery_address' => '12 Allen Avenue', 'paid_now' => 30000, 'method' => 'bank_transfer', 'reference' => 'TRF-1']);
$inv = invoice_of((int)$o['id']);
eq([$inv['status'], (float)$inv['balance_due'], $inv['due_date']], ['partially_paid', 70000.0, $day(5)], 'part paid, ₦70,000 left, due on the delivery day');
$debts = debts_of((int)$o['id']);
eq(count($debts), 1, 'one debt, before the goods leave');
eq([$debts[0]['type'], $debts[0]['source'], (int)$debts[0]['invoice_id'], (int)$debts[0]['customer_id'], (float)$debts[0]['total_amount'],
    (float)$debts[0]['amount_settled'], (float)$debts[0]['balance'], $debts[0]['due_date'], $debts[0]['status']],
   ['receivable', 'invoice', (int)$inv['id'], 200, 100000.0, 30000.0, 70000.0, $day(5), 'settling'], 'owed to Ada: the invoice\'s total, what was paid, the balance, the pay-by date');
eq($debts[0]['description'], 'Balance on order ' . $o['order_number'], 'described by the order number');
eq((string)q("SELECT reference FROM payments WHERE invoice_id = ?", [$inv['id']]), 'TRF-1', 'the transfer reference is kept');
eq(order_pay_tag($inv)['words'], 'Deposit paid', 'tagged Deposit paid');
$second = order_record_payment($db, BIZ, (int)$o['id'], 40000, $today, 'cash', null, null, BISI, '₦');
eq($second['new_balance'], 30000.0, 'a second payment: ₦30,000 left');
$debts = debts_of((int)$o['id']);
eq([count($debts), (float)$debts[0]['amount_settled'], (float)$debts[0]['balance']], [1, 70000.0, 30000.0], 'the same debt follows');
$rec = receipts_of((int)$o['id']);
eq(count($rec), 2, 'a second receipt');
eq([(int)$rec[1]['id'], (float)$rec[1]['amount'], (float)$rec[1]['balance_remaining'], (int)$rec[1]['order_id']], [$second['receipt_id'], 40000.0, 30000.0, (int)$o['id']],
   'for that payment, saying what is left, with the order');
eq((int)q("SELECT receipt_id FROM orders WHERE id = ?", [$o['id']]), (int)$rec[0]['id'], 'the order still points at its first receipt');
eq(told('payment.invoice'), [ADA, BISI], 'both payments heard, each by whoever didn\'t record it');
$all = order_record_payment($db, BIZ, (int)$o['id'], 30000, $today, 'pos', null, null, BISI, '₦');
eq([$all['new_balance'], invoice_of((int)$o['id'])['status'], debts_of((int)$o['id'])[0]['status']], [0.0, 'paid', 'cleared'], 'paid off: the debt is cleared');
eq(refused(fn() => order_record_payment($db, BIZ, (int)$o['id'], 1, $today, 'cash', null, null, BISI, '₦')), 'Payments cannot be recorded on this invoice.', 'nothing more can be paid');

section('When a balance is due');
$now = make_order(['customer_id' => 200, 'items' => [['name' => 'Closure unit', 'quantity' => 1, 'unit_price' => 50000]], 'fulfillment' => 'now', 'paid_now' => 20000]);
eq(invoice_of((int)$now['id'])['due_date'], $day(7), 'taken away today: a week to pay the rest');
$later = make_order(['customer_id' => 200, 'items' => [['name' => 'Frontal', 'quantity' => 1, 'unit_price' => 50000]], 'fulfillment' => 'pickup', 'paid_now' => 10000]);
eq(invoice_of((int)$later['id'])['due_date'], $day(7), 'pickup with no day set: a week');
$set = make_order(['customer_id' => 200, 'items' => [['name' => 'Bob wig', 'quantity' => 1, 'unit_price' => 50000]], 'paid_now' => 10000, 'pay_by' => $day(3)]);
eq([invoice_of((int)$set['id'])['due_date'], debts_of((int)$set['id'])[0]['due_date']], [$day(3), $day(3)], 'or the day the owner chose, on the invoice and the debt');
exec_sql("UPDATE invoices SET status = 'overdue', due_date = ? WHERE order_id = ?", [$day(-1), $set['id']]);
order_set_pay_by($db, BIZ, (int)$set['id'], $day(10), ADA);
eq([invoice_of((int)$set['id'])['due_date'], invoice_of((int)$set['id'])['status'], debts_of((int)$set['id'])[0]['due_date']], [$day(10), 'partially_paid', $day(10)],
   'moved to a later day: no longer overdue, and the debt moves too');
eq(refused(fn() => order_set_pay_by($db, BIZ, (int)$set['id'], 'next week', ADA)), 'Pick the day the balance is due.', 'a date it can read');

section('Pay later');
clean_slate();
$o = make_order(['customer_id' => 201, 'items' => [['name' => 'Wholesale bundles', 'quantity' => 10, 'unit_price' => 12500]], 'deliver_by' => $day(4)]);
$inv = invoice_of((int)$o['id']);
eq([$inv['status'], (float)$inv['amount_paid'], (float)$inv['balance_due']], ['sent', 0.0, 125000.0], 'an invoice with everything owed');
eq([count(receipts_of((int)$o['id'])), (float)debts_of((int)$o['id'])[0]['balance'], debts_of((int)$o['id'])[0]['status']], [0, 125000.0, 'active'], 'no receipt; all of it in Debts');
eq(order_pay_tag($inv)['words'], 'Not paid', 'tagged Not paid');
eq(told('payment.invoice'), [], 'nobody hears of money that didn\'t come');
$count = (int)q("SELECT COUNT(*) FROM orders");
eq(refused(fn() => make_order(['customer_name' => 'Tunde', 'items' => [['name' => 'Wig', 'quantity' => 1, 'unit_price' => 30000]]])),
   'Pay later or part payment needs the customer\'s name and phone, so the balance can be followed up. Add them, or take the full amount now.',
   'pay later needs a phone to chase');
eq((int)q("SELECT COUNT(*) FROM orders"), $count, 'and nothing was made');

section('A quick sale to a walk-in');
clean_slate();
$s301 = stock_of(301);
$q1 = make_order(['channel' => 'quick', 'fulfillment' => 'now', 'items' => [['product_id' => 301, 'quantity' => 1, 'unit_price' => 55000]], 'paid_now' => 55000]);
$walk = customer((int)$q1['customer_id']);
eq([$walk['full_name'], (int)$walk['is_walk_in'], $q1['customer_phone']], ['Walk-in customer', 1, null], 'sold to the hidden walk-in customer, with no phone');
eq([$q1['status'], $q1['channel'], $q1['done_at'] !== null], ['fulfilled', 'quick', true], 'done at once: collected');
$inv = invoice_of((int)$q1['id']);
eq([$inv['status'], $inv['delivered_at'] !== null, count(receipts_of((int)$q1['id']))], ['paid', true, 1], 'paid, handed over, one receipt');
eq(stock_of(301), $s301 - 1, 'stock taken off');
eq(order_stage($q1)['words'], 'Collected', 'the owner reads Collected');
eq((int)q("SELECT COUNT(*) FROM reviews WHERE order_id = ?", [$q1['id']]), 0, 'nobody to ask for a review');
$q2 = make_order(['channel' => 'quick', 'fulfillment' => 'now', 'items' => [['name' => 'Edge control', 'quantity' => 2, 'unit_price' => 3500]], 'paid_now' => 7000]);
eq([(int)$q2['customer_id'], (int)q("SELECT COUNT(*) FROM customers WHERE business_id = ? AND is_walk_in = 1", [BIZ])], [(int)$q1['customer_id'], 1],
   'every walk-in sale uses the same one');
$count = (int)q("SELECT COUNT(*) FROM orders");
$s300 = stock_of(300);
eq(refused(fn() => make_order(['channel' => 'quick', 'fulfillment' => 'now', 'items' => [['product_id' => 300, 'quantity' => 1, 'unit_price' => 65000]], 'paid_now' => 20000])),
   'Pay later or part payment needs the customer\'s name and phone, so the balance can be followed up. Add them, or take the full amount now.', 'a walk-in can\'t leave a balance');
eq([(int)q("SELECT COUNT(*) FROM orders"), stock_of(300)], [$count, $s300], 'and nothing was made or taken off stock');
eq(refused(fn() => make_order(['items' => []])), 'Add at least one item.', 'an order needs an item');
eq(refused(fn() => make_order(['items' => [['name' => 'Wig', 'quantity' => 0, 'unit_price' => 1]]])), 'Each item needs a quantity above 0.', 'a quantity above 0');
eq(refused(fn() => make_order(['items' => [['name' => 'Wig', 'quantity' => 1, 'unit_price' => -1]]])), 'A price can\'t be below 0.', 'no price below 0');
eq(refused(fn() => make_order(['items' => [['name' => 'Wig', 'quantity' => 1, 'unit_price' => 100]], 'discount' => 101, 'paid_now' => 0])),
   'The discount can be at most what the items come to, ₦100.', 'a discount no more than the items');
$deal = make_order(['customer_id' => 200, 'items' => [['name' => 'Fabric', 'quantity' => 2.5, 'unit_price' => 4000]], 'discount' => 1000, 'paid_now' => 9000, 'fulfillment' => 'now']);
eq([(float)$deal['subtotal'], (float)$deal['discount_amount'], (float)$deal['total'], fmt_qty((float)order_lines($db, (int)$deal['id'])[0]['quantity'])],
   [10000.0, 1000.0, 9000.0, '2.5'], '2.5 metres, a discount, the total after it');
eq([(float)invoice_of((int)$deal['id'])['discount_amount'], (float)invoice_of((int)$deal['id'])['total']], [1000.0, 9000.0], 'the invoice says the same');
eq([fmt_qty(1), fmt_qty(2.5), fmt_qty(0.75), fmt_qty(10), fmt_qty(1000)], ['1', '2.5', '0.75', '10', '1000'], 'quantities as people write them');

/* ══ Changing an order ════════════════════════════════════════ */

section('Changing the items: up and down');
clean_slate();
$o = make_order(['customer_id' => 200, 'items' => [['product_id' => 300, 'quantity' => 1, 'unit_price' => 65000]], 'paid_now' => 65000, 'deliver_by' => $day(2)]);
$inv = invoice_of((int)$o['id']);
$s300 = stock_of(300);
$up = order_update_lines($db, BIZ, (int)$o['id'], [['product_id' => 300, 'quantity' => 2, 'unit_price' => 65000]], 0, ADA, '₦');
$now = invoice_of((int)$o['id']);
eq([(int)$now['id'], $now['doc_number']], [(int)$inv['id'], $inv['doc_number']], 'the invoice keeps its number');
eq([$up['total'], $up['overpaid'], (float)$now['total'], (float)$now['amount_paid'], (float)$now['balance_due'], $now['status']],
   [130000.0, 0.0, 130000.0, 65000.0, 65000.0, 'partially_paid'], 'one more: ₦65,000 still to pay, the deposit stays');
eq(stock_of(300), $s300 - 1, 'one more off stock');
eq((float)q("SELECT quantity FROM invoice_items WHERE invoice_id = ?", [$inv['id']]), 2.0, 'the invoice line says 2');
eq([(float)debts_of((int)$o['id'])[0]['balance'], debts_of((int)$o['id'])[0]['due_date']], [65000.0, $day(2)], 'the new balance is in Debts, due on the pickup day');
$down = order_update_lines($db, BIZ, (int)$o['id'], [['product_id' => 300, 'quantity' => 1, 'unit_price' => 65000]], 15000, ADA, '₦');
$now = invoice_of((int)$o['id']);
eq([$down['total'], $down['overpaid'], (float)$now['balance_due'], $now['status']], [50000.0, 15000.0, 0.0, 'paid'], 'back down with a discount: ₦15,000 paid over');
eq(stock_of(300), $s300, 'and the item back on the shelf');
eq(debts_of((int)$o['id'])[0]['status'], 'cleared', 'nothing owed: the debt is cleared');
eq(order_pay_tag($now)['words'], 'Paid more', 'tagged Paid more');
$edit = array_values(array_filter(order_events($db, BIZ, (int)$o['id']), fn($e) => $e['kind'] === 'edited'));
eq(count($edit), 2, 'each change is in the Log');
eq([$edit[0]['data']['old_total'] ?? null, $edit[0]['data']['old_lines'][0]['quantity'] ?? null], [65000, 1], 'keeping the old version');
eq((int)q("SELECT edited_at IS NOT NULL FROM orders WHERE id = ?", [$o['id']]), 1, 'the order says it was changed');

section('Keep the extra for the next order, and use it');
clean_slate();
exec_sql("INSERT INTO customers (id, business_id, full_name, phone, email) VALUES (260, 2, 'Amaka Keep', '08035550260', NULL)");
$m0 = money_today();
$a = make_order(['customer_id' => 260, 'items' => [['name' => 'Bridal set', 'quantity' => 1, 'unit_price' => 100000]], 'paid_now' => 100000]);
order_update_lines($db, BIZ, (int)$a['id'], [['name' => 'Bridal set', 'quantity' => 1, 'unit_price' => 85000]], 0, ADA, '₦');
eq(order_keep_overpaid($db, BIZ, (int)$a['id'], ADA, '₦'), 15000.0, '₦15,000 kept');
eq(order_keep_overpaid($db, BIZ, (int)$a['id'], ADA, '₦'), 0.0, 'keeping again keeps nothing more');
eq([(float)invoice_of((int)$a['id'])['amount_paid'], invoice_of((int)$a['id'])['status']], [85000.0, 'paid'], 'the order is paid exactly');
eq(kept_balance($db, BIZ, 260), 15000.0, 'Amaka has ₦15,000 kept');
eq(money_today(), $m0 + 100000, 'money in: the ₦100,000 she paid, kept or not');
$c = customer(260);
eq([(float)$c['total_invoiced'], (float)$c['total_received'], (float)$c['outstanding_balance']], [85000.0, 100000.0, 0.0], 'her figures: ₦85,000 bought, ₦100,000 paid');
$b = make_order(['customer_id' => 260, 'items' => [['name' => 'Wig cap', 'quantity' => 1, 'unit_price' => 40000]], 'paid_now' => 25000, 'use_kept' => true]);
$inv = invoice_of((int)$b['id']);
eq([(float)$inv['amount_paid'], $inv['status']], [40000.0, 'paid'], 'the next order: ₦25,000 paid and ₦15,000 kept money');
$rec = receipts_of((int)$b['id']);
eq(array_column($rec, 'method'), ['cash', 'kept'], 'a receipt for each, the kept one saying so');
eq(kept_balance($db, BIZ, 260), 0.0, 'nothing kept any more');
eq(money_today(), $m0 + 125000, 'money in only counts the ₦25,000 that came in now');
$c = customer(260);
eq([(float)$c['total_invoiced'], (float)$c['total_received'], (float)$c['outstanding_balance']], [125000.0, 125000.0, 0.0], 'her figures add up');
eq(debts_of((int)$b['id']), [], 'nothing owed');
eq(order_use_kept($db, BIZ, (int)$b['id'], ADA, '₦'), 0.0, 'using kept money again does nothing');

section('Give it back instead, then the refund');
clean_slate();
exec_sql("INSERT INTO customers (id, business_id, full_name, phone, email) VALUES (261, 2, 'Bola Return', '08035550261', 'bola.return@example.test')");
$c1 = make_order(['customer_id' => 261, 'items' => [['product_id' => 300, 'quantity' => 1, 'unit_price' => 60000]], 'paid_now' => 60000, 'method' => 'bank_transfer']);
order_update_lines($db, BIZ, (int)$c1['id'], [['product_id' => 300, 'quantity' => 1, 'unit_price' => 50000]], 0, ADA, '₦');
order_keep_overpaid($db, BIZ, (int)$c1['id'], ADA, '₦');
eq(order_unkeep($db, BIZ, (int)$c1['id'], ADA, '₦'), 10000.0, '"Give it back instead": ₦10,000 no longer kept');
eq([kept_balance($db, BIZ, 261), order_overpaid(invoice_of((int)$c1['id']))], [0.0, 10000.0], 'the order is paid over again');
eq(order_unkeep($db, BIZ, (int)$c1['id'], ADA, '₦'), 0.0, 'a second tap does nothing');
$m0 = money_today();
eq(refused(fn() => refund_record($db, BIZ, (int)$c1['id'], ['amount' => 10000, 'method' => 'bank_transfer'], null, ADA, '₦')),
   'Add the transfer reference or a screenshot, so it shows on the refund slip.', 'a transfer needs its reference or a screenshot');
$r = refund_record($db, BIZ, (int)$c1['id'], ['amount' => 10000, 'method' => 'bank_transfer', 'reference' => 'TRF-889', 'reason' => 'Changed to one bundle'], null, ADA, '₦');
eq([$r['status'], $r['doc_number'], (float)$r['amount'], $r['reference']], ['paid', 'ADA-RFD-001', 10000.0, 'TRF-889'], 'refund ADA-RFD-001, with its reference');
$pay = rows("SELECT * FROM payments WHERE id = ?", [$r['payment_id']])[0] ?? [];
eq([(float)$pay['amount'], $pay['method'], $pay['reference']], [-10000.0, 'bank_transfer', 'TRF-889'], 'a minus payment on the invoice');
$inv = invoice_of((int)$c1['id']);
eq([(float)$inv['amount_paid'], $inv['status']], [50000.0, 'paid'], 'what is left still covers it: still paid (it used to say part paid)');
eq(money_today(), $m0 - 10000, 'money in goes down by what went back');
$cash = make_order(['customer_id' => 261, 'items' => [['name' => 'Comb', 'quantity' => 1, 'unit_price' => 2000]], 'paid_now' => 2000]);
eq(refund_record($db, BIZ, (int)$cash['id'], ['amount' => 2000, 'method' => 'cash'], null, ADA, '₦')['status'], 'paid', 'cash needs no proof');

section('Cancelling: refused while money is paid, done after the refund');
clean_slate();
$s300 = stock_of(300);
eq(order_cancel($db, BIZ, (int)$c1['id'], ADA, 'Customer changed her mind'), '₦50,000 was paid on this order. Give it back first, then cancel.', 'refused with money paid');
eq(q("SELECT status FROM orders WHERE id = ?", [$c1['id']]), 'approved', 'the order stays');
eq(refused(fn() => refund_ask($db, BIZ, (int)$c1['id'], 60000, '', ADA)), 'You can give back at most what was paid on this order: ₦50,000.', 'a refund of at most what was paid');
$ask = refund_ask($db, BIZ, (int)$c1['id'], 50000, 'Order cancelled', ADA);
eq([$ask['status'], (int)$ask['asked_email'], $ask['url']], ['asked', 1, APP_URL . '/public/refund/' . $ask['token']], 'asked for account details, by email');
ok(str_contains($ask['wa_text'], $ask['url']) && str_contains($ask['wa_text'], '₦50,000') && str_contains($ask['wa_text'], $c1['order_number']), 'and ready to send on WhatsApp');
eq($ask['wa_phone'], '2348035550261', 'to her number, as WhatsApp wants it');
eq((int)refund_ask($db, BIZ, (int)$c1['id'], 50000, '', ADA)['id'], (int)$ask['id'], 'asking again is the same refund');
deliver();
$m = mails_to('bola.return@example.test');
eq(count($m), 1, 'the customer gets the link once');
ok(str_contains($m[0]['html'] ?? '', '/public/refund/' . $ask['token']) && str_contains($m[0]['text'] ?? '', '₦50,000'), 'with the amount and their link');
eq(refund_save_details($db, $ask['token'], ['payee_kind' => 'bank', 'payee_name' => 'Bola Return', 'payee_bank' => 'GTBank', 'payee_number' => '01234']),
   'Type the account number, digits only.', 'the public form checks the number');
eq(refund_save_details($db, 'nope', []), 'This link isn\'t working. Ask the shop to send it again.', 'and the link');
eq(refund_save_details($db, $ask['token'], ['payee_kind' => 'bank', 'payee_name' => 'Bola Return', 'payee_bank' => 'GTBank', 'payee_number' => '0123 456 789']), null, 'details sent');
eq([refund_open($db, BIZ, (int)$c1['id'])['status'] ?? null, refund_open($db, BIZ, (int)$c1['id'])['payee_number'] ?? null], ['details_in', '0123456789'], 'the details are in');
eq(told('refund.details'), [ADA, BISI], 'owners and admins hear');
eq(json_decode((string)q("SELECT data FROM notifications WHERE event = 'refund.details' LIMIT 1"), true)['payee'] ?? null, 'Bank: Bola Return, GTBank, 0123456789', 'with where to send it');
$png = tempnam(sys_get_temp_dir(), 'proof');
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$paid = refund_record($db, BIZ, (int)$c1['id'], ['amount' => 50000, 'method' => 'mobile_money', 'refund_id' => $ask['id']],
                      ['name' => 'receipt.png', 'type' => 'image/png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)], ADA, '₦');
eq([(int)$paid['id'], $paid['status'], $paid['doc_number']], [(int)$ask['id'], 'paid', 'ADA-RFD-003'], 'the asked refund is paid');
ok(str_starts_with((string)$paid['proof_path'], 'storage/private/refund_proofs/2/') && is_file(BASE_PATH . '/' . $paid['proof_path']), 'with its screenshot, kept privately');
@unlink(BASE_PATH . '/' . $paid['proof_path']);
@unlink($png);
eq([(float)invoice_of((int)$c1['id'])['amount_paid'], invoice_of((int)$c1['id'])['status']], [0.0, 'sent'], 'nothing paid on it now');
eq(order_cancel($db, BIZ, (int)$c1['id'], ADA, 'Customer changed her mind'), '', 'now it cancels');
$o = order_find($db, BIZ, (int)$c1['id']);
eq([$o['status'], $o['cancel_reason'], invoice_of((int)$c1['id'])['status']], ['cancelled', 'Customer changed her mind', 'cancelled'], 'the order and its invoice are cancelled, with why');
eq(stock_of(300), $s300 + 1, 'the stock is back');
eq(array_column(debts_of((int)$c1['id']), 'status'), ['written_off'], 'its debt is written off');
eq(order_cancel($db, BIZ, (int)$c1['id'], ADA), 'This order is already cancelled.', 'cancelling twice does nothing');
deliver();
ok((bool)array_filter(mails_to('bola.return@example.test'), fn($m) => str_contains($m['subject'], 'was cancelled')), 'the customer is told');
// An invoice cancelled on its own page takes its order with it, through invoices.order_id
$h = make_order(['customer_id' => 200, 'items' => [['name' => 'Wig wash', 'quantity' => 1, 'unit_price' => 15000]]]);
exec_sql("UPDATE orders SET invoice_id = NULL WHERE id = ?", [$h['id']]);
$db->beginTransaction();
eq(invoice_cancel($db, BIZ, (int)invoice_of((int)$h['id'])['id']), '', 'the invoice is cancelled from its own page');
$db->commit();
eq(q("SELECT status FROM orders WHERE id = ?", [$h['id']]), 'cancelled', 'and its order with it');

section('Turning down a new shop order');
clean_slate();
exec_sql("INSERT INTO orders (business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone, customer_email,
            payment_method, currency_code, subtotal, total, status)
          VALUES (2, 200, 'ORD-0721', ?, 'whatsapp', 'pickup', 'Ngozi Eze', '08031110000', 'ngozi@example.test', 'none', 'NGN', 55000, 55000, 'pending')",
         [bin2hex(random_bytes(16))]);
$wa = (int)q("SELECT id FROM orders WHERE order_number = 'ORD-0721'");
eq(order_next_action(order_find($db, BIZ, $wa), null, null), ['key' => 'confirm', 'label' => 'Confirm order', 'icon' => 'check'], 'a new order: Confirm order');
eq(order_pay_tag(null)['words'], '', 'no payment tag before there is an invoice');
order_turn_down($db, BIZ, $wa, BISI, 'Out of stock until next week.');
eq([q("SELECT status FROM orders WHERE id = ?", [$wa]), q("SELECT cancel_reason FROM orders WHERE id = ?", [$wa])], ['rejected', 'Out of stock until next week.'], 'turned down, with why');
eq(refused(fn() => order_turn_down($db, BIZ, $wa, BISI)), 'Only a new order can be turned down.', 'only once');
eq(order_stage(order_find($db, BIZ, $wa)), ['key' => 'turned_down', 'words' => 'Turned down', 'tone' => 'is-line'], 'the owner reads Turned down');
eq(order_steps(order_find($db, BIZ, $wa)), [], 'and no steps');
deliver();
ok((bool)array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['text'], 'Out of stock until next week.')), 'the shopper is told why');

/* ══ Moving an order on ═══════════════════════════════════════ */

section('Sent out, delivered, and back: the delivery note follows');
clean_slate();
$d = make_order(['customer_id' => 200, 'items' => [['product_id' => 300, 'quantity' => 1, 'unit_price' => 65000]], 'fulfillment' => 'delivery',
                 'deliver_by' => $day(1), 'delivery_address' => '12 Allen Avenue', 'paid_now' => 65000]);
$delivery = fn() => rows("SELECT * FROM deliveries WHERE order_id = ?", [$d['id']])[0] ?? [];
$order = fn() => order_find($db, BIZ, (int)$d['id']);
eq([order_stage($order(), $delivery())['words'], order_next_action($order(), invoice_of((int)$d['id']), $delivery())['label'] ?? null], ['Confirmed', 'Send it out'],
   'confirmed; next: Send it out');
eq(array_column(order_steps($order(), $delivery()), 'state'), ['done', 'now', 'next', 'next'], 'the dots: New done, Confirmed now');
order_set_stage($db, BIZ, (int)$d['id'], 'ready', BISI);
order_set_stage($db, BIZ, (int)$d['id'], 'ready', BISI);
eq([$order()['status'], $delivery()['status'], $delivery()['dispatch_date'] !== null], ['ready', 'dispatched', true], 'on its way: the delivery note is dispatched');
eq([order_stage($order(), $delivery())['words'], order_stage($order(), $delivery())['tone']], ['On its way', 'is-accent'], 'the owner reads On its way');
eq(order_next_action($order(), invoice_of((int)$d['id']), $delivery())['label'] ?? null, 'Mark delivered', 'next: Mark delivered');
deliver();
eq(count(array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['subject'], 'is on its way'))), 1, 'the shopper hears "on its way" once for two taps');
order_set_stage($db, BIZ, (int)$d['id'], 'done', BISI);
$inv = invoice_of((int)$d['id']);
eq([$order()['status'], $order()['done_at'] !== null, $delivery()['status'], $delivery()['actual_delivery'] !== null, $inv['delivered_at'] !== null],
   ['fulfilled', true, 'delivered', true, true], 'done: delivered, on the order, the delivery note and the invoice');
eq((int)q("SELECT COUNT(*) FROM reviews WHERE order_id = ?", [$d['id']]), 1, 'the shopper is asked for a review');
eq([order_stage($order(), $delivery())['words'], order_next_action($order(), $inv, $delivery())], ['Delivered', null], 'Delivered, nothing more to do');
deliver();
ok((bool)array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['subject'], 'has been delivered') && str_contains($m['html'], '/review/')),
   'the shopper hears "delivered", with the link to rate it');
order_set_stage($db, BIZ, (int)$d['id'], 'undo_done', ADA);
eq([$order()['status'], $order()['done_at'], $delivery()['status'], $delivery()['actual_delivery'], invoice_of((int)$d['id'])['delivered_at']],
   ['approved', null, 'pending_dispatch', null, null], 'marked by mistake: back to Confirmed, and the delivery note too');
order_set_stage($db, BIZ, (int)$d['id'], 'done', ADA);
exec_sql("UPDATE deliveries SET status = 'signed_off' WHERE order_id = ?", [$d['id']]);
eq(refused(fn() => order_set_stage($db, BIZ, (int)$d['id'], 'undo_done', ADA)), 'The customer signed for this delivery, so it stays delivered.', 'not once the customer signed');
eq(array_column(array_filter(order_events($db, BIZ, (int)$d['id']), fn($e) => in_array($e['kind'], ['ready', 'done', 'undone'], true)), 'kind'),
   ['ready', 'done', 'undone', 'done'], 'each move is in the Log, once');

section('Ready for pickup');
clean_slate();
$p = make_order(['customer_id' => 200, 'items' => [['name' => 'Wig install', 'quantity' => 1, 'unit_price' => 20000]], 'deliver_by' => $day(1), 'paid_now' => 5000]);
eq(order_next_action($p, invoice_of((int)$p['id']), null)['label'] ?? null, 'Mark ready', 'next: Mark ready');
order_set_stage($db, BIZ, (int)$p['id'], 'ready', ADA);
$p = order_find($db, BIZ, (int)$p['id']);
eq([$p['status'], order_stage($p)['words'], order_next_action($p, invoice_of((int)$p['id']), null)['label'] ?? null], ['ready', 'Ready for pickup', 'Mark collected'],
   'Ready for pickup; next: Mark collected');
$track = shop_order_steps($p + ['delivery' => null], 'Ada\'s Hair & Co');
eq(array_column($track['steps'], 'state', 'label')['Ready for pickup'] ?? null, 'now', 'the shopper\'s tracking page shows Ready for pickup');
deliver();
ok((bool)array_filter(mails_to('ngozi@example.test'), fn($m) => str_contains($m['subject'], 'is ready for pickup')), 'and they hear it is ready for pickup');
ok(str_contains(shop_order_whatsapp_text($p, 'Ada\'s Hair & Co', null), 'is ready for pickup'), 'the WhatsApp text says so too');
order_set_stage($db, BIZ, (int)$p['id'], 'done', ADA);
$p = order_find($db, BIZ, (int)$p['id']);
eq([order_stage($p)['words'], order_next_action($p, invoice_of((int)$p['id']), null)['label'] ?? null], ['Collected', 'Record payment'],
   'collected with a balance: next is Record payment');
eq(refused(fn() => order_set_stage($db, BIZ, 999999, 'done', ADA)), 'That order isn\'t here.', 'an order of another business, or none, isn\'t found');
eq(refused(fn() => order_update_lines($db, BIZ, (int)$p['id'], [['name' => 'Wig install', 'quantity' => 2, 'unit_price' => 20000]], 0, ADA, '₦')),
   'This order is done, so its items can\'t change.', 'a done order\'s items stay as they were');

section('Ticked on Tasks, or moved by its delivery note');
clean_slate();
$t = make_order(['customer_id' => 200, 'items' => [['name' => 'Wig styling', 'quantity' => 1, 'unit_price' => 30000]], 'deliver_by' => $day(1), 'paid_now' => 30000]);
$tinv = (int)invoice_of((int)$t['id'])['id'];
exec_sql("INSERT INTO tasks (business_id, kind, title, due_date, invoice_id, order_id, step_key, created_by) VALUES (2, 'task', 'Hand over the wig', ?, ?, ?, 'deliver', 20)",
         [$day(1), $tinv, $t['id']]);
order_from_invoice_delivered($db, BIZ, $tinv, false, ADA);
eq([q("SELECT status FROM orders WHERE id = ?", [$t['id']]), (int)q("SELECT done_at IS NOT NULL FROM tasks WHERE invoice_id = ?", [$tinv]),
    (int)q("SELECT delivered_at IS NOT NULL FROM invoices WHERE id = ?", [$tinv])], ['fulfilled', 1, 1], 'ticked on Tasks: the order is done, the step ticked');
order_from_invoice_delivered($db, BIZ, $tinv, true, ADA);
eq([q("SELECT status FROM orders WHERE id = ?", [$t['id']]), (int)q("SELECT done_at IS NULL FROM tasks WHERE invoice_id = ?", [$tinv])], ['approved', 1], 'unticked: back to Confirmed');
order_from_invoice_delivered($db, BIZ, 400, false, ADA);
eq((int)q("SELECT delivered_at IS NOT NULL FROM invoices WHERE id = 400"), 1, 'an invoice without an order only keeps its own promise');
$g = make_order(['customer_id' => 200, 'items' => [['name' => 'Braids', 'quantity' => 1, 'unit_price' => 30000]], 'fulfillment' => 'delivery', 'paid_now' => 30000]);
$gd = (int)q("SELECT id FROM deliveries WHERE order_id = ?", [$g['id']]);
exec_sql("UPDATE deliveries SET status = 'in_transit' WHERE id = ?", [$gd]);
order_sync_from_delivery($db, BIZ, $gd);
eq(q("SELECT status FROM orders WHERE id = ?", [$g['id']]), 'ready', 'its delivery note is on its way: the order is too');
exec_sql("UPDATE deliveries SET status = 'signed_off' WHERE id = ?", [$gd]);
order_sync_from_delivery($db, BIZ, $gd);
eq([q("SELECT status FROM orders WHERE id = ?", [$g['id']]), (int)q("SELECT COUNT(*) FROM reviews WHERE order_id = ?", [$g['id']])], ['fulfilled', 1], 'signed for: the order is done, with its review invitation');
exec_sql("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$g['id']]);
exec_sql("UPDATE deliveries SET status = 'dispatched' WHERE id = ?", [$gd]);
order_sync_from_delivery($db, BIZ, $gd);
eq(q("SELECT status FROM orders WHERE id = ?", [$g['id']]), 'cancelled', 'a cancelled order never moves');

done();
