<?php
/**
 * Nfelia — what happens to an invoice, wherever it is done from (the invoice
 * screen, a debt, a shop order). Each function runs inside the caller's
 * transaction and locks the invoice row first, so a double tap or two people
 * at once can't apply the same change twice.
 */

/** The goods on an invoice's lines, as stock it takes off: [product_id => -qty]. */
function invoice_stock_target(PDO $db, int $biz_id, int $invoice_id): array {
    $stmt = $db->prepare("
        SELECT ii.product_id, SUM(ii.quantity) AS qty
        FROM invoice_items ii
        JOIN products p ON p.id = ii.product_id AND p.business_id = ?
        WHERE ii.invoice_id = ? AND p.is_service = 0
        GROUP BY ii.product_id
    ");
    $stmt->execute([$biz_id, $invoice_id]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int)$row['product_id']] = -round((float)$row['qty'], 2);
    }
    return $out;
}

/** Lock and return an invoice of this business, or throw. */
function invoice_lock(PDO $db, int $biz_id, int $invoice_id): array {
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? AND business_id = ? FOR UPDATE");
    $stmt->execute([$invoice_id, $biz_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        throw new DomainException('That invoice isn\'t here.');
    }
    return $invoice;
}

/**
 * Record money received on an invoice. Amounts are worked in kobo, so two
 * payments that add up to the total leave nothing owing (not 0.0000000000018).
 * Throws DomainException with a message for the person when it can't be done.
 * Returns the payment id, the invoice as it was, and its new figures.
 */
function invoice_record_payment(PDO $db, int $biz_id, int $invoice_id, float $amount, string $payment_date, string $method,
                                ?string $reference, ?string $note, int $user_id, string $symbol = '₦'): array {
    $invoice = invoice_lock($db, $biz_id, $invoice_id);
    if (!in_array($invoice['status'], ['sent', 'partially_paid', 'overdue'], true)) {
        throw new DomainException('Payments cannot be recorded on this invoice.');
    }
    $amount  = money2($amount);
    $balance = money2((float)$invoice['total'] - (float)$invoice['amount_paid']);
    if ($amount <= 0) {
        throw new DomainException('Payment amount must be greater than zero.');
    }
    if ($amount > $balance) {
        throw new DomainException('Payment exceeds the remaining balance of ' . money($balance, $symbol) . '.');
    }
    $db->prepare("
        INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$biz_id, $invoice_id, $user_id, $amount, $payment_date, $method, $reference ?: null, $note ?: null]);
    $payment_id = (int)$db->lastInsertId();

    $new_paid    = money2((float)$invoice['amount_paid'] + $amount);
    $new_balance = money2((float)$invoice['total'] - $new_paid);
    $new_status  = $new_balance <= 0 ? 'paid' : 'partially_paid';
    $db->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ? AND business_id = ?")
       ->execute([$new_paid, max(0, $new_balance), $new_status, $invoice_id, $biz_id]);

    invoice_sync_debt($db, $biz_id, $invoice_id);
    customer_refresh_totals($db, $biz_id, (int)$invoice['customer_id']);
    return ['payment_id' => $payment_id, 'invoice' => $invoice, 'new_paid' => $new_paid,
            'new_balance' => max(0, $new_balance), 'new_status' => $new_status];
}

/**
 * Record money given back on an invoice: a payment with a minus amount, on the
 * day it was given back, so the books show both the payment and the refund.
 */
