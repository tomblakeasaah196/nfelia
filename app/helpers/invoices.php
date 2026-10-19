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
 * An invoice still covered by what is left paid stays paid (giving back what
 * was paid over the total); it is part paid only when something is owed.
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
    $payment_id = (int)$db->lastInsertId();

    $new_paid    = money2($paid - $amount);
    $new_balance = money2(max(0, (float)$invoice['total'] - $new_paid));
    $new_status  = match (true) {
        $invoice['status'] === 'cancelled'      => 'cancelled',
        $new_paid >= (float)$invoice['total']   => 'paid',
        $new_paid > 0                           => 'partially_paid',
        default                                 => 'sent',
    };
    $db->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ? AND business_id = ?")
       ->execute([$new_paid, $new_balance, $new_status, $invoice_id, $biz_id]);

    invoice_sync_debt($db, $biz_id, $invoice_id);
    customer_refresh_totals($db, $biz_id, (int)$invoice['customer_id']);
    return ['invoice' => $invoice, 'payment_id' => $payment_id, 'new_paid' => $new_paid, 'new_balance' => $new_balance];
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
 * moved; its debt is written off; its order (invoices.order_id, or an older
 * shop order that points at it) is cancelled, and its delivery closed so
 * nobody signs for it. Returns an error message, or '' when it is cancelled.
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
        WHERE (invoice_id = ? OR id = ?) AND business_id = ? AND status NOT IN ('cancelled', 'rejected')
    ")->execute([$invoice_id, (int)($invoice['order_id'] ?? 0), $biz_id]);
    $db->prepare("
        UPDATE deliveries SET status = 'failed', notes = CONCAT(COALESCE(notes, ''), ' [Invoice cancelled]')
        WHERE invoice_id = ? AND business_id = ? AND deleted_at IS NULL AND status IN ('pending_dispatch', 'dispatched', 'in_transit')
    ")->execute([$invoice_id, $biz_id]);

    // Its delivery step goes off Tasks & Calendar (P2)
    planner_invoice_cancelled($db, $biz_id, $invoice_id);

    customer_refresh_totals($db, $biz_id, (int)$invoice['customer_id']);
    return '';
}

/**
 * The WhatsApp reminder for an unpaid invoice, as a wa.me link to its
 * customer: the balance, how late it is, and the invoice's public page.
 * Both the Send a reminder button (views/invoices/view.php, where
 * assets/js/wa.js opens WhatsApp with it directly) and
 * InvoiceController::send_reminder() use it, so they say the same thing.
 */
function invoice_reminder_wa_link(array $invoice, ?array $customer, array $business, ?array $order = null): string {
    $currency  = $business['currency_symbol'] ?? '₦';
    $wa_number = wa_digits((string)(($customer['whatsapp'] ?? '') ?: ($customer['phone'] ?? '')), $business);

    $days_overdue = '';
    if (!empty($invoice['due_date'])) {
        $diff = (int)((time() - strtotime($invoice['due_date'])) / 86400);
        if ($diff > 0) $days_overdue = " ({$diff} day" . ($diff > 1 ? 's' : '') . " past due)";
    }

    $public_url = APP_URL . '/doc/' . $invoice['token'];
    $text = "Hello " . ($customer['full_name'] ?? '') . ",\n\n"
        . "This is a friendly reminder regarding your outstanding invoice from *{$business['name']}*:\n\n"
        . "Invoice: *{$invoice['doc_number']}*" . ($order ? " for your order *{$order['order_number']}*" : '') . "\n"
        . "Total: *{$currency}" . number_format((float)$invoice['total'], 2) . "*\n"
        . "Balance Due: *{$currency}" . number_format((float)$invoice['balance_due'], 2) . "*" . $days_overdue . "\n"
        . (!empty($invoice['due_date']) ? "Due Date: " . fmt_date($invoice['due_date'], 'd M Y') . "\n" : '')
        . "\nView invoice: {$public_url}\n\n"
        . "Kindly let us know once payment is made. Thank you!\n\n"
        . "_Sent via Nfelia — nfelia.com_";
    return "https://wa.me/{$wa_number}?text=" . urlencode($text);
}
