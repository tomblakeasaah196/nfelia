<?php
/**
 * Nfelia — Product / Inventory Controller
 * Full CRUD, stock management, audit trail, setup, import/export.
 */
class ProductController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) {
            redirect('login');
        }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();

        // Any change to products changes the shop page: once this request
        // is answered, Cloudflare drops its copy of the shop (cloudflare.php)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            storefront_purge((int)$this->biz_id);
        }
    }

    /* ──────────────────────────────────────────
     *  LIST — filterable, paginated inventory
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // ── Filters ──
        $search       = trim($_GET['q'] ?? '');
        $category     = trim($_GET['category'] ?? '');
        $stock_status = trim($_GET['stock'] ?? '');    // all | in_stock | low | out | service
        $active_filter = trim($_GET['active'] ?? '');  // '' | 1 | 0
        $sort         = trim($_GET['sort'] ?? 'newest');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $per_page     = 24;
        $offset       = ($page - 1) * $per_page;

        // ── Build WHERE clauses ──
        $where  = ["p.business_id = ?", "p.deleted_at IS NULL"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($category !== '') {
            $where[]  = "p.category = ?";
            $params[] = $category;
        }
        if ($active_filter !== '') {
            $where[]  = "p.is_active = ?";
            $params[] = (int) $active_filter;
        }

        switch ($stock_status) {
            case 'in_stock':
                $where[] = "p.is_service = 0 AND p.stock_qty > p.low_stock_threshold";
                break;
            case 'low':
                $where[] = "p.is_service = 0 AND p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold";
                break;
            case 'out':
                $where[] = "p.is_service = 0 AND p.stock_qty <= 0";
                break;
            case 'restock':     // low or out: the low-stock alert opens this
                $where[] = "p.is_service = 0 AND p.stock_qty <= p.low_stock_threshold";
                break;
            case 'service':
                $where[] = "p.is_service = 1";
                break;
        }

        $where_sql = implode(' AND ', $where);

        // ── Sort ──
        $order = match($sort) {
            'name_asc'   => 'p.name ASC',
            'name_desc'  => 'p.name DESC',
            'price_asc'  => 'p.sell_price ASC',
            'price_desc' => 'p.sell_price DESC',
            'stock_asc'  => 'p.stock_qty ASC',
            'stock_desc' => 'p.stock_qty DESC',
            'oldest'     => 'p.created_at ASC',
            default      => 'p.created_at DESC',
        };

        // ── Count ──
        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM products p WHERE {$where_sql}");
        $stmtCount->execute($params);
        $total_items = (int) $stmtCount->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // ── Fetch page ──
        $stmtProducts = $this->db->prepare("
            SELECT p.*
            FROM products p
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtProducts->execute($params);
        $products = $stmtProducts->fetchAll();

        // ── Summary stats (unfiltered, for the business) ──
        $stats = $this->db->prepare("
            SELECT
                COUNT(*)                                                          AS total_products,
                SUM(CASE WHEN is_service = 0 THEN stock_qty * cost_price ELSE 0 END) AS total_stock_value,
                SUM(CASE WHEN is_service = 0 AND stock_qty <= low_stock_threshold AND stock_qty > 0 THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(CASE WHEN is_service = 0 AND stock_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                SUM(CASE WHEN is_service = 1 THEN 1 ELSE 0 END)                  AS services_count
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL
        ");
        $stats->execute([$this->biz_id]);
        $summary = $stats->fetch();

        // ── Categories for filter dropdown ──
        $stmtCats = $this->db->prepare("
            SELECT DISTINCT category FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND category IS NOT NULL AND category != ''
            ORDER BY category ASC
        ");
        $stmtCats->execute([$this->biz_id]);
        $categories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

        $title     = ($business['business_type'] ?? '') === 'service' ? 'Services' : 'Stock';
        $view_file = BASE_PATH . '/views/products/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  CREATE — new product or service
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $name        = trim($_POST['name'] ?? '');
            $sku         = trim($_POST['sku'] ?? '');
            $auto_sku    = !empty($_POST['auto_sku']);
            $category    = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sell_price  = (float)($_POST['sell_price'] ?? 0);
            $cost_price  = (float)($_POST['cost_price'] ?? 0);
            $stock_qty   = (float)($_POST['stock_qty'] ?? 0);
            $threshold   = (float)($_POST['low_stock_threshold'] ?? 5);
            $unit        = trim($_POST['unit'] ?? 'pcs');
            $is_service  = !empty($_POST['is_service']) ? 1 : 0;

            // Validation
            $errors = [];
            if ($name === '') $errors[] = 'Product name is required.';
            if ($sell_price < 0) $errors[] = 'Selling price cannot be negative.';
            if ($cost_price < 0) $errors[] = 'Cost price cannot be negative.';
            if (!$is_service && $stock_qty < 0) $errors[] = 'Stock quantity cannot be negative.';

            // Check duplicate name within business
            $dup = $this->db->prepare("SELECT id FROM products WHERE business_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1");
            $dup->execute([$this->biz_id, $name]);
            if ($dup->fetch()) $errors[] = 'A product with this name already exists.';

            if (!empty($errors)) {
                flash('error', implode(' ', $errors));
                // Re-render form with old input
                $product   = $_POST;
                $editing   = false;
                $categories_list = $this->get_categories();
                $title     = "Add Product";
                $view_file = BASE_PATH . '/views/products/form.php';
                require_once BASE_PATH . '/views/layouts/app.php';
                return;
            }

            // Insert
            $stmt = $this->db->prepare("
                INSERT INTO products (business_id, name, sku, category, description, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->biz_id, $name, '', $category ?: null, $description ?: null,
                $sell_price, $cost_price,
                $is_service ? 0 : $stock_qty,
                $threshold, $unit, $is_service
            ]);
            $product_id = (int)$this->db->lastInsertId();

            // Auto-SKU: first 3 chars of name (uppercase) + zero-padded ID
            if ($auto_sku || $sku === '') {
                $sku = $this->generate_sku($name, $product_id);
            }
            $this->db->prepare("UPDATE products SET sku = ? WHERE id = ? AND business_id = ?")
                      ->execute([$sku, $product_id, $this->biz_id]);

            // Handle image uploads (multi-image)
            $this->handle_product_images($product_id, $product_id);

            // Handle variant groups
            $this->save_variant_groups($product_id);

            // How people book it, if they do
            $this->save_booking_fields($product_id);

            // Log initial stock as stock_in movement (only for physical products)
            if (!$is_service && $stock_qty > 0) {
                $this->log_movement($product_id, 'stock_in', $stock_qty, 0, $stock_qty, $cost_price, 'Initial stock on product creation');
            }

            flash('success', "{$name} added to inventory.");
            redirect('products/view/' . $product_id);
        }

        // GET — show empty form
        $product   = [];
        $editing   = false;
        $categories_list = $this->get_categories();
        $product_images = [];
        $variant_groups = [];
        $title     = "Add Product";
        $view_file = BASE_PATH . '/views/products/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT — update product or service
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $product = $this->find_product($id);
        if (!$product) { not_found(); return; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $name        = trim($_POST['name'] ?? '');
            $sku         = trim($_POST['sku'] ?? '');
            $category    = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sell_price  = (float)($_POST['sell_price'] ?? 0);
            $cost_price  = (float)($_POST['cost_price'] ?? 0);
            $threshold   = (float)($_POST['low_stock_threshold'] ?? 5);
            $unit        = trim($_POST['unit'] ?? 'pcs');
            $is_service  = !empty($_POST['is_service']) ? 1 : 0;

            $errors = [];
            if ($name === '') $errors[] = 'Product name is required.';
            if ($sell_price < 0) $errors[] = 'Selling price cannot be negative.';

            // Duplicate name check (excluding self)
            $dup = $this->db->prepare("SELECT id FROM products WHERE business_id = ? AND name = ? AND deleted_at IS NULL AND id != ? LIMIT 1");
            $dup->execute([$this->biz_id, $name, $id]);
            if ($dup->fetch()) $errors[] = 'Another product with this name already exists.';

            if (!empty($errors)) {
                flash('error', implode(' ', $errors));
                $product   = array_merge($product, $_POST);
                $editing   = true;
                $categories_list = $this->get_categories();
                $title     = "Edit — " . e($product['name']);
                $view_file = BASE_PATH . '/views/products/form.php';
                require_once BASE_PATH . '/views/layouts/app.php';
                return;
            }

            // If switching from physical to service, set stock to 0
            $was_service = (int)$product['is_service'];
            $new_stock   = $is_service ? 0 : (float)$product['stock_qty'];

            $stmt = $this->db->prepare("
                UPDATE products SET
                    name = ?, sku = ?, category = ?, description = ?,
                    sell_price = ?, cost_price = ?,
                    low_stock_threshold = ?, unit = ?, is_service = ?,
                    stock_qty = ?
                WHERE id = ? AND business_id = ?
            ");
            $stmt->execute([
                $name, $sku, $category ?: null, $description ?: null,
                $sell_price, $cost_price,
                $threshold, $unit, $is_service,
                $new_stock,
                $id, $this->biz_id
            ]);

            // Handle image uploads (multi-image)
            $this->handle_product_images((int)$id, (int)$id);

            // Handle variant groups
            $this->save_variant_groups((int)$id);

            // How people book it, if they do
            $this->save_booking_fields((int)$id);

            flash('success', "{$name} updated.");
            redirect('products/view/' . $id);
        }

        // GET — show form with existing data
        $editing   = true;
        $categories_list = $this->get_categories();
        $product_images = $this->get_product_images((int)$id);
        $variant_groups = $this->get_variant_groups((int)$id);
        $title     = "Edit — " . e($product['name']);
        $view_file = BASE_PATH . '/views/products/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — product detail + stock movements
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $product = $this->find_product($id);
        if (!$product) { not_found(); return; }

        // Stock movements (latest 50)
        $move_filter = trim($_GET['move_type'] ?? '');
        $move_where  = "sm.product_id = ? AND sm.business_id = ?";
        $move_params = [$id, $this->biz_id];

        if ($move_filter !== '' && in_array($move_filter, ['stock_in','stock_out','adjustment','sale','return'])) {
            $move_where   .= " AND sm.type = ?";
            $move_params[] = $move_filter;
        }

        $stmtMoves = $this->db->prepare("
            SELECT sm.*, u.full_name as user_name
            FROM stock_movements sm
            LEFT JOIN users u ON u.id = sm.user_id
            WHERE {$move_where}
            ORDER BY sm.created_at DESC
            LIMIT 50
        ");
        $stmtMoves->execute($move_params);
        $movements = $stmtMoves->fetchAll();

        // Movement summary counts
        $stmtMoveSummary = $this->db->prepare("
            SELECT type, COUNT(*) as cnt, SUM(quantity) as total_qty
            FROM stock_movements
            WHERE product_id = ? AND business_id = ?
            GROUP BY type
        ");
        $stmtMoveSummary->execute([$id, $this->biz_id]);
        $move_summary = [];
        foreach ($stmtMoveSummary->fetchAll() as $row) {
            $move_summary[$row['type']] = $row;
        }

        // Margin calculation
        $margin_pct = $product['sell_price'] > 0
            ? round(($product['sell_price'] - $product['cost_price']) / $product['sell_price'] * 100, 1)
            : 0;
        $stock_value = (float)$product['stock_qty'] * (float)$product['cost_price'];

        $title     = e($product['name']);
        $view_file = BASE_PATH . '/views/products/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — soft delete
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('products'); return; }
        csrf_verify();

        $product = $this->find_product($id);
        if (!$product) { not_found(); return; }

        $this->db->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', e($product['name']) . " removed from inventory.");
        redirect('products');
    }

    /* ──────────────────────────────────────────
     *  ADJUST STOCK — manual in/out/correction
     * ────────────────────────────────────────── */
    public function adjust_stock($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('products'); return; }
        csrf_verify();

        $product = $this->find_product($id);
        if (!$product || $product['is_service']) {
            flash('error', 'Cannot adjust stock for this item.');
            redirect('products');
            return;
        }

        $adj_type  = trim($_POST['adj_type'] ?? '');   // stock_in | stock_out | adjustment
        $quantity  = abs((float)($_POST['quantity'] ?? 0));
        $unit_cost = (float)($_POST['unit_cost'] ?? $product['cost_price']);
        $note      = trim($_POST['note'] ?? '');

        if ($quantity <= 0) {
            flash('error', 'Quantity must be greater than zero.');
            redirect('products/view/' . $id);
            return;
        }
        if (!in_array($adj_type, ['stock_in', 'stock_out', 'adjustment'])) {
            flash('error', 'Invalid adjustment type.');
            redirect('products/view/' . $id);
            return;
        }

        $stock_before = (float)$product['stock_qty'];

        if ($adj_type === 'stock_in') {
            $stock_after = $stock_before + $quantity;
        } elseif ($adj_type === 'stock_out') {
            $stock_after = max(0, $stock_before - $quantity);
        } else {
            // Adjustment: the quantity IS the new absolute stock level
            $stock_after = $quantity;
            $quantity    = abs($stock_after - $stock_before);
        }

        // Update product stock
        $this->db->prepare("UPDATE products SET stock_qty = ? WHERE id = ? AND business_id = ?")
                  ->execute([$stock_after, $id, $this->biz_id]);

        // Log movement
        $this->log_movement($id, $adj_type, $quantity, $stock_before, $stock_after, $unit_cost, $note ?: 'Manual adjustment');

        $direction = $adj_type === 'stock_in' ? 'added to' : ($adj_type === 'stock_out' ? 'removed from' : 'adjusted for');
        flash('success', "{$quantity} units {$direction} " . e($product['name']) . ". New stock: {$stock_after}");
        redirect('products/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  TOGGLE STATUS — AJAX active/inactive
     * ────────────────────────────────────────── */
    public function toggle_status($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        csrf_verify();

        $product = $this->find_product($id);
        if (!$product) { json_response(['error' => 'Not found'], 404); }

        $new_status = $product['is_active'] ? 0 : 1;
        $this->db->prepare("UPDATE products SET is_active = ? WHERE id = ? AND business_id = ?")
                  ->execute([$new_status, $id, $this->biz_id]);

        json_response(['status' => $new_status ? 'active' : 'inactive']);
    }

    /* ──────────────────────────────────────────
     *  SETUP — inventory configuration page
     * ────────────────────────────────────────── */
    public function setup() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = trim($_POST['setup_action'] ?? '');

            if ($action === 'quick_add') {
                // Quick-add a product from setup page
                $name       = trim($_POST['name'] ?? '');
                $category   = trim($_POST['category'] ?? '');
                $sell_price = (float)($_POST['sell_price'] ?? 0);
                $cost_price = (float)($_POST['cost_price'] ?? 0);
                $stock_qty  = (float)($_POST['stock_qty'] ?? 0);
                $unit       = trim($_POST['unit'] ?? 'pcs');
                $is_service = !empty($_POST['is_service']) ? 1 : 0;

                if ($name !== '') {
                    $stmt = $this->db->prepare("
                        INSERT INTO products (business_id, name, sku, category, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service)
                        VALUES (?, ?, '', ?, ?, ?, ?, 5, ?, ?)
                    ");
                    $stmt->execute([$this->biz_id, $name, $category ?: null, $sell_price, $cost_price, $is_service ? 0 : $stock_qty, $unit, $is_service]);
                    $pid = (int)$this->db->lastInsertId();
                    $sku = $this->generate_sku($name, $pid);
                    $this->db->prepare("UPDATE products SET sku = ? WHERE id = ?")->execute([$sku, $pid]);

                    if (!$is_service && $stock_qty > 0) {
                        $this->log_movement($pid, 'stock_in', $stock_qty, 0, $stock_qty, $cost_price, 'Initial stock via setup');
                    }
                    flash('success', "{$name} added.");
                }
                redirect('products/setup');
                return;
            }
        }

        // Existing categories
        $categories_list = $this->get_categories();

        // Recently added products (for the setup progress feel)
        $stmtRecent = $this->db->prepare("
            SELECT id, name, sku, category, sell_price, stock_qty, is_service
            FROM products WHERE business_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmtRecent->execute([$this->biz_id]);
        $recent_products = $stmtRecent->fetchAll();

        // Product count
        $stmtCnt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE business_id = ? AND deleted_at IS NULL");
        $stmtCnt->execute([$this->biz_id]);
        $product_count = (int)$stmtCnt->fetchColumn();

        $title     = "Inventory Setup";
        $view_file = BASE_PATH . '/views/products/setup.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EXPORT — CSV download
     * ────────────────────────────────────────── */
    public function export() {
        $stmt = $this->db->prepare("
            SELECT sku, name, category, description, sell_price, cost_price, stock_qty, low_stock_threshold, unit,
                   CASE WHEN is_service = 1 THEN 'Yes' ELSE 'No' END as is_service,
                   CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END as status
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute([$this->biz_id]);
        $rows = $stmt->fetchAll();

        $business = auth_business();
        $filename = slugify($business['name']) . '-inventory-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $out = fopen('php://output', 'w');
        fputcsv($out, ['SKU', 'Name', 'Category', 'Description', 'Sell Price', 'Cost Price', 'Stock Qty', 'Low Stock Threshold', 'Unit', 'Is Service', 'Status']);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    /* ──────────────────────────────────────────
     *  IMPORT — CSV bulk upload
     * ────────────────────────────────────────── */
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('products/setup'); return; }
        csrf_verify();

        if (empty($_FILES['csv_file']['tmp_name'])) {
            flash('error', 'Please upload a CSV file.');
            redirect('products/setup');
            return;
        }

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$file) {
            flash('error', 'Could not read uploaded file.');
            redirect('products/setup');
            return;
        }

        $header = fgetcsv($file);
        if (!$header) {
            flash('error', 'CSV file is empty.');
            fclose($file);
            redirect('products/setup');
            return;
        }

        // Normalize headers to lowercase
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Map columns
        $col = [
            'name'       => array_search('name', $header),
            'sku'        => array_search('sku', $header),
            'category'   => array_search('category', $header),
            'description'=> array_search('description', $header),
            'sell_price' => array_search('sell price', $header) !== false ? array_search('sell price', $header) : array_search('sell_price', $header),
            'cost_price' => array_search('cost price', $header) !== false ? array_search('cost price', $header) : array_search('cost_price', $header),
            'stock_qty'  => array_search('stock qty', $header) !== false ? array_search('stock qty', $header) : array_search('stock_qty', $header),
            'unit'       => array_search('unit', $header),
            'is_service' => array_search('is service', $header) !== false ? array_search('is service', $header) : array_search('is_service', $header),
        ];

        if ($col['name'] === false) {
            flash('error', 'CSV must have a "Name" column.');
            fclose($file);
            redirect('products/setup');
            return;
        }

        $imported = 0;
        $skipped  = 0;
        $user_id  = auth_user()['id'];

        $this->db->beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== false) {
                $name = trim($row[$col['name']] ?? '');
                if ($name === '') { $skipped++; continue; }

                // Check duplicate
                $dup = $this->db->prepare("SELECT id FROM products WHERE business_id = ? AND name = ? AND deleted_at IS NULL");
                $dup->execute([$this->biz_id, $name]);
                if ($dup->fetch()) { $skipped++; continue; }

                $sell  = $col['sell_price'] !== false ? (float)($row[$col['sell_price']] ?? 0) : 0;
                $cost  = $col['cost_price'] !== false ? (float)($row[$col['cost_price']] ?? 0) : 0;
                $stock = $col['stock_qty'] !== false  ? (float)($row[$col['stock_qty']] ?? 0)  : 0;
                $unit  = $col['unit'] !== false        ? trim($row[$col['unit']] ?? 'pcs')      : 'pcs';
                $cat   = $col['category'] !== false    ? trim($row[$col['category']] ?? '')      : '';
                $desc  = $col['description'] !== false ? trim($row[$col['description']] ?? '')   : '';
                $svc_raw = $col['is_service'] !== false ? strtolower(trim($row[$col['is_service']] ?? '')) : '';
                $is_svc  = in_array($svc_raw, ['yes', '1', 'true', 'service']) ? 1 : 0;

                $stmt = $this->db->prepare("
                    INSERT INTO products (business_id, name, sku, category, description, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service)
                    VALUES (?, ?, '', ?, ?, ?, ?, ?, 5, ?, ?)
                ");
                $stmt->execute([$this->biz_id, $name, $cat ?: null, $desc ?: null, $sell, $cost, $is_svc ? 0 : $stock, $unit, $is_svc]);
                $pid = (int)$this->db->lastInsertId();

                // Generate SKU
                $sku_val = $col['sku'] !== false ? trim($row[$col['sku']] ?? '') : '';
                if ($sku_val === '') $sku_val = $this->generate_sku($name, $pid);
                $this->db->prepare("UPDATE products SET sku = ? WHERE id = ?")->execute([$sku_val, $pid]);

                // Log initial stock
                if (!$is_svc && $stock > 0) {
                    $this->log_movement($pid, 'stock_in', $stock, 0, $stock, $cost, 'CSV import');
                }

                $imported++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Import failed: ' . $e->getMessage());
            fclose($file);
            redirect('products/setup');
            return;
        }

        fclose($file);
        flash('success', "{$imported} products imported. {$skipped} skipped (duplicates or empty rows).");
        redirect('products/setup');
    }

    /* ──────────────────────────────────────────
     *  DUPLICATE — clone a product
     * ────────────────────────────────────────── */
    public function duplicate($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('products'); return; }
        csrf_verify();

        $product = $this->find_product($id);
        if (!$product) { not_found(); return; }

        $stmt = $this->db->prepare("
            INSERT INTO products (business_id, name, sku, category, description, sell_price, cost_price, stock_qty, low_stock_threshold, unit, is_service)
            VALUES (?, ?, '', ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $stmt->execute([
            $this->biz_id,
            $product['name'] . ' (Copy)',
            $product['category'],
            $product['description'],
            $product['sell_price'],
            $product['cost_price'],
            $product['low_stock_threshold'],
            $product['unit'],
            $product['is_service']
        ]);
        $new_id = (int)$this->db->lastInsertId();
        $sku = $this->generate_sku($product['name'] . ' Copy', $new_id);
        $this->db->prepare("UPDATE products SET sku = ? WHERE id = ?")->execute([$sku, $new_id]);

        flash('success', 'Product duplicated. Stock set to 0 — adjust as needed.');
        redirect('products/edit/' . $new_id);
    }

    /* ──────────────────────────────────────────
     *  AJAX — Delete a product image
     * ────────────────────────────────────────── */
    public function delete_image($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        csrf_verify();

        $image_id = (int)($id ?: ($_POST['image_id'] ?? 0));
        if (!$image_id) { json_response(['error' => 'Missing image ID'], 400); }

        $stmt = $this->db->prepare("SELECT * FROM product_images WHERE id = ? AND business_id = ?");
        $stmt->execute([$image_id, $this->biz_id]);
        $img = $stmt->fetch();
        if (!$img) { json_response(['error' => 'Image not found'], 404); }

        // Delete file from disk
        $full_path = BASE_PATH . '/' . $img['image_path'];
        if (file_exists($full_path)) @unlink($full_path);

        // Delete DB record
        $this->db->prepare("DELETE FROM product_images WHERE id = ? AND business_id = ?")->execute([$image_id, $this->biz_id]);

        // If this was the cover, promote next image or clear products.image_path
        if ($img['is_cover']) {
            $next = $this->db->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? AND business_id = ? ORDER BY sort_order ASC LIMIT 1");
            $next->execute([$img['product_id'], $this->biz_id]);
            $next_img = $next->fetch();
            if ($next_img) {
                $this->db->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ?")->execute([$next_img['id']]);
                $this->db->prepare("UPDATE products SET image_path = ? WHERE id = ? AND business_id = ?")
                          ->execute([$next_img['image_path'], $img['product_id'], $this->biz_id]);
            } else {
                $this->db->prepare("UPDATE products SET image_path = NULL WHERE id = ? AND business_id = ?")
                          ->execute([$img['product_id'], $this->biz_id]);
            }
        }

        json_response(['success' => true]);
    }

    /* ──────────────────────────────────────────
     *  AJAX — Set cover image
     * ────────────────────────────────────────── */
    public function set_cover($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        csrf_verify();

        $image_id = (int)($id ?: ($_POST['image_id'] ?? 0));
        $stmt = $this->db->prepare("SELECT * FROM product_images WHERE id = ? AND business_id = ?");
        $stmt->execute([$image_id, $this->biz_id]);
        $img = $stmt->fetch();
        if (!$img) { json_response(['error' => 'Image not found'], 404); }

        // Unset all covers for this product, then set the new one
        $this->db->prepare("UPDATE product_images SET is_cover = 0 WHERE product_id = ? AND business_id = ?")
                  ->execute([$img['product_id'], $this->biz_id]);
        $this->db->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ?")->execute([$image_id]);
        $this->db->prepare("UPDATE products SET image_path = ? WHERE id = ? AND business_id = ?")
                  ->execute([$img['image_path'], $img['product_id'], $this->biz_id]);

        json_response(['success' => true]);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_product($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    private function generate_sku(string $name, int $id): string {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        if (strlen($prefix) < 3) $prefix = str_pad($prefix, 3, 'X');
        return $prefix . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    }

    private function log_movement(int $product_id, string $type, float $quantity, float $before, float $after, float $unit_cost, string $note, ?string $ref_type = null, ?int $ref_id = null): void {
        $this->db->prepare("
            INSERT INTO stock_movements (business_id, product_id, user_id, type, quantity, stock_before, stock_after, unit_cost, reference_type, reference_id, note, movement_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ")->execute([
            $this->biz_id, $product_id, auth_user()['id'],
            $type, $quantity, $before, $after, $unit_cost,
            $ref_type, $ref_id, $note
        ]);
    }

    private function get_categories(): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT category FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND category IS NOT NULL AND category != ''
            ORDER BY category ASC
        ");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ── Multi-Image Helpers ── */

    private function handle_product_images(int $product_id, int $file_id): void {
        if (empty($_FILES['images']['tmp_name'][0])) return;

        $dest_dir = UPLOAD_PATH . '/products/' . $this->biz_id;
        if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

        // Check current max sort order
        $stmtMax = $this->db->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ? AND business_id = ?");
        $stmtMax->execute([$product_id, $this->biz_id]);
        $max_sort = (int)$stmtMax->fetchColumn();

        // Check if product already has a cover
        $stmtCover = $this->db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND business_id = ? AND is_cover = 1");
        $stmtCover->execute([$product_id, $this->biz_id]);
        $has_cover = (int)$stmtCover->fetchColumn() > 0;

        $files = $_FILES['images'];
        for ($i = 0; $i < count($files['tmp_name']); $i++) {
            if (empty($files['tmp_name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $max_sort++;
            $is_cover = (!$has_cover && $i === 0) ? 1 : 0;
            $filename = $product_id . '_' . time() . '_' . $i . '.png';
            $dest = $dest_dir . '/' . $filename;
            $rel = 'uploads/products/' . $this->biz_id . '/' . $filename;

            $file_arr = [
                'tmp_name' => $files['tmp_name'][$i],
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'size'     => $files['size'][$i],
                'error'    => $files['error'][$i],
            ];

            if (save_image($file_arr, $dest, 600)) {
                $this->db->prepare("
                    INSERT INTO product_images (product_id, business_id, image_path, sort_order, is_cover)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$product_id, $this->biz_id, $rel, $max_sort, $is_cover]);

                // Keep products.image_path in sync with cover
                if ($is_cover) {
                    $this->db->prepare("UPDATE products SET image_path = ? WHERE id = ? AND business_id = ?")
                              ->execute([$rel, $product_id, $this->biz_id]);
                    $has_cover = true;
                }
            }
        }
    }

    private function get_product_images(int $product_id): array {
        $stmt = $this->db->prepare("
            SELECT * FROM product_images
            WHERE product_id = ? AND business_id = ?
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$product_id, $this->biz_id]);
        return $stmt->fetchAll();
    }

    /* ── Variant Group Helpers ── */

    /**
     * Booking fields from the form: how it is booked, how long, how many a
     * day, and the deposit. Anything unknown means "people buy it".
     */
    private function save_booking_fields(int $product_id): void {
        $mode = in_array($_POST['booking_mode'] ?? 'none', BOOKING_MODES, true) ? $_POST['booking_mode'] : 'none';
        $type = in_array($_POST['deposit_type'] ?? 'none', ['none', 'percent', 'fixed'], true) ? $_POST['deposit_type'] : 'none';
        $value = max(0, (float)($_POST['deposit_value'] ?? 0));
        if ($type === 'percent') {
            $value = min(100, $value);
        }
        $this->db->prepare("
            UPDATE products SET booking_mode = ?, booking_minutes = ?, booking_capacity = ?, deposit_type = ?, deposit_value = ?
            WHERE id = ? AND business_id = ?
        ")->execute([
            $mode,
            $mode === 'slot' ? max(15, min(720, (int)($_POST['booking_minutes'] ?? 60))) : null,
            max(1, min(20, (int)($_POST['booking_capacity'] ?? 1))),
            $mode === 'none' || $value <= 0 ? 'none' : $type,
            $mode === 'none' || $type === 'none' || $value <= 0 ? null : $value,
            $product_id, $this->biz_id,
        ]);
    }

    private function save_variant_groups(int $product_id): void {
        $group_names = $_POST['variant_group_name'] ?? [];
        $group_options = $_POST['variant_group_options'] ?? [];

        if (empty($group_names)) return;

        // Delete existing variant groups (cascade deletes options)
        $this->db->prepare("DELETE FROM product_variant_groups WHERE product_id = ? AND business_id = ?")
                  ->execute([$product_id, $this->biz_id]);

        for ($i = 0; $i < count($group_names); $i++) {
            $name = trim($group_names[$i] ?? '');
            $opts_str = trim($group_options[$i] ?? '');
            if ($name === '') continue;

            $this->db->prepare("
                INSERT INTO product_variant_groups (product_id, business_id, name, sort_order)
                VALUES (?, ?, ?, ?)
            ")->execute([$product_id, $this->biz_id, $name, $i]);
            $group_id = (int)$this->db->lastInsertId();

            // Parse comma-separated options
            $opts = array_filter(array_map('trim', explode(',', $opts_str)));
            foreach ($opts as $j => $label) {
                if ($label === '') continue;
                $this->db->prepare("
                    INSERT INTO product_variant_options (variant_group_id, label, sort_order)
                    VALUES (?, ?, ?)
                ")->execute([$group_id, $label, $j]);
            }
        }
    }

    private function get_variant_groups(int $product_id): array {
        $stmt = $this->db->prepare("
            SELECT pvg.*, GROUP_CONCAT(pvo.label ORDER BY pvo.sort_order SEPARATOR ', ') as options_str
            FROM product_variant_groups pvg
            LEFT JOIN product_variant_options pvo ON pvo.variant_group_id = pvg.id
            WHERE pvg.product_id = ? AND pvg.business_id = ?
            GROUP BY pvg.id
            ORDER BY pvg.sort_order ASC
        ");
        $stmt->execute([$product_id, $this->biz_id]);
        return $stmt->fetchAll();
    }
}