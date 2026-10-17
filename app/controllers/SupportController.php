<?php
/**
 * Nfelia — Support & User Guide Controller
 * NFELIA AUDIT FIX — H04 — Fixed relative view paths to use BASE_PATH — 2026-06-03
 * NFELIA AUDIT FIX — C07 — Fixed notification insert: link -> action_url — 2026-06-03
 * NFELIA AUDIT FIX — L04 — Replaced direct $_SESSION flash with flash() helper — 2026-06-03
 */
class SupportController {

    private $db;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
    }

    /* ══════════════════════════════════════════
     *  USER GUIDE
     * ══════════════════════════════════════════ */
    public function guide() {
        $user     = auth_user();
        $business = auth_business();
        $title    = 'User Guide';
        $view_file = BASE_PATH . '/views/support/guide.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  CONTACT SUPPORT PAGE
     * ══════════════════════════════════════════ */
    public function contact() {
        $biz_id   = auth_business_id();
        $user     = auth_user();
        $business = auth_business();

        $stmt = $this->db->prepare(
            "SELECT * FROM support_tickets WHERE business_id = ? ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$biz_id]);
        $tickets = $stmt->fetchAll();

        $title     = 'Contact Support';
        $view_file = BASE_PATH . '/views/support/contact.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  SUBMIT TICKET — POST
     * ══════════════════════════════════════════ */
    public function submit_ticket() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('contact-support'); return; }
        csrf_verify();

        $biz_id  = auth_business_id();
        $user_id = auth_user()['id'];
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$subject || !$message) {
            flash('error', 'Please fill in both subject and message.');
            redirect('contact-support');
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO support_tickets (business_id, user_id, subject, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$biz_id, $user_id, $subject, $message]);

        // Notify admin (business_id = 1)
        try {
            $biz = auth_business();
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (1, 'general', 'New Support Ticket', ?, ?, NOW())
            ")->execute([
                e($biz['name']) . ': ' . e($subject),
                APP_URL . '/admin/support'
            ]);
        } catch (\Exception $e) {
            error_log('[Nfelia] Support ticket notification failed: ' . $e->getMessage());
        }

        flash('success', "Your message has been sent. We'll respond shortly.");
        redirect('contact-support');
    }
}
