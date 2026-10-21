<?php
/**
 * Nfelia — the order: every sale starts as one (docs/ORDERS_HUB.md).
 *
 * An order holds a sale together: its one invoice (the only owner of money),
 * the payments on it, each with its receipt, its delivery note, its debt
 * while something is owed, refunds, money kept for the customer's next
 * order, and its history (order_events, the Log).
 *
 * Every function that changes something runs inside the caller's
 * transaction when there is one, else its own, and locks the order row
 * first (SELECT ... FOR UPDATE), so a double tap or two people at once
 * never do the same thing twice. What the person needs to hear comes back
 * as a DomainException in plain words. Shoppers and the team are told once
 * the work is saved: after this file's own commit, or straight away inside
 * the caller's transaction, which then commits or rolls back with it.
 *
 * Money rules (section 3): stock only ever moves under the invoice, through
 * stock_settle(); every payment on an order's invoice makes a receipt; an
 * order with a balance has one receivable debt, kept in step here.
 */

/* ══════════════════════════════════════════════════════════════
 *  READING
 * ══════════════════════════════════════════════════════════════ */

function order_find(PDO $db, int $biz, int $order_id, bool $lock = false): ?array {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND business_id = ?" . ($lock ? " FOR UPDATE" : ""));
    $stmt->execute([$order_id, $biz]);
    return $stmt->fetch() ?: null;
}

