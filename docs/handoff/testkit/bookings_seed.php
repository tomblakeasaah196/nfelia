<?php
// Bookings test data, on top of storefront_seed.php (run that first). Local
// database only: it empties bookings and tasks.
//
//   Glow by Temi (4)     time slots: braids 3 hours with a 30% deposit, lashes
//                        1 hour with a ₦5,000 deposit, gel polish 90 minutes
//                        with no deposit. Open every day; no notice needed.
//   Lola & Co. (7)       whole days: two packages, one a day, ₦100,000 deposit.
//   Haven Shortlets (10) nights: two apartments, 50% deposit.
//   Chinedu & Co. (11)   AC servicing, 90 minutes, no deposit, two at once.
$db = new PDO('mysql:host=localhost;dbname=smartqaq_nfelia_core;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DELETE FROM bookings');
$db->exec('DELETE FROM tasks');
$db->exec('UPDATE businesses SET booking_number_seq = 0, booking_settings = NULL');

$set = $db->prepare("UPDATE products SET booking_mode = ?, booking_minutes = ?, booking_capacity = ?, deposit_type = ?, deposit_value = ? WHERE business_id = ? AND name = ?");
foreach ([
    ['slot', 180, 1, 'percent', 30, 4, 'Knotless braids, medium, waist length'],
    ['slot', 60, 1, 'fixed', 5000, 4, 'Classic lash set'],
    ['slot', 90, 1, 'none', null, 4, 'Gel polish, hands'],
    ['day', null, 1, 'fixed', 100000, 7, 'Intimate'],
    ['day', null, 1, 'fixed', 100000, 7, 'Classic'],
    ['nights', null, 1, 'percent', 50, 10, 'One-bedroom apartment'],
    ['nights', null, 1, 'percent', 50, 10, 'Studio'],
    ['slot', 90, 1, 'none', null, 11, 'AC servicing'],
] as $row) {
    $set->execute($row);
}

$every = fn(string $open, string $close) => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => $open, 'close' => $close]);
$db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = ?")->execute([json_encode(['hours' => $every('09:00', '19:00'), 'notice' => 0, 'ahead' => 60, 'step' => 30, 'at_once' => 1]), 4]);
$db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = ?")->execute([json_encode(['hours' => $every('08:00', '22:00'), 'notice' => 24, 'ahead' => 365, 'step' => 60, 'at_once' => 1]), 7]);
$db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = ?")->execute([json_encode(['hours' => $every('00:00', '23:59'), 'notice' => 0, 'ahead' => 180, 'checkin' => '14:00', 'checkout' => '12:00']), 10]);
$db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = ?")->execute([json_encode(['hours' => $every('08:00', '17:00'), 'notice' => 0, 'ahead' => 30, 'step' => 30, 'at_once' => 2]), 11]);

$bank = $db->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_number, account_name, is_default, sort_order) VALUES (?, ?, ?, ?, 1, 1)");
foreach ([[4, 'Kuda', '2001234567', 'Glow by Temi'], [7, 'Zenith Bank', '1012345678', 'Lola and Co Events'], [10, 'GTBank', '0223344556', 'Haven Shortlets Ltd']] as $b) {
    if (!(int)$db->query('SELECT COUNT(*) FROM bank_accounts WHERE business_id = ' . (int)$b[0])->fetchColumn()) {
        $bank->execute($b);
    }
}
echo "Bookings seeded: Glow, Lola, Haven and Chinedu take bookings.\n";
