<?php
/**
 * Signing in to Ops: email and password, or a passkey made on ops.nfelia.com.
 * New people arrive by invite (a link set by an admin under Team) and choose
 * their password there. A forgotten password is reset by an emailed link.
 *
 * Wrong details always get the same answer, whether or not the email is on
 * the team, and tries are limited per email and per network.
 */
class OpsAuthController
{
    public function signin_form(): void
    {
        if (ops_staff()) {
            ops_redirect('');
        }
        ops_render_bare('signin', ['email' => (string)($_SESSION['ops_email'] ?? '')], 'Sign in');
    }

    public function signin(): void
    {
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $_SESSION['ops_email'] = $email;

        if (!throttle_allows('ops_ip', ops_ip(), 30, 15) || !throttle_allows('ops_email', $email, 6, 15)) {
            ops_audit('signin.throttled', 'Sign-in held back: too many tries for ' . $email, ['staff' => null]);
            ops_flash('error', 'Too many tries. Wait 15 minutes, or sign in with your passkey.');
            ops_redirect('signin');
        }

        $staff = $email !== '' ? ops_staff_by_email($email) : null;
        // A hash is checked even for an unknown email, so the answer takes
        // as long whether or not the person is on the team.
        $ok = password_verify($password, $staff['password_hash'] ?? '$2y$10$gnNVOVaeoK/GzJO/gOw9suhZe0YEqyR3LiRgwPqpGN7cNSyaVEoXi');
        if (!$staff || !$ok || !(int)$staff['is_active'] || $staff['password_hash'] === null) {
            ops_audit('signin.failed', 'Wrong email or password for ' . ($email !== '' ? $email : '(no email)'), ['staff' => $staff ? (int)$staff['id'] : null]);
            ops_flash('error', 'That email and password don\'t match an active Ops account.');
            ops_redirect('signin');
        }
        if (password_needs_rehash($staff['password_hash'], PASSWORD_DEFAULT)) {
            get_db()->prepare("UPDATE ops_staff SET password_hash = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $staff['id']]);
        }
        $this->finish($staff, 'email and password');
    }

    public function passkey_options(): void
    {
        ops_json(['ok' => true, 'options' => ops_passkey_get_options()]);
    }

    public function passkey_signin(): void
    {
        if (!throttle_allows('ops_ip', ops_ip(), 30, 15)) {
            ops_json(['ok' => false, 'message' => 'Too many tries. Wait 15 minutes and try again.'], 429);
        }
        try {
            $staff = ops_passkey_verify(ops_input());
        } catch (Throwable $e) {
            if (!$e instanceof RuntimeException) error_log('Nfelia Ops passkey sign-in: ' . $e->getMessage());
            ops_audit('signin.failed', 'A passkey sign-in was refused: ' . $e->getMessage(), ['staff' => null]);
            ops_json(['ok' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'That passkey could not be checked. Try again.'], 400);
        }
        $next = $this->next();
        ops_signin($staff, 'a passkey');
        ops_json(['ok' => true, 'to' => ops_url($next)]);
    }

    public function signout(): void
    {
        ops_audit('signout', 'Signed out');
        ops_session_end();
        ops_flash('info', 'You are signed out of Nfelia Ops.');
        ops_redirect('signin');
    }

    /* ── Invites ──────────────────────────────────────────── */

    public function invite_form(string $token): void
    {
        $staff = ops_token_peek($token, 'invite');
        if (!$staff) {
            ops_render_bare('message', ['heading' => 'This invite has expired', 'text' => 'Invites work once, for 72 hours. Ask an admin on the Nfelia team to send you a new one.'], 'Invite', 410);
        }
        ops_render_bare('invite', ['invitee' => $staff, 'token' => $token], 'Join Nfelia Ops');
    }

    public function invite_accept(string $token): void
    {
        $staff = ops_token_peek($token, 'invite');
        if (!$staff) {
            ops_render_bare('message', ['heading' => 'This invite has expired', 'text' => 'Invites work once, for 72 hours. Ask an admin on the Nfelia team to send you a new one.'], 'Invite', 410);
        }
        $name     = trim((string)($_POST['full_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $problem  = mb_strlen($name) < 2 ? 'Write your name as the team should see it.' : ops_password_problem($password, (string)($_POST['password_again'] ?? ''));
        if ($problem) {
            ops_flash('error', $problem);
            ops_redirect('invite/' . $token);
        }
        if (!ops_token_use((int)$staff['token_id'])) {
            ops_redirect('invite/' . $token);
        }
        get_db()->prepare("UPDATE ops_staff SET full_name = ?, password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?")
            ->execute([mb_substr($name, 0, 120), password_hash($password, PASSWORD_DEFAULT), $staff['id']]);
        ops_audit('team.joined', $name . ' accepted the invite and joined as ' . ops_role_label($staff['role']), ['staff' => (int)$staff['id'], 'type' => 'staff', 'id' => (int)$staff['id']]);

        $staff = ops_staff_by_id((int)$staff['id']);
        ops_signin($staff, 'a new password');
        ops_flash('success', 'Welcome to Nfelia Ops. Add a passkey so signing in takes one touch.');
        ops_redirect('account#passkeys');
    }

    /* ── A forgotten password ─────────────────────────────── */

    public function forgot_form(): void
    {
        ops_render_bare('forgot', ['email' => (string)($_SESSION['ops_email'] ?? '')], 'Reset your password');
    }

    public function forgot(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (throttle_allows('ops_reset_ip', ops_ip(), 10, 60) && throttle_allows('ops_reset', $email, 3, 60)) {
            $staff = ops_staff_by_email($email);
            if ($staff && (int)$staff['is_active'] && $staff['password_hash'] !== null) {
                $token = ops_token_issue((int)$staff['id'], 'reset');
                $id = notify_email(null, $staff['email'], $staff['full_name'], 'Reset your Nfelia Ops password', 'ops_reset', [
                    'name' => $staff['full_name'], 'url' => ops_url('reset/' . $token),
                ], null, 'ops.reset');
                if ($id) notify_flush_later($id);
                ops_audit('password.reset_sent', 'A password reset link was emailed', ['staff' => (int)$staff['id'], 'type' => 'staff', 'id' => (int)$staff['id']]);
            }
        }
        ops_flash('success', 'If that email is on the Ops team, a link to choose a new password is on its way. It works for an hour.');
        ops_redirect('signin');
    }

    public function reset_form(string $token): void
    {
        if (!ops_token_peek($token, 'reset')) {
            ops_render_bare('message', ['heading' => 'This link has expired', 'text' => 'Reset links work once, for an hour. Ask for a new one from the sign-in page.'], 'Reset', 410);
        }
        ops_render_bare('reset', ['token' => $token], 'Choose a new password');
    }

    public function reset(string $token): void
    {
        $staff = ops_token_peek($token, 'reset');
        if (!$staff) {
            ops_render_bare('message', ['heading' => 'This link has expired', 'text' => 'Reset links work once, for an hour. Ask for a new one from the sign-in page.'], 'Reset', 410);
        }
        $password = (string)($_POST['password'] ?? '');
        if ($problem = ops_password_problem($password, (string)($_POST['password_again'] ?? ''))) {
            ops_flash('error', $problem);
            ops_redirect('reset/' . $token);
        }
        if (!ops_token_use((int)$staff['token_id'])) {
            ops_redirect('reset/' . $token);
        }
        // Every other session ends: whoever knew the old password is out.
        get_db()->prepare("UPDATE ops_staff SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $staff['id']]);
        ops_audit('password.reset', 'Chose a new password from a reset link; signed out everywhere else', ['staff' => (int)$staff['id'], 'type' => 'staff', 'id' => (int)$staff['id']]);
        $this->finish(ops_staff_by_id((int)$staff['id']), 'a new password');
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function next(): string
    {
        $next = ops_safe_next($_SESSION['ops_next'] ?? '');
        unset($_SESSION['ops_next']);
        return $next;
    }

    private function finish(array $staff, string $how): never
    {
        $next = $this->next();
        ops_signin($staff, $how);
        ops_redirect($next);
    }
}
