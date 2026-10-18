-- Getting the numbers right (docs/AUDIT_2026-10.md, PR 2).
--
-- document_counters.doc_type  takes EXP, so expenses get numbers like every
--                             other document (M4). All documents now use the
--                             one PHP generator (generate_doc_number).
-- receipts.customer_id        may be empty: a walk-in sale has no client (M5).
--
-- Each statement is safe to run twice: migrate.php runs the file in one
-- exec(), which reports an error only from the first statement.

ALTER TABLE `document_counters`
    MODIFY `doc_type` ENUM('QUO','INV','REC','DEL','DEA','EXP') NOT NULL;

ALTER TABLE `receipts`
    MODIFY `customer_id` INT(11) NULL DEFAULT NULL;

-- bookings.receipt_id         the receipt made for a deposit the owner ticked
--                             as received when confirming (M21).
ALTER TABLE `bookings`
    ADD COLUMN IF NOT EXISTS `receipt_id` INT(11) NULL DEFAULT NULL AFTER `task_id`;

-- Customer totals are now worked out from invoices, payments, direct receipts
-- and manual debts after every money change (customer_refresh_totals() in
-- app/helpers/money.php). Bring every client's figures in line once (M10).
UPDATE `customers` c SET
    total_invoiced = (SELECT COALESCE(SUM(i.total), 0) FROM invoices i
                      WHERE i.customer_id = c.id AND i.business_id = c.business_id AND i.status NOT IN ('draft', 'cancelled')),
    total_received = (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id
                      WHERE i.customer_id = c.id AND p.business_id = c.business_id)
                   + (SELECT COALESCE(SUM(r.amount), 0) FROM receipts r
                      WHERE r.customer_id = c.id AND r.business_id = c.business_id AND r.invoice_id IS NULL)
                   + (SELECT COALESCE(SUM(s.amount), 0) FROM debt_settlements s JOIN debts d ON d.id = s.debt_id
                      WHERE d.customer_id = c.id AND d.business_id = c.business_id AND d.type = 'receivable' AND d.invoice_id IS NULL),
    outstanding_balance = (SELECT COALESCE(SUM(i.balance_due), 0) FROM invoices i
                           WHERE i.customer_id = c.id AND i.business_id = c.business_id AND i.status IN ('sent', 'partially_paid', 'overdue'))
                        + (SELECT COALESCE(SUM(d.balance), 0) FROM debts d
                           WHERE d.customer_id = c.id AND d.business_id = c.business_id AND d.type = 'receivable'
                             AND d.invoice_id IS NULL AND d.status IN ('active', 'settling'));
