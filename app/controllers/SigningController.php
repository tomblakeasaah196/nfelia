<?php
/**
 * Signatures (app/helpers/signing.php): Settings › Signatures, an owner's or
 * admin's own drawn signature, and signing one document or taking its
 * signature off. Owners and admins only: they sign for the business.
 *
 *   POST signing/settings   per kind of document: signed by default, and the style
 *   POST signing/mine       my drawn signature (or remove it)
 *   POST signing/sign       sign a document (type, id, on = 1) or take it off (on = 0)
 */
class SigningController {

    private PDO $db;
    private int $biz_id;
    private array $user;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        require_once BASE_PATH . '/app/helpers/signing.php';
        $this->db     = get_db();
        $this->biz_id = (int)auth_business_id();
        $this->user   = auth_user();
        require_boss('sign for the business');
    }

    public function settings() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('settings#signatures');
        csrf_verify();
        signing_save($this->db, $this->biz_id, (array)($_POST['signing'] ?? []));
        flash('success', 'Signatures saved. They apply to documents made from now on.');
        redirect('settings#signatures');
    }

    public function mine() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('settings#signatures');
        csrf_verify();
        if (!empty($_POST['remove'])) {
            $this->db->prepare("UPDATE users SET signature_path = NULL, signature_at = NULL WHERE id = ? AND business_id = ?")
                     ->execute([(int)$this->user['id'], $this->biz_id]);
            flash('success', 'Your drawn signature is removed. Documents you sign now carry the stamp.');
            redirect('settings#signatures');
        }
        $path = signing_store_drawing($this->biz_id, (string)($_POST['signature'] ?? ''));
        if ($path === null) {
            flash('error', 'That signature didn\'t come through. Clear it and draw it again.');
            redirect('settings#signatures');
        }
        $this->db->prepare("UPDATE users SET signature_path = ?, signature_at = NOW() WHERE id = ? AND business_id = ?")
                 ->execute([$path, (int)$this->user['id'], $this->biz_id]);
        flash('success', 'Your signature is saved. Documents you sign from now on carry it.');
        redirect('settings#signatures');
    }

    public function sign() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard');
        csrf_verify();
        $type = (string)($_POST['type'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);
        $on   = !empty($_POST['on']);
        $back = (string)($_POST['back'] ?? '');
        // Back to the page it came from, on this site only
        $back = preg_match('#^[a-z0-9/_-]+$#i', $back) ? $back : 'dashboard';
        if (!isset(SIGN_TYPES[$type]) || $id <= 0) {
            flash('error', 'That document is not here.');
            redirect($back);
        }
        $problem = doc_switch($this->db, $this->biz_id, $type, $id, $on, (int)$this->user['id']);
        if ($problem !== '') {
            flash('error', $problem);
        } else {
            flash('success', $on ? SIGN_TYPES[$type]['label'] . ' signed.' : 'The signature is off this ' . strtolower(SIGN_TYPES[$type]['label']) . '.');
        }
        redirect($back);
    }
}
