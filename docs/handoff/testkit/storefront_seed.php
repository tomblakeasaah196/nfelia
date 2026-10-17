<?php
// Test data for storefront_test.py and shoot_storefront.js. Local database
// only: it empties the tables it fills, and writes test photos under
// uploads/products/ and uploads/heroes/ (delete uploads/ when done).
//
// One shop per look, ids 2 to 11, each with an owner (owner<id>@nfelia.test)
// and products in a few categories. Some products have photos and some
// don't, so both the photo frames and the placeholders show. Nfelia HQ (1)
// is the founder account. Every password is testpass123.
$root = dirname(__DIR__, 3);
$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pw = password_hash('testpass123', PASSWORD_BCRYPT);

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['bookings', 'reviews', 'delivery_items', 'deliveries', 'receipt_items', 'receipts', 'invoice_items', 'invoices',
          'order_items', 'orders', 'quote_requests', 'customers', 'notifications', 'sessions', 'subscriptions',
          'product_variant_options', 'product_variant_groups', 'product_images', 'products', 'bank_accounts',
          'storefront_versions', 'shop_name_holds', 'users', 'businesses', 'countries'] as $t) {
    $db->exec("DELETE FROM `$t`");
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, monthly_price)
           VALUES (1, 'Nigeria', 'NG', '🇳🇬', 'Naira', 'NGN', '₦', 10000)");

// id, name, subdomain, look, category, phone, whatsapp, address, tagline, about, delivery
$shops = [
    [1, 'Nfelia HQ', null, 'desk', 'services', '08030000001', null, 'Lagos', null, null, null],
    [2, 'Bisi Indigo', 'bisi-indigo', 'atelier', 'fashion', '0803 123 4567', '08031234567', "12 Lafenwa Road\nAbeokuta, Ogun State",
        'Adire, cut to your measure.', "Hand-dyed in our Abeokuta workshop since 2014.\n\nBuy it ready to wear, or send your measurements and we cut it for you.",
        'We deliver across Nigeria in 3 to 5 days. Pickup from the workshop is free.'],
    [3, "Mama Nkechi's Kitchen", 'mama-nkechi', 'buka', 'food_beverage', '0809 555 1212', '08095551212', 'Independence Layout, Enugu',
        'Home cooking, delivered hot.', 'Soups by the litre, rice by the plate and small chops for every party.',
        'Delivery within Enugu is ₦1,500. Pickup from our kitchen is free.'],
    [4, 'Glow by Temi', 'glow-by-temi', 'glow', 'other', '0812 000 4545', '08120004545', 'Bodija, Ibadan',
        'Braids, nails and lashes in Ibadan.', null, 'Come to the studio in Bodija, or we come to you for a small fee.'],
    [5, 'Kola Gadgets', 'kola-gadgets', 'volt', 'tech_repair', '0802 777 8899', '08027778899', 'Computer Village, Ikeja',
        'Phones and laptops, tested before you pay.', 'UK used and brand new devices, and a repair desk for screens and batteries.',
        'Pickup in Ikeja, or delivery anywhere in Lagos the same day.'],
    [6, 'Musa & Sons Provisions', 'musa-provisions', 'market', 'wholesale_retail', '0803 444 1000', '08034441000', 'Idumota, Lagos Island',
        'Wholesale prices for shops, schools and events.', null, 'Minimum order is 5 cartons. Free delivery on Lagos Island from 20 cartons.'],
    [7, 'Lola & Co. Events', 'lola-events', 'stage', 'creative', '0805 123 9876', '08051239876', 'Lekki, Lagos',
        'Weddings, owambe and birthdays, styled end to end.', 'Decor, MC, small chops and photography, from one team.', null],
    [8, 'Ayomide Farms', 'ayomide-farms', 'harvest', 'other', '0703 222 3333', '07032223333', 'Ogbomoso, Oyo State',
        'Fresh from the farm, every Saturday.', null, 'Farm gate pickup on Saturdays. Saturday delivery to Ibadan and Lagos.'],
    [9, 'Zainab Naturals', 'zainab-naturals', 'shea', 'other', '0806 909 1010', '08069091010', 'Kaduna',
        'Handmade skincare from Kaduna, in small batches.', 'Made by hand every week.', 'We deliver across Nigeria.'],
    [10, 'Haven Shortlets', 'haven-shortlets', 'haven', 'other', '0901 234 5678', '09012345678', 'Lekki Phase 1, Lagos',
        'Short stays in Lekki and Ikoyi, with power that stays on.', 'Furnished apartments with 24-hour power, fast Wi-Fi and a caretaker on call.', null],
    [11, 'Chinedu & Co.', 'chinedu-co', 'desk', 'services', '0808 111 2222', '08081112222', 'Port Harcourt',
        'Clean homes, cool rooms, no leaks.', 'Deep cleaning, fumigation, AC servicing and plumbing across Port Harcourt.', null],
];
$biz = $db->prepare("INSERT INTO businesses (id, name, slug, subdomain, subdomain_status, storefront_look, category, doc_prefix, phone, whatsapp, email, address,
                         tagline, about_text, delivery_info, landing_enabled, currency_symbol, country_id, onboarding_complete, onboarding_step, email_verified_at,
                         social_instagram, rfq_fields)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, '₦', 1, 1, 3, NOW(), ?, ?)");
