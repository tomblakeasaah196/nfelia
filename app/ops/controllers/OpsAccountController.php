<?php
/**
 * Your own Ops account: password, passkeys, and signing out everywhere.
 */
class OpsAccountController
{
    private array $staff;

    public function __construct()
    {
        $this->staff = ops_staff();
    }

    public function index(): void
    {
        ops_render('account', ['passkeys' => ops_passkey_list((int)$this->staff['id'])], 'Your account');
    }

    public function password(): void
    {
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$this->staff['password_hash'])) {
            ops_flash('error', 'Your current password is not right.');
            ops_redirect('account');
        }
        $password = (string)($_POST['password'] ?? '');
        if ($problem = ops_password_problem($password, (string)($_POST['password_again'] ?? ''))) {
            ops_flash('error', $problem);
            ops_redirect('account');
        }
        $db = get_db();
        $db->prepare("UPDATE ops_staff SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $this->staff['id']]);
        ops_audit('password.changed', 'Changed their password; signed out everywhere else', ['type' => 'staff', 'id' => (int)$this->staff['id']]);
        $email = notify_email(null, $this->staff['email'], $this->staff['full_name'], 'Your Nfelia Ops password was changed', 'ops_security', [
            'name' => $this->staff['full_name'],
            'what' => 'The password of your Nfelia Ops account was changed, and every other device was signed out.',
            'url'  => ops_url('account'),
        ], null, 'ops.security');
        if ($email) notify_flush_later($email);
        // This device stays signed in; every other one is out.
        $_SESSION['ops_epoch'] = (int)$this->staff['session_epoch'] + 1;
        ops_flash('success', 'Password changed. Every other device is signed out.');
        ops_redirect('account');
    }

    public function signout_everywhere(): void
    {
        ops_bump_epoch((int)$this->staff['id']);
        ops_audit('signout.everywhere', 'Signed out on every device', ['type' => 'staff', 'id' => (int)$this->staff['id']]);
        ops_session_end();
        ops_flash('info', 'You are signed out on every device, this one too.');
        ops_redirect('signin');
    }

    public function passkey_options(): void
    {
        ops_json(['ok' => true, 'options' => ops_passkey_create_options($this->staff)]);
    }

    public function passkey_add(): void
    {
        try {
            $added = ops_passkey_register($this->staff, ops_input());
        } catch (Throwable $e) {
            if (!$e instanceof RuntimeException) error_log('Nfelia Ops passkey add: ' . $e->getMessage());
            ops_json(['ok' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'That passkey could not be added. Try again.'], 400);
        }
        ops_audit('passkey.added', 'Added a passkey (' . $added['name'] . ')', ['type' => 'passkey', 'id' => $added['id']]);
        $email = notify_email(null, $this->staff['email'], $this->staff['full_name'], 'A passkey was added to your Nfelia Ops account', 'ops_security', [
            'name' => $this->staff['full_name'],
            'what' => 'A passkey (' . $added['name'] . ') was added to your Nfelia Ops account. It can now sign you in.',
            'url'  => ops_url('account#passkeys'),
        ], null, 'ops.security');
        if ($email) notify_flush_later($email);
        ops_json(['ok' => true, 'passkey' => $added]);
    }

    public function passkey_remove(string $id): void
    {
        $stmt = get_db()->prepare("DELETE FROM ops_passkeys WHERE id = ? AND staff_id = ?");
        $stmt->execute([(int)$id, $this->staff['id']]);
        if ($stmt->rowCount()) {
            ops_audit('passkey.removed', 'Removed a passkey', ['type' => 'passkey', 'id' => (int)$id]);
            ops_flash('success', 'Passkey removed. It can no longer sign you in.');
        }
        ops_redirect('account#passkeys');
    }
}
