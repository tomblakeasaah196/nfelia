<?php
/**
 * Records Books in Ops: the team makes a client's book when they ask (or
 * when support needs one). Tom's rule: Support makes it and it goes to the
 * owner; only an Admin can also download it. The owner is told every time,
 * with who made it and why (records.team_built), and everything is in the
 * audit log.
 *
 *   GET  records                    books made lately, and finding a client
 *   GET  records/new                ?q= finds a client; ?client= the wizard for them
 *   POST records                    make it (client, sheets, period, reason)
 *   GET  records/{id}               how it is going, and what is in it
 *   GET  records/{id}/status        the same, for the page while it waits (JSON)
 *   GET  records/{id}/download      the file (Admins: exports.download)
 */
class OpsRecordsController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        require_once BASE_PATH . '/app/helpers/records.php';
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $rows = $this->db->query("SELECT rb.*, b.name AS business_name, s.full_name AS staff_name
                                  FROM records_books rb JOIN businesses b ON b.id = rb.business_id LEFT JOIN ops_staff s ON s.id = rb.staff_id
                                  WHERE rb.kind = 'book' AND rb.status <> 'cancelled' ORDER BY rb.id DESC LIMIT 40")->fetchAll();
        ops_render('records/index', ['rows' => $rows], 'Records Books');
    }

    public function create_form(): void
    {
        $client = (int)($_GET['client'] ?? 0);
        if (!$client) {
            $q = trim((string)($_GET['q'] ?? ''));
            $found = [];
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $this->db->prepare("SELECT b.id, b.name, b.email, b.subdomain, (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' LIMIT 1) AS owner
                                            FROM businesses b WHERE b.name LIKE ? OR b.email LIKE ? OR b.subdomain LIKE ? OR b.id = ?
                                               OR EXISTS (SELECT 1 FROM users u WHERE u.business_id = b.id AND (u.full_name LIKE ? OR u.email LIKE ?))
                                            ORDER BY b.name LIMIT 20");
                $stmt->execute([$like, $like, $like, ctype_digit($q) ? (int)$q : 0, $like, $like]);
                $found = $stmt->fetchAll();
            }
            ops_render('records/find', ['q' => $q, 'found' => $found], 'Make a Records Book');
        }
        $b = records_business($this->db, $client) ?? ops_not_found();
        $sheets = records_sheets();
        $counts = [];
        foreach ($sheets as $key => $sheet) {
            if (!isset($sheet['line_of'])) $counts[$key] = records_sheet_count($this->db, $sheet, $client, null, null);
        }
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$client]);
        $sub = $stmt->fetch() ?: null;
        ops_render('records/new', ['b' => $b, 'sheets' => $sheets, 'counts' => $counts, 'sub' => $sub,
                                   'can_download' => ops_can('exports.download')], 'Records Book for ' . $b['name']);
    }

    public function create(): void
    {
        $client = (int)($_POST['client'] ?? 0);
        $b = records_business($this->db, $client) ?? ops_not_found();
        $all = array_keys(array_filter(records_sheets(), fn($s) => !isset($s['line_of'])));
        $sheets = array_values(array_intersect($all, (array)($_POST['sheets'] ?? [])));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $back = 'records/new?client=' . $client;
        if (!$sheets) {
            ops_flash('error', 'Tick at least one kind of record.');
            ops_redirect($back);
        }
        if (mb_strlen($reason) < 5) {
            ops_flash('error', 'Say why you are making it. ' . $b['name'] . ' is told, with your name.');
            ops_redirect($back);
        }
        [$from, $to] = records_period_from($_POST);
        $cells = records_estimate_cells($this->db, $client, $sheets, $from, $to);
        if ($cells > records_max_cells()) {
            ops_flash('error', 'That would be about ' . number_format($cells) . ' cells, more than one book holds (' . number_format(records_max_cells()) . '). Choose a shorter period.');
            ops_redirect($back);
        }
        // Support sends it to the owner; an Admin chooses
        $send = !ops_can('exports.download') || !empty($_POST['send_to_owner']);
        $id = records_queue($this->db, $client, [
            'charge' => 'team', 'sheets' => $sheets, 'from' => $from, 'to' => $to,
            'staff_id' => (int)$this->me['id'], 'reason' => $reason, 'send_to_owner' => $send,
        ]);
        records_build_later($id);
        ops_audit('records.made', 'Made a Records Book of ' . $b['name'] . ($send ? ' for the owner' : '') . ': ' . $reason,
                  ['business' => $client, 'type' => 'records_book', 'id' => $id, 'data' => ['sheets' => $sheets, 'from' => $from, 'to' => $to]]);
        ops_flash('success', 'Making it now. ' . $b['name'] . '\'s owner is told when it is ready' . ($send ? ', and it is in their Your records.' : '.'));
        ops_redirect('records/' . $id);
    }

    public function show(string $id): void
    {
        $book = $this->book((int)$id);
        $downloads = $this->db->prepare("SELECT a.created_at, s.full_name FROM ops_audit a LEFT JOIN ops_staff s ON s.id = a.staff_id
                                         WHERE a.action = 'records.downloaded' AND a.target_type = 'records_book' AND a.target_id = ? ORDER BY a.id DESC");
        $downloads->execute([$book['id']]);
        ops_render('records/show', ['book' => $book, 'sheets' => records_sheets(), 'downloads' => $downloads->fetchAll(),
                                    'can_download' => ops_can('exports.download')], 'Records Book · ' . $book['business_name']);
    }

    public function status(string $id): void
    {
        $book = $this->book((int)$id);
        ops_json(['status' => $book['status'], 'ready' => $book['status'] === 'ready']);
    }

    public function download(string $id): void
    {
        $book = $this->book((int)$id);
        if (!records_file($book)) {
            ops_flash('error', 'That book\'s file is no longer kept.');
            ops_redirect('records/' . $book['id']);
        }
        ops_audit('records.downloaded', 'Downloaded ' . $book['business_name'] . '\'s Records Book of ' . date('j M Y', strtotime((string)$book['created_at'])),
                  ['business' => (int)$book['business_id'], 'type' => 'records_book', 'id' => (int)$book['id']]);
        records_send($this->db, $book);
    }

    private function book(int $id): array
    {
        $stmt = $this->db->prepare("SELECT rb.*, b.name AS business_name, s.full_name AS staff_name
                                    FROM records_books rb JOIN businesses b ON b.id = rb.business_id LEFT JOIN ops_staff s ON s.id = rb.staff_id
                                    WHERE rb.id = ? AND rb.kind = 'book'");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ops_not_found();
    }
}