/** The order an invoice belongs to: invoices.order_id, else an older shop order that points at it. */
function order_by_invoice(PDO $db, int $biz, int $invoice_id): ?array {
    $stmt = $db->prepare("
        SELECT o.* FROM invoices i
        JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
        WHERE i.id = ? AND i.business_id = ?
    ");
    $stmt->execute([$invoice_id, $biz]);
    if ($order = $stmt->fetch()) {
        return $order;
    }
    $stmt = $db->prepare("SELECT * FROM orders WHERE invoice_id = ? AND business_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$invoice_id, $biz]);
    return $stmt->fetch() ?: null;
}

/** An order's lines, in the order they were typed. */
function order_lines(PDO $db, int $order_id): array {
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY sort_order, id");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

/** An order's one invoice: the latest that isn't cancelled, else the latest. */
function order_invoice(PDO $db, int $biz, array $order): ?array {
    $stmt = $db->prepare("
        SELECT * FROM invoices
        WHERE business_id = ? AND (order_id = ? OR id = ?)
        ORDER BY status = 'cancelled', id DESC
        LIMIT 1
    ");
    $stmt->execute([$biz, (int)$order['id'], (int)($order['invoice_id'] ?? 0)]);
    return $stmt->fetch() ?: null;
}

/** Something happened to an order: a line in its Log, with who and, in $data, what it was before. */
function order_log(PDO $db, int $biz, int $order_id, string $kind, string $text, ?int $user_id = null, array $data = []): void {
    $db->prepare("INSERT INTO order_events (business_id, order_id, kind, text, data, user_id) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([$biz, $order_id, mb_substr($kind, 0, 30), mb_substr($text, 0, 500),
                  $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $user_id ?: null]);
}

/** An order's Log, oldest first, each with who did it (user_name) and its data as an array. */
function order_events(PDO $db, int $biz, int $order_id): array {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name AS user_name
        FROM order_events e
        LEFT JOIN users u ON u.id = e.user_id AND u.business_id = e.business_id
        WHERE e.order_id = ? AND e.business_id = ?
        ORDER BY e.id ASC
    ");
    $stmt->execute([$order_id, $biz]);
    $events = $stmt->fetchAll();
    foreach ($events as &$event) {
        $event['data'] = $event['data'] !== null ? (json_decode((string)$event['data'], true) ?: []) : [];
    }
    unset($event);
    return $events;
}

/**
 * Orders for a list, newest first, each with its invoice's figures (inv_id,
 * inv_number, inv_total, inv_paid, inv_balance, inv_status) and its delivery
 * note's status (delivery_status), as views/partials/order_row.php expects.
 *   $f: q (number, name or phone), view (confirm | progress | balance | done),
 *       customer_id
 */
function order_rows(PDO $db, int $biz, array $f = [], int $limit = 20, int $offset = 0): array {
    [$where, $params] = order__list_where($f);
    $stmt = $db->prepare("SELECT * FROM (" . order__list_sql() . ") x WHERE {$where}
                          ORDER BY x.created_at DESC, x.id DESC LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset));
    $stmt->execute(array_merge([$biz], $params));
    return $stmt->fetchAll();
}

/** How many orders order_rows() would list for each view, and the balance left on them: all, confirm, progress, balance, done, balance_total, matching. */
function order_counts(PDO $db, int $biz, array $f = []): array {
    [$where, $params] = order__list_where(['view' => ''] + $f);
    [$view_where, $view_params] = order__list_where($f);
    $v = order__list_views();
    $stmt = $db->prepare("
        SELECT COUNT(*) AS `all`,
               SUM({$v['confirm']}) AS confirm, SUM({$v['progress']}) AS progress,
               SUM({$v['balance']}) AS balance, SUM({$v['done']}) AS done,
               COALESCE(SUM(CASE WHEN {$v['balance']} THEN x.inv_balance END), 0) AS balance_total,
               SUM({$view_where}) AS matching
        FROM (" . order__list_sql() . ") x WHERE {$where}");
    $stmt->execute(array_merge($view_params, [$biz], $params));
    $row = $stmt->fetch() ?: [];
    return array_map(fn($n) => $n === null ? 0 : (str_contains((string)$n, '.') ? (float)$n : (int)$n), $row);
}

/* ══════════════════════════════════════════════════════════════
 *  CUSTOMERS
 * ══════════════════════════════════════════════════════════════ */

/**
 * The business's one hidden "Walk-in customer", for quick sales to someone
 * who gives no name (an invoice needs a customer). Made the first time.
 */
function order_walk_in_customer(PDO $db, int $biz): int {
    $find = $db->prepare("SELECT id FROM customers WHERE business_id = ? AND is_walk_in = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1");
    $find->execute([$biz]);
    if ($id = (int)$find->fetchColumn()) {
        return $id;
    }
    return order__tx($db, function () use ($db, $biz, $find) {
        // One at a time per business, so two first quick sales make one
        $db->prepare("SELECT id FROM businesses WHERE id = ? FOR UPDATE")->execute([$biz]);
        $find->execute([$biz]);
        if ($id = (int)$find->fetchColumn()) {
            return $id;
        }
        $db->prepare("INSERT INTO customers (business_id, full_name, notes, is_walk_in) VALUES (?, 'Walk-in customer', ?, 1)")
           ->execute([$biz, 'Quick sales to people who gave no name. Hidden from Clients.']);
        return (int)$db->lastInsertId();
    });
}

/**
 * The customer with this phone number, else a new one. Numbers are compared
 * by their last ten digits, so 0803 111 0000 and +234 803 111 0000 are the
 * same person. An email they gave fills in one the customer didn't have.
 */
function order_match_customer(PDO $db, int $biz, string $name, ?string $phone, ?string $email): int {
    $digits = (string)preg_replace('/\D/', '', (string)$phone);
    $email  = trim((string)$email);
    $email  = filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 255) : '';
    if (strlen($digits) >= 7) {
        $stmt = $db->prepare("
            SELECT id, email FROM customers
            WHERE business_id = ? AND deleted_at IS NULL AND is_walk_in = 0
              AND RIGHT(REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', ''), 10) = ?
            ORDER BY id LIMIT 1
        ");
        $stmt->execute([$biz, substr($digits, -10)]);
        if ($found = $stmt->fetch()) {
            if ($email !== '' && empty($found['email'])) {
                $db->prepare("UPDATE customers SET email = ? WHERE id = ? AND business_id = ?")->execute([$email, $found['id'], $biz]);
            }
            return (int)$found['id'];
        }
    }
    $name = trim($name) !== '' ? trim($name) : 'Customer';
    $db->prepare("INSERT INTO customers (business_id, full_name, phone, email) VALUES (?, ?, ?, ?)")
       ->execute([$biz, mb_substr($name, 0, 255), trim((string)$phone) !== '' ? mb_substr(trim((string)$phone), 0, 20) : null, $email ?: null]);
    return (int)$db->lastInsertId();
}

/** A quantity as people write it: 1, 2.5, 0.75. */
function fmt_qty(float $q): string {
    return rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
}

/* ══════════════════════════════════════════════════════════════
 *  MAKING AND CONFIRMING
 * ══════════════════════════════════════════════════════════════ */

/**
 * A new order typed in by the owner or staff: a manual order, a quick sale,
 * or one made from a quote. It starts Confirmed (its invoice is made now),
 * and a sale collected on the spot is done straight away. Returns the order.
 *   $in: channel manual|quick|quote; customer_id?; customer_name; customer_phone?;
 *        customer_email?; items [[product_id?, name, quantity, unit_price, note?]];
 *        discount; fulfillment now|pickup|delivery; deliver_by?; delivery_address?;
 *        paid_now; method; reference?; pay_by?; quotation_id?; note?; use_kept
 */
function order_create(PDO $db, int $biz, array $in, int $user_id): array {
    $channel     = in_array($in['channel'] ?? '', ['manual', 'quick', 'quote'], true) ? $in['channel'] : 'manual';
    $fulfillment = in_array($in['fulfillment'] ?? '', ['now', 'pickup', 'delivery'], true) ? $in['fulfillment'] : 'now';
    $method      = in_array($in['method'] ?? '', ['cash', 'bank_transfer', 'mobile_money', 'pos', 'other'], true) ? $in['method'] : 'cash';
    $deliver_by  = $fulfillment === 'now' ? planner_today() : trim((string)($in['deliver_by'] ?? ''));
    if ($deliver_by !== '' && !planner_valid_date($deliver_by)) {
        throw new DomainException('Pick the day they get it.');
    }
    $pay_by = trim((string)($in['pay_by'] ?? ''));
    if ($pay_by !== '' && !planner_valid_date($pay_by)) {
        throw new DomainException('Pick the day the balance is due.');
    }
    $phone = trim((string)($in['customer_phone'] ?? ''));
    if ($phone !== '' && ($problem = phone_problem($phone)) !== null) {
        throw new DomainException($problem);
    }
    $email = trim((string)($in['customer_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Check the email address: it doesn\'t look right.');
    }

    return order__tx($db, function () use ($db, $biz, $in, $user_id, $channel, $fulfillment, $method, $deliver_by, $pay_by, $phone, $email) {
        $business = order__business($db, $biz);
        $symbol   = (string)($business['currency_symbol'] ?? '₦');
        $lines    = order__clean_items($db, $biz, (array)($in['items'] ?? []));
        $subtotal = money2(array_sum(array_column($lines, 'line_total')));
        $discount = money2((float)($in['discount'] ?? 0));
        if ($discount < 0) {
            throw new DomainException('A discount can\'t be below 0.');
        }
        if ($discount > $subtotal) {
            throw new DomainException('The discount can be at most what the items come to, ' . planner_money($subtotal, $symbol) . '.');
        }
        $total    = money2($subtotal - $discount);
        $paid_now = money2((float)($in['paid_now'] ?? 0));
        if ($paid_now < 0 || $paid_now > $total) {
            throw new DomainException('What was paid now can be at most the total, ' . planner_money($total, $symbol) . '.');
        }

        // Who it is for: the customer picked, the one with this phone (or a
        // new one), or nobody named: the walk-in customer
        $name = trim((string)($in['customer_name'] ?? ''));
        if (!empty($in['customer_id'])) {
            $customer = order__customer($db, $biz, (int)$in['customer_id']);
            if (!$customer) {
                throw new DomainException('That customer isn\'t here any more. Choose them again.');
            }
        } else {
            $customer_id = ($name === '' && $phone === '')
                ? order_walk_in_customer($db, $biz)
                : order_match_customer($db, $biz, $name !== '' ? $name : $phone, $phone ?: null, $email ?: null);
            $customer = order__customer($db, $biz, $customer_id);
        }
        $walk_in = (int)$customer['is_walk_in'] === 1;
        $name  = $walk_in ? (string)$customer['full_name'] : ($name !== '' ? $name : (string)$customer['full_name']);
        $phone = $walk_in ? '' : ($phone !== '' ? $phone : (string)($customer['phone'] ?? ''));
        $email = $walk_in ? '' : ($email !== '' ? $email : (string)($customer['email'] ?? ''));

        // Nobody can chase a walk-in, or someone without a phone, for a balance
        $kept = (!empty($in['use_kept']) && !$walk_in) ? min(kept_balance($db, $biz, (int)$customer['id']), money2($total - $paid_now)) : 0.0;
        if (money2($total - $paid_now - $kept) > 0 && ($walk_in || (empty($in['customer_id']) && preg_replace('/\D/', '', $phone) === ''))) {
            throw new DomainException('Pay later or part payment needs the customer\'s name and phone, so the balance can be followed up. Add them, or take the full amount now.');
        }

        $quotation_id = (int)($in['quotation_id'] ?? 0);
        $quote_number = null;
        if ($quotation_id) {
            $stmt = $db->prepare("SELECT doc_number FROM quotations WHERE id = ? AND business_id = ?");
            $stmt->execute([$quotation_id, $biz]);
            $quote_number = $stmt->fetchColumn() ?: null;
            $quotation_id = $quote_number ? $quotation_id : 0;
        }
        $address = trim((string)($in['delivery_address'] ?? ''));
        if ($fulfillment === 'delivery' && $address === '') {
            $address = trim((string)($customer['address'] ?? ''));
        }

        $order_number = generate_doc_number($db, $biz, 'ORD');
        $db->prepare("
            INSERT INTO orders (business_id, customer_id, quotation_id, order_number, public_token, channel, fulfillment_type,
                customer_name, customer_phone, customer_email, delivery_address, notes, payment_method, currency_code,
                subtotal, discount_amount, total, status, reviewed_by, reviewed_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'none', ?, ?, ?, ?, 'approved', ?, NOW(), ?)
        ")->execute([
            $biz, (int)$customer['id'], $quotation_id ?: null, $order_number, shop_order_token(), $channel,
            $fulfillment === 'delivery' ? 'delivery' : 'pickup',
            mb_substr($name, 0, 100), $phone !== '' ? mb_substr($phone, 0, 30) : null, $email !== '' ? mb_substr($email, 0, 100) : null,
            $fulfillment === 'delivery' && $address !== '' ? $address : null,
            trim((string)($in['note'] ?? '')) ?: null, business_currency_code($business),
            $subtotal, $discount, $total, $user_id, $user_id,
        ]);
        $order_id = (int)$db->lastInsertId();
        order__write_lines($db, $order_id, $lines);
        order_log($db, $biz, $order_id, 'created', match ($channel) {
            'quick' => 'Quick sale.',
            'quote' => $quote_number ? 'Order made from quote ' . $quote_number . '.' : 'Order made from a quote.',
            default => 'Order typed in.',
        }, $user_id, ['total' => $total]);

        order_confirm($db, $biz, $order_id, $user_id, [
            'payment_received' => false,
            'paid_now'     => $paid_now,
            'method'       => $method,
            'reference'    => $in['reference'] ?? null,
            'deliver_mode' => $fulfillment === 'now' ? 'immediate' : ($deliver_by !== '' ? 'date' : null),
            'deliver_by'   => $deliver_by !== '' ? $deliver_by : null,
            'deliver_show' => !empty($in['deliver_show']),
            'pay_by'       => $pay_by !== '' ? $pay_by : null,
            'use_kept'     => !empty($in['use_kept']) && !$walk_in,
            // Collected on the spot: the customer hears "thank you", not "confirmed" too
            'tell'         => $fulfillment !== 'now',
        ]);
        if ($fulfillment === 'now') {
            order_set_stage($db, $biz, $order_id, 'done', $user_id);
        }
        return order_find($db, $biz, $order_id);
    });
}

/**
 * An accepted quote becomes its order, once: the quote's customer and lines
 * (its tax as a line of its own, its discounts taken off the total), to be
 * collected unless the owner sets a delivery, with the balance in Debts.
 * The quote is marked converted. Asking again returns the same order.
 * Throws DomainException when the quote can't become an order.
 */
function order_from_quote(PDO $db, int $biz, int $quotation_id, int $user_id): array {
    return order__tx($db, function () use ($db, $biz, $quotation_id, $user_id) {
        $stmt = $db->prepare("SELECT * FROM quotations WHERE id = ? AND business_id = ? FOR UPDATE");
        $stmt->execute([$quotation_id, $biz]);
        $quote = $stmt->fetch();
        if (!$quote) {
            throw new DomainException('That quote isn\'t here any more.');
        }
        $stmt = $db->prepare("SELECT * FROM orders WHERE quotation_id = ? AND business_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$quotation_id, $biz]);
        if ($existing = $stmt->fetch()) {
            return $existing;
        }
        if (!in_array($quote['status'], ['sent', 'in_negotiation', 'pending_signature', 'accepted'], true)) {
            throw new DomainException(match ($quote['status']) {
                'draft'     => 'Send the quote first. It becomes an order when the customer accepts it.',
                'converted' => 'This quote was already made into an invoice.',
                default     => 'This quote was ' . $quote['status'] . ', so it can\'t become an order.',
            });
        }
        $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id");
        $stmt->execute([$quotation_id]);
        $items = [];
        $line_discounts = 0.0;
        foreach ($stmt->fetchAll() as $qi) {
            $items[] = ['product_id' => $qi['product_id'] ?: null, 'name' => (string)$qi['description'],
                        'quantity' => (float)$qi['quantity'], 'unit_price' => (float)$qi['unit_price']];
            $line_discounts += money2((float)$qi['quantity'] * (float)$qi['unit_price']) - (float)$qi['subtotal'];
        }
        if (!$items) {
            // A quote with only a total (made before quotes had lines): one line for all of it
            $items[] = ['name' => 'As quoted in ' . $quote['doc_number'], 'quantity' => 1,
                        'unit_price' => money2((float)$quote['total'] - (float)$quote['tax_amount'] + (float)$quote['discount_amount'])];
        }
        if ((float)$quote['tax_amount'] > 0) {
            $items[] = ['name' => 'Tax' . ((float)$quote['tax_rate'] > 0 ? ' (' . rtrim(rtrim(number_format((float)$quote['tax_rate'], 2), '0'), '.') . '%)' : ''),
                        'quantity' => 1, 'unit_price' => (float)$quote['tax_amount']];
        }
        $order = order_create($db, $biz, [
            'channel' => 'quote', 'quotation_id' => $quotation_id, 'customer_id' => (int)$quote['customer_id'],
            'items' => $items, 'discount' => money2(max(0, $line_discounts) + (float)$quote['discount_amount']),
            'fulfillment' => 'pickup', 'paid_now' => 0, 'method' => 'cash',
        ], $user_id);
        $db->prepare("UPDATE quotations SET status = 'converted' WHERE id = ? AND business_id = ?")->execute([$quotation_id, $biz]);
        $db->prepare("
            INSERT INTO quotation_activities (business_id, quotation_id, actor_type, user_id, action, old_status, new_status, message)
            VALUES (?, ?, 'business', ?, 'status_changed', ?, 'converted', ?)
        ")->execute([$biz, $quotation_id, $user_id ?: null, $quote['status'], 'Order ' . $order['order_number'] . ' made from this quote.']);
        return $order;
    });
}

/**
 * Confirm an order: a new shop order is claimed (pending → approved); an
 * order typed in is already approved and only needs its documents. Makes the
 * invoice, takes the stock off, records the shop payment or what was paid
 * now (each with its receipt), uses kept money when asked, makes the
 * delivery note for a delivery, and keeps the debt in step.
 *   $opt: payment_received (shop orders: default true when the shopper sent
 *         proof), paid_now, method, reference, deliver_mode immediate|date|null,
 *         deliver_by, deliver_show, delivered_at, pay_by, use_kept,
 *         tell (default true: the shopper hears "confirmed")
 * Returns ['invoice_id' => int, 'receipt_id' => ?int, 'delivery_id' => ?int].
 */
function order_confirm(PDO $db, int $biz, int $order_id, int $user_id, array $opt = []): array {
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id, $opt) {
        $order = order__lock($db, $biz, $order_id);
        $is_new = in_array($order['status'], ['pending', 'pending_verification'], true);
        if (!$is_new && !($order['status'] === 'approved' && !order_invoice($db, $biz, $order))) {
            throw new DomainException('This order has already been dealt with.');
        }
        if ($is_new) {
            $db->prepare("UPDATE orders SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND business_id = ?")
               ->execute([$user_id, $order_id, $biz]);
        }
        $business = order__business($db, $biz);
        $symbol   = (string)($business['currency_symbol'] ?? '₦');

        // The order's own customer, if they are still here, else matched by phone
        $customer = !empty($order['customer_id']) ? order__customer($db, $biz, (int)$order['customer_id']) : null;
        $customer_id = $customer ? (int)$customer['id']
            : order_match_customer($db, $biz, (string)$order['customer_name'], $order['customer_phone'], $order['customer_email']);

        $total    = money2((float)$order['total']);
        $received = isset($opt['payment_received']) ? (bool)$opt['payment_received'] : !empty($order['payment_proof_path']);
        $paid_now = $received ? $total : money2((float)($opt['paid_now'] ?? 0));
        if ($paid_now < 0 || $paid_now > $total) {
            throw new DomainException('What was paid can be at most the total, ' . planner_money($total, $symbol) . '.');
        }
        $method = in_array($opt['method'] ?? '', ['cash', 'bank_transfer', 'mobile_money', 'pos', 'cheque', 'other'], true) ? $opt['method']
            : ($received && in_array($order['payment_method'], ['bank_transfer', 'mobile_money'], true) ? $order['payment_method'] : 'cash');
        $use_kept = !empty($opt['use_kept']);
        $kept     = $use_kept ? min(kept_balance($db, $biz, $customer_id), money2($total - $paid_now)) : 0.0;
        $left     = money2($total - $paid_now - $kept);

        $deliver_mode = in_array($opt['deliver_mode'] ?? null, ['immediate', 'date'], true) ? $opt['deliver_mode'] : null;
        $deliver_by   = null;
        if ($deliver_mode) {
            $deliver_by = (string)($opt['deliver_by'] ?? '') ?: planner_today();
            if (!planner_valid_date($deliver_by)) {
                throw new DomainException('Pick the delivery date.');
            }
        }
        $pay_by = planner_today();
        if ($left > 0) {
            $pay_by = planner_valid_date($opt['pay_by'] ?? null) ? $opt['pay_by'] : order__default_pay_by($deliver_by);
        }

        // The invoice: sent, as the goods are going out. No notes: the order
        // number shows on it as a reference instead.
        $invoice_number = generate_doc_number($db, $biz, 'INV');
        $db->prepare("
            INSERT INTO invoices (business_id, customer_id, quotation_id, order_id, bank_account_id, user_id, doc_number, status,
                issue_date, due_date, deliver_mode, deliver_by, deliver_show, delivered_at,
                subtotal, discount_amount, tax_rate, tax_amount, total, amount_paid, balance_due, notes, token)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, ?, NULL, ?)
        ")->execute([
            $biz, $customer_id, $order['quotation_id'] ?: null, $order_id, order__bank_account($db, $biz, $order), $user_id, $invoice_number,
            planner_today(), $pay_by, $deliver_mode, $deliver_by, !empty($opt['deliver_show']) ? 1 : 0, $opt['delivered_at'] ?? null,
            money2((float)$order['subtotal']), money2((float)$order['discount_amount']), $total, $total, generate_token(),
        ]);
        $invoice_id = (int)$db->lastInsertId();
        $lines = order_lines($db, $order_id);
        order__invoice_lines($db, $biz, $invoice_id, $lines);
        doc_made($db, 'invoice', $invoice_id, $user_id);   // signed or not, as Settings › Signatures say
        $db->prepare("UPDATE orders SET customer_id = ?, invoice_id = ? WHERE id = ? AND business_id = ?")
           ->execute([$customer_id, $invoice_id, $order_id, $biz]);
        $order['customer_id'] = $customer_id;
        $order['invoice_id']  = $invoice_id;
        order_log($db, $biz, $order_id, 'confirmed', 'Confirmed. Invoice ' . $invoice_number . ' made.', $user_id, ['invoice_id' => $invoice_id]);

        // The goods leave the shelf, under the invoice (the only owner of stock moves)
        stock_settle($db, $biz, 'invoice', $invoice_id, invoice_stock_target($db, $biz, $invoice_id), 'order ' . $order['order_number']);

        // What was paid, each with its receipt. A shop order's proof is told
        // as one "paid and approved" below, not as a second payment alert.
        // The debt is worked out once, at the end, so a deposit and kept
        // money that cover everything never leave a debt behind.
        $receipt_id = null;
        if ($paid_now > 0) {
            $paid = order__payment($db, $biz, $order, $paid_now, planner_today(), $method,
                                   $received ? null : (trim((string)($opt['reference'] ?? '')) ?: null),
                                   $received ? 'Shop order ' . $order['order_number'] . ', proof sent with the order.' : null,
                                   $user_id, $symbol, !$received, false);
            $receipt_id = $paid['receipt_id'];
        }
        if ($use_kept && order__apply_kept($db, $biz, $order, $user_id, $symbol, false) > 0 && !$receipt_id) {
            $stmt = $db->prepare("SELECT receipt_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $receipt_id = (int)$stmt->fetchColumn() ?: null;
        }

        // A delivery gets its delivery note, for the rider and the sign-off
        $delivery_id = null;
        if ($order['fulfillment_type'] === 'delivery') {
            $db->prepare("
                INSERT INTO deliveries (business_id, customer_id, invoice_id, order_id, doc_number, token, signoff_token,
                    recipient_name, recipient_phone, delivery_address, estimated_delivery)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $biz, $customer_id, $invoice_id, $order_id, generate_doc_number($db, $biz, 'DEL'), generate_token(), generate_token(),
                (string)$order['customer_name'], $order['customer_phone'] ?: null, (string)($order['delivery_address'] ?? ''), $deliver_by,
            ]);
            $delivery_id = (int)$db->lastInsertId();
            order__delivery_lines($db, $delivery_id, $lines);
            doc_made($db, 'delivery', $delivery_id, $user_id);
            $db->prepare("UPDATE orders SET delivery_id = ? WHERE id = ? AND business_id = ? AND delivery_id IS NULL")
               ->execute([$delivery_id, $order_id, $biz]);
        }

        order_sync_debt($db, $biz, $order_id, $left > 0 ? $pay_by : null);
        customer_refresh_totals($db, $biz, $customer_id);

        if ($received && $paid_now > 0) {
            order__after(fn() => notify('payment.order', $biz, [
                'order_id' => $order_id, 'order_number' => $order['order_number'],
                'customer' => $order['customer_name'], 'amount' => $total,
            ], ['actor' => $user_id, 'dedupe' => (string)$order_id]));
        }
        if ($opt['tell'] ?? true) {
            order__after(fn() => shop_order_tell($db, $order_id, 'confirmed'));
        }
        return ['invoice_id' => $invoice_id, 'receipt_id' => $receipt_id, 'delivery_id' => $delivery_id];
    });
}

/* ══════════════════════════════════════════════════════════════
 *  MONEY
 * ══════════════════════════════════════════════════════════════ */

/**
 * Money received on an order: a payment on its invoice, then its receipt.
 * The owners and admins hear about it, except whoever recorded it.
 * Returns ['payment_id', 'receipt_id', 'new_balance'].
 */
function order_record_payment(PDO $db, int $biz, int $order_id, float $amount, string $date, string $method, ?string $reference,
                              ?string $note, int $user_id, string $symbol): array {
    // 'kept' is only ever money moved by order_use_kept(), never typed in
    if (!in_array($method, ['cash', 'bank_transfer', 'mobile_money', 'pos', 'cheque', 'other'], true)) {
        $method = 'cash';
    }
    return order__tx($db, function () use ($db, $biz, $order_id, $amount, $date, $method, $reference, $note, $user_id, $symbol) {
        $order = order__lock($db, $biz, $order_id);
        if (in_array($order['status'], ['cancelled', 'rejected'], true)) {
            throw new DomainException('This order was ' . ($order['status'] === 'cancelled' ? 'cancelled' : 'turned down') . ', so no payments can be recorded on it.');
        }
        return order__payment($db, $biz, $order, $amount, $date, $method, $reference, $note, $user_id, $symbol, true);
    });
}

/**
 * The order's debt follows its invoice: one receivable while something is
 * owed ("Balance on order ADA-ORD-012", due on the pay-by date), cleared at
 * nothing owed, opened again if a change or a refund leaves a balance. A
 * cancelled invoice's debt was written off by invoice_cancel().
 */
function order_sync_debt(PDO $db, int $biz, int $order_id, ?string $pay_by = null): void {
    order__tx($db, function () use ($db, $biz, $order_id, $pay_by) {
        order__sync_debt($db, $biz, order__lock($db, $biz, $order_id), $pay_by);
    });
}

/** order_sync_debt() for an order the caller has locked. */
function order__sync_debt(PDO $db, int $biz, array $order, ?string $pay_by): void {
    $order_id = (int)$order['id'];
    $invoice = order_invoice($db, $biz, $order);
    if (!$invoice || in_array($invoice['status'], ['cancelled', 'draft'], true)) {
        return;
    }
    $total   = money2((float)$invoice['total']);
    $balance = money2(max(0, $total - (float)$invoice['amount_paid']));
    $settled = money2($total - $balance);
    $status  = $balance <= 0 ? 'cleared' : ($settled > 0 ? 'settling' : 'active');
    $due     = planner_valid_date($pay_by) ? $pay_by : $invoice['due_date'];
    $customer = order__customer($db, $biz, (int)$invoice['customer_id'], true);

    $stmt = $db->prepare("
        SELECT id, status FROM debts
        WHERE invoice_id = ? AND business_id = ? AND type = 'receivable'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([(int)$invoice['id'], $biz]);
    $debt = $stmt->fetch() ?: null;
    if ($debt && $debt['status'] === 'written_off') {
        return;   // the owner wrote it off in Debts: it stays off, and no second one is made
    }
    $debt_id = (int)($debt['id'] ?? 0);
    $fields = [(int)$invoice['customer_id'], $order_id, 'Balance on order ' . $order['order_number'],
               $customer['full_name'] ?? null, isset($customer['phone']) ? mb_substr((string)$customer['phone'], 0, 20) : null,
               $total, $settled, $balance, $due, $status];
    if ($debt_id) {
        $db->prepare("
            UPDATE debts SET customer_id = ?, order_id = ?, source = 'invoice', description = ?, counterparty_name = ?, counterparty_phone = ?,
                total_amount = ?, amount_settled = ?, balance = ?, due_date = ?, status = ?
            WHERE id = ? AND business_id = ?
        ")->execute([...$fields, $debt_id, $biz]);
    } elseif ($balance > 0) {
        $db->prepare("
            INSERT INTO debts (customer_id, order_id, description, counterparty_name, counterparty_phone, total_amount, amount_settled, balance,
                due_date, status, business_id, type, source, invoice_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'receivable', 'invoice', ?)
        ")->execute([...$fields, $biz, (int)$invoice['id']]);
    }
}

/** When the balance is due: on the invoice and its debt. A later date takes an overdue invoice off overdue. */
function order_set_pay_by(PDO $db, int $biz, int $order_id, string $date, int $user_id): void {
    if (!planner_valid_date($date)) {
        throw new DomainException('Pick the day the balance is due.');
    }
    order__tx($db, function () use ($db, $biz, $order_id, $date, $user_id) {
        $order = order__lock($db, $biz, $order_id);
        $invoice = order_invoice($db, $biz, $order);
        if (!$invoice || $invoice['status'] === 'cancelled') {
            throw new DomainException('Confirm the order first, then set when the balance is due.');
        }
        $status = $invoice['status'];
        if ($status === 'overdue' && $date >= planner_today()) {
            $status = (float)$invoice['amount_paid'] > 0 ? 'partially_paid' : 'sent';
        }
        $db->prepare("UPDATE invoices SET due_date = ?, status = ? WHERE id = ? AND business_id = ?")
           ->execute([$date, $status, (int)$invoice['id'], $biz]);
        doc_changed($db, 'invoice', (int)$invoice['id'], $user_id);
        order_sync_debt($db, $biz, $order_id, $date);
        order_log($db, $biz, $order_id, 'pay_by', 'Balance due by ' . date('D j M Y', strtotime($date)) . '.', $user_id,
                  ['from' => $invoice['due_date'], 'to' => $date]);
    });
}

/**
 * When the customer gets it: the day the order is delivered or collected.
 * It lives on the invoice's delivery promise (Tasks & Calendar count down to
 * it), the delivery note's planned day and a job plan's delivery step follow.
 * The balance's pay-by date is left as it is: it is a separate promise.
 */
function order_set_when(PDO $db, int $biz, int $order_id, string $date, int $user_id): void {
    if (!planner_valid_date($date)) {
        throw new DomainException('Pick the day they get it.');
    }
    order__tx($db, function () use ($db, $biz, $order_id, $date, $user_id) {
        $order   = order__lock($db, $biz, $order_id);
        $invoice = order_invoice($db, $biz, $order);
        if (!$invoice || $invoice['status'] === 'cancelled') {
            throw new DomainException('Confirm the order first, then set the day.');
        }
        if (order_stage($order, order__delivery($db, $biz, $order))['key'] === 'done') {
            throw new DomainException('This order is already done.');
        }
        $db->prepare("UPDATE invoices SET deliver_mode = 'date', deliver_by = ? WHERE id = ? AND business_id = ?")
           ->execute([$date, (int)$invoice['id'], $biz]);
        planner_move_delivery($db, $biz, (int)$invoice['id'], $date);
        $db->prepare("
            UPDATE deliveries SET estimated_delivery = ?
            WHERE business_id = ? AND (order_id = ? OR id = ?) AND deleted_at IS NULL AND status IN ('pending_dispatch','dispatched','in_transit')
        ")->execute([$date . ' 00:00:00', $biz, $order_id, (int)($order['delivery_id'] ?? 0)]);
        $word = ($order['fulfillment_type'] ?? '') === 'delivery' ? 'Delivery' : 'Collection';
        order_log($db, $biz, $order_id, 'when', $word . ' day set to ' . date('D j M', strtotime($date)) . '.', $user_id,
                  ['from' => $invoice['deliver_by'], 'to' => $date]);
    });
}

/* ══════════════════════════════════════════════════════════════
 *  WHERE AN ORDER STANDS (for the screens)
 * ══════════════════════════════════════════════════════════════ */

/**
 * The customer's own step words, as the owner sees them:
 *   ['key' => new|confirmed|ready|done|turned_down|cancelled, 'words', 'tone' (an nf-tag class)]
 * A delivery note on its way counts as ready, delivered or signed for as done.
 */
function order_stage(array $order, ?array $delivery = null): array {
    $status  = (string)($order['status'] ?? '');
    $dstatus = $delivery && empty($delivery['deleted_at']) ? (string)($delivery['status'] ?? '') : '';
    $is_delivery = ($order['fulfillment_type'] ?? '') === 'delivery';
    $key = match (true) {
        $status === 'rejected'  => 'turned_down',
        $status === 'cancelled' => 'cancelled',
        in_array($status, ['pending', 'pending_verification'], true) => 'new',
        $status === 'fulfilled', in_array($dstatus, ['delivered', 'signed_off'], true) => 'done',
        $status === 'ready', in_array($dstatus, ['dispatched', 'in_transit'], true) => 'ready',
        default => 'confirmed',
    };
    $words = match ($key) {
        'new'         => 'New',
        'confirmed'   => 'Confirmed',
        'ready'       => $is_delivery ? 'On its way' : 'Ready for pickup',
        'done'        => $is_delivery ? 'Delivered' : 'Collected',
        'turned_down' => 'Turned down',
        default       => 'Cancelled',
    };
    $tone = ['new' => 'is-warn', 'confirmed' => '', 'ready' => 'is-accent', 'done' => 'is-in', 'turned_down' => 'is-line', 'cancelled' => 'is-line'][$key];
    return ['key' => $key, 'words' => $words, 'tone' => $tone];
}

/** The order page's four dots: New · Confirmed · Ready (On its way) · Collected (Delivered). None once turned down or cancelled. */
function order_steps(array $order, ?array $delivery = null): array {
    $stage = order_stage($order, $delivery)['key'];
    if (in_array($stage, ['turned_down', 'cancelled'], true)) {
        return [];
    }
    $is_delivery = ($order['fulfillment_type'] ?? '') === 'delivery';
    $steps = [['new', 'New'], ['confirmed', 'Confirmed'], ['ready', $is_delivery ? 'On its way' : 'Ready'],
              ['done', $is_delivery ? 'Delivered' : 'Collected']];
    $at = (int)array_search($stage, array_column($steps, 0), true);
    $out = [];
    foreach ($steps as $i => [$key, $label]) {
        $state = ($i < $at || $stage === 'done') ? 'done' : ($i === $at ? 'now' : 'next');
        $out[] = ['key' => $key, 'label' => $label, 'state' => $state];
    }
    return $out;
}

/**
 * The payment tag: Paid · Deposit paid · Not paid · Paid more (when a change
 * left more paid than the new total). No tag while there is no invoice.
 * Returns ['words', 'tone', 'balance', 'paid', 'overpaid'].
 */
function order_pay_tag(?array $invoice): array {
    if (!$invoice || ($invoice['status'] ?? '') === 'cancelled') {
        return ['words' => '', 'tone' => '', 'balance' => 0.0, 'paid' => 0.0, 'overpaid' => 0.0];
    }
    $total   = money2((float)$invoice['total']);
    $paid    = money2((float)$invoice['amount_paid']);
    $balance = money2(max(0, $total - $paid));
    $over    = order_overpaid($invoice);
    [$words, $tone] = match (true) {
        $over > 0      => ['Paid more', 'is-warn'],
        $balance <= 0  => ['Paid', 'is-in'],
        $paid > 0      => ['Deposit paid', 'is-warn'],
        default        => ['Not paid', 'is-owed'],
    };
    return ['words' => $words, 'tone' => $tone, 'balance' => $balance, 'paid' => $paid, 'overpaid' => $over];
}

/**
 * The one button on top of the order page: ['key', 'label', 'icon'] or null.
 * key is what it does: confirm, ready or done (order_set_stage), payment.
 */
function order_next_action(array $order, ?array $invoice, ?array $delivery): ?array {
    $is_delivery = ($order['fulfillment_type'] ?? '') === 'delivery';
    return match (order_stage($order, $delivery)['key']) {
        'new'       => ['key' => 'confirm', 'label' => 'Confirm order', 'icon' => 'check'],
        'confirmed' => $is_delivery ? ['key' => 'ready', 'label' => 'Send it out', 'icon' => 'delivery']
                                    : ['key' => 'ready', 'label' => 'Mark ready', 'icon' => 'check'],
        'ready'     => ['key' => 'done', 'label' => $is_delivery ? 'Mark delivered' : 'Mark collected', 'icon' => 'check'],
        'done'      => order_pay_tag($invoice)['balance'] > 0 ? ['key' => 'payment', 'label' => 'Record payment', 'icon' => 'payment'] : null,
        default     => null,
    };
}

/* ══════════════════════════════════════════════════════════════
 *  MOVING ON
 * ══════════════════════════════════════════════════════════════ */

/**
 * Move an order on, or back:
 *   ready      ready for pickup, or on its way: the delivery note goes out
 *   done       collected or delivered: the delivery note is delivered (unless
 *              already signed for), the invoice's delivery promise is kept,
 *              and the shopper is thanked and asked for a review
 *   undo_done  back to Confirmed, when it was marked done by mistake
 * The same move twice does nothing the second time.
 */
function order_set_stage(PDO $db, int $biz, int $order_id, string $to, int $user_id): void {
    if (!in_array($to, ['ready', 'done', 'undo_done'], true)) {
        throw new InvalidArgumentException("Unknown order stage {$to}");
    }
    order__tx($db, function () use ($db, $biz, $order_id, $to, $user_id) {
        order__move($db, $biz, order__lock($db, $biz, $order_id), $to, $user_id, false);
    });
}

/**
 * Tasks' tick and the invoice's "Delivered" call this: an invoice that
 * belongs to an order moves the order (and so its delivery note and the
 * shopper); any other invoice only keeps its delivery promise.
 */
function order_from_invoice_delivered(PDO $db, int $biz, int $invoice_id, bool $undo, int $user_id): void {
    order__tx($db, function () use ($db, $biz, $invoice_id, $undo, $user_id) {
        $order = order_by_invoice($db, $biz, $invoice_id);
        $order = $order ? order_find($db, $biz, (int)$order['id'], true) : null;
        $moves = $order && ($undo ? $order['status'] === 'fulfilled' : in_array($order['status'], ['approved', 'processing', 'ready'], true));
        if ($moves) {
            order__move($db, $biz, $order, $undo ? 'undo_done' : 'done', $user_id, false);
            return;
        }
        planner_invoice_delivered($db, $biz, $invoice_id, $undo, $user_id);
    });
}

/**
 * A delivery note moved somewhere else (its own page, the customer's
 * sign-off): its order follows. Out for delivery is ready, delivered or
 * signed for is done. An order that is new, turned down or cancelled, or
 * already further on, stays where it is.
 */
function order_sync_from_delivery(PDO $db, int $biz, int $delivery_id): void {
    order__tx($db, function () use ($db, $biz, $delivery_id) {
        $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([$delivery_id, $biz]);
        $delivery = $stmt->fetch();
        if (!$delivery) {
            return;
        }
        $order_id = (int)($delivery['order_id'] ?? 0);
        if (!$order_id) {
            $stmt = $db->prepare("SELECT id FROM orders WHERE delivery_id = ? AND business_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$delivery_id, $biz]);
            $order_id = (int)$stmt->fetchColumn();
        }
        if (!$order_id && !empty($delivery['invoice_id'])) {
            $order_id = (int)(order_by_invoice($db, $biz, (int)$delivery['invoice_id'])['id'] ?? 0);
        }
        $order = $order_id ? order_find($db, $biz, $order_id, true) : null;
        $to = match ($delivery['status']) {
            'dispatched', 'in_transit' => 'ready',
            'delivered', 'signed_off'  => 'done',
            default                    => null,
        };
        if (!$order || !$to) {
            return;
        }
        $from = $to === 'ready' ? ['approved', 'processing'] : ['approved', 'processing', 'ready'];
        if (in_array($order['status'], $from, true)) {
            order__move($db, $biz, $order, $to, null, true);
        }
    });
}

/** A new order the business won't take (pending only). The shopper is told, with the reason. */
function order_turn_down(PDO $db, int $biz, int $order_id, int $user_id, string $reason = ''): void {
    $reason = mb_substr(trim($reason), 0, 300);
    order__tx($db, function () use ($db, $biz, $order_id, $user_id, $reason) {
        $order = order__lock($db, $biz, $order_id);
        if (!in_array($order['status'], ['pending', 'pending_verification'], true)) {
            throw new DomainException('Only a new order can be turned down.');
        }
        $db->prepare("UPDATE orders SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), cancel_reason = ? WHERE id = ? AND business_id = ?")
           ->execute([$user_id, $reason ?: null, $order_id, $biz]);
        order_log($db, $biz, $order_id, 'turned_down', 'Turned down.' . ($reason !== '' ? ' Reason: ' . $reason : ''), $user_id);
        order__after(fn() => shop_order_tell($db, $order_id, 'turned_down', $reason));
    });
}

/**
 * Cancel an order. With money paid on it, it refuses (give it back first,
 * then cancel); otherwise its invoice is cancelled (stock back, debt written
 * off, delivery closed, its delivery step off Tasks), the shopper is told.
 * Returns '' when cancelled, else what stopped it.
 */
function order_cancel(PDO $db, int $biz, int $order_id, int $user_id, string $reason = ''): string {
    $reason = mb_substr(trim($reason), 0, 300);
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id, $reason) {
        $order = order__lock($db, $biz, $order_id);
        if ($order['status'] === 'cancelled') {
            return 'This order is already cancelled.';
        }
        if ($order['status'] === 'rejected') {
            return 'This order was turned down, so there is nothing to cancel.';
        }
        $symbol  = (string)(order__business($db, $biz)['currency_symbol'] ?? '₦');
        $invoice = order_invoice($db, $biz, $order);
        if ($invoice && $invoice['status'] !== 'cancelled') {
            $paid = money2((float)$invoice['amount_paid']);
            if ($paid > 0) {
                return planner_money($paid, $symbol) . ' was paid on this order. Give it back first, then cancel.';
            }
            $problem = invoice_cancel($db, $biz, (int)$invoice['id'], $symbol);
            if ($problem !== '') {
                return $problem;
            }
        }
        $db->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND business_id = ?")
           ->execute([$reason ?: null, $order_id, $biz]);
        order_log($db, $biz, $order_id, 'cancelled', 'Cancelled.' . ($reason !== '' ? ' Reason: ' . $reason : ''), $user_id);
        order__after(fn() => shop_order_tell($db, $order_id, 'cancelled', $reason));
        return '';
    });
}

/* ══════════════════════════════════════════════════════════════
 *  CHANGING THE ITEMS, AND MONEY PAID OVER
 * ══════════════════════════════════════════════════════════════ */

/** Why a shop order sent with payment proof can't change before it is confirmed. */
const ORDER_EDIT_AFTER_PROOF = 'The customer already paid for this order as it is. Confirm it with their payment first, then change its items.';

/**
 * Change an order's items or discount, any time before it is done. A new
 * order only changes its lines and totals. A confirmed one also changes its
 * invoice (same number; worked out again, paid when what was paid covers
 * it), its stock (only the difference moves), its delivery note's lines and
 * its debt. The Log keeps the old lines. Returns ['overpaid', 'total']:
 * overpaid is what was paid over the new total, to give back or keep.
 */
function order_update_lines(PDO $db, int $biz, int $order_id, array $items, float $discount, int $user_id, string $symbol): array {
    return order__tx($db, function () use ($db, $biz, $order_id, $items, $discount, $user_id, $symbol) {
        $order    = order__lock($db, $biz, $order_id);
        $delivery = order__delivery($db, $biz, $order);
        $stage    = order_stage($order, $delivery)['key'];
        if (in_array($stage, ['done', 'cancelled', 'turned_down'], true)) {
            throw new DomainException(match ($stage) {
                'done'      => 'This order is done, so its items can\'t change.',
                'cancelled' => 'This order was cancelled, so its items can\'t change.',
                default     => 'This order was turned down, so its items can\'t change.',
            });
        }
        // The shopper paid for what they asked for: confirm that payment first,
        // so a change afterwards shows any difference to give back or collect
        if ($stage === 'new' && !empty($order['payment_proof_path'])) {
            throw new DomainException(ORDER_EDIT_AFTER_PROOF);
        }
        $lines    = order__clean_items($db, $biz, $items);
        $subtotal = money2(array_sum(array_column($lines, 'line_total')));
        $discount = money2($discount);
        if ($discount < 0) {
            throw new DomainException('A discount can\'t be below 0.');
        }
        if ($discount > $subtotal) {
            throw new DomainException('The discount can be at most what the items come to, ' . planner_money($subtotal, $symbol) . '.');
        }
        $total = money2($subtotal - $discount);
        $old   = order_lines($db, $order_id);

        order__write_lines($db, $order_id, $lines);
        $db->prepare("UPDATE orders SET subtotal = ?, discount_amount = ?, total = ?, edited_at = NOW() WHERE id = ? AND business_id = ?")
           ->execute([$subtotal, $discount, $total, $order_id, $biz]);

        $overpaid = 0.0;
        $invoice  = order_invoice($db, $biz, $order);
        if ($invoice && $invoice['status'] !== 'cancelled') {
            $invoice_id = (int)$invoice['id'];
            $rows = order_lines($db, $order_id);
            order__invoice_lines($db, $biz, $invoice_id, $rows);
            $paid    = money2((float)$invoice['amount_paid']);
            $balance = money2(max(0, $total - $paid));
            $status  = match (true) {
                $paid >= $total                    => 'paid',
                $invoice['status'] === 'overdue'   => 'overdue',
                $paid > 0                          => 'partially_paid',
                default                            => 'sent',
            };
            // Paid in full until now: the new balance gets a pay-by date of its own
            $due = (float)$invoice['balance_due'] <= 0 && $balance > 0 ? order__default_pay_by($invoice['deliver_by']) : $invoice['due_date'];
            $db->prepare("UPDATE invoices SET subtotal = ?, discount_amount = ?, total = ?, balance_due = ?, status = ?, due_date = ? WHERE id = ? AND business_id = ?")
               ->execute([$subtotal, $discount, $total, $balance, $status, $due, $invoice_id, $biz]);
            doc_changed($db, 'invoice', $invoice_id, $user_id);   // signed: signed again, in the name of whoever changed it
            stock_settle($db, $biz, 'invoice', $invoice_id, invoice_stock_target($db, $biz, $invoice_id), 'order ' . $order['order_number'] . ' (changed)');
            if ($delivery && !in_array($delivery['status'], ['delivered', 'signed_off'], true)) {
                order__delivery_lines($db, (int)$delivery['id'], $rows);
                doc_changed($db, 'delivery', (int)$delivery['id'], $user_id);
            }
            order_sync_debt($db, $biz, $order_id);
            customer_refresh_totals($db, $biz, (int)$invoice['customer_id']);
            $overpaid = money2(max(0, $paid - $total));
            // A refund asked for earlier is never more than what is now paid over
            $asked = refund_open($db, $biz, $order_id);
            if ($asked && $overpaid <= 0) {
                order__refund_off($db, $biz, $order_id, $asked, $user_id, 'Refund called off: after the change nothing was paid over.');
            } elseif ($asked && (float)$asked['amount'] > $overpaid) {
                $db->prepare("UPDATE refunds SET amount = ? WHERE id = ? AND business_id = ?")->execute([$overpaid, (int)$asked['id'], $biz]);
            }
        }

        order_log($db, $biz, $order_id, 'edited',
                  'Items changed: the total went from ' . planner_money((float)$order['total'], $symbol) . ' to ' . planner_money($total, $symbol) . '.',
                  $user_id, [
                      'old_total' => (float)$order['total'], 'old_discount' => (float)$order['discount_amount'],
                      'old_lines' => array_map(fn($l) => ['product_id' => $l['product_id'], 'name' => $l['product_name'], 'quantity' => (float)$l['quantity'],
                                                          'unit_price' => (float)$l['unit_price'], 'note' => $l['item_note']], $old),
                  ]);
        return ['overpaid' => $overpaid, 'total' => $total];
    });
}

/** What was paid over an invoice's total (after its order was changed): max(0, paid - total). */
function order_overpaid(?array $invoice): float {
    if (!$invoice) {
        return 0.0;
    }
    return money2(max(0, (float)$invoice['amount_paid'] - (float)$invoice['total']));
}

/**
 * "Keep for next order": what was paid over the total moves off this
 * invoice (a 'kept' payment of minus the extra, so it is paid exactly) and
 * is kept for the customer. Returns the amount kept (0 when nothing was over).
 */
function order_keep_overpaid(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float {
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id, $symbol) {
        $order   = order__lock($db, $biz, $order_id);
        $invoice = order_invoice($db, $biz, $order);
        $extra   = order_overpaid($invoice);
        if ($extra <= 0) {
            return 0.0;
        }
        $customer_id = (int)$invoice['customer_id'];
        if ((int)(order__customer($db, $biz, $customer_id, true)['is_walk_in'] ?? 0) === 1) {
            throw new DomainException('Money can only be kept for a customer with a name. Give it back instead.');
        }
        $payment_id = order__kept_payment($db, $biz, (int)$invoice['id'], -$extra, 'Kept for their next order', $user_id);
        $db->prepare("INSERT INTO kept_money (business_id, customer_id, order_id, payment_id, amount, note, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$biz, $customer_id, $order_id, $payment_id, $extra, 'Kept from order ' . $order['order_number'], $user_id]);
        order_sync_debt($db, $biz, $order_id);
        customer_refresh_totals($db, $biz, $customer_id);
        order_log($db, $biz, $order_id, 'kept', planner_money($extra, $symbol) . ' paid over the new total, kept for their next order.', $user_id,
                  ['amount' => $extra, 'payment_id' => $payment_id]);
        return $extra;
    });
}

/** Money kept for a customer's next order, not used or given back yet. */
function kept_balance(PDO $db, int $biz, int $customer_id): float {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM kept_money WHERE business_id = ? AND customer_id = ?");
    $stmt->execute([$biz, $customer_id]);
    return money2(max(0, (float)$stmt->fetchColumn()));
}

/**
 * What this order kept for the customer that is still there to give back:
 * what it kept, less what it gave back, never more than the customer still
 * has kept. Kept money this order itself used up (from an earlier order)
 * doesn't count against it.
 */
function kept_from_order(PDO $db, int $biz, int $customer_id, int $order_id): float {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM kept_money WHERE business_id = ? AND customer_id = ? AND order_id = ? AND note NOT LIKE ?");
    $stmt->execute([$biz, $customer_id, $order_id, KEPT_USED_NOTE . '%']);
    return money2(min(max(0, (float)$stmt->fetchColumn()), kept_balance($db, $biz, $customer_id)));
}

/** How a kept_money line that pays an order starts (see order__apply_kept()). */
const KEPT_USED_NOTE = 'Used on order ';

/**
 * "Use it": money kept for this customer pays what it can of this order (a
 * 'kept' payment, with its receipt). Returns the amount used.
 */
function order_use_kept(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float {
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id, $symbol) {
        return order__apply_kept($db, $biz, order__lock($db, $biz, $order_id), $user_id, $symbol, true);
    });
}

/**
 * "Give it back instead": undo a keep from this order that wasn't used yet.
 * The money goes back on the order it came from (a 'kept' payment), so the
 * order is paid over again and the refund takes over. Returns the amount.
 */
function order_unkeep(PDO $db, int $biz, int $order_id, int $user_id, string $symbol): float {
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id, $symbol) {
        $order   = order__lock($db, $biz, $order_id);
        $invoice = order_invoice($db, $biz, $order);
        if (!$invoice || $invoice['status'] === 'cancelled') {
            return 0.0;
        }
        $customer_id = (int)$invoice['customer_id'];
        $db->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ? FOR UPDATE")->execute([$customer_id, $biz]);
        $amount = kept_from_order($db, $biz, $customer_id, $order_id);
        if ($amount <= 0) {
            return 0.0;
        }
        $payment_id = order__kept_payment($db, $biz, (int)$invoice['id'], $amount, 'Kept money to be given back instead', $user_id);
        $db->prepare("INSERT INTO kept_money (business_id, customer_id, order_id, payment_id, amount, note, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$biz, $customer_id, $order_id, $payment_id, -$amount, 'Given back instead of kept', $user_id]);
        order_sync_debt($db, $biz, $order_id);
        customer_refresh_totals($db, $biz, $customer_id);
        order_log($db, $biz, $order_id, 'unkept', planner_money($amount, $symbol) . ' no longer kept: it is to be given back.', $user_id,
                  ['amount' => $amount, 'payment_id' => $payment_id]);
        return $amount;
    });
}

/* ══════════════════════════════════════════════════════════════
 *  REFUNDS
 * ══════════════════════════════════════════════════════════════ */

/** The refund waiting on this order: asked for account details, or with them in. */
function refund_open(PDO $db, int $biz, int $order_id): ?array {
    $stmt = $db->prepare("SELECT * FROM refunds WHERE business_id = ? AND order_id = ? AND status IN ('asked', 'details_in') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$biz, $order_id]);
    return $stmt->fetch() ?: null;
}

/**
 * "Call it off": a refund that was asked for and not yet paid is cancelled
 * (the customer chose to keep the money with the shop, or it was a
 * mistake). Its link then says so. Returns false when none was open.
 */
function refund_call_off(PDO $db, int $biz, int $order_id, int $user_id): bool {
    return order__tx($db, function () use ($db, $biz, $order_id, $user_id) {
        order__lock($db, $biz, $order_id);
        $open = refund_open($db, $biz, $order_id);
        if (!$open) {
            return false;
        }
        order__refund_off($db, $biz, $order_id, $open, $user_id, 'Refund called off.');
        return true;
    });
}

/** Cancel an open refund and log why. The order row is locked by the caller. */
function order__refund_off(PDO $db, int $biz, int $order_id, array $refund, int $user_id, string $why): void {
    $db->prepare("UPDATE refunds SET status = 'cancelled' WHERE id = ? AND business_id = ? AND status IN ('asked', 'details_in')")
       ->execute([(int)$refund['id'], $biz]);
    order_log($db, $biz, $order_id, 'refund', $why, $user_id, ['refund_id' => (int)$refund['id']]);
}

/**
 * "Ask for account details": a refund waiting for where to send the money.
 * The customer gets the link by email when they have one (once per refund);
 * the page also offers it as a WhatsApp message (wa_text, to wa_phone, the
 * customer's number as wa.me wants it). Asking again changes the amount on
 * the same refund. Returns the refund with 'url', 'wa_text' and 'wa_phone'.
 */
function refund_ask(PDO $db, int $biz, int $order_id, float $amount, string $reason, int $user_id): array {
    $reason = mb_substr(trim($reason), 0, 300);
    return order__tx($db, function () use ($db, $biz, $order_id, $amount, $reason, $user_id) {
        $order    = order__lock($db, $biz, $order_id);
        $invoice  = order_invoice($db, $biz, $order);
        $business = order__business($db, $biz);
        $symbol   = (string)($business['currency_symbol'] ?? '₦');
        $paid     = $invoice ? money2((float)$invoice['amount_paid']) : 0.0;
        $amount   = money2($amount);
        if ($amount <= 0) {
            throw new DomainException('Enter how much you are giving back.');
        }
        if ($amount > $paid) {
            throw new DomainException('You can give back at most what was paid on this order: ' . planner_money($paid, $symbol) . '.');
        }
        $refund = refund_open($db, $biz, $order_id);
        if ($refund) {
            $db->prepare("UPDATE refunds SET amount = ?, reason = ? WHERE id = ? AND business_id = ?")
               ->execute([$amount, $reason ?: $refund['reason'], (int)$refund['id'], $biz]);
            $refund_id = (int)$refund['id'];
        } else {
            $db->prepare("
                INSERT INTO refunds (business_id, order_id, invoice_id, customer_id, status, amount, reason, token, asked_at, user_id)
                VALUES (?, ?, ?, ?, 'asked', ?, ?, ?, NOW(), ?)
            ")->execute([$biz, $order_id, $invoice['id'] ?? null, $invoice['customer_id'] ?? $order['customer_id'], $amount, $reason ?: null,
                         bin2hex(random_bytes(16)), $user_id]);
            $refund_id = (int)$db->lastInsertId();
        }
        $stmt = $db->prepare("SELECT * FROM refunds WHERE id = ?");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch();
        $customer = order__customer($db, $biz, (int)$refund['customer_id'], true);
        $name  = (string)($customer['full_name'] ?? $order['customer_name']);
        $email = (string)(($customer['email'] ?? '') ?: ($order['customer_email'] ?? ''));
        $first = mail_first_name($name);
        $url   = refund_url((string)$refund['token']);
        $money = planner_money($amount, $symbol);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db->prepare("UPDATE refunds SET asked_email = 1 WHERE id = ?")->execute([$refund_id]);
            $refund['asked_email'] = 1;
            $vars = ['first_name' => $first, 'shop_name' => (string)$business['name'], 'order_number' => (string)$order['order_number'],
                     'amount' => $money, 'refund_url' => $url];
            order__after(fn() => notify_email($biz, $email, $name, 'Your ' . $money . ' refund for order ' . $order['order_number'] . ' — ' . $business['name'],
                                              'refund_details', $vars, 'refund:' . $refund_id . ':ask:' . date('YmdHi'), 'shopper.refund_details'));   // "Ask again" sends again; a double tap doesn't
        }
        order_log($db, $biz, $order_id, 'refund_asked', 'Asked ' . $name . ' where to send ' . $money . ' back.', $user_id,
                  ['refund_id' => $refund_id, 'amount' => $amount]);

        return $refund + [
            'url'      => $url,
            'wa_text'  => 'Hello ' . $first . ', this is ' . $business['name'] . '. We are sending you ' . $money . ' back for order '
                        . $order['order_number'] . '. Tell us where it should go here: ' . $url,
            'wa_phone' => wa_digits((string)(($customer['phone'] ?? '') ?: ($order['customer_phone'] ?? '')), $business),
        ];
    });
}

/** The customer's page for a refund: where they say where the money should go, and later see the slip. */
function refund_url(string $token): string {
    return APP_URL . '/public/refund/' . $token;
}

/**
 * The customer's answer from the refund page: bank account or mobile
 * money, the name on it, the bank or network, the number. Owners and admins
 * hear the details are in. Returns what to fix, or null when saved.
 */
function refund_save_details(PDO $db, string $token, array $in): ?string {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return 'This link isn\'t working. Ask the shop to send it again.';
    }
    $kind = $in['payee_kind'] ?? '';
    if (!in_array($kind, ['bank', 'momo'], true)) {
        return 'Choose bank account or mobile money.';
    }
    $name   = mb_substr(trim((string)($in['payee_name'] ?? '')), 0, 150);
    $bank   = mb_substr(trim((string)($in['payee_bank'] ?? '')), 0, 100);
    $number = (string)preg_replace('/[\s.\-]/', '', (string)($in['payee_number'] ?? ''));
    if ($name === '') {
        return 'Type the name on the account.';
    }
    if ($bank === '') {
        return $kind === 'bank' ? 'Type the name of the bank.' : 'Type the mobile money network.';
    }
    if (!preg_match('/^\+?\d{6,20}$/', $number)) {
        return $kind === 'bank' ? 'Type the account number, digits only.' : 'Type the mobile money number, digits only.';
    }
    return order__tx($db, function () use ($db, $token, $kind, $name, $bank, $number) {
        $stmt = $db->prepare("SELECT * FROM refunds WHERE token = ? FOR UPDATE");
        $stmt->execute([$token]);
        $refund = $stmt->fetch();
        if (!$refund) {
            return 'This link isn\'t working. Ask the shop to send it again.';
        }
        if ($refund['status'] === 'paid') {
            return 'This refund has already been paid.';
        }
        if ($refund['status'] === 'cancelled') {
            return 'This refund was called off. Contact the shop if you have any questions.';
        }
        $biz = (int)$refund['business_id'];
        $db->prepare("
            UPDATE refunds SET status = 'details_in', payee_kind = ?, payee_name = ?, payee_bank = ?, payee_number = ?, details_at = NOW()
            WHERE id = ?
        ")->execute([$kind, $name, $bank, $number, (int)$refund['id']]);

        $order = $refund['order_id'] ? order_find($db, $biz, (int)$refund['order_id']) : null;
        if ($order) {
            order_log($db, $biz, (int)$order['id'], 'refund_details',
                      ($order['customer_name'] ?: 'The customer') . ' sent where to send the refund: ' . $bank . ' ending ' . substr($number, -4) . '.', null,
                      ['refund_id' => (int)$refund['id']]);
            $payee = ($kind === 'bank' ? 'Bank: ' : 'Mobile money: ') . $name . ', ' . $bank . ', ' . $number;
            order__after(fn() => notify('refund.details', $biz, [
                'refund_id' => (int)$refund['id'], 'order_id' => (int)$order['id'], 'order_number' => $order['order_number'],
                'customer' => $order['customer_name'], 'amount' => (float)$refund['amount'], 'payee' => $payee,
            ], ['dedupe' => $refund['id'] . ':' . md5($payee)]));
        }
        return null;
    });
}

/**
 * The owner gave money back: the refund is recorded on the order's invoice
 * (a minus payment) and gets its RFD number. Anything but cash needs the
 * transfer reference or a proof (picture or PDF), which go on the refund
 * slip. $in: amount, method, reference?, reason?, refund_id? (else the one
 * waiting on the order, else a new one). $file: a $_FILES entry or null.
 */
function refund_record(PDO $db, int $biz, int $order_id, array $in, ?array $file, int $user_id, string $symbol): array {
    $amount    = money2((float)($in['amount'] ?? 0));
    $method    = $in['method'] ?? '';
    $reference = mb_substr(trim((string)($in['reference'] ?? '')), 0, 100);
    $reason    = mb_substr(trim((string)($in['reason'] ?? '')), 0, 300);
    if (!in_array($method, ['cash', 'bank_transfer', 'mobile_money', 'pos', 'other'], true)) {
        throw new DomainException('Choose how you gave the money back.');
    }
    $has_file = $file && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($method !== 'cash' && $reference === '' && !$has_file) {
        throw new DomainException('Add the transfer reference or a screenshot, so it shows on the refund slip.');
    }
    $proof = $has_file ? order__store_proof($file, $biz) : null;
    try {
        return order__tx($db, function () use ($db, $biz, $order_id, $in, $amount, $method, $reference, $reason, $proof, $user_id, $symbol) {
            $order   = order__lock($db, $biz, $order_id);
            $invoice = order_invoice($db, $biz, $order);
            if (!$invoice) {
                throw new DomainException('Nothing was paid on this order.');
            }
            $refund = null;
            if (!empty($in['refund_id'])) {
                $stmt = $db->prepare("SELECT * FROM refunds WHERE id = ? AND business_id = ? AND order_id = ? FOR UPDATE");
                $stmt->execute([(int)$in['refund_id'], $biz, $order_id]);
                $refund = $stmt->fetch() ?: null;
                if (!$refund || !in_array($refund['status'], ['asked', 'details_in'], true)) {
                    throw new DomainException(($refund['status'] ?? '') === 'cancelled' ? 'That refund was called off.' : 'That refund is already recorded.');
                }
            } else {
                $refund = refund_open($db, $biz, $order_id);
            }

            $done = invoice_record_refund($db, $biz, (int)$invoice['id'], $amount, planner_today(), $method, $reason ?: null, $user_id, $symbol);
            if ($reference !== '') {
                $db->prepare("UPDATE payments SET reference = ? WHERE id = ? AND business_id = ?")->execute([$reference, $done['payment_id'], $biz]);
            }
            $number = generate_doc_number($db, $biz, 'RFD');
            if ($refund) {
                $db->prepare("
                    UPDATE refunds SET status = 'paid', doc_number = ?, invoice_id = ?, amount = ?, method = ?, reference = ?, proof_path = ?,
                        reason = COALESCE(?, reason), payment_id = ?, paid_at = NOW(), user_id = ?
                    WHERE id = ? AND business_id = ?
                ")->execute([$number, (int)$invoice['id'], $amount, $method, $reference ?: null, $proof, $reason ?: null, $done['payment_id'], $user_id,
                             (int)$refund['id'], $biz]);
                $refund_id = (int)$refund['id'];
            } else {
                $db->prepare("
                    INSERT INTO refunds (business_id, order_id, invoice_id, customer_id, doc_number, status, amount, method, reference, proof_path,
                        reason, token, payment_id, paid_at, user_id)
                    VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ")->execute([$biz, $order_id, (int)$invoice['id'], (int)$invoice['customer_id'], $number, $amount, $method, $reference ?: null, $proof,
                             $reason ?: null, bin2hex(random_bytes(16)), $done['payment_id'], $user_id]);
                $refund_id = (int)$db->lastInsertId();
            }
            order_sync_debt($db, $biz, $order_id);
            order_log($db, $biz, $order_id, 'refund', planner_money($amount, $symbol) . ' given back by ' . order__method_words($method) . '. Refund ' . $number . '.',
                      $user_id, ['refund_id' => $refund_id, 'amount' => $amount, 'method' => $method, 'reference' => $reference ?: null]);
            $stmt = $db->prepare("SELECT * FROM refunds WHERE id = ?");
            $stmt->execute([$refund_id]);
            return $stmt->fetch();
        });
    } catch (Throwable $e) {
        // Nothing was recorded, so the proof isn't kept either
        if ($proof) {
            @unlink(BASE_PATH . '/' . $proof);
        }
        throw $e;
    }
}

