<?php
/**
 * Mobile money payments to check (Admin and Support): the receipt a client
 * uploaded, approved (their plan runs a month) or turned down with a reason.
 *
 *   GET  payments                  waiting, oldest first, then the last 20 decided
 *   GET  payments/{id}/receipt     the uploaded receipt
 *   POST payments/{id}/approve
 *   POST payments/{id}/reject      with a reason the client sees
 */
class OpsPaymentsController
{
    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $base = "SELECT sp.*, b.name AS business_name, c.name AS country_name, c.currency_symbol, c.currency_code, c.flag_emoji,
                        (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' ORDER BY u.id LIMIT 1) AS owner_name,
                        s.full_name AS reviewer
                 FROM subscription_payments sp
                 JOIN businesses b ON b.id = sp.business_id
                 JOIN countries c ON c.id = sp.country_id
                 LEFT JOIN ops_staff s ON s.id = sp.reviewed_by_staff";
        $pending = $this->db->query($base . " WHERE sp.status = 'pending_verification' ORDER BY sp.submitted_at ASC")->fetchAll();
        $decided = $this->db->query($base . " WHERE sp.status IN ('approved', 'rejected') ORDER BY sp.reviewed_at DESC LIMIT 20")->fetchAll();
        ops_render('payments', ['pending' => $pending, 'decided' => $decided], 'Payments to check');
    }

    public function receipt(string $id): void
    {
        $stmt = $this->db->prepare("SELECT receipt_path FROM subscription_payments WHERE id = ?");
        $stmt->execute([(int)$id]);
        $path = (string)$stmt->fetchColumn();
        if ($path !== '' && !str_starts_with($path, '/')) {
            $path = BASE_PATH . '/' . $path;
        }
        $real = $path !== '' ? realpath($path) : false;
        // Only from where MoMoController keeps receipts, whatever the row says.
        $roots = array_filter([realpath(BASE_PATH . '/storage/momo_receipts'), realpath(rtrim((string)($_ENV['MOMO_RECEIPT_DIR'] ?? ''), '/') ?: '/nonexistent')]);
        $inside = $real && array_filter($roots, fn($root) => str_starts_with($real, $root . '/'));
        if (!$inside) {
            ops_render('message', ['heading' => 'No receipt', 'text' => 'The receipt file for this payment is not on the server.'], 'Receipt', 404);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($real);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            ops_render('message', ['heading' => 'Not a receipt', 'text' => 'This file is not a picture or a PDF, so it is not shown.'], 'Receipt', 415);
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: inline; filename="receipt_' . (int)$id . '"');
        readfile($real);
        exit;
    }

    public function approve(string $id): void
    {
        $p = $this->pending((int)$id);
        $db = $this->db;
        $period_end = date('Y-m-d', strtotime('+1 month'));
        try {
            $db->beginTransaction();
            $mark = $db->prepare("UPDATE subscription_payments SET status = 'approved', reviewed_at = NOW(), reviewed_by = NULL, reviewed_by_staff = ?
                                  WHERE id = ? AND status = 'pending_verification'");
            $mark->execute([$this->me['id'], $p['id']]);
            if ($mark->rowCount() !== 1) {
                $db->rollBack();
                ops_flash('error', 'Someone else decided this payment a moment ago.');
                ops_redirect('payments');
            }
            $db->prepare("
                UPDATE subscriptions SET status = 'active', plan = 'standard', monthly_amount = ?,
                       current_period_start = CURDATE(), current_period_end = ?, grace_ends_at = ?,
                       last_payment_date = CURDATE(), last_payment_ref = ?, trial_ends_at = NULL, cancelled_at = NULL, cancel_reason = NULL, updated_at = NOW()
                WHERE business_id = ?
            ")->execute([$p['amount'], $period_end, date('Y-m-d H:i:s', strtotime('+1 month +3 days')), 'MOMO_' . $p['id'], $p['business_id']]);
            $db->prepare("INSERT INTO subscription_status_log (business_id, old_status, new_status, changed_by, notes) VALUES (?, 'pending_verification', 'active', NULL, ?)")
               ->execute([$p['business_id'], 'MoMo payment approved in Nfelia Ops by ' . $this->me['full_name']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        notify('billing.paid', (int)$p['business_id'], [
            'amount_text' => $p['currency_symbol'] . number_format((float)$p['amount'], 0) . ' ' . $p['currency_code'], 'period_end' => $period_end,
        ], ['dedupe' => 'momo:' . $p['id']]);
        require_once BASE_PATH . '/app/controllers/ReferralController.php';
        ReferralController::reward_referral($db, (int)$p['business_id']);
        storefront_purge((int)$p['business_id']);
        ops_audit('payment.approved', 'Approved ' . $p['business_name'] . '\'s mobile money payment of ' . $p['currency_symbol'] . number_format((float)$p['amount'], 0),
                  ['business' => (int)$p['business_id'], 'type' => 'payment', 'id' => (int)$p['id']]);
        ops_flash('success', 'Approved. ' . $p['business_name'] . ' is paid up to ' . date('j M Y', strtotime($period_end)) . ' and has been told.');
        ops_redirect('payments');
    }

    public function reject(string $id): void
    {
        $p = $this->pending((int)$id);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            ops_flash('error', 'Say why, in words the client will understand: they see it, with a link to send it again.');
            ops_redirect('payments#payment-' . $p['id']);
        }
        $db = $this->db;
        try {
            $db->beginTransaction();
            $mark = $db->prepare("UPDATE subscription_payments SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = NULL, reviewed_by_staff = ?
                                  WHERE id = ? AND status = 'pending_verification'");
            $mark->execute([$reason, $this->me['id'], $p['id']]);
            if ($mark->rowCount() !== 1) {
                $db->rollBack();
                ops_flash('error', 'Someone else decided this payment a moment ago.');
                ops_redirect('payments');
            }
            $now = (string)$db->query("SELECT status FROM subscriptions WHERE business_id = " . (int)$p['business_id'])->fetchColumn();
            $back_to = $now === 'pending_verification' || $now === '' ? 'trial' : $now;
            $db->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE business_id = ?")->execute([$back_to, $p['business_id']]);
            $db->prepare("INSERT INTO subscription_status_log (business_id, old_status, new_status, changed_by, notes) VALUES (?, 'pending_verification', ?, NULL, ?)")
               ->execute([$p['business_id'], $back_to, 'MoMo payment turned down in Nfelia Ops by ' . $this->me['full_name'] . ': ' . $reason]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        notify('billing.momo_rejected', (int)$p['business_id'], ['reason' => $reason], ['dedupe' => 'momo:' . $p['id']]);
        ops_audit('payment.rejected', 'Turned down ' . $p['business_name'] . '\'s mobile money payment: ' . $reason,
                  ['business' => (int)$p['business_id'], 'type' => 'payment', 'id' => (int)$p['id']]);
        ops_flash('success', 'Turned down. ' . $p['business_name'] . ' has been told why.');
        ops_redirect('payments');
    }

    private function pending(int $id): array
    {
        $stmt = $this->db->prepare("SELECT sp.*, b.name AS business_name, c.currency_symbol, c.currency_code
                                    FROM subscription_payments sp JOIN businesses b ON b.id = sp.business_id JOIN countries c ON c.id = sp.country_id
                                    WHERE sp.id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) {
            ops_not_found();
        }
        if ($p['status'] !== 'pending_verification') {
            ops_flash('error', 'This payment was already ' . $p['status'] . '.');
            ops_redirect('payments');
        }
        return $p;
    }
}
