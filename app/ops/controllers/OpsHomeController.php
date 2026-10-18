<?php
/**
 * The first page of Ops: what needs someone now, for this person's role.
 */
class OpsHomeController
{
    public function index(): void
    {
        $staff = ops_staff();
        $db    = get_db();

        $passkeys = count(ops_passkey_list((int)$staff['id']));
        $recent   = [];
        if (ops_can('audit')) {
            $recent = $db->query("
                SELECT a.*, s.full_name, b.name AS business_name FROM ops_audit a
                LEFT JOIN ops_staff s ON s.id = a.staff_id LEFT JOIN businesses b ON b.id = a.business_id
                WHERE a.action NOT IN ('signin', 'signout')
                ORDER BY a.id DESC LIMIT 8
            ")->fetchAll();
        }

        // At most five, what needs someone first.
        $tiles = [];
        $count = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
        if (ops_can('errors')) {
            $open = $count("SELECT COUNT(*) FROM error_groups WHERE status = 'open' AND level IN ('fatal', 'error') AND kind <> 'slow'");
            $tiles[] = ['Open errors', (string)$open, 'Crashes and errors nobody has taken on', 'errors', $open ? 'bad' : 'ok'];
        }
        if (ops_can('clients.act')) {
            $pay = $count("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending_verification'");
            $tiles[] = ['Payments to check', (string)$pay, 'Mobile money receipts waiting', 'payments', $pay ? 'bad' : ''];
        }
        if (ops_can('support')) {
            $tickets = $count("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
            $tiles[] = ['Open tickets', (string)$tickets, 'Clients waiting on an answer', 'support', $tickets ? 'bad' : ''];
        }
        if (ops_can('errors')) {
            require_once BASE_PATH . '/app/ops/monitor.php';
            $state = ops_health_state(ops_health($db));
            $tiles[] = ['Health', ['ok' => 'All good', 'warn' => 'Worth a look', 'bad' => 'Needs attention'][$state], 'The cron, emails, phones, the server', 'health', $state === 'ok' ? 'ok' : ($state === 'bad' ? 'bad' : '')];
        }
        if (ops_can('finance')) {
            $mrr = (float)$db->query("SELECT COALESCE(SUM(monthly_amount + extra_seats * 5000), 0) FROM subscriptions WHERE status = 'active' AND payment_method = 'paystack'")->fetchColumn();
            $tiles[] = ['Monthly recurring', '₦' . number_format($mrr), $count("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'") . ' paying', 'finance', ''];
        } elseif (ops_can('clients')) {
            $trials = $count("SELECT COUNT(*) FROM subscriptions WHERE status = 'trial' AND trial_ends_at <= CURDATE() + INTERVAL 3 DAY");
            $tiles[] = ['Trials ending', (string)$trials, 'In the next 3 days', 'clients?plan=attention', ''];
        }

        ops_render('home', ['tiles' => $tiles, 'passkeys' => $passkeys, 'recent' => $recent], 'Home');
    }
}
