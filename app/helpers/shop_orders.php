<?php
/**
 * Nfelia — after the sale: order tracking links and reviews
 *
 * Every shop order gets a private link, name.nfelia.com/order/{token}
 * (orders.public_token), with its progress, its items, the receipt once paid
 * and, once the order is done, a review link. "Done" means its delivery was
 * signed for, or the owner marked it fulfilled: only then is a review
 * invitation made (reviews, one per order). Reviews show on the shop once the
 * shopper answers; owners can't write or edit them.
 */

function shop_order_token(): string {
    return bin2hex(random_bytes(16));
}

/**
 * The shopper's tracking link for an order. Orders from before tracking
 * links existed get a token the first time someone asks for one.
 */
function shop_order_url(PDO $db, array $order, array $business): ?string {
    if (empty($business['subdomain']) || ($business['subdomain_status'] ?? 'active') !== 'active') {
        return null;
    }
    $token = (string)($order['public_token'] ?? '');
    if ($token === '') {
        $db->prepare("UPDATE orders SET public_token = ? WHERE id = ? AND public_token IS NULL")
           ->execute([shop_order_token(), (int)$order['id']]);
        $stmt = $db->prepare("SELECT public_token FROM orders WHERE id = ?");
        $stmt->execute([(int)$order['id']]);
        $token = (string)$stmt->fetchColumn();
    }
    return shop_url($business['subdomain']) . '/order/' . $token;
}

/**
 * An order of this shop by its tracking token, with its items, delivery and
 * receipt, or null.
 */
