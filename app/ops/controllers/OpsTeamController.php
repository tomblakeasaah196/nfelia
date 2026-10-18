<?php
/**
 * The Ops team (admins only): invite people, change their role, take
 * someone off the team and bring them back. There is always at least one
 * active admin, and nobody can remove or demote themselves.
 */
class OpsTeamController
{
    private array $me;

    public function __construct()
    {
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $team = get_db()->query("
            SELECT s.*,
                   (SELECT COUNT(*) FROM ops_passkeys p WHERE p.staff_id = s.id) AS passkeys,
                   EXISTS (SELECT 1 FROM ops_tokens t WHERE t.staff_id = s.id AND t.purpose = 'invite' AND t.used_at IS NULL AND t.expires_at > NOW()) AS invite_live
            FROM ops_staff s
            ORDER BY s.is_active DESC, s.password_hash IS NULL, FIELD(s.role, 'admin', 'support', 'sales'), s.full_name
        ")->fetchAll();
        ops_render('team', ['team' => $team, 'me' => $this->me], 'Team');
    }

    public function invite(): void
    {
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role  = (string)($_POST['role'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(OPS_ROLES[$role])) {
            ops_flash('error', 'Give their name, a working email and a role.');
            ops_redirect('team');
        }
        if ($existing = ops_staff_by_email($email)) {
            ops_flash('error', (int)$existing['is_active']
                ? $existing['full_name'] . ' is already on the team.'
                : $existing['full_name'] . ' was on the team before. Bring them back from the list below.');
            ops_redirect('team');
        }
        $db = get_db();
        $db->prepare("INSERT INTO ops_staff (full_name, email, role, invited_by) VALUES (?, ?, ?, ?)")
            ->execute([mb_substr($name, 0, 120), $email, $role, $this->me['id']]);
        $staff = ops_staff_by_id((int)$db->lastInsertId());
        ops_send_invite($staff, $this->me);
        ops_audit('team.invited', 'Invited ' . $name . ' (' . $email . ') as ' . ops_role_label($role), ['type' => 'staff', 'id' => (int)$staff['id']]);
        ops_flash('success', 'Invite sent to ' . $email . '. It works for 72 hours.');
        ops_redirect('team');
    }

    public function role(string $id): void
    {
        $staff = $this->other((int)$id);
        $role  = (string)($_POST['role'] ?? '');
        if (!isset(OPS_ROLES[$role]) || $role === $staff['role']) {
            ops_redirect('team');
        }
        if ($staff['role'] === 'admin' && (int)$staff['is_active'] && ops_active_admins() <= 1) {
            ops_flash('error', 'Ops needs at least one admin. Make someone else an admin first.');
            ops_redirect('team');
        }
        get_db()->prepare("UPDATE ops_staff SET role = ? WHERE id = ?")->execute([$role, $staff['id']]);
        ops_audit('team.role', $staff['full_name'] . ': ' . ops_role_label($staff['role']) . ' → ' . ops_role_label($role),
            ['type' => 'staff', 'id' => (int)$staff['id'], 'data' => ['from' => $staff['role'], 'to' => $role]]);
        ops_flash('success', $staff['full_name'] . ' is now ' . ops_role_label($role) . '.');
        ops_redirect('team');
    }

    public function deactivate(string $id): void
    {
        $staff = $this->other((int)$id);
        if ($staff['role'] === 'admin' && ops_active_admins() <= 1) {
            ops_flash('error', 'Ops needs at least one admin.');
            ops_redirect('team');
        }
        $db = get_db();
        // Off the team at once: every session ends, invites stop working.
        $db->prepare("UPDATE ops_staff SET is_active = 0, session_epoch = session_epoch + 1 WHERE id = ?")->execute([$staff['id']]);
        $db->prepare("UPDATE ops_tokens SET used_at = NOW() WHERE staff_id = ? AND used_at IS NULL")->execute([$staff['id']]);
        ops_audit('team.removed', 'Took ' . $staff['full_name'] . ' off the team', ['type' => 'staff', 'id' => (int)$staff['id']]);
        ops_flash('success', $staff['full_name'] . ' is off the team and signed out everywhere.');
        ops_redirect('team');
    }

    public function reactivate(string $id): void
    {
        $staff = $this->other((int)$id);
        get_db()->prepare("UPDATE ops_staff SET is_active = 1 WHERE id = ?")->execute([$staff['id']]);
        ops_audit('team.restored', 'Brought ' . $staff['full_name'] . ' back to the team as ' . ops_role_label($staff['role']), ['type' => 'staff', 'id' => (int)$staff['id']]);
        if ($staff['password_hash'] === null) {
            ops_send_invite(ops_staff_by_id((int)$staff['id']), $this->me);
            ops_flash('success', $staff['full_name'] . ' is back on the team. A new invite is on its way.');
        } else {
            ops_flash('success', $staff['full_name'] . ' is back on the team and can sign in again.');
        }
        ops_redirect('team');
    }

    public function resend(string $id): void
    {
        $staff = $this->other((int)$id);
        if ($staff['password_hash'] !== null || !(int)$staff['is_active']) {
            ops_redirect('team');
        }
        ops_send_invite($staff, $this->me);
        ops_audit('team.invite_resent', 'Sent ' . $staff['full_name'] . ' a new invite', ['type' => 'staff', 'id' => (int)$staff['id']]);
        ops_flash('success', 'A new invite is on its way to ' . $staff['email'] . '. The old link no longer works.');
        ops_redirect('team');
    }

    /** Someone on the team other than you. */
    private function other(int $id): array
    {
        $staff = ops_staff_by_id($id);
        if (!$staff) {
            ops_not_found();
        }
        if ((int)$staff['id'] === (int)$this->me['id']) {
            ops_flash('error', 'Ask another admin to change your own place on the team.');
            ops_redirect('team');
        }
        return $staff;
    }
}
