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

        $tiles = [];
        if (ops_can('errors')) {
            require_once BASE_PATH . '/app/ops/monitor.php';
            $e = $db->query("
                SELECT SUM(status = 'open' AND level IN ('fatal', 'error') AND kind <> 'slow') AS open_errors,
                       SUM(first_seen_at > NOW() - INTERVAL 1 DAY AND kind <> 'slow' AND level NOT IN ('notice', 'deprecated')) AS new_day,
                       SUM(regressed_at > NOW() - INTERVAL 7 DAY) AS back_week,
                       SUM(kind = 'slow' AND last_seen_at > NOW() - INTERVAL 1 DAY) AS slow_day
                FROM error_groups
            ")->fetch();
            $state = ops_health_state(ops_health($db));
            $tiles[] = ['Open errors', (string)(int)$e['open_errors'], 'Crashes and errors nobody has handled', 'errors', (int)$e['open_errors'] ? 'bad' : 'ok'];
            $tiles[] = ['New today', (string)(int)$e['new_day'], 'Problems first seen in the last 24 hours', 'errors?sort=new&status=all', ''];
            $tiles[] = ['Came back', (string)(int)$e['back_week'], 'Marked fixed, then happened again (7 days)', 'errors?status=all&sort=recent', (int)$e['back_week'] ? 'bad' : ''];
            $tiles[] = ['Health', ['ok' => 'All good', 'warn' => 'Worth a look', 'bad' => 'Needs attention'][$state], 'The cron, emails, phones, the server', 'health', $state === 'ok' ? 'ok' : ($state === 'bad' ? 'bad' : '')];
            $tiles[] = ['Slow pages', (string)(int)$e['slow_day'], 'Over ' . (ERRORS_SLOW_MS / 1000) . ' seconds, last 24 hours', 'errors?kind=slow', ''];
        }

        ops_render('home', ['tiles' => $tiles, 'passkeys' => $passkeys, 'recent' => $recent], 'Home');
    }
}
