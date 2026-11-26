<?php
/**
 * The order a shop shows what it sells (app/helpers/storefront.php, "The
 * order a shop shows what it sells"): pins first, then the owner's order of
 * categories, then what was booked or ordered most in the last 90 days, then
 * the newest. Set from Shop Studio (/landing-page/pin, /category-order) and
 * the Catalogue (/products/toggle-pin), over HTTP to the running app.
 *   bash tests/notifications/run.sh shop/order
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const BIZ = 2;
$db = get_db();
// No sales yet: the fixture's own orders and bookings would count
exec_sql("DELETE FROM reviews");
exec_sql("DELETE FROM order_items");
exec_sql("DELETE FROM orders");
exec_sql("DELETE FROM bookings");

/** The shop's items, names only, in the order it shows them. */
function shop_names(?callable $only = null): array
{
    $biz = rows("SELECT * FROM businesses WHERE id = ?", [BIZ])[0];
    $items = storefront_catalogue($biz, storefront_settings($biz, 'published'))['items'];
    return array_values(array_column($only ? array_filter($items, $only) : $items, 'name'));
}

function shop_cats(): array
{
    $biz = rows("SELECT * FROM businesses WHERE id = ?", [BIZ])[0];
    return storefront_catalogue($biz, storefront_settings($biz, 'published'))['categories'];
}

function pins(): array
{
    return array_map('intval', array_column(rows("SELECT id FROM products WHERE business_id = ? AND pin_rank IS NOT NULL ORDER BY pin_rank", [BIZ]), 'id'));
}

/** Where $a and $b sit in a page: true when $a comes first. */
function before(string $page, string $a, string $b): bool
{
    $i = strpos($page, $a);
    $j = strpos($page, $b);
    return $i !== false && $j !== false && $i < $j;
}

