<?php
use Dompdf\Dompdf;
use Dompdf\Options;
/**
 * Nfelia — Reports Controller
 * Profit & Loss reporting from existing data.
 * No new tables needed — reads from invoices, receipts, expenses, debts.
 */
require_once BASE_PATH . '/app/helpers/report_period.php';

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

        // Period: a preset or a month range (app/helpers/report_period.php)
        $period       = report_period($_GET);
        $period_start = $period['start'];
        $period_end   = $period['end'];
        $month_label  = $period['label'];

        // ── 1. REVENUE: money received this period: invoice payments and
        // sales paid on the spot, the same basis as Home (money_in)
        $revenue = money_in($this->db, $this->biz_id, $period_start, $period_end);

        // ── 2. EXPENSES: All expenses this period
        $expenses_total = $this->expenses_between($period_start, $period_end);

        // ── 3. GROSS PROFIT
        $gross_profit = $revenue - $expenses_total;

        // ── 3b. THE PERIOD BEFORE, to compare with
        $prev_revenue  = money_in($this->db, $this->biz_id, $period['prev_start'], $period['prev_end']);
        $prev_expenses = $this->expenses_between($period['prev_start'], $period['prev_end']);
        $prev_profit   = $prev_revenue - $prev_expenses;

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
            SELECT type, COALESCE(SUM(balance), 0) AS total, COUNT(*) AS count,
                   COALESCE(SUM(CASE WHEN invoice_id IS NULL THEN balance ELSE 0 END), 0) AS not_invoiced
            FROM debts
            WHERE business_id = ? AND status IN ('active','settling')
            GROUP BY type
        ");
        $stmtDebts->execute([$this->biz_id]);
        $debt_rows = $stmtDebts->fetchAll();
        $debts_receivable = 0; $debts_receivable_count = 0;
        $debts_payable    = 0; $debts_payable_count    = 0;
        // What could still come in: unpaid invoices, plus debts that didn't
        // come from an invoice (overdue invoices are already in the first)
        $collectable_extra = 0;
        foreach ($debt_rows as $dr) {
            if ($dr['type'] === 'receivable') { $debts_receivable = (float)$dr['total']; $debts_receivable_count = (int)$dr['count']; $collectable_extra = (float)$dr['not_invoiced']; }
            if ($dr['type'] === 'payable')    { $debts_payable    = (float)$dr['total']; $debts_payable_count    = (int)$dr['count']; }
        }

        // ── 7. TREND across the period: by day for a month or less, else by month
        if ($period['by'] === 'day') {
            $trend_rev_map = money_in_by_day($this->db, $this->biz_id, $period_start, $period_end);
            $bucket_sql    = '%Y-%m-%d';
        } else {
            $trend_rev_map = money_in_by_month($this->db, $this->biz_id, $period_start, $period_end);
            $bucket_sql    = '%Y-%m';
        }
        $stmtTrendExp = $this->db->prepare("
            SELECT DATE_FORMAT(expense_date, '{$bucket_sql}') AS ym, COALESCE(SUM(amount), 0) AS total
            FROM expenses WHERE business_id = ? AND deleted_at IS NULL AND expense_date BETWEEN ? AND ?
            GROUP BY ym
        ");
        $stmtTrendExp->execute([$this->biz_id, $period_start, $period_end]);
        $trend_exp_map = [];
        foreach ($stmtTrendExp->fetchAll() as $r) { $trend_exp_map[$r['ym']] = (float)$r['total']; }

        $trend = [];
        foreach (report_period_buckets($period) as $key => $label) {
            $rev = $trend_rev_map[$key] ?? 0;
            $exp = $trend_exp_map[$key] ?? 0;
            $trend[] = ['label' => $label, 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
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

        // Month and year choices for the month range picker
        $available_months = [];
        for ($m = 1; $m <= 12; $m++) {
            $available_months[$m] = date('M', mktime(0, 0, 0, $m, 1));
        }
        $available_years = range((int)date('Y'), REPORT_PERIOD_FIRST_YEAR);

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

        $period       = report_period($_GET);
        $period_start = $period['start'];
        $period_end   = $period['end'];
        $month_label  = $period['label'];

        // Revenue (the same basis as the screen and Home)
        $revenue = money_in($this->db, $this->biz_id, $period_start, $period_end);

        // Expenses
        $expenses_total = $this->expenses_between($period_start, $period_end);
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
        ['primary' => $primary, 'text' => $primary_text] = brand_doc_colours($business);   // the brand colours, else Nfelia's (app/helpers/brand.php)

        ob_start();
        require BASE_PATH . '/views/reports/profit_loss_pdf.php';
        $html = ob_get_clean();

        // The session is let go before the render, so the person's other
        // tabs don't wait on it; the PDF is kept for the next download of
        // the same figures (app/helpers/pdf.php).
        session_write_close();
        pdf_send(pdf_file($html), 'PL_Report_' . $period['filename'] . '.pdf');
    }

    /** Expenses recorded from $from to $to inclusive (Y-m-d). */
    private function expenses_between(string $from, string $to): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM expenses
            WHERE business_id = ? AND deleted_at IS NULL
              AND expense_date BETWEEN ? AND ?
        ");
        $stmt->execute([$this->biz_id, $from, $to]);
        return (float)$stmt->fetchColumn();
    }
}