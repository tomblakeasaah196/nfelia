<?php
/**
 * Nfelia — Invoices AJAX API
 * NFELIA AUDIT FIX — C04 — Created missing AJAX endpoint — 2026-06-03
 * Handles invoice search and status updates via AJAX.
 */

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

$db     = get_db();
$biz_id = auth_business_id();
$action = $_GET['action'] ?? 'search';

switch ($action) {

    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            json_response(['data' => []]);
        }
        $like = '%' . $q . '%';
        $stmt = $db->prepare("
            SELECT i.id, i.doc_number, i.total, i.balance_due, i.status,
                   c.full_name AS customer_name, c.id AS customer_id,
                   c.phone AS customer_phone, c.address AS customer_address
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ?
              AND (i.doc_number LIKE ? OR c.full_name LIKE ?)
            ORDER BY i.created_at DESC
            LIMIT 15
        ");
        $stmt->execute([$biz_id, $like, $like]);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    case 'get_items':
        $invoice_id = (int)($_GET['invoice_id'] ?? 0);
        if (!$invoice_id) { json_response(['error' => 'Missing invoice_id'], 400); }
        // Security: ensure invoice belongs to this business
        $stmtCheck = $db->prepare("SELECT id FROM invoices WHERE id = ? AND business_id = ?");
        $stmtCheck->execute([$invoice_id, $biz_id]);
        if (!$stmtCheck->fetch()) { json_response(['error' => 'Not found'], 404); }

        $stmt = $db->prepare("
            SELECT ii.id, ii.description, ii.quantity, ii.unit_price, ii.discount_percent,
                   p.id AS product_id, p.name AS product_name
            FROM invoice_items ii
            LEFT JOIN products p ON p.id = ii.product_id
            WHERE ii.invoice_id = ?
        ");
        $stmt->execute([$invoice_id]);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
