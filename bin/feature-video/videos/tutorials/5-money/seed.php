<?php
/**
 * Tutorial 5 (your money, your team and your records): Adanna Luxe Hair after
 * video 4, with Temitope's bridal order part paid (her balance is in Debts),
 * a Records Book Adanna bought during her trial for a loan, and a savings
 * co-operative asking to see it (made-up names). The supplier, the staff
 * member and the task are added on camera.
 */
require __DIR__ . '/../adanna.php';
require_once BASE_PATH . '/app/helpers/records.php';
$db  = adanna_reset(9);
$p   = adanna_products($db);
adanna_sales($db, $p);
$day = fn(int $n) => planner_add_days(planner_today(), $n);
$temitope = adanna_customer($db, 'temitope');
$bridal = order_create($db, 2, ['customer_id' => $temitope, 'channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'bank_transfer',
    'items' => [['product_id' => $p['bone'], 'name' => 'Bone Straight Bundles, 18 inches', 'quantity' => 6, 'unit_price' => 115000],
                ['product_id' => $p['frontal'], 'name' => '', 'quantity' => 2, 'unit_price' => 110000],
                ['product_id' => null, 'name' => 'Bridal installation and styling', 'quantity' => 1, 'unit_price' => 80000]],
    'discount' => 40000, 'paid_now' => 500000, 'pay_by' => $day(16), 'deliver_by' => $day(16), 'date' => $day(-1)], 20);

// The Records Book, bought for a loan application, and who asked to see it
$book = records_queue($db, 2, ['charge' => 'paid', 'amount' => 7500, 'currency' => 'NGN', 'payment_ref' => 'PSK-ALH-7731', 'reason' => 'For a loan application']);
records_build($db, $book);
$code = (string)$db->query("SELECT code FROM records_books WHERE id = {$book}")->fetchColumn();
$db->prepare("INSERT INTO record_requests (book_id, business_id, code, label, name, organisation, email, phone, role, reason, token_hash, status, confirmed_at, asked_at)
              VALUES (?, 2, ?, ?, 'Kemi Adebayo', 'Lekki Traders Savings Co-operative', 'kemi@lekkitraders.test', '08051112233', 'bank',
                      'We are checking your sales for the business loan of five hundred thousand naira you asked us for.', ?, 'asked', NOW(), NOW())")
   ->execute([$book, $code, 'Adanna Luxe Hair\'s Records Book', hash('sha256', bin2hex(random_bytes(16)))]);
echo json_encode(['bridal' => (int)$bridal['id'], 'request' => (int)$db->lastInsertId()]), "\n";
