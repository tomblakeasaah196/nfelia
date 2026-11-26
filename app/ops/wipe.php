<?php
/**
 * A fresh start for a client (Ops > Clients > Fresh start, Admins only):
 * wipe what they pick of a business's records, after the Admin's password,
 * "WIPE" and the shop's link typed out, and a reason, with the client's
 * written request when there is one. The owner is emailed.
 *
 * Before anything goes it is copied: a zip only Admins can download for
 * WIPE_KEEP_DAYS, with restore.sql (phpMyAdmin > Import puts it all back)
 * and the files that went with the rows. Copy and wipe are one transaction,
 * so a wipe that can't be copied doesn't happen.
 *
 * Always stays: their sign-in and team, their Nfelia plan and its payments,
 * the shop's link, look and posts, bank accounts, and Records Books.
 */

const WIPE_KEEP_DAYS = 30;

/** What can be picked, in the order it goes. 'needs': only together with that part. */
const WIPE_PARTS = [
    'sales'     => ['label' => 'Sales and money',
                    'hint'  => 'Orders, bookings, quote requests, quotes, invoices, payments, receipts, deliveries, deeds, refunds, money kept, debts and expenses, with their reviews, signatures and history, the tasks tied to them, and the notification bell. Numbers start again at 001.'],
    'tasks'     => ['label' => 'All tasks',
                    'hint'  => 'Every task, job and appointment, not only the ones tied to sales.'],
    'stock'     => ['label' => 'Stock',
                    'hint'  => 'All stock history, and every product goes to 0.'],
    'products'  => ['label' => 'Products and services',
                    'hint'  => 'With their photos, options and stock history. Shop sections that listed them are emptied.'],
    'customers' => ['label' => 'Customers', 'needs' => 'sales',
                    'hint'  => 'Every customer. Only with Sales and money, which they appear on.'],
    'suppliers' => ['label' => 'Suppliers', 'needs' => 'sales',
                    'hint'  => 'Every supplier. Only with Sales and money, which they appear on.'],
];

/** How the rows people would recognise are named in counts, in the order they are told; lines and history are "more records". */
const WIPE_WHAT = [
    'orders' => 'orders', 'bookings' => 'bookings', 'quote_requests' => 'quote requests', 'quotations' => 'quotes', 'invoices' => 'invoices',
    'payments' => 'payments', 'receipts' => 'receipts', 'deliveries' => 'deliveries', 'delivery_notes' => 'deliveries', 'deeds' => 'deeds',
    'refunds' => 'refunds', 'debts' => 'debts', 'expenses' => 'expenses', 'tasks' => 'tasks', 'products' => 'products',
    'product_images' => 'photos', 'stock_movements' => 'stock moves', 'customers' => 'customers', 'suppliers' => 'suppliers',
];

/** Files the wiped rows own: copied, then deleted once nothing else uses them. */
const WIPE_FILES = [
    'orders' => ['payment_proof_path'], 'bookings' => ['payment_proof_path'], 'refunds' => ['proof_path'],
    'deliveries' => ['signoff_signature_path'], 'delivery_notes' => ['signoff_signature_path'], 'doc_signatures' => ['image_path'],
    'products' => ['image_path'], 'product_images' => ['image_path'],
];

/** The words to type to confirm: WIPE and the shop's link (the name in its address while it has no link). */
function wipe_phrase(array $business): string
{
    return 'WIPE ' . ($business['subdomain'] ?: $business['slug']);
}

/** Typed as shown? Spaces and capitals don't matter. */
function wipe_phrase_ok(string $typed, array $business): bool
{
    $plain = fn(string $s) => strtoupper((string)preg_replace('/\s+/', ' ', trim($s)));
    return $plain($typed) === $plain(wipe_phrase($business));
}

/** The parts picked, in the order they go; anything unknown is left out. */
function wipe_parts_clean(array $parts): array
{
    return array_values(array_intersect(array_keys(WIPE_PARTS), array_map('strval', $parts)));
}

