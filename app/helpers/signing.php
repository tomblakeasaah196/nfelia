<?php
/**
 * Signatures and verify codes on documents (docs/handoff/Signatures.md).
 *
 * Tom's rules (October 2026):
 *   - Nothing is signed unless someone wants it. Settings › Signatures turns
 *     signing on by default per kind of document and picks how the business
 *     signs: a stamp ("Signed for … by …"), or the signer's own signature,
 *     drawn once. Each document can be switched on or off.
 *   - Owners and admins sign for the business, in their own name.
 *   - Invoices, receipts and deeds: the business only. Quotes: the customer
 *     signs to accept, and delivery notes: the receiver signs at handover,
 *     both only when that document is signed (otherwise they accept, or
 *     confirm it arrived, with one tap).
 *   - Every signature holds the hash of what was signed. A change signs the
 *     document again in the name of whoever changed it (a new version);
 *     when they may not sign, it shows as changed since it was signed.
 *
 * Every document also has a verify code (doc_codes), printed with a QR code
 * on its page and its PDF; nfelia.com/verify/<code> says it is genuine, who
 * signed it and whether it changed since.
 */

const SIGN_TYPES = [
    'quote'    => ['table' => 'quotations', 'label' => 'Quote',               'many' => 'Quotes',         'sheet' => 'quotes',     'customer' => 'Accepted and signed by'],
    'invoice'  => ['table' => 'invoices',   'label' => 'Invoice',             'many' => 'Invoices',       'sheet' => 'invoices',   'customer' => null],
    'receipt'  => ['table' => 'receipts',   'label' => 'Receipt',             'many' => 'Receipts',       'sheet' => 'receipts',   'customer' => null],
    'delivery' => ['table' => 'deliveries', 'label' => 'Delivery note',       'many' => 'Delivery notes', 'sheet' => 'deliveries', 'customer' => 'Received and signed by'],
    'deed'     => ['table' => 'deeds',      'label' => 'Deed of acquisition', 'many' => 'Deeds',          'sheet' => 'deeds',      'customer' => null],
];

/** What a document is called: a service business's deed is its certificate of completion (views/deeds). */
function doc_label(PDO $db, string $type, int $biz): string
{
    if ($type === 'deed') {
        $stmt = $db->prepare("SELECT business_type FROM businesses WHERE id = ?");
        $stmt->execute([$biz]);
        if ($stmt->fetchColumn() === 'service') return 'Certificate of completion';
    }
    return SIGN_TYPES[$type]['label'];
}

/** The letters of verify codes: Crockford's base 32 (no I, L, O or U), as the Records Book's. */
const SIGN_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/**
 * Signing never stops what it is part of: an order being made, a page being
 * shown. A failure is reported to Ops › Errors and the step is skipped
 * ($fallback is returned).
 */
function signing_safely(callable $step, $fallback = null)
{
    try {
        return $step();
    } catch (Throwable $e) {
        function_exists('errors_report_throwable') ? errors_report_throwable($e) : error_log('[signing] ' . $e->getMessage());
        return $fallback;
    }
}

/* ══════════════════════════════════════════════════════════════
 *  SETTINGS
 * ══════════════════════════════════════════════════════════════ */

/** Settings › Signatures for a business: [type => ['on' => bool, 'style' => 'stamp'|'drawn']]. Off and stamped until set. */
function signing_settings(?array $business): array
{
    $saved = json_decode((string)($business['signing'] ?? ''), true);
    $out = [];
    foreach (SIGN_TYPES as $type => $_) {
        $s = is_array($saved[$type] ?? null) ? $saved[$type] : [];
        $out[$type] = ['on' => !empty($s['on']), 'style' => ($s['style'] ?? '') === 'drawn' ? 'drawn' : 'stamp'];
    }
    return $out;
}

/** Settings › Signatures for business $biz, read fresh (the session's copy of the business may be older). */
function signing_settings_for(PDO $db, int $biz): array
{
    $stmt = $db->prepare("SELECT signing FROM businesses WHERE id = ?");
    $stmt->execute([$biz]);
    return signing_settings(['signing' => $stmt->fetchColumn() ?: null]);
}

/**
 * The "Signed" box on a document's form: what it says when the form was
 * sent with it (sign_box marks it was there), else null (use Settings).
 */