/* ══════════════════════════════════════════════════════════════
 *  INSIDE THIS FILE
 * ══════════════════════════════════════════════════════════════ */

/**
 * Run $work inside the caller's transaction, or in its own. In its own, what
 * order__after() was handed runs once the work is committed.
 */
function order__tx(PDO $db, callable $work): mixed {
    if ($db->inTransaction()) {
        return $work();
    }
    $queue = &order__after_queue();
    $queue = [];
    $db->beginTransaction();
    try {
        $out = $work();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $queue = null;
        throw $e;
    }
    $run = $queue;
    $queue = null;
    foreach ($run as $job) {
        try {
            $job();
        } catch (Throwable $e) {
            // The order is saved; a message that couldn't be queued is only logged
            error_log('Nfelia order after-commit: ' . $e->getMessage());
        }
    }
    return $out;
}

/** What to do once the work is saved: queued by order__tx(), or now inside the caller's own transaction. */
function order__after(callable $job): void {
    $queue = &order__after_queue();
    if (is_array($queue)) {
        $queue[] = $job;
        return;
    }
    $job();
}

function &order__after_queue(): ?array {
    static $queue = null;
    return $queue;
}

/** Lock the order row and return it, or say it isn't here. */
function order__lock(PDO $db, int $biz, int $order_id): array {
    $order = order_find($db, $biz, $order_id, true);
    if (!$order) {
        throw new DomainException('That order isn\'t here.');
    }
    return $order;
}

