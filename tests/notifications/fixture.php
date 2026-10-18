<?php
/**
 * The world the notification tests run in, rebuilt from nothing on each run.
 * It WIPES every business table: only ever against a local or CI database
 * (lib.php's t_guard() checks before anything calls this).
 *
 *   Business 1  Nfelia            the Nfelia team (platform alerts land here)
 *               Tolu (owner, 10), Sade (staff, 11)
 *   Business 2  Ada's Hair & Co   a shop at adas-hair.<host>, paid by card
 *               Ada (owner, 20), Bisi (admin, 21), Chidi (staff, 22),
 *               Dayo (staff who left, 23)
 *   Business 3  Kola Prints       another business, on a free trial
 *               Kola (owner, 30)
 *
 * Everyone's password is T_PASSWORD. Ids are fixed so tests can name things.
 */

const FX_TABLES = [
    'notify_outbox', 'notifications', 'notification_prefs', 'push_subscriptions', 'user_devices', 'user_passkeys', 'sessions',
    'account_tokens', 'auth_throttle', 'planner_sends', 'planner_dismissals', 'tasks', 'reviews', 'bookings', 'order_items', 'orders',
    'quote_requests', 'quotation_activities', 'quotation_items', 'quotations', 'debt_settlements', 'debts', 'payments', 'receipt_items',
    'receipts', 'invoice_items', 'invoices', 'delivery_items', 'deliveries', 'deeds', 'stock_movements', 'product_images', 'products',
    'customers', 'support_tickets', 'subscription_payments', 'subscription_history', 'subscription_status_log', 'subscriptions',
    'referrals', 'general_feedback', 'document_counters', 'shop_name_holds', 'storefront_versions', 'shop_posts', 'bank_accounts',
    'users', 'businesses', 'countries',
];

