<?php
/**
 * Several services in one appointment (app/helpers/bookings.php, "Several
 * services in one appointment"): a shopper adds services to a time-slot
 * booking; it stays one booking, as long as them all, their prices added up
 * and one deposit, the highest of theirs. Over HTTP to the running app.
 *   bash tests/notifications/run.sh shop/visit
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const BIZ = 2;
$db = get_db();
exec_sql("DELETE FROM booking_items");
exec_sql("DELETE FROM bookings");

// A salon's menu: four services by the hour, and the fixture's bridal day (booked by the day)
$add = $db->prepare("INSERT INTO products (id, business_id, name, category, sell_price, stock_qty, is_service, booking_mode, booking_minutes, deposit_type, deposit_value, is_active, on_shop)
                     VALUES (?, 2, ?, ?, ?, 0, 1, 'slot', ?, ?, ?, 1, 1)");
foreach ([
    [510, 'Acrylic Long',   'Acrylic', 25000, 120, 'fixed',   5000],
    [511, 'Gel Toes',       'Toes',    10000,  60, 'fixed',   5000],
    [512, 'BIAB Overlay',   'BIAB',    40000,  60, 'percent',   20],   // 8,000: the highest deposit
    [513, 'Lash Lift',      'Lashes',   8000,  45, 'none',       0],
] as [$id, $name, $cat, $price, $minutes, $dep, $value]) {
    $add->execute([$id, $name, $cat, $price, $minutes, $dep, $value]);
}
// Open 09:00 to 18:00 every day, one client at a time, booked on the hour
$open = ['open' => '09:00', 'close' => '18:00'];
exec_sql("UPDATE businesses SET booking_settings = ? WHERE id = 2",
         [json_encode(['hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $open), 'notice' => 0, 'ahead' => 60, 'step' => 60, 'at_once' => 1])]);
$date = date('Y-m-d', strtotime('+3 days'));

$biz = fn() => rows("SELECT * FROM businesses WHERE id = ?", [BIZ])[0];
$catalogue = storefront_catalogue($biz(), storefront_settings($biz(), 'published'));
$item = fn(int $id) => array_column($catalogue['items'], null, 'id')[$id];

/* ══ The rules ════════════════════════════════════════════════ */

section('One visit: times added up, prices added up, the highest deposit');
$visit = booking_combine($catalogue, $item(510), [511]);
eq([$visit['info']['minutes'], $visit['price'], $visit['deposit'], $visit['name']], [180, 35000.0, 5000.0, 'Acrylic Long + Gel Toes'],
   'Acrylic and Gel Toes: 3 hours, ₦35,000, one ₦5,000 deposit');
eq(booking_combine($catalogue, $item(510), [511, 512])['deposit'], 8000.0, 'a 20% deposit on ₦40,000 is the highest: ₦8,000 once');
eq(booking_combine($catalogue, $item(513), [])['deposit'], 0.0, 'a service with no deposit, alone, takes none');
eq(booking_combine($catalogue, $item(513), [511])['deposit'], 5000.0, 'with one that takes one, the visit takes it');
ok(booking_combine($catalogue, $item(510), [302]) === null, 'a whole-day booking can\'t be added to a visit');
ok(booking_combine($catalogue, $item(302), [510]) === null, 'nor can anything be added to one');
eq(booking_parse_extras('511, 510,511,x,0', 510), [511], 'each added service once, never the first one again');
eq(booking_parse_extras([1, 2, 3, 4, 5, 6], 99), [1, 2, 3, 4], 'at most ' . BOOKING_MAX_EXTRAS . ' added');
eq(array_column(booking_extras($catalogue, $item(510)), 'id'), [511, 512, 513], 'what can be added: the other services by the hour');

/* ══ The booking page ═════════════════════════════════════════ */

section('The booking page offers the other services');
$host = 'adas-hair.' . parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : '');
$shopper = (new Http())->on($host);
$page = $shopper->get(booking_page_path($item(510)))->body;
ok(str_contains($page, 'Add another service to this visit') && str_contains($page, 'value="511" data-addon') && !str_contains($page, 'value="302" data-addon'),
   'Gel Toes can be added; the bridal day can\'t');
$page = $shopper->get(booking_page_path($item(302)))->body;
ok(!str_contains($page, 'data-addon'), 'a whole-day booking offers none');

section('Free times are for the whole visit');
$last = fn(array $times) => end($times)['t'] ?? null;
$alone = $shopper->get("book/510/free?date={$date}")->data()['times'] ?? [];
$both  = $shopper->get("book/510/free?date={$date}&with=511")->data()['times'] ?? [];
eq([$last($alone), $last($both)], ['16:00', '15:00'], 'alone it can start at 4pm; with Gel Toes the last start is 3pm, to finish by 6pm');
eq($shopper->get("book/510/free?date={$date}&with=302")->status, 409, 'asking with a service that can\'t be added is refused');