/** What is wrong with a pick of parts, or null. */
function wipe_parts_problem(array $parts): ?string
{
    if (!$parts) {
        return 'Tick at least one thing to wipe.';
    }
    foreach ($parts as $part) {
        $needs = WIPE_PARTS[$part]['needs'] ?? null;
        if ($needs && !in_array($needs, $parts, true)) {
            return WIPE_PARTS[$part]['label'] . ' can only go with ' . WIPE_PARTS[$needs]['label'] . ', which they appear on.';
        }
    }
    return null;
}

/** "sales and money", "sales and money and stock", "sales and money, stock, and suppliers". */
function wipe_parts_text(array $parts): string
{
    $labels = array_map(fn($p) => mb_strtolower(WIPE_PARTS[$p]['label']), wipe_parts_clean($parts));
    return match (count($labels)) {
        0 => '',
        1 => $labels[0],
        2 => $labels[0] . ' and ' . $labels[1],
        default => implode(', ', array_slice($labels, 0, -1)) . ', and ' . end($labels),
    };
}

/**
 * Every change a wipe makes, in order. A 'delete' step takes the rows of
 * `table` x (joined with `join`) that match `where`; an 'update' step sets
 * columns on rows that stay. Lines go before their papers (some have no
 * business_id of their own), and children before the rows they hang off, so
 * no foreign key changes anything the copy didn't see.
 */
