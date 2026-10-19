<?php
/**
 * Nfelia's own money (Admins): what clients pay, what Nfelia spends, and
 * referrals. Amounts paid by card are kept in kobo (subscription_history);
 * mobile money in the payer's own currency (subscription_payments).
 *
 *   GET  finance                         this month, MRR, 6 months, the model, expenses
 *   POST finance/expenses                add an expense
 *   POST finance/expenses/{id}/delete
 *   GET  referrals                       who brought whom
 */
class OpsFinanceController
{
    private const CATEGORIES = ['server' => 'Server and hosting', 'marketing' => 'Marketing', 'salary' => 'Salaries', 'tools' => 'Tools and software',
                                'domain' => 'Domains', 'design' => 'Design', 'legal' => 'Legal', 'other' => 'Other'];

    public function index(): void
    {
        $db = get_db();
        $card = $db->query("
            SELECT COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount END), 0) / 100 AS this_month,
                   COALESCE(SUM(amount), 0) / 100 AS all_time
            FROM subscription_history WHERE event_type = 'charge.success' AND status = 'success'")->fetch();
        $momo = $db->query("
            SELECT sp.currency_code, c.currency_symbol, SUM(sp.amount) AS total
            FROM subscription_payments sp JOIN countries c ON c.id = sp.country_id
            WHERE sp.status = 'approved' AND sp.reviewed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            GROUP BY sp.currency_code, c.currency_symbol")->fetchAll();
        $mrr = (float)$db->query(ops_mrr_sql())->fetchColumn();
        $plans = $db->query("SELECT COALESCE(SUM(" . ops_plan_sql('trial') . "), 0) AS trial FROM subscriptions s")->fetch();
        $signups = (int)$db->query("SELECT COUNT(*) FROM businesses WHERE created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime(date('Y-m-01') . " -{$i} months");
            $months[date('Y-m', $ts)] = ['label' => date('M', $ts), 'total' => 0.0];
        }
        foreach ($db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) / 100 AS total FROM subscription_history
                             WHERE event_type = 'charge.success' AND status = 'success' AND created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                             GROUP BY ym")->fetchAll() as $r) {
            if (isset($months[$r['ym']])) $months[$r['ym']]['total'] = (float)$r['total'];
        }

        $spend = $db->query("
            SELECT COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount END), 0) / 100 AS this_month,
                   COALESCE(SUM(CASE WHEN recurring = 1 THEN amount END), 0) / 100 AS monthly_recurring
            FROM admin_expenses")->fetch();
        $expenses = $db->query("SELECT * FROM admin_expenses WHERE expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') - INTERVAL 2 MONTH ORDER BY expense_date DESC, id DESC")->fetchAll();
        $recurring = (float)$spend['monthly_recurring'];
        require_once BASE_PATH . '/app/helpers/prices.php';
        $month_price = prices_now($db, prices_country($db, 'NG'))['month'];   // what a new client in Nigeria pays
        $model = [
            'profit'    => $mrr - $recurring,
            'margin'    => $mrr > 0 ? round(($mrr - $recurring) / $mrr * 100, 1) : 0,
            'breakeven' => $recurring > 0 ? (int)ceil($recurring / $month_price) : 0,
            'paying'    => (int)($plans['active'] ?? 0),
        ];
        ops_render('finance', compact('card', 'momo', 'mrr', 'plans', 'signups', 'months', 'spend', 'expenses', 'model') + ['categories' => self::CATEGORIES], 'Finance');
    }

    public function add_expense(): void
    {
        $category = isset(self::CATEGORIES[$_POST['category'] ?? '']) ? $_POST['category'] : 'other';
        $amount   = (int)round((float)($_POST['amount'] ?? 0) * 100);
        $date     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['expense_date'] ?? '')) ? $_POST['expense_date'] : date('Y-m-d');
        $desc     = trim((string)($_POST['description'] ?? ''));
        if ($amount <= 0) {
            ops_flash('error', 'Give an amount above zero.');
            ops_redirect('finance#expenses');
        }
        get_db()->prepare("INSERT INTO admin_expenses (category, description, amount, expense_date, recurring, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$category, $desc !== '' ? mb_substr($desc, 0, 500) : null, $amount, $date, !empty($_POST['recurring']) ? 1 : 0]);
        ops_audit('finance.expense', 'Added an expense: ₦' . number_format($amount / 100, 2) . ' (' . self::CATEGORIES[$category] . ')' . ($desc !== '' ? ', ' . $desc : ''));
        ops_flash('success', 'Expense added.');
        ops_redirect('finance#expenses');
    }

    public function delete_expense(string $id): void
    {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM admin_expenses WHERE id = ?");
        $stmt->execute([(int)$id]);
        if ($row = $stmt->fetch()) {
            $db->prepare("DELETE FROM admin_expenses WHERE id = ?")->execute([(int)$id]);
            ops_audit('finance.expense_removed', 'Removed an expense: ₦' . number_format($row['amount'] / 100, 2) . ' on ' . $row['expense_date'] . ($row['description'] ? ', ' . $row['description'] : ''),
                      ['data' => $row]);
            ops_flash('success', 'Expense removed.');
        }
        ops_redirect('finance#expenses');
    }

    public function referrals(): void
    {
        $db = get_db();
        $rows = $db->query("
            SELECT r.*, b1.id AS referrer_id, b1.name AS referrer_name, b2.id AS referred_id, b2.name AS referred_name
            FROM referrals r LEFT JOIN businesses b1 ON b1.id = r.referrer_business_id LEFT JOIN businesses b2 ON b2.id = r.referred_business_id
            ORDER BY r.created_at DESC LIMIT 200")->fetchAll();
        $stats = $db->query("SELECT COUNT(*) AS total, SUM(status = 'converted') AS converted, SUM(status = 'rewarded') AS rewarded,
                                    SUM(CASE WHEN status = 'rewarded' THEN discount_amount ELSE 0 END) AS discount FROM referrals")->fetch();
        ops_render('referrals', ['rows' => $rows, 'stats' => $stats], 'Referrals');
    }
}
