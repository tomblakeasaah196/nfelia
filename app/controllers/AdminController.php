<?php
/**
 * Nfelia — Founder Admin Panel Controller
 * Restricted to business_id = 1 (founder account).
 * Route prefix: /admin/*
 *
 * Tabs: Dashboard, Businesses, Feedback, Referrals, Support, Shop names, Financials
 */
class AdminController {

    private $db;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
        $this->guard();
    }

    private function guard(): void {
        if (!is_platform_admin()) {
            flash('error', 'Access denied.'); // NFELIA AUDIT FIX L04 2026-06-03
            redirect('dashboard');
            exit;
        }
    }

    /* ══════════════════════════════════════════
     *  DASHBOARD — Overview of everything
     * ══════════════════════════════════════════ */
    public function dashboard() {
        // Key metrics
        $metrics = [];

        // Total businesses
        $metrics['total_businesses'] = (int)$this->db->query("SELECT COUNT(*) FROM businesses")->fetchColumn();

        // By subscription status
        $stmt = $this->db->query("
            SELECT status, COUNT(*) AS cnt FROM subscriptions GROUP BY status
        ");
        $status_counts = [];
        while ($row = $stmt->fetch()) { $status_counts[$row['status']] = (int)$row['cnt']; }
        $metrics['status_counts'] = $status_counts;

        // Active paying
        $metrics['paying'] = ($status_counts['active'] ?? 0);
        $metrics['trialing'] = ($status_counts['trial'] ?? 0);
        $metrics['suspended'] = ($status_counts['suspended'] ?? 0) + ($status_counts['cancelled'] ?? 0);

        // MRR (Monthly Recurring Revenue), in naira: monthly_amount is what the
        // last month cost (seats included), in the business's own currency,
        // so businesses paying in another currency are left out
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(s.monthly_amount), 0) AS mrr
            FROM subscriptions s
            JOIN businesses b ON b.id = s.business_id
            LEFT JOIN countries c ON c.id = b.country_id
            WHERE s.status = 'active' AND COALESCE(c.currency_code, 'NGN') = 'NGN'
        ");
        $metrics['mrr'] = (float)($stmt->fetchColumn() ?: 0);

        // Revenue this month
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM subscription_history
            WHERE event_type = 'charge.success' AND status = 'success'
            AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ");
        $stmt->execute();
        $metrics['revenue_this_month'] = (int)$stmt->fetchColumn() / 100;

        // Total revenue all-time
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) FROM subscription_history
            WHERE event_type = 'charge.success' AND status = 'success'
        ");
        $metrics['total_revenue'] = (int)$stmt->fetchColumn() / 100;

        // Referral stats
        $stmt = $this->db->query("SELECT COUNT(*) FROM referrals WHERE status = 'rewarded'");
        $metrics['total_referrals_rewarded'] = (int)$stmt->fetchColumn();

        // Feedback average
        $stmt = $this->db->query("SELECT AVG(rating) FROM feedback");
        $metrics['avg_rating'] = round((float)$stmt->fetchColumn(), 1);
        $stmt = $this->db->query("SELECT COUNT(*) FROM feedback");
        $metrics['total_feedback'] = (int)$stmt->fetchColumn();

        // Signups last 30 days
        $stmt = $this->db->query("SELECT COUNT(*) FROM businesses WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $metrics['signups_30d'] = (int)$stmt->fetchColumn();

        // Recent signups (last 10)
        $stmt = $this->db->query("
            SELECT b.id, b.name, b.created_at, s.status AS sub_status, s.trial_ends_at
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id
            ORDER BY b.created_at DESC LIMIT 10
        ");
        $recent_signups = $stmt->fetchAll();

        // NFELIA AUDIT FIX L05 — Single aggregated query instead of 6 separate queries — 2026-06-03
        $revenue_chart = [];
        // Pre-build 6-month labels for ordering
        $chart_months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-{$i} months");
            $chart_months[date('Y-m', $ts)] = ['month' => date('M Y', $ts), 'revenue' => 0];
        }
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
            FROM subscription_history
            WHERE event_type = 'charge.success' AND status = 'success'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY ym
        ");
        foreach ($stmt->fetchAll() as $row) {
            if (isset($chart_months[$row['ym']])) {
                $chart_months[$row['ym']]['revenue'] = (float)$row['total'] / 100;   // kobo to naira
            }
        }
        $revenue_chart = array_values($chart_months);

        $tab       = 'dashboard';
        $title     = 'Admin Panel';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  BUSINESSES — List all tenants
     * ══════════════════════════════════════════ */
    public function businesses() {
        $search = trim($_GET['q'] ?? '');
        $filter = $_GET['status'] ?? 'all';

        $sql = "
            SELECT b.*, s.status AS sub_status, s.plan, s.trial_ends_at,
                   s.current_period_end, s.user_seats, s.extra_seats,
                   (SELECT COUNT(*) FROM users WHERE business_id = b.id AND is_active = 1) AS user_count,
                   (SELECT MAX(last_login_at) FROM users WHERE business_id = b.id AND is_active = 1) AS last_login
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $sql .= " AND (b.name LIKE ? OR b.email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($filter !== 'all') {
            $sql .= " AND s.status = ?";
            $params[] = $filter;
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $businesses = $stmt->fetchAll();

        $tab       = 'businesses';
        $title     = 'Admin — Businesses';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  MANAGE SUBSCRIPTION — POST (AJAX)
     * ══════════════════════════════════════════ */
    public function manage_subscription() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();

        $biz_id = (int)$_POST['business_id'];
        $action = $_POST['action'] ?? '';

        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$biz_id]);
        $sub = $stmt->fetch() ?: null;
        if (!$sub) { json_response(['error' => 'That business has no subscription.'], 404); return; }

        switch ($action) {
            case 'activate':
                // A month on the house (or paid some other way): it starts
                // when the current paid month ends, like a payment
                $this->db->beginTransaction();
                [, $end] = billing_start_month($this->db, $biz_id, (float)$sub['monthly_amount'], (int)$sub['extra_seats'], null, 'manual', 'ADMIN_' . time());
                $this->db->commit();
                $note = 'Activated by the Nfelia team until ' . $end;
                $this->admin_notify($biz_id, 'subscription_activated', 'Your subscription has been activated by the Nfelia team until ' . date('j M Y', strtotime($end)) . '.');
                break;

            case 'suspend':
                require_once __DIR__ . '/PaystackController.php';
                PaystackController::stop_card_renewals($this->db, $biz_id);
                $this->db->prepare("UPDATE subscriptions SET status = 'suspended' WHERE business_id = ?")->execute([$biz_id]);
                $note = 'Suspended by the Nfelia team';
                $this->admin_notify($biz_id, 'subscription_suspended', 'Your account has been suspended. Contact support for assistance.');
                break;

            case 'extend_trial':
                // From today when the trial has already ended
                $days = max(1, min(30, (int)($_POST['days'] ?? 7)));
                $this->db->prepare("
                    UPDATE subscriptions SET status = 'trial', trial_ends_at = DATE_ADD(GREATEST(COALESCE(trial_ends_at, CURDATE()), CURDATE()), INTERVAL ? DAY)
                    WHERE business_id = ?
                ")->execute([$days, $biz_id]);
                $note = "Trial extended by {$days} days";
                $this->admin_notify($biz_id, 'trial_expiring_soon', "Your trial has been extended by {$days} days. Enjoy!");
                break;

            default:
                json_response(['error' => 'Invalid action'], 400);
                return;
        }

        // Every admin change is on record: who, when, from what
        $stmt = $this->db->prepare("SELECT status FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$biz_id]);
        $this->db->prepare("
            INSERT INTO subscription_status_log (business_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)
        ")->execute([$biz_id, $sub['status'], (string)$stmt->fetchColumn(), (int)auth_user()['id'], $note]);

        // Whether the shop is open follows the subscription
        storefront_purge($biz_id);
        json_response(['success' => true, 'message' => ucfirst($action) . ' completed.']);
    }

    /* ══════════════════════════════════════════
     *  "SHOP BY NFELIA" ADD-ON — POST
     *  The paid add-on takes the line off a shop's footer.
     * ══════════════════════════════════════════ */
    public function shop_credit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/businesses'); }
        csrf_verify();

        $biz_id = (int)($_POST['business_id'] ?? 0);
        $hide   = ($_POST['hide'] ?? '') === '1' ? 1 : 0;
        $this->db->prepare("UPDATE businesses SET hide_nfelia_credit = ? WHERE id = ?")->execute([$hide, $biz_id]);
        storefront_purge($biz_id);

        flash('success', $hide ? 'That shop no longer shows "Shop by Nfelia".' : 'That shop shows "Shop by Nfelia" again.');
        redirect('admin/businesses');
    }

    /* ══════════════════════════════════════════
     *  FEEDBACK INBOX
     * ══════════════════════════════════════════ */
    public function feedback() {
        $stmt = $this->db->query("
            SELECT f.*, b.name AS business_name, u.full_name AS user_name
            FROM feedback f
            LEFT JOIN businesses b ON b.id = f.business_id
            LEFT JOIN users u ON u.id = f.user_id
            ORDER BY f.created_at DESC LIMIT 100
        ");
        $feedbacks = $stmt->fetchAll();

        // Rating distribution
        $stmt = $this->db->query("
            SELECT rating, COUNT(*) AS cnt FROM feedback GROUP BY rating ORDER BY rating DESC
        ");
        $rating_dist = [];
        $total_fb = 0;
        while ($row = $stmt->fetch()) { $rating_dist[(int)$row['rating']] = (int)$row['cnt']; $total_fb += (int)$row['cnt']; }

        // Average
        $avg_rating = $total_fb > 0 ? array_sum(array_map(fn($r, $c) => $r * $c, array_keys($rating_dist), array_values($rating_dist))) / $total_fb : 0;

        $tab       = 'feedback';
        $title     = 'Admin — Feedback';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  REFERRALS OVERVIEW
     * ══════════════════════════════════════════ */
    public function referrals() {
        $stmt = $this->db->query("
            SELECT r.*, b1.name AS referrer_name, b2.name AS referred_name
            FROM referrals r
            LEFT JOIN businesses b1 ON b1.id = r.referrer_business_id
            LEFT JOIN businesses b2 ON b2.id = r.referred_business_id
            ORDER BY r.created_at DESC LIMIT 100
        ");
        $referrals = $stmt->fetchAll();

        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'converted') AS converted,
                SUM(status = 'rewarded') AS rewarded,
                SUM(CASE WHEN status = 'rewarded' THEN discount_amount ELSE 0 END) AS total_discount_kobo
            FROM referrals
        ");
        $ref_stats = $stmt->fetch();

        $tab       = 'referrals';
        $title     = 'Admin — Referrals';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  FINANCIALS — Revenue, Expenses, Model
     * ══════════════════════════════════════════ */
    public function financials() {
        $sub_tab = $_GET['sub'] ?? 'overview';

        // Revenue summary
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN amount ELSE 0 END), 0) AS this_month,
                COALESCE(SUM(amount), 0) AS all_time
            FROM subscription_history
            WHERE event_type = 'charge.success' AND status = 'success'
        ");
        $revenue = $stmt->fetch();
        $revenue['this_month'] = (int)$revenue['this_month'] / 100;
        $revenue['all_time']   = (int)$revenue['all_time'] / 100;

        // NFELIA AUDIT FIX M01 (financials tab) — monthly_amount stored in NGN, not kobo — 2026-06-03
        $stmt = $this->db->query("SELECT COALESCE(SUM(monthly_amount + (extra_seats * 5000)), 0) FROM subscriptions WHERE status = 'active'");
        $revenue['mrr'] = (float)$stmt->fetchColumn();

        // Expenses
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(NOW()) AND YEAR(expense_date) = YEAR(NOW()) THEN amount ELSE 0 END), 0) AS this_month,
                COALESCE(SUM(amount), 0) AS all_time,
                COALESCE(SUM(CASE WHEN recurring = 1 THEN amount ELSE 0 END), 0) AS monthly_recurring
            FROM admin_expenses
        ");
        $expenses = $stmt->fetch();
        $expenses['this_month']       = (int)$expenses['this_month'] / 100;
        $expenses['all_time']         = (int)$expenses['all_time'] / 100;
        $expenses['monthly_recurring'] = (int)$expenses['monthly_recurring'] / 100;

        // Expense list (current month)
        $stmt = $this->db->prepare("
            SELECT * FROM admin_expenses
            WHERE MONTH(expense_date) = MONTH(NOW()) AND YEAR(expense_date) = YEAR(NOW())
            ORDER BY expense_date DESC
        ");
        $stmt->execute();
        $expense_list = $stmt->fetchAll();

        // Expense categories this month
        $stmt = $this->db->query("
            SELECT category, SUM(amount) AS total FROM admin_expenses
            WHERE MONTH(expense_date) = MONTH(NOW()) AND YEAR(expense_date) = YEAR(NOW())
            GROUP BY category ORDER BY total DESC
        ");
        $expense_categories = $stmt->fetchAll();

        // Financial model inputs
        $active_subs = (int)$this->db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn();
        $trial_subs  = (int)$this->db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'trial'")->fetchColumn();

        $model = [
            'active_subs'       => $active_subs,
            'trial_subs'        => $trial_subs,
            'price_per_sub'     => 10000, // ₦10,000
            'monthly_expenses'  => $expenses['monthly_recurring'],
            'mrr'               => $revenue['mrr'],
            'margin'            => $revenue['mrr'] > 0 ? round(($revenue['mrr'] - $expenses['monthly_recurring']) / $revenue['mrr'] * 100, 1) : 0,
            'breakeven_users'   => $expenses['monthly_recurring'] > 0 ? ceil($expenses['monthly_recurring'] / 10000) : 0,
            'profit'            => $revenue['mrr'] - $expenses['monthly_recurring'],
        ];

        $tab       = 'financials';
        $title     = 'Admin — Financials';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  ADD EXPENSE — POST
     * ══════════════════════════════════════════ */
    public function add_expense() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/financials'); return; }
        csrf_verify();

        $category    = trim($_POST['category'] ?? 'other');
        $description = trim($_POST['description'] ?? '');
        $amount      = (int)(floatval($_POST['amount'] ?? 0) * 100); // NGN to kobo
        $date        = $_POST['expense_date'] ?? date('Y-m-d');
        $recurring   = isset($_POST['recurring']) ? 1 : 0;

        $allowed_cats = ['server', 'marketing', 'salary', 'tools', 'domain', 'design', 'legal', 'other'];
        if (!in_array($category, $allowed_cats)) $category = 'other';

        if ($amount <= 0) {
            flash('error', 'Amount must be greater than zero.'); // NFELIA AUDIT FIX L04 2026-06-03
            redirect('admin/financials?sub=expenses');
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO admin_expenses (category, description, amount, expense_date, recurring, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$category, $description ?: null, $amount, $date, $recurring]);

        flash('success', 'Expense added.'); // NFELIA AUDIT FIX L04 2026-06-03
        redirect('admin/financials?sub=expenses');
    }

    /* ══════════════════════════════════════════
     *  DELETE EXPENSE — POST
     * ══════════════════════════════════════════ */
    public function delete_expense() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/financials'); return; }
        csrf_verify();

        $id = (int)$_POST['expense_id'];
        $this->db->prepare("DELETE FROM admin_expenses WHERE id = ?")->execute([$id]);

        flash('success', 'Expense removed.'); // NFELIA AUDIT FIX L04 2026-06-03
        redirect('admin/financials?sub=expenses');
    }

    /* ══════════════════════════════════════════
     *  SUPPORT TICKETS INBOX
     * ══════════════════════════════════════════ */
    public function support() {
        $filter = $_GET['status'] ?? 'all';

        $sql = "
            SELECT st.*, b.name AS business_name, u.full_name AS user_name
            FROM support_tickets st
            LEFT JOIN businesses b ON b.id = st.business_id
            LEFT JOIN users u ON u.id = st.user_id
        ";
        $params = [];
        if ($filter !== 'all') {
            $sql .= " WHERE st.status = ?";
            $params[] = $filter;
        }
        $sql .= " ORDER BY FIELD(st.status, 'open', 'replied', 'closed'), st.created_at DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $support_tickets = $stmt->fetchAll();

        // Counts
        $stmt = $this->db->query("SELECT status, COUNT(*) AS cnt FROM support_tickets GROUP BY status");
        $ticket_counts = [];
        while ($row = $stmt->fetch()) { $ticket_counts[$row['status']] = (int)$row['cnt']; }

        $tab       = 'support';
        $title     = 'Admin — Support';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  REPLY TO TICKET — POST
     * ══════════════════════════════════════════ */
    public function reply_ticket() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/support'); return; }
        csrf_verify();

        $ticket_id = (int)$_POST['ticket_id'];
        $reply     = trim($_POST['reply'] ?? '');
        $status    = $_POST['new_status'] ?? 'replied';

        if (!$reply) {
            flash('error', 'Reply cannot be empty.'); // NFELIA AUDIT FIX L04 2026-06-03
            redirect('admin/support');
            return;
        }

        $this->db->prepare("
            UPDATE support_tickets SET admin_reply = ?, status = ?, replied_at = NOW() WHERE id = ?
        ")->execute([$reply, $status, $ticket_id]);

        // Notify the business
        $stmt = $this->db->prepare("SELECT business_id, subject FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        if ($ticket) {
            $this->admin_notify($ticket['business_id'], 'support_reply',
                'We replied to your support message: "' . htmlspecialchars(substr($ticket['subject'], 0, 50)) . '"');
        }

        flash('success', 'Reply sent.'); // NFELIA AUDIT FIX L04 2026-06-03
        redirect('admin/support');
    }

    /* ══════════════════════════════════════════
     *  SHOP NAMES — names waiting for approval
     *  Names that look like a bank, telco, government body or an official
     *  account wait here (see shop_name_needs_review in shop_links.php).
     * ══════════════════════════════════════════ */
    /**
     * Reviews shoppers posted on shops, newest first. The founder can hide
     * one that breaks the rules (abuse, someone's phone number, spam) and
     * show it again; nobody can edit what a shopper wrote.
     */
    public function reviews() {
        $filter = in_array($_GET['show'] ?? '', ['hidden', 'low'], true) ? $_GET['show'] : 'all';
        $where  = match ($filter) {
            'hidden' => "r.status = 'hidden'",
            'low'    => "r.status IN ('published', 'hidden') AND r.rating <= 2",
            default  => "r.status IN ('published', 'hidden')",
        };
        $stmt = $this->db->query("
            SELECT r.id, r.rating, r.comment, r.status, r.customer_name, r.submitted_at, r.hidden_at,
                   o.order_number, b.id AS business_id, b.name AS business_name, b.subdomain
            FROM reviews r
            JOIN orders o ON o.id = r.order_id
            JOIN businesses b ON b.id = r.business_id
            WHERE {$where}
            ORDER BY r.submitted_at DESC, r.id DESC
            LIMIT 200
        ");
        $reviews = $stmt->fetchAll();
        $review_filter = $filter;

        $tab       = 'reviews';
        $title     = 'Admin — Reviews';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    public function review_visibility() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/reviews'); }
        csrf_verify();

        $review_id = (int)($_POST['review_id'] ?? 0);
        $hide      = ($_POST['hide'] ?? '') === '1';
        $stmt = $this->db->prepare("SELECT business_id FROM reviews WHERE id = ? AND status IN ('published', 'hidden')");
        $stmt->execute([$review_id]);
        $biz_id = (int)$stmt->fetchColumn();
        if ($biz_id) {
            $this->db->prepare("UPDATE reviews SET status = ?, hidden_at = ? WHERE id = ?")
                     ->execute([$hide ? 'hidden' : 'published', $hide ? date('Y-m-d H:i:s') : null, $review_id]);
            storefront_purge($biz_id);
            flash('success', $hide ? 'That review is hidden from the shop.' : 'That review shows on the shop again.');
        }
        redirect('admin/reviews');
    }

    public function shop_names() {
        $stmt = $this->db->query("
            SELECT b.id, b.name, b.category, b.email, b.phone, b.created_at,
                   b.subdomain, b.subdomain_requested, b.subdomain_requested_at
            FROM businesses b
            WHERE b.subdomain_status = 'pending' AND b.subdomain_requested IS NOT NULL
            ORDER BY b.subdomain_requested_at ASC
        ");
        $pending_names = $stmt->fetchAll();

        $tab       = 'shop_names';
        $title     = 'Admin — Shop names';
        $view_file = BASE_PATH . '/views/admin/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  APPROVE OR TURN DOWN A SHOP NAME — POST
     * ══════════════════════════════════════════ */
    public function review_shop_name() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/shop-names'); }
        csrf_verify();

        $biz_id   = (int)($_POST['business_id'] ?? 0);
        $name     = (string)($_POST['name'] ?? '');
        $decision = $_POST['decision'] ?? '';

        $stmt = $this->db->prepare("SELECT id, name, subdomain, subdomain_requested, subdomain_status FROM businesses WHERE id = ?");
        $stmt->execute([$biz_id]);
        $biz = $stmt->fetch();

        // The owner may have picked another name since this page loaded
        if (!$biz || $biz['subdomain_status'] !== 'pending' || $biz['subdomain_requested'] !== $name
            || !in_array($decision, ['approve', 'reject'], true)) {
            flash('warning', 'That request changed since the page loaded. This is the current list.');
            redirect('admin/shop-names');
        }

        $host      = $name . '.' . shop_base_host();
        $is_rename = $biz['subdomain'] !== null;

        if ($decision === 'reject') {
            // A shop with a live link keeps it; one without goes back to setup
            $this->db->prepare("
                UPDATE businesses
                SET subdomain_requested = NULL, subdomain_status = ?, subdomain_requested_at = NULL,
                    subdomain_reviewed_at = NOW(), subdomain_reviewed_by = ?
                WHERE id = ? AND subdomain_status = 'pending' AND subdomain_requested = ?
            ")->execute([$is_rename ? 'active' : 'rejected', auth_user()['id'], $biz_id, $name]);

            if ($is_rename) {
                shop_notify($biz_id, 'Your shop link stays the same', "We couldn't approve {$host}. Your shop keeps its current link.", 'landing-page');
            } else {
                shop_notify($biz_id, 'Please choose another shop name', "We couldn't approve {$host}. Choose another name to open your shop.", 'shop-setup');
            }

            flash('success', $host . ' was turned down. ' . $biz['name'] . ' has been told.');
            redirect('admin/shop-names');
        }

        // The rules may have changed since the request, so check once more
        $check = shop_name_check($name, $biz_id);
        if (!in_array($check['state'], ['ok', 'review'], true)) {
            flash('error', $host . ' can\'t be used: ' . $check['message']);
            redirect('admin/shop-names');
        }

        try {
            $this->db->beginTransaction();
            $this->db->prepare("
                UPDATE businesses
                SET subdomain = ?, subdomain_renames = subdomain_renames + ?,
                    subdomain_requested = NULL, subdomain_status = 'active', subdomain_requested_at = NULL,
                    subdomain_reviewed_at = NOW(), subdomain_reviewed_by = ?
                WHERE id = ? AND subdomain_status = 'pending' AND subdomain_requested = ?
            ")->execute([$name, $is_rename ? 1 : 0, auth_user()['id'], $biz_id, $name]);
            // The old name keeps sending people to the shop for 90 days
            if ($is_rename) {
                shop_name_hold($biz_id, $biz['subdomain']);
            }
            shop_name_release($biz_id, $name);
            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($e->getCode() !== '23000') { throw $e; }
            flash('error', 'Another shop already has ' . $host . '.');
            redirect('admin/shop-names');
        }
        storefront_purge($biz_id, $is_rename ? [$biz['subdomain']] : []);

        if ($is_rename) {
            shop_notify($biz_id, 'Your new shop link is live', "{$host} is approved. Your old link sends people to it for the next 90 days, so update it wherever you've shared it.", 'landing-page');
        } else {
            shop_notify($biz_id, 'Your shop link is live', "{$host} is approved. Share it with your customers.", 'landing-page');
        }

        flash('success', $host . ' is live for ' . $biz['name'] . '.');
        redirect('admin/shop-names');
    }

    /* ── Internal helpers ─────────────────────── */

    /**
     * notifications.type is an ENUM: a type it doesn't list (support_reply,
     * subscription_suspended…) made the insert fail and the owner was never
     * told. Those go in as 'general'.
     */
    private const NOTIFICATION_TYPES = [
        'low_stock', 'invoice_overdue', 'payment_received', 'delivery_signed', 'subscription_renewal',
        'subscription_failed', 'trial_ending', 'feedback_request', 'general', 'momo_submitted', 'momo_approved',
        'momo_rejected', 'subscription_activated', 'subscription_cancelled',
    ];

    private function admin_notify(int $biz_id, string $type, string $message): void {
        if (!in_array($type, self::NOTIFICATION_TYPES, true)) {
            $type = 'general';
        }
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, created_at)
                VALUES (?, ?, 'Nfelia Admin', ?, NOW())
            ");
            $stmt->execute([$biz_id, $type, $message]);
        } catch (Exception $e) {
            error_log("Admin notification failed: " . $e->getMessage());
        }
    }
}