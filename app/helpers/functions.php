<?php
/**
 * Nfelia — Global Helper Functions
 */

// NFELIA AUDIT FIX — C02 — Pure PHP document number generator (no stored procedure) — 2026-06-03

/**
 * Generate the next sequential document number for a business: the one
 * generator for every document (the stored procedure some screens called is
 * not in the repo, and shared the same counters).
 *
 * Inside the caller's transaction it takes part in it: the counter row stays
 * locked until the caller commits, and a rollback gives the number back.
 * Outside one, it runs its own.
 *
 * @param PDO    $db          Active database connection
 * @param int    $business_id The business to generate for
 * @param string $doc_type    One of: QUO, INV, REC, DEL, DEA, EXP
 * @return string             e.g. "NFA-INV-004"
 */
function generate_doc_number(PDO $db, int $business_id, string $doc_type): string {
    $valid_types = ['QUO', 'INV', 'REC', 'DEL', 'DEA', 'EXP'];
    if (!in_array($doc_type, $valid_types)) {
        throw new \InvalidArgumentException("Invalid doc_type: {$doc_type}");
    }

    $own = !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        // Lock the counter row so two requests can never take the same number
        $stmtLock = $db->prepare(
            "SELECT last_number FROM document_counters
             WHERE business_id = ? AND doc_type = ? FOR UPDATE"
        );
        $stmtLock->execute([$business_id, $doc_type]);
        $last = $stmtLock->fetchColumn();
        if ($last === false) {
            // A new counter starts after the highest number this business
            // already used for the type, so no number is ever repeated
            $table = ['QUO' => 'quotations', 'INV' => 'invoices', 'REC' => 'receipts',
                      'DEL' => 'deliveries', 'DEA' => 'deeds', 'EXP' => 'expenses'][$doc_type];
            $stmtMax = $db->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(doc_number, '-', -1) AS UNSIGNED)), 0)
                 FROM {$table} WHERE business_id = ? AND doc_number LIKE ?"
            );
            $stmtMax->execute([$business_id, '%-' . $doc_type . '-%']);
            $db->prepare(
                "INSERT INTO document_counters (business_id, doc_type, last_number) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_number = last_number"
            )->execute([$business_id, $doc_type, (int)$stmtMax->fetchColumn()]);
            $stmtLock->execute([$business_id, $doc_type]);
            $last = $stmtLock->fetchColumn();
        }
        $next_num = (int)$last + 1;

        $db->prepare(
            "UPDATE document_counters SET last_number = ? WHERE business_id = ? AND doc_type = ?"
        )->execute([$next_num, $business_id, $doc_type]);

        $stmtBiz = $db->prepare("SELECT doc_prefix FROM businesses WHERE id = ?");
        $stmtBiz->execute([$business_id]);
        $prefix = $stmtBiz->fetchColumn() ?: 'NFE';

        if ($own) {
            $db->commit();
        }
        return strtoupper($prefix) . '-' . $doc_type . '-' . str_pad((string)$next_num, 3, '0', STR_PAD_LEFT);

    } catch (\Exception $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[Nfelia] generate_doc_number failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Generate a cryptographically secure URL token (64 chars)
 */
function generate_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Plain ASCII letters for a name: "Ọja" becomes "Oja", "Éclat" "Eclat".
 */
function ascii_fold(string $text): string {
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if ($t !== false) return $t;
    }
    $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : false;
    return $t !== false ? $t : (string)preg_replace('/[^\x20-\x7E]/', '', $text);
}

/**
 * Derive a document prefix from a business name. Works on its plain letters,
 * so a name starting with "Ọ" or "É" doesn't break sign-up (D3).
 */
function derive_prefix(string $business_name): string {
    $plain = (string)preg_replace('/[^A-Za-z0-9\s]/', '', ascii_fold($business_name));
    $words = preg_split('/\s+/', trim(strtoupper($plain)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return 'NFE';
    }
    if (count($words) >= 3) {
        return substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1);
    } elseif (count($words) === 2) {
        return substr($words[0], 0, 2) . substr($words[1], 0, 1);
    } else {
        return substr($words[0], 0, 3);
    }
}

/**
 * What's wrong with a phone number typed in a form, or null when it is fine
 * (or empty). Too long or too few digits is refused before it reaches a
 * column that can't hold it (D4).
 */
function phone_problem(string $phone, int $max = 20): ?string {
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($phone) > $max || strlen($digits) < 7 || strlen($digits) > 15) {
        return 'Enter a phone number like 0803 123 4567.';
    }
    return null;
}

