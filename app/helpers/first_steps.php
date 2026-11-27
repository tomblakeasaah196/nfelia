<?php
/**
 * "Get started" on Home: the first things that make Nfelia useful, each
 * ticked off by itself as soon as it is done, for the owner and admins.
 *
 * Why (27 Nov 2026): of the five businesses that found Nfelia on their own
 * in November, four finished setting up and only one ever used it. After
 * setup, Home was a page of empty cards with nothing saying where to start.
 *
 * The card goes when every step is done, or when the person presses Hide
 * (users.first_steps_hidden_at).
 */

/**
 * The steps for this business and person, in order: each
 * ['key', 'title', 'hint', 'url', 'done'].
 */
function first_steps(PDO $db, array $business, int $user_id, bool $owner): array
{
    $biz  = (int)$business['id'];
    $one  = function (string $sql) use ($db, $biz): bool {
        $stmt = $db->prepare($sql);
        $stmt->execute([$biz]);
        return (bool)$stmt->fetchColumn();
    };
    $service = ($business['business_type'] ?? '') === 'service';
    $sold = $one("SELECT 1 FROM receipts WHERE business_id = ? LIMIT 1")
         || $one("SELECT 1 FROM invoices WHERE business_id = ? LIMIT 1")
         || $one("SELECT 1 FROM orders WHERE business_id = ? LIMIT 1")
         || $one("SELECT 1 FROM bookings WHERE business_id = ? LIMIT 1");
    $push = $db->prepare("SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1");
    $push->execute([$user_id]);

    $steps = [
        ['key' => 'sell', 'title' => $service ? 'Add your services' : 'Add what you sell',
         'hint' => $service ? 'Each service with its price, so bookings and receipts fill themselves in.' : 'Your products with their prices, so orders and receipts fill themselves in.',
         'url' => 'products/create', 'done' => $one("SELECT 1 FROM products WHERE business_id = ? LIMIT 1")],
        ['key' => 'customer', 'title' => 'Add a customer',
         'hint' => 'Their name and WhatsApp number, so you can send them receipts and reminders.',
         'url' => 'customers/create', 'done' => $one("SELECT 1 FROM customers WHERE business_id = ? LIMIT 1")],
        ['key' => 'sale', 'title' => $service ? 'Record your first sale or booking' : 'Record your first sale',
         'hint' => 'An order, a receipt or an invoice: Nfelia keeps the count of what came in.',
         'url' => 'orders/create', 'done' => $sold],
    ];
    if ($owner) {
        $steps[] = ['key' => 'shop', 'title' => 'Put your shop online',
                    'hint' => 'Your own page where customers see what you sell and order on WhatsApp.',
                    'url' => 'landing-page', 'done' => $one("SELECT 1 FROM businesses WHERE id = ? AND storefront_published IS NOT NULL")];
    }
    $steps[] = ['key' => 'phone', 'title' => 'Get alerts on your phone',
                'hint' => 'Know the moment an order, a booking or a payment comes in.',
                'url' => 'more#notifications', 'done' => (bool)$push->fetchColumn()];
    return $steps;
}

/** Whether the card shows: an owner or admin who hasn't hidden it, with a step left. */
function first_steps_show(PDO $db, array $steps, int $user_id): bool
{
    if (!array_filter($steps, fn($s) => !$s['done'])) {
        return false;
    }
    // SELECT *: the page can run a moment before the migration that adds the column
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return empty(($stmt->fetch() ?: [])['first_steps_hidden_at']);
}
