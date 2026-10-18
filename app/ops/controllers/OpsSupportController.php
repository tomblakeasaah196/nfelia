<?php
/**
 * Support (Admin and Support): tickets clients send from Nfelia, the
 * ratings they leave in the app, and shoppers' reviews of shops.
 *
 *   GET  support                 tickets, open first (?status=open|replied|closed|all)
 *   GET  support/{id}            one ticket, with the client beside it
 *   POST support/{id}/reply      reply (the person who wrote in hears, in the app and by email), and its status
 *   GET  feedback                ratings and comments from inside the app
 *   GET  reviews                 shoppers' reviews (?show=hidden|low)
 *   POST reviews/{id}            hide one that breaks the rules, or show it again
 */
class OpsSupportController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = get_db();
    }

    public function index(): void
    {
        $status = in_array($_GET['status'] ?? '', ['open', 'replied', 'closed', 'all'], true) ? $_GET['status'] : 'open';
        $stmt = $this->db->prepare("
            SELECT st.*, b.name AS business_name, u.full_name AS user_name
            FROM support_tickets st LEFT JOIN businesses b ON b.id = st.business_id LEFT JOIN users u ON u.id = st.user_id
            " . ($status === 'all' ? '' : 'WHERE st.status = ?') . "
            ORDER BY FIELD(st.status, 'open', 'replied', 'closed'), st.created_at DESC LIMIT 200");
        $stmt->execute($status === 'all' ? [] : [$status]);
        $counts = $this->db->query("SELECT status, COUNT(*) FROM support_tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        ops_render('support/index', ['rows' => $stmt->fetchAll(), 'status' => $status, 'counts' => $counts], 'Support');
    }

    public function show(string $id): void
    {
        $t = $this->ticket((int)$id);
        $stmt = $this->db->prepare("SELECT id, subject, status, created_at FROM support_tickets WHERE business_id = ? AND id <> ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$t['business_id'], $t['id']]);
        ops_render('support/show', ['t' => $t, 'others' => $stmt->fetchAll()], 'Ticket #' . $t['id']);
    }

    public function reply(string $id): void
    {
        $t = $this->ticket((int)$id);
        $reply  = trim((string)($_POST['reply'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['replied', 'closed', 'open'], true) ? $_POST['status'] : 'replied';
        if ($reply === '' && $status === $t['status']) {
            ops_flash('error', 'Write a reply, or choose a different status.');
            ops_redirect('support/' . $t['id']);
        }
        if ($reply !== '') {
            $this->db->prepare("UPDATE support_tickets SET admin_reply = ?, status = ?, replied_at = NOW() WHERE id = ?")->execute([$reply, $status, $t['id']]);
            notify('support.reply', (int)$t['business_id'], ['ticket_id' => (int)$t['id'], 'subject' => $t['subject'], 'reply' => $reply],
                   ['users' => [(int)$t['user_id']], 'dedupe' => $t['id'] . ':' . md5($reply)]);
            ops_audit('support.reply', 'Replied to "' . mb_substr($t['subject'], 0, 80) . '" from ' . ($t['business_name'] ?? 'a client'),
                      ['business' => (int)$t['business_id'], 'type' => 'ticket', 'id' => (int)$t['id']]);
            ops_flash('success', 'Reply sent. ' . ($t['user_name'] ?? 'They') . ' will see it in Nfelia and by email.');
        } else {
            $this->db->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$status, $t['id']]);
            ops_audit('support.status', 'Marked "' . mb_substr($t['subject'], 0, 80) . '" ' . $status, ['business' => (int)$t['business_id'], 'type' => 'ticket', 'id' => (int)$t['id']]);
            ops_flash('success', 'Marked ' . $status . '.');
        }
        ops_redirect('support/' . $t['id']);
    }

    public function feedback(): void
    {
        $rows = $this->db->query("
            SELECT f.*, b.name AS business_name, u.full_name AS user_name
            FROM feedback f LEFT JOIN businesses b ON b.id = f.business_id LEFT JOIN users u ON u.id = f.user_id
            ORDER BY f.created_at DESC LIMIT 200")->fetchAll();
        $dist = $this->db->query("SELECT rating, COUNT(*) FROM feedback GROUP BY rating")->fetchAll(PDO::FETCH_KEY_PAIR);
        ops_render('support/feedback', ['rows' => $rows, 'dist' => $dist], 'Feedback');
    }

    public function reviews(): void
    {
        $show = in_array($_GET['show'] ?? '', ['hidden', 'low'], true) ? $_GET['show'] : 'all';
        $where = match ($show) {
            'hidden' => "r.status = 'hidden'",
            'low'    => "r.status IN ('published', 'hidden') AND r.rating <= 2",
            default  => "r.status IN ('published', 'hidden')",
        };
        $rows = $this->db->query("
            SELECT r.id, r.rating, r.comment, r.status, r.customer_name, r.submitted_at, r.hidden_at,
                   o.order_number, b.id AS business_id, b.name AS business_name, b.subdomain
            FROM reviews r JOIN orders o ON o.id = r.order_id JOIN businesses b ON b.id = r.business_id
            WHERE {$where} ORDER BY r.submitted_at DESC, r.id DESC LIMIT 200")->fetchAll();
        ops_render('support/reviews', ['rows' => $rows, 'show' => $show], 'Reviews');
    }

    public function review(string $id): void
    {
        $hide = ($_POST['hide'] ?? '') === '1';
        $stmt = $this->db->prepare("SELECT r.business_id, b.name FROM reviews r JOIN businesses b ON b.id = r.business_id WHERE r.id = ? AND r.status IN ('published', 'hidden')");
        $stmt->execute([(int)$id]);
        $r = $stmt->fetch();
        if ($r) {
            $this->db->prepare("UPDATE reviews SET status = ?, hidden_at = ? WHERE id = ?")
               ->execute([$hide ? 'hidden' : 'published', $hide ? date('Y-m-d H:i:s') : null, (int)$id]);
            storefront_purge((int)$r['business_id']);
            ops_audit($hide ? 'review.hidden' : 'review.shown', ($hide ? 'Hid' : 'Showed again') . ' a review on ' . $r['name'] . '\'s shop',
                      ['business' => (int)$r['business_id'], 'type' => 'review', 'id' => (int)$id]);
            ops_flash('success', $hide ? 'That review is hidden from the shop.' : 'That review shows on the shop again.');
        }
        ops_redirect(ops_back('reviews'));
    }

    private function ticket(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT st.*, b.name AS business_name, u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone, s.status AS plan_status
            FROM support_tickets st LEFT JOIN businesses b ON b.id = st.business_id LEFT JOIN users u ON u.id = st.user_id
            LEFT JOIN subscriptions s ON s.business_id = st.business_id WHERE st.id = ?");
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) ops_not_found();
        return $t;
    }
}