function order__business(PDO $db, int $biz): array {
    $stmt = $db->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$biz]);
    return $stmt->fetch() ?: ['id' => $biz, 'name' => '', 'currency_symbol' => '₦'];
}

/** A customer of this business (not deleted, unless $any). */
function order__customer(PDO $db, int $biz, int $customer_id, bool $any = false): ?array {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?" . ($any ? "" : " AND deleted_at IS NULL"));
    $stmt->execute([$customer_id, $biz]);
    return $stmt->fetch() ?: null;
}

/** The order's delivery note: the latest that wasn't deleted, a failed one last. */
function order__delivery(PDO $db, int $biz, array $order): ?array {
    $stmt = $db->prepare("
        SELECT * FROM deliveries
        WHERE business_id = ? AND deleted_at IS NULL AND (order_id = ? OR id = ?)
        ORDER BY status = 'failed', id DESC
        LIMIT 1
    ");
    $stmt->execute([$biz, (int)$order['id'], (int)($order['delivery_id'] ?? 0)]);
    return $stmt->fetch() ?: null;
}

/**
 * When a balance is due unless the owner says: the delivery or collection
 * day while it is still ahead, else a week from today (goods that left
 * today give the customer a week, not a balance due tonight).
 */
function order__default_pay_by(?string $deliver_by): string {
    $today = planner_today();
    return planner_valid_date($deliver_by) && $deliver_by > $today ? $deliver_by : planner_add_days($today, 7);
}

/** The account shown on the invoice for paying: the quote's, else the business's default. */
function order__bank_account(PDO $db, int $biz, array $order): ?int {
    if (!empty($order['quotation_id'])) {
        // Not one removed since the quote went out (Settings › Payment accounts)
        $stmt = $db->prepare("
            SELECT q.bank_account_id FROM quotations q
            JOIN bank_accounts b ON b.id = q.bank_account_id AND b.is_active = 1 AND b.deleted_at IS NULL
            WHERE q.id = ? AND q.business_id = ?
        ");
        $stmt->execute([(int)$order['quotation_id'], $biz]);
        if ($id = (int)$stmt->fetchColumn()) {
            return $id;
        }
    }
    $stmt = $db->prepare("
        SELECT id FROM bank_accounts
        WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
        ORDER BY is_default DESC, sort_order, id
        LIMIT 1
    ");
    $stmt->execute([$biz]);
    return (int)$stmt->fetchColumn() ?: null;
}

function order__method_words(string $method): string {
    return match ($method) {
        'cash'          => 'cash',
        'bank_transfer' => 'bank transfer',
        'mobile_money'  => 'mobile money',
        'pos'           => 'POS',
        'cheque'        => 'cheque',
        'kept'          => 'money kept for them',
        default         => 'another way',
    };
}

/**
 * Lines as typed, checked: [product_id?, name, quantity, unit_price, note?,
 * variant?]. A product must be this business's; its name fills an empty one.
 * Rows left completely empty by a form are skipped.
 */
function order__clean_items(PDO $db, int $biz, array $items): array {
    $lines = [];
    $product = $db->prepare("SELECT name FROM products WHERE id = ? AND business_id = ?");
    foreach ($items as $item) {
        $item  = (array)$item;
        $id    = (int)($item['product_id'] ?? 0) ?: null;
        $name  = trim((string)($item['name'] ?? ''));
        $qty   = round((float)($item['quantity'] ?? 0), 2);
        $price = round((float)($item['unit_price'] ?? 0), 2);
        if ($id) {
            $product->execute([$id, $biz]);
            $found = $product->fetchColumn();
            if ($found === false) {
                throw new DomainException('One of the items is no longer in your stock. Take it out and add it again.');
            }
            $name = $name !== '' ? $name : (string)$found;
        }
        if (!$id && $name === '' && $qty == 0.0 && $price == 0.0) {
            continue;
        }
        if ($name === '') {
            throw new DomainException('Give each item a name.');
        }
        if ($qty <= 0) {
            throw new DomainException('Each item needs a quantity above 0.');
        }
        if ($price < 0) {
            throw new DomainException('A price can\'t be below 0.');
        }
        $lines[] = [
            'product_id' => $id, 'name' => mb_substr($name, 0, 500), 'quantity' => $qty, 'unit_price' => $price,
            'line_total' => money2($qty * $price),
            'note'       => mb_substr(trim((string)($item['note'] ?? '')), 0, 255) ?: null,
            'variant'    => mb_substr(trim((string)($item['variant'] ?? '')), 0, 255) ?: null,
        ];
    }
    if (!$lines) {
        throw new DomainException('Add at least one item.');
    }
    return $lines;
}

/** Replace an order's lines with checked ones (order__clean_items), in the order given. */
function order__write_lines(PDO $db, int $order_id, array $lines): void {
    $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
    $insert = $db->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, variant_selection, item_note, line_total, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach (array_values($lines) as $i => $line) {
        $insert->execute([$order_id, $line['product_id'], $line['name'], $line['unit_price'], $line['quantity'], $line['variant'], $line['note'],
                          $line['line_total'], $i + 1]);
    }
}

/** An order line as an invoice or delivery note says it: its name, and the variant chosen. */
function order__line_words(array $row): string {
    return mb_substr($row['product_name'] . (!empty($row['variant_selection']) ? ' (' . $row['variant_selection'] . ')' : ''), 0, 500);
}

/**
 * Replace an invoice's lines with the order's (order_items rows). A product
 * that is gone altogether is left off the line, so the line still saves.
 */
function order__invoice_lines(PDO $db, int $biz, int $invoice_id, array $rows): void {
    $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoice_id]);
    $product = $db->prepare("SELECT id FROM products WHERE id = ? AND business_id = ?");
    $insert  = $db->prepare("
        INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, discount_percent, discount_amount, subtotal, sort_order)
        VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)
    ");
    foreach (array_values($rows) as $i => $row) {
        $product_id = null;
        if (!empty($row['product_id'])) {
            $product->execute([(int)$row['product_id'], $biz]);
            $product_id = (int)$product->fetchColumn() ?: null;
        }
        $insert->execute([$invoice_id, $product_id, order__line_words($row), $row['quantity'], $row['unit_price'], $row['line_total'], $i + 1]);
    }
}