function wipe_steps(int $b, array $parts): array
{
    $has   = fn(string $p) => in_array($p, $parts, true);
    $steps = [];
    $del = function (string $part, string $table, string $where, string $join = '') use (&$steps) {
        $steps[] = ['do' => 'delete', 'part' => $part, 'table' => $table, 'join' => $join, 'where' => $where];
    };
    $set = function (string $part, string $table, string $where, array $set) use (&$steps) {
        $steps[] = ['do' => 'update', 'part' => $part, 'table' => $table, 'join' => '', 'where' => $where, 'set' => $set];
    };

    if ($has('sales')) {
        $del('sales', 'order_items',      "o.business_id = {$b}", 'JOIN orders o ON o.id = x.order_id');
        $del('sales', 'invoice_items',    "i.business_id = {$b}", 'JOIN invoices i ON i.id = x.invoice_id');
        $del('sales', 'quotation_items',  "q.business_id = {$b}", 'JOIN quotations q ON q.id = x.quotation_id');
        $del('sales', 'receipt_items',    "r.business_id = {$b}", 'JOIN receipts r ON r.id = x.receipt_id');
        $del('sales', 'delivery_items',   "d.business_id = {$b}", 'JOIN deliveries d ON d.id = x.delivery_id');
        $del('sales', 'delivery_items',   "n.business_id = {$b} AND x.delivery_id IS NULL", 'JOIN delivery_notes n ON n.id = x.delivery_note_id');
        $del('sales', 'expense_items',    "e.business_id = {$b}", 'JOIN expenses e ON e.id = x.expense_id');
        $del('sales', 'debt_settlements', "(x.business_id = {$b} OR x.debt_id IN (SELECT id FROM debts WHERE business_id = {$b}))");
        if (!$has('tasks')) {
            // Tasks tied to an order, an invoice or a booking; a job's steps go with their job
            $tied = fn(string $t) => "({$t}.order_id IS NOT NULL OR {$t}.invoice_id IS NOT NULL"
                                   . " OR {$t}.id IN (SELECT task_id FROM bookings WHERE business_id = {$b} AND task_id IS NOT NULL))";
            $del('sales', 'tasks', "x.business_id = {$b} AND " . $tied('p') . ' AND NOT ' . $tied('x'), 'JOIN tasks p ON p.id = x.parent_id');
            $del('sales', 'tasks', "x.business_id = {$b} AND " . $tied('x'));
        }
        foreach (['doc_signatures', 'doc_codes', 'record_requests', 'reviews', 'order_events', 'refunds', 'kept_money', 'debts', 'payments',
                  'receipts', 'deeds', 'deliveries', 'delivery_notes', 'bookings', 'orders', 'invoices', 'quotation_activities', 'quotations',
                  'quote_requests', 'expenses', 'notifications'] as $table) {
            $del('sales', $table, "x.business_id = {$b}");
        }
        $del('sales', 'notify_outbox', "x.business_id = {$b} AND x.status = 'pending'");
        // Numbers start again at 001
        $set('sales', 'document_counters', "x.business_id = {$b} AND x.last_number <> 0", ['last_number' => 0]);
        $set('sales', 'businesses', "x.id = {$b} AND (COALESCE(x.order_number_seq, 0) <> 0 OR COALESCE(x.booking_number_seq, 0) <> 0)",
             ['order_number_seq' => 0, 'booking_number_seq' => 0]);
        // Contacts that stay are owed, and owe, nothing now
        if (!$has('customers')) {
            $set('sales', 'customers', "x.business_id = {$b} AND (x.total_invoiced <> 0 OR x.total_received <> 0 OR x.outstanding_balance <> 0)",
                 ['total_invoiced' => 0, 'total_received' => 0, 'outstanding_balance' => 0]);
        }
        if (!$has('suppliers')) {
            $set('sales', 'suppliers', "x.business_id = {$b} AND (x.total_purchased <> 0 OR x.total_paid <> 0 OR x.outstanding_balance <> 0)",
                 ['total_purchased' => 0, 'total_paid' => 0, 'outstanding_balance' => 0]);
        }
    }
    if ($has('tasks')) {
        $del('tasks', 'tasks', "x.business_id = {$b}");
    }
    if ($has('stock')) {
        $del('stock', 'stock_movements', "x.business_id = {$b}");
        $set('stock', 'products', "x.business_id = {$b} AND x.stock_qty <> 0", ['stock_qty' => 0]);
    }
    if ($has('products')) {
        if (!$has('sales')) {
            // Papers that stay keep their lines, without the link to a product that is gone
            foreach (['order_items', 'invoice_items', 'quotation_items', 'receipt_items', 'delivery_items', 'expense_items', 'bookings'] as $table) {
                $set('products', $table, "x.product_id IN (SELECT id FROM products WHERE business_id = {$b})", ['product_id' => null]);
            }
        }
        $del('products', 'product_variant_options', "g.business_id = {$b}", 'JOIN product_variant_groups g ON g.id = x.variant_group_id');
        foreach (['product_variant_groups', 'product_images', 'stock_movements', 'products'] as $table) {
            $del('products', $table, "x.business_id = {$b}");
        }
    }
    if ($has('customers')) {
        if (!$has('tasks')) {
            $set('customers', 'tasks', "x.business_id = {$b} AND x.customer_id IS NOT NULL", ['customer_id' => null]);
        }
        $del('customers', 'customers', "x.business_id = {$b}");
    }
    if ($has('suppliers')) {
        $del('suppliers', 'suppliers', "x.business_id = {$b}");
    }
    return $steps;
}

/**
 * For the form: what each part would take now, each on its own (Customers
 * and Suppliers as if Sales and money went too):
 * [part => ['total' => rows, 'what' => [label => rows]]].
 */
function wipe_counts(PDO $db, int $b): array
{
    $out = [];
    foreach (array_keys(WIPE_PARTS) as $part) {
        $total = 0;
        $what  = [];
        foreach (wipe_steps($b, [$part]) as $s) {
            if ($s['do'] !== 'delete') {
                continue;
            }
            $n = (int)$db->query("SELECT COUNT(*) FROM `{$s['table']}` x {$s['join']} WHERE {$s['where']}")->fetchColumn();
            $total += $n;
            if ($n && isset(WIPE_WHAT[$s['table']])) {
                $label = WIPE_WHAT[$s['table']];
                $what[$label] = ($what[$label] ?? 0) + $n;
            }
        }
        $out[$part] = ['total' => $total, 'what' => $what];
    }
    return $out;
}