function shop_order_by_token(PDO $db, int $business_id, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM orders WHERE business_id = ? AND public_token = ? LIMIT 1");
    $stmt->execute([$business_id, $token]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $stmt = $db->prepare("SELECT product_id, product_name, unit_price, quantity, variant_selection, item_note, line_total
                          FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $stmt->execute([(int)$order['id']]);
    $order['items'] = $stmt->fetchAll();

    $order['delivery'] = null;
    if (!empty($order['delivery_id'])) {
        // A deleted delivery no longer says anything about the order (S16)
        $stmt = $db->prepare("SELECT id, status, signoff_timestamp FROM deliveries WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$order['delivery_id'], $business_id]);
        $order['delivery'] = $stmt->fetch() ?: null;
    }

    $order['receipt_token'] = null;
    if (!empty($order['receipt_id'])) {
        $stmt = $db->prepare("SELECT token FROM receipts WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$order['receipt_id'], $business_id]);
        $order['receipt_token'] = $stmt->fetchColumn() ?: null;
    }

    $stmt = $db->prepare("SELECT * FROM reviews WHERE order_id = ? LIMIT 1");
    $stmt->execute([(int)$order['id']]);
    $order['review'] = $stmt->fetch() ?: null;
    return $order;
}

/**
 * Whether an order is finished: the owner marked it fulfilled, or the
 * shopper signed for its delivery.
 */
function shop_order_is_done(array $order, ?array $delivery): bool {
    return ($order['status'] ?? '') === 'fulfilled'
        || ($delivery && ($delivery['status'] ?? '') === 'signed_off');
}

/**
 * Where an order stands, as steps for the tracking page:
 *   state  open | done | turned_down | cancelled
 *   steps  [['label', 'detail', 'state' => done|now|next, 'time' => ?string], …]
 */
function shop_order_steps(array $order, string $shop_name): array {
    $status   = (string)$order['status'];
    $delivery = $order['delivery'] ?? null;
    $dstatus  = (string)($delivery['status'] ?? '');
    $confirmed = in_array($status, ['approved', 'processing', 'fulfilled'], true);
    $is_delivery = ($order['fulfillment_type'] ?? '') === 'delivery';
    $done = shop_order_is_done($order, $delivery);

    if ($status === 'rejected') {
        return ['state' => 'turned_down', 'steps' => []];
    }
    if ($status === 'cancelled') {
        return ['state' => 'cancelled', 'steps' => []];
    }

    $steps = [];
    $steps[] = ['label' => 'Order received', 'detail' => '', 'state' => 'done', 'time' => (string)$order['created_at']];

    if (($order['channel'] ?? '') === 'whatsapp') {
        $steps[] = [
            'label'  => $confirmed ? 'Confirmed with you' : 'Waiting to be confirmed',
            'detail' => $confirmed ? '' : 'Send it to ' . $shop_name . ' on WhatsApp if you haven\'t yet, so they can confirm it with you.',
            'state'  => $confirmed ? 'done' : 'now',
            'time'   => $confirmed ? ($order['reviewed_at'] ?? null) : null,
        ];
    } else {
        $steps[] = [
            'label'  => $confirmed ? 'Payment confirmed' : 'Checking your payment',
            'detail' => $confirmed ? 'The shop checked your transfer.' : $shop_name . ' is checking your transfer.',
            'state'  => $confirmed ? 'done' : 'now',
            'time'   => $confirmed ? ($order['reviewed_at'] ?? null) : null,
        ];
    }

    $moving = in_array($dstatus, ['dispatched', 'in_transit', 'delivered', 'signed_off'], true);
    $steps[] = [
        'label'  => 'Being prepared',
        'detail' => '',
        'state'  => ($done || $moving) ? 'done' : ($confirmed ? 'now' : 'next'),
        'time'   => null,
    ];

    if ($is_delivery) {
        $arrived = $done || in_array($dstatus, ['delivered', 'signed_off'], true);
        $steps[] = [
            'label'  => 'On its way',
            'detail' => '',
            'state'  => $arrived ? 'done' : ($moving ? 'now' : 'next'),
            'time'   => null,
        ];
        $steps[] = [
            'label'  => 'Delivered',
            'detail' => $arrived ? '' : 'You confirm it on your phone when it arrives.',
            'state'  => $arrived ? 'done' : 'next',
            'time'   => $delivery['signoff_timestamp'] ?? null,
        ];
    } else {
        $steps[] = [
            'label'  => 'Collected',
            'detail' => $done ? '' : 'Pick it up from ' . $shop_name . '.',
            'state'  => $done ? 'done' : 'next',
            'time'   => null,
        ];
    }

    return ['state' => $done ? 'done' : 'open', 'steps' => $steps];
}

/* ──────────────────────────────────────────
 *  REVIEWS
 * ────────────────────────────────────────── */

/**
 * The review invitation for a finished order: made the first time, then
 * the same one. Null while the order isn't finished.
 */
function review_invite(PDO $db, int $order_id): ?array {
    $stmt = $db->prepare("
        SELECT o.id, o.business_id, o.customer_name, o.status, d.status AS delivery_status
        FROM orders o
        LEFT JOIN deliveries d ON d.id = o.delivery_id AND d.deleted_at IS NULL
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order || !shop_order_is_done($order, ['status' => $order['delivery_status']])) {
        return null;
    }

    $db->prepare("INSERT IGNORE INTO reviews (business_id, order_id, customer_name, token) VALUES (?, ?, ?, ?)")
       ->execute([(int)$order['business_id'], $order_id, mb_substr((string)$order['customer_name'], 0, 100), shop_order_token()]);
    $stmt = $db->prepare("SELECT * FROM reviews WHERE order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetch() ?: null;
}

function review_url(array $business, array $review): ?string {
    if (empty($business['subdomain'])) {
        return null;
    }
    return shop_url($business['subdomain']) . '/review/' . $review['token'];
}

/**
 * A shop's published reviews, newest first, with their count and average.
 */
function shop_reviews(PDO $db, int $business_id, int $limit = 6): array {
    $stmt = $db->prepare("SELECT COUNT(*), AVG(rating) FROM reviews WHERE business_id = ? AND status = 'published'");
    $stmt->execute([$business_id]);
    [$count, $average] = $stmt->fetch(PDO::FETCH_NUM);

    $stmt = $db->prepare("
        SELECT customer_name, rating, comment, submitted_at
        FROM reviews
        WHERE business_id = ? AND status = 'published'
        ORDER BY submitted_at DESC, id DESC
        LIMIT " . max(1, min(50, $limit)));
    $stmt->execute([$business_id]);

    return [
        'count'   => (int)$count,
        'average' => $count ? round((float)$average, 1) : 0.0,
        'items'   => $stmt->fetchAll(),
    ];
}

/**
 * "Bisi Adeyemi" → "Bisi A."; a shopper's full name never appears on a shop.
 */
function review_display_name(string $full): string {
    $parts = preg_split('/\s+/u', trim($full)) ?: [];
    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
    if (!$parts) {
        return 'A customer';
    }
    $first = mb_convert_case(mb_substr($parts[0], 0, 30), MB_CASE_TITLE);
    return count($parts) > 1 ? $first . ' ' . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) . '.' : $first;
}
