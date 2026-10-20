-- Indexes for the lists and reports every business opens, and two
-- duplicates removed. Each one serves queries that filter by business and
-- then by a status or a date (app/controllers/*Controller.php index(),
-- ReportController, DashboardController, cron/planner.php):
--
--   payments   by business and payment date      P&L, home, customer pages
--   invoices   by business, status, created_at   the invoice list and its filters
--   invoices   by business and due date          overdue scans in the cron
--   receipts   by business and issue date        the receipt list, P&L
--   expenses   by business, deleted, date        the expense list, P&L
--   customers  by business, deleted, active      the client list and pickers
--   deliveries by business, deleted, status      the delivery list
--   sessions   by expiry                         the cron's clean-up
--
-- sessions.idx_session_token repeated the UNIQUE key on the same column,
-- and subscriptions.idx_business_id repeated its UNIQUE key: both only
-- cost writes. IF EXISTS / IF NOT EXISTS keep this safe to run again.

CREATE INDEX IF NOT EXISTS `idx_payments_biz_date`      ON `payments`   (`business_id`, `payment_date`);
CREATE INDEX IF NOT EXISTS `idx_invoices_biz_status_at` ON `invoices`   (`business_id`, `status`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_invoices_biz_due`       ON `invoices`   (`business_id`, `due_date`);
CREATE INDEX IF NOT EXISTS `idx_receipts_biz_issue`     ON `receipts`   (`business_id`, `issue_date`);
CREATE INDEX IF NOT EXISTS `idx_expenses_biz_live_date` ON `expenses`   (`business_id`, `deleted_at`, `expense_date`);
CREATE INDEX IF NOT EXISTS `idx_customers_biz_live`     ON `customers`  (`business_id`, `deleted_at`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_deliveries_biz_live`    ON `deliveries` (`business_id`, `deleted_at`, `status`);
CREATE INDEX IF NOT EXISTS `idx_sessions_expires`       ON `sessions`   (`expires_at`);

DROP INDEX IF EXISTS `idx_session_token` ON `sessions`;
DROP INDEX IF EXISTS `idx_business_id`   ON `subscriptions`;
