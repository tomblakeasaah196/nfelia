<?php
/**
 * Sales typed in late, and the money and numbers around them (a shop's
 * questions, October 2026): a new order dated the day it happened, an
 * order's dates changed afterwards, money a client pays before choosing
 * (Kept with you › Add money), a paid order cancelled with its money kept,
 * and a cancelled order deleted with its numbers freed for the next papers.
 *   bash tests/notifications/run.sh orders/late
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, BISI = 21, CHIDI = 22, BIZ = 2, NGOZI = 200, FEMI = 201;
$db    = get_db();
$today = planner_today();
$day   = fn(int $n) => planner_add_days($today, $n);
$june  = $day(-130);

function refused(callable $work): string
{
    try {
        $work();
        return '';
    } catch (DomainException $e) {
        return $e->getMessage();
    }
}

function make_order(array $in): array
{
    return order_create(get_db(), BIZ, $in + ['channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'cash', 'paid_now' => 0], ADA);
}

function bundle(int $qty = 1): array
{
    return [['product_id' => 300, 'name' => '', 'quantity' => $qty, 'unit_price' => 65000]];
}

function invoice_of(int $order_id): array
{
    return rows("SELECT * FROM invoices WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$order_id])[0] ?? [];
}

function money_on(string $day): float
{
    return money_in(get_db(), BIZ, $day, $day);
}

function counter(string $type): int
{
    return (int)q("SELECT last_number FROM document_counters WHERE business_id = ? AND doc_type = ?", [BIZ, $type]);
}

/* ══ A sale typed in after the day it happened ═══════════════════ */

section('A quick sale dated the day it happened');
$stock = (float)q("SELECT stock_qty FROM products WHERE id = 300");
$o = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'fulfillment' => 'now', 'paid_now' => 65000, 'date' => $june]);
$inv = invoice_of((int)$o['id']);
$pay = rows("SELECT * FROM payments WHERE invoice_id = ?", [$inv['id']]);
$rec = rows("SELECT * FROM receipts WHERE order_id = ?", [$o['id']]);
eq(substr((string)$o['created_at'], 0, 10), $june, 'the order is dated that day');
eq([$inv['issue_date'], $inv['due_date'], substr((string)$inv['delivered_at'], 0, 10)], [$june, $june, $june], 'its invoice too: issued, due and collected that day');
eq([count($pay), $pay[0]['payment_date'] ?? null, count($rec), $rec[0]['issue_date'] ?? null], [1, $june, 1, $june], 'the payment and its receipt too');
eq([$o['status'], substr((string)q("SELECT done_at FROM orders WHERE id = ?", [$o['id']]), 0, 10)], ['fulfilled', $june], 'collected on the spot, that day');
eq([money_on($june), money_on($today)], [65000.0, 0.0], 'reports count the money that day, not today');
eq((float)q("SELECT stock_qty FROM products WHERE id = 300"), $stock - 1, 'the stock still leaves the shelf now');
ok(str_ends_with((string)q("SELECT text FROM order_events WHERE order_id = ? AND kind = 'created'", [$o['id']]), 'Dated ' . order__day_words($june) . '.'),
   'the Log says it was dated');
deliver();
eq(mails_to('ngozi@example.test'), [], 'Ngozi gets no "thank you for collecting" months later');

section('A day not yet come, or no day at all, is refused');
eq(refused(fn() => make_order(['customer_id' => NGOZI, 'items' => bundle(), 'date' => $day(1)])),
   'Pick today or a day before. ' . order__day_words($day(1)) . ' hasn\'t come yet.', 'tomorrow is refused');
eq(refused(fn() => make_order(['customer_id' => NGOZI, 'items' => bundle(), 'date' => '2026-02-30'])), 'Pick the day of the sale.', 'a day that doesn\'t exist is refused');
$t = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'date' => '']);
eq([substr((string)$t['created_at'], 0, 10), invoice_of((int)$t['id'])['issue_date']], [$today, $today], 'no day given: today, as before');

section('The New order screen sends the day');
$ada = signin('ada@adashair.test');
ok(str_contains($ada->get('orders/create')->body, 'id="nf-sale-day"'), 'New order has the date line');
$before = (int)q("SELECT MAX(id) FROM orders");
$ada->post('orders/save', ['mode' => 'quick', 'sale' => 'shop', 'fulfillment' => 'now', 'pay' => 'now', 'method' => 'cash', 'walk_in' => '1',
                           'sale_date' => $day(-3), 'items' => [['product_id' => 300, 'name' => 'Body Wave 18in', 'quantity' => '1', 'unit_price' => '65000']]]);
