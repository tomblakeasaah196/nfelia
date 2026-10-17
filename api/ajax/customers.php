<?php
/**
 * Nfelia — Customers AJAX API
 * NFELIA AUDIT FIX — C04 — Created missing AJAX endpoint — 2026-06-03
 * Handles customer search for invoice/quotation builders.
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
            SELECT id, full_name, business_name, phone, email, address
            FROM customers
            WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
              AND (full_name LIKE ? OR business_name LIKE ? OR phone LIKE ? OR email LIKE ?)
            ORDER BY full_name ASC
            LIMIT 15
        ");
        $stmt->execute([$biz_id, $like, $like, $like, $like]);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { json_response(['error' => 'Missing ID'], 400); }
        $stmt = $db->prepare(
            "SELECT id, full_name, business_name, phone, whatsapp, email, address
             FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $biz_id]);
        $customer = $stmt->fetch();
        if (!$customer) { json_response(['error' => 'Not found'], 404); }
        json_response(['data' => $customer]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
