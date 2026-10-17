<?php
/**
 * Nfelia — Founder Admin Panel Controller
 * Restricted to business_id = 1 (founder account).
 * Route prefix: /admin/*
 *
 * Tabs: Dashboard, Businesses, Feedback, Referrals, Support, Financials
 */
class AdminController {

    private $db;
    private const FOUNDER_BUSINESS_ID = 1;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
        $this->guard();
    }

    private function guard(): void {
        if ((int)auth_business_id() !== self::FOUNDER_BUSINESS_ID) {
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

        // MRR (Monthly Recurring Revenue)
        // NFELIA AUDIT FIX M01 — monthly_amount stored in NGN, not kobo — 2026-06-03
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(monthly_amount + (extra_seats * 5000)), 0) AS mrr
            FROM subscriptions WHERE status = 'active'
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
                $chart_months[$row['ym']]['revenue'] = (float)$row['total'];
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

        switch ($action) {
            case 'activate':
                $this->db->prepare("
                    UPDATE subscriptions SET status = 'active', current_period_start = NOW(),
                    current_period_end = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE business_id = ?
                ")->execute([$biz_id]);
                $this->admin_notify($biz_id, 'subscription_activated', 'Your subscription has been activated by the Nfelia team.');
                break;

            case 'suspend':
                $this->db->prepare("UPDATE subscriptions SET status = 'suspended' WHERE business_id = ?")->execute([$biz_id]);
                $this->admin_notify($biz_id, 'subscription_suspended', 'Your account has been suspended. Contact support for assistance.');
                break;

            case 'extend_trial':
                $days = max(1, min(30, (int)($_POST['days'] ?? 7)));
                $this->db->prepare("
                    UPDATE subscriptions SET status = 'trial', trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL ? DAY)
                    WHERE business_id = ?
                ")->execute([$days, $biz_id]);
                $this->admin_notify($biz_id, 'trial_expiring_soon', "Your trial has been extended by {$days} days. Enjoy!");
                break;

            default:
                json_response(['error' => 'Invalid action'], 400);
                return;
        }

        json_response(['success' => true, 'message' => ucfirst($action) . ' completed.']);
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

    /* ── Internal helpers ─────────────────────── */

    private function admin_notify(int $biz_id, string $type, string $message): void {
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