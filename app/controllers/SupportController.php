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
        $ticket_id = (int)$this->db->lastInsertId();

        // The Nfelia team hears about it (app/helpers/notify.php).
        notify('platform.ticket', NOTIFY_PLATFORM_BUSINESS_ID, [
            'ticket_id' => $ticket_id, 'business' => auth_business()['name'], 'subject' => $subject,
        ], ['dedupe' => (string)$ticket_id]);

        flash('success', "Your message has been sent. We'll respond shortly.");
        redirect('contact-support');
    }
}