$user = $db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
foreach ($shops as [$id, $name, $sub, $look, $cat, $phone, $wa, $addr, $tagline, $about, $delivery]) {
    $biz->execute([$id, $name, 'shop-' . $id, $sub, $sub ? 'active' : 'none', $look, $cat, 'S' . $id, $phone, $wa,
                   $id === 1 ? 'founder@nfelia.test' : "owner$id@nfelia.test", $addr, $tagline, $about, $delivery,
                   $id > 1 ? strtolower(str_replace([' ', '&', '.', "'"], '', $name)) : null,
                   $id === 6 ? '{"quantity_range":true,"company_name":true}' : null]);
    $user->execute([$id, $id, 'owner', $name . ' owner', $id === 1 ? 'founder@nfelia.test' : "owner$id@nfelia.test", $pw]);
}
$user->execute([20, 2, 'staff', 'Tunde Staff', 'staff2@nfelia.test', $pw]);

$db->exec("INSERT INTO subscriptions (business_id, country_id, plan, status, trial_ends_at, current_period_end, last_payment_date)
           SELECT id, 1, 'standard', 'active', NULL, DATE_ADD(CURDATE(), INTERVAL 20 DAY), CURDATE() FROM businesses");
// Two shops on trial, so they stay out of search engines
$db->exec("UPDATE subscriptions SET plan = 'trial', status = 'trial', trial_ends_at = DATE_ADD(CURDATE(), INTERVAL 9 DAY), current_period_end = NULL, last_payment_date = NULL WHERE business_id IN (4, 9)");

$bank = $db->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_number, account_name, is_default, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
foreach ([[2, 'GTBank', '0123456789', 'Bisi Indigo Ltd', 1, 1], [2, 'Moniepoint', '5012345678', 'Bisi Indigo Ltd', 0, 2],
          [3, 'Access Bank', '0011223344', "Mama Nkechi's Kitchen", 1, 1], [5, 'Opay', '8027778899', 'Kola Gadgets', 1, 1],
          [6, 'First Bank', '3012345678', 'Musa and Sons Ltd', 1, 1], [8, 'Wema Bank', '0234567890', 'Ayomide Farms', 1, 1],
          [9, 'Kuda', '2011223344', 'Zainab Naturals', 1, 1], [10, 'Zenith Bank', '1012345678', 'Haven Shortlets Ltd', 1, 1],
          [11, 'UBA', '2098765432', 'Chinedu and Co', 1, 1]] as $b) {
    $bank->execute($b);
}

// A test photo: soft shapes in two colours. Not a real product photo, just
// something with the size and weight of one.
function seed_photo(string $root, int $biz, string $file, int $w, int $h, array $c1, array $c2): string {
    $dir = "$root/uploads/products/$biz";
    if (str_starts_with($file, 'hero')) {
        $dir = "$root/uploads/heroes/$biz";
    }
    @mkdir($dir, 0755, true);
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $col = imagecolorallocate($im, (int)($c1[0] + ($c2[0] - $c1[0]) * $t), (int)($c1[1] + ($c2[1] - $c1[1]) * $t), (int)($c1[2] + ($c2[2] - $c1[2]) * $t));
        imageline($im, 0, $y, $w, $y, $col);
    }
    $light = imagecolorallocatealpha($im, 255, 255, 255, 80);
    imagefilledellipse($im, (int)($w * 0.5), (int)($h * 0.55), (int)($w * 0.62), (int)($w * 0.62), $light);
    imagefilledellipse($im, (int)($w * 0.5), (int)($h * 0.55), (int)($w * 0.3), (int)($w * 0.3), $light);
    imagepng($im, "$dir/$file", 8);
    imagedestroy($im);
    return (str_starts_with($file, 'hero') ? "uploads/heroes/$biz/" : "uploads/products/$biz/") . $file;
}