$web = rows("SELECT * FROM orders WHERE id > ? ORDER BY id DESC LIMIT 1", [$before])[0] ?? [];
eq([substr((string)($web['created_at'] ?? ''), 0, 10), invoice_of((int)($web['id'] ?? 0))['issue_date'] ?? null], [$day(-3), $day(-3)], 'a quick sale from three days ago');
ok(str_contains($ada->follow()->body, 'Dated ' . order__day_words($day(-3))), 'and the owner is told it was dated');

/* ══ Changing an order's dates ═══════════════════════════════════ */

section('Owners and admins put an order on its own day');
$p = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'paid_now' => 30000, 'pay_by' => $day(10), 'deliver_by' => $day(2)]);
$second = order_record_payment($db, BIZ, (int)$p['id'], 20000, $today, 'bank_transfer', 'TRF-1', null, ADA, '₦');
$pays = rows("SELECT p.id, p.amount FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE i.order_id = ? ORDER BY p.id", [$p['id']]);
$moved = order_set_dates($db, BIZ, (int)$p['id'], $day(-20), [(int)$pays[0]['id'] => $day(-20), (int)$pays[1]['id'] => $day(-15)], ADA);
$inv = invoice_of((int)$p['id']);
eq($moved, 3, 'three things moved: the order, and both payments');
eq([substr((string)q("SELECT created_at FROM orders WHERE id = ?", [$p['id']]), 0, 10), $inv['issue_date']], [$day(-20), $day(-20)], 'the order and its invoice');
eq([$inv['due_date'], $inv['deliver_by']], [$day(10), $day(2)], 'its pay-by and pickup days are their own promises: they stay');
eq(array_column(rows("SELECT payment_date FROM payments WHERE invoice_id = ? ORDER BY id", [$inv['id']]), 'payment_date'), [$day(-20), $day(-15)], 'each payment on its own day');
eq(array_column(rows("SELECT issue_date FROM receipts WHERE order_id = ? ORDER BY id", [$p['id']]), 'issue_date'), [$day(-20), $day(-15)], 'and each receipt with it');
eq([money_on($day(-20)), money_on($day(-15))], [30000.0, 20000.0], 'reports count each amount that day');
eq(count(rows("SELECT id FROM order_events WHERE order_id = ? AND kind = 'dated'", [$p['id']])), 3, 'the Log keeps each old date');
eq(order_set_dates($db, BIZ, (int)$p['id'], $day(-20), [], ADA), 0, 'the same dates again: nothing moves');
eq(refused(fn() => order_set_dates($db, BIZ, (int)$p['id'], $day(1), [], ADA)), 'Pick today or a day before. ' . order__day_words($day(1)) . ' hasn\'t come yet.', 'a day to come is refused');
eq(refused(fn() => order_set_dates($db, BIZ, (int)$p['id'], '', [], ADA)), 'Pick the day of the order.', 'no day is refused, not taken as today');

section('A sale paid on the day moves whole');
$q1 = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'fulfillment' => 'now', 'paid_now' => 65000]);
order_set_dates($db, BIZ, (int)$q1['id'], $day(-40), [], ADA);
$inv = invoice_of((int)$q1['id']);
eq([$inv['issue_date'], $inv['due_date'], $inv['deliver_by'], substr((string)$inv['delivered_at'], 0, 10),
    substr((string)q("SELECT done_at FROM orders WHERE id = ?", [$q1['id']]), 0, 10)],
   [$day(-40), $day(-40), $day(-40), $day(-40), $day(-40)], 'paid, taken and collected that day: all of it follows');

section('Change dates on the order page');
$page = $ada->get('orders/view/' . $q1['id'])->body;
ok(str_contains($page, 'id="nf-dates"') && str_contains($page, 'name="paid_on['), 'the owner has Change dates, with each payment');
$chidi = signin('chidi@adashair.test');
ok(!str_contains($chidi->get('orders/view/' . $q1['id'])->body, 'id="nf-dates"'), 'staff don\'t');
$chidi->post('orders/dates/' . $q1['id'], ['date' => $day(-50)]);
eq(invoice_of((int)$q1['id'])['issue_date'], $day(-40), 'and can\'t post it either');
$bisi = signin('bisi@adashair.test');
$bisi->post('orders/dates/' . $q1['id'], ['date' => $day(-41)]);
eq(invoice_of((int)$q1['id'])['issue_date'], $day(-41), 'an admin can');

