<?php
/**
 * Nfelia — what a business has paid for, and what that lets it use.
 *
 * billing_access() is the one answer for the app (app/middleware/
 * subscription_check.php), the shop (shop_visibility() in shop_links.php) and
 * reminders (planner_business_can_use() in planner.php), so all three open
 * and lock on the same day.
 *
 * Paying buys one month at a time, by card (PaystackController) or Mobile
 * Money (MoMoController, once the Nfelia team approves the receipt in Nfelia
 * Ops → Payments to check). When a month
 * ends with no new payment, the business has BILLING_GRACE_DAYS more days,
 * then the app and the shop lock until it pays (Tom, October 2026).
 */

/** Days after a paid month ends before the app and the shop lock. */
const BILLING_GRACE_DAYS = 3;

/** Staff a business may add during its free trial, beside the owner (Tom, October 2026). */
const TRIAL_STAFF_SEATS = 1;

/**
 * What the business may use today, from its subscriptions row (null when it
 * has none, which is let through as before):
 *   'trial'   on its free trial, to the end of trial_ends_at
 *   'paid'    inside a paid month (a cancelled one runs to the end of its month)
 *   'grace'   the month ended up to BILLING_GRACE_DAYS ago, or a card renewal
 *             failed and its grace (grace_ends_at) is still running
 *   'locked'  anything else: trial over, grace over, suspended
 * Dates are whole days in Lagos time: a month ending on the 18th is paid all
 * of the 18th.
 */
function billing_access(?array $sub, ?string $today = null): string {
    if (!$sub || empty($sub['status'])) {
        return 'paid';
    }
    $today      = $today ?? date('Y-m-d');
    $trial_end  = !empty($sub['trial_ends_at']) ? substr((string)$sub['trial_ends_at'], 0, 10) : null;
    $period_end = !empty($sub['current_period_end']) ? substr((string)$sub['current_period_end'], 0, 10) : null;

    switch ($sub['status']) {
        case 'trial':
            return ($trial_end === null || $today <= $trial_end) ? 'trial' : 'locked';

        case 'active':
            // No end date, or already past it when this rule shipped (left
            // as they are until their next payment, Tom's decision)
            if ($period_end === null || !empty($sub['expiry_exempt']) || $today <= $period_end) {
                return 'paid';
            }
            return $today <= billing_add_days($period_end, BILLING_GRACE_DAYS) ? 'grace' : 'locked';

        case 'cancelled':
            return ($period_end !== null && $today <= $period_end) ? 'paid' : 'locked';

        case 'past_due':
            $until = !empty($sub['grace_ends_at'])
                ? substr((string)$sub['grace_ends_at'], 0, 10)
                : ($period_end !== null ? billing_add_days($period_end, BILLING_GRACE_DAYS) : null);
            return ($until !== null && $today <= $until) ? 'grace' : 'locked';

        case 'pending_verification':
            // Left by a Mobile Money receipt sent before receipts stopped
            // unlocking the app: it keeps what its trial or month still gives.
            if ($trial_end !== null && $today <= $trial_end) return 'trial';
            if ($period_end !== null && $today <= $period_end) return 'paid';
            return 'locked';

        default: // suspended
            return 'locked';
    }
}

/**
 * How many people the business may have, the owner included: the seats it
 * pays for (user_seats + extra_seats), and during the free trial at least the
 * owner and TRIAL_STAFF_SEATS staff. Every seat check reads this (adding
 * staff, the Team tab, check_subscription()). After the trial the paid seats
 * apply again: staff added during it keep their accounts, and only adding
 * more is limited.
 */
function billing_seats(?array $sub, ?string $today = null): int {
    $paid = max(1, (int)($sub['user_seats'] ?? 1)) + max(0, (int)($sub['extra_seats'] ?? 0));
    return billing_access($sub, $today) === 'trial' ? max($paid, 1 + TRIAL_STAFF_SEATS) : $paid;
}

