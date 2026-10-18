<?php
/**
 * The old founder admin page. Everything it did moved to Nfelia Ops at
 * ops.nfelia.com (app/ops/), for the Nfelia team's own accounts, and the
 * Nfelia business (#1) is an ordinary business now.
 *
 * What is left: every /admin address goes to its place in Ops, and while
 * Ops has no admin yet, the owner of the Nfelia business sets up the first
 * one here (no terminal needed).
 */
class AdminController {

    /** Old admin pages → their place in Ops. */
    private const MOVED = [
        'dashboard' => '', 'businesses' => 'clients', 'support' => 'support', 'feedback' => 'feedback', 'referrals' => 'referrals',
        'financials' => 'finance', 'reviews' => 'reviews', 'shop_names' => 'shop-names', 'momo' => 'payments',
    ];

    private PDO $db;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
    }

    public function dashboard() {
        if ($this->may_set_up()) {
            $title     = 'Set up Nfelia Ops';
            $view_file = BASE_PATH . '/views/admin/ops_setup.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }
        $this->moved('dashboard');
    }

    /** Any other old address. */
    public function __call(string $name, array $args) {
        $this->moved($name);
    }

    /**
     * The first Nfelia Ops admin: the owner of the Nfelia business, once,
     * while Ops has no admin. Everyone after that is invited from Ops.
     */
    public function ops_setup() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin'); }
        csrf_verify();
        if (!$this->may_set_up()) {
            flash('error', 'Nfelia Ops already has an admin. Ask them to invite you.');
            redirect('dashboard');
        }
        $user  = auth_user();
        $email = strtolower(trim((string)$user['email']));
        $staff = ops_staff_by_email($email);
        if ($staff) {
            $this->db->prepare("UPDATE ops_staff SET role = 'admin', is_active = 1 WHERE id = ?")->execute([$staff['id']]);
        } else {
            $this->db->prepare("INSERT INTO ops_staff (full_name, email, role) VALUES (?, ?, 'admin')")->execute([$user['full_name'], $email]);
            $staff = ['id' => (int)$this->db->lastInsertId()];
        }
        $staff = ops_staff_by_id((int)$staff['id']);
        ops_send_invite($staff, null);
        ops_audit('team.invited', $user['full_name'] . ' set up Nfelia Ops from the old admin page, as its first admin', ['staff' => null, 'type' => 'staff', 'id' => (int)$staff['id']]);
        flash('success', 'Check ' . $email . ': the link to finish setting up your Ops account is on its way.');
        redirect('admin');
    }

    private function may_set_up(): bool {
        return is_platform_admin() && ops_active_admins() === 0;
    }

    private function moved(string $page): never {
        if ((int)auth_business_id() !== FOUNDER_BUSINESS_ID) {
            redirect('dashboard');
        }
        header('Location: ' . ops_url(self::MOVED[$page] ?? ''), true, 301);
        exit;
    }
}