function invoice_record_refund(PDO $db, int $biz_id, int $invoice_id, float $amount, string $date, string $method,
                               ?string $note, int $user_id, string $symbol = '₦'): array {
    $invoice = invoice_lock($db, $biz_id, $invoice_id);
    $amount  = money2($amount);
    $paid    = money2((float)$invoice['amount_paid']);
    if ($amount <= 0) {
        throw new DomainException('Refund amount must be greater than zero.');
    }
    if ($amount > $paid) {
        throw new DomainException('You can refund at most what was paid: ' . money($paid, $symbol) . '.');
    }
    $db->prepare("
        INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference, note)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?)
    ")->execute([$biz_id, $invoice_id, $user_id, -$amount, $date, $method, 'Refund' . ($note ? ': ' . $note : '')]);

    $new_paid    = money2($paid - $amount);
    $new_balance = money2((float)$invoice['total'] - $new_paid);
    $new_status  = $invoice['status'] === 'cancelled' ? 'cancelled' : ($new_paid > 0 ? 'partially_paid' : 'sent');
    $db->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ? AND business_id = ?")
       ->execute([$new_paid, $new_balance, $new_status, $invoice_id, $biz_id]);

    invoice_sync_debt($db, $biz_id, $invoice_id);
    customer_refresh_totals($db, $biz_id, (int)$invoice['customer_id']);
    return ['invoice' => $invoice, 'new_paid' => $new_paid, 'new_balance' => $new_balance];
}

/** An invoice's open debt follows what is still owed on the invoice. */
function invoice_sync_debt(PDO $db, int $biz_id, int $invoice_id): void {
    $stmt = $db->prepare("SELECT balance_due, status FROM invoices WHERE id = ? AND business_id = ?");
    $stmt->execute([$invoice_id, $biz_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return;
    }
    $stmt = $db->prepare("SELECT id, total_amount FROM debts WHERE invoice_id = ? AND business_id = ? AND status IN ('active', 'settling')");
    $stmt->execute([$invoice_id, $biz_id]);
    foreach ($stmt->fetchAll() as $debt) {
        $balance = money2(min((float)$debt['total_amount'], (float)$invoice['balance_due']));
        $settled = money2((float)$debt['total_amount'] - $balance);
        $status  = $balance <= 0 ? 'cleared' : ($settled > 0 ? 'settling' : 'active');
        $db->prepare("UPDATE debts SET amount_settled = ?, balance = ?, status = ? WHERE id = ? AND business_id = ?")
           ->execute([$settled, max(0, $balance), $status, $debt['id'], $biz_id]);
    }
}

/**
 * Cancel an invoice: once, and only with nothing paid on it (Tom's decision:
 * record a refund first). The stock it took off goes back, exactly what it
 * moved; its debt is written off; a shop order made into this invoice is
 * cancelled, and its delivery closed so nobody signs for it. Returns an error
 * message, or '' when it is cancelled.
 */
function invoice_cancel(PDO $db, int $biz_id, int $invoice_id, string $symbol = '₦'): string {
    $invoice = invoice_lock($db, $biz_id, $invoice_id);
    if ($invoice['status'] === 'cancelled') {
        return 'Invoice is already cancelled.';
    }
    if ((float)$invoice['amount_paid'] > 0) {
        return 'This invoice has ' . money((float)$invoice['amount_paid'], $symbol) . ' paid on it. Record a refund for that first, then cancel it.';
    }
    $db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ? AND business_id = ?")->execute([$invoice_id, $biz_id]);

    stock_settle($db, $biz_id, 'invoice', $invoice_id, [], 'invoice ' . $invoice['doc_number'] . ' cancelled');

    $db->prepare("
        UPDATE debts SET status = 'written_off', note = CONCAT(COALESCE(note,''), ' [Invoice cancelled]')
        WHERE invoice_id = ? AND business_id = ? AND status IN ('active','settling')
    ")->execute([$invoice_id, $biz_id]);

    $db->prepare("
        UPDATE orders SET status = 'cancelled'
        WHERE invoice_id = ? AND business_id = ? AND status NOT IN ('cancelled', 'rejected')
    ")->execute([$invoice_id, $biz_id]);
    $db->prepare("
        UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes, ''), ' [Invoice cancelled]')
        WHERE invoice_id = ? AND business_id = ? AND deleted_at IS NULL AND status IN ('pending_dispatch', 'dispatched', 'in_transit')
    ")->execute([$invoice_id, $biz_id]);

    customer_refresh_totals($db, $biz_id, (int)$invoice['customer_id']);
    return '';
}
