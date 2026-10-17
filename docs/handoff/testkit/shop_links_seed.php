<?php
// Test data for shop_links_test.py. Local database only: it empties the
// tables it fills.
//   1 Nfelia HQ     founder, paying, no link yet
//   2 Bisi Indigo   fashion, on trial, two products on sale and one hidden,
//                   plus Tunde (staff)
//   3 Volt Repairs  trial ended (its shop shows "closed for now")
//   4 Kemi Foods    food, paying, one product
// Every password is testpass123.
$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pw = password_hash('testpass123', PASSWORD_BCRYPT);

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['order_items', 'orders', 'quote_requests', 'customers', 'notifications', 'sessions', 'subscriptions', 'products', 'users', 'businesses', 'countries'] as $t) {
    $db->exec("DELETE FROM `$t`");
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, monthly_price)
           VALUES (1, 'Nigeria', 'NG', '🇳🇬', 'Naira', 'NGN', '₦', 10000)");

// Bisi's tagline tries to end the page's script blocks
$db->exec("INSERT INTO businesses (id, name, slug, category, doc_prefix, phone, whatsapp, email, landing_enabled, currency_symbol, country_id, onboarding_complete, onboarding_step, email_verified_at, tagline) VALUES
    (1, 'Nfelia HQ', 'nfelia-hq', 'services', 'NFL', '08030000001', NULL, 'founder@nfelia.test', 1, '₦', 1, 1, 3, NOW(), NULL),
    (2, 'Bisi Indigo Fashion', 'bisi-indigo', 'fashion', 'BIF', '0803 123 4567', NULL, 'bisi@nfelia.test', 1, '₦', 1, 1, 3, NOW(), 'Adire & </script><script>alert(1)</script> fabrics'),
    (3, 'Volt Repairs', 'volt-repairs', 'tech_repair', 'VLT', '08091112222', '+234 (0)809 999 8888', 'volt@nfelia.test', 1, '₦', 1, 1, 3, NOW(), NULL),
    (4, 'Kemi Foods', 'kemi-foods', 'food_beverage', 'KMF', '07012345678', NULL, 'kemi@nfelia.test', 1, '₦', 1, 1, 3, NOW(), NULL)");

$user = $db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
foreach ([
    [1, 1, 'owner', 'Founder', 'founder@nfelia.test'],
    [2, 2, 'owner', 'Bisi', 'bisi@nfelia.test'],
    [3, 2, 'staff', 'Tunde Staff', 'tunde@nfelia.test'],
    [4, 3, 'owner', 'Volt Owner', 'volt@nfelia.test'],
    [5, 4, 'owner', 'Kemi', 'kemi@nfelia.test'],
] as $u) {
    $user->execute([...$u, $pw]);
}

$db->exec("INSERT INTO subscriptions (business_id, country_id, plan, status, trial_ends_at, current_period_end, last_payment_date) VALUES
    (1, 1, 'standard', 'active', NULL, DATE_ADD(CURDATE(), INTERVAL 20 DAY), CURDATE()),
    (2, 1, 'trial', 'trial', DATE_ADD(CURDATE(), INTERVAL 10 DAY), NULL, NULL),
    (3, 1, 'trial', 'trial', DATE_SUB(CURDATE(), INTERVAL 2 DAY), NULL, NULL),
    (4, 1, 'standard', 'active', NULL, DATE_ADD(CURDATE(), INTERVAL 20 DAY), CURDATE())");

$db->exec("INSERT INTO products (id, business_id, name, description, category, sell_price, cost_price, stock_qty, is_service, is_active) VALUES
    (1, 2, 'Adire kaftan', 'Hand-dyed', 'Kaftans', 25000, 12000, 10, 0, 1),
    (2, 2, 'Ankara wrapper', 'Six yards', 'Fabrics', 8000, 4000, 30, 0, 1),
    (3, 2, 'Hidden item', 'Not for sale', 'Fabrics', 1000, 500, 5, 0, 0),
    (4, 4, 'Jollof tray', 'Party size', 'Food', 30000, 15000, 5, 0, 1)");

echo "Seeded 4 businesses, 5 users, 4 products.\n";