/* ══ Money paid before choosing ══════════════════════════════════ */

section('Add money: paid in advance, kept with you');
$money_june = money_on($june);
$adv = kept_add($db, BIZ, FEMI, 40000, $june, 'bank_transfer', 'TRF-777', ADA);
$r = rows("SELECT * FROM receipts WHERE id = ?", [$adv['id']])[0];
eq([$r['invoice_id'], $r['order_id'], $r['issue_date'], (float)$r['amount'], $r['method']], [null, null, $june, 40000.0, 'bank_transfer'], 'a receipt of its own, on the day it came in');
eq($r['note'], 'Paid in advance, kept for your next order. Reference TRF-777.', 'saying what it is for, with the reference');
eq(kept_balance($db, BIZ, FEMI), 40000.0, 'kept with you for Femi');
eq(money_on($june), $money_june + 40000, 'money in counts it on the day it came in');
eq((float)q("SELECT total_received FROM customers WHERE id = ?", [FEMI]), 40000.0, 'Femi\'s page shows it received');
deliver();
ok((bool)bell(BISI, 'payment.receipt'), 'the admin hears it came in');
eq(refused(fn() => kept_add($db, BIZ, FEMI, 0, '', 'cash', null, ADA)), 'Type how much came in.', 'nothing is refused');
eq(refused(fn() => kept_add($db, BIZ, FEMI, 100, $day(1), 'cash', null, ADA)), 'Pick today or a day before. ' . order__day_words($day(1)) . ' hasn\'t come yet.', 'a day to come is refused');
$walk_in = order_walk_in_customer($db, BIZ);
eq(refused(fn() => kept_add($db, BIZ, $walk_in, 100, '', 'cash', null, ADA)), 'That client isn\'t here any more.', 'nobody can keep money for a walk-in');

section('Their next order uses it, and it is never counted twice');
$money_today = money_on($today);
$f = make_order(['customer_id' => FEMI, 'items' => bundle(), 'use_kept' => true, 'pay_by' => $day(7)]);
$inv = invoice_of((int)$f['id']);
eq([(float)$inv['amount_paid'], (float)$inv['balance_due']], [40000.0, 25000.0], 'the ₦40,000 pays part of it; ₦25,000 is left');
eq(kept_balance($db, BIZ, FEMI), 0.0, 'nothing is kept any more');
eq(money_on($today), $money_today, 'using it is not new money');
eq((float)q("SELECT total_received FROM customers WHERE id = ?", [FEMI]), 40000.0, 'still ₦40,000 received from Femi, once');

section('Add money on the client\'s page');
$page = $chidi->get('customers/view/' . FEMI . '?tab=kept')->body;
ok(str_contains($page, 'id="nf-kept-add"') && str_contains($page, "tab: 'kept'"), 'the Kept with you tab opens, with Add money');
$chidi->post('customers/add_money/' . FEMI, ['amount' => '15,000', 'method' => 'cash', 'date' => $today]);
eq(kept_balance($db, BIZ, FEMI), 15000.0, 'staff can take money in, as they take payments');
$page = $chidi->follow()->body;
ok(str_contains($page, '₦15,000 kept for Femi') && str_contains($page, 'Paid in advance'), 'and see it on the tab, line by line');
$advance = (int)q("SELECT receipt_id FROM kept_money WHERE customer_id = ? AND receipt_id IS NOT NULL ORDER BY id DESC LIMIT 1", [FEMI]);
ok(str_contains($ada->get('receipts/view/' . $advance)->body, 'Paid in advance'), 'its receipt says paid in advance');

section('A receipt for money paid in advance');
$used = (int)$adv['id'];
$ada->post('receipts/delete/' . $used);
eq((int)q("SELECT COUNT(*) FROM receipts WHERE id = ?", [$used]), 1, 'money already used on an order keeps its receipt');
$ada->post('receipts/delete/' . $advance);
eq([(int)q("SELECT COUNT(*) FROM receipts WHERE id = ?", [$advance]), kept_balance($db, BIZ, FEMI)], [0, 0.0], 'unused, it can go, and the money kept with it');

/* ══ Cancelling a paid order ═════════════════════════════════════ */