/** "12 orders, 30 invoices, 25 receipts, 4 quotes and 104 more records", from wipe_counts() or a wipe's counts. */
function wipe_what_text(array $what, int $total): string
{
    if ($total <= 0) {
        return 'Nothing to wipe';
    }
    $order = array_flip(array_values(array_unique(WIPE_WHAT)));
    uksort($what, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
    $shown = array_slice($what, 0, 4, true);
    $one   = fn(string $label) => str_ends_with($label, 'ies') ? substr($label, 0, -3) . 'y' : substr($label, 0, -1);
    $named = array_map(fn($label, $n) => number_format($n) . ' ' . ($n === 1 ? $one($label) : $label), array_keys($shown), $shown);
    $rest  = $total - array_sum($shown);
    if ($rest > 0) {
        $named[] = number_format($rest) . ($shown ? ' more' : '') . ' record' . ($rest === 1 ? '' : 's');
    }
    return count($named) > 1 ? implode(', ', array_slice($named, 0, -1)) . ' and ' . end($named) : $named[0];
}

/** A wipe's counts (per table) as [label => rows], for wipe_what_text(). */
function wipe_counts_what(array $counts): array
{
    $what = [];
    foreach ($counts as $table => $n) {
        if ($n && isset(WIPE_WHAT[$table])) {
            $what[WIPE_WHAT[$table]] = ($what[WIPE_WHAT[$table]] ?? 0) + (int)$n;
        }
    }
    return $what;
}

/**
 * Keep the client's written request (a screenshot or a PDF) where only Ops
 * can open it. Pictures are saved again as a JPEG, so nothing but the picture
 * is kept. Null when none was added.
 */
function wipe_store_proof(array $file, int $b): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        throw new DomainException('Their request didn\'t come through. Add it again, or leave it out.');
    }
    $max = max(1, (int)($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 10));
    if (filesize($file['tmp_name']) > $max * 1024 * 1024) {
        throw new DomainException('Their request is bigger than ' . $max . ' MB. Add a smaller screenshot or PDF.');
    }
    $mime = mime_content_type($file['tmp_name']);
    $name = bin2hex(random_bytes(16));
    $dir  = private_file_dir('wipe_proofs', $b);
    $rel  = 'storage/private/wipe_proofs/' . $b . '/' . $name;
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        if (!save_image($file, $dir . '/' . $name . '.jpg', 1600, 'jpg')) {
            throw new DomainException('That picture couldn\'t be read. Add a screenshot or a PDF.');
        }
        return $rel . '.jpg';
    }
    if ($mime === 'application/pdf') {
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name . '.pdf')) {
            throw new DomainException('That PDF couldn\'t be kept. Add it again.');
        }
        return $rel . '.pdf';
    }
    throw new DomainException('Add their request as a screenshot (JPG, PNG or WebP) or a PDF.');
}

/**
 * Wipe $parts of $business, copying everything first. Returns the wipe:
 * ['id', 'counts' => [table => rows], 'total', 'expires']. Throws (and
 * changes nothing) when any of it fails.
 */