function signing_box(array $post): ?bool
{
    return isset($post['sign_box']) ? !empty($post['sign_on']) : null;
}

function signing_save(PDO $db, int $biz, array $settings): void
{
    $clean = [];
    foreach (SIGN_TYPES as $type => $_) {
        $clean[$type] = ['on' => !empty($settings[$type]['on']), 'style' => ($settings[$type]['style'] ?? '') === 'drawn' ? 'drawn' : 'stamp'];
    }
    $db->prepare("UPDATE businesses SET signing = ? WHERE id = ?")->execute([json_encode($clean), $biz]);
}

/** An owner or admin of $biz who is still active, who may sign for it; null for anyone else. */
function sign_user(PDO $db, int $biz, ?int $user_id): ?array
{
    if (!$user_id) return null;
    $stmt = $db->prepare("SELECT id, business_id, full_name, role, signature_path FROM users
                          WHERE id = ? AND business_id = ? AND is_active = 1 AND role IN ('owner', 'admin')");
    $stmt->execute([$user_id, $biz]);
    return $stmt->fetch() ?: null;
}

/**
 * Keep someone's drawn signature: a PNG from the signature pad, redrawn by
 * GD (so only an image is ever stored) and cropped to the ink, under a name
 * made from its content, so a document keeps the drawing it was signed with
 * even after a new one. Returns the stored path, or null when it isn't a
 * usable drawing.
 */
function signing_store_drawing(int $biz, string $data_url): ?string
{
    if (!str_starts_with($data_url, 'data:image/png;base64,') || strlen($data_url) > 3 * 1024 * 1024) return null;
    $bytes = base64_decode(substr($data_url, 22), true);
    $info = $bytes ? @getimagesizefromstring($bytes) : false;
    if (!$info || $info['mime'] !== 'image/png' || $info[0] > 4000 || $info[1] > 2000) return null;
    $image = @imagecreatefromstring($bytes);
    if (!$image) return null;
    // Only the ink: a pad's empty margins would print the signature small
    if (($ink = @imagecropauto($image, IMG_CROP_SIDES)) !== false) {
        imagedestroy($image);
        $image = $ink;
    }
    imagesavealpha($image, true);
    ob_start();
    imagepng($image, null, 8);
    $png = (string)ob_get_clean();
    imagedestroy($image);
    $name = 'sig-' . sha1($png) . '.png';
    $dir = private_file_dir('signatures', $biz);
    if (!is_file($dir . '/' . $name) && file_put_contents($dir . '/' . $name, $png) === false) return null;
    return 'storage/private/signatures/' . $biz . '/' . $name;
}

/** A stored drawing as a data: URI, for a page or a PDF ('' when it is gone). */
function signing_image_uri(?string $path): string
{
    $rel = ltrim((string)$path, '/');
    if ($rel === '' || !str_starts_with($rel, 'storage/private/signatures/') && !str_starts_with($rel, 'uploads/delivery_signoffs/')) return '';
    $full = realpath(BASE_PATH . '/' . $rel);
    if (!$full || !str_starts_with($full, realpath(BASE_PATH) . '/') || !is_file($full)) return '';
    return 'data:image/png;base64,' . base64_encode((string)file_get_contents($full));
}

/* ══════════════════════════════════════════════════════════════
 *  VERIFY CODES
 * ══════════════════════════════════════════════════════════════ */

/** The document's verify code, made the first time it is asked for. */
function doc_code(PDO $db, int $biz, string $type, int $id): string
{
    $stmt = $db->prepare("SELECT code FROM doc_codes WHERE doc_type = ? AND doc_id = ?");
    $stmt->execute([$type, $id]);
    if ($code = $stmt->fetchColumn()) return (string)$code;
    for ($try = 0; $try < 8; $try++) {
        $code = '';
        for ($i = 0; $i < 10; $i++) $code .= SIGN_ALPHABET[random_int(0, 31)];
        // Never the same as a Records Book's: verify reads both
        $taken = $db->prepare("SELECT 1 FROM records_books WHERE code = ? UNION ALL SELECT 1 FROM doc_codes WHERE code = ?");
        $taken->execute([$code, $code]);
        if ($taken->fetchColumn()) continue;
        try {
            $db->prepare("INSERT INTO doc_codes (business_id, doc_type, doc_id, code) VALUES (?, ?, ?, ?)")->execute([$biz, $type, $id, $code]);
            return $code;
        } catch (PDOException $e) {
            // Made at the same moment by another page: theirs stands
            $stmt->execute([$type, $id]);
            if ($code = $stmt->fetchColumn()) return (string)$code;
        }
    }
    throw new RuntimeException('could not make a verify code');
}

/** The document a verify code belongs to: ['business_id', 'doc_type', 'doc_id', 'code'], or null. */
function doc_code_find(PDO $db, string $code): ?array
{
    $stmt = $db->prepare("SELECT business_id, doc_type, doc_id, code FROM doc_codes WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

/** "K7Q2M-XA9WT": a code as people read it. */
function doc_code_display(string $code): string
{
    return substr($code, 0, 5) . '-' . substr($code, 5);
}

function doc_verify_url(string $code): string
{
    return APP_URL . '/verify/' . $code;
}

/** The QR code that opens the document's verify page, as a data: URI (no Nfelia mark: it is small on a page). */
function doc_qr_uri(string $code): string
{
    return signing_safely(function () use ($code) {
        require_once __DIR__ . '/qr.php';
        return 'data:image/png;base64,' . base64_encode(qr_png(doc_verify_url($code), 4, false));
    }, '');
}

/* ══════════════════════════════════════════════════════════════
 *  WHAT IS SIGNED
 * ══════════════════════════════════════════════════════════════ */

/** The document's own row (with business_id, doc_number, sign_on), or null. */
function doc_row(PDO $db, string $type, int $id): ?array
{
    if (!isset(SIGN_TYPES[$type])) return null;
    $stmt = $db->prepare("SELECT * FROM " . SIGN_TYPES[$type]['table'] . " WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * What a signature covers: the document's content, as it was given to the
 * customer. Not what happens to it afterwards: payments on an invoice, a
 * delivery being dispatched or a quote's answer never change a signature.
 * Fixed order and plain strings, so the same content always gives the same
 * hash. Null when the document is gone.
 */
function doc_facts(PDO $db, string $type, int $id): ?array
{
    $one  = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetch() ?: null; };
    $all  = function (string $sql, array $args) use ($db) { $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(); };
    $amt  = fn($v) => number_format((float)$v, 2, '.', '');
    $qty  = fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
    $text = fn($v) => trim((string)($v ?? ''));
    switch ($type) {
        case 'quote':
            $d = $one("SELECT q.business_id, q.doc_number, q.issue_date, q.expiry_date, q.subtotal, q.discount_amount, q.tax_rate, q.tax_amount, q.total, q.notes,
                              c.full_name AS customer
                       FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id WHERE q.id = ?", [$id]);
            if (!$d) return null;
            $lines = array_map(fn($l) => [$text($l['description']), $qty($l['quantity']), $amt($l['unit_price']), $amt($l['discount_amount']), $amt($l['subtotal'])],
                $all("SELECT description, quantity, unit_price, discount_amount, subtotal FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id", [$id]));
            return ['number' => $d['doc_number'], 'date' => $d['issue_date'], 'good until' => (string)$d['expiry_date'], 'customer' => $text($d['customer']),
                    'lines' => $lines, 'subtotal' => $amt($d['subtotal']), 'discount' => $amt($d['discount_amount']), 'tax' => $amt($d['tax_amount']),
                    'total' => $amt($d['total']), 'notes' => $text($d['notes'])];
        case 'invoice':
            $d = $one("SELECT i.business_id, i.doc_number, i.issue_date, i.due_date, i.subtotal, i.discount_amount, i.tax_amount, i.total, i.notes, i.payment_terms,
                              c.full_name AS customer, o.order_number
                       FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
                       WHERE i.id = ?", [$id]);
            if (!$d) return null;
            $lines = array_map(fn($l) => [$text($l['description']), $qty($l['quantity']), $amt($l['unit_price']), $amt($l['discount_amount']), $amt($l['subtotal'])],
                $all("SELECT description, quantity, unit_price, discount_amount, subtotal FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id", [$id]));
            return ['number' => $d['doc_number'], 'order' => (string)$d['order_number'], 'date' => $d['issue_date'], 'due' => (string)$d['due_date'],
                    'customer' => $text($d['customer']), 'lines' => $lines, 'subtotal' => $amt($d['subtotal']), 'discount' => $amt($d['discount_amount']),
                    'tax' => $amt($d['tax_amount']), 'total' => $amt($d['total']), 'notes' => $text($d['notes']), 'terms' => $text($d['payment_terms'])];
        case 'receipt':
            $d = $one("SELECT r.business_id, r.doc_number, r.issue_date, r.amount, r.method, r.note, c.full_name AS customer, i.doc_number AS invoice, o.order_number
                       FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN invoices i ON i.id = r.invoice_id
                       LEFT JOIN orders o ON o.id = r.order_id AND o.business_id = r.business_id WHERE r.id = ?", [$id]);
            if (!$d) return null;
            $lines = array_map(fn($l) => [$text($l['description']), $qty($l['quantity']), $amt($l['unit_price']), $amt($l['subtotal'])],
                $all("SELECT description, quantity, unit_price, subtotal FROM receipt_items WHERE receipt_id = ? ORDER BY sort_order, id", [$id]));
            return ['number' => $d['doc_number'], 'order' => (string)$d['order_number'], 'invoice' => (string)$d['invoice'], 'date' => $d['issue_date'],
                    'customer' => $text($d['customer']), 'amount' => $amt($d['amount']), 'how' => (string)$d['method'], 'note' => $text($d['note']), 'lines' => $lines];
        case 'delivery':
            $d = $one("SELECT d.business_id, d.doc_number, d.recipient_name, d.delivery_address, c.full_name AS customer, i.doc_number AS invoice, o.order_number
                       FROM deliveries d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN invoices i ON i.id = d.invoice_id
                       LEFT JOIN orders o ON o.id = d.order_id AND o.business_id = d.business_id WHERE d.id = ? AND d.deleted_at IS NULL", [$id]);
            if (!$d) return null;
            $lines = array_map(fn($l) => [$text($l['description']), $qty($l['quantity']), $text($l['unit'])],
                $all("SELECT description, quantity, unit FROM delivery_items WHERE delivery_id = ? ORDER BY sort_order, id", [$id]));
            return ['number' => $d['doc_number'], 'order' => (string)$d['order_number'], 'invoice' => (string)$d['invoice'], 'customer' => $text($d['customer']),
                    'to' => $text($d['recipient_name']), 'address' => $text($d['delivery_address']), 'lines' => $lines];
        case 'deed':
            $d = $one("SELECT e.business_id, e.doc_number, e.issued_at, e.total_amount, e.currency_code, e.line_items, e.provenance_text, c.full_name AS customer
                       FROM deeds e LEFT JOIN customers c ON c.id = e.customer_id WHERE e.id = ?", [$id]);
            if (!$d) return null;
            $lines = array_map(fn($l) => [$text($l['description'] ?? $l['name'] ?? ''), $qty($l['quantity'] ?? $l['qty'] ?? 1), $amt($l['unit_price'] ?? $l['price'] ?? 0)],
                array_values(array_filter((array)json_decode((string)$d['line_items'], true), 'is_array')));
            return ['number' => $d['doc_number'], 'date' => (string)$d['issued_at'], 'customer' => $text($d['customer']), 'lines' => $lines,
                    'total' => $amt($d['total_amount']), 'currency' => (string)$d['currency_code'], 'provenance' => $text($d['provenance_text'])];
    }
    return null;
}

function doc_hash(array $facts): string
{
    return hash('sha256', json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * The key seals are made with: a secret kept on the server, never in the
 * database, so a row changed there can't be sealed again. It is made the
 * first time it is needed and kept in storage/private/seal.key (which deploys
 * never touch), so it never changes when other settings do; SEAL_KEY in .env
 * is used instead when it is set. Losing it means no seal checks out.
 */
function doc_seal_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $env = trim((string)($_ENV['SEAL_KEY'] ?? getenv('SEAL_KEY') ?: ''));
    if (strlen($env) >= 32) return $key = $env;
    $file = BASE_PATH . '/storage/private/seal.key';
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0750, true);
    $fh = @fopen($file, 'c+');
    if (!$fh) throw new RuntimeException('The seal key can\'t be kept in ' . $file);
    flock($fh, LOCK_EX);   // two first signatures at once: the first key written stands
    $saved = trim((string)stream_get_contents($fh));
    if (strlen($saved) < 32) {
        $saved = bin2hex(random_bytes(32));
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $saved);
        fflush($fh);
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($file, 0640);
    return $key = $saved;
}

/** A signature's seal: everything it says, keyed, so a row edited in the database no longer checks out. */
function doc_seal(array $s): string
{
    return hash_hmac('sha256', implode('|', [$s['doc_type'], (int)$s['doc_id'], (int)$s['version'], $s['party'], $s['signer_name'],
                                                (string)($s['signer_role'] ?? ''), $s['style'], (string)($s['image_path'] ?? ''), $s['content_hash'],
                                                $s['signed_at']]), doc_seal_key());
}

function doc_seal_ok(array $s): bool
{
    return hash_equals(doc_seal($s), (string)$s['seal']);
}

/** "3F9A·C1D2": the start of a seal, printed under a signature. */
function doc_seal_short(string $seal): string
{
    return strtoupper(substr($seal, 0, 4) . '·' . substr($seal, 4, 4));
}

/* ══════════════════════════════════════════════════════════════
 *  SIGNING
 * ══════════════════════════════════════════════════════════════ */

/** The signature standing for a party (not superseded), or null. */
function doc_latest(PDO $db, string $type, int $id, string $party = 'business'): ?array
{
    $stmt = $db->prepare("SELECT * FROM doc_signatures WHERE doc_type = ? AND doc_id = ? AND party = ? AND superseded_at IS NULL ORDER BY version DESC, id DESC LIMIT 1");
    $stmt->execute([$type, $id, $party]);
    return $stmt->fetch() ?: null;
}

/** Every signature on a document, oldest first. */
function doc_history(PDO $db, string $type, int $id): array
{
    $stmt = $db->prepare("SELECT * FROM doc_signatures WHERE doc_type = ? AND doc_id = ? ORDER BY signed_at, id");
    $stmt->execute([$type, $id]);
    return $stmt->fetchAll();
}

/** Write a signature, sealed; the party's earlier one stops standing. Returns the new row. */
function doc_signature_add(PDO $db, string $type, int $id, int $biz, string $party, array $who, array $facts): array
{
    $stmt = $db->prepare("SELECT COALESCE(MAX(version), 0) FROM doc_signatures WHERE doc_type = ? AND doc_id = ? AND party = ?");
    $stmt->execute([$type, $id, $party]);
    $row = [
        'business_id' => $biz, 'doc_type' => $type, 'doc_id' => $id, 'version' => (int)$stmt->fetchColumn() + 1, 'party' => $party,
        'user_id' => $who['user_id'] ?? null, 'signer_name' => mb_substr(trim((string)$who['name']), 0, 150), 'signer_role' => $who['role'] ?? null,
        'style' => $who['style'] ?? 'stamp', 'image_path' => $who['image_path'] ?? null, 'content_hash' => doc_hash($facts),
        'facts' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ip' => $who['ip'] ?? null, 'signed_at' => date('Y-m-d H:i:s'),
    ];
    $row['seal'] = doc_seal($row);
    $db->prepare("UPDATE doc_signatures SET superseded_at = ? WHERE doc_type = ? AND doc_id = ? AND party = ? AND superseded_at IS NULL")
       ->execute([$row['signed_at'], $type, $id, $party]);
    $db->prepare("INSERT INTO doc_signatures (business_id, doc_type, doc_id, version, party, user_id, signer_name, signer_role, style, image_path,
                                              content_hash, facts, seal, ip, signed_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$biz, $type, $id, $row['version'], $party, $row['user_id'], $row['signer_name'], $row['signer_role'], $row['style'], $row['image_path'],
                  $row['content_hash'], $row['facts'], $row['seal'], $row['ip'], $row['signed_at']]);
    $row['id'] = (int)$db->lastInsertId();
    return $row;
}

/**
 * The business signs the document as it is now, in $signer's name (an owner
 * or admin, sign_user()), with the style Settings gives this kind: their
 * drawing when they have one and the style asks for it, else the stamp.
 * Returns the signature, or null when the document is gone.
 */
function doc_sign(PDO $db, string $type, int $id, array $signer): ?array
{
    $biz = (int)$signer['business_id'];
    $facts = doc_facts($db, $type, $id);
    if ($facts === null) return null;
    $drawn = signing_settings_for($db, $biz)[$type]['style'] === 'drawn' && !empty($signer['signature_path']);
    return doc_signature_add($db, $type, $id, $biz, 'business', [
        'user_id' => (int)$signer['id'], 'name' => (string)$signer['full_name'], 'role' => ucfirst((string)$signer['role']),
        'style' => $drawn ? 'drawn' : 'stamp', 'image_path' => $drawn ? $signer['signature_path'] : null, 'ip' => function_exists('client_ip') ? client_ip() : null,
    ], $facts);
}

/** The customer signs: a quote they accept, a delivery they receive. */
function doc_customer_sign(PDO $db, string $type, int $id, string $name, ?string $image_path, ?string $ip): ?array
{
    $row = doc_row($db, $type, $id);
    $facts = $row ? doc_facts($db, $type, $id) : null;
    if ($facts === null) return null;
    return doc_signature_add($db, $type, $id, (int)$row['business_id'], 'customer',
        ['name' => $name, 'style' => $image_path ? 'drawn' : 'stamp', 'image_path' => $image_path, 'ip' => $ip], $facts);
}

/**
 * A document was just made: signed or not as Settings say for its kind
 * (or as $on says, the quote form's box), and signed at once when whoever
 * made it may sign for the business. Staff's documents wait for an owner or
 * admin to sign them.
 */
function doc_made(PDO $db, string $type, int $id, ?int $user_id, ?bool $on = null): void
{
    signing_safely(function () use ($db, $type, $id, $user_id, $on) {
        $row = doc_row($db, $type, $id);
        if (!$row) return;
        $biz = (int)$row['business_id'];
        $on ??= signing_settings_for($db, $biz)[$type]['on'];
        $db->prepare("UPDATE " . SIGN_TYPES[$type]['table'] . " SET sign_on = ? WHERE id = ?")->execute([$on ? 1 : 0, $id]);
        if ($on && ($signer = sign_user($db, $biz, $user_id))) {
            doc_sign($db, $type, $id, $signer);
        }
    });
}

/**
 * A signed document was changed: signed again, as a new version, in the
 * name of whoever changed it when they may sign (Tom's call). Otherwise it
 * stays as signed, and shows as changed since it was signed until an owner
 * or admin signs it again.
 */
function doc_changed(PDO $db, string $type, int $id, ?int $user_id): void
{
    signing_safely(function () use ($db, $type, $id, $user_id) {
        $row = doc_row($db, $type, $id);
        if (!$row || empty($row['sign_on'])) return;
        $latest = doc_latest($db, $type, $id);
        $facts = doc_facts($db, $type, $id);
        if ($facts === null || ($latest && hash_equals((string)$latest['content_hash'], doc_hash($facts)))) return;
        if ($signer = sign_user($db, (int)$row['business_id'], $user_id)) {
            doc_sign($db, $type, $id, $signer);
        }
    });
}

/**
 * Sign a document, or take its signature off (an owner or admin, from the
 * document's own page). Returns '' when done, or why not.
 */
function doc_switch(PDO $db, int $biz, string $type, int $id, bool $on, int $user_id): string
{
    $row = doc_row($db, $type, $id);
    if (!$row || (int)$row['business_id'] !== $biz) return 'That document is not here.';
    $signer = sign_user($db, $biz, $user_id);
    if (!$signer) return 'Only the owner or an admin can sign for the business.';
    if ($type === 'quote' && !$on && in_array($row['status'], ['accepted', 'converted'], true) && doc_latest($db, $type, $id, 'customer')) {
        return 'The customer signed this quote, so it stays signed.';
    }
    if ($type === 'delivery' && !$on && doc_latest($db, $type, $id, 'customer')) {
        return 'The receiver signed this delivery note, so it stays signed.';
    }
    $db->prepare("UPDATE " . SIGN_TYPES[$type]['table'] . " SET sign_on = ? WHERE id = ? AND business_id = ?")->execute([$on ? 1 : 0, $id, $biz]);
    if ($on) {
        $latest = doc_latest($db, $type, $id);
        $facts = doc_facts($db, $type, $id);
        if (!$latest || $facts === null || !hash_equals((string)$latest['content_hash'], doc_hash($facts)) || (int)$latest['user_id'] !== (int)$signer['id']) {
            doc_sign($db, $type, $id, $signer);
        }
    } else {
        $db->prepare("UPDATE doc_signatures SET superseded_at = NOW() WHERE doc_type = ? AND doc_id = ? AND party = 'business' AND superseded_at IS NULL")
           ->execute([$type, $id]);
    }
    return '';
}

/** The person looking at a document in the app, when they may sign it (an owner or admin, not Nfelia support viewing as them); else null. */
function doc_sign_viewer(): ?int
{
    if (!is_logged_in() || !is_boss() || (function_exists('view_as_active') && view_as_active())) return null;
    return (int)(auth_user()['id'] ?? 0) ?: null;
}

/**
 * Everything a page or a PDF needs to show a document's signatures and its
 * verify code:
 *   on, code, display (K7Q2M-XA9WT), url, qr (data: URI)
 *   business, customer   the standing signatures (rows), or null; image as
 *                        a data: URI when drawn
 *   changed              signed, but the content has changed since
 *   versions             how many times the business has signed it
 *   can_sign             the person looking may sign for the business
 * Empty when it can't be had: the page shows without it.
 */
function doc_sign_state(PDO $db, string $type, int $id, ?int $viewer_id = null, bool $with_qr = true): array
{
    return signing_safely(fn() => doc__sign_state($db, $type, $id, $viewer_id, $with_qr), []);
}

function doc__sign_state(PDO $db, string $type, int $id, ?int $viewer_id, bool $with_qr): array
{
    $row = doc_row($db, $type, $id);
    if (!$row) return [];
    $biz = (int)$row['business_id'];
    $code = doc_code($db, $biz, $type, $id);
    $picture = fn(?array $s) => $s ? $s + ['image' => $s['style'] === 'drawn' ? signing_image_uri($s['image_path']) : '', 'legacy' => false] : null;
    $business = $picture(doc_latest($db, $type, $id));
    $customer = $picture(doc_latest($db, $type, $id, 'customer'));
    // Signed before signatures were sealed: shown as they were, without a seal
    $legacy = fn(string $name, $at, string $image) => ['legacy' => true, 'signer_name' => $name, 'signer_role' => null, 'signed_at' => (string)$at,
                                                       'image' => $image, 'style' => 'drawn', 'seal' => '', 'version' => 1];
    if ($type === 'quote' && !$business && !empty($row['sign_on']) && !empty($row['owner_signature_data'])) {
        $business = $legacy((string)$row['owner_signed_by_name'], $row['owner_signed_at'], (string)$row['owner_signature_data']);
    }
    if ($type === 'quote' && !$customer && !empty($row['signature_data'])) {
        $customer = $legacy((string)$row['signed_by_name'], $row['signed_at'], (string)$row['signature_data']);
    }
    if ($type === 'delivery' && !$customer && !empty($row['signoff_signature_path'])) {
        $customer = $legacy((string)$row['signoff_name'], $row['signoff_timestamp'], signing_image_uri($row['signoff_signature_path']));
    }
    $facts = $business && !$business['legacy'] ? doc_facts($db, $type, $id) : null;
    $count = $db->prepare("SELECT COUNT(*) FROM doc_signatures WHERE doc_type = ? AND doc_id = ? AND party = 'business'");
    $count->execute([$type, $id]);
    return [
        'type' => $type, 'id' => $id, 'label' => doc_label($db, $type, $biz), 'on' => !empty($row['sign_on']),
        'code' => $code, 'display' => doc_code_display($code), 'url' => doc_verify_url($code), 'qr' => $with_qr ? doc_qr_uri($code) : '',
        'business' => $business, 'customer' => $customer,
        'changed' => $business && $facts !== null && !hash_equals((string)$business['content_hash'], doc_hash($facts)),
        'versions' => (int)$count->fetchColumn(),
        'can_sign' => $viewer_id ? sign_user($db, $biz, $viewer_id) !== null : false,
        'customer_words' => SIGN_TYPES[$type]['customer'],
    ];
}

/**
 * Forget the signatures and verify codes of documents that are gone (a
 * purge: Settings › Purge). Returns the drawings nothing uses any more, for
 * the caller to delete once its transaction is through.
 */
function doc_forget_missing(PDO $db, int $biz): array
{
    $images = [];
    foreach (SIGN_TYPES as $type => $t) {
        $gone = " FROM doc_signatures x LEFT JOIN {$t['table']} d ON d.id = x.doc_id WHERE x.business_id = ? AND x.doc_type = ? AND d.id IS NULL";
        $stmt = $db->prepare("SELECT DISTINCT x.image_path" . $gone . " AND x.image_path IS NOT NULL");
        $stmt->execute([$biz, $type]);
        $images = array_merge($images, $stmt->fetchAll(PDO::FETCH_COLUMN));
        $db->prepare("DELETE x" . $gone)->execute([$biz, $type]);
        $db->prepare("DELETE x FROM doc_codes x LEFT JOIN {$t['table']} d ON d.id = x.doc_id WHERE x.business_id = ? AND x.doc_type = ? AND d.id IS NULL")
           ->execute([$biz, $type]);
    }
    $used = $db->prepare("SELECT (SELECT COUNT(*) FROM doc_signatures WHERE image_path = ?) + (SELECT COUNT(*) FROM users WHERE signature_path = ?)
                                 + (SELECT COUNT(*) FROM deliveries WHERE signoff_signature_path = ?)");
    return array_values(array_filter(array_unique($images), function ($path) use ($used) {
        $used->execute([$path, $path, $path]);
        return !(int)$used->fetchColumn();
    }));
}

/* ══════════════════════════════════════════════════════════════
 *  NFELIA.COM/VERIFY
 * ══════════════════════════════════════════════════════════════ */

/**
 * What nfelia.com/verify says about a document's code (null when it is no
 * document's): the document's key facts (as a Records Book shows them,
 * customer names masked), every signature with whether its seal checks out
 * and whether the document changed since, and the business.
 */
function doc_verify_lookup(PDO $db, string $code): ?array
{
    $found = doc_code_find($db, $code);
    if (!$found) return null;
    $type = (string)$found['doc_type'];
    $id = (int)$found['doc_id'];
    $biz = (int)$found['business_id'];
    require_once __DIR__ . '/records.php';
    $business = records_business($db, $biz);
    if (!$business) return null;
    $row = doc_row($db, $type, $id);
    $facts = $row ? records_record_now($db, ['business_id' => $biz], SIGN_TYPES[$type]['sheet'], $id) : null;
    $now = $row ? doc_facts($db, $type, $id) : null;
    $now_hash = $now !== null ? doc_hash($now) : '';
    $signatures = [];
    foreach (doc_history($db, $type, $id) as $s) {
        $then = json_decode((string)$s['facts'], true) ?: [];
        $signatures[] = $s + [
            'seal_ok' => doc_seal_ok($s),
            'standing' => $s['superseded_at'] === null,
            'unchanged' => $now !== null && hash_equals((string)$s['content_hash'], $now_hash),
            'what_changed' => $now !== null ? doc_changed_fields($then, $now) : [],
        ];
    }
    $number = (string)($row['doc_number'] ?? '');
    return [
        'kind' => 'document', 'code' => $code, 'doc_type' => $type, 'doc_id' => $id, 'business' => $business, 'kept' => $row !== null,
        'label' => trim(doc_label($db, $type, $biz) . ' ' . $number), 'facts' => $facts ?? [], 'removed' => $row === null,
        'signed' => !empty($row['sign_on']), 'signatures' => $signatures,
    ];
}

/** Which of a document's parts differ between two sets of facts, in words ("the total", "the items"). */
function doc_changed_fields(array $then, array $now): array
{
    $words = ['number' => 'the number', 'order' => 'the order', 'invoice' => 'the invoice', 'date' => 'the date', 'due' => 'the due date',
              'good until' => 'how long it is good for', 'customer' => 'the customer', 'to' => 'who receives it', 'address' => 'the address',
              'lines' => 'the items', 'subtotal' => 'the subtotal', 'discount' => 'the discount', 'tax' => 'the tax', 'total' => 'the total',
              'amount' => 'the amount', 'how' => 'how it was paid', 'notes' => 'the notes', 'note' => 'the note', 'terms' => 'the terms',
              'currency' => 'the currency', 'provenance' => 'where it came from'];
    $out = [];
    foreach ($words as $key => $word) {
        if (json_encode($then[$key] ?? null) !== json_encode($now[$key] ?? null)) $out[] = $word;
    }
    return $out;
}
