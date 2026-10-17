-- Fix inflated dashboard totals and the always-zero overdue count.
--
-- v_dashboard_summary LEFT JOINed invoices, payments and expenses side by
-- side on business_id, so every invoice row was repeated once per payment
-- and per expense before SUM ran. A business with 9 invoices, 9 payments and
-- 29 expenses saw ₦278,721,000 "invoiced this month" instead of ₦1,147,000,
-- and ₦44,955,000 outstanding instead of ₦185,000. Each total is now its own
-- subquery, so nothing multiplies. The rules for what counts are unchanged.
--
-- v_monthly_pl had the same join and is rewritten the same way (nothing reads
-- it today; this keeps it from being trusted in future).
--
-- v_overdue_invoices only matched 'sent' and 'partially_paid', but the
-- dashboard marks those invoices 'overdue' just before counting them, so the
-- count was always 0. It now includes 'overdue'.

CREATE OR REPLACE VIEW `v_dashboard_summary` AS
SELECT
    b.id   AS business_id,
    b.name AS business_name,
    COALESCE((SELECT SUM(i.total) FROM invoices i
              WHERE i.business_id = b.id
                AND i.status IN ('paid', 'partially_paid', 'overdue')
                AND i.issue_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND i.issue_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH), 0) AS total_invoiced_this_month,
    COALESCE((SELECT SUM(p.amount) FROM payments p
              WHERE p.business_id = b.id
                AND p.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND p.payment_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH), 0) AS total_received_this_month,
    COALESCE((SELECT SUM(i.balance_due) FROM invoices i
              WHERE i.business_id = b.id
                AND i.status IN ('sent', 'partially_paid', 'overdue')), 0) AS total_outstanding,
    COALESCE((SELECT SUM(e.amount) FROM expenses e
              WHERE e.business_id = b.id
                AND e.deleted_at IS NULL
                AND e.expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND e.expense_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH), 0) AS total_expenses_this_month
FROM businesses b;

CREATE OR REPLACE VIEW `v_monthly_pl` AS
SELECT
    t.business_id,
    t.period,
    t.total_revenue,
    t.total_expenses,
    t.total_revenue - t.total_expenses AS net_profit
FROM (
    SELECT
        b.id AS business_id,
        DATE_FORMAT(CURDATE(), '%Y-%m') AS period,
        COALESCE((SELECT SUM(p.amount) FROM payments p
                  WHERE p.business_id = b.id
                    AND p.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND p.payment_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH), 0) AS total_revenue,
        COALESCE((SELECT SUM(e.amount) FROM expenses e
                  WHERE e.business_id = b.id
                    AND e.deleted_at IS NULL
                    AND e.expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND e.expense_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH), 0) AS total_expenses
    FROM businesses b
) t;

CREATE OR REPLACE VIEW `v_overdue_invoices` AS
SELECT
    i.id,
    i.business_id,
    i.doc_number,
    i.due_date,
    i.total,
    i.balance_due,
    TO_DAYS(CURDATE()) - TO_DAYS(i.due_date) AS days_overdue,
    c.full_name AS customer_name,
    c.phone     AS customer_phone,
    c.whatsapp  AS customer_whatsapp
FROM invoices i
JOIN customers c ON c.id = i.customer_id
WHERE i.status IN ('sent', 'partially_paid', 'overdue')
  AND i.due_date < CURDATE();
