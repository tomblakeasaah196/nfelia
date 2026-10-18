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
        if (ops_can('sales') && !ops_can('errors')) {
            // Sales' own first page: what to do today.
            $due = $count("SELECT COUNT(*) FROM leads WHERE next_follow_up_at <= NOW() + INTERVAL 1 DAY AND stage IN ('new', 'contacted', 'demo', 'trial')"
                         . " AND (owner_id = " . (int)$staff['id'] . " OR owner_id IS NULL)");
            $tiles[] = ['Follow-ups today', (string)$due, 'Yours, and nobody\'s, due by tomorrow', 'leads?due=1', $due ? 'bad' : 'ok'];
            $new = $count("SELECT COUNT(*) FROM leads WHERE stage = 'new'");
            $tiles[] = ['New leads', (string)$new, 'Not spoken to yet', 'leads?stage=new', ''];
            $trial = $count("SELECT COUNT(*) FROM leads WHERE stage = 'trial'");
            $tiles[] = ['On a trial', (string)$trial, 'Leads using the free trial now', 'leads?stage=trial', ''];
            $won = $count("SELECT COUNT(*) FROM leads WHERE stage = 'paying' AND stage_changed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
            $tiles[] = ['Won this month', (string)$won, 'Leads that started paying', 'leads?stage=paying', $won ? 'ok' : ''];
        }
        if (ops_can('finance')) {
            $mrr = (float)$db->query(ops_mrr_sql())->fetchColumn();
            $tiles[] = ['Monthly recurring', '₦' . number_format($mrr), $count("SELECT COUNT(*) FROM subscriptions s WHERE " . ops_plan_sql('active')) . ' paying', 'finance', ''];
        } elseif (ops_can('clients')) {
            $trials = $count("SELECT COUNT(*) FROM subscriptions WHERE status = 'trial' AND trial_ends_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY");
            $tiles[] = ['Trials ending', (string)$trials, 'In the next 3 days', 'clients?plan=attention', ''];
        }

        ops_render('home', ['tiles' => $tiles, 'passkeys' => $passkeys, 'recent' => $recent], 'Home');
    }
}