/* ══ Booking it ═══════════════════════════════════════════════ */

section('Ngozi books Acrylic and Gel Toes together');
$details = ['customer_name' => 'Ngozi Eze', 'customer_phone' => '08031110000'];
$r = $shopper->post('book/510', $details + ['date' => $date, 'time' => '10:00', 'with' => '511']);
eq($r->status, 200, 'it goes through ' . ($r->data()['error'] ?? ''));
eq([$r->data()['total'] ?? null, $r->data()['deposit'] ?? null, $r->data()['service'] ?? null], [35000, 5000, 'Acrylic Long + Gel Toes'], 'one total, one deposit');
$b = rows("SELECT * FROM bookings WHERE booking_number = ?", [$r->data()['booking_number'] ?? ''])[0] ?? [];
eq([$b['product_id'] ?? null, $b['product_name'] ?? null, $b['starts_at'] ?? null, $b['ends_at'] ?? null, (float)($b['deposit_due'] ?? 0)],
   [510, 'Acrylic Long + Gel Toes', $date . ' 10:00:00', $date . ' 13:00:00', 5000.0], 'one booking, 10am to 1pm');
eq(array_map(fn($l) => [$l['product_id'], $l['product_name'], (int)$l['minutes'], (float)$l['unit_price'], (float)$l['deposit']],
             rows("SELECT * FROM booking_items WHERE booking_id = ? ORDER BY sort_order", [$b['id'] ?? 0])),
   [[510, 'Acrylic Long', 120, 25000.0, 5000.0], [511, 'Gel Toes', 60, 10000.0, 5000.0]], 'with a line for each service');
$told = bell(20, 'booking.new');
eq($told[0]['title'] ?? '', 'New booking: Acrylic Long + Gel Toes', 'the owner is told about both');

section('The three hours are held');
$times = array_column($shopper->get("book/511/free?date={$date}")->data()['times'] ?? [], 't');
ok(!array_intersect(['10:00', '11:00', '12:00'], $times) && in_array('13:00', $times, true), 'nobody else can book 10am to 1pm; 1pm is free');
$r2 = $shopper->post('book/513', ['customer_name' => 'Bola Ade', 'customer_phone' => '08032220000', 'date' => $date, 'time' => '12:00']);
eq($r2->status, 409, 'a lash lift at noon is refused');

section('Both see each service');
$mine = $shopper->get('booking/' . ($b['public_token'] ?? ''))->body;
ok(str_contains($mine, '2 services in one visit') && str_contains($mine, '<li>Gel Toes</li>'), 'Ngozi\'s booking page lists them');
$ada = signin('ada@adashair.test');
$owner = $ada->get('bookings/view/' . (int)($b['id'] ?? 0))->body;
ok(str_contains($owner, 'Gel Toes · 1 hour') && str_contains($owner, '₦10,000') && str_contains($owner, '₦25,000'), 'Ada\'s view shows each, with its time and price');

section('A booking of one service is as before');
$r3 = $shopper->post('book/512', ['customer_name' => 'Tola Ige', 'customer_phone' => '08033330000', 'date' => $date, 'time' => '15:00']);
eq([$r3->status, $r3->data()['deposit'] ?? null], [200, 8000], 'BIAB alone: its own 20% deposit');
eq((int)q("SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id WHERE b.booking_number = ?", [$r3->data()['booking_number'] ?? '']), 0, 'and no lines of its own');

section('Refused when an added service can\'t be booked');
$r4 = $shopper->post('book/510', ['customer_name' => 'Femi Ola', 'customer_phone' => '08034440000', 'date' => $date, 'time' => '16:00', 'with' => '302']);
eq([$r4->status, $r4->data()['error'] ?? ''], [409, 'One of the services you added can\'t be booked any more. Reload the page and pick again.'], 'a day booking as an extra');
$r5 = $shopper->post('book/510', ['customer_name' => 'Femi Ola', 'customer_phone' => '08034440000', 'date' => $date, 'time' => '16:00', 'with' => '511']);
eq($r5->status, 409, 'Acrylic and Gel Toes at 4pm would end after closing: refused');

section('Gel Toes counts as booked, for the shop\'s order');
eq(array_intersect_key(shop_popularity($db, BIZ), array_flip([510, 511, 512, 513])), [510 => 1, 512 => 1, 511 => 1], 'once each: the visit\'s first service and the one added');

done();
