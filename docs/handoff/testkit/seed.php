<?php
// Demo data for landing-page screenshots. Local database only.
$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$appdir = $argv[1];

$ym  = date('Y-m');
$day = fn(int $d) => sprintf('%s-%02d', $ym, $d);
$monthsAgo = fn(int $m, int $d) => date('Y-m-', strtotime("first day of -$m month")) . sprintf('%02d', $d);
$tok = fn() => bin2hex(random_bytes(32));
$pw  = password_hash('DemoPass1', PASSWORD_BCRYPT);

foreach (['tasks','planner_dismissals','planner_sends','push_subscriptions','notifications','quotation_items','quotation_activities','quotations','order_items','orders','receipts','payments','invoice_items','invoices','debts','expenses','products','customers','subscriptions','users','bank_accounts','deliveries','document_counters','businesses','countries'] as $t) {
    $db->exec("DELETE FROM `$t`");
}

$db->exec("INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, monthly_price, extra_seat_price, trial_days, is_active)
           VALUES (1, 'Nigeria', 'NG', '🇳🇬', 'Nigerian Naira', 'NGN', '₦', 'paystack', 10000, 5000, 14, 1)");

$biz = $db->prepare("INSERT INTO businesses (id, name, slug, tagline, category, business_type, doc_prefix, logo_path, primary_color, secondary_color, font_pairing, address, phone, whatsapp, email, email_verified_at, currency_symbol, country_id, default_tax_rate, onboarding_complete, onboarding_step, is_active, landing_enabled)
                     VALUES (?,?,?,?,?,?,?,?,?,?,'modern_sans',?,?,?,?,NOW(),'₦',1,?,1,5,1,1)");
$biz->execute([1, 'Ivie Luxe Hair', 'ivie-luxe-hair', 'Luxury hair, honest prices', 'fashion', 'product', 'IVL', 'uploads/logos/1/logo.png', '#7A1F4B', '#C9A24A',
               'Shop 21, Lekki Town Square, Lekki Phase 1, Lagos', '0803 555 0142', '2348035550142', 'hello@ivieluxehair.ng', 0]);
$biz->execute([2, 'Kemi Events & Décor', 'kemi-events', 'Events that people remember', 'services', 'service', 'KED', 'uploads/logos/2/logo.png', '#0F5C4A', '#C9A24A',
               '14 Adeniyi Jones Avenue, Ikeja, Lagos', '0812 555 0190', '2348125550190', 'bookings@kemievents.ng', 7.5]);

$db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?,?,?,?,?,?,1,1)")
   ->execute([1, 1, 'owner', 'Ivie Okoro', 'ivie@demo.nfelia', $pw]);
$db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?,?,?,?,?,?,1,1)")
   ->execute([2, 2, 'owner', 'Kemi Adebayo', 'kemi@demo.nfelia', $pw]);

