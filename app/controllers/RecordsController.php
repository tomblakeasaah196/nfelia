<?php
/**
 * Your records (the owner): the Records Book, the free plain copy, and
 * people who asked to see a record (app/helpers/records.php,
 * app/helpers/record_requests.php).
 *
 *   GET  records                  the page: make a book, past books, the plain copy, requests
 *   POST records/make             a book: free once in a paid month, otherwise paid for first
 *   POST records/plain            the plain copy (CSV files in a ZIP), free for everyone
 *   GET  records/status/{id}      how a build is going (JSON, for the page while it waits)
 *   GET  records/download/{id}    a book or plain copy of this business
 *   GET  records/request/{id}     someone's request, to approve or decline
 *   POST records/answer/{id}      approve or decline it
 *
 * Open while the plan is paused too (subscription_check.php lets it
 * through): records belong to the business whatever it pays.
 */
class RecordsController {

    private PDO $db;
    private int $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        require_once BASE_PATH . '/app/helpers/records.php';
        require_once BASE_PATH . '/app/helpers/record_requests.php';
        $this->db     = get_db();
        $this->biz_id = (int)auth_business_id();
        if (!is_owner()) {
            flash('error', 'Only the owner can take the business\'s records out of Nfelia.');
            redirect('dashboard');
        }
    }

    public function index() {
        $offer = records_offer($this->db, $this->biz_id);
        $stmt  = $this->db->prepare("SELECT * FROM records_books WHERE business_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 30");
        $stmt->execute([$this->biz_id]);
        $rows  = $stmt->fetchAll();
        $books = array_values(array_filter($rows, fn($b) => $b['kind'] === 'book'));
        $plain = array_values(array_filter($rows, fn($b) => $b['kind'] === 'raw'));
        $stmt  = $this->db->prepare("SELECT * FROM record_requests WHERE business_id = ? AND status IN ('asked', 'approved', 'declined', 'expired') ORDER BY id DESC LIMIT 20");
        $stmt->execute([$this->biz_id]);
        $requests = $stmt->fetchAll();
        $stmt = $this->db->prepare("SELECT * FROM subscription_payments WHERE business_id = ? AND kind = 'book' AND status = 'pending_verification' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->biz_id]);
        $checking = $stmt->fetch() ?: null;
        $sheets = records_sheets();
        $month_price = billing_prices($this->db, $this->biz_id)['monthly'];

        $title     = 'Your records';
        $view_file = BASE_PATH . '/views/records/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /** A book: free when this paid month's is unused, otherwise paid for first. */
    public function make() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('records'); }
        csrf_verify();
        $all = array_keys(array_filter(records_sheets(), fn($s) => !isset($s['line_of'])));
        $sheets = array_values(array_intersect($all, (array)($_POST['sheets'] ?? [])));
        if (!$sheets) {
            flash('error', 'Tick at least one kind of record to put in your book.');
            redirect('records');
        }
        [$from, $to] = records_period_from($_POST);
        if ($this->building()) {
            flash('warning', 'We are still making your last book. It will be ready in a minute or two.');
            redirect('records');
        }
        if (records_estimate_cells($this->db, $this->biz_id, $sheets, $from, $to) > records_max_cells()) {
            flash('error', 'That is a lot of records for one book! Choose a shorter period (this year, say), or fewer kinds of records.');
            redirect('records');
        }
        $offer = records_offer($this->db, $this->biz_id);
        $user  = auth_user();
        if ($offer['free']) {
            $id = records_queue($this->db, $this->biz_id, ['charge' => 'free', 'sheets' => $sheets, 'from' => $from, 'to' => $to, 'requested_by' => (int)$user['id']]);
            records_build_later($id);
            flash('success', 'We are making your Records Book now. It takes a minute; we\'ll tell you when it is ready.');
            redirect('records#book-' . $id);
        }

        // Paid for first: it waits until the money comes in
        $id = records_queue($this->db, $this->biz_id, [
            'charge' => 'paid', 'status' => 'awaiting_payment', 'sheets' => $sheets, 'from' => $from, 'to' => $to, 'requested_by' => (int)$user['id'],
            'amount' => $offer['price'], 'currency' => $offer['currency'],
        ]);
        if ($offer['method'] === 'mobile_money') {
            redirect('momo/checkout?kind=book&book=' . $id);
        }
        if ($offer['method'] === 'paystack') {
            require_once BASE_PATH . '/app/controllers/PaystackController.php';
            [$ok, $where] = PaystackController::start_book($this->db, $user, $this->biz_id, $id, $offer['price'], $offer['currency']);
            if ($ok) {
                header('Location: ' . $where);
                exit;
            }
            $this->db->prepare("UPDATE records_books SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            flash('error', 'We couldn\'t start the payment: ' . $where . ' Try again in a moment.');
            redirect('records');
        }
        $this->db->prepare("UPDATE records_books SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        flash('warning', 'Paying for a Records Book is arranged with our team where you are. Message support and we will make it for you.');
        redirect('records');
    }

    /** The plain copy: free, once a day. */
    public function plain() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('records'); }
        csrf_verify();
        if (!throttle_allows('records_plain', (string)$this->biz_id, 3, 60 * 24)) {
            flash('warning', 'You have made a few plain copies today. The last one is below; try again tomorrow if you need a new one.');
            redirect('records');
        }
        $id = records_queue($this->db, $this->biz_id, ['kind' => 'raw', 'requested_by' => (int)auth_user()['id']]);
        records_build_later($id);
        flash('success', 'We are making your plain copy now. It will be below in a moment.');
        redirect('records#plain');
    }

    /** How a build is going, for the page that waits on it. */
    public function status($id = null) {
        $book = $this->book((int)$id);
        json_response([
            'status'   => $book['status'],
            'ready'    => $book['status'] === 'ready',
            'download' => $book['status'] === 'ready' ? APP_URL . '/records/download/' . (int)$book['id'] : null,
        ]);
    }

    public function download($id = null) {
        $book = $this->book((int)$id);
        if ($book['status'] !== 'ready') {
            flash('error', $book['status'] === 'expired' ? 'That file is no longer kept. Make a new one.' : 'That one isn\'t ready yet.');
            redirect('records');
        }
        records_send($this->db, $book);
    }

    /** Someone's request for a record: who, why, and the two buttons. */
    public function request($id = null) {
        $r = $this->owned_request((int)$id);
        $answer = in_array($_GET['answer'] ?? '', ['approve', 'decline'], true) ? $_GET['answer'] : null;
        $roles = RECORD_REQUEST_ROLES;
        $title     = 'A request for your records';
        $view_file = BASE_PATH . '/views/records/request.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    public function answer($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('records'); }
        csrf_verify();
        $r = $this->owned_request((int)$id);
        $approve = ($_POST['answer'] ?? '') === 'approve';
        if (!record_request_decide($this->db, $r, $approve, (string)($_POST['note'] ?? ''), ['user' => (int)auth_user()['id']])) {
            flash('warning', 'This request was already answered.');
            redirect('records/request/' . (int)$r['id']);
        }
        flash('success', $approve ? 'Shared. ' . $r['name'] . ' has a link to download it for the next ' . RECORD_REQUEST_LINK_DAYS . ' days.'
                                  : 'Declined. ' . $r['name'] . ' has been told, politely.');
        redirect('records#requests');
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function book(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM records_books WHERE id = ? AND business_id = ?");
        $stmt->execute([$id, $this->biz_id]);
        $book = $stmt->fetch();
        if (!$book) { not_found(); }
        return $book;
    }

    private function owned_request(int $id): array {
        $r = record_request($this->db, $id);
        if (!$r || (int)$r['business_id'] !== $this->biz_id || $r['status'] === 'unconfirmed' || $r['status'] === 'new') { not_found(); }
        return $r;
    }

    /** A book of this business being made right now. */
    private function building(): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM records_books WHERE business_id = ? AND kind = 'book' AND status IN ('queued', 'building')");
        $stmt->execute([$this->biz_id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
