<?php
/**
 * Demo data for the October 2026 video (sales typed in late, money paid in
 * advance, deleting a cancelled order): a made-up "Ada's Studio" on the test
 * fixture, never a real business. Prints the ids scenes.json opens.
 * WIPES the database, so it refuses unless APP_ENV is local or testing.
 */
require __DIR__ . '/../../../../tests/notifications/lib.php';
t_guard();
fixture_reset();

$db  = get_db();
$t   = planner_today();
$day = fn(int $n) => planner_add_days($t, $n);
exec_sql("UPDATE businesses SET name = 'Ada''s Studio' WHERE id = 2");
exec_sql("UPDATE products SET name = 'Ankara gown', unit = 'piece' WHERE id = 300");
exec_sql("UPDATE products SET name = 'Aso-oke gele' WHERE id = 301");
exec_sql("UPDATE customers SET full_name = 'Chioma Okafor', email = NULL WHERE id = 201");
$order = fn(array $in) => order_create($db, 2, $in + ['channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'cash', 'paid_now' => 0], 20);
$gown  = [['product_id' => 300, 'name' => '', 'quantity' => 1, 'unit_price' => 65000]];

// Some history, so the screens look lived in
$order(['customer_id' => 200, 'items' => $gown, 'fulfillment' => 'now', 'paid_now' => 65000, 'date' => $day(-9)]);
kept_add($db, 2, 201, 40000, $day(-130), 'bank_transfer', 'TRF-4471', 20);
// Change dates: an order paid in two parts
$two = $order(['customer_id' => 200, 'items' => $gown, 'paid_now' => 30000, 'deliver_by' => $day(2), 'pay_by' => $day(9)]);
order_record_payment($db, 2, (int)$two['id'], 20000, $t, 'bank_transfer', 'TRF-9', null, 20, '₦');
// Cancel and keep: paid in full, not collected yet
$paid = $order(['customer_id' => 200, 'items' => $gown, 'paid_now' => 65000, 'deliver_by' => $day(3)]);
// Delete: the newest order, cancelled, so its numbers come back
$cancelled = $order(['customer_id' => 201, 'items' => $gown, 'deliver_by' => $day(3), 'pay_by' => $day(7)]);
order_cancel($db, 2, (int)$cancelled['id'], 20, 'Typed in by mistake');

// A clean screen: no bell count, and the "first invoice" feedback prompt already answered
exec_sql("DELETE FROM notifications");
foreach (['first_invoice', 'first_delivery_signoff', 'first_receipt', 'first_quotation'] as $trigger) {
    exec_sql("INSERT IGNORE INTO feedback_triggers_seen (user_id, trigger_action) VALUES (20, ?)", [$trigger]);
}
echo json_encode(['two' => (int)$two['id'], 'paid' => (int)$paid['id'], 'cancelled' => (int)$cancelled['id'], 'client' => 201]), "\n";
