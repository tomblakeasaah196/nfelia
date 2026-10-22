<?php
/**
 * Adanna Luxe Hair: the made-up hair vendor the five tutorials follow, never
 * a real business. Built on the test fixture's business 2 (Ada's Hair & Co)
 * and its owner (user 20), so the fixture's countries, plans and Nfelia
 * itself are all there. Each video's seed starts from adanna_reset() and adds
 * what the earlier videos made on camera.
 *
 * Owner: adanna@adannaluxehair.com, password TestPass1 (scenes.json "login").
 * WIPES the database, so it refuses unless APP_ENV is local or testing.
 */
require_once __DIR__ . '/../../../../tests/notifications/lib.php';

const ADANNA_EMAIL = 'adanna@adannaluxehair.com';
const ADANNA_ASSETS = __DIR__ . '/assets';

// name, category, price, cost, stock, low-stock alert, unit, picture, description, options
const ADANNA_PRODUCTS = [
    'bone'    => ['Bone Straight Bundles', 'Bundles', 85000, 52000, 12, 3, 'bundle', 'bone-straight-14.jpg',
                  '100% human hair. Silky and bone straight from top to tip.',
                  ['Length' => ['12 inches' => -10000, '14 inches' => 0, '16 inches' => 15000, '18 inches' => 30000]]],
    'body'    => ['Body Wave Bundles 18"', 'Bundles', 120000, 75000, 8, 3, 'bundle', 'body-wave-18.jpg',
                  'Soft, bouncy body wave. Holds curls and colour well.', []],
    'deep'    => ['Deep Wave Bundles 20"', 'Bundles', 145000, 92000, 6, 2, 'bundle', 'deep-wave-20.jpg',
                  'Full deep wave with natural shine. Lasts for years with good care.', []],
    'pixie'   => ['Pixie Curls 12"', 'Bundles', 75000, 45000, 10, 3, 'bundle', 'pixie-curls-12.jpg',
                  'Short, tight pixie curls. Light and easy to style.', []],
    'bob'     => ['Bob Wig 10"', 'Wigs', 95000, 58000, 5, 2, 'piece', 'bob-wig-10.jpg',
                  'Ready to wear bob wig with a natural parting.', []],
    'closure' => ['HD Lace Closure 5x5', 'Closures', 65000, 38000, 7, 2, 'piece', 'hd-lace-closure-5x5.jpg',
                  'Thin HD lace that melts into every skin tone.', []],
    'frontal' => ['HD Lace Frontal 13x4', 'Frontals', 110000, 68000, 4, 2, 'piece', 'hd-lace-frontal-13x4.jpg',
                  'Ear to ear HD lace for a flawless hairline.', []],
    'glue'    => ['Lace Glue & Edge Control Set', 'Hair care', 18000, 9500, 20, 5, 'set', 'glue-and-edge-control.jpg',
                  'Strong hold lace glue and edge control. Sweat and water resistant.', []],
];

// Customers met in the later videos
const ADANNA_CUSTOMERS = [
    'chiamaka' => ['Chiamaka Nwosu', '08061234501', '15 Isaac John Street, Ikeja GRA, Lagos'],
    'funke'    => ['Funke Adeyemi', '08061234502', '7 Bode Thomas Street, Surulere, Lagos'],
    'blessing' => ['Blessing Okon', '08061234503', '22 Herbert Macaulay Way, Yaba, Lagos'],
    'temitope' => ['Temitope Bakare', '08061234504', '3 Ajose Adeogun Street, Victoria Island, Lagos'],
    'zainab'   => ['Zainab Bello', '08061234505', '11 Admiralty Road, Lekki Phase 1, Lagos'],
];

/**
 * The business as video 1 leaves it: signed up, onboarded, shop link chosen,
 * on the free trial with $trial_days left. No products, clients or sales.
 */
