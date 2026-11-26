<?php
/**
 * Clients: every business on Nfelia, and each one's whole picture.
 *
 *   GET  clients                  the list (?q, ?plan, ?country, ?sort, ?page)                 clients
 *   GET  clients/{id}             one client: plan, payments, people, use, shop, errors, tickets, notes
 *   POST clients/{id}/plan        activate a month, extend the trial, suspend                     clients.act
 *   POST clients/{id}/credit      "Shop by Nfelia" on the shop's footer, or not                   clients.act
 *   POST clients/{id}/ai          adding items from a photo: on or off, and photos a week         clients.ai
 *   POST clients/{id}/notes       a note for the team                                             clients
 *   POST clients/{id}/view-as     open their Nfelia read-only, with a reason (they are told)      clients.view_as
 */
class OpsClientsController
{
    private const PAGE = 30;

    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $q       = trim((string)($_GET['q'] ?? ''));
        $plan    = (string)($_GET['plan'] ?? '');
        $country = (int)($_GET['country'] ?? 0);
        $sort    = in_array($_GET['sort'] ?? '', ['new', 'active', 'name'], true) ? $_GET['sort'] : 'new';
        $page    = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1 = 1'];
        $args  = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(b.name LIKE ? OR b.email LIKE ? OR b.phone LIKE ? OR b.subdomain LIKE ? OR b.id = ?
                         OR EXISTS (SELECT 1 FROM users u WHERE u.business_id = b.id AND (u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)))';
            array_push($args, $like, $like, $like, $like, ctype_digit($q) ? (int)$q : 0, $like, $like, $like);
        }
        // Where each stands is worked out from the dates, as billing_access() does
        if ($plan === 'attention') {
            $where[] = "(" . ops_plan_sql('due') . " OR " . ops_plan_sql('paused') . " OR " . ops_plan_sql('checking') . "
                         OR EXISTS (SELECT 1 FROM subscription_payments sp WHERE sp.business_id = b.id AND sp.status = 'pending_verification')
                         OR (s.status = 'trial' AND s.trial_ends_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY))";
        } elseif (isset(OPS_PLAN_STATES[$plan])) {
            $where[] = ops_plan_sql($plan);
        }
        if ($country) {
            $where[] = 'b.country_id = ?';
            $args[] = $country;
        }
        $from = "FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id LEFT JOIN countries c ON c.id = b.country_id
                 WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stmt->execute($args);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PAGE));
        $page  = min($page, $pages);
        $order = match ($sort) { 'active' => 'last_login IS NULL, last_login DESC', 'name' => 'b.name', default => 'b.created_at DESC, b.id DESC' };

        $stmt = $this->db->prepare("
            SELECT b.id, b.name, b.email, b.phone, b.created_at, b.subdomain, b.subdomain_status, b.is_active,
                   s.status AS plan_status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.expiry_exempt, s.payment_method,
                   c.flag_emoji, c.name AS country,
                   (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' ORDER BY u.id LIMIT 1) AS owner,
                   (SELECT COUNT(*) FROM users u WHERE u.business_id = b.id AND u.is_active = 1) AS team,
                   (SELECT MAX(u.last_login_at) FROM users u WHERE u.business_id = b.id) AS last_login
            {$from} ORDER BY {$order} LIMIT " . self::PAGE . " OFFSET " . (($page - 1) * self::PAGE));
        $stmt->execute($args);

        ops_render('clients/index', [
            'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
            'filter' => compact('q', 'plan', 'country', 'sort'), 'plans' => OPS_PLAN_STATES,
            'countries' => $this->db->query("SELECT id, name, flag_emoji FROM countries ORDER BY name")->fetchAll(),
        ], 'Clients');
    }

    public function show(string $id): void
    {
        $b  = $this->client((int)$id);
        $db = $this->db;
        $one = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetch() ?: []; };
        $all = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(); };

        $people = $all("SELECT id, full_name, email, phone, role, is_active, last_login_at, created_at FROM users WHERE business_id = ?
                        ORDER BY is_active DESC, FIELD(role, 'owner', 'admin', 'manager', 'staff'), full_name", [$b['id']]);
        $use = $one("
            SELECT (SELECT COUNT(*) FROM customers WHERE business_id = ?) AS customers,
                   (SELECT COUNT(*) FROM products WHERE business_id = ? AND is_active = 1) AS products,
                   (SELECT COUNT(*) FROM invoices WHERE business_id = ?) AS invoices,
                   (SELECT COUNT(*) FROM invoices WHERE business_id = ? AND created_at > NOW() - INTERVAL 30 DAY) AS invoices_30,
                   (SELECT COUNT(*) FROM receipts WHERE business_id = ? AND created_at > NOW() - INTERVAL 30 DAY) AS receipts_30,
                   (SELECT COUNT(*) FROM quotations WHERE business_id = ? AND created_at > NOW() - INTERVAL 30 DAY) AS quotes_30,
                   (SELECT COUNT(*) FROM orders WHERE business_id = ?) AS orders,
                   (SELECT COUNT(*) FROM orders WHERE business_id = ? AND created_at > NOW() - INTERVAL 30 DAY) AS orders_30,
                   (SELECT COUNT(*) FROM tasks WHERE business_id = ? AND created_at > NOW() - INTERVAL 30 DAY) AS tasks_30
        ", array_fill(0, 9, $b['id']));
        $payments = $all("
            SELECT 'card' AS how, event_type AS what, amount / 100 AS amount, status, paystack_reference AS ref, created_at FROM subscription_history WHERE business_id = ?
            UNION ALL
            SELECT 'momo', CONCAT('Mobile money (', COALESCE(momo_provider_label, 'MoMo'), ')'), amount, status, reference_number, submitted_at FROM subscription_payments WHERE business_id = ?
            ORDER BY created_at DESC LIMIT 20", [$b['id'], $b['id']]);
        $plan_log = $all("SELECT old_status, new_status, notes, changed_at FROM subscription_status_log WHERE business_id = ? ORDER BY id DESC LIMIT 10", [$b['id']]);
        $reviews  = $one("SELECT COUNT(*) AS n, ROUND(AVG(rating), 1) AS avg FROM reviews WHERE business_id = ? AND status = 'published'", [$b['id']]);
        $referred_by = $one("SELECT r.*, rb.name AS referrer FROM referrals r LEFT JOIN businesses rb ON rb.id = r.referrer_business_id WHERE r.referred_business_id = ? LIMIT 1", [$b['id']]);
        $referred = $all("SELECT r.status, r.created_at, rb.name FROM referrals r LEFT JOIN businesses rb ON rb.id = r.referred_business_id WHERE r.referrer_business_id = ? ORDER BY r.id DESC LIMIT 10", [$b['id']]);

        $errors = ops_can('errors') ? $all("
            SELECT g.id, g.title, g.level, g.kind, g.status, COUNT(e.id) AS n, MAX(e.created_at) AS last_at
            FROM error_events e JOIN error_groups g ON g.id = e.group_id
            WHERE e.business_id = ? AND e.created_at > NOW() - INTERVAL 30 DAY
            GROUP BY g.id ORDER BY last_at DESC LIMIT 10", [$b['id']]) : null;
        $tickets = ops_can('support') ? $all("SELECT id, subject, status, created_at FROM support_tickets WHERE business_id = ? ORDER BY id DESC LIMIT 10", [$b['id']]) : null;
        $notes   = $all("SELECT n.*, s.full_name FROM ops_notes n LEFT JOIN ops_staff s ON s.id = n.staff_id WHERE n.subject_type = 'business' AND n.subject_id = ? ORDER BY n.id DESC LIMIT 30", [$b['id']]);
        $views   = $all("SELECT v.*, s.full_name FROM ops_view_as v LEFT JOIN ops_staff s ON s.id = v.staff_id WHERE v.business_id = ? AND v.started_at IS NOT NULL ORDER BY v.id DESC LIMIT 10", [$b['id']]);
        $history = ops_can('audit') ? $all("SELECT a.*, s.full_name, NULL AS business_name FROM ops_audit a LEFT JOIN ops_staff s ON s.id = a.staff_id WHERE a.business_id = ? ORDER BY a.id DESC LIMIT 15", [$b['id']]) : null;
        $books   = ops_can('exports') ? $all("SELECT id, status, charge, created_at FROM records_books WHERE business_id = ? AND kind = 'book' AND status <> 'cancelled' ORDER BY id DESC LIMIT 5", [$b['id']]) : null;
        $asks    = ops_can('requests') ? $all("SELECT id, name, organisation, label, status, created_at FROM record_requests WHERE business_id = ? AND status <> 'unconfirmed' ORDER BY id DESC LIMIT 5", [$b['id']]) : null;
        $ai      = product_ai_state($db, (int)$b['id']) + ['month' => product_ai_month($db, (int)$b['id']), 'key' => product_ai_key() !== ''];

        ops_render('clients/show', compact('b', 'people', 'use', 'payments', 'plan_log', 'reviews', 'referred_by', 'referred', 'errors', 'tickets', 'notes', 'views', 'history', 'books', 'asks', 'ai')
                   + ['plans' => OPS_PLAN_STATES], $b['name']);
    }

    public function plan(string $id): void
    {
        $this->need('clients.act');
        $b = $this->client((int)$id);
        $action = (string)($_POST['action'] ?? '');
        $before = $b['plan_status'] ?? 'none';
        $field  = fn(string $f) => substr((string)$this->db->query("SELECT {$f} FROM subscriptions WHERE business_id = " . (int)$b['id'])->fetchColumn(), 0, 10) ?: null;

        switch ($action) {
            case 'activate':
                // A month on the house (or paid some other way): it starts
                // when the current paid month ends, like a payment
                $stmt = $this->db->prepare("SELECT monthly_amount, extra_seats FROM subscriptions WHERE business_id = ?");
                $stmt->execute([$b['id']]);
                $sub = $stmt->fetch() ?: ['monthly_amount' => 0, 'extra_seats' => 0];
                $prices = billing_prices($this->db, (int)$b['id']);
                $amount = (float)$sub['monthly_amount'] > 0 ? (float)$sub['monthly_amount'] : $prices['monthly'] + (int)$sub['extra_seats'] * $prices['seat'];
                $this->db->beginTransaction();
                try {
                    [, $end] = billing_start_month($this->db, (int)$b['id'], $amount, (int)$sub['extra_seats'], null, 'manual', 'OPS_' . time());
                    $this->db->commit();
                } catch (Throwable $e) {
                    $this->db->rollBack();
                    throw $e;
                }
                notify('billing.activated', (int)$b['id'], ['period_end' => $end], ['dedupe' => 'activated:' . $end]);
                $said = 'Activated for a month, to ' . date('j M Y', strtotime((string)$end)) . '.';
                break;
            case 'extend_trial':
                $days = max(1, min(30, (int)($_POST['days'] ?? 7)));
                $this->db->prepare("UPDATE subscriptions SET status = 'trial', trial_ends_at = DATE_ADD(GREATEST(COALESCE(trial_ends_at, CURDATE()), CURDATE()), INTERVAL ? DAY)
                                    WHERE business_id = ?")->execute([$days, $b['id']]);
                $ends = $field('trial_ends_at');
                notify('billing.trial_extended', (int)$b['id'], ['days' => $days, 'trial_ends' => $ends], ['dedupe' => 'trial:' . $ends]);
                $said = 'Trial extended by ' . $days . ' day' . ($days === 1 ? '' : 's') . ', to ' . date('j M Y', strtotime((string)$ends)) . '.';
                break;
            case 'suspend':
                $reason = trim((string)($_POST['reason'] ?? ''));
                if (mb_strlen($reason) < 5) {
                    ops_flash('error', 'Say why it is being suspended (the team sees this in the audit log).');
                    ops_redirect('clients/' . $b['id'] . '#plan');
                }
                require_once BASE_PATH . '/app/controllers/PaystackController.php';
                PaystackController::stop_card_renewals($this->db, (int)$b['id']);   // an old card plan would charge them anyway
                $this->db->prepare("UPDATE subscriptions SET status = 'suspended' WHERE business_id = ?")->execute([$b['id']]);
                notify('billing.suspended', (int)$b['id'], [], ['dedupe' => 'suspended:' . date('Y-m-d H')]);
                $said = 'Suspended: ' . $reason;
                break;
            default:
                ops_redirect('clients/' . $b['id']);
        }
        $this->db->prepare("INSERT INTO subscription_status_log (business_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, NULL, ?)")
           ->execute([$b['id'], $before, $this->db->query("SELECT status FROM subscriptions WHERE business_id = " . (int)$b['id'])->fetchColumn() ?: null,
                      'Nfelia Ops, ' . $this->me['full_name'] . ': ' . $said]);
        storefront_purge((int)$b['id']);   // whether the shop is open follows the plan
        ops_audit('client.plan', $b['name'] . ': ' . $said, ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id'], 'data' => ['action' => $action, 'from' => $before]]);
        ops_flash('success', $said . ' ' . $b['name'] . ' has been told.');
        ops_redirect('clients/' . $b['id'] . '#plan');
    }

    public function credit(string $id): void
    {
        $this->need('clients.act');
        $b = $this->client((int)$id);
        $hide = ($_POST['hide'] ?? '') === '1' ? 1 : 0;
        $this->db->prepare("UPDATE businesses SET hide_nfelia_credit = ? WHERE id = ?")->execute([$hide, $b['id']]);
        storefront_purge((int)$b['id']);
        $said = $hide ? 'Took "Shop by Nfelia" off the shop' : 'Put "Shop by Nfelia" back on the shop';
        ops_audit('client.credit', $b['name'] . ': ' . $said, ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id']]);
        ops_flash('success', $said . '.');
        ops_redirect('clients/' . $b['id'] . '#shop');
    }

    /**
     * Adding items from a photo (app/helpers/product_ai.php): on or off, and
     * how many photos a week. Each photo costs Nfelia money, so Admins only.
     * The owner is told when it comes on.
     */
    public function ai(string $id): void
    {
        $this->need('clients.ai');
        $b     = $this->client((int)$id);
        $on    = ($_POST['on'] ?? '') === '1' ? 1 : 0;
        $limit = (int)($_POST['limit'] ?? $b['ai_weekly_limit']);
        if ($limit < 1 || $limit > 1000) {
            ops_flash('error', 'Photos a week: a number from 1 to 1000.');
            ops_redirect('clients/' . $b['id'] . '#ai');
        }
        $this->db->prepare("UPDATE businesses SET ai_enabled = ?, ai_weekly_limit = ? WHERE id = ?")->execute([$on, $limit, $b['id']]);

        $was  = (int)$b['ai_enabled'];
        $said = match (true) {
            $on && !$was => 'Switched AI on, with ' . $limit . ' photos a week',
            !$on && $was => 'Switched AI off',
            $on          => 'AI stays on, now with ' . $limit . ' photos a week',
            default      => 'AI stays off; ' . $limit . ' photos a week when it is on',
        };
        if ($on && !$was) {
            notify('account.ai_on', (int)$b['id'], ['limit' => $limit], ['dedupe' => 'ai_on:' . date('Y-m-d')]);
        }
        ops_audit('client.ai', $b['name'] . ': ' . $said, ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id'],
                                                         'data' => ['on' => $on, 'limit' => $limit, 'was' => ['on' => $was, 'limit' => (int)$b['ai_weekly_limit']]]]);
        ops_flash('success', $said . '.' . ($on && !$was ? ' ' . $b['name'] . ' has been told.' : ''));
        ops_redirect('clients/' . $b['id'] . '#ai');
    }

    public function note(string $id): void
    {
        $b = $this->client((int)$id);
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') {
            $this->db->prepare("INSERT INTO ops_notes (subject_type, subject_id, staff_id, body) VALUES ('business', ?, ?, ?)")->execute([$b['id'], $this->me['id'], mb_substr($body, 0, 4000)]);
            ops_audit('client.note', 'Wrote a note on ' . $b['name'], ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id']]);
        }
        ops_redirect('clients/' . $b['id'] . '#notes');
    }

    /**
     * Open the client's Nfelia as its owner sees it: read-only, for 30
     * minutes. The reason is kept, the owner is told (by email too), and the
     * link that starts it works once, for 2 minutes, in this browser.
     */
    public function view_as(string $id): void
    {
        $this->need('clients.view_as');
        $b = $this->client((int)$id);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            ops_flash('error', 'Say why you need to see their account. They are told, and it stays in the audit log.');
            ops_redirect('clients/' . $b['id'] . '#view-as');
        }
        $stmt = $this->db->prepare("SELECT id, full_name FROM users WHERE business_id = ? AND is_active = 1 ORDER BY FIELD(role, 'owner', 'admin', 'manager', 'staff'), id LIMIT 1");
        $stmt->execute([$b['id']]);
        $owner = $stmt->fetch();
        if (!$owner) {
            ops_flash('error', $b['name'] . ' has nobody active to view as.');
            ops_redirect('clients/' . $b['id']);
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare("INSERT INTO ops_view_as (staff_id, business_id, user_id, reason, token_hash) VALUES (?, ?, ?, ?, ?)")
           ->execute([$this->me['id'], $b['id'], $owner['id'], mb_substr($reason, 0, 255), hash('sha256', $token)]);
        ops_audit('client.view_as', 'Opened ' . $b['name'] . ' as ' . $owner['full_name'] . ' (read-only): ' . $reason,
                  ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id'], 'data' => ['reason' => $reason]]);
        ops_redirect(APP_URL . '/support-view/' . $token);
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function client(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, s.status AS plan_status, s.plan, s.trial_ends_at, s.current_period_start, s.current_period_end, s.grace_ends_at, s.expiry_exempt,
                   s.monthly_amount, s.extra_seats, s.user_seats, s.payment_method, s.cancelled_at, s.cancel_reason, s.paystack_subscription_code,
                   c.name AS country, c.flag_emoji, c.currency_symbol AS country_currency
            FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id LEFT JOIN countries c ON c.id = b.country_id
            WHERE b.id = ?");
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        if (!$b) {
            ops_not_found();
        }
        return $b;
    }

    private function need(string $permission): void
    {
        if (!ops_can($permission)) {
            ops_forbidden();
        }
    }
}