/** $date plus $days, as Y-m-d. */
function billing_add_days(string $date, int $days): string {
    return (new DateTimeImmutable(substr($date, 0, 10)))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/**
 * One month after $date, kept inside the next month: 31 January gives
 * 28 (or 29) February, not 3 March.
 */
function billing_add_month(string $date): string {
    $d    = new DateTimeImmutable(substr($date, 0, 10));
    $next = $d->modify('first day of next month');
    $day  = min((int)$d->format('j'), (int)$next->format('t'));
    return $next->setDate((int)$next->format('Y'), (int)$next->format('n'), $day)->format('Y-m-d');
}

/**
 * The month a payment made today buys: it starts when the current paid month
 * ends (paying early adds a month, it never throws days away), or today if
 * that month is already over. Returns [start, end] as Y-m-d.
 */
function billing_next_period(?array $sub, ?string $today = null): array {
    $today      = $today ?? date('Y-m-d');
    $period_end = !empty($sub['current_period_end']) ? substr((string)$sub['current_period_end'], 0, 10) : null;
    $running    = $period_end !== null && $period_end >= $today
               && in_array($sub['status'] ?? '', ['active', 'cancelled', 'past_due', 'pending_verification'], true);
    $start = $running ? $period_end : $today;
    return [$start, billing_add_month($start)];
}

/**
 * Start a paid month for a business, inside the caller's transaction:
 * the subscription becomes active for [start, end], with $extra_seats seats
 * beside the owner. When $covered is a list of user ids (the staff the owner
 * ticked when paying), every other active team member is switched off.
 * Returns the period [start, end].
 */
function billing_start_month(PDO $db, int $biz_id, float $amount, int $extra_seats, ?array $covered, string $method, string $reference): array {
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE business_id = ? FOR UPDATE");
    $stmt->execute([$biz_id]);
    $sub = $stmt->fetch() ?: null;
    [$start, $end] = billing_next_period($sub);
    $extra_seats = max(0, min(MAX_SEATS - 1, $extra_seats));

    $db->prepare("
        INSERT INTO subscriptions
            (business_id, plan, status, user_seats, extra_seats, monthly_amount, payment_method,
             current_period_start, current_period_end, grace_ends_at, last_payment_ref, last_payment_date, expiry_exempt)
        VALUES (?, 'standard', 'active', 1, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 0)
        ON DUPLICATE KEY UPDATE
            plan = 'standard', status = 'active',
            extra_seats = VALUES(extra_seats), monthly_amount = VALUES(monthly_amount),
            payment_method = VALUES(payment_method),
            current_period_start = VALUES(current_period_start), current_period_end = VALUES(current_period_end),
            grace_ends_at = VALUES(grace_ends_at),
            last_payment_ref = VALUES(last_payment_ref), last_payment_date = CURDATE(),
            trial_ends_at = NULL, cancelled_at = NULL, cancel_reason = NULL,
            expiry_exempt = 0, updated_at = NOW()
    ")->execute([
        $biz_id, $extra_seats, $amount, $method,
        $start, $end, billing_add_days($end, BILLING_GRACE_DAYS) . ' 23:59:59',
        $reference,
    ]);

    if ($covered !== null) {
        billing_keep_only($db, $biz_id, $covered);
    }
    return [$start, $end];
}

/**
 * Switch off every active team member except the owner and the ids in
 * $keep (who a payment covers). Returns how many were switched off.
 */
function billing_keep_only(PDO $db, int $biz_id, array $keep): int {
    $keep   = array_values(array_unique(array_map('intval', $keep)));
    $params = [$biz_id];
    $not_in = '';
    if ($keep) {
        $not_in  = ' AND id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')';
        $params  = array_merge($params, $keep);
    }
    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE business_id = ? AND role <> 'owner' AND is_active = 1" . $not_in);
    $stmt->execute($params);
    $off = $stmt->rowCount();
    // Their open tasks come to the owner meanwhile (P1)
    if ($off && function_exists('planner_hand_back_tasks')) {
        planner_hand_back_tasks($db, $biz_id);
    }
    return $off;
}

/** The business's active team members other than the owner. */
function billing_team(PDO $db, int $biz_id): array {
    $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE business_id = ? AND is_active = 1 AND role <> 'owner' ORDER BY full_name");
    $stmt->execute([$biz_id]);
    return $stmt->fetchAll();
}

/**
 * The staff a payment covers: the ticked ids that are really active team
 * members of this business, at most MAX_SEATS - 1 of them.
 */
function billing_covered_from_post(PDO $db, int $biz_id, $posted): array {
    $team = array_column(billing_team($db, $biz_id), 'id');
    $ids  = array_map('intval', is_array($posted) ? $posted : explode(',', (string)$posted));
    return array_slice(array_values(array_intersect($team, $ids)), 0, MAX_SEATS - 1);
}

/**
 * What a business pays, in its country's money: a month (the one paying now
 * would buy) and each extra seat with it, the month already running and a
 * seat added to it (monthly_now, seat_now), a Records Book. From prices_for()
 * (app/helpers/prices.php): a price changed in Ops → Prices applies to a
 * business that was already here from the notice date it was given, and a
 * month that started before then keeps the old price.
 */
function billing_prices(PDO $db, int $biz_id): array {
    require_once BASE_PATH . '/app/controllers/CountryController.php';
    require_once __DIR__ . '/prices.php';
    $country = CountryController::get_country_for_business($db, $biz_id);
    $stmt = $db->prepare("SELECT b.created_at AS joined, s.* FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id WHERE b.id = ?");
    $stmt->execute([$biz_id]);
    $row    = $stmt->fetch() ?: [];
    $joined = (string)($row['joined'] ?? date('Y-m-d H:i:s'));
    [$month_starts] = billing_next_period(!empty($row['status']) ? $row : null);
    $running_from = !empty($row['current_period_start']) ? substr((string)$row['current_period_start'], 0, 10) : null;
    return [
        'country'  => $country,
        'method'   => $country['payment_method'] ?? 'paystack',
        'currency' => $country['currency_code'] ?? 'NGN',
        'symbol'   => $country['currency_symbol'] ?? '₦',
        'monthly'  => prices_for($db, $country, 'month', $joined, $month_starts),
        'monthly_now' => prices_for($db, $country, 'month', $joined, $running_from),
        'seat'     => prices_for($db, $country, 'seat', $joined, $month_starts),
        'seat_now' => prices_for($db, $country, 'seat', $joined, $running_from),
        'book'     => prices_for($db, $country, 'book', $joined),
        'coming'   => prices_coming($db, $country, $joined),
    ];
}
