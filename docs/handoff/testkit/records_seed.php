<?php
/**
 * A year and a half of a busy shop, for looking at a Records Book and taking
 * its pictures for the home page (docs/handoff/testkit/shoot_records.js).
 * Local or test database only: it adds business 50, Ivie Luxe Hair, and
 * everything she did, and replaces her if she is already there.
 *
 *   php docs/handoff/testkit/records_seed.php
 *
 * The numbers come from a fixed seed, so every run gives the same shop.
 */
if (PHP_SAPI !== 'cli') { exit; }
require_once __DIR__ . '/../../../tests/bootstrap.php';
if (!in_array(APP_ENV, ['local', 'testing'], true)) {
    fwrite(STDERR, "Refusing: APP_ENV must be local or testing.\n");
    exit(2);
}

mt_srand(2026);
$db  = get_db();
$BIZ = 50;
$OWNER = 500;
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$tok  = fn(int $n = 64) => bin2hex(random_bytes(intdiv($n, 2)));
$day  = fn(int $ago) => date('Y-m-d', strtotime("-{$ago} days"));
$at   = fn(int $ago, int $h = 10) => date('Y-m-d', strtotime("-{$ago} days")) . sprintf(' %02d:%02d:00', $h, mt_rand(0, 59));

// Start clean for this business
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['records_books', 'record_requests', 'tasks', 'reviews', 'bookings', 'order_events', 'refunds', 'kept_money', 'document_counters', 'order_items', 'orders',
          'debt_settlements', 'debts', 'payments',
          'receipt_items', 'receipts', 'invoice_items', 'invoices', 'quotation_items', 'quotations', 'delivery_items', 'deliveries', 'expense_items',
          'expenses', 'stock_movements', 'products', 'customers', 'suppliers', 'bank_accounts', 'subscriptions', 'users', 'shop_posts', 'quote_requests'] as $t) {
    $col = in_array($t, ['order_items', 'invoice_items', 'receipt_items', 'quotation_items', 'delivery_items', 'expense_items'], true) ? null : 'business_id';
    if ($col) $db->exec("DELETE FROM `{$t}` WHERE business_id = {$BIZ}");
}
$db->exec("DELETE FROM businesses WHERE id = {$BIZ}");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
$db->exec("INSERT IGNORE INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, monthly_price, extra_seat_price, book_price, trial_days, is_active)
           VALUES (1, 'Nigeria', 'NG', '🇳🇬', 'Nigerian Naira', 'NGN', '₦', 'paystack', 10000, 5000, 7500, 14, 1)");

// Her logo: an IL monogram, gold on plum
$dir = BASE_PATH . '/uploads/logos/' . $BIZ;
@mkdir($dir, 0755, true);
$img = imagecreatetruecolor(400, 400);
imagesavealpha($img, true);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagefilledellipse($img, 200, 200, 392, 392, imagecolorallocate($img, 0x7A, 0x1F, 0x4B));
imageellipse($img, 200, 200, 350, 350, imagecolorallocate($img, 0xC9, 0xA2, 0x4A));
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
if (is_file($font)) {
    imagettftext($img, 120, 0, 92, 255, imagecolorallocate($img, 0xC9, 0xA2, 0x4A), $font, 'IL');
}
imagepng($img, $dir . '/logo.png');

$db->prepare("INSERT INTO businesses (id, name, slug, subdomain, subdomain_status, storefront_look, tagline, category, business_type, doc_prefix, logo_path, primary_color, secondary_color,
                 font_pairing, address, phone, whatsapp, email, email_verified_at, about_text, social_instagram, social_tiktok, currency_symbol, country_id,
                 onboarding_complete, onboarding_step, is_active, landing_enabled, created_at)
              VALUES (?, 'Ivie Luxe Hair', 'ivie-luxe-hair', 'ivieluxe', 'active', 'atelier', 'Luxury hair, honest prices', 'fashion', 'product', 'IVL', ?, '#7A1F4B', '#C9A24A',
                 'elegant_serif', 'Shop 21, Lekki Town Square, Lekki Phase 1, Lagos', '08035550142', '2348035550142', 'hello@ivieluxehair.ng', NOW(),
                 'Ivie Luxe Hair sells bundles, wigs and closures in Lekki, and installs them too. Ivie started from her room in 2019 and moved into the shop in 2023. Every bundle is checked by hand before it goes out.',
                 'ivieluxehair', 'ivieluxe', '₦', 1, 1, 5, 1, 1, ?)")
   ->execute([$BIZ, 'uploads/logos/' . $BIZ . '/logo.png', $at(540, 9)]);
$pw = password_hash('DemoPass12', PASSWORD_BCRYPT, ['cost' => 4]);
$user = $db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, phone, is_email_verified, is_active, created_at, last_login_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
$user->execute([$OWNER, $BIZ, 'owner', 'Ivie Okoro', 'ivie@ivieluxe.test', $pw, '08035550142', 1, $at(540), $at(0, 8)]);
$user->execute([$OWNER + 1, $BIZ, 'staff', 'Tobi Ade', 'tobi@ivieluxe.test', $pw, '08035550143', 1, $at(300), $at(1, 17)]);
$user->execute([$OWNER + 2, $BIZ, 'staff', 'Grace Eze', 'grace@ivieluxe.test', $pw, '08035550144', 0, $at(400), $at(90, 12)]);
$db->prepare("INSERT INTO subscriptions (business_id, country_id, payment_method, plan, status, user_seats, extra_seats, monthly_amount, current_period_start, current_period_end)
              VALUES (?, 1, 'paystack', 'standard', 'active', 1, 1, 15000, ?, ?)")->execute([$BIZ, $day(10), date('Y-m-d', strtotime('+20 days'))]);
$db->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_number, account_name, is_default) VALUES (?, 'Moniepoint', '6012345678', 'Ivie Luxe Hair', 1)")->execute([$BIZ]);

// People
$first = ['Folake', 'Chioma', 'Adaeze', 'Zainab', 'Temi', 'Blessing', 'Funmi', 'Ngozi', 'Lara', 'Amaka', 'Bisola', 'Kemi', 'Halima', 'Uche', 'Tosin', 'Ifeoma', 'Yetunde', 'Nkechi', 'Damilola', 'Aisha'];
$last  = ['Ade', 'Obi', 'Nwosu', 'Musa', 'Adeyemi', 'Etim', 'Bakare', 'Eze', 'Coker', 'Okafor', 'Bello', 'Lawal', 'Ibrahim', 'Okeke', 'Afolabi'];
$customers = [];
$cust = $db->prepare("INSERT INTO customers (business_id, full_name, business_name, phone, whatsapp, email, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
for ($i = 0; $i < 44; $i++) {
    $name = $first[$i % count($first)] . ' ' . $last[($i * 7) % count($last)];
    $phone = '080' . mt_rand(30000000, 39999999);
    $cust->execute([$BIZ, $name, $i % 9 === 0 ? $first[$i % count($first)] . '\'s Salon' : null, $phone, '234' . substr($phone, 1),
                    $i % 3 === 0 ? strtolower(str_replace(' ', '.', $name)) . '@example.test' : null, $pick(['Lekki Phase 1', 'Ajah', 'Ikoyi', 'Yaba', 'Surulere', 'Victoria Island']) . ', Lagos',
                    $at(540 - $i * 11)]);
    $customers[] = (int)$db->lastInsertId();
}
$sup = $db->prepare("INSERT INTO suppliers (business_id, full_name, business_name, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?)");
$suppliers = [];
foreach ([['Mr Chen', 'Guangzhou Hair Imports'], ['Bose Hassan', 'Balogun Wholesale'], ['Femi Ojo', 'LagosLogistics'], ['Mrs Okon', 'Okon Packaging']] as $i => [$n, $c]) {
    $sup->execute([$BIZ, $n, $c, '0809' . mt_rand(1000000, 9999999), $pick(['Guangzhou, China', 'Balogun Market, Lagos', 'Ikeja, Lagos']), $at(500 - $i * 30)]);
    $suppliers[] = (int)$db->lastInsertId();
}

// Stock
$products = [];
$prod = $db->prepare("INSERT INTO products (business_id, sku, name, category, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service, booking_mode, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ([
    ['BST-22', 'Bone Straight 22" (3 bundles)', 'Bundles', 185000, 120000, 6, 3, 'set'], ['BST-18', 'Bone Straight 18" (3 bundles)', 'Bundles', 145000, 92000, 9, 3, 'set'],
    ['BDW-18', 'Body Wave 18" bundle', 'Bundles', 65000, 40000, 14, 5, 'bundle'], ['BDW-24', 'Body Wave 24" bundle', 'Bundles', 85000, 54000, 7, 4, 'bundle'],
    ['DPW-20', 'Deep Wave 20" bundle', 'Bundles', 72000, 45000, 11, 4, 'bundle'], ['KCF-20', 'Kinky Curly Frontal Wig 20"', 'Wigs', 145000, 90000, 2, 3, 'wig'],
    ['PXC-12', 'Pixie Curls 12"', 'Wigs', 48000, 28000, 9, 4, 'wig'], ['BOB-10', 'Bob Wig 10"', 'Wigs', 55000, 31000, 12, 4, 'wig'],
    ['HDC-55', 'HD Lace Closure 5x5', 'Closures', 55000, 32000, 1, 4, 'piece'], ['HDF-13', 'HD Lace Frontal 13x4', 'Closures', 78000, 46000, 5, 3, 'piece'],
    ['GLU-01', 'Lace glue (Bold Hold)', 'Care', 9500, 5200, 30, 10, 'bottle'], ['SHM-01', 'Silk shampoo 500ml', 'Care', 12000, 6500, 18, 8, 'bottle'],
] as $i => [$sku, $name, $cat, $price, $cost, $stock, $low, $unit]) {
    $prod->execute([$BIZ, $sku, $name, $cat, $price, $cost, $stock, $low, $unit, 0, 'none', $at(530 - $i)]);
    $products[] = ['id' => (int)$db->lastInsertId(), 'name' => $name, 'price' => $price, 'cost' => $cost];
}
foreach ([['Wig install', 25000, 'slot'], ['Wig revamp and styling', 35000, 'slot'], ['Bridal hair (on the day)', 120000, 'day']] as $i => [$name, $price, $mode]) {
    $prod->execute([$BIZ, null, $name, 'Services', $price, 0, 0, 0, null, 1, $mode, $at(520 - $i)]);
    $products[] = ['id' => (int)$db->lastInsertId(), 'name' => $name, 'price' => $price, 'cost' => 0, 'service' => true];
}

// Selling. Every sale is an order (docs/ORDERS_HUB.md): typed in, from a
// quote, a quick sale at the counter, or from the shop and WhatsApp. Once
// confirmed it has its invoice; each payment has its receipt, a delivery its
// delivery note, and a balance left is a debt, as the app keeps them.
$counter = ['QUO' => 0, 'ORD' => 0, 'INV' => 0, 'REC' => 0, 'DEL' => 0, 'EXP' => 0, 'RFD' => 0];
$num = function (string $t) use (&$counter) { return 'IVL-' . $t . '-' . str_pad((string)++$counter[$t], 3, '0', STR_PAD_LEFT); };
$db->prepare("INSERT INTO customers (business_id, full_name, notes, is_walk_in, created_at) VALUES (?, 'Walk-in customer', 'Quick sales to people who gave no name. Hidden from Clients.', 1, ?)")
   ->execute([$BIZ, $at(530)]);
$walk_in = ['id' => (int)$db->lastInsertId(), 'full_name' => 'Walk-in customer', 'phone' => null];
$cn = $db->query("SELECT id, full_name, phone FROM customers WHERE business_id = {$BIZ} AND is_walk_in = 0")->fetchAll();
$q   = $db->prepare("INSERT INTO quotations (business_id, customer_id, user_id, doc_number, status, issue_date, expiry_date, subtotal, total, token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$qi  = $db->prepare("INSERT INTO quotation_items (quotation_id, product_id, description, quantity, unit_price, subtotal, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
$ord = $db->prepare("INSERT INTO orders (business_id, customer_id, quotation_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
                        delivery_address, payment_method, currency_code, subtotal, total, status, reviewed_by, reviewed_at, created_by, created_at, done_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'NGN', ?, ?, ?, ?, ?, ?, ?, ?)");
$oi  = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total, variant_selection, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$inv = $db->prepare("INSERT INTO invoices (business_id, customer_id, user_id, order_id, quotation_id, doc_number, status, issue_date, due_date, subtotal, total, amount_paid, balance_due, token, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$ii  = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, subtotal, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
$pay = $db->prepare("INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$rec = $db->prepare("INSERT INTO receipts (business_id, invoice_id, payment_id, order_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$del = $db->prepare("INSERT INTO deliveries (business_id, invoice_id, order_id, customer_id, doc_number, token, signoff_token, status, recipient_name, recipient_phone, delivery_address,
                        provider, rider_name, dispatch_date, actual_delivery, signoff_name, signoff_timestamp, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$di  = $db->prepare("INSERT INTO delivery_items (delivery_id, description, quantity, unit, sort_order) VALUES (?, ?, ?, ?, ?)");
$owe = $db->prepare("INSERT INTO debts (business_id, customer_id, user_id, type, source, invoice_id, order_id, description, counterparty_name, total_amount, amount_settled, balance, due_date, status, created_at)
                     VALUES (?, ?, ?, 'receivable', 'invoice', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$kept = $db->prepare("INSERT INTO kept_money (business_id, customer_id, order_id, payment_id, amount, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$methods = ['bank_transfer', 'bank_transfer', 'pos', 'cash', 'bank_transfer'];

/**
 * One sale, the way the orders hub keeps it. $c the customer, $lines
 * [[product, qty]], $channel manual | quote | quick | cart_pop | whatsapp,
 * $ago the day it was placed, $paid [[amount, days ago, method?, note?]] (a
 * 'kept' method moves kept money; minus is money going back), $stage new |
 * confirmed | ready | done | turned_down. Returns its ids and numbers.
 */
$sell = function (array $c, array $lines, string $channel, int $ago, array $paid, string $stage, bool $delivery, ?int $quote_id = null)
        use ($db, $BIZ, $OWNER, $num, $pick, $tok, $day, $at, $ord, $oi, $inv, $ii, $pay, $rec, $del, $di, $owe, $methods): array {
    $total = array_sum(array_map(fn($l) => $l[0]['price'] * $l[1], $lines));
    $shop  = in_array($channel, ['cart_pop', 'whatsapp'], true);
    $by    = $shop ? null : $pick([$OWNER, $OWNER, $OWNER + 1]);
    $status = ['new' => $pick(['pending', 'pending_verification']), 'confirmed' => 'approved', 'ready' => 'ready', 'done' => 'fulfilled', 'turned_down' => 'rejected'][$stage];
    $address = $delivery ? $pick(['Lekki Phase 1', 'Ajah', 'Ikoyi', 'Yaba', 'Surulere']) . ', Lagos' : null;
    $number = $num('ORD');
    $ord->execute([$BIZ, $c['id'], $quote_id, $number, bin2hex(random_bytes(16)), $channel, $delivery ? 'delivery' : 'pickup', $c['full_name'], $c['phone'], $address,
                   $shop ? $pick(['bank_transfer', 'none']) : 'none', $total, $total, $status, in_array($stage, ['new', 'turned_down'], true) ? null : $OWNER,
                   in_array($stage, ['new', 'turned_down'], true) ? null : $at($ago, 12), $by, $at($ago, 11), $stage === 'done' ? $at(max(0, $ago - ($delivery ? 2 : 0)), 16) : null]);
    $oid = (int)$db->lastInsertId();
    foreach ($lines as $s => [$p, $qty]) $oi->execute([$oid, $p['id'], $p['name'], $p['price'], $qty, $p['price'] * $qty, $shop ? $pick(['', 'Natural black', '1B', 'Brown']) : '', $s + 1]);
    $out = ['order' => $oid, 'number' => $number, 'invoice' => null, 'total' => $total];
    if (in_array($stage, ['new', 'turned_down'], true)) {
        return $out;
    }

    // Confirmed: the invoice, and what was paid on it, each with its receipt
    $got = array_sum(array_column($paid, 0));
    $due = $got >= $total ? $ago : max(0, $ago - 7);
    $inv->execute([$BIZ, $c['id'], $by ?? $OWNER, $oid, $quote_id, $num('INV'), $got >= $total ? 'paid' : ($got > 0 ? 'partially_paid' : ($due > 0 ? 'overdue' : 'sent')),
                   $day($ago), $day($due), $total, $total, $got, $total - $got, $tok(), $at($ago, 12)]);
    $iid = (int)$db->lastInsertId();
    $out['invoice'] = $iid;
    foreach ($lines as $s => [$p, $qty]) $ii->execute([$iid, $p['id'], $p['name'], $qty, $p['price'], $p['price'] * $qty, $s + 1]);
    $left = $total;
    $first_receipt = null;
    foreach ($paid as $k => $row) {
        [$amount, $when] = $row;
        $m = $row[2] ?? ($shop ? 'bank_transfer' : ($channel === 'quick' ? $pick(['cash', 'pos', 'bank_transfer']) : $pick($methods)));
        $pay->execute([$BIZ, $iid, $by ?? $OWNER, $amount, $day($when), $m, $m === 'bank_transfer' ? 'TRF' . mt_rand(100000, 999999) : null, $row[3] ?? null, $at($when, 15)]);
        $pid = (int)$db->lastInsertId();
        $out['payments'][$k] = $pid;
        $left -= $amount;
        if ($amount > 0) {
            $rec->execute([$BIZ, $iid, $pid, $oid, $c['id'], $by ?? $OWNER, $num('REC'), $day($when), $amount, max(0, $left), $m, $row[3] ?? null, $tok(), $at($when, 15)]);
            $first_receipt ??= (int)$db->lastInsertId();
        }
    }

    // Its delivery note, from the day it was confirmed
    $did = null;
    if ($delivery) {
        $state = ['confirmed' => 'pending_dispatch', 'ready' => 'dispatched', 'done' => 'signed_off'][$stage];
        $del->execute([$BIZ, $iid, $oid, $c['id'], $num('DEL'), $tok(), $tok(), $state, $c['full_name'], $c['phone'], $address, $pick(['gig', 'kwik', 'private_rider']),
                       $pick(['Musa', 'Kunle', 'Emeka']), $state === 'pending_dispatch' ? null : $at(max(0, $ago - 1), 10),
                       $state === 'signed_off' ? $at(max(0, $ago - 2), 16) : null, $state === 'signed_off' ? $c['full_name'] : null,
                       $state === 'signed_off' ? $at(max(0, $ago - 2), 16) : null, $at($ago, 12)]);
        $did = (int)$db->lastInsertId();
        foreach ($lines as $s => [$p, $qty]) $di->execute([$did, $p['name'], $qty, 'pcs', $s + 1]);
    }
    $db->prepare("UPDATE orders SET invoice_id = ?, receipt_id = ?, delivery_id = ? WHERE id = ?")->execute([$iid, $first_receipt, $did, $oid]);

    // A balance left is owed, in Debts, until it is paid
    if ($got < $total) {
        $owe->execute([$BIZ, $c['id'], $OWNER, $iid, $oid, 'Balance on order ' . $number, $c['full_name'], $total, $got, $total - $got, $day($due),
                       $got > 0 ? 'settling' : 'active', $at($ago, 12)]);
    }
    return $out;
};

// Typed in and quick sales, every few days for a year and a half; some from a quote
for ($ago = 520; $ago >= 0; $ago -= mt_rand(1, 4)) {
    $season = 1 + 0.5 * sin($ago / 58);      // busier some months than others
    $c = $pick($cn);
    $lines = [];
    for ($k = 0, $n = mt_rand(1, 3); $k < $n; $k++) {
        $p = $products[mt_rand(0, count($products) - 1)];
        $lines[] = [$p, $p['service'] ?? false ? 1 : mt_rand(1, 2)];
    }
    $total = array_sum(array_map(fn($l) => $l[0]['price'] * $l[1], $lines));
    $kind = mt_rand(1, 10) <= 6 * $season / 1.5 ? 'order' : 'quick';
    $quote_id = null;
    if (mt_rand(1, 6) === 1) {
        $status = $ago > 30 ? $pick(['accepted', 'converted', 'declined', 'expired', 'converted']) : $pick(['sent', 'in_negotiation', 'accepted']);
        $q->execute([$BIZ, $c['id'], $OWNER, $num('QUO'), $status, $day($ago + 3), $day($ago - 4), $total, $total, $tok(), $at($ago + 3)]);
        $qid = (int)$db->lastInsertId();
        foreach ($lines as $s => [$p, $qty]) $qi->execute([$qid, $p['id'], $p['name'], $qty, $p['price'], $p['price'] * $qty, $s + 1]);
        if ($status === 'converted') {
            $kind = 'order';
            $quote_id = $qid;
        }
    }
    if ($kind === 'order') {
        // A deposit, then the rest; now and then nothing yet, or half
        $paid = $ago > 45 ? ($ago % 17 === 0 ? 0 : ($ago % 7 === 0 ? round($total / 2, -3) : $total)) : ($ago > 10 ? round($total * mt_rand(0, 10) / 10, -3) : 0);
        $deposit = $paid >= $total && mt_rand(1, 3) === 1 ? round($total * 0.3, -3) : 0;
        $rows = $paid <= 0 ? [] : ($deposit ? [[$deposit, $ago], [$paid - $deposit, max(0, $ago - mt_rand(2, 9))]] : [[$paid, max(0, $ago - mt_rand(0, 9))]]);
        $sell($c, $lines, $quote_id ? 'quote' : 'manual', $ago, $rows, $ago > 10 ? 'done' : $pick(['confirmed', 'ready', 'done']), mt_rand(1, 3) === 1, $quote_id);
    } else {
        $sell(mt_rand(1, 4) === 1 ? $walk_in : $c, $lines, 'quick', $ago, [[$total, $ago]], 'done', false);
    }
}

// From the shop and WhatsApp, paid by transfer when confirmed
for ($ago = 400; $ago >= 0; $ago -= mt_rand(2, 6)) {
    $c = $pick($cn);
    $p = $products[mt_rand(0, 9)];
    $line = [[$p, mt_rand(1, 2)]];
    $stage = $ago > 7 ? $pick(['done', 'done', 'done', 'turned_down']) : $pick(['new', 'confirmed', 'ready']);
    $o = $sell($c, $line, $pick(['cart_pop', 'cart_pop', 'whatsapp']), $ago, [[$p['price'] * $line[0][1], $ago]], $stage, mt_rand(1, 3) > 1);
    if ($stage === 'done' && mt_rand(1, 3) === 1) {
        $db->prepare("INSERT INTO reviews (business_id, order_id, customer_name, token, rating, comment, status, created_at, submitted_at) VALUES (?, ?, ?, ?, ?, ?, 'published', ?, ?)")
           ->execute([$BIZ, $o['order'], $c['full_name'], bin2hex(random_bytes(16)), $pick([5, 5, 5, 4, 4, 3]), $pick(['Beautiful hair, no shedding at all.', 'Delivery was fast and the wig fits well.',
                      'Lovely customer service. I will buy again.', 'Exactly like the pictures.', 'Good, but delivery took a day longer.']), $at($ago - 3), $at($ago - 3, 19)]);
    }
}

// A change midway: an order paid in full came down by ₦20,000, kept for the
// customer's next order, which used it (order_keep_overpaid(), order_use_kept())
$c = $cn[3];
$a = $sell($c, [[$products[0], 1]], 'manual', 38, [[$products[0]['price'] + 20000, 38, 'bank_transfer'], [-20000, 36, 'kept', 'Kept for their next order']], 'done', false);
$kept->execute([$BIZ, $c['id'], $a['order'], $a['payments'][1], 20000, 'Kept from order ' . $a['number'], $OWNER, $at(36, 14)]);
$b = $sell($c, [[$products[2], 1]], 'manual', 12, [[$products[2]['price'] - 20000, 12, 'cash'], [20000, 12, 'kept', 'Paid from money kept for them']], 'done', false);
$kept->execute([$BIZ, $c['id'], $b['order'], $b['payments'][1], -20000, 'Used on order ' . $b['number'], $OWNER, $at(12, 15)]);

// Money given back: a shorter bundle than paid for, ₦7,000 back by transfer,
// to the account the customer gave on the refund form (refund_record())
$c = $cn[8];
$r = $sell($c, [[$products[4], 1]], 'manual', 26, [[$products[4]['price'] + 7000, 26, 'bank_transfer'], [-7000, 24, 'bank_transfer', 'Refund: Changed to a shorter bundle']], 'done', false);
$db->prepare("UPDATE payments SET reference = 'TRF551028' WHERE id = ?")->execute([$r['payments'][1]]);
$db->prepare("INSERT INTO refunds (business_id, order_id, invoice_id, customer_id, doc_number, status, amount, method, reference, reason, token, asked_at, asked_email,
                 payee_kind, payee_name, payee_bank, payee_number, details_at, payment_id, paid_at, user_id, created_at)
              VALUES (?, ?, ?, ?, ?, 'paid', 7000, 'bank_transfer', 'TRF551028', 'Changed to a shorter bundle', ?, ?, 0, 'bank', ?, 'GTBank', '0123456789', ?, ?, ?, ?, ?)")
   ->execute([$BIZ, $r['order'], $r['invoice'], $c['id'], $num('RFD'), bin2hex(random_bytes(16)), $at(25, 10), $c['full_name'], $at(25, 13), $r['payments'][1], $at(24, 11), $OWNER, $at(25, 10)]);

// The counters carry on from the numbers used, as the generator would
foreach ($counter as $type => $n) {
    if ($n) $db->prepare("INSERT INTO document_counters (business_id, doc_type, last_number) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_number = VALUES(last_number)")->execute([$BIZ, $type, $n]);
}

// Spending
$exp = $db->prepare("INSERT INTO expenses (business_id, supplier_id, doc_number, category, description, amount, expense_date, payment_method, payment_status, amount_paid, balance_due, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
for ($ago = 520; $ago >= 0; $ago -= mt_rand(3, 9)) {
    [$cat, $what, $amt, $sup_id] = $pick([
        ['stock_purchase', 'Bundles restock from Guangzhou', mt_rand(25, 60) * 10000, $suppliers[0]], ['rent', 'Shop rent (part)', 150000, null],
        ['transport', 'Delivery riders this week', mt_rand(8, 20) * 1000, $suppliers[2]], ['marketing', 'Instagram ads', mt_rand(10, 40) * 1000, null],
        ['utilities', 'Prepaid electricity', mt_rand(15, 35) * 1000, null], ['office_supplies', 'Packaging and bags', mt_rand(5, 15) * 1000, $suppliers[3]],
        ['salary', 'Tobi\'s pay', 80000, null],
    ]);
    $paid = $cat === 'stock_purchase' && $ago < 60 && mt_rand(1, 2) === 1 ? round($amt / 2, -3) : $amt;
    $exp->execute([$BIZ, $sup_id, $num('EXP'), $cat, $what, $amt, $day($ago), $pick(['bank_transfer', 'cash', 'pos']), $paid >= $amt ? 'paid' : 'partial', $paid, $amt - $paid, $at($ago, 17)]);
}

// Debts, bookings, tasks, a post
$debt = $db->prepare("INSERT INTO debts (business_id, customer_id, user_id, type, source, description, counterparty_name, total_amount, amount_settled, balance, due_date, status, created_at)
                      VALUES (?, ?, ?, ?, 'manual', ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ([[45, 'receivable', 'Balance on bridal hair', 60000, 20000], [20, 'receivable', 'Wig on credit till month end', 48000, 0], [130, 'payable', 'Guangzhou order on credit', 400000, 400000]] as [$ago, $type, $what, $total, $settled]) {
    $c = $type === 'receivable' ? $pick($cn) : ['id' => null, 'full_name' => 'Mr Chen'];
    $debt->execute([$BIZ, $c['id'], $OWNER, $type, $what, $c['full_name'], $total, $settled, $total - $settled, $day($ago - 30), $settled >= $total ? 'cleared' : ($settled ? 'settling' : 'active'), $at($ago)]);
}
$book = $db->prepare("INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due,
                         customer_name, customer_phone, status, channel, created_at) VALUES (?, ?, ?, ?, ?, 'slot', ?, ?, 1, ?, ?, ?, ?, ?, ?, 'site', ?)");
for ($i = 1, $ago = 200; $ago >= -10; $i++, $ago -= mt_rand(4, 12)) {
    $p = $products[12 + mt_rand(0, 1)];
    $c = $pick($cn);
    $start = date('Y-m-d', strtotime(($ago >= 0 ? '-' : '+') . abs($ago) . ' days')) . ' ' . sprintf('%02d', mt_rand(9, 16)) . ':00:00';
    $book->execute([$BIZ, $p['id'], $p['name'], 'BK-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), bin2hex(random_bytes(16)), $start, date('Y-m-d H:i:s', strtotime($start . ' +2 hours')),
                    $p['price'], $p['price'], $p['price'] / 2, $c['full_name'], $c['phone'], $ago > 0 ? 'completed' : 'confirmed', $at(max(0, $ago + 5))]);
}
$task = $db->prepare("INSERT INTO tasks (business_id, kind, title, due_date, due_time, customer_id, created_by, done_at, done_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
for ($ago = 120; $ago >= -14; $ago -= mt_rand(2, 6)) {
    $kind = mt_rand(1, 3) === 1 ? 'appointment' : 'task';
    $title = $kind === 'appointment' ? 'Wig fitting with ' . $pick($first) : $pick(['Restock closures', 'Post new arrivals on Instagram', 'Call Guangzhou supplier', 'Pay shop rent', 'Send overdue reminders']);
    $due = date('Y-m-d', strtotime(($ago >= 0 ? '-' : '+') . abs($ago) . ' days'));
    $task->execute([$BIZ, $kind, $title, $due, $kind === 'appointment' ? sprintf('%02d:00:00', mt_rand(10, 16)) : null, $kind === 'appointment' ? $pick($customers) : null,
                    $OWNER, $ago > 0 ? $due . ' 18:00:00' : null, $ago > 0 ? $OWNER : null, $at(max(0, $ago + 2))]);
}
$db->prepare("INSERT INTO shop_posts (business_id, title, body, status, published_at, created_by, created_at) VALUES (?, ?, ?, 'published', ?, ?, ?)")
   ->execute([$BIZ, 'New: HD frontals in stock', 'Our HD lace frontals are back, in 13x4. Come and see them in the shop, or order on WhatsApp.', $at(30), $OWNER, $at(30)]);

// Customers' figures, worked out the way Nfelia does
require_once BASE_PATH . '/app/helpers/money.php';
foreach ($customers as $cid) customer_refresh_totals($db, $BIZ, $cid);

echo "Ivie Luxe Hair (business {$BIZ}) is ready: owner ivie@ivieluxe.test / DemoPass12\n";
foreach (['customers', 'products', 'quotations', 'orders', 'invoices', 'receipts', 'payments', 'refunds', 'kept_money', 'deliveries', 'expenses', 'bookings', 'tasks', 'reviews'] as $t) {
    echo '  ' . str_pad($t, 12) . $db->query("SELECT COUNT(*) FROM {$t} WHERE business_id = {$BIZ}")->fetchColumn() . "\n";
}
