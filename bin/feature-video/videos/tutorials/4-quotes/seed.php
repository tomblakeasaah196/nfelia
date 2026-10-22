<?php
/**
 * Tutorial 4 (quotes, deposits and customers' money): Adanna Luxe Hair after
 * video 3, plus Zainab (who pays before choosing), Blessing's paid order (to
 * cancel and keep the money) and the newest order, typed in by mistake and
 * cancelled (to delete). Temitope, the bride, is added on camera.
 */
require __DIR__ . '/../adanna.php';
$db  = adanna_reset(10);
$p   = adanna_products($db);
$c   = adanna_sales($db, $p);
$day = fn(int $n) => planner_add_days(planner_today(), $n);
$zainab   = adanna_customer($db, 'zainab');
$blessing = adanna_customer($db, 'blessing');
$paid = order_create($db, 2, ['customer_id' => $blessing, 'channel' => 'manual', 'fulfillment' => 'delivery',
    'items' => [['product_id' => $p['deep'], 'name' => '', 'quantity' => 1, 'unit_price' => 145000]],
    'method' => 'bank_transfer', 'paid_now' => 145000, 'deliver_by' => $day(2), 'delivery_address' => ADANNA_CUSTOMERS['blessing'][2]], 20);
$oops = order_create($db, 2, ['customer_id' => $c['funke'], 'channel' => 'manual', 'fulfillment' => 'pickup',
    'items' => [['product_id' => $p['bob'], 'name' => '', 'quantity' => 1, 'unit_price' => 95000]],
    'method' => 'cash', 'paid_now' => 0, 'pay_by' => $day(7)], 20);
order_cancel($db, 2, (int)$oops['id'], 20, 'Typed in twice by mistake');
echo json_encode(['zainab' => $zainab, 'blessing' => $blessing, 'paid' => (int)$paid['id'], 'oops' => (int)$oops['id']]), "\n";