section('Cancel a paid order and keep the money for the customer');
$money_today = money_on($today);
$stock = (float)q("SELECT stock_qty FROM products WHERE id = 300");
$k = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'paid_now' => 65000, 'deliver_by' => $day(3)]);
$kept_before = kept_balance($db, BIZ, NGOZI);
eq(order_cancel($db, BIZ, (int)$k['id'], ADA, 'Changed her mind'), '₦65,000 was paid on this order. Give it back first, then cancel.', 'without keeping, it still asks for the money back first');
eq(order_cancel($db, BIZ, (int)$k['id'], ADA, 'Changed her mind', true), '', 'keeping it, it cancels');
$inv = invoice_of((int)$k['id']);
eq([q("SELECT status FROM orders WHERE id = ?", [$k['id']]), $inv['status'], (float)$inv['amount_paid']], ['cancelled', 'cancelled', 0.0], 'the order and its invoice are cancelled, nothing paid on it');
eq(kept_balance($db, BIZ, NGOZI), $kept_before + 65000, 'the ₦65,000 is kept for Ngozi');
eq(money_on($today), $money_today + 65000, 'it came in once, today, and keeping it is not new money');
eq((float)q("SELECT stock_qty FROM products WHERE id = 300"), $stock, 'the stock is back on the shelf');
ok(str_contains((string)q("SELECT text FROM order_events WHERE order_id = ? AND kind = 'kept'", [$k['id']]), 'kept for Ngozi\'s next order'), 'the Log says so');
deliver();
$mail = mails_to('ngozi@example.test');
ok($mail && str_contains(end($mail)['text'] ?? '', 'you paid is kept for your next order'), 'Ngozi is told her money waits for her');
$w = make_order(['items' => bundle(), 'paid_now' => 65000, 'deliver_by' => $day(3)]);
eq(order_cancel($db, BIZ, (int)$w['id'], ADA, '', true), 'Money can only be kept for a customer with a name. Give it back instead.', 'not for a walk-in');
$page = $ada->get('orders/view/' . $w['id'])->body;
ok(str_contains($page, 'Give it back first, then cancel.') && !str_contains($page, 'Cancel and keep'), 'whose cancel sheet only offers giving it back');
$k2 = make_order(['customer_id' => NGOZI, 'items' => bundle(), 'paid_now' => 10000, 'deliver_by' => $day(3)]);
ok(str_contains($ada->get('orders/view/' . $k2['id'])->body, 'Cancel and keep ₦10,000'), 'a named customer\'s sheet offers keeping it');
$ada->post('orders/cancel/' . $k2['id'], ['keep' => '1', 'reason' => '']);
eq(q("SELECT status FROM orders WHERE id = ?", [$k2['id']]), 'cancelled', 'and it works from the page');

/* ══ Deleting a cancelled order ══════════════════════════════════ */

