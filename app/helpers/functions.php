<?php
/**
 * Nfelia — Global Helper Functions
 */

// NFELIA AUDIT FIX — C02 — Pure PHP document number generator (no stored procedure) — 2026-06-03

/**
 * Generate the next sequential document number for a business.
 * Uses a transaction + row-level lock on document_counters for atomicity.
 * Replaces the missing stored procedure entirely.
 *
 * @param PDO    $db          Active database connection
 * @param int    $business_id The business to generate for
 * @param string $doc_type    One of: QUO, INV, REC, DEL, DEA
 * @return string             e.g. "NFA-INV-004"
 */
function generate_doc_number(PDO $db, int $business_id, string $doc_type): string {
    $valid_types = ['QUO', 'INV', 'REC', 'DEL', 'DEA'];
    if (!in_array($doc_type, $valid_types)) {
        throw new \InvalidArgumentException("Invalid doc_type: {$doc_type}");
    }

    $db->beginTransaction();
    try {
        // Lock the counter row to prevent race conditions under concurrent requests
        $stmtLock = $db->prepare(
            "SELECT last_number FROM document_counters
             WHERE business_id = ? AND doc_type = ? FOR UPDATE"
        );
        $stmtLock->execute([$business_id, $doc_type]);
        $row = $stmtLock->fetch();

        if (!$row) {
            // Counter row doesn't exist — initialise it
            $db->prepare(
                "INSERT INTO document_counters (business_id, doc_type, last_number) VALUES (?, ?, 0)"
            )->execute([$business_id, $doc_type]);
            $next_num = 1;
        } else {
            $next_num = (int)$row['last_number'] + 1;
        }

        // Persist the incremented counter
        $db->prepare(
            "UPDATE document_counters SET last_number = ? WHERE business_id = ? AND doc_type = ?"
        )->execute([$next_num, $business_id, $doc_type]);

        $db->commit();

        // Fetch the business prefix (outside the transaction — read-only, no locking needed)
        $stmtBiz = $db->prepare("SELECT doc_prefix FROM businesses WHERE id = ?");
        $stmtBiz->execute([$business_id]);
        $biz = $stmtBiz->fetch();
        $prefix = $biz['doc_prefix'] ?? 'NFE';

        return strtoupper($prefix) . '-' . $doc_type . '-' . str_pad((string)$next_num, 3, '0', STR_PAD_LEFT);

    } catch (\Exception $e) {
        $db->rollBack();
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
 * Derive a document prefix from a business name
 */
function derive_prefix(string $business_name): string {
    $words = preg_split('/\s+/', trim(strtoupper($business_name)));
    if (count($words) >= 3) {
        return substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1);
    } elseif (count($words) === 2) {
        return substr($words[0], 0, 2) . substr($words[1], 0, 1);
    } else {
        return substr($words[0], 0, 3);
    }
}

/**
 * Generate a URL-safe business slug
 */
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Format money amounts.
 * Uses the provided symbol — defaults to the session business symbol, then ₦.
 */
/**
 * Whether a business may use Nfelia now, from its row in subscriptions. The
 * one rule behind the app's lock-out (app/middleware/subscription_check.php),
 * the shop staying open (shop_visibility) and the cron's reminders
 * (planner_business_can_use), on Lagos time like the rest of the app:
 *
 *   trial                 through the whole of trial_ends_at
 *   active                yes (cron/planner.php moves a plan that is not
 *                         renewed by card to past_due once its period ends)
 *   pending_verification  yes: a mobile money payment is being checked
 *   past_due              until grace_ends_at (else 3 days after the period)
 *   cancelled             through the period already paid for (a trial
 *                         cancelled early, through the trial)
 *   suspended             no
 */
function subscription_open(array $sub, ?DateTimeImmutable $now = null): bool {
    $tz    = planner_tz();
    $now   = $now ?? new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $day   = fn($v) => $v ? substr((string)$v, 0, 10) : null;
    switch ($sub['status'] ?? null) {
        case 'trial':
            return empty($sub['trial_ends_at']) || $today <= $day($sub['trial_ends_at']);
        case 'active':
        case 'pending_verification':
            return true;
        case 'past_due':
            if (!empty($sub['grace_ends_at'])) {
                return $now <= new DateTimeImmutable($sub['grace_ends_at'], $tz);
            }
            return !empty($sub['current_period_end']) && $today <= planner_add_days($day($sub['current_period_end']), 3);
        case 'cancelled':
            $until = $day($sub['current_period_end'] ?? null) ?? $day($sub['trial_ends_at'] ?? null);
            return $until !== null && $today <= $until;
        default:
            return false;
    }
}

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
 * @param array  $file      $_FILES entry
 * @param string $dest      Full destination path
 * @param int    $max_width Maximum width in pixels
 * @return bool
 */
function save_image(array $file, string $dest, int $max_width = 800): bool {
    $tmp  = $file['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) return false;

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

    // Only resize if wider than max
    if ($orig_w > $max_width) {
        $new_w = $max_width;
        $new_h = (int)round($orig_h * ($max_width / $orig_w));
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

    // Save as PNG (logos need transparency support)
    $result = imagepng($src, $dest, 8);
    imagedestroy($src);
    return $result;
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

    // 2. Handle Videos & GIFs natively (Already compressed by the browser)
    $video_mimes = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo', 'video/avi', 'image/gif'];
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