/** Replace a delivery note's lines with the order's. */
function order__delivery_lines(PDO $db, int $delivery_id, array $rows): void {
    $db->prepare("DELETE FROM delivery_items WHERE delivery_id = ?")->execute([$delivery_id]);
    $insert = $db->prepare("INSERT INTO delivery_items (delivery_id, product_id, description, quantity, sort_order) VALUES (?, ?, ?, ?, ?)");
    foreach (array_values($rows) as $i => $row) {
        $insert->execute([$delivery_id, $row['product_id'] ?: null, order__line_words($row), $row['quantity'], $i + 1]);
    }
}

/**
 * A payment on the order's invoice and its receipt (REC, with the order,
 * the invoice and the payment), the debt kept in step, a line in the Log,
 * and, when $alert, the owners and admins told (not whoever recorded it).
 */
function order__payment(PDO $db, int $biz, array $order, float $amount, string $date, string $method, ?string $reference, ?string $note,
                        int $user_id, string $symbol, bool $alert, bool $sync_debt = true): array {
    $invoice = order_invoice($db, $biz, $order);
    if (!$invoice || $invoice['status'] === 'cancelled') {
        throw new DomainException('Confirm the order first, then record what was paid.');
    }
    if (!planner_valid_date($date)) {
        throw new DomainException('Pick the day it was paid.');
    }
    $paid = invoice_record_payment($db, $biz, (int)$invoice['id'], $amount, $date, $method, $reference ?: null, $note ?: null, $user_id, $symbol);
    $amount  = money2($amount);
    $balance = money2((float)$paid['new_balance']);

    $number = generate_doc_number($db, $biz, 'REC');
    $db->prepare("
        INSERT INTO receipts (business_id, invoice_id, payment_id, order_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$biz, (int)$invoice['id'], $paid['payment_id'], (int)$order['id'], (int)$invoice['customer_id'], $user_id, $number, $date,
                 $amount, $balance, $method, $note ?: null, generate_token()]);
    $receipt_id = (int)$db->lastInsertId();
    $db->prepare("UPDATE orders SET receipt_id = ? WHERE id = ? AND business_id = ? AND receipt_id IS NULL")
       ->execute([$receipt_id, (int)$order['id'], $biz]);
    doc_made($db, 'receipt', $receipt_id, $user_id);

    if ($sync_debt) {
        order_sync_debt($db, $biz, (int)$order['id']);
    }
    order_log($db, $biz, (int)$order['id'], 'payment',
              planner_money($amount, $symbol) . ' paid ' . ($method === 'kept' ? 'from ' : 'by ') . order__method_words($method) . '. Receipt ' . $number . '.',
              $user_id, ['payment_id' => $paid['payment_id'], 'receipt_id' => $receipt_id, 'amount' => $amount, 'method' => $method, 'reference' => $reference ?: null]);

    if ($alert) {
        $customer = (string)(order__customer($db, $biz, (int)$invoice['customer_id'], true)['full_name'] ?? '');
        order__after(fn() => notify('payment.invoice', $biz, [
            'invoice_id' => (int)$invoice['id'], 'invoice_number' => $invoice['doc_number'],
            'order_id' => (int)$order['id'], 'order_number' => $order['order_number'],
            'customer' => trim($customer), 'amount' => $amount, 'method' => str_replace('_', ' ', $method), 'balance' => $balance,
        ], ['actor' => $user_id, 'dedupe' => (string)$paid['payment_id']]));
    }
    return ['payment_id' => $paid['payment_id'], 'receipt_id' => $receipt_id, 'new_balance' => $balance];
}