$product = $db->prepare("INSERT INTO products (business_id, name, description, category, sell_price, cost_price, stock_qty, unit, is_service, is_active, image_path, created_at)
                         VALUES (?, ?, ?, ?, ?, 0, 10, ?, ?, 1, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))");
$group   = $db->prepare("INSERT INTO product_variant_groups (product_id, business_id, name, sort_order) VALUES (?, ?, ?, ?)");
$option  = $db->prepare("INSERT INTO product_variant_options (variant_group_id, label, sort_order) VALUES (?, ?, ?)");
$image   = $db->prepare("INSERT INTO product_images (product_id, business_id, image_path, sort_order, is_cover) VALUES (?, ?, ?, ?, ?)");
$age = 0;
function add($biz, $name, $desc, $cat, $price, $unit = null, $service = 0, $variants = [], $photo = null) {
    global $product, $group, $option, $image, $db, $root, $age;
    $age++;
    $product->execute([$biz, $name, $desc, $cat, $price, $unit, $service, null, $age]);
    $pid = (int)$db->lastInsertId();
    $g = 0;
    foreach ($variants as $gname => $opts) {
        $group->execute([$pid, $biz, $gname, $g++]);
        $gid = (int)$db->lastInsertId();
        foreach ($opts as $i => $label) {
            $option->execute([$gid, $label, $i]);
        }
    }
    if ($photo) {
        [$w, $h, $c1, $c2] = $photo;
        $rel = seed_photo($root, $biz, "seed_$pid.png", $w, $h, $c1, $c2);
        $image->execute([$pid, $biz, $rel, 0, 1]);
        $db->prepare("UPDATE products SET image_path = ? WHERE id = ?")->execute([$rel, $pid]);
    }
    return $pid;
}

// Atelier
$blue = [[46, 63, 158], [22, 26, 51]];
add(2, 'Olokun wrap dress', 'Hand-dyed adire, cut to your measure.', 'Dresses', 38000, null, 0, ['Fabric' => ['Olokun', 'Oniko', 'Eleko']], [600, 750, ...$blue]);
add(2, 'Kampala shirt', 'Relaxed fit with a hidden placket.', 'Shirts', 22000, null, 0, ['Colour' => ['Navy', 'Wine', 'Cream'], 'Size' => ['S', 'M', 'L', 'XL']]);
add(2, 'Oniko scarf', 'Light cotton, long enough to wrap twice.', 'Accessories', 9500, null, 0, ['Fabric' => ['Oniko']], [600, 750, [122, 36, 51], [46, 63, 158]]);
add(2, 'Agbada set, made to measure', "Three pieces in the fabric you choose.\nTakes about 7 days after your fitting.", 'Made to measure', 85000, 'set', 0, ['Fabric' => ['Olokun', 'Oniko', 'Eleko', 'Alabere']]);
add(2, 'Adire tote bag', 'Strong canvas lining.', 'Accessories', 12500);
add(2, 'Eleko kaftan', 'Ankle length, side pockets.', 'Dresses', 31000, null, 0, ['Colour' => ['Indigo', 'Black']], [600, 750, [31, 92, 74], [22, 26, 51]]);
add(2, 'Fitting at the workshop', 'We measure you and help you choose a cloth.', 'Made to measure', 0, null, 1);

