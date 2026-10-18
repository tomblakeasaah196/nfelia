<?php
// Demo data for the landing page's card screenshots (shoot_landing.js).
// Local database only. Run after seed.php and planner_seed.sql.txt:
//   php docs/handoff/testkit/landing_seed.php <out dir>
// It gives Ivie Luxe Hair (business 1) a live website at ivieluxe.<host> in
// the Shea look, with product photos cropped from the landing hero photo, a
// bookable wig install, three website orders (one with a transfer slip, one
// collected with a review), a booking request, delivery lines, and simple
// monogram logos for Ivie and Kemi. Files go under uploads/ (delete when done);
// tokens for the shooter go to <out dir>/tokens.json.
$root = dirname(__DIR__, 3);
$out  = $argv[1] ?? sys_get_temp_dir();
$db   = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tok  = fn() => bin2hex(random_bytes(16));
@mkdir($out, 0777, true);

// Photos: crops of the hero photo, upscaled and lightly sharpened.
$hero = imagecreatefromwebp("$root/assets/img/home/hero-1024.webp");
foreach (['products/1', 'heroes/1', 'logos/1', 'logos/2', 'payment_proofs/1'] as $d) { @mkdir("$root/uploads/$d", 0777, true); }
imagejpeg($hero, "$root/uploads/heroes/1/hero.jpg", 86);
$crops = [3 => [748, 30, 240, 300], 1 => [600, 50, 210, 262], 2 => [440, 45, 180, 190], 8 => [835, 470, 150, 188], 5 => [880, 770, 120, 150], 6 => [300, 215, 400, 500]];
$db->exec('DELETE FROM product_images WHERE business_id = 1');
foreach ($crops as $pid => [$x, $y, $w, $h]) {
    $th = (int)round(800 * $h / $w);
    $im = imagecreatetruecolor(800, $th);
    imagecopyresampled($im, $hero, 0, 0, $x, $y, 800, $th, $w, $h);
    imageconvolution($im, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0);
    $rel = "uploads/products/1/p$pid.jpg";
    imagejpeg($im, "$root/$rel", 88);
    $db->prepare('UPDATE products SET image_path = ? WHERE id = ?')->execute([$rel, $pid]);
    $db->prepare('INSERT INTO product_images (product_id, business_id, image_path, sort_order, is_cover) VALUES (?, 1, ?, 0, 1)')->execute([$pid, $rel]);
}

// Monogram logos.
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
foreach ([[1, 'IL', [122, 31, 75]], [2, 'KE', [15, 92, 74]]] as [$id, $txt, $bg]) {
    $s = 512; $im = imagecreatetruecolor($s, $s);
    imagesavealpha($im, true); imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $gold = imagecolorallocate($im, 201, 162, 74);
    imagefilledellipse($im, $s / 2, $s / 2, $s - 4, $s - 4, imagecolorallocate($im, ...$bg));
    imageellipse($im, $s / 2, $s / 2, $s - 60, $s - 60, $gold);
    $box = imagettfbbox(170, 0, $font, $txt);
    imagettftext($im, 170, 0, (int)(($s - ($box[2] - $box[0])) / 2 - $box[0]), (int)(($s + ($box[1] - $box[7])) / 2 - $box[1]), $gold, $font, $txt);
    imagepng($im, "$root/uploads/logos/$id/logo.png");
}

// A plain transfer slip for the order waiting on a payment check.
$w = 720; $h = 1100; $im = imagecreatetruecolor($w, $h);
$ink = imagecolorallocate($im, 20, 30, 45); $mut = imagecolorallocate($im, 105, 115, 130); $green = imagecolorallocate($im, 0, 118, 74);
$white = imagecolorallocate($im, 255, 255, 255); $line = imagecolorallocate($im, 225, 230, 236);
imagefill($im, 0, 0, imagecolorallocate($im, 246, 248, 250));
$R = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'; $B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$c = fn($size, $f, $t, $y, $col) => imagettftext($im, $size, 0, (int)(($w - imagettfbbox($size, 0, $f, $t)[2]) / 2), $y, $col, $f, $t);
imagefilledellipse($im, $w / 2, 170, 150, 150, $green);
imagesetthickness($im, 12); imageline($im, 322, 172, 352, 202, $white); imageline($im, 352, 202, 405, 140, $white);
$c(30, $B, 'Transfer successful', 320, $ink); $c(52, $B, 'N145,000.00', 420, $ink);
imagefilledrectangle($im, 40, 480, $w - 40, 1010, $white);
foreach ([['To', 'IVIE LUXE HAIR'], ['Bank', 'Moniepoint MFB'], ['Account', '6012345789'], ['From', 'KEMI BELLO'], ['Date', date('d M Y, g:i A', strtotime('-28 minutes'))], ['Reference', '2210' . date('md') . '48731']] as $i => [$k, $v]) {
    $y = 560 + $i * 82;
    imagettftext($im, 21, 0, 75, $y, $mut, $R, $k);
    imagettftext($im, 21, 0, $w - 75 - imagettfbbox(21, 0, $B, $v)[2], $y, $ink, $B, $v);
    if ($i < 5) imageline($im, 75, $y + 32, $w - 75, $y + 32, $line);
}
imagejpeg($im, "$root/uploads/payment_proofs/1/ord-103.jpg", 88);