// A nail salon's menu, added Acrylic first (as Vee's was). The fixture's
// three items have no category, so they come last whatever happens.
$add = $db->prepare("INSERT INTO products (id, business_id, name, category, sell_price, stock_qty, is_service, booking_mode, booking_minutes, deposit_type, deposit_value, is_active, on_shop, created_at)
                     VALUES (?, 2, ?, ?, ?, 0, 1, 'slot', 120, 'fixed', 5000, 1, 1, NOW() - INTERVAL ? DAY)");
foreach ([
    [410, 'Acrylic Long With Nail Art',   'Acrylic', 30000, 20],
    [411, 'Acrylic Medium With Nail Art', 'Acrylic', 25000, 19],
    [412, 'BIAB Overlay',                 'BIAB',    15000, 18],
    [413, 'Gel-X Long',                   'Gel-X',   28000, 17],
    [414, 'Gel-X Short',                  'gel-x',   20000, 16],   // typed in small letters: its own category, as before
    [415, 'Classic Pedicure',             'Toes',    12000, 15],
] as [$id, $name, $cat, $price, $ago]) {
    $add->execute([$id, $name, $cat, $price, $ago]);
}
$bookable = fn($i) => $i['booking'] !== null;

/* ══ The order with nothing set ═══════════════════════════════ */

section('With nothing set: categories as added, each newest first');
eq(shop_cats(), ['Acrylic', 'BIAB', 'Gel-X', 'gel-x', 'Toes'], 'categories in the order their first item was added');
eq(array_slice(shop_names(), 0, 6), ['Acrylic Medium With Nail Art', 'Acrylic Long With Nail Art', 'BIAB Overlay', 'Gel-X Long', 'Gel-X Short', 'Classic Pedicure'],
   'items by category, the newest first inside each (no longer A to Z)');
eq(array_slice(shop_names(), 6), ['Bridal styling', 'HD Closure 5x5', 'Body Wave 18in'], 'items with no category last, newest first');

section('What sells most comes first inside its category');
// Two bookings for Acrylic Long; one cancelled and one from last year for Acrylic Medium
$book = $db->prepare("INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, customer_name, customer_phone, status, created_at)
                      VALUES (2, ?, ?, ?, ?, 'slot', NOW() + INTERVAL 2 DAY, NOW() + INTERVAL 2 DAY + INTERVAL 2 HOUR, 'Ngozi Eze', '08031110000', ?, NOW() - INTERVAL ? DAY)");
foreach ([[410, 'BKG-0101', 'confirmed', 3], [410, 'BKG-0102', 'pending', 1], [411, 'BKG-0103', 'cancelled', 2], [411, 'BKG-0104', 'completed', 200]] as [$pid, $no, $status, $ago]) {
    $book->execute([$pid, 'x', $no, bin2hex(random_bytes(16)), $status, $ago]);
}
eq(array_slice(shop_names(), 0, 2), ['Acrylic Long With Nail Art', 'Acrylic Medium With Nail Art'], 'two live bookings beat none (a cancelled one and one from 200 days ago don\'t count)');
// An order for the BIAB and Gel-X Long, three times each... counted once per order
exec_sql("INSERT INTO orders (id, business_id, order_number, public_token, channel, customer_name, customer_phone, currency_code, subtotal, total, status)
          VALUES (801, 2, 'ORD-0801', ?, 'cart_pop', 'Ngozi Eze', '08031110000', 'NGN', 1, 1, 'fulfilled')", [bin2hex(random_bytes(16))]);
exec_sql("INSERT INTO orders (id, business_id, order_number, public_token, channel, customer_name, customer_phone, currency_code, subtotal, total, status)
          VALUES (802, 2, 'ORD-0802', ?, 'cart_pop', 'Ngozi Eze', '08031110000', 'NGN', 1, 1, 'rejected')", [bin2hex(random_bytes(16))]);
exec_sql("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (801, 300, 'Body Wave 18in', 1, 3, 3), (802, 301, 'HD Closure 5x5', 1, 1, 1)");
eq(array_slice(shop_names(), 6), ['Body Wave 18in', 'Bridal styling', 'HD Closure 5x5'], 'an order counts; a rejected one doesn\'t');
eq(shop_popularity($db, BIZ), [300 => 1, 410 => 2], 'counted once per order or booking, over the last 90 days');

/* ══ The owner's order ════════════════════════════════════════ */

section('Ada puts Gel-X first, from Shop Studio');
$ada = signin('ada@adashair.test');
$studio = $ada->get('landing-page');
eq($studio->status, 200, 'Studio opens');
ok(str_contains($studio->body, '"catOrder"') && str_contains($studio->body, '"categories":["Acrylic","BIAB","Gel-X","gel-x","Toes"]'), 'and knows the categories, in the shop\'s order');
$r = $ada->json('landing-page/category-order', ['categories' => ['Gel-X', 'Acrylic', 'BIAB', 'Toes']]);
eq($r->status, 200, 'saved');
eq($r->data()['message'] ?? '', 'Gel-X shows first on your shop now.', 'and she is told what changed');
eq(shop_cats(), ['Gel-X', 'gel-x', 'Acrylic', 'BIAB', 'Toes'], 'Gel-X first, "gel-x" with it (matched ignoring case)');
eq(array_slice(shop_names($bookable), 0, 4), ['Gel-X Long', 'Gel-X Short', 'Acrylic Long With Nail Art', 'Acrylic Medium With Nail Art'],
   'the booking list follows: Gel-X before Acrylic');
eq(json_decode((string)q("SELECT category_order FROM businesses WHERE id = ?", [BIZ]), true), ['Gel-X', 'Acrylic', 'BIAB', 'Toes'], 'kept as she named them');

section('Her shop shows it, no Publish needed');
$host = 'adas-hair.' . parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : '');
$shop = (new Http())->on($host)->get('/');
eq($shop->status, 200, 'the shop opens');
ok(before($shop->body, 'Gel-X Long', 'Acrylic Long With Nail Art'), 'Gel-X is listed before Acrylic');

section('A category not placed, and one she renames');
$ada->json('landing-page/category-order', ['categories' => ['Toes', '  Toes ', 'toes', '', 42]]);
eq(json_decode((string)q("SELECT category_order FROM businesses WHERE id = ?", [BIZ]), true), ['Toes'], 'each name once, blanks and non-names dropped');
eq(shop_cats(), ['Toes', 'Acrylic', 'BIAB', 'Gel-X', 'gel-x'], 'the rest follow in the order they were added');
$ada->json('landing-page/category-order', ['categories' => []]);
eq(q("SELECT category_order FROM businesses WHERE id = ?", [BIZ]), null, 'an empty list goes back to the usual order');
$ada->json('landing-page/category-order', ['categories' => ['Gel-X', 'Acrylic', 'BIAB', 'Toes']]);

section('Only the owner or an admin, and only with the session\'s token');
$chidi = signin('chidi@adashair.test');
eq($chidi->json('landing-page/category-order', ['categories' => ['Toes']])->status, 403, 'staff can\'t change the shop\'s order');
eq($ada->json('landing-page/category-order', ['categories' => ['Toes']], ['X-CSRF-Token: nope'])->status, 419, 'a forged request is refused');
eq(shop_cats()[0], 'Gel-X', 'and nothing changed');

/* ══ Pins ═════════════════════════════════════════════════════ */

section('Ada pins the pedicure from her Catalogue');
$ada->get('products');
$r = $ada->post('products/toggle-pin/415', ['on' => '1'], ['X-Requested-With: XMLHttpRequest', 'Accept: application/json']);
eq($r->status, 200, 'pinned');
eq($r->data()['message'] ?? '', 'Classic Pedicure is pinned to the top of your shop.', 'she is told');
eq(shop_names()[0], 'Classic Pedicure', 'it shows first, before every category');
$list = $ada->get('products')->body;
ok(str_contains($list, 'toggle-pin/415" data-pin="415" data-at="1"') && str_contains($list, 'landing-page?open=order'), 'her Catalogue shows it pinned, and the way to Order on your shop');

section('And the BIAB from Studio: it goes second');
$r = $ada->json('landing-page/pin', ['id' => 412, 'on' => true]);
eq($r->data()['pins'] ?? null, [415, 412], 'pins first to last');
eq($r->data()['message'] ?? '', 'BIAB Overlay is pinned to the top of your shop, number 2.', 'with its place');
eq(array_slice(shop_names(), 0, 3), ['Classic Pedicure', 'BIAB Overlay', 'Gel-X Long'], 'pins, then her categories');
eq($ada->json('landing-page/pin', ['id' => 415, 'on' => true])->data()['pins'] ?? null, [415, 412], 'pinning it again keeps its place');
$page = $ada->get('products/view/412')->body;
ok(str_contains($page, 'Pin to the top of my shop') && str_contains($page, 'Number 2 of up to 8'), 'its page says so');

section('Unpinning lets the rest move up');
$r = $ada->post('products/toggle-pin/415', ['on' => '0'], ['X-Requested-With: XMLHttpRequest', 'Accept: application/json']);
eq(pins(), [412], 'one pin left');
eq((int)q("SELECT pin_rank FROM products WHERE id = 412"), 1, 'and it is number 1 now');
eq($r->data()['message'] ?? '', 'Classic Pedicure is no longer pinned. It shows in its usual place.', 'she is told');

section('At most 8, and only what the shop shows');
$add2 = $db->prepare("INSERT INTO products (id, business_id, name, category, sell_price, stock_qty, is_service, is_active, on_shop) VALUES (?, 2, ?, 'Extras', 2000, 0, 1, 1, 1)");
foreach (range(420, 427) as $id) {
    $add2->execute([$id, 'Extra ' . $id]);
}
foreach (range(420, 426) as $id) {
    $ada->json('landing-page/pin', ['id' => $id, 'on' => true]);
}
eq(count(pins()), 8, 'eight pinned');
$r = $ada->json('landing-page/pin', ['id' => 427, 'on' => true]);
eq($r->status, 422, 'a ninth is refused');
eq($r->data()['error'] ?? '', 'You have pinned 8 already. Unpin one first, then pin this.', 'with what to do');
$r = $ada->post('products/toggle-pin/427', ['on' => '1'], ['X-Requested-With: XMLHttpRequest', 'Accept: application/json']);
eq([$r->status, $r->data()['message'] ?? ''], [422, 'You have pinned 8 already. Unpin one first, then pin this.'], 'from the Catalogue too');
// A pinned item taken off the shop frees its place at the next pin
exec_sql("UPDATE products SET on_shop = 0 WHERE id = 420");
ok(!in_array('Extra 420', shop_names(), true), 'an item off the shop is not shown, pinned or not');
eq($ada->json('landing-page/pin', ['id' => 427, 'on' => true])->status, 200, 'its place goes to the next pin');
eq(pins(), [412, 421, 422, 423, 424, 425, 426, 427], 'eight, all on the shop, in order');
$r = $ada->json('landing-page/pin', ['id' => 420, 'on' => true]);
eq([$r->status, $r->data()['error'] ?? ''], [422, 'Extra 420 isn’t on your shop. Switch on Show on my shop first, then pin it.'], 'an item off the shop can\'t be pinned');

section('Another shop\'s items are out of reach');
$kola = signin('kola@kolaprints.test');
eq($kola->json('landing-page/pin', ['id' => 413, 'on' => true])->status, 422, 'Kola can\'t pin Ada\'s item');
eq($kola->post('products/toggle-pin/413', ['on' => '1'], ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'])->status, 404, 'nor from his Catalogue');
ok(!in_array(413, pins(), true), 'and it isn\'t pinned');

/* ══ Packages and the sorting rules on their own ══════════════ */

section('The rules, item by item');
$i = fn($id, $cat, $pin = null, $at = '2026-01-01') => ['id' => $id, 'category' => $cat, 'pin' => $pin, 'created_at' => $at];
$sorted = shop_sort_items([$i(1, ''), $i(2, 'B'), $i(3, 'A'), $i(4, 'A', null, '2026-02-01'), $i(5, 'B', 2), $i(6, '', 1)], ['A', 'B'], [3 => 5]);
eq(array_column($sorted, 'id'), [6, 5, 3, 4, 2, 1], 'pins by place, then categories in order (the best seller first, then the newest), then no category');
eq(shop_categories([$i(9, 'Wigs'), $i(3, 'Bundles'), $i(5, 'wigs'), $i(7, '')], ['WIGS']), ['wigs', 'Wigs', 'Bundles'], 'placed ones first, ignoring case (two spellings by first added); then the rest');

done();
