<?php
/**
 * Everything done in Ops, newest first (admins only). Filter by person,
 * by kind of action, or by client; ?before= pages back.
 */
class OpsAuditController
{
    private const PAGE = 50;

    public function index(): void
    {
        $db    = get_db();
        $where = ['1 = 1'];
        $args  = [];

        $who      = (int)($_GET['staff'] ?? 0);
        $kind     = preg_replace('/[^a-z_.]/', '', (string)($_GET['kind'] ?? ''));
        $business = (int)($_GET['business'] ?? 0);
        $before   = (int)($_GET['before'] ?? 0);
        if ($who)      { $where[] = 'a.staff_id = ?';    $args[] = $who; }
        if ($kind)     { $where[] = 'a.action LIKE ?';   $args[] = $kind . '%'; }
        if ($business) { $where[] = 'a.business_id = ?'; $args[] = $business; }
        if ($before)   { $where[] = 'a.id < ?';          $args[] = $before; }

        $stmt = $db->prepare("
            SELECT a.*, s.full_name, b.name AS business_name
            FROM ops_audit a
            LEFT JOIN ops_staff s ON s.id = a.staff_id
            LEFT JOIN businesses b ON b.id = a.business_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.id DESC LIMIT " . (self::PAGE + 1));
        $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $more = count($rows) > self::PAGE;
        $rows = array_slice($rows, 0, self::PAGE);

        $team  = $db->query("SELECT id, full_name FROM ops_staff ORDER BY full_name")->fetchAll();
        $kinds = $db->query("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) AS k FROM ops_audit ORDER BY k")->fetchAll(PDO::FETCH_COLUMN);

        ops_render('audit', [
            'rows' => $rows, 'more' => $more, 'team' => $team, 'kinds' => $kinds,
            'filter' => ['staff' => $who, 'kind' => $kind, 'business' => $business],
        ], 'Audit log');
    }
}
