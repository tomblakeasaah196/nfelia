<?php
/**
 * Nfelia — Notifications AJAX API
 * Handles: fetching unread counts, listing recent alerts, and marking as read.
 * Returns pure JSON.
 */

// 1. Strict Security Barrier
if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized access'], 401);
}

$db = get_db();
$biz_id = auth_business_id();
$action = $_GET['action'] ?? 'list';

switch ($action) {

    /* ══════════════════════════════════════════
     *  ACTION: COUNT UNREAD
     *  Used for the red notification bubble/badge
     * ══════════════════════════════════════════ */
    case 'count':
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE business_id = ? AND is_read = 0");
        $stmt->execute([$biz_id]);
        $count = (int)$stmt->fetchColumn();

        json_response([
            'success' => true, 
            'count'   => $count
        ]);
        break;

    /* ══════════════════════════════════════════
     *  ACTION: LIST RECENT
     *  Populates the dropdown UI
     * ══════════════════════════════════════════ */
    case 'list':
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10))); // Cap at 50 max
        
        $stmt = $db->prepare("
            SELECT id, type, title, message, action_url, is_read, created_at 
            FROM notifications 
            WHERE business_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        // Use bindValue because PDO gets weird with LIMIT parameters sometimes
        $stmt->bindValue(1, $biz_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll();

        // Format dates before sending to frontend
        foreach ($notifications as &$n) {
            // Using your existing helper format
            $n['formatted_date'] = fmt_date($n['created_at'], 'd/m/Y, H:i'); 
            // Sanitize variables for UI safety
            $n['title']   = e($n['title']);
            $n['message'] = e($n['message']);
        }

        json_response([
            'success'       => true, 
            'notifications' => $notifications
        ]);
        break;

    /* ══════════════════════════════════════════
     *  ACTION: MARK AS READ
     *  State mutator (Requires POST & CSRF)
     * ══════════════════════════════════════════ */
    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed. Must be POST.'], 405);
        }

        // Validate CSRF from payload (usually sent in JS fetch body or headers)
        $posted_csrf = $_POST['csrf_token'] ?? json_decode(file_get_contents('php://input'), true)['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_csrf)) {
            json_response(['error' => 'Security token mismatch.'], 403);
        }

        $notif_id = $_POST['id'] ?? json_decode(file_get_contents('php://input'), true)['id'] ?? 'all';

        if ($notif_id === 'all') {
            // Mark every unread notification as read
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE business_id = ? AND is_read = 0");
            $stmt->execute([$biz_id]);
        } else {
            // Mark a specific single notification as read
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE business_id = ? AND id = ?");
            $stmt->execute([$biz_id, (int)$notif_id]);
        }

        json_response(['success' => true]);
        break;

    /* ══════════════════════════════════════════
     *  DEFAULT: UNKNOWN ACTION
     * ══════════════════════════════════════════ */
    default:
        json_response(['error' => 'Invalid action requested.'], 400);
        break;
}