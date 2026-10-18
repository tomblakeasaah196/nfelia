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

        ops_render('home', ['tiles' => [], 'passkeys' => $passkeys, 'recent' => $recent], 'Home');
    }
}
