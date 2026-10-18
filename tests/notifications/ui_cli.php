<?php
/**
 * What the browser checks (ui_test.js) need from the database, as JSON:
 *   php ui_cli.php reset                 rebuild the world, with a few alerts
 *   php ui_cli.php sql "SELECT ..."      rows
 *   php ui_cli.php notify EVENT BIZ JSON a new alert (prints the bell row ids)
 */
require __DIR__ . '/lib.php';
t_guard();
[, $cmd] = $argv + [null, null];
switch ($cmd) {
    case 'reset':
        fixture_reset();
        exec_sql("DELETE FROM notifications");
        $order = ['order_id' => 700, 'order_number' => 'ORD-0700', 'customer' => 'Ngozi Eze', 'items' => 1, 'total' => 65000,
                  'channel' => 'cart_pop', 'fulfillment' => 'delivery'];
        $ids = ['order' => notify('order.new', 2, $order, ['channels' => []])];
        $ids['payment'] = notify('payment.invoice', 2, ['invoice_id' => 400, 'invoice_number' => 'ADA-INV-0400', 'customer' => 'Femi & Sons "Wholesale"',
                                 'amount' => 25000, 'method' => 'cash', 'balance' => 75000], ['channels' => []]);
        echo json_encode($ids);
        break;
    case 'sql':
        echo json_encode(rows($argv[2]));
        break;
    case 'notify':
        echo json_encode(notify($argv[2], (int)$argv[3], json_decode($argv[4], true) ?: [], ['channels' => []]));
        break;
    default:
        fwrite(STDERR, "usage: php ui_cli.php reset | sql SQL | notify EVENT BIZ JSON\n");
        exit(2);
}