/**
 * Kept money pays what it can of the order's balance: a 'kept' payment with
 * its receipt, and a minus line in kept_money. The order row is locked by
 * the caller. Returns the amount used.
 */
function order__apply_kept(PDO $db, int $biz, array $order, int $user_id, string $symbol, bool $sync_debt): float {
    $invoice = order_invoice($db, $biz, $order);
    if (!$invoice || !in_array($invoice['status'], ['sent', 'partially_paid', 'overdue'], true)) {
        return 0.0;
    }
    $customer_id = (int)$invoice['customer_id'];
    // One at a time per customer, so the same kept money is never used twice
    $db->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ? FOR UPDATE")->execute([$customer_id, $biz]);
    $amount = money2(min(kept_balance($db, $biz, $customer_id), max(0, (float)$invoice['total'] - (float)$invoice['amount_paid'])));
    if ($amount <= 0) {
        return 0.0;
    }
    $paid = order__payment($db, $biz, $order, $amount, planner_today(), 'kept', null, 'Paid from money kept for them', $user_id, $symbol, false, $sync_debt);
    $db->prepare("INSERT INTO kept_money (business_id, customer_id, order_id, payment_id, amount, note, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute([$biz, $customer_id, (int)$order['id'], $paid['payment_id'], -$amount, KEPT_USED_NOTE . $order['order_number'], $user_id]);
    return $amount;
}

/**
 * Money moved between a customer's own orders (method 'kept'): minus to keep
 * what was paid over, plus to put it back. Not new money and no receipt.
 * Returns the payment id.
 */
function order__kept_payment(PDO $db, int $biz, int $invoice_id, float $amount, string $note, int $user_id): int {
    $invoice = invoice_lock($db, $biz, $invoice_id);
    $amount  = money2($amount);
    $db->prepare("
        INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference, note)
        VALUES (?, ?, ?, ?, ?, 'kept', NULL, ?)
    ")->execute([$biz, $invoice_id, $user_id, $amount, planner_today(), $note]);
    $payment_id = (int)$db->lastInsertId();
    $total    = money2((float)$invoice['total']);
    $new_paid = money2((float)$invoice['amount_paid'] + $amount);
    $status   = match (true) {
        $new_paid >= $total => 'paid',
        $new_paid > 0       => 'partially_paid',
        default             => 'sent',
    };
    $db->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ? AND business_id = ?")
       ->execute([$new_paid, money2(max(0, $total - $new_paid)), $status, $invoice_id, $biz]);
    return $payment_id;
}

/**
 * The order moves (order_set_stage, a Tasks tick, its delivery note). With
 * $from_delivery the delivery note already moved, so it is left as it is.
 */
function order__move(PDO $db, int $biz, array $order, string $to, ?int $user_id, bool $from_delivery): void {
    $order_id    = (int)$order['id'];
    $status      = (string)$order['status'];
    $is_delivery = $order['fulfillment_type'] === 'delivery';
    $delivery    = order__delivery($db, $biz, $order);
    $invoice     = order_invoice($db, $biz, $order);
    $invoice_id  = $invoice && $invoice['status'] !== 'cancelled' ? (int)$invoice['id'] : 0;
    $not_now = match ($status) {
        'pending', 'pending_verification' => 'Confirm the order first.',
        'rejected'  => 'This order was turned down.',
        'cancelled' => 'This order was cancelled.',
        default     => null,
    };

    if ($to === 'ready') {
        if ($status === 'ready' || ($status === 'fulfilled' && $from_delivery)) {
            return;
        }
        if ($not_now || $status === 'fulfilled') {
            throw new DomainException($not_now ?? 'This order is already done.');
        }
        $db->prepare("UPDATE orders SET status = 'ready' WHERE id = ? AND business_id = ?")->execute([$order_id, $biz]);
        if (!$from_delivery && $delivery && $delivery['status'] === 'pending_dispatch') {
            $db->prepare("UPDATE deliveries SET status = 'dispatched', dispatch_date = NOW() WHERE id = ? AND business_id = ?")
               ->execute([(int)$delivery['id'], $biz]);
        }
        order_log($db, $biz, $order_id, 'ready', $is_delivery ? 'Sent out: on its way.' : 'Ready for pickup.', $user_id);
        order__after(fn() => shop_order_tell($db, $order_id, $is_delivery ? 'on_the_way' : 'ready'));
        return;
    }

    if ($to === 'done') {
        if ($status === 'fulfilled') {
            return;
        }
        if ($not_now) {
            throw new DomainException($not_now);
        }
        $db->prepare("UPDATE orders SET status = 'fulfilled', done_at = NOW() WHERE id = ? AND business_id = ?")->execute([$order_id, $biz]);
        if (!$from_delivery && $delivery && !in_array($delivery['status'], ['delivered', 'signed_off'], true)) {
            $db->prepare("UPDATE deliveries SET status = 'delivered', dispatch_date = COALESCE(dispatch_date, NOW()), actual_delivery = NOW() WHERE id = ? AND business_id = ?")
               ->execute([(int)$delivery['id'], $biz]);
        }
        // Its invoice's delivery promise is kept: off Tasks & Calendar (P2)
        if ($invoice_id) {
            planner_invoice_delivered($db, $biz, $invoice_id, false, $user_id);
        }
        // A walk-in gave no name: there is nobody to ask for a review
        $walk_in = (int)(order__customer($db, $biz, (int)$order['customer_id'], true)['is_walk_in'] ?? 0) === 1;
        if (!$walk_in) {
            review_invite($db, $order_id);
        }
        order_log($db, $biz, $order_id, 'done', $is_delivery ? 'Delivered.' : 'Collected.', $user_id);
        order__after(fn() => shop_order_tell($db, $order_id, 'delivered'));
        return;
    }

    // undo_done: marked done by mistake
    if (in_array($status, ['approved', 'processing'], true)) {
        return;
    }
    if ($status !== 'fulfilled') {
        throw new DomainException('Only an order that is done can be moved back.');
    }
    if ($delivery && $delivery['status'] === 'signed_off') {
        throw new DomainException('The customer signed for this delivery, so it stays delivered.');
    }
    $db->prepare("UPDATE orders SET status = 'approved', done_at = NULL WHERE id = ? AND business_id = ?")->execute([$order_id, $biz]);
    if ($delivery && $delivery['status'] === 'delivered') {
        $db->prepare("UPDATE deliveries SET status = 'pending_dispatch', dispatch_date = NULL, actual_delivery = NULL WHERE id = ? AND business_id = ?")
           ->execute([(int)$delivery['id'], $biz]);
    }
    if ($invoice_id) {
        planner_invoice_delivered($db, $biz, $invoice_id, true, $user_id);
    }
    order_log($db, $biz, $order_id, 'undone', 'Moved back to Confirmed.', $user_id);
}

/**
 * Keep a refund's proof (a picture or a PDF) where only the business can
 * open it, as payment proofs are. Pictures are saved again as a JPEG, so
 * nothing but the picture is kept. Returns its path from the app's folder.
 */
function order__store_proof(array $file, int $biz): string {
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        throw new DomainException('The file didn\'t come through. Add it again.');
    }
    $max = max(1, (int)($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 10));
    if (filesize($file['tmp_name']) > $max * 1024 * 1024) {
        throw new DomainException('That file is bigger than ' . $max . ' MB. Add a smaller screenshot or PDF.');
    }
    $mime = mime_content_type($file['tmp_name']);
    $name = bin2hex(random_bytes(16));
    $dir  = private_file_dir('refund_proofs', $biz);
    $rel  = 'storage/private/refund_proofs/' . $biz . '/' . $name;
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        if (!save_image($file, $dir . '/' . $name . '.jpg', 1600, 'jpg')) {
            throw new DomainException('That picture couldn\'t be read. Add a screenshot or a PDF.');
        }
        return $rel . '.jpg';
    }
    if ($mime === 'application/pdf') {
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name . '.pdf')) {
            throw new DomainException('That PDF couldn\'t be kept. Add it again.');
        }
        return $rel . '.pdf';
    }
    throw new DomainException('Add a screenshot or photo (JPG, PNG or WebP) or a PDF.');
}

