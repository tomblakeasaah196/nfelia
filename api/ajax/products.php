<?php
/**
 * Nfelia — Products AJAX API
 * NFELIA AUDIT FIX — C04 — Created missing AJAX endpoint — 2026-06-03
 * Handles product search for invoice/quotation builders.
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
            SELECT id, name, sku, sell_price, stock_qty, unit, category
            FROM products
            WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL
              AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)
            ORDER BY name ASC
            LIMIT 15
        ");
        $stmt->execute([$biz_id, $like, $like, $like]);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { json_response(['error' => 'Missing ID'], 400); }
        $stmt = $db->prepare(
            "SELECT id, name, sku, sell_price, cost_price, stock_qty, unit, category
             FROM products WHERE id = ? AND business_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $biz_id]);
        $product = $stmt->fetch();
        if (!$product) { json_response(['error' => 'Not found'], 404); }
        json_response(['data' => $product]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
