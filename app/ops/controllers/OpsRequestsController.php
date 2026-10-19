<?php
/**
 * Requests for documents (Admins only, Tom's decision): someone checked a
 * code at nfelia.com/verify and asked the business for the full document
 * (app/helpers/record_requests.php). One click asks the client; junk is
 * turned down here without bothering them.
 *
 *   GET  requests                 waiting first, then the rest
 *   GET  requests/{id}            one request: who, why, what, and its trail
 *   POST requests/{id}/ask        ask the client (bell, phone, email)
 *   POST requests/{id}/decline    turn it down, with a note they see
 */
class OpsRequestsController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        require_once BASE_PATH . '/app/helpers/records.php';
        require_once BASE_PATH . '/app/helpers/record_requests.php';
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $base = "SELECT rq.*, b.name AS business_name FROM record_requests rq JOIN businesses b ON b.id = rq.business_id";
        $waiting = $this->db->query($base . " WHERE rq.status IN ('new', 'asked') ORDER BY rq.status = 'asked', rq.id")->fetchAll();
        $done = $this->db->query($base . " WHERE rq.status IN ('approved', 'declined', 'expired') ORDER BY rq.id DESC LIMIT 30")->fetchAll();
        ops_render('requests/index', ['waiting' => $waiting, 'done' => $done, 'roles' => RECORD_REQUEST_ROLES], 'Document requests');
    }

    public function show(string $id): void
    {
        $r = $this->request((int)$id);
        $staff = fn($sid) => $sid ? (ops_staff_by_id((int)$sid)['full_name'] ?? 'Someone') : null;
        ops_render('requests/show', ['r' => $r, 'roles' => RECORD_REQUEST_ROLES, 'asked_by' => $staff($r['asked_by']), 'declined_by' => $staff($r['decided_by_staff'])],
                   'Request from ' . ($r['organisation'] ?: $r['name']));
    }

    public function ask(string $id): void
    {
        $r = $this->request((int)$id);
        if (!record_request_ask($this->db, $r, $this->me)) {
            ops_flash('error', 'Someone already dealt with this request.');
            ops_redirect('requests/' . (int)$r['id']);
        }
        ops_flash('success', 'Asked. ' . $r['business_name'] . '\'s owner has it in the app, on their phone and by email.');
        ops_redirect('requests/' . (int)$r['id']);
    }

    public function decline(string $id): void
    {
        $r = $this->request((int)$id);
        $note = trim((string)($_POST['note'] ?? ''));
        if (mb_strlen($note) < 5) {
            ops_flash('error', 'Say why, in words they will understand: they get it by email.');
            ops_redirect('requests/' . (int)$r['id']);
        }
        if (!record_request_decide($this->db, $r, false, $note, ['staff' => (int)$this->me['id']])) {
            ops_flash('error', 'Someone already dealt with this request.');
            ops_redirect('requests/' . (int)$r['id']);
        }
        ops_audit('records.request_declined', 'Turned down ' . ($r['organisation'] ?: $r['name']) . '\'s request for ' . $r['business_name'] . '\'s ' . $r['label'] . ': ' . $note,
                  ['business' => (int)$r['business_id'], 'type' => 'record_request', 'id' => (int)$r['id']]);
        ops_flash('success', 'Turned down. ' . $r['name'] . ' has been told.');
        ops_redirect('requests');
    }

    private function request(int $id): array
    {
        $r = record_request($this->db, $id);
        if (!$r || $r['status'] === 'unconfirmed') ops_not_found();
        return $r;
    }
}
