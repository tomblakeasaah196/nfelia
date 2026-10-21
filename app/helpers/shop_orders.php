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
    // A shop with a live link has tracking links, also while a new name it
    // asked for is being checked (W7)
    if (empty($business['subdomain'])) {
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

    $stmt = $db->prepare("SELECT oi.product_id, oi.product_name, oi.unit_price, oi.quantity, oi.variant_selection, oi.item_note, oi.line_total,
                                 p.image_path
                          FROM order_items oi
                          LEFT JOIN products p ON p.id = oi.product_id AND p.business_id = ? AND p.deleted_at IS NULL
                          WHERE oi.order_id = ? ORDER BY oi.id ASC");
    $stmt->execute([$business_id, (int)$order['id']]);
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
    $confirmed = in_array($status, ['approved', 'processing', 'ready', 'fulfilled'], true);
    $is_delivery = ($order['fulfillment_type'] ?? '') === 'delivery';
    $done = shop_order_is_done($order, $delivery);
    // 'ready' is the owner's "Mark ready": ready for pickup, or on its way
    // when it is delivered (app/helpers/orders.php)
    $ready = $status === 'ready';

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
    } elseif (($order['payment_method'] ?? 'none') === 'none') {
        // No transfer was made (the shop has no bank account on its page):
        // nothing to check, only to confirm (W15)
        $steps[] = [
            'label'  => $confirmed ? 'Confirmed' : 'Waiting to be confirmed',
            'detail' => $confirmed ? '' : $shop_name . ' will contact you on the number you gave.',
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

    $moving = in_array($dstatus, ['dispatched', 'in_transit', 'delivered', 'signed_off'], true) || ($ready && $is_delivery);
    $steps[] = [
        'label'  => 'Being prepared',
        'detail' => '',
        'state'  => ($done || $moving || $ready) ? 'done' : ($confirmed ? 'now' : 'next'),
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
            'label'  => 'Ready for pickup',
            'detail' => $ready ? 'Pick it up from ' . $shop_name . '.' : '',
            'state'  => $done ? 'done' : ($ready ? 'now' : 'next'),
            'time'   => null,
        ];
        $steps[] = [
            'label'  => 'Collected',
            'detail' => '',
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

/**
 * Tell the shopper their order moved on, by email when they gave one: once
 * per step (the outbox's dedupe key), after the page has answered, retried
 * if the mail server is down. The order page also offers the same news as a
 * ready-written WhatsApp message (shop_order_whatsapp_text()).
 *   $state: confirmed, preparing, ready (ready for pickup; on_the_way for a
 *   delivery: chosen here), on_the_way, delivered (or collected, for pickup:
 *   chosen here), cancelled, turned_down
 */
function shop_order_tell(PDO $db, int $order_id, string $state, string $message = ''): void {
    try {
        $stmt = $db->prepare("SELECT o.*, b.name AS shop_name FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order || empty($order['customer_email'])) {
            return;
        }
        if ($state === 'delivered' && ($order['fulfillment_type'] ?? '') !== 'delivery') {
            $state = 'collected';
        }
        if ($state === 'ready' && ($order['fulfillment_type'] ?? '') === 'delivery') {
            $state = 'on_the_way';
        }
        $business = business_with_shop_link(['id' => (int)$order['business_id'], 'name' => $order['shop_name']]);
        $review = in_array($state, ['delivered', 'collected'], true) ? review_invite($db, $order_id) : null;
        $subject = match ($state) {
            'confirmed'  => 'Your order ' . $order['order_number'] . ' is confirmed',
            'preparing'  => 'Your order ' . $order['order_number'] . ' is being prepared',
            'ready'      => 'Your order ' . $order['order_number'] . ' is ready for pickup',
            'on_the_way' => 'Your order ' . $order['order_number'] . ' is on its way',
            'delivered'  => 'Your order ' . $order['order_number'] . ' has been delivered',
            'collected'  => 'Thank you for collecting order ' . $order['order_number'],
            'cancelled'  => 'Your order ' . $order['order_number'] . ' was cancelled',
            default      => 'About your order ' . $order['order_number'],
        } . ' — ' . $order['shop_name'];
        notify_email((int)$order['business_id'], (string)$order['customer_email'], (string)$order['customer_name'], $subject, 'order_update', [
            'state'        => $state,
            'first_name'   => mail_first_name((string)$order['customer_name']),   // plain letters only: anyone can type anything there
            'shop_name'    => (string)$order['shop_name'],
            'order_number' => (string)$order['order_number'],
            'order_url'    => shop_order_url($db, $order, $business),
            'review_url'   => $review && $review['status'] === 'invited' ? review_url($business, $review) : null,
            'message'      => $message,
            // The whole order, at the start and at the end (a record of what they got)
            'details'      => in_array($state, ['confirmed', 'delivered', 'collected'], true) ? order_mail_details($db, $order) : null,
        ], 'order:' . $order_id . ':' . $state, 'shopper.order_' . $state);
    } catch (Throwable $e) {
        error_log('Nfelia shop_order_tell ' . $order_id . ' ' . $state . ': ' . $e->getMessage());
    }
}

/**
 * The order as its emails show it (views/emails/_order_details.php): each
 * line with its photo, options, how many and price; the money (paid, the
 * balance and when it is due); and how and when the customer gets it. All
 * figures are words already, as the email is drawn later from the outbox.
 * Item notes stay out: on a shop order they are the shopper's own words.
 * $with_where adds the delivery address (once the shop has the order in hand).
 */
function order_mail_details(PDO $db, array $order, bool $with_where = true): array {
    $biz = (int)$order['business_id'];
    $stmt = $db->prepare("SELECT currency_symbol FROM businesses WHERE id = ?");
    $stmt->execute([$biz]);
    $sym = trim((string)$stmt->fetchColumn()) ?: '₦';
    $money = fn($n) => planner_money((float)$n, $sym);
    $day = fn(?string $d) => $d ? date('l j F', strtotime($d)) : '';

    $stmt = $db->prepare("SELECT oi.product_name, oi.variant_selection, oi.quantity, oi.unit_price, oi.line_total, p.image_path
                          FROM order_items oi
                          LEFT JOIN products p ON p.id = oi.product_id AND p.business_id = ? AND p.deleted_at IS NULL
                          WHERE oi.order_id = ? ORDER BY oi.sort_order, oi.id");
    $stmt->execute([$biz, (int)$order['id']]);
    $lines = array_map(fn($l) => [
        'name'    => (string)$l['product_name'],
        'options' => (string)($l['variant_selection'] ?? ''),
        'each'    => fmt_qty((float)$l['quantity']) . ' × ' . $money($l['unit_price']),
        'total'   => $money($l['line_total']),
        'photo'   => order_mail_photo($l['image_path'] ?? null),
    ], $stmt->fetchAll());

    $inv = order_invoice($db, $biz, $order);
    $total = $inv ? (float)$inv['total'] : (float)$order['total'];
    $paid  = $inv ? (float)$inv['amount_paid'] : 0.0;
    $due   = $inv ? max(0, (float)$inv['balance_due']) : max(0, $total);
    $now   = $inv && ($inv['deliver_mode'] ?? '') === 'immediate';
    $when  = $now ? '' : $day($inv['deliver_by'] ?? null);
    $how   = match (true) {
        $now                                         => 'Taken in the shop',
        ($order['fulfillment_type'] ?? '') === 'delivery' => 'Delivery',
        default                                      => 'Pickup',
    };
    return [
        'lines'    => $lines,
        'discount' => (float)($order['discount_amount'] ?? 0) > 0 ? '−' . $money($order['discount_amount']) : '',
        'total'    => $money($total),
        'paid'     => $paid > 0 ? $money($paid) : '',
        'balance'  => $due > 0 ? $money($due) : '',
        'due_by'   => $due > 0 && $inv ? $day($inv['due_date'] ?? null) : '',
        'how'      => $how,
        'when'     => $when,
        'address'  => $with_where && $how === 'Delivery' ? trim((string)($order['delivery_address'] ?? '')) : '',
    ];
}

/** The same news as shop_order_tell(), ready to send on WhatsApp from the order page. */
function shop_order_whatsapp_text(array $order, string $shop_name, ?string $track_url, ?string $delivery_status = null): string {
    $first = preg_split('/\s+/u', trim((string)$order['customer_name']))[0] ?: 'there';
    $num   = $order['order_number'];
    $moving = in_array($delivery_status, ['dispatched', 'in_transit'], true)
           || ($order['status'] === 'ready' && ($order['fulfillment_type'] ?? '') === 'delivery');
    $news = match (true) {
        $order['status'] === 'rejected'   => "we're sorry, we couldn't take your order {$num}.",
        $order['status'] === 'cancelled'  => "your order {$num} has been cancelled.",
        $order['status'] === 'fulfilled'  => ($order['fulfillment_type'] === 'delivery' ? "your order {$num} has been delivered" : "thank you for collecting your order {$num}") . '. We hope you love it!',
        $moving                           => "your order {$num} is on its way to you.",
        $order['status'] === 'ready'      => "your order {$num} is ready for pickup.",
        $order['status'] === 'processing' => "we're getting your order {$num} ready.",
        $order['status'] === 'approved'   => "your order {$num} is confirmed.",
        default                           => "we've received your order {$num}.",
    };
    return 'Hello ' . $first . ', this is ' . $shop_name . ': ' . $news
        . ($track_url && !in_array($order['status'], ['rejected', 'cancelled'], true) ? ' Follow it here: ' . $track_url : '');
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