function adanna_reset(int $trial_days = 13): PDO
{
    t_guard();
    fixture_reset();
    $db = get_db();

    // Ada's fixture data goes; the business and its owner stay, renamed
    foreach (['reviews', 'order_items', 'orders', 'debts', 'tasks', 'invoice_items', 'invoices', 'quotations', 'deliveries'] as $t) {
        $col = $t === 'order_items' ? 'order_id IN (SELECT id FROM orders WHERE business_id = 2)'
             : ($t === 'invoice_items' ? 'invoice_id IN (SELECT id FROM invoices WHERE business_id = 2)' : 'business_id = 2');
        $db->exec("DELETE FROM `{$t}` WHERE {$col}");
    }
    $db->exec("DELETE FROM products WHERE business_id = 2");
    $db->exec("DELETE FROM customers WHERE business_id = 2");
    $db->exec("DELETE FROM users WHERE business_id = 2 AND id <> 20");

    $logo = adanna_picture('logo.jpg', 'logos/2', 'logo_seed.png', 600, 'png');
    $db->prepare("UPDATE businesses SET name = 'Adanna Luxe Hair', slug = 'adannaluxehair', doc_prefix = 'ALH', subdomain = 'adannaluxehair',
                    subdomain_status = 'active', storefront_look = 'glow', landing_enabled = 1, category = 'beauty', business_type = 'product',
                    tagline = 'Luxury hair. Delivered to your door.', primary_color = '#0B5A3C', secondary_color = '#C9A55C',
                    font_pairing = 'corporate_serif', logo_path = ?, email = ?, phone = '08031234567', whatsapp = '08031234567',
                    address = 'Shop 14, Admiralty Way, Lekki Phase 1, Lagos', default_tax_rate = 0, currency_symbol = '₦', country_id = 1
                  WHERE id = 2")
       ->execute([$logo, ADANNA_EMAIL]);
    $db->prepare("UPDATE users SET full_name = 'Adanna Okeke', email = ? WHERE id = 20")->execute([ADANNA_EMAIL]);
    $db->exec("DELETE FROM bank_accounts WHERE business_id = 2");
    $db->exec("INSERT INTO bank_accounts (business_id, kind, bank_name, account_number, account_name, is_default)
               VALUES (2, 'bank', 'Moniepoint', '8031234567', 'Adanna Luxe Hair', 1)");
    $db->prepare("UPDATE subscriptions SET plan = 'trial', status = 'trial', user_seats = 1, extra_seats = 0, monthly_amount = 0,
                    trial_ends_at = DATE_ADD(CURDATE(), INTERVAL ? DAY), current_period_start = NULL, current_period_end = NULL,
                    paystack_subscription_code = NULL, paystack_customer_code = NULL
                  WHERE business_id = 2")
       ->execute([$trial_days]);

    // A clean screen: no bell count, no "first ..." feedback prompts
    $db->exec("DELETE FROM notifications");
    foreach (['first_invoice', 'first_delivery_signoff', 'first_receipt', 'first_quotation'] as $trigger) {
        $db->prepare("INSERT IGNORE INTO feedback_triggers_seen (user_id, trigger_action) VALUES (20, ?)")->execute([$trigger]);
    }
    return $db;
}

/** A picture from assets/, saved where the app keeps uploads; returns its path for the database. */
function adanna_picture(string $file, string $dir, string $name, int $width, string $format = 'jpg'): string
{
    $dest_dir = UPLOAD_PATH . '/' . $dir;
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'nfv');
    copy(ADANNA_ASSETS . '/' . $file, $tmp);
    if (!save_image(['tmp_name' => $tmp, 'name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], "{$dest_dir}/{$name}", $width, $format)) {
        throw new RuntimeException("Could not save {$file}");
    }
    @unlink($tmp);
    return 'uploads/' . $dir . '/' . $name;
}

/** One product from ADANNA_PRODUCTS, with its picture and options; returns its id. */
function adanna_product(PDO $db, string $key): int
{
    [$name, $category, $price, $cost, $stock, $low, $unit, $picture, $about, $options] = ADANNA_PRODUCTS[$key];
    $db->prepare("INSERT INTO products (business_id, sku, name, description, category, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service, is_active)
                  VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)")
       ->execute(['ALH-' . strtoupper($key), $name, $about, $category, $price, $cost, $stock, $low, $unit]);
    $id = (int)$db->lastInsertId();
    $path = adanna_picture($picture, 'products/2', "seed_{$key}.jpg", 1200);
    $db->prepare("INSERT INTO product_images (product_id, business_id, image_path, sort_order, is_cover) VALUES (?, 2, ?, 0, 1)")->execute([$id, $path]);
    $db->prepare("UPDATE products SET image_path = ? WHERE id = ?")->execute([$path, $id]);
    $g = 0;
    foreach ($options as $group => $choices) {
        $db->prepare("INSERT INTO product_variant_groups (product_id, business_id, name, sort_order) VALUES (?, 2, ?, ?)")->execute([$id, $group, $g++]);
        $gid = (int)$db->lastInsertId();
        $o = 0;
        foreach ($choices as $label => $delta) {
            $db->prepare("INSERT INTO product_variant_options (variant_group_id, label, price_delta, sort_order) VALUES (?, ?, ?, ?)")->execute([$gid, $label, $delta, $o++]);
        }
    }
    return $id;
}

/** Every product but the ones named; returns key => id. */
function adanna_products(PDO $db, array $except = []): array
{
    $ids = [];
    foreach (array_keys(ADANNA_PRODUCTS) as $key) {
        if (!in_array($key, $except, true)) {
            $ids[$key] = adanna_product($db, $key);
        }
    }
    return $ids;
}

/** A customer from ADANNA_CUSTOMERS; returns its id. */
function adanna_customer(PDO $db, string $key): int
{
    [$name, $phone, $address] = ADANNA_CUSTOMERS[$key];
    $db->prepare("INSERT INTO customers (business_id, full_name, phone, whatsapp, address) VALUES (2, ?, ?, ?, ?)")
       ->execute([$name, $phone, '234' . substr($phone, 1), $address]);
    return (int)$db->lastInsertId();
}