foreach ([1, 2] as $b) {
    $db->prepare("INSERT INTO subscriptions (business_id, country_id, payment_method, plan, status, user_seats, extra_seats, monthly_amount, current_period_start, current_period_end, next_billing_date)
                  VALUES (?, 1, 'paystack', 'standard', 'active', 1, 0, 10000, ?, ?, ?)")
       ->execute([$b, $day(1), date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+20 days'))]);
}

// ---------- Ivie Luxe Hair ----------
$cust = $db->prepare("INSERT INTO customers (id, business_id, full_name, business_name, phone, whatsapp) VALUES (?,?,?,?,?,?)");
$ivieCustomers = [
    [1, 'Mrs Folake Ade', null, '0802 555 0111'],
    [2, 'Chioma Obi', "Chioma's Hair Studio", '0806 555 0122'],
    [3, 'Adaeze Nwosu', null, '0809 555 0133'],
    [4, 'Zainab Musa', null, '0813 555 0144'],
    [5, 'Temi Adeyemi', null, '0817 555 0155'],
    [6, 'Blessing Etim', null, '0705 555 0166'],
    [7, 'Funmi Bakare', null, '0708 555 0177'],
    [8, 'Ngozi Eze', null, '0901 555 0188'],
    [9, 'Lara Coker', null, '0903 555 0199'],
];
foreach ($ivieCustomers as [$id, $name, $bn, $phone]) {
    $cust->execute([$id, 1, $name, $bn, $phone, '234' . ltrim(str_replace(' ', '', $phone), '0')]);
}

$prod = $db->prepare("INSERT INTO products (id, business_id, sku, name, category, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
$ivieProducts = [
    [1, 'BST-22', 'Bone Straight 22" (3 bundles)', 'Bundles', 185000, 120000, 6, 3, 'set', 0],
    [2, 'BDW-18', 'Body Wave 18" bundle', 'Bundles', 65000, 40000, 14, 5, 'bundle', 0],
    [3, 'KCF-20', 'Kinky Curly Frontal Wig 20"', 'Wigs', 145000, 90000, 2, 3, 'wig', 0],
    [4, 'PXC-12', 'Pixie Curls 12"', 'Wigs', 48000, 28000, 9, 4, 'wig', 0],
    [5, 'HDC-55', 'HD Lace Closure 5x5', 'Closures', 55000, 32000, 1, 4, 'piece', 0],
    [6, 'SRV-RV', 'Wig revamp & styling', 'Services', 15000, 0, 0, 0, 'job', 1],
    [7, 'EDG-KT', 'Edge control & hair care kit', 'Care', 12000, 6000, 3, 5, 'kit', 0],
    [8, 'DPW-26', 'Deep Wave 26" (2 bundles)', 'Bundles', 210000, 140000, 4, 2, 'set', 0],
];
foreach ($ivieProducts as $p) { $prod->execute([$p[0], 1, ...array_slice($p, 1)]); }
$price = array_column($ivieProducts, 4, 0);
$pname = array_column($ivieProducts, 2, 0);

$inv  = $db->prepare("INSERT INTO invoices (id, business_id, customer_id, bank_account_id, user_id, doc_number, status, issue_date, due_date, subtotal, tax_rate, tax_amount, total, amount_paid, balance_due, payment_terms, notes, token, created_at)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$item = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, subtotal, sort_order) VALUES (?,?,?,?,?,?,?)");
$pay  = $db->prepare("INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference) VALUES (?,?,?,?,?,?,?)");
$rec  = $db->prepare("INSERT INTO receipts (business_id, invoice_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, token) VALUES (?,?,?,?,?,?,?,?,?,?)");
$debt = $db->prepare("INSERT INTO debts (business_id, customer_id, user_id, type, source, invoice_id, description, total_amount, amount_settled, balance, due_date, status, created_at)
                      VALUES (?,?,?,'receivable','invoice',?,?,?,?,?,?,?,?)");

$db->exec("INSERT INTO bank_accounts (id, business_id, bank_name, account_number, account_name, is_default, is_active) VALUES
           (1, 1, 'Moniepoint MFB', '6012345789', 'Ivie Luxe Hair', 1, 1),
           (2, 2, 'GTBank', '0571234098', 'Kemi Events & Decor Ltd', 1, 1)");

$recNo = 300;
// [id, inv no, customer, issue day, due day, [[product, qty]], paid, method]
$ivieInvoices = [
    [1, 131, 1, 2,  30, [[8, 1]], 175000, 'bank_transfer'],
    [2, 133, 2, 5,  12, [[2, 2], [5, 1]], 125000, 'bank_transfer'],
    [3, 134, 3, 8,  31, [[1, 1]], 145000, 'pos'],
    [4, 135, 4, 10, 10, [[3, 1]], 145000, 'bank_transfer'],
    [5, 136, 5, 12, 14, [[2, 1]], 35000, 'cash'],
    [6, 137, 6, 14, 28, [[4, 1]], 28000, 'bank_transfer'],
    [7, 138, 7, 15, 15, [[4, 1], [7, 2]], 72000, 'bank_transfer'],
    [8, 139, 8, 16, 16, [[6, 1], [7, 1]], 27000, 'cash'],
    [9, 140, 9, 17, 17, [[8, 1]], 210000, 'bank_transfer'],
];
foreach ($ivieInvoices as [$id, $no, $cid, $iss, $due, $lines, $paid, $method]) {
    $total = 0;
    foreach ($lines as [$pid, $q]) { $total += $price[$pid] * $q; }
    $bal = $total - $paid;
    $status = $bal <= 0 ? 'paid' : (strtotime($day($due)) < strtotime(date('Y-m-d')) ? 'overdue' : 'partially_paid');
    $inv->execute([$id, 1, $cid, 1, 1, "IVL-INV-$no", $status, $day($iss), $day(min($due, 31)), $total, 0, 0, $total, $paid, $bal,
                   'Balance due by the date above', null, $tok(), $day($iss) . ' 1' . ($iss % 9) . ':2' . ($iss % 6) . ':00']);
    $i = 0;
    foreach ($lines as [$pid, $q]) { $item->execute([$id, $pid, $pname[$pid], $q, $price[$pid], $price[$pid] * $q, $i++]); }
    $pay->execute([1, $id, 1, $paid, $day($iss), $method, null]);
    $rec->execute([1, $id, $cid, 1, 'IVL-REC-' . (++$recNo), $day($iss), $paid, max(0, $bal), $method, $tok()]);
    if ($bal > 0) {
        $debt->execute([1, $cid, 1, $id, "Balance on IVL-INV-$no", $total, $paid, $bal, $day(min($due, 31)), 'settling', $day($iss) . ' 12:00:00']);
    }
}

// Earlier months: sales (as receipts) and spending, for the six-month trend.
$exp = $db->prepare("INSERT INTO expenses (business_id, doc_number, category, description, amount, expense_date, payment_method, payment_status, amount_paid, balance_due) VALUES (?,?,?,?,?,?,?,'paid',?,0)");
$expNo = 0;
$history = [5 => [610000, 480000], 4 => [720000, 530000], 3 => [680000, 560000], 2 => [840000, 600000], 1 => [905000, 640000]];
foreach ($history as $m => [$rev, $spend]) {
    foreach ([0.42, 0.33, 0.25] as $k => $share) {
        $rec->execute([1, null, 1 + (($m + $k) % 9), 1, 'IVL-REC-' . (++$recNo), $monthsAgo($m, 6 + $k * 9), round($rev * $share, -3), 0, 'bank_transfer', $tok()]);
    }
    foreach ([['Stock', 'Stock from supplier', 0.6], ['Rent', 'Shop rent', 0.23], ['Salary', 'Sales assistant salary', 0.1], ['Utilities', 'Generator fuel', 0.07]] as [$cat, $desc, $share]) {
        $exp->execute([1, 'IVL-EXP-' . str_pad((string)(++$expNo), 3, '0', STR_PAD_LEFT), $cat, $desc, round($spend * $share, -3), $monthsAgo($m, 4 + $expNo % 20), 'bank_transfer', round($spend * $share, -3)]);
    }
}
foreach ([['Stock', 'Bundles from supplier (Trade Fair)', 420000, 3], ['Rent', 'Shop rent (October)', 150000, 1], ['Salary', 'Sales assistant salary', 60000, 15],
          ['Utilities', 'Generator fuel', 24000, 9], ['Packaging', 'Branded boxes and bags', 15000, 6], ['Transport', 'Dispatch riders', 18500, 13], ['Utilities', 'Data and airtime', 8000, 11]] as [$cat, $desc, $amt, $d]) {
    $exp->execute([1, 'IVL-EXP-' . str_pad((string)(++$expNo), 3, '0', STR_PAD_LEFT), $cat, $desc, $amt, $day($d), 'bank_transfer', $amt]);
}

$db->prepare("INSERT INTO deliveries (business_id, invoice_id, customer_id, doc_number, token, signoff_token, status, recipient_name, recipient_phone, delivery_address, provider, rider_name)
              VALUES (1, 9, 9, 'IVL-DEL-041', ?, ?, 'dispatched', 'Lara Coker', '0903 555 0199', '7 Admiralty Way, Lekki Phase 1', 'Kwik', 'Sunday')")
   ->execute([$tok(), $tok()]);

$db->exec("INSERT INTO document_counters (business_id, doc_type, last_number) VALUES (1,'INV',140),(1,'REC',$recNo),(1,'DEL',41),(2,'INV',27),(2,'QUO',19),(2,'REC',12)");

// ---------- Kemi Events & Décor ----------
$cust->execute([20, 2, 'Ngozi Okafor', 'Okafor & Partners Ltd', '0802 555 0300', '2348025550300']);
$kemiLines = [
    ['Venue decoration, gold and white theme (120 guests)', 1, 550000],
    ['Floral centrepieces', 12, 20000],
    ['Chiavari chairs and table linen (120 guests)', 1, 180000],
    ['Backdrop and stage lighting', 1, 110000],
    ['Event coordination and on-the-day team', 1, 120000],
];
$sub = array_sum(array_map(fn($l) => $l[1] * $l[2], $kemiLines));
$tax = round($sub * 0.075, 2);
$total = $sub + $tax;
$paid = $total / 2;
$kemiToken = str_repeat('k', 4) . substr($tok(), 4);
$inv->execute([20, 2, 20, 2, 2, 'KED-INV-027', 'partially_paid', $day(9), date('Y-12-05'), $sub, 7.5, $tax, $total, $paid, $total - $paid,
               '50% deposit to confirm the booking. Balance 7 days before the event.',
               'End-of-year staff party, Friday 12 December. Grand Ballroom, Victoria Island.', $kemiToken, $day(9) . ' 10:15:00']);
foreach ($kemiLines as $i => [$desc, $q, $unit]) { $item->execute([20, null, $desc, $q, $unit, $q * $unit, $i]); }
$pay->execute([2, 20, 2, $paid, $day(10), 'bank_transfer', 'OKP/TRF/2291']);
$rec->execute([2, 20, 20, 2, 'KED-REC-012', $day(10), $paid, $total - $paid, 'bank_transfer', $tok()]);
$debt->execute([2, 20, 2, 20, 'Balance on KED-INV-027', $total, $paid, $total - $paid, date('Y-12-05'), 'settling', $day(10) . ' 09:00:00']);

file_put_contents("$appdir/../kemi_token.txt", $kemiToken);
echo "seeded. Kemi invoice token saved.\n";
