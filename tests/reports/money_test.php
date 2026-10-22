<?php
/**
 * Money out, cash in and cash out (Tom's decision, October 2026): what was
 * paid, on the day it was paid. An expense counts what was paid when it was
 * recorded; later payments on its supplier debt count on their own day;
 * what is unpaid is owed, not spent; stock bought is money out, shown on its
 * own line, and a gap it covers reads as money put into stock, not a loss.
 * Home, the profit and loss and the Records Book agree (app/helpers/money.php).
 *   bash tests/notifications/run.sh reports/money
 */
require __DIR__ . '/../notifications/lib.php';
require_once BASE_PATH . '/app/helpers/records.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, BIZ = 2, NGOZI = 200;
$db = get_db();

// Expenses and suppliers are not part of the shared fixture: start clean
exec_sql("DELETE FROM expense_items WHERE expense_id IN (SELECT id FROM expenses WHERE business_id = ?)", [BIZ]);
exec_sql("DELETE FROM expenses WHERE business_id = ?", [BIZ]);
exec_sql("DELETE FROM suppliers WHERE business_id = ?", [BIZ]);

$today = planner_today();
$first = (new DateTimeImmutable($today))->modify('first day of last month')->format('Y-m-d');
$last  = (new DateTimeImmutable($today))->modify('last day of last month')->format('Y-m-d');
$on    = fn(int $n) => planner_add_days($first, $n);
$ym    = substr($first, 0, 7);
$ada   = signin('ada@adashair.test');

function expense_id(string $description): int
{
    return (int)q("SELECT id FROM expenses WHERE business_id = ? AND description = ? ORDER BY id DESC LIMIT 1", [BIZ, $description]);
}

section('The things a vendor pays for, last month');
$ada->post('suppliers/create', ['full_name' => 'Alhaji Musa Hair Imports']);
$musa = (int)q("SELECT id FROM suppliers WHERE business_id = ? AND full_name = 'Alhaji Musa Hair Imports'", [BIZ]);
ok($musa > 0, 'a supplier with a name and nothing else is added');

$ada->post('expenses/create', ['category' => 'rent', 'description' => 'Shop rent', 'amount' => '50000', 'expense_date' => $on(1),
                               'payment_status' => 'paid', 'payment_method' => 'cash']);
$ada->post('expenses/create', ['category' => 'stock_purchase', 'description' => 'Bone straight 20in, ten bundles', 'expense_date' => $on(2),
                               'payment_status' => 'partial', 'amount_paid' => '100000', 'payment_method' => 'bank_transfer', 'supplier_id' => (string)$musa,
                               'item_description' => ['Bone straight 20in'], 'item_quantity' => ['10'], 'item_unit_cost' => ['30000'], 'item_product_id' => ['']]);
$ada->post('expenses/create', ['category' => 'utilities', 'description' => 'NEPA bill', 'amount' => '20000', 'expense_date' => $on(3),
                               'payment_status' => 'unpaid']);
$ada->post('expenses/create', ['category' => 'transport', 'description' => 'Keke typed twice', 'amount' => '10000', 'expense_date' => $on(1),
                               'payment_status' => 'paid', 'payment_method' => 'cash']);
$rent = expense_id('Shop rent'); $stock = expense_id('Bone straight 20in, ten bundles'); $nepa = expense_id('NEPA bill'); $twice = expense_id('Keke typed twice');
ok($rent && $stock && $nepa && $twice, 'rent paid, stock part paid, a bill unpaid, and one typed twice');
$ada->post('expenses/delete/' . $twice);
ok(q("SELECT deleted_at FROM expenses WHERE id = ?", [$twice]) !== null, 'the one typed twice is deleted');

$debt = (int)q("SELECT id FROM debts WHERE expense_id = ? AND source = 'expense' AND type = 'payable'", [$stock]);
ok($debt > 0, 'what is left on the stock is owed to the supplier');
$ada->post('debts/settle/' . $debt, ['amount' => '80000', 'method' => 'bank_transfer', 'settlement_date' => $on(9)]);
$ada->post('debts/settle/' . $debt, ['amount' => '50000', 'method' => 'cash', 'settlement_date' => $today]);
eq([(float)q("SELECT amount_paid FROM expenses WHERE id = ?", [$stock]), (float)q("SELECT balance_due FROM expenses WHERE id = ?", [$stock])],
   [230000.0, 70000.0], 'two later payments on the stock: 230,000 paid on it now, 70,000 left');