function wipe_run(PDO $db, array $business, array $parts, array $staff, string $reason, ?string $proof): array
{
    $parts = wipe_parts_clean($parts);
    if ($problem = wipe_parts_problem($parts)) {
        throw new DomainException($problem);
    }
    $b     = (int)$business['id'];
    $dir   = private_file_dir('wipes', $b);
    $tmp   = $dir . '/tmp-' . bin2hex(random_bytes(8));
    $made  = [];   // temporary files, gone at the end whatever happens
    $zip_full = null;
    $files = [];
    $counts = [];

    $db->beginTransaction();
    try {
        // One wipe of a business at a time
        $db->query("SELECT id FROM businesses WHERE id = {$b} FOR UPDATE")->fetchColumn();
        $db->prepare("INSERT INTO client_wipes (business_id, staff_id, parts, reason, proof_path) VALUES (?, ?, ?, ?, ?)")
           ->execute([$b, (int)$staff['id'], implode(',', $parts), mb_substr($reason, 0, 1000), $proof]);
        $id = (int)$db->lastInsertId();

        // Each step's undo goes in its own file; restore.sql runs them last step first
        $steps = wipe_steps($b, $parts);
        foreach ($steps as $i => $s) {
            $made[$i] = $tmp . '-' . $i . '.sql';
            $out = fopen($made[$i], 'wb');
            $from = "`{$s['table']}` x {$s['join']}";
            $cols = $s['do'] === 'delete' ? 'x.*' : 'x.id, ' . implode(', ', array_map(fn($c) => "x.`{$c}`", array_keys($s['set'])));
            $last = 0;
            do {
                $rows = $db->query("SELECT {$cols} FROM {$from} WHERE ({$s['where']}) AND x.id > {$last} ORDER BY x.id LIMIT 500 FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) {
                    break;
                }
                $last = (int)end($rows)['id'];
                fwrite($out, $s['do'] === 'delete' ? wipe_sql_insert($db, $s['table'], $rows) : wipe_sql_update($db, $s['table'], $rows));
                foreach (WIPE_FILES[$s['table']] ?? [] as $col) {
                    if ($s['do'] === 'delete') {
                        foreach ($rows as $row) {
                            if (!empty($row[$col])) $files[] = ltrim((string)$row[$col], '/');
                        }
                    }
                }
            } while (count($rows) === 500);
            fclose($out);

            if ($s['do'] === 'delete') {
                $n = $db->exec("DELETE x FROM {$from} WHERE {$s['where']}");
                $counts[$s['table']] = ($counts[$s['table']] ?? 0) + (int)$n;
            } else {
                $assign = implode(', ', array_map(fn($c, $v) => "x.`{$c}` = " . ($v === null ? 'NULL' : $db->quote((string)$v)), array_keys($s['set']), $s['set']));
                $db->exec("UPDATE {$from} SET {$assign} WHERE {$s['where']}");
            }
        }

        // Shop sections that listed products list none now (they would hide new ones)
        if (in_array('products', $parts, true)) {
            $made['shop'] = $tmp . '-shop.sql';
            $row = $db->query("SELECT id, featured_product_ids, storefront_draft, storefront_published FROM businesses WHERE id = {$b}")->fetch(PDO::FETCH_ASSOC);
            file_put_contents($made['shop'], wipe_sql_update($db, 'businesses', [$row]));
            $db->prepare("UPDATE businesses SET featured_product_ids = NULL, storefront_draft = ?, storefront_published = ? WHERE id = ?")
               ->execute([wipe_shop_without_products($row['storefront_draft']), wipe_shop_without_products($row['storefront_published']), $b]);
        }

        // The copy: restore.sql (last change undone first), the files, and how to use them
        $made['restore'] = $tmp . '-restore.sql';
        $restore = fopen($made['restore'], 'wb');
        fwrite($restore, "-- Nfelia: what Ops wiped from " . str_replace(["\r", "\n"], ' ', (string)$business['name']) . " (business {$b}) on " . date('j M Y, H:i')
                         . "\n-- Import it into the Nfelia database to put it all back (see README.txt).\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        foreach (array_reverse(array_keys($made)) as $key) {
            if ($key === 'restore') continue;
            $part = fopen($made[$key], 'rb');
            stream_copy_to_stream($part, $restore);
            fclose($part);
        }
        fwrite($restore, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($restore);

        $files = array_values(array_unique(array_filter($files, fn($f) => !str_contains($f, '..')
            && (str_starts_with($f, 'uploads/') || str_starts_with($f, 'storage/private/')))));
        $copy_rel = 'storage/private/wipes/' . $b . '/' . $id . '-' . bin2hex(random_bytes(12)) . '.zip';
        $zip_full = BASE_PATH . '/' . $copy_rel;
        $zip = new ZipArchive();
        if ($zip->open($zip_full, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The copy could not be started, so nothing was wiped.');
        }
        $zip->addFile($made['restore'], 'restore.sql');
        $zip->addFromString('README.txt', wipe_readme($business, $parts, $staff, $reason, $counts));
        foreach ($files as $rel) {
            if (is_file(BASE_PATH . '/' . $rel)) {
                $zip->addFile(BASE_PATH . '/' . $rel, 'files/' . $rel);
            }
        }
        if (!$zip->close() || !is_file($zip_full)) {
            throw new RuntimeException('The copy could not be saved, so nothing was wiped.');
        }

        $expires = date('Y-m-d H:i:s', strtotime('+' . WIPE_KEEP_DAYS . ' days'));
        $db->prepare("UPDATE client_wipes SET counts = ?, copy_path = ?, copy_size = ?, copy_sha256 = ?, copy_expires_at = ? WHERE id = ?")
           ->execute([json_encode($counts), $copy_rel, filesize($zip_full), hash_file('sha256', $zip_full), $expires, $id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($zip_full && is_file($zip_full)) {
            @unlink($zip_full);
        }
        throw $e;
    } finally {
        foreach ($made as $file) {
            @unlink($file);
        }
    }

    wipe_forget_files($db, $files);
    storefront_purge($b);   // the shop shows what is left
    return ['id' => $id, 'counts' => $counts, 'total' => array_sum($counts), 'expires' => $expires];
}

/** INSERT IGNORE statements putting $rows back into $table, 100 rows a statement. */
function wipe_sql_insert(PDO $db, string $table, array $rows): string
{
    $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
    $sql  = '';
    foreach (array_chunk($rows, 100) as $chunk) {
        $values = array_map(fn($row) => '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), $row)) . ')', $chunk);
        $sql .= "INSERT IGNORE INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $values) . ";\n";
    }
    return $sql;
}

/** UPDATE statements putting back the columns of $rows (id first) as they were. */
function wipe_sql_update(PDO $db, string $table, array $rows): string
{
    $sql = '';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        unset($row['id']);
        $assign = implode(', ', array_map(fn($c, $v) => "`{$c}` = " . ($v === null ? 'NULL' : $db->quote((string)$v)), array_keys($row), $row));
        $sql .= "UPDATE `{$table}` SET {$assign} WHERE `id` = {$id};\n";
    }
    return $sql;
}

/** A shop's settings (JSON) with every list of products emptied, as LandingPageController::keep_own_products() leaves them. */
function wipe_shop_without_products(?string $json): ?string
{
    $settings = $json !== null ? json_decode($json, true) : null;
    if (!is_array($settings) || !isset($settings['sections']) || !is_array($settings['sections'])) {
        return $json;
    }
    foreach ($settings['sections'] as &$section) {
        foreach (['products', 'only', 'picks', 'routine', 'breaks', 'guests'] as $key) {
            if (isset($section[$key])) {
                $section[$key] = [];
            }
        }
    }
    unset($section);
    return json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Delete the files of wiped rows that nothing else uses (photos with their resized copies). */
function wipe_forget_files(PDO $db, array $paths): void
{
    $used = $db->prepare("
        SELECT (SELECT COUNT(*) FROM users WHERE signature_path = ?) + (SELECT COUNT(*) FROM doc_signatures WHERE image_path = ?)
             + (SELECT COUNT(*) FROM deliveries WHERE signoff_signature_path = ?) + (SELECT COUNT(*) FROM delivery_notes WHERE signoff_signature_path = ?)
             + (SELECT COUNT(*) FROM orders WHERE payment_proof_path = ?) + (SELECT COUNT(*) FROM bookings WHERE payment_proof_path = ?)
             + (SELECT COUNT(*) FROM refunds WHERE proof_path = ?) + (SELECT COUNT(*) FROM products WHERE image_path = ?)
             + (SELECT COUNT(*) FROM product_images WHERE image_path = ?) + (SELECT COUNT(*) FROM shop_posts WHERE image_path = ?)
             + (SELECT COUNT(*) FROM businesses WHERE logo_path = ? OR hero_image_path = ?)");
    $root = realpath(BASE_PATH . '/storage/private');
    foreach ($paths as $rel) {
        try {
            $used->execute(array_fill(0, 12, $rel));
            if ((int)$used->fetchColumn()) {
                continue;
            }
            if (str_starts_with($rel, 'uploads/')) {
                storefront_forget_image($rel);
            } elseif ($root && ($full = realpath(BASE_PATH . '/' . $rel)) && str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
                @unlink($full);
            }
        } catch (Throwable $e) {
            error_log('Nfelia wipe_forget_files ' . $rel . ': ' . $e->getMessage());
        }
    }
}

/** README.txt in the copy: what it is and how to put it back. */
function wipe_readme(array $business, array $parts, array $staff, string $reason, array $counts): string
{
    $link = $business['subdomain'] ? $business['subdomain'] . '.' . shop_base_host() : $business['slug'];
    return "What Nfelia Ops wiped from {$business['name']} (business {$business['id']}, {$link})\r\n"
         . 'on ' . date('j M Y \a\t H:i') . ', by ' . $staff['full_name'] . ":\r\n"
         . wipe_parts_text($parts) . ': ' . wipe_what_text(wipe_counts_what($counts), array_sum($counts)) . ".\r\n"
         . "Why: {$reason}\r\n\r\n"
         . "restore.sql  puts every row back as it was. In phpMyAdmin, open the Nfelia database,\r\n"
         . "             then Import this file (or paste it into the SQL tab and press Go).\r\n"
         . "             Rows that exist again are left alone. Put it back before they start\r\n"
         . "             again: afterwards their new records and these would share numbers.\r\n"
         . "files/       the photos, proofs and signatures that went with the rows. Upload what is\r\n"
         . "             inside files/ into the site's folder (public_html/nfelia.com), keeping\r\n"
         . "             the folders as they are.\r\n\r\n"
         . 'This copy is kept for ' . WIPE_KEEP_DAYS . " days after the wipe, then deleted for good.\r\n";
}

/** Past wipes of a business, newest first, with who did them. */
function wipe_history(PDO $db, int $b): array
{
    $stmt = $db->prepare("SELECT w.*, s.full_name FROM client_wipes w LEFT JOIN ops_staff s ON s.id = w.staff_id WHERE w.business_id = ? ORDER BY w.id DESC LIMIT 20");
    $stmt->execute([$b]);
    return $stmt->fetchAll();
}

/** The full path of a wipe's copy while it is kept, or null. */
function wipe_copy_file(array $wipe): ?string
{
    if (empty($wipe['copy_path'])) {
        return null;
    }
    $full = realpath(BASE_PATH . '/' . $wipe['copy_path']);
    $root = realpath(BASE_PATH . '/storage/private/wipes');
    return $full && $root && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full) ? $full : null;
}

/** Every five minutes (cron/planner.php): copies past their WIPE_KEEP_DAYS are deleted. */
function wipe_cron(PDO $db): array
{
    $done = ['wipe_copies_deleted' => 0];
    foreach ($db->query("SELECT id, copy_path FROM client_wipes WHERE copy_path IS NOT NULL AND copy_expires_at < NOW() LIMIT 100")->fetchAll() as $wipe) {
        if ($file = wipe_copy_file($wipe)) {
            @unlink($file);
        }
        $db->prepare("UPDATE client_wipes SET copy_path = NULL WHERE id = ?")->execute([$wipe['id']]);
        $done['wipe_copies_deleted']++;
    }
    return $done;
}