// The website.
$db->exec("UPDATE products SET name = 'Wig install and styling', description = 'Install, cut and style on your wig. About two hours. Bring your wig, or buy one here.',
           booking_mode = 'slot', booking_minutes = 120, booking_capacity = 1, deposit_type = 'percent', deposit_value = 50, sell_price = 25000 WHERE id = 6");
$db->exec("UPDATE products SET description = '100% human hair. Full from top to bottom, and it lasts.' WHERE business_id = 1 AND id <> 6");
$db->prepare("UPDATE businesses SET subdomain = 'ivieluxe', subdomain_status = 'active', storefront_look = 'shea', hero_image_path = 'uploads/heroes/1/hero.jpg',
              tagline = 'Luxury hair, honest prices.', about_text = ?, delivery_info = ?, social_instagram = 'ivieluxehair', city = 'Lagos', city_slug = 'lagos',
              featured_product_ids = '[3,1,8,2,6]', booking_settings = NULL WHERE id = 1")
   ->execute(["Bundles, wigs and closures at Lekki Town Square since 2019.\n\nCome to the shop, or order here and we deliver.",
              'Same-day delivery in Lekki and VI. Anywhere in Lagos in 1 to 2 days. Pay by transfer to our account below.']);

// Website orders, a review and a booking request.
$db->exec('DELETE FROM reviews WHERE business_id = 1');
$db->exec('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE business_id = 1)');
$db->exec('DELETE FROM orders WHERE business_id = 1');
$ord = $db->prepare("INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone, delivery_address,
                     payment_method, payment_proof_path, currency_code, subtotal, total, status, invoice_id, delivery_id, created_at) VALUES (?,1,?,?,?,?,?,?,?,?,'bank_transfer',?,'NGN',?,?,?,?,?,?)");
$line = $db->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?,?,?,?,?,?)');
$del  = (int)$db->query('SELECT id FROM deliveries WHERE business_id = 1 LIMIT 1')->fetchColumn();
$t = ['track' => $tok(), 'track_done' => $tok(), 'track_new' => $tok()];
$ord->execute([101, 9, 'IVL-ORD-018', $t['track'], 'cart_pop', 'delivery', 'Lara Coker', '0903 555 0199', '7 Admiralty Way, Lekki Phase 1', null, 210000, 210000, 'approved', 9, $del, date('Y-m-d H:i:s', strtotime('-1 day 10:20'))]);
$line->execute([101, 8, 'Deep Wave 26" (2 bundles)', 210000, 1, 210000]);
$ord->execute([102, 3, 'IVL-ORD-019', $t['track_done'], 'whatsapp', 'pickup', 'Adaeze Nwosu', '0809 555 0133', null, null, 185000, 185000, 'fulfilled', 3, null, date('Y-m-d H:i:s', strtotime('-6 days 15:05'))]);
$line->execute([102, 1, 'Bone Straight 22" (3 bundles)', 185000, 1, 185000]);
$ord->execute([103, null, 'IVL-ORD-020', $t['track_new'], 'cart_pop', 'delivery', 'Kemi Bello', '0814 555 0210', '22 Bourdillon Road, Ikoyi', 'uploads/payment_proofs/1/ord-103.jpg', 145000, 145000, 'pending_verification', null, null, date('Y-m-d H:i:s', strtotime('-25 minutes'))]);
$line->execute([103, 3, 'Kinky Curly Frontal Wig 20"', 145000, 1, 145000]);
$db->prepare("INSERT INTO reviews (business_id, order_id, customer_name, token, rating, comment, status, created_at, submitted_at) VALUES (1, 102, 'Adaeze N.', ?, 5, ?, 'published', NOW(), NOW())")
   ->execute([$tok(), 'Full from top to bottom, and it came well packed. I will buy again.']);
$db->exec('DELETE FROM bookings WHERE business_id = 1');
$sat = date('Y-m-d', strtotime('next saturday'));
$db->prepare("INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due, currency_code,
              customer_id, customer_name, customer_phone, customer_note, channel, payment_method, status, created_at) VALUES (1, 6, 'Wig install and styling', 'IVL-BKG-007', ?, 'slot', ?, ?, 1, 25000, 25000, 12500, 'NGN',
              5, 'Temi Adeyemi', '0817 555 0155', 'Bringing my own wig. Bob cut, please.', 'site', 'bank_transfer', 'pending', NOW() - INTERVAL 40 MINUTE)")
   ->execute([$tok(), "$sat 10:00:00", "$sat 12:00:00"]);

// The dispatched delivery: one line, not yet signed.
$db->exec("DELETE FROM delivery_items WHERE delivery_id = $del");
$db->prepare("INSERT INTO delivery_items (delivery_id, product_id, description, quantity, unit, sort_order) VALUES (?, 8, 'Deep Wave 26\" (2 bundles)', 1, 'set', 0)")->execute([$del]);
$db->exec("UPDATE deliveries SET status = 'dispatched', signoff_name = NULL, signoff_timestamp = NULL, signoff_signature_path = NULL, actual_delivery = NULL WHERE id = $del");

file_put_contents("$out/tokens.json", json_encode($t));
echo "Ivie's website seeded at ivieluxe. Tokens in $out/tokens.json\n";