// Buka
add(3, 'Jollof rice and chicken', 'Smoky party jollof, fried plantain and one piece of chicken.', 'Rice and mains', 3500, 'plate', 0, [], [600, 600, [212, 80, 42], [156, 91, 46]]);
add(3, 'Fried rice and turkey', 'With liver, prawns and coleslaw.', 'Rice and mains', 4500, 'plate');
add(3, 'Ofe onugbu, 1 litre', 'Bitterleaf soup with assorted meat and stockfish.', 'Soups by the litre', 9500, 'litre');
add(3, 'Egusi soup, 1 litre', 'With spinach and goat meat.', 'Soups by the litre', 9000, 'litre', 0, [], [600, 600, [227, 180, 72], [142, 74, 36]]);
add(3, 'Party pack for 10', 'Puff-puff, samosa, spring rolls and peppered gizzard.', 'Small chops', 25000, 'pack');
add(3, 'Zobo, 1 litre', 'Chilled, with pineapple and ginger.', 'Drinks', 1500, 'bottle');

// Glow (services)
add(4, 'Knotless braids, medium, waist length', 'Hair not included.', 'Hair', 45000, null, 1, ['Length' => ['Shoulder', 'Mid-back', 'Waist']]);
add(4, 'Cornrows with extensions', '', 'Hair', 15000, null, 1);
add(4, 'Gel polish, hands', '', 'Nails', 8000, null, 1, [], [600, 600, [142, 59, 138], [42, 22, 48]]);
add(4, 'Acrylic full set', 'Any length, plain colour.', 'Nails', 14000, null, 1);
add(4, 'Classic lash set', '', 'Lashes', 12000, null, 1);

// Volt
add(5, 'iPhone 12, 128GB', 'Battery 86%. Face ID works.', 'Phones', 385000, null, 0, ['Condition' => ['UK used'], 'Colour' => ['Black', 'Blue']], [600, 600, [227, 232, 238], [201, 211, 223]]);
add(5, 'Samsung Galaxy A15, 128GB', 'Sealed box.', 'Phones', 165000, null, 0, ['Storage' => ['128GB', '256GB']]);
add(5, 'HP EliteBook 840 G6', '16GB RAM, 512GB SSD.', 'Laptops', 420000, null, 0, ['Specs' => ['16GB RAM', '512GB SSD', 'Core i5']]);
add(5, 'Fast charger, 25W', 'USB-C, with cable.', 'Chargers', 9500);
add(5, 'Power bank, 20,000mAh', '', 'Chargers', 21000, null, 0, [], [600, 600, [14, 27, 43], [74, 85, 102]]);
add(5, 'Screen replacement', 'Most phones done the same day.', 'Repairs', 45000, null, 1);
add(5, 'Battery replacement', '', 'Repairs', 25000, null, 1);
add(5, 'Charging port repair', '', 'Repairs', 12000, null, 1);

// Market
add(6, 'Instant noodles, chicken 70g', 'Carton of 40 packs.', 'Noodles and pasta', 9600, 'carton');
add(6, 'Spaghetti 500g', 'Carton of 20.', 'Noodles and pasta', 21500, 'carton');
add(6, 'Macaroni 500g', 'Carton of 20.', 'Noodles and pasta', 20500, 'carton', 0, ['Brand' => ['Golden Penny', 'Honeywell']]);
add(6, 'Vegetable oil, 5 litres', 'Carton of 4.', 'Oils', 52000, 'carton');
add(6, 'Groundnut oil, 3 litres', 'Carton of 6.', 'Oils', 45000, 'carton');
add(6, 'Rice, long grain', '50kg bag.', 'Rice and grains', 78000, 'bag');
add(6, 'Beans, oloyin', '50kg bag.', 'Rice and grains', 95000, 'bag');