function fixture_reset(): void
{
    $db = get_db();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (FX_TABLES as $t) {
        $db->exec("DELETE FROM `{$t}`");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    $today = (string)$db->query("SELECT CURDATE()")->fetchColumn();
    $day   = fn(int $n) => date('Y-m-d', strtotime("{$today} {$n} days"));
    $tok   = fn(int $n = 32) => bin2hex(random_bytes($n / 2));
    $pw    = password_hash(T_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);

    $db->exec("INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, monthly_price, extra_seat_price, trial_days, is_active)
               VALUES (1, 'Nigeria', 'NG', '🇳🇬', 'Nigerian Naira', 'NGN', '₦', 'paystack', 10000, 5000, 14, 1),
                      (2, 'Ghana', 'GH', '🇬🇭', 'Ghana Cedi', 'GHS', 'GH₵', 'mobile_money', 150, 75, 14, 1)");

    $biz = $db->prepare("INSERT INTO businesses (id, name, slug, doc_prefix, subdomain, subdomain_status, storefront_look, landing_enabled, primary_color,
                            secondary_color, email, phone, whatsapp, email_verified_at, currency_symbol, country_id, onboarding_complete, onboarding_step, is_active)
                         VALUES (?, ?, ?, ?, ?, 'active', 'glow', 1, '#8B004A', '#B69F5E', ?, '08035550100', '2348035550100', NOW(), '₦', 1, 1, 5, 1)");
    $biz->execute([1, 'Nfelia', 'nfelia-team', 'NFL', 'nfelia-team', 'team@nfelia.test']);
    $biz->execute([2, 'Ada\'s Hair & Co', 'adas-hair', 'ADA', 'adas-hair', 'hello@adashair.test']);
    $biz->execute([3, 'Kola Prints', 'kola-prints', 'KOL', 'kola-prints', 'hello@kolaprints.test']);

    $user = $db->prepare("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    foreach ([
        [10, 1, 'owner', 'Tolu Nfelia', 'tolu@nfelia.test', 1], [11, 1, 'staff', 'Sade Support', 'sade@nfelia.test', 1],
        [20, 2, 'owner', 'Ada Obi', 'ada@adashair.test', 1], [21, 2, 'admin', 'Bisi Admin', 'bisi@adashair.test', 1],
        [22, 2, 'staff', 'Chidi Staff', 'chidi@adashair.test', 1], [23, 2, 'staff', 'Dayo Left', 'dayo@adashair.test', 0],
        [30, 3, 'owner', 'Kola Ade', 'kola@kolaprints.test', 1],
    ] as [$id, $b, $role, $name, $email, $active]) {
        $user->execute([$id, $b, $role, $name, $email, $pw, $active]);
    }

    $db->prepare("INSERT INTO subscriptions (business_id, country_id, payment_method, plan, status, user_seats, extra_seats, monthly_amount,
                    current_period_start, current_period_end, paystack_subscription_code, paystack_customer_code)
                  VALUES (1, 1, 'paystack', 'standard', 'active', 5, 0, 10000, ?, ?, NULL, NULL),
                         (2, 1, 'paystack', 'standard', 'active', 5, 0, 10000, ?, ?, 'SUB_ada', 'CUS_ada')")
       ->execute([$day(-10), $day(20), $day(-10), $day(20)]);
    $db->prepare("INSERT INTO subscriptions (business_id, country_id, plan, status, user_seats, trial_ends_at) VALUES (3, 1, 'trial', 'trial', 1, ?)")
       ->execute([$day(10)]);

    // Ada's customers, stock, documents and money.
    $db->exec("INSERT INTO customers (id, business_id, full_name, phone, whatsapp, email) VALUES
        (200, 2, 'Ngozi Eze', '08031110000', '2348031110000', 'ngozi@example.test'),
        (201, 2, 'Femi & Sons \"Wholesale\"', '08031110001', '2348031110001', NULL),
        (300, 3, 'Kola Customer', '08031110300', NULL, NULL)");
    $db->exec("INSERT INTO products (id, business_id, name, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service, is_active) VALUES
        (300, 2, 'Body Wave 18in', 65000, 40000, 10, 3, 'bundle', 0, 1),
        (301, 2, 'HD Closure 5x5', 55000, 30000, 1, 4, 'piece', 0, 1)");
    // A service booked by the day, with a deposit; the shop opens every day.
    $db->exec("INSERT INTO products (id, business_id, name, sell_price, stock_qty, is_service, booking_mode, deposit_type, deposit_value, is_active)
               VALUES (302, 2, 'Bridal styling', 80000, 0, 1, 'day', 'fixed', 20000, 1)");
    $open = ['open' => '07:00', 'close' => '21:00'];
    $db->prepare("UPDATE businesses SET booking_settings = ? WHERE id = 2")
       ->execute([json_encode(['hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $open), 'notice' => 0, 'ahead' => 60])]);

    $inv = $db->prepare("INSERT INTO invoices (id, business_id, customer_id, user_id, doc_number, status, issue_date, due_date, subtotal, total, amount_paid, balance_due, token)
                         VALUES (?, 2, ?, 20, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $inv->execute([400, 200, 'ADA-INV-0400', 'sent', $day(-2), $day(7), 100000, 100000, 100000, $tok()]);
    $inv->execute([401, 200, 'ADA-INV-0401', 'sent', $day(-20), $day(-3), 50000, 50000, 50000, $tok()]);
    $inv->execute([402, 201, 'ADA-INV-0402', 'overdue', $day(-30), $day(-10), 40000, 40000, 40000, $tok()]);
    $db->exec("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal) VALUES
        (400, 'Body Wave 18in', 1, 100000, 100000), (401, 'Closure', 1, 50000, 50000), (402, 'Wig revamp', 1, 40000, 40000)");

    $quote = $db->prepare("INSERT INTO quotations (id, business_id, customer_id, user_id, doc_number, status, issue_date, expiry_date, subtotal, total, token)
                           VALUES (?, 2, 200, 20, ?, ?, ?, ?, 250000, 250000, ?)");
    $quote->execute([500, 'ADA-QUO-0500', 'sent', $day(-1), $day(5), 'fxsign' . $tok(20)]);
    $quote->execute([501, 'ADA-QUO-0501', 'sent', $day(-1), $day(5), 'fxdecline' . $tok(20)]);
    $quote->execute([502, 'ADA-QUO-0502', 'sent', $day(-1), $day(5), 'fxcomment' . $tok(20)]);
    $quote->execute([503, 'ADA-QUO-0503', 'sent', $day(-9), $day(-1), 'fxexpired' . $tok(20)]);
    $quote->execute([504, 'ADA-QUO-0504', 'accepted', $day(-9), $day(5), 'fxanswered' . $tok(20)]);

    $db->prepare("INSERT INTO deliveries (id, business_id, customer_id, doc_number, token, signoff_token, status, recipient_name, recipient_phone, delivery_address)
                  VALUES (600, 2, 200, 'ADA-DN-0600', ?, ?, 'dispatched', 'Ngozi Eze', '08031110000', '12 Allen Avenue, Ikeja'),
                         (601, 2, 200, 'ADA-DN-0601', ?, ?, 'pending_dispatch', 'Ngozi Eze', '08031110000', '12 Allen Avenue, Ikeja')")
       ->execute([$tok(), $tok(), $tok(), $tok()]);

    $order = $db->prepare("INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
                              customer_email, delivery_address, payment_method, payment_proof_path, currency_code, subtotal, total, status, delivery_id)
                           VALUES (?, 2, 200, ?, ?, 'cart_pop', ?, 'Ngozi Eze', '08031110000', ?, '12 Allen Avenue, Ikeja', ?, ?, 'NGN', ?, ?, ?, ?)");
    $order->execute([700, 'ORD-0700', $tok(), 'delivery', 'ngozi@example.test', 'bank_transfer', 'uploads/order_proofs/2/proof.png', 65000, 65000, 'pending_verification', null]);
    $order->execute([701, 'ORD-0701', $tok(), 'pickup', 'ngozi@example.test', 'none', null, 55000, 55000, 'pending', null]);
    $order->execute([702, 'ORD-0702', $tok(), 'delivery', 'ngozi@example.test', 'bank_transfer', null, 65000, 65000, 'fulfilled', 600]);
    $order->execute([703, 'ORD-0703', $tok(), 'pickup', 'ngozi@example.test', 'none', null, 65000, 65000, 'approved', null]);
    $db->exec("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES
        (700, 300, 'Body Wave 18in', 65000, 1, 65000), (701, 301, 'HD Closure 5x5', 55000, 1, 55000),
        (702, 300, 'Body Wave 18in', 65000, 1, 65000), (703, 300, 'Body Wave 18in', 65000, 1, 65000)");
    $db->prepare("INSERT INTO reviews (id, business_id, order_id, customer_name, token, status) VALUES (1, 2, 702, 'Ngozi Eze', ?, 'invited')")
       ->execute([$tok(32)]);

    $db->exec("INSERT INTO debts (id, business_id, customer_id, user_id, type, source, description, counterparty_name, counterparty_phone, total_amount, amount_settled, balance, status)
               VALUES (800, 2, 200, 20, 'receivable', 'manual', 'Balance on wig revamp', 'Ngozi Eze', '08031110000', 30000, 0, 30000, 'active')");

    $db->exec("INSERT INTO tasks (id, business_id, kind, title, due_date, assigned_to, created_by) VALUES
        (900, 2, 'task', 'Restock closures', CURDATE(), 22, 20)");
}

/** A business's subscription row, reshaped for one check. */
function fixture_subscription(int $business_id, array $set): void
{
    $cols = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($set)));
    exec_sql("UPDATE subscriptions SET {$cols} WHERE business_id = ?", [...array_values($set), $business_id]);
}