/** The rows order_rows() and order_counts() read: each order with its invoice and delivery note (business id is the one placeholder). */
function order__list_sql(): string {
    return "
        SELECT o.*, i.id AS inv_id, i.doc_number AS inv_number, i.total AS inv_total, i.amount_paid AS inv_paid,
               i.balance_due AS inv_balance, i.status AS inv_status, d.status AS delivery_status
        FROM orders o
        LEFT JOIN invoices i ON i.id = (SELECT i2.id FROM invoices i2 WHERE i2.business_id = o.business_id
                                          AND (i2.order_id = o.id OR i2.id = o.invoice_id)
                                        ORDER BY i2.status = 'cancelled', i2.id DESC LIMIT 1)
        LEFT JOIN deliveries d ON d.id = (SELECT d2.id FROM deliveries d2 WHERE d2.business_id = o.business_id AND d2.deleted_at IS NULL
                                            AND (d2.order_id = o.id OR d2.id = o.delivery_id)
                                          ORDER BY d2.status = 'failed', d2.id DESC LIMIT 1)
        WHERE o.business_id = ?";
}

/** Each list view as a condition on order__list_sql()'s rows, matching order_stage() and order_pay_tag(). */
function order__list_views(): array {
    $done = "(x.status = 'fulfilled' OR (x.status IN ('approved','processing','ready') AND COALESCE(x.delivery_status, '') IN ('delivered','signed_off')))";
    return [
        'confirm'  => "(x.status IN ('pending','pending_verification'))",
        'progress' => "(x.status IN ('approved','processing','ready') AND NOT {$done})",
        'balance'  => "(x.inv_id IS NOT NULL AND x.inv_status NOT IN ('cancelled','draft') AND x.inv_balance > 0 AND x.status NOT IN ('cancelled','rejected'))",
        'done'     => $done,
    ];
}

/** The WHERE for a list: [sql, params]. */
function order__list_where(array $f): array {
    $where = ['1'];
    $params = [];
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        // Phones compare as digits: "0803 123 4567" is found by 08031234567 and +2348031234567
        $where[] = "(x.order_number LIKE ? OR x.customer_name LIKE ? OR REGEXP_REPLACE(COALESCE(x.customer_phone, ''), '[^0-9]', '') LIKE ? OR x.inv_number LIKE ?)";
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
        $digits = preg_replace('/\D/', '', $q);
        array_push($params, $like, $like, strlen($digits) >= 4 ? '%' . substr($digits, -10) . '%' : $like, $like);
    }
    if (!empty($f['customer_id'])) {
        $where[] = "x.customer_id = ?";
        $params[] = (int)$f['customer_id'];
    }
    $views = order__list_views();
    if (isset($views[$f['view'] ?? ''])) {
        $where[] = $views[$f['view']];
    }
    return [implode(' AND ', $where), $params];
}
