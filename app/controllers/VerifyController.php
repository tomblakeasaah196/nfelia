<?php
/**
 * nfelia.com/verify: anyone (a bank, a landlord, a buyer) checks a Records
 * Book or one record in it, and can ask the business for the full document.
 * No sign-in. What a code shows is in records_verify_lookup()
 * (app/helpers/records.php): that it is genuine, the key facts, and what
 * changed since the book was made; never a customer's phone, email or
 * address.
 *
 *   GET  verify                      type a code, or check a file
 *   POST verify                      a typed code: on to its page
 *   GET  verify/{code}               what the code is
 *   POST verify/check                a file: is it a book Nfelia made, unchanged?
 *   POST verify/{code}/ask           ask the business for the full document
 *   GET  verify/confirm/{token}      the person who asked confirms their email
 *   GET  verify/file/{token}         the document, once the business said yes
 */
class VerifyController {

    private PDO $db;

    public function __construct() {
        require_once BASE_PATH . '/app/helpers/records.php';
        require_once BASE_PATH . '/app/helpers/record_requests.php';
        $this->db = get_db();
        if (empty($_SESSION['csrf_token'])) {
            csrf_regenerate();
        }
    }

    public function handle(string $action, ?string $param): void {
        $post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($post && !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            $this->page(['mode' => 'start', 'problem' => 'That form had expired. Try again.'], 419);
        }
        match (true) {
            $action === 'index' && !$post                     => $this->page(['mode' => 'start']),
            $action === 'index' && $post                      => $this->typed(),
            $action === 'check' && $post                      => $this->check_file(),
            $action === 'confirm' && $param !== null          => $this->confirm($param),
            $action === 'file' && $param !== null             => $this->file($param),
            $param === 'ask' && $post                         => $this->ask($action),
            $param === null && !$post                         => $this->code($action),
            default                                           => $this->page(['mode' => 'missing'], 404),
        };
    }

    /** A typed code, written however: on to its own page. */
    private function typed(): never {
        $p = records_parse_code((string)($_POST['code'] ?? ''));
        if (!$p) {
            $this->page(['mode' => 'start', 'problem' => 'That doesn\'t look like a Nfelia code. It is 10 letters and numbers, like K7Q2M-XA9WT, sometimes with more after it.'], 422);
        }
        $code = $p['letter'] === null ? $p['book'] : records_row_code($p['book'], $p['letter'], (int)$p['id']);
        header('Location: ' . records_verify_url($code), true, 303);
        exit;
    }

    private function code(string $code): never {
        if (!$this->allowed('verify_code', 120)) {
            $this->page(['mode' => 'start', 'problem' => 'Too many checks from your connection in the last hour. Wait a little and try again.'], 429);
        }
        $found = records_verify_lookup($this->db, rawurldecode($code));
        if (!$found) {
            $this->page(['mode' => 'unknown', 'code' => mb_substr(rawurldecode($code), 0, 40)], 404);
        }
        $this->page(['mode' => 'found', 'found' => $found, 'sent' => !empty($_GET['asked'])]);
    }

    /** Is this file a Records Book exactly as Nfelia made it? */
    private function check_file(): never {
        if (!$this->allowed('verify_file', 30)) {
            $this->page(['mode' => 'start', 'problem' => 'Too many files checked from your connection in the last hour. Wait a little and try again.'], 429);
        }
        $f = $_FILES['book'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $this->page(['mode' => 'start', 'problem' => 'Choose the Excel file (.xlsx) to check.'], 422);
        }
        if ($f['size'] > 50 * 1024 * 1024) {
            $this->page(['mode' => 'start', 'problem' => 'That file is bigger than any Records Book we make.'], 422);
        }
        // Only its fingerprint is used: the file itself is never kept
        $sha = hash_file('sha256', $f['tmp_name']);
        @unlink($f['tmp_name']);
        $stmt = $this->db->prepare("SELECT code FROM records_books WHERE sha256 = ? AND kind = 'book' AND status IN ('ready', 'expired') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sha]);
        $code = $stmt->fetchColumn();
        $found = $code ? records_verify_lookup($this->db, (string)$code) : null;
        $this->page(['mode' => $found ? 'found' : 'file_unknown', 'found' => $found, 'file_match' => (bool)$found, 'file_name' => (string)$f['name']]);
    }

    /** Someone asks the business for the full document. */
    private function ask(string $code): never {
        $found = records_verify_lookup($this->db, rawurldecode($code));
        if (!$found || !$found['kept']) {
            $this->page(['mode' => 'unknown', 'code' => mb_substr(rawurldecode($code), 0, 40)], 404);
        }
        if (!empty($_POST['website'])) {   // a field people never see: only robots fill it
            header('Location: ' . records_verify_url($found['code']) . '?asked=1', true, 303);
            exit;
        }
        [$ok, $message] = record_request_create($this->db, $found, $_POST, client_ip(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$ok) {
            $this->page(['mode' => 'found', 'found' => $found, 'ask_problem' => $message, 'form' => $_POST], 422);
        }
        $_SESSION['verify_sent'] = $message;
        header('Location: ' . records_verify_url($found['code']) . '?asked=1', true, 303);
        exit;
    }

    /** The person who asked confirms their email: the Nfelia team takes it from here. */
    private function confirm(string $token): never {
        $r = record_request_confirm($this->db, $token);
        if (!$r) {
            $this->page(['mode' => 'notice', 'title' => 'This link doesn\'t work', 'body' => 'It may have expired (we keep an unconfirmed request for a day). Ask again from the code\'s page.'], 404);
        }
        $this->page(['mode' => 'notice', 'title' => 'Thank you, your request is in',
                     'body' => 'We will ask ' . $r['business_name'] . ' about ' . $r['label'] . '. If they say yes, we email you a link to download it. Nothing is shared unless they agree.'], 200);
    }

    /** The document, once the business said yes. */
    private function file(string $token): never {
        $r = record_request_by_token($this->db, $token);
        if (!$r) {
            $this->page(['mode' => 'notice', 'title' => 'This link doesn\'t work', 'body' => 'Check it was copied whole, or ask again at nfelia.com/verify.'], 404);
        }
        [$title, $body] = record_request_download($this->db, $r);   // sends the file and stops when it can
        $this->page(['mode' => 'notice', 'title' => $title, 'body' => $body], 410);
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function allowed(string $bucket, int $per_hour): bool {
        return throttle_allows($bucket, client_ip(), $per_hour, 60);
    }

    private function page(array $v, int $status = 200): never {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $v['sent_message'] = $_SESSION['verify_sent'] ?? null;
        unset($_SESSION['verify_sent']);
        extract($v, EXTR_SKIP);
        $roles = RECORD_REQUEST_ROLES;
        require BASE_PATH . '/views/verify/page.php';
        exit;
    }
}