section('Money out: what was paid, when it was paid');
eq(money_out($db, BIZ, $first, $last), 230000.0, 'last month: rent 50,000, the stock\'s 100,000 on the day, and 80,000 paid on it later');
eq(money_out($db, BIZ, $on(2), $on(2)), 100000.0, 'on the day of the stock only what was paid then: later payments are not counted twice');
eq(money_out($db, BIZ, $on(9), $on(9)), 80000.0, 'a later payment counts on the day it was paid');
eq(money_out($db, BIZ, $today, $today), 50000.0, 'and today\'s payment today, though the stock was bought last month');
eq(money_out($db, BIZ, $on(3), $on(3)), 0.0, 'the unpaid bill is not money out');
eq(money_out_by($db, BIZ, $first, $last, 'category'), ['stock_purchase' => 180000.0, 'rent' => 50000.0], 'by category, largest first: no transport (deleted), no utilities (unpaid)');
eq(money_out_by($db, BIZ, $first, $last, '%Y-%m-%d'), [$on(2) => 100000.0, $on(9) => 80000.0, $on(1) => 50000.0], 'by day');

section('Profit and loss, last month: a restock reads as money into stock');
$sale = order_create($db, BIZ, ['customer_id' => NGOZI, 'channel' => 'manual', 'fulfillment' => 'now', 'method' => 'cash', 'date' => $on(4),
                               'items' => [['product_id' => 300, 'name' => '', 'quantity' => 1, 'unit_price' => 65000]], 'paid_now' => 65000], ADA);
eq(money_in($db, BIZ, $first, $last), 65000.0, 'one bundle sold last month: 65,000 came in');
eq(money_left_reads(65000, 230000, 180000), 'stock', '65,000 in, 230,000 out, 180,000 of it on stock');
$pl = $ada->get('reports/profit-loss?period=last_month')->body;
ok(str_contains($pl, '₦230,000'), 'Money Out is 230,000, the cash that left');
ok((bool)preg_match('#Spent on stock</span>\s*<span[^>]*>₦180,000<#', $pl), 'with Spent on stock, 180,000, on its own line');
ok(str_contains($pl, 'Left after spending') && str_contains($pl, '−₦165,000'), 'Left after spending is −165,000, the true cash');
ok(str_contains($pl, '₦180,000 of it went into stock you can still sell') && str_contains($pl, 'Money into stock.'), 'and it says the money went into stock');
ok(!str_contains($pl, '>Loss<') && !str_contains($pl, '% margin'), 'not a loss, and no margin');
ok((bool)preg_match('#Unpaid expenses</div>\s*<div[^>]*>₦90,000<#', $pl), 'Money You Owe: 90,000 unpaid on expenses (70,000 stock, 20,000 bill)');
ok(str_contains($pl, 'Bills you haven\'t paid yet (₦90,000) count once you pay them'), 'and the summary says unpaid bills count once paid');
$pdf = $ada->get('reports/profit-loss-pdf?period=last_month');
ok($pdf->status === 200 && str_starts_with($pdf->body, '%PDF'), 'the PDF of the same period is made');

section('Home and the Records Book say the same');
$home = $ada->get('dashboard')->body;
ok(str_contains($home, '<dt>Spent this month</dt><dd>₦50,000</dd>'), 'Home: 50,000 spent this month, the payment made today');
ok(str_contains($home, '<dt>Of it, on stock</dt><dd>₦50,000</dd>'), 'all of it on stock');
$facts = records_facts($db, BIZ, $first, $last);
eq($facts['spent'], 230000.0, 'the Records Book: 230,000 spent last month');
eq($facts['months'][$ym]['spent'] ?? null, 230000.0, 'and the same in its month by month');

done();