// Stage (services)
$s1 = add(7, 'Intimate', 'Up to 80 guests. Decor and lighting, an MC for four hours and small chops for everyone.', 'Packages', 450000, null, 1, [], [700, 900, [31, 73, 68], [212, 167, 60]]);
add(7, 'Classic', 'Up to 250 guests. Everything in Intimate, plus stage, backdrop and six hours of photography.', 'Packages', 1200000, null, 1);
add(7, 'Grand', '500 guests and more. Everything in Classic, with a two-day setup.', 'Packages', 2800000, null, 1, [], [700, 900, [14, 59, 54], [243, 238, 226]]);
add(7, 'Small chops tray', 'For 50 guests.', 'Extras', 75000, 'tray');

// Harvest
add(8, 'Eggs, crate of 30', 'Large, laid this week.', 'Eggs and birds', 5800, 'crate', 0, [], [600, 600, [242, 181, 48], [201, 167, 116]]);
add(8, 'Live broiler', 'About 2.5kg. We dress it free if you ask.', 'Eggs and birds', 12500, 'each');
add(8, 'Smoked catfish, 5 pieces', 'Smoked over wood at the farm.', 'Fish', 9000, 'pack');
add(8, 'Yam, 5 tubers', 'Big tubers from this season.', 'Tubers', 15000, 'bundle');
add(8, 'Honey, 1 litre', 'Raw honey from our own hives.', 'Honey', 8500, 'bottle');

// Shea
add(9, 'Whipped shea butter', 'Shea butter, coconut oil and vitamin E.', 'Dry skin', 6500, null, 0, ['Size' => ['250g', '500g'], 'Scent' => ['Unscented', 'Lavender']], [600, 750, [246, 240, 230], [163, 25, 78]]);
add(9, 'African black soap, 200g', 'Plantain skin ash, palm kernel oil, shea butter.', 'Dry skin', 3000);
add(9, 'Hibiscus face toner, 100ml', 'Hibiscus, witch hazel and aloe vera.', 'Oily skin', 5500, null, 0, ['Made with' => ['Hibiscus', 'Witch hazel', 'Aloe vera']]);
add(9, 'Oud perfume oil, 12ml', '', 'Perfume oils', 9000, null, 0, ['Size' => ['6ml', '12ml']]);
add(9, 'Calming body oil', 'For sensitive skin.', 'Sensitive', 7000, null, 0, ['Size' => ['100ml']]);

// Haven
add(10, 'One-bedroom apartment', 'Lekki Phase 1, sleeps 2.', 'Lekki', 85000, 'night', 1, ['Amenities' => ['24-hour power', 'Wi-Fi', 'Pool']], [600, 600, [220, 230, 236], [11, 110, 121]]);
add(10, 'Studio', 'Ikoyi, sleeps 2.', 'Ikoyi', 60000, 'night', 1, ['Amenities' => ['24-hour power', 'Wi-Fi']]);
add(10, 'Three-bedroom duplex', 'Victoria Island, sleeps 6.', 'Victoria Island', 250000, 'night', 1, ['Amenities' => ['24-hour power', 'Wi-Fi', 'Pool', 'Parking']]);

// Desk
add(11, 'Deep cleaning', "Kitchen degreased, inside the cupboards too\nBathrooms scrubbed and disinfected\nFloors, windows and ceiling fans", 'Cleaning', 45000, null, 1);
add(11, 'Fumigation', "Cockroaches, ants, rats and bedbugs\nSafe to come back in after 4 hours", 'Pest control', 25000, null, 1);
add(11, 'AC servicing', "Filters and coils washed\nGas level checked\nDrain pipe cleared", 'AC and plumbing', 8000, 'unit', 1);
add(11, 'Plumbing', "Leaks and blocked drains\nPumping machine repairs", 'AC and plumbing', 10000, null, 1);

