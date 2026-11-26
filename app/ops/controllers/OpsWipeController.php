<?php
/**
 * A fresh start for a client, for Admins only (app/ops/wipe.php).
 *
 *   GET  clients/{id}/wipe   what each part holds, the form, and past wipes          clients.wipe
 *   POST clients/{id}/wipe   wipe: password, "WIPE <link>", a reason, their request    clients.wipe
 *   GET  wipes/{id}/copy     download what a wipe took, while it is kept              clients.wipe
 *   GET  wipes/{id}/proof    the client's written request                              clients.wipe
 */
require_once BASE_PATH . '/app/ops/wipe.php';

class OpsWipeController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function form(string $id): void
    {
        $b = $this->client((int)$id);
        ops_render('clients/wipe', [
            'b' => $b, 'counts' => wipe_counts($this->db, (int)$b['id']), 'phrase' => wipe_phrase($b),
            'past' => wipe_history($this->db, (int)$b['id']), 'old' => $_SESSION['ops_wipe_old'][(int)$b['id']] ?? [],
        ], 'Wipe ' . $b['name']);
    }

    public function wipe(string $id): void
    {
        $b      = $this->client((int)$id);
        $back   = 'clients/' . $b['id'] . '/wipe';
        $parts  = wipe_parts_clean((array)($_POST['parts'] ?? []));
        $reason = trim((string)($_POST['reason'] ?? ''));
        // Ticks and the reason come back with the form if anything is wrong
        $_SESSION['ops_wipe_old'][(int)$b['id']] = ['parts' => $parts, 'reason' => $reason];

        if (!password_verify((string)($_POST['password'] ?? ''), (string)($this->me['password_hash'] ?? ''))) {
            ops_audit('client.wipe_refused', 'Wrong password when wiping ' . $b['name'], ['business' => (int)$b['id'], 'type' => 'business', 'id' => (int)$b['id']]);
            $this->refuse($back, 'That isn\'t your Ops password. Nothing was wiped.');
        }
        if (!wipe_phrase_ok((string)($_POST['phrase'] ?? ''), $b)) {
            $this->refuse($back, 'Type ' . wipe_phrase($b) . ' exactly as shown. Nothing was wiped.');
        }
        if ($problem = wipe_parts_problem($parts)) {
            $this->refuse($back, $problem);
        }
        if (mb_strlen($reason) < 10) {
            $this->refuse($back, 'Say why, and who asked, in a few words. It stays in the audit log.');
        }
        try {
            $proof = wipe_store_proof($_FILES['proof'] ?? [], (int)$b['id']);
        } catch (DomainException $e) {
            $this->refuse($back, $e->getMessage());
        }

        try {
            $done = wipe_run($this->db, $b, $parts, $this->me, $reason, $proof);
        } catch (Throwable $e) {
            if ($proof) {
                @unlink(BASE_PATH . '/' . $proof);
            }
            errors_report_throwable($e, ['job' => 'Ops: wipe a client', 'business' => (int)$b['id']]);
            $this->refuse($back, $e instanceof DomainException || $e instanceof RuntimeException
                ? $e->getMessage() : 'Something went wrong, so nothing was wiped. It is in Errors.');
        }
        unset($_SESSION['ops_wipe_old'][(int)$b['id']]);

        $what = wipe_parts_text($parts);
        $told = notify('account.wiped', (int)$b['id'], ['parts' => $what, 'numbers' => in_array('sales', $parts, true)], ['dedupe' => 'wipe:' . $done['id']]);
        if ($told) {
            $this->db->prepare("UPDATE client_wipes SET owner_told_at = NOW() WHERE id = ?")->execute([$done['id']]);
        }
        $summary = 'Wiped ' . $b['name'] . "'s " . $what . ' (' . number_format($done['total']) . ' records): ' . $reason;
        ops_audit('client.wipe', $summary, ['business' => (int)$b['id'], 'type' => 'client_wipe', 'id' => $done['id'],
                  'data' => ['parts' => $parts, 'reason' => $reason, 'counts' => $done['counts'], 'proof' => $proof !== null]]);
        ops_alert($this->db, 'client_wipe', $this->me['full_name'] . ' wiped ' . $b['name'] . "'s " . $what,
                  number_format($done['total']) . ' records went. A copy is kept for ' . WIPE_KEEP_DAYS . ' days.',
                  ops_url($back), 'wipe:' . $done['id'], ['permission' => 'clients.wipe']);

        ops_flash('success', 'Wiped ' . number_format($done['total']) . ' records from ' . $b['name'] . '. A copy is kept until '
                  . date('j M Y', strtotime($done['expires'])) . ($told ? '. The owner has been emailed.' : '. Nobody active on their account could be told.'));
        ops_redirect($back . '#past');
    }

    public function copy(string $id): void
    {
        $w = $this->find((int)$id);
        $file = wipe_copy_file($w);
        if (!$file) {
            ops_flash('error', 'That copy is no longer kept.');
            ops_redirect('clients/' . $w['business_id'] . '/wipe#past');
        }
        ops_audit('client.wipe_copy', 'Downloaded the copy of what was wiped from ' . $w['business_name'] . ' on ' . date('j M Y', strtotime((string)$w['created_at'])),
                  ['business' => (int)$w['business_id'], 'type' => 'client_wipe', 'id' => (int)$w['id']]);
        require_once BASE_PATH . '/app/helpers/records.php';
        records_stream($file, $w['slug'] . '-wiped-' . date('Y-m-d', strtotime((string)$w['created_at'])) . '.zip', 'application/zip');
    }

    public function proof(string $id): void
    {
        $w = $this->find((int)$id);
        if (!$w['proof_path']) {
            ops_not_found();
        }
        send_private_proof($w['proof_path']);
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function refuse(string $back, string $message): never
    {
        ops_flash('error', $message);
        ops_redirect($back . '#wipe');
    }

    private function client(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ops_not_found();
    }

    private function find(int $id): array
    {
        $stmt = $this->db->prepare("SELECT w.*, b.name AS business_name, b.slug FROM client_wipes w JOIN businesses b ON b.id = w.business_id WHERE w.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ops_not_found();
    }
}
