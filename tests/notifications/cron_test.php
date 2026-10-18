<?php
/**
 * Everything cron/planner.php tells people, run at set times of day (the PHP
 * clock is faked with faketime; dates come from the database). Each check
 * also runs the cron again to prove nobody hears the same thing twice.
 *   php tests/notifications/cron_test.php
 */
require __DIR__ . '/lib.php';
t_guard();
fixture_reset();
$today = (string)q("SELECT CURDATE()");
$day = fn(int $n) => date('Y-m-d', strtotime("{$today} {$n} days"));

/** Only the run of interest: the fixture's other dated things are moved out of the way. */
function quiet_world(): void
{
    exec_sql("UPDATE invoices SET status = 'paid', balance_due = 0");
    exec_sql("UPDATE quotations SET expiry_date = CURDATE() + INTERVAL 30 DAY WHERE status = 'sent'");
    exec_sql("UPDATE products SET stock_qty = 100");
    exec_sql("UPDATE tasks SET due_date = NULL");
    exec_sql("UPDATE subscriptions SET status = 'active', current_period_end = CURDATE() + INTERVAL 20 DAY, paystack_subscription_code = 'SUB_x', cancelled_at = NULL, trial_ends_at = NULL");
    clean_slate();
}

section('A task reminder');
quiet_world();
exec_sql("INSERT INTO tasks (id, business_id, kind, title, due_date, due_time, remind_before, remind_at, assigned_to, created_by)
          VALUES (910, 2, 'task', 'Call the closure supplier', ?, '14:00:00', 60, ?, 22, 20)", [$today, "{$today} 13:00:00"]);
$out = cron_at('13:05');
ok(str_contains($out, 'reminders=1'), 'the cron sends one reminder: ' . $out);
$b = bell(22, 'task.reminder');
eq(count($b), 1, 'Chidi, who has the task, gets it (not Ada who made it)');
eq($b[0]['action_url'] ?? null, 'tasks?task=910', 'it opens that very task');
eq($b[0]['title'] ?? null, 'Call the closure supplier', 'titled with the task');
$m = mails_to('chidi@adashair.test');
eq(count($m), 1, 'and by email (reminder emails are on by default)');
ok(str_contains($m[0]['html'] ?? '', '/notifications/open/' . ($b[0]['id'] ?? 0)), 'the email opens it through the alert link');
cron_at('13:10');
eq(count(bell(22, 'task.reminder')), 1, 'the next run sends nothing more');

section('Old reminders, and people who left');
quiet_world();
exec_sql("INSERT INTO tasks (business_id, kind, title, remind_at, assigned_to, created_by) VALUES
          (2, 'task', 'Two days late', ?, 22, 20), (2, 'task', 'For Dayo', ?, 23, 20)",
         [$day(-2) . ' 10:00:00', "{$today} 12:00:00"]);
cron_at('13:05');
eq(count(rows("SELECT id FROM notifications WHERE event = 'task.reminder'")), 0, 'a reminder two days late, or for someone who left, is dropped');
ok((int)q("SELECT COUNT(*) FROM tasks WHERE reminded_at IS NULL AND title IN ('Two days late', 'For Dayo')") === 0, 'and stamped, so it never comes back');

section('The morning plan');
quiet_world();
exec_sql("UPDATE tasks SET due_date = CURDATE() WHERE id = 900");
exec_sql("DELETE FROM planner_sends");
cron_at('06:30');
eq(count(rows("SELECT id FROM notifications WHERE event = 'morning.plan'")), 0, 'not before 7am');
cron_at('07:30');
$b = bell(22, 'morning.plan');
eq(count($b), 1, 'at 7:30 Chidi gets his day');
ok(str_starts_with($b[0]['title'] ?? '', 'Your day:'), 'titled with his day: ' . ($b[0]['title'] ?? ''));
eq(count(mails_to('chidi@adashair.test')), 1, 'and the morning email');
cron_at('09:00');
eq(count(bell(22, 'morning.plan')), 1, 'once a day');

section('Low stock, once a day to the team');
quiet_world();
exec_sql("UPDATE products SET stock_qty = 1 WHERE id = 301");
cron_at('08:30');
eq(told('stock.low'), [], 'not before 9am');
cron_at('09:30');
eq(told('stock.low'), [20, 21, 22], 'from 9am: owner, admin and staff');
eq(bell(22, 'stock.low')[0]['title'] ?? null, '1 item is running low', 'saying what');
eq(bell(22, 'stock.low')[0]['action_url'] ?? null, 'products?stock=restock', 'and opening the restock list');
cron_at('15:00');
eq(count(rows("SELECT id FROM notifications WHERE event = 'stock.low'")), 3, 'and only once that day');

section('Overdue invoices');
quiet_world();
exec_sql("UPDATE invoices SET status = 'sent', balance_due = total WHERE id = 401");
exec_sql("UPDATE invoices SET status = 'overdue', balance_due = total WHERE id = 402");
cron_at('07:00');
eq(told('invoice.overdue'), [], 'not before 8am');
cron_at('08:30');
eq((string)q("SELECT status FROM invoices WHERE id = 401"), 'overdue', 'an invoice past its due date is marked overdue');
eq(told('invoice.overdue'), [20, 20, 21, 21], 'Ada and Bisi hear about each (staff don\'t)');
$b = array_column(bell(20, 'invoice.overdue'), null, 'action_url');
eq($b['invoices/view/401']['message'] ?? null, 'Ngozi Eze owes ₦50,000, 3 days past due.', 'who owes how much, how late');
ok(isset($b['invoices/view/402']), 'each opens its own invoice');
cron_at('12:00');
eq(count(rows("SELECT id FROM notifications WHERE event = 'invoice.overdue'")), 4, 'once a week, not every run');
exec_sql("UPDATE invoices SET due_date = due_date - INTERVAL 7 DAY WHERE id = 401");
cron_at('12:30');
eq(count(rows("SELECT id FROM notifications WHERE event = 'invoice.overdue' AND action_url = 'invoices/view/401'")), 4, 'a week later, still unpaid: told again');
exec_sql("UPDATE invoices SET balance_due = 0, status = 'paid' WHERE id IN (401, 402)");

section('Many overdue at once: one summary to the phone, each in the bell');
quiet_world();
foreach (range(1, 5) as $i) {
    exec_sql("INSERT INTO invoices (business_id, customer_id, user_id, doc_number, status, issue_date, due_date, subtotal, total, amount_paid, balance_due, token)
              VALUES (2, 200, 20, ?, 'overdue', CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 2 DAY, 10000, 10000, 0, 10000, ?)", ["ADA-INV-09{$i}", bin2hex(random_bytes(8))]);
}
cron_at('09:00');
eq(count(bell(20, 'invoice.overdue')), 5, 'each of the 5 is in Ada\'s bell');
eq(count(outbox('invoice.overdue')), 0, 'but none of them buzzes or emails on its own');
$s = bell(20, 'invoice.overdue_many');
eq($s[0]['title'] ?? null, '5 invoices are overdue', 'one summary instead');
eq($s[0]['action_url'] ?? null, 'invoices?status=overdue', 'opening the overdue list');
eq(count(outbox('invoice.overdue_many', 'push', 20)), 1, 'and that one does reach the phone');
exec_sql("DELETE FROM invoices WHERE doc_number LIKE 'ADA-INV-09%'");

section('Quotes that expire');
quiet_world();
exec_sql("UPDATE quotations SET expiry_date = CURDATE() - INTERVAL 1 DAY WHERE id = 503");
cron_at('10:00');
eq((string)q("SELECT status FROM quotations WHERE id = 503"), 'expired', 'a sent quote past its date turns expired');
eq(told('quote.expired'), [20, 21], 'and the owner and admin hear');
eq(bell(20, 'quote.expired')[0]['action_url'] ?? null, 'quotations/view/503', 'it opens the quote');
cron_at('10:05');
eq(count(rows("SELECT id FROM notifications WHERE event = 'quote.expired'")), 2, 'once');

section('Free trials ending and ended');
quiet_world();
fixture_subscription(3, ['status' => 'trial', 'trial_ends_at' => $day(2)]);
cron_at('10:00');
$b = bell(30, 'billing.trial_ending');
eq($b[0]['title'] ?? null, 'Your free trial ends in 2 days', 'Kola is told two days before');
eq(count(mails_to('kola@kolaprints.test')), 1, 'by email too (billing always emails)');
cron_at('11:00');
eq(count(bell(30, 'billing.trial_ending')), 1, 'once');
fixture_subscription(3, ['trial_ends_at' => $day(-1)]);
cron_at('11:05');
eq(bell(30, 'billing.trial_ended')[0]['title'] ?? null, 'Your free trial has ended', 'the day after it ends, he hears it has');

section('A paid month: a warning, then 3 days to pay, then Nfelia pauses');
quiet_world();
fixture_subscription(2, ['paystack_subscription_code' => null, 'current_period_end' => $day(3)]);
cron_at('10:00');
eq(bell(20, 'billing.renewal_soon')[0]['title'] ?? null, 'Your paid month ends in 3 days', 'three days before: a warning to the owner');
eq(told('billing.renewal_soon'), [20], 'only the owner');
fixture_subscription(2, ['current_period_end' => $day(-1)]);
cron_at('10:05');
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 2"), 'active', 'the month is up: nothing is changed (billing_access() works it out from the dates)');
eq(count(bell(20, 'billing.due')), 1, 'and the owner is told');
eq(bell(20, 'billing.due')[0]['message'] ?? null, 'It ended on ' . date('j M Y', strtotime($day(-1))) . '. Pay by ' . date('j M Y', strtotime($day(2))) . ' to keep Nfelia and your website open.', 'with the 3 days to pay');
$ada = signin('ada@adashair.test');
$ada->get('dashboard');
ok(str_contains($ada->body, 'Your paid month has ended. Pay by'), 'the app says how long is left');
cron_at('10:06');
eq(count(bell(20, 'billing.due')), 1, 'once');
fixture_subscription(2, ['current_period_end' => $day(-4)]);
cron_at('10:10');
eq(count(bell(20, 'billing.ended')), 1, 'the days run out: told Nfelia is paused');
$ada->get('dashboard');
eq($ada->to(), 'settings?tab=billing', 'and the app sends her to billing');
cron_at('10:15');
eq(count(bell(20, 'billing.ended')), 1, 'once');

section('Already past its date when months began to expire: left as it is');
quiet_world();
fixture_subscription(2, ['current_period_end' => $day(-20), 'expiry_exempt' => 1]);
cron_at('10:00');
eq(told('billing.due') + told('billing.ended'), [], 'nobody is told anything');
$ada = signin('ada@adashair.test');
$ada->get('dashboard');
eq($ada->status, 200, 'and the app stays open (until the next payment)');
fixture_subscription(2, ['expiry_exempt' => 0]);

section('An old card plan that still renews itself: left to Paystack');
quiet_world();
fixture_subscription(2, ['paystack_subscription_code' => 'SUB_ada', 'current_period_end' => $day(-1)]);
cron_at('10:00');
eq((string)q("SELECT status FROM subscriptions WHERE business_id = 2"), 'active', 'a card plan a day past its month is left for Paystack to renew');
eq(told('billing.due'), [], 'and nobody is told it is due (a failed charge says so itself)');

section('A cancelled plan: open while paid for, then the end');
quiet_world();
fixture_subscription(2, ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'current_period_end' => $day(2)]);
$ada = signin('ada@adashair.test');
$ada->get('dashboard');
eq($ada->status, 200, 'cancelled with 2 paid days left: still in');
ok(str_contains($ada->body, 'You keep full access until'), 'and told until when');
fixture_subscription(2, ['current_period_end' => $day(-1)]);
cron_at('10:00');
eq(count(bell(20, 'billing.ended')), 1, 'the paid time used up: told it has ended');

section('The cron retries what failed');
quiet_world();
touch(T_MAIL_DIR . '/REFUSE');
notify('billing.failed', 2, []);
notify_flush();
eq(outbox('billing.failed', 'email')[0]['status'] ?? null, 'pending', 'the mail server was down: waiting');
unlink(T_MAIL_DIR . '/REFUSE');
exec_sql("UPDATE notify_outbox SET next_attempt_at = NOW() - INTERVAL 1 MINUTE");
$out = cron_at('10:00');
eq(outbox('billing.failed', 'email')[0]['status'] ?? null, 'sent', 'the next cron run sends it');
ok(str_contains($out, 'outbox_sent=1'), 'and says so: ' . $out);

section('Housekeeping at 3am');
quiet_world();
exec_sql("INSERT INTO notifications (business_id, user_id, type, event, title, message, is_read, created_at) VALUES
    (2, 20, 'x', 'x', 'old and read', '', 1, NOW() - INTERVAL 7 MONTH),
    (2, 20, 'x', 'x', 'old, unread', '', 0, NOW() - INTERVAL 7 MONTH),
    (2, 20, 'x', 'x', 'ancient', '', 0, NOW() - INTERVAL 13 MONTH)");
exec_sql("INSERT INTO notify_outbox (event, channel, payload, status, created_at) VALUES ('x', 'email', '{}', 'sent', NOW() - INTERVAL 2 MONTH)");
cron_at('03:10');
eq(array_column(rows("SELECT title FROM notifications WHERE event = 'x'"), 'title'), ['old, unread'], 'read alerts go after 6 months, all after a year');
eq((int)q("SELECT COUNT(*) FROM notify_outbox WHERE event = 'x'"), 0, 'the record of sends after a month');

section('A business that cannot use Nfelia hears nothing from the cron');
quiet_world();
fixture_subscription(2, ['status' => 'suspended']);
exec_sql("INSERT INTO tasks (business_id, kind, title, remind_at, assigned_to, created_by) VALUES (2, 'task', 'Suspended reminder', ?, 22, 20)", ["{$today} 12:00:00"]);
exec_sql("UPDATE products SET stock_qty = 1 WHERE id = 301");
cron_at('13:05');
eq(count(rows("SELECT id FROM notifications WHERE business_id = 2")), 0, 'no reminders, no stock alerts');

done();