// Two hero photos
$db->prepare("UPDATE businesses SET hero_image_path = ? WHERE id = 7")->execute([seed_photo($root, 7, 'hero_seed.png', 1400, 1360, [31, 73, 68], [212, 167, 60])]);

// Bisi and Kemi's kitchen have published Studio settings, so every section
// is drawn; the others use what the old Landing Page columns hold.
$published = [
    2 => ['look' => 'atelier', 'colours' => ['set' => 'look'], 'font' => '', 'sections' => [
        ['type' => 'announcement', 'on' => true, 'text' => 'Fittings this Saturday at the Abeokuta workshop'],
        ['type' => 'hero', 'on' => true, 'headline' => 'Adire, cut to your measure.', 'text' => 'Hand-dyed in our Abeokuta workshop. Buy it ready to wear, or send your measurements and we cut it for you.', 'button' => 'Shop new pieces', 'badge' => '', 'image' => ''],
        ['type' => 'featured', 'on' => true, 'title' => 'New pieces', 'products' => []],
        ['type' => 'catalogue', 'on' => true, 'title' => '', 'only' => []],
        ['type' => 'quote', 'on' => true, 'title' => 'Made to measure', 'text' => 'Send your measurements, or book a fitting at the workshop. Collect it in 7 days, or we deliver to you.', 'timeline' => true, 'delivery_location' => true],
        ['type' => 'about', 'on' => true, 'title' => '', 'text' => "Hand-dyed in our Abeokuta workshop since 2014.\n\nBuy it ready to wear, or send your measurements and we cut it for you."],
        ['type' => 'video', 'on' => true, 'title' => 'How we dye our cloth', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['type' => 'delivery', 'on' => true, 'title' => '', 'text' => 'We deliver across Nigeria in 3 to 5 days. Pickup from the workshop is free.', 'show_bank' => true],
        ['type' => 'faq', 'on' => true, 'title' => '', 'items' => [
            ['q' => 'How long does made to measure take?', 'a' => 'About 7 days after your fitting.'],
            ['q' => 'Can I return a piece?', 'a' => "Ready-to-wear pieces can be swapped within 7 days.\nMade-to-measure pieces can't be returned."],
        ]],
        ['type' => 'contact', 'on' => true, 'text' => 'Open Monday to Saturday, 9am to 6pm.', 'instagram' => 'bisi.indigo', 'tiktok' => 'bisiindigo', 'show_address' => true, 'show_phone' => true, 'show_email' => true],
    ]],
    3 => ['look' => 'buka', 'colours' => ['set' => 'look'], 'font' => '', 'sections' => [
        ['type' => 'announcement', 'on' => false, 'text' => ''],
        ['type' => 'hero', 'on' => true, 'headline' => 'Home cooking, delivered hot.', 'text' => 'Order by 10am for lunch delivery across Enugu. Soups come by the litre, ready for your freezer.', 'button' => '', 'badge' => 'Party packs from ₦25,000', 'image' => ''],
        ['type' => 'featured', 'on' => false],
        ['type' => 'catalogue', 'on' => true],
        ['type' => 'delivery', 'on' => true, 'text' => 'Delivery within Enugu is ₦1,500. Pickup from our kitchen in Independence Layout is free.', 'show_bank' => false],
        ['type' => 'quote', 'on' => true],
        ['type' => 'about', 'on' => true, 'text' => 'Soups by the litre, rice by the plate and small chops for every party.'],
        ['type' => 'contact', 'on' => true, 'text' => 'Monday to Saturday, 8am to 9pm.'],
    ]],
];
foreach ($published as $id => $settings) {
    $json = json_encode($settings, JSON_UNESCAPED_UNICODE);
    $db->prepare("UPDATE businesses SET storefront_published = ?, storefront_draft = ?, storefront_published_at = NOW() WHERE id = ?")->execute([$json, $json, $id]);
}

echo "Seeded 11 businesses, one shop per look, with products, photos and banks.\n";
