<?php
/**
 * Demo data for looking at the order screens (screenshots, a walk through):
 * the test world (tests/notifications/fixture.php) plus orders in every
 * state, for a fashion shop like the ones Nfelia is for. WIPES the local
 * database, like the tests.
 *   php tests/orders/demo_seed.php
 * Then sign in as ada@adashair.test with the test password (TestPass1).
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();

const ADA = 20, BIZ = 2;
$db    = get_db();
$today = planner_today();
$day   = fn(int $n) => planner_add_days($today, $n);

// The shop: renamed since sign-up, so its papers still start with the old prefix
$db->exec("UPDATE businesses SET name = 'RN Afriluxe', doc_prefix = 'CAS' WHERE id = 2");
$db->exec("INSERT INTO products (id, business_id, name, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service, is_active) VALUES
    (310, 2, 'Kaftan XL Black', 50000, 30000, 6, 2, 'piece', 0, 1),
    (311, 2, 'Kaftan XL Grey', 50000, 30000, 4, 2, 'piece', 0, 1),
    (312, 2, 'Agbada Navy', 80000, 50000, 2, 1, 'piece', 0, 1),
    (313, 2, 'British wool', 12000, 8000, 40, 10, 'yard', 0, 1),
    (314, 2, 'Tailoring', 25000, 0, 0, 0, 'job', 1, 1)");
$db->exec("INSERT INTO customers (id, business_id, full_name, phone, whatsapp, email, address) VALUES
    (210, 2, 'Tom-Blake Asaah', '08031234567', '2348031234567', 'tom@example.test', '14 Admiralty Way, Lekki'),
    (211, 2, 'Oluwa Jacob', '08039876543', NULL, NULL, NULL),
    (212, 2, 'Mama Bisi', '08035551234', NULL, NULL, 'Shop 12, Balogun Market'),
    (213, 2, 'Chidi Okafor', '08036667777', NULL, 'chidi@example.test', NULL),
    (214, 2, 'Amaka Nwosu', '08034445555', NULL, 'amaka@example.test', NULL)");

$made = [];
$make = function (string $label, array $in) use ($db, &$made): array {
    $order = order_create($db, BIZ, $in + ['channel' => 'manual', 'fulfillment' => 'pickup', 'method' => 'cash', 'paid_now' => 0], ADA);
    $made[$label] = $order;
    return $order;
};
$item = fn(int $id, float $qty = 1, ?float $price = null) => ['product_id' => $id, 'name' => '', 'quantity' => $qty,
    'unit_price' => $price ?? (float)q("SELECT sell_price FROM products WHERE id = ?", [$id])];

// 1. A deposit paid, for delivery tomorrow (the screenshot Tom sent)
$make('deposit, delivery tomorrow', ['customer_id' => 210, 'customer_name' => 'Tom-Blake Asaah', 'customer_phone' => '08031234567',
    'items' => [$item(310), $item(311) + ['note' => 'This in green, if you have it']], 'fulfillment' => 'delivery',
    'deliver_by' => $day(1), 'delivery_address' => '14 Admiralty Way, Lekki', 'paid_now' => 30000, 'method' => 'bank_transfer',
    'reference' => 'GTB-88213']);

// 2. Pay later, ready for pickup
$o = $make('pay later, ready', ['customer_id' => 211, 'customer_name' => 'Oluwa Jacob', 'customer_phone' => '08039876543',
    'items' => [$item(313, 10) + ['note' => 'For Melissa\'s wedding']], 'deliver_by' => $day(2), 'pay_by' => $day(5)]);
order_set_stage($db, BIZ, (int)$o['id'], 'ready', ADA);

// 3. A quick sale at the counter
$make('quick sale', ['channel' => 'quick', 'items' => [$item(310)], 'fulfillment' => 'now', 'paid_now' => 50000]);

// 4. Delivered, with a balance still owed (Mama Bisi: 15,000)
$o = $make('delivered, balance', ['customer_id' => 212, 'customer_name' => 'Mama Bisi', 'customer_phone' => '08035551234',
    'items' => [$item(314, 1, 45000) + ['name' => 'Aso-oke, sewn']], 'fulfillment' => 'delivery', 'deliver_by' => $day(-2),
    'delivery_address' => 'Shop 12, Balogun Market', 'paid_now' => 30000, 'pay_by' => $day(-1)]);
order_set_stage($db, BIZ, (int)$o['id'], 'ready', ADA);
order_set_stage($db, BIZ, (int)$o['id'], 'done', ADA);

// 5. Paid in full, then changed: paid more than the new total
$o = $make('overpaid after a change', ['customer_id' => 213, 'customer_name' => 'Chidi Okafor', 'customer_phone' => '08036667777',
    'items' => [$item(312), $item(311)], 'deliver_by' => $day(3), 'paid_now' => 130000, 'method' => 'pos']);
order_update_lines($db, BIZ, (int)$o['id'], [$item(312)], 0, ADA, '₦');

// 6. Kept money: Amaka paid over, kept it, and has a new order to use it on
$o = $make('kept from', ['customer_id' => 214, 'customer_name' => 'Amaka Nwosu', 'customer_phone' => '08034445555',
    'items' => [$item(310), $item(311)], 'paid_now' => 100000, 'method' => 'bank_transfer', 'reference' => 'ZEN-1101']);
order_update_lines($db, BIZ, (int)$o['id'], [$item(310), $item(313, 2)], 0, ADA, '₦');
order_keep_overpaid($db, BIZ, (int)$o['id'], ADA, '₦');
$make('kept money to use', ['customer_id' => 214, 'customer_name' => 'Amaka Nwosu', 'customer_phone' => '08034445555',
    'items' => [$item(311)], 'deliver_by' => $day(4)]);

// 7. A refund under way: the customer sent their account details
$o = $make('refund, details in', ['customer_id' => 200, 'customer_name' => 'Ngozi Eze', 'customer_phone' => '08031110000',
    'items' => [$item(312)], 'deliver_by' => $day(6), 'paid_now' => 80000, 'method' => 'bank_transfer', 'reference' => 'OPAY-7781']);
order_update_lines($db, BIZ, (int)$o['id'], [$item(310)], 0, ADA, '₦');
$refund = refund_ask($db, BIZ, (int)$o['id'], 30000, 'Changed to the kaftan', ADA);
refund_save_details($db, $refund['token'], ['payee_kind' => 'bank', 'payee_name' => 'Ngozi Eze', 'payee_bank' => 'GTBank', 'payee_number' => '0123456789']);

// 8. Cancelled before anything was paid
$o = $make('cancelled', ['customer_id' => 201, 'customer_name' => 'Femi & Sons', 'customer_phone' => '08031110001',
    'items' => [$item(313, 5)], 'deliver_by' => $day(2)]);
order_cancel($db, BIZ, (int)$o['id'], ADA, 'Customer changed their mind');

// 9. Made from the quote the customer accepted
$make('from a quote', ['channel' => 'quote', 'quotation_id' => 504, 'customer_id' => 200, 'customer_name' => 'Ngozi Eze',
    'customer_phone' => '08031110000', 'items' => [['name' => 'Bridal styling', 'quantity' => 1, 'unit_price' => 250000]],
    'deliver_by' => $day(10)]);
$db->exec("UPDATE quotations SET status = 'converted' WHERE id = 504");

// The fixture's shop orders (ORD-0700 waiting to be checked, ORD-0701 from WhatsApp) stay as they are: new.

echo "Signed in as ada@adashair.test / " . T_PASSWORD . " (RN Afriluxe, prefix CAS)\n";
foreach ($made as $label => $o) {
    echo str_pad($label, 26) . ' ' . $o['order_number'] . '  orders/view/' . $o['id'] . "\n";
}
echo "Shop orders waiting: orders/view/700 (proof to check), orders/view/701 (WhatsApp)\n";