/**
 * Generate a URL-safe business slug
 */
function slugify(string $text): string {
    $text = strtolower(trim(ascii_fold($text)));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Format money amounts.
 * Uses the provided symbol — defaults to the session business symbol, then ₦.
 */
function money(float $amount, string $symbol = ''): string {
    if ($symbol === '') {
        $symbol = $_SESSION['business']['currency_symbol'] ?? '₦';
    }
    return $symbol . number_format($amount, 2);
}

/**
 * Format date for display
 */
function fmt_date(string $date, string $format = 'd M Y'): string {
    return $date ? date($format, strtotime($date)) : '—';
}

/**
 * Sanitize output to prevent XSS
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $path): never {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Flash message — set
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Flash message — get and clear
 */
function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Determine if request is AJAX
 */
function is_ajax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Generate a 6-digit OTP
 */
function generate_otp(): string {
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Resize and save an uploaded image using GD.
 *
 * Files over 20 MB or 52 megapixels are refused before GD opens them, and the
 * saved copy is at most $max_width wide and three times that tall, so a huge
 * or very tall picture can't fill the disk or the server's memory.
 *
 * @param array  $file      $_FILES entry
 * @param string $dest      Full destination path
 * @param int    $max_width Maximum width in pixels
 * @param string $format    'png' (keeps transparency) or 'jpg' (photos and screenshots)
 * @return bool
 */
function save_image(array $file, string $dest, int $max_width = 800, string $format = 'png'): bool {
    $tmp  = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_file($tmp) || filesize($tmp) > 20 * 1024 * 1024) return false;
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 52000000) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($tmp); break;
        case 'image/png':  $src = imagecreatefrompng($tmp);  break;
        case 'image/webp': $src = imagecreatefromwebp($tmp); break;
        default: return false;
    }
    if (!$src) return false;

    $orig_w = imagesx($src);
    $orig_h = imagesy($src);

    // Only resize if wider than max, or taller than three times that
    $scale = min(1, $max_width / $orig_w, ($max_width * 3) / $orig_h);
    if ($scale < 1) {
        $new_w = max(1, (int)round($orig_w * $scale));
        $new_h = max(1, (int)round($orig_h * $scale));
        $dst = imagecreatetruecolor($new_w, $new_h);

        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($src);
        $src = $dst;
    }

    if ($format === 'jpg') {
        // Flatten onto white: JPEG has no transparency
        $flat = imagecreatetruecolor(imagesx($src), imagesy($src));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imagedestroy($src);
        $result = imagejpeg($flat, $dest, 82);
        imagedestroy($flat);
        return $result;
    }

    // Save as PNG (logos need transparency support)
    $result = imagepng($src, $dest, 8);
    imagedestroy($src);
    return $result;
}

/**
 * Where private files live: delivery signatures and payment proofs. storage/
 * is closed to the web (.htaccess), so these are only ever sent by
 * send_private_file() from a page that has checked who is asking.
 */
function private_file_dir(string $kind, int $business_id): string {
    $dir = BASE_PATH . '/storage/private/' . $kind . '/' . $business_id;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

/**
 * Send a private image (its path relative to the app, as stored) and stop.
 * Files saved before they moved to storage/ are still read from their old
 * folders under uploads/, which .htaccess now closes to the web.
 */
function send_private_file(?string $rel_path): never {
    $rel     = ltrim((string)$rel_path, '/');
    $allowed = ['storage/private/', 'uploads/delivery_signoffs/', 'uploads/order_proofs/', 'uploads/booking_proofs/'];
    $full    = false;
    foreach ($allowed as $prefix) {
        if (str_starts_with($rel, $prefix)) {
            $full = realpath(BASE_PATH . '/' . $rel);
            break;
        }
    }
    $info = ($full && str_starts_with($full, realpath(BASE_PATH) . '/') && is_file($full)) ? @getimagesize($full) : false;
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(404);
        exit;
    }
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: ' . $info['mime']);
    header('Content-Length: ' . filesize($full));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($full);
    exit;
}

/**
 * Convert a GIF or video file to a compressed MP4 using FFmpeg.
 * Audio is stripped — this is for silent hero backgrounds.
 *
 * @param string $input   Full path to the uploaded temp file
 * @param string $output  Full destination path (must end in .mp4)
 * @return bool           True on success
 */
