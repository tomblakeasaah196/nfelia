<?php
/**
 * Nfelia — moving stock.
 *
 * Every change to a product's stock goes through stock_move(): the product's
 * row is locked first, so two changes at once can't overwrite each other, and
 * each change is written to stock_movements with what caused it. Documents
 * that move stock (invoices, receipts, expenses) undo exactly what they moved,
 * from those movements (stock_moved_for), never from their current lines.
 */

/**
 * Move one product's stock by $delta (positive in, negative out), inside the
 * caller's transaction. Services, deleted products and other businesses'
 * products are left alone. $type is a stock_movements type: stock_in,
 * stock_out, adjustment, sale or return. Returns the stock after, or null
 * when nothing moved.
 */
function stock_move(PDO $db, int $biz_id, int $product_id, float $delta, string $type, ?string $ref_type, ?int $ref_id, string $note, ?float $unit_cost = null, ?int $user_id = null): ?float {
    $delta = round($delta, 2);
    if ($delta == 0.0) {
        return null;
    }
    $stmt = $db->prepare("SELECT stock_qty, is_service FROM products WHERE id = ? AND business_id = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([$product_id, $biz_id]);
    $product = $stmt->fetch();
    if (!$product || (int)$product['is_service'] === 1) {
        return null;
    }
    $before = (float)$product['stock_qty'];
    $after  = round($before + $delta, 2);
    $db->prepare("UPDATE products SET stock_qty = ? WHERE id = ? AND business_id = ?")->execute([$after, $product_id, $biz_id]);
    $db->prepare("
        INSERT INTO stock_movements (business_id, product_id, user_id, type, quantity, stock_before, stock_after, unit_cost, reference_type, reference_id, note, movement_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
    ")->execute([$biz_id, $product_id, $user_id ?? (int)(auth_user()['id'] ?? 0), $type, abs($delta), $before, $after,
                 $unit_cost, $ref_type, $ref_id, mb_substr($note, 0, 500)]);
    return $after;
}

/**
 * What a document has moved so far, per product: [product_id => net change]
 * (negative when it took stock off, as a sale does). Read from its own
 * movements, so undoing it puts back exactly that, even when its lines, or a
 * product's goods/service setting, changed since.
 */
function stock_moved_for(PDO $db, int $biz_id, string $ref_type, int $ref_id): array {
    $stmt = $db->prepare("
        SELECT product_id, SUM(CASE WHEN stock_after >= stock_before THEN quantity ELSE -quantity END) AS net
        FROM stock_movements
        WHERE business_id = ? AND reference_type = ? AND reference_id = ?
        GROUP BY product_id
    ");
    $stmt->execute([$biz_id, $ref_type, $ref_id]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        if (round((float)$row['net'], 2) != 0.0) {
            $out[(int)$row['product_id']] = round((float)$row['net'], 2);
        }
    }
    return $out;
}

/**
 * Bring what a document has moved in line with $target ([product_id =>
 * net change wanted], negative for goods going out). Only the difference
 * moves. Returns true when anything moved.
 */
function stock_settle(PDO $db, int $biz_id, string $ref_type, int $ref_id, array $target, string $doc_label,
                      string $out_type = 'sale', string $in_type = 'return', array $unit_costs = []): bool {
    $moved   = stock_moved_for($db, $biz_id, $ref_type, $ref_id);
    $changed = false;
    foreach (array_unique(array_merge(array_keys($moved), array_keys($target))) as $product_id) {
        $diff = round(($target[$product_id] ?? 0) - ($moved[$product_id] ?? 0), 2);
        if ($diff == 0.0) {
            continue;
        }
        $type = $diff < 0 ? $out_type : $in_type;
        $note = $diff < 0 ? 'Stock taken off for ' . $doc_label : 'Stock added for ' . $doc_label;
        if (stock_move($db, $biz_id, (int)$product_id, $diff, $type, $ref_type, $ref_id, $note, $unit_costs[$product_id] ?? null) !== null) {
            $changed = true;
        }
    }
    return $changed;
}