section('Delete a cancelled order: its numbers come back');
$d = make_order(['customer_id' => FEMI, 'items' => bundle(), 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
$dinv = invoice_of((int)$d['id']);
eq(order_delete_check($db, BIZ, $d)['problem'], 'Only a cancelled or turned-down order can be deleted. Cancel it first.', 'a live order can\'t be deleted');
eq(order_cancel($db, BIZ, (int)$d['id'], ADA), '', 'cancelled');
$d = order_find($db, BIZ, (int)$d['id']);
$check = order_delete_check($db, BIZ, $d);
eq([$check['problem'], $check['freed']], [null, [$d['order_number'], $dinv['doc_number']]], 'nothing stops it; its order and invoice numbers would come back');
$done = order_delete($db, BIZ, (int)$d['id'], ADA);
eq($done['freed'], [$d['order_number'], $dinv['doc_number']], 'deleted, and both numbers freed');
eq([(int)q("SELECT COUNT(*) FROM orders WHERE id = ?", [$d['id']]), (int)q("SELECT COUNT(*) FROM invoices WHERE id = ?", [$dinv['id']]),
    (int)q("SELECT COUNT(*) FROM order_items WHERE order_id = ?", [$d['id']]), (int)q("SELECT COUNT(*) FROM order_events WHERE order_id = ?", [$d['id']]),
    (int)q("SELECT COUNT(*) FROM debts WHERE invoice_id = ?", [$dinv['id']])], [0, 0, 0, 0, 0], 'the order, its invoice, lines, Log and debt are gone');
ok((bool)q("SELECT id FROM purge_audit_logs WHERE business_id = ? AND purge_mode = 'Order delete' AND deleted_summary LIKE ?", [BIZ, '%' . $d['order_number'] . '%']),
   'the purge ledger keeps who deleted what');
$again = make_order(['customer_id' => FEMI, 'items' => bundle(), 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
eq([$again['order_number'], invoice_of((int)$again['id'])['doc_number']], [$d['order_number'], $dinv['doc_number']], 'the next order and invoice take those numbers again');

section('Money given back goes with it; money kept keeps it');
$rf = make_order(['customer_id' => FEMI, 'items' => bundle(), 'paid_now' => 65000, 'deliver_by' => $day(3)]);
$rf_rec = receipts_number((int)$rf['id']);
$money_today = money_on($today);
refund_record($db, BIZ, (int)$rf['id'], ['amount' => 65000, 'method' => 'cash'], null, ADA, '₦');
eq(order_cancel($db, BIZ, (int)$rf['id'], ADA), '', 'paid, given back in cash, cancelled');
$rf = order_find($db, BIZ, (int)$rf['id']);
$check = order_delete_check($db, BIZ, $rf);
eq($check['problem'], null, 'nothing stayed, so it can go');
ok(in_array($rf_rec, $check['documents'], true) && in_array('ADA-RFD-001', $check['documents'], true), 'with its receipt and refund slip');
order_delete($db, BIZ, (int)$rf['id'], ADA);
eq([money_on($today), counter('REC'), counter('RFD')], [$money_today - 65000, (int)substr($rf_rec, -3) - 1, 0], 'the money in and out goes, and the receipt and refund numbers come back');
$kept_order = order_find($db, BIZ, (int)$k['id']);
eq(order_delete_check($db, BIZ, $kept_order)['problem'], 'Money from this order is kept for Ngozi, so the order stays as the record of it.', 'an order whose money is kept for the customer stays');
eq(refused(fn() => order_delete($db, BIZ, (int)$k['id'], ADA)), 'Money from this order is kept for Ngozi, so the order stays as the record of it.', 'even asked directly');

section('Older numbers stay gaps');
$old = make_order(['customer_id' => FEMI, 'items' => bundle(), 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
order_cancel($db, BIZ, (int)$old['id'], ADA);
$newer = make_order(['customer_id' => FEMI, 'items' => bundle(), 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
$ord_counter = counter('ORD');
eq(order_delete($db, BIZ, (int)$old['id'], ADA)['freed'], [], 'a newer order came after it: nothing is freed');
eq(counter('ORD'), $ord_counter, 'so no two orders ever share a number');
eq(doc_numbers_free($db, BIZ, ['XYZ-ORD-' . str_pad((string)$ord_counter, 3, '0', STR_PAD_LEFT)], false), [], 'a number from another prefix is never freed');

section('A turned-down shop order can go too');
$num = generate_doc_number($db, BIZ, 'ORD');
exec_sql("INSERT INTO orders (business_id, customer_id, order_number, public_token, channel, customer_name, customer_phone, currency_code, subtotal, total, status)
          VALUES (?, ?, ?, ?, 'cart_pop', 'Bola Ade', '08061234567', 'NGN', 65000, 65000, 'pending')", [BIZ, FEMI, $num, bin2hex(random_bytes(16))]);
$td = (int)$db->lastInsertId();
order_turn_down($db, BIZ, $td, ADA, 'Sold out');
eq(order_delete($db, BIZ, $td, ADA)['freed'], [$num], 'turned down, deleted, number freed');

section('Only the owner deletes, from the order page');
$x = make_order(['customer_id' => FEMI, 'items' => bundle(), 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
order_cancel($db, BIZ, (int)$x['id'], ADA);
ok(!str_contains($bisi->get('orders/view/' . $x['id'])->body, 'id="nf-delete"'), 'an admin isn\'t offered it');
$bisi->post('orders/delete/' . $x['id']);
eq((int)q("SELECT COUNT(*) FROM orders WHERE id = ?", [$x['id']]), 1, 'nor can post it');
$page = $ada->get('orders/view/' . $x['id'])->body;
ok(str_contains($page, 'id="nf-delete"') && str_contains($page, 'Your next papers take <b class="whitespace-nowrap">' . $x['order_number']), 'the owner is, with the numbers that come back');
$ada->post('orders/delete/' . $x['id']);
eq([(int)q("SELECT COUNT(*) FROM orders WHERE id = ?", [$x['id']]), $ada->to()], [0, 'orders'], 'deleted, back to Orders');
ok(str_contains($ada->follow()->body, 'Order ' . $x['order_number'] . ' deleted.'), 'saying so');

done();

/** The order's first receipt number. */
function receipts_number(int $order_id): string
{
    return (string)q("SELECT doc_number FROM receipts WHERE order_id = ? ORDER BY id LIMIT 1", [$order_id]);
}
