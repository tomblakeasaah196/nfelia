<?php
/**
 * Nfelia — money that has to add up.
 *
 * money_in() is the one basis for "money received", used by Home and the
 * profit and loss alike: payments recorded on invoices, by their date, plus
 * receipts for sales paid on the spot (receipts with no invoice), by theirs.
 * A receipt made for an invoice payment is that payment's paperwork, so it is
 * never counted twice.
 *
 * customer_refresh_totals() works a client's invoiced, received and owed
 * figures out again from their documents after every change (Tom's decision,
 * October 2026), instead of nudging stored running totals up and down.
 */

/** Amounts are compared and stored to the kobo: 0.1 + 0.2 must equal 0.3. */
function money2(float $amount): float {
    return round($amount, 2);
}

/** Money received from $from to $to inclusive (Y-m-d). */
function money_in(PDO $db, int $biz_id, string $from, string $to): float {
    $stmt = $db->prepare("
        SELECT (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE business_id = ? AND payment_date BETWEEN ? AND ?)
             + (SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE business_id = ? AND invoice_id IS NULL AND issue_date BETWEEN ? AND ?)
    ");
    $stmt->execute([$biz_id, $from, $to, $biz_id, $from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Money received per month ['2026-10' => amount], from $from to $to inclusive. */
function money_in_by_month(PDO $db, int $biz_id, string $from, string $to): array {
    $stmt = $db->prepare("
        SELECT ym, SUM(total) AS total FROM (
            SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
            FROM payments WHERE business_id = ? AND payment_date BETWEEN ? AND ? GROUP BY ym
            UNION ALL
            SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, SUM(amount) AS total
            FROM receipts WHERE business_id = ? AND invoice_id IS NULL AND issue_date BETWEEN ? AND ? GROUP BY ym
        ) money GROUP BY ym
    ");
    $stmt->execute([$biz_id, $from, $to, $biz_id, $from, $to]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['ym']] = (float)$row['total'];
    }
    return $out;
}

/**
 * Work a client's figures out again from their documents:
 *   invoiced  every invoice that went out (not drafts, not cancelled)
 *   received  payments on their invoices, sales paid on the spot, and what
 *             they paid off debts recorded by hand
 *   owed      what is left on their open invoices, and on those debts
 * Call it after anything that changes one of those, for each client touched.
 */
function customer_refresh_totals(PDO $db, int $biz_id, ?int $customer_id): void {
    if (!$customer_id) {
        return;
    }
    $db->prepare("
        UPDATE customers c SET
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
                                     AND d.invoice_id IS NULL AND d.status IN ('active', 'settling'))
        WHERE c.id = ? AND c.business_id = ?
    ")->execute([$customer_id, $biz_id]);
}

/**
 * An expense's open supplier debt follows what is still unpaid on the
 * expense; a deleted expense's debt is written off.
 */
function expense_sync_debt(PDO $db, int $biz_id, int $expense_id): void {
    $stmt = $db->prepare("SELECT doc_number, description, supplier_id, balance_due, deleted_at FROM expenses WHERE id = ? AND business_id = ?");
    $stmt->execute([$expense_id, $biz_id]);
    $expense = $stmt->fetch();

    // An expense still owed to a supplier is a payable from the moment it is
    // saved (the Debts page used to make it only when it was opened)
    $stmt = $db->prepare("SELECT COUNT(*) FROM debts WHERE expense_id = ? AND business_id = ? AND source = 'expense'");
    $stmt->execute([$expense_id, $biz_id]);
    if ($expense && $expense['deleted_at'] === null && $expense['supplier_id'] && (float)$expense['balance_due'] > 0 && !(int)$stmt->fetchColumn()) {
        $db->prepare("
            INSERT INTO debts (business_id, user_id, type, supplier_id, description, total_amount, amount_settled, balance, status, source, expense_id)
            VALUES (?, NULL, 'payable', ?, ?, ?, 0, ?, 'active', 'expense', ?)
        ")->execute([$biz_id, (int)$expense['supplier_id'],
                     'Unpaid expense ' . ($expense['doc_number'] ?? '') . ' — ' . mb_substr((string)$expense['description'], 0, 100),
                     money2((float)$expense['balance_due']), money2((float)$expense['balance_due']), $expense_id]);
        return;
    }

    $stmt = $db->prepare("SELECT id, total_amount FROM debts WHERE expense_id = ? AND business_id = ? AND source = 'expense' AND status IN ('active', 'settling')");
    $stmt->execute([$expense_id, $biz_id]);
    foreach ($stmt->fetchAll() as $debt) {
        if (!$expense || $expense['deleted_at'] !== null) {
            $db->prepare("UPDATE debts SET status = 'written_off', note = CONCAT(COALESCE(note, ''), ' [Expense deleted]') WHERE id = ?")->execute([$debt['id']]);
            continue;
        }
        $balance = money2(max(0, (float)$expense['balance_due']));
        $total   = max((float)$debt['total_amount'], $balance);
        $settled = money2($total - $balance);
        $db->prepare("UPDATE debts SET total_amount = ?, amount_settled = ?, balance = ?, status = ? WHERE id = ?")
           ->execute([$total, $settled, $balance, $balance <= 0 ? 'cleared' : ($settled > 0 ? 'settling' : 'active'), $debt['id']]);
    }
}
