<?php
/**
 * Free trials in Ops: every business on its free trial, on a board by how
 * far it has got (Ops › Free trials, OpsClientsController::trials), and the
 * "set up, not using it" leads Sales gets from the cron (ops_sales_cron).
 *
 * Why (27 Nov 2026): of the five businesses that found Nfelia on their own
 * in November, four finished setting up and only one ever used it, and Ops
 * could only see that by opening each client.
 *
 * "Using it" means what the getting-started emails mean
 * (app/helpers/getting_started.php): something to sell and a first sale.
 */

const TRIAL_STAGES = [
    'setup'   => ['label' => 'Not set up',           'hint' => 'Signed up, setup not finished'],
    'idle'    => ['label' => 'Set up, not using it', 'hint' => 'Nothing added yet'],
    'started' => ['label' => 'Getting started',      'hint' => 'Added things, no first sale yet'],
    'using'   => ['label' => 'Using it',             'hint' => 'Selling with Nfelia'],
];

/** Days after sign-up before a set-up business with nothing added becomes a lead. */
const TRIAL_IDLE_DAYS = 2;

/** SQL counts for one business (alias b): what it has added. */
const TRIAL_USE_SQL = "
    (SELECT COUNT(*) FROM products p WHERE p.business_id = b.id) AS products,
    (SELECT COUNT(*) FROM customers cu WHERE cu.business_id = b.id AND cu.is_walk_in = 0) AS customers,
    (SELECT COUNT(*) FROM orders o WHERE o.business_id = b.id) + (SELECT COUNT(*) FROM invoices i WHERE i.business_id = b.id)
      + (SELECT COUNT(*) FROM receipts r WHERE r.business_id = b.id) + (SELECT COUNT(*) FROM bookings bk WHERE bk.business_id = b.id) AS sales";

/** Where a trial stands: a key of TRIAL_STAGES. */
function trial_stage(array $b): string
{
    if (!(int)$b['onboarding_complete']) return 'setup';
    if ((int)$b['products'] > 0 && (int)$b['sales'] > 0) return 'using';
    if ((int)$b['products'] + (int)$b['customers'] + (int)$b['sales'] === 0) return 'idle';
    return 'started';
}

/**
 * Every business on its free trial, by stage: stage => rows, the trial
 * ending soonest first. Each row has what the board shows.
 */
function ops_trials(PDO $db): array
{
    $rows = $db->query("
        SELECT b.id, b.name, b.created_at, b.onboarding_complete, b.onboarding_step, b.storefront_published IS NOT NULL AS shop_online,
               s.trial_ends_at, c.flag_emoji, c.name AS country,
               DATEDIFF(CURDATE(), DATE(b.created_at)) AS day, DATEDIFF(s.trial_ends_at, CURDATE()) AS days_left,
               (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' ORDER BY u.id LIMIT 1) AS owner,
               (SELECT MAX(u.last_login_at) FROM users u WHERE u.business_id = b.id) AS last_login,
               (SELECT MAX(n.created_at) FROM notify_outbox n WHERE n.business_id = b.id AND n.channel = 'email'
                  AND (n.event = 'ops.client_email' OR n.event LIKE 'onboarding.%')) AS last_emailed,
               (SELECT l.id FROM leads l WHERE l.business_id = b.id LIMIT 1) AS lead_id,
               " . TRIAL_USE_SQL . "
        FROM businesses b
        JOIN subscriptions s ON s.business_id = b.id
        LEFT JOIN countries c ON c.id = b.country_id
        WHERE b.is_active = 1 AND " . ops_plan_sql('trial') . "
        ORDER BY s.trial_ends_at IS NULL, s.trial_ends_at, b.created_at")->fetchAll();
    $board = array_fill_keys(array_keys(TRIAL_STAGES), []);
    foreach ($rows as $r) {
        $board[trial_stage($r)][] = $r;
    }
    return $board;
}

/**
 * Trials set up at least TRIAL_IDLE_DAYS ago with nothing added (no product,
 * customer or sale) and no lead yet: each becomes a New lead for Sales, with
 * a follow-up now. Returns how many were made. Only the last 30 days: an
 * older business is past its trial.
 */
function ops_trial_idle_leads(PDO $db): int
{
    $rows = $db->query("
        SELECT b.id, b.name, b.email, b.phone, u.full_name, u.email AS owner_email, u.phone AS owner_phone,
               DATEDIFF(CURDATE(), DATE(b.created_at)) AS day
        FROM businesses b
        JOIN subscriptions s ON s.business_id = b.id AND s.status = 'trial'
        JOIN users u ON u.id = (SELECT MIN(o.id) FROM users o WHERE o.business_id = b.id AND o.role = 'owner' AND o.is_active = 1)
        WHERE b.is_active = 1 AND b.onboarding_complete = 1
          AND b.created_at < NOW() - INTERVAL " . TRIAL_IDLE_DAYS . " DAY AND b.created_at > NOW() - INTERVAL 30 DAY
          AND NOT EXISTS (SELECT 1 FROM leads l WHERE l.business_id = b.id)
          AND NOT EXISTS (SELECT 1 FROM products p WHERE p.business_id = b.id)
          AND NOT EXISTS (SELECT 1 FROM customers cu WHERE cu.business_id = b.id AND cu.is_walk_in = 0)
          AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.business_id = b.id)
          AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.business_id = b.id)
          AND NOT EXISTS (SELECT 1 FROM receipts r WHERE r.business_id = b.id)
          AND NOT EXISTS (SELECT 1 FROM bookings bk WHERE bk.business_id = b.id)
        LIMIT 50")->fetchAll();
    $made = 0;
    foreach ($rows as $b) {
        $made += ops_lead_for_business($db, $b, 'idle', 'new',
            'Set up Nfelia ' . (int)$b['day'] . ' days ago but has added nothing yet: no products, customers or sales.');
    }
    return $made;
}