function save_video_as_mp4(string $input, string $output): bool {
    $ffmpeg = trim(shell_exec('which ffmpeg') ?: '/usr/bin/ffmpeg');
    if (!$ffmpeg || !is_executable($ffmpeg)) {
        error_log('[Nfelia] save_video_as_mp4: ffmpeg not found');
        return false;
    }

    // Ensure output directory exists
    $dir = dirname($output);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $cmd = $ffmpeg
         . ' -i '    . escapeshellarg($input)
         . ' -vcodec libx264'
         . ' -crf 28'                                                // Quality: 18 (best) → 35 (smallest)
         . ' -preset fast'                                           // Speed vs compression balance
         . ' -vf ' . escapeshellarg('scale=trunc(iw/2)*2:trunc(ih/2)*2') // Force even dimensions
         . ' -pix_fmt yuv420p'                                       // Max browser compatibility
         . ' -movflags +faststart'                                   // Allow streaming before full download
         . ' -an'                                                    // Strip audio — hero is always silent
         . ' -y '  . escapeshellarg($output)
         . ' 2>&1';

    exec($cmd, $output_lines, $return_code);

    if ($return_code !== 0) {
        error_log('[Nfelia] save_video_as_mp4 failed: ' . implode("\n", $output_lines));
        return false;
    }

    return file_exists($output) && filesize($output) > 0;
}

/**
 * Handle hero background media upload.
 * Shared Hosting Safe: Browser handles compression, PHP just saves the file.
 */
function save_media(array $file, string $dest_dir, string $base_name): ?array {
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    $tmp  = $file['tmp_name'];
    $mime = mime_content_type($tmp) ?: ($file['type'] ?? '');

    // 1. Handle Static Images
    $image_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime, $image_mimes)) {
        $dest = $dest_dir . '/' . $base_name . '.png';
        if (save_image($file, $dest, 1920)) {
            return ['path' => $dest, 'type' => 'image'];
        }
        return null;
    }

    // 2. MP4 videos and GIFs, kept as they are. Other video types were saved
    //    with an .mp4 name they didn't match, so they are refused (W18).
    $video_mimes = ['video/mp4', 'image/gif'];
    if (in_array($mime, $video_mimes)) {
        $ext = ($mime === 'image/gif') ? 'gif' : 'mp4';
        $dest = $dest_dir . '/' . $base_name . '.' . $ext;

        if (move_uploaded_file($tmp, $dest)) {
            return ['path' => $dest, 'type' => 'video'];
        }
        return null;
    }

    error_log('[Nfelia] save_media: unsupported mime type: ' . $mime);
    return null;
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/*** Returns the raw CSRF token string from the session.*/
function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/*** CSRF hidden input field*/
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Does this row belong to the business? Ids posted from a form (a client, a bank
 * account, a product, an invoice) are checked with this before they are saved,
 * so one business can't attach another business's records to its documents.
 */
function owned_by_business(PDO $db, string $table, ?int $id, int $business_id): bool {
    $tables = ['customers', 'bank_accounts', 'products', 'invoices', 'quotations', 'suppliers', 'receipts', 'deliveries'];
    if (!$id || $id <= 0 || !in_array($table, $tables, true)) return false;
    $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE id = ? AND business_id = ? LIMIT 1");
    $stmt->execute([$id, $business_id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * The VAT rate a business set during onboarding, for new invoices and quotes
 * (the signed-in session doesn't carry it).
 */
function business_default_tax(int $business_id): float {
    $stmt = get_db()->prepare("SELECT default_tax_rate FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    return (float)$stmt->fetchColumn();
}

/**
 * Lines posted from an invoice or quote form, in the shape the form reads back
 * (so a form shown again after an error keeps what was typed).
 */
function posted_lines(array $post): array {
    $lines = [];
    foreach (($post['item_description'] ?? []) as $i => $desc) {
        $lines[] = [
            'product_id'       => (int)($post['item_product_id'][$i] ?? 0),
            'description'      => (string)$desc,
            'quantity'         => (float)($post['item_quantity'][$i] ?? 1),
            'unit_price'       => (float)($post['item_unit_price'][$i] ?? 0),
            'discount_percent' => (float)($post['item_discount'][$i] ?? 0),
        ];
    }
    return $lines;
}

/**
 * The first name to greet someone with in an email, from a name a shopper
 * typed: plain letters only (no links, numbers or long text), or "there".
 * Anyone can type any email address into a shop's form, so nothing they
 * write themselves goes out in the email.
 */
function mail_first_name(string $name): string {
    $first = preg_split('/\s+/u', trim($name))[0] ?? '';
    return preg_match("/^\p{L}[\p{L}'’-]{0,29}$/u", $first) ? $first : 'there';
}
