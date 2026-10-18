<?php
use Dompdf\Dompdf;
use Dompdf\Options;
/**
 * Nfelia — Reports Controller
 * Profit & Loss reporting from existing data.
 * No new tables needed — reads from invoices, receipts, expenses, debts.
 */
class ReportController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
        require_boss('see the profit and loss');
    }

    /* ──────────────────────────────────────────
     *  PROFIT & LOSS — Main report
     * ────────────────────────────────────────── */
    public function profit_loss() {
        $user     = auth_user();
        $business = auth_business();
        $currency = $business['currency_symbol'] ?? '₦';

        // Period selection (default: current month)
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year'] ?? date('Y'));
        $month = max(1, min(12, $month));
        $year  = max(2020, min((int)date('Y') + 1, $year));

        $period_start = sprintf('%04d-%02d-01', $year, $month);
        $period_end   = date('Y-m-t', strtotime($period_start)); // last day of month

        $month_label = date('F Y', strtotime($period_start));

        // ── 1. REVENUE: Payments received this period (from receipts)
        $stmtRev = $this->db->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM receipts
    WHERE business_id = ?
      AND issue_date BETWEEN ? AND ?
");
        $stmtRev->execute([$this->biz_id, $period_start, $period_end]);
        $revenue = (float)$stmtRev->fetchColumn();

        // ── 2. EXPENSES: All expenses this period
        $stmtExp = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE business_id = ? AND deleted_at IS NULL
              AND expense_date BETWEEN ? AND ?
        ");
        $stmtExp->execute([$this->biz_id, $period_start, $period_end]);
        $expenses_total = (float)$stmtExp->fetchColumn();

        // ── 3. GROSS PROFIT
        $gross_profit = $revenue - $expenses_total;

        // ── 4. EXPENSE BREAKDOWN by category
        $stmtCat = $this->db->prepare("
            SELECT category, COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE business_id = ? AND deleted_at IS NULL
              AND expense_date BETWEEN ? AND ?
            GROUP BY category
            ORDER BY total DESC
        ");
        $stmtCat->execute([$this->biz_id, $period_start, $period_end]);
        $expense_categories = $stmtCat->fetchAll();

        // ── 5. PENDING REVENUE: unpaid/partial invoices (all time, current outstanding)
        $stmtPending = $this->db->prepare("
    SELECT COALESCE(SUM(balance_due), 0) AS total,
           COUNT(*) AS count
    FROM invoices
    WHERE business_id = ?
      AND status IN ('sent','partially_paid','overdue')
      AND balance_due > 0
");
        $stmtPending->execute([$this->biz_id]);
        $pending = $stmtPending->fetch();
        $pending_revenue = (float)$pending['total'];
        $pending_count   = (int)$pending['count'];

        // ── 6. OUTSTANDING DEBTS (receivable vs payable)
        $stmtDebts = $this->db->prepare("
            SELECT type, COALESCE(SUM(balance), 0) AS total, COUNT(*) AS count
            FROM debts
            WHERE business_id = ? AND status IN ('active','settling')
            GROUP BY type
        ");
        $stmtDebts->execute([$this->biz_id]);
        $debt_rows = $stmtDebts->fetchAll();
        $debts_receivable = 0; $debts_receivable_count = 0;
        $debts_payable    = 0; $debts_payable_count    = 0;
        foreach ($debt_rows as $dr) {
            if ($dr['type'] === 'receivable') { $debts_receivable = (float)$dr['total']; $debts_receivable_count = (int)$dr['count']; }
            if ($dr['type'] === 'payable')    { $debts_payable    = (float)$dr['total']; $debts_payable_count    = (int)$dr['count']; }
        }

        // ── 7. MONTHLY TREND (last 6 months) — single aggregated query each
        $trend_start = date('Y-m-01', strtotime('-5 months', strtotime($period_start)));
        $trend_end   = $period_end;

        $stmtTrendRev = $this->db->prepare("
            SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
            FROM receipts WHERE business_id = ? AND issue_date BETWEEN ? AND ?
            GROUP BY ym
        ");
        $stmtTrendRev->execute([$this->biz_id, $trend_start, $trend_end]);
        $trend_rev_map = [];
        foreach ($stmtTrendRev->fetchAll() as $r) { $trend_rev_map[$r['ym']] = (float)$r['total']; }

        $stmtTrendExp = $this->db->prepare("
            SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
            FROM expenses WHERE business_id = ? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ?
            GROUP BY ym
        ");
        $stmtTrendExp->execute([$this->biz_id, $trend_start, $trend_end]);
        $trend_exp_map = [];
        foreach ($stmtTrendExp->fetchAll() as $r) { $trend_exp_map[$r['ym']] = (float)$r['total']; }

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts  = date('Y-m', strtotime("-{$i} months", strtotime($period_start)));
            $rev = $trend_rev_map[$ts] ?? 0;
            $exp = $trend_exp_map[$ts] ?? 0;
            $trend[] = ['label' => date('M', strtotime($ts . '-01')), 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
        }

        // ── 8. INVOICES ISSUED this period (for context)
        $stmtInv = $this->db->prepare("
            SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total
            FROM invoices
            WHERE business_id = ?
              AND issue_date BETWEEN ? AND ?
        ");
        $stmtInv->execute([$this->biz_id, $period_start, $period_end]);
        $invoices_issued = $stmtInv->fetch();

        // Available months for selector
        $available_months = [];
        for ($m = 1; $m <= 12; $m++) {
            $available_months[$m] = date('F', mktime(0, 0, 0, $m, 1));
        }

        $title     = "Profit & Loss";
        $view_file = BASE_PATH . '/views/reports/profit_loss.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  P&L PDF EXPORT
     * ────────────────────────────────────────── */
    public function profit_loss_pdf() {
        if (!is_logged_in()) { redirect('login'); return; }

        $user     = auth_user();
        $business = auth_business();
        $currency = $business['currency_symbol'] ?? '₦';

        $month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
        $year  = max(2020, min((int)date('Y') + 1, (int)($_GET['year'] ?? date('Y'))));

        $period_start = sprintf('%04d-%02d-01', $year, $month);
        $period_end   = date('Y-m-t', strtotime($period_start));
        $month_label  = date('F Y', strtotime($period_start));

        // Revenue
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE business_id=? AND issue_date BETWEEN ? AND ?");
        $stmt->execute([$this->biz_id, $period_start, $period_end]);
        $revenue = (float)$stmt->fetchColumn();

        // Expenses
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE business_id=? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ?");
        $stmt->execute([$this->biz_id, $period_start, $period_end]);
        $expenses_total = (float)$stmt->fetchColumn();
        $gross_profit = $revenue - $expenses_total;

        // Expense breakdown
        $stmt = $this->db->prepare("SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses WHERE business_id=? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
        $stmt->execute([$this->biz_id, $period_start, $period_end]);
        $expense_categories = $stmt->fetchAll();

        // Logo
        $logo_src = '';
        if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
            $ext = strtolower(pathinfo($business['logo_path'], PATHINFO_EXTENSION));
            $logo_src = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents(BASE_PATH . '/' . $business['logo_path']));
        }
        $primary = $business['primary_color'] ?? '#0F172A';

        ob_start();
        require BASE_PATH . '/views/reports/profit_loss_pdf.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', BASE_PATH . '/storage/pdf');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('PL_Report_' . $month_label . '.pdf', ['Attachment' => true]);
        exit;
    }
}