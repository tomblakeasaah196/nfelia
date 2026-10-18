<?php
/**
 * Nfelia — Planner: tasks, appointments, job plans, and the dates Nfelia
 * already knows, merged into one list for Tasks & Calendar, the morning
 * plan and reminders.
 *
 * Two kinds of item:
 *   - tasks rows (to-dos, appointments, job plans and their steps), which
 *     the owner creates and ticks off;
 *   - automatic items, read live from invoices, debts, deliveries, shop
 *     orders, products and quotations. Nothing is copied, so an item clears
 *     by itself when the money comes in or the goods go out. Ticking one off
 *     by hand records a dismissal (planner_dismissals).
 *
 * "Late" is never stored. It is worked out on read from the date, in
 * Africa/Lagos time, which matches the database session (+01:00).
 *
 * Every item is returned in one shape (planner_item()) so the page, the
 * calendar, the morning email and push all read the same fields.
 */

function planner_tz(): DateTimeZone {
    static $tz = null;
    return $tz ??= new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Lagos');
}

function planner_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', planner_tz());
}

function planner_today(): string {
    return planner_now()->format('Y-m-d');
}

function planner_add_days(string $date, int $days): string {
    return (new DateTimeImmutable($date, planner_tz()))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function planner_days_between(string $from, string $to): int {
    $a = new DateTimeImmutable($from, planner_tz());
    $b = new DateTimeImmutable($to, planner_tz());
    return (int)$a->diff($b)->format('%r%a');
}

/** Owner and admin manage everything; staff manage their own work. */
function planner_is_manager(array $user): bool {
    return in_array($user['role'] ?? 'staff', ['owner', 'admin'], true);
}

function planner_valid_date(?string $d): bool {
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

function planner_valid_time(?string $t): bool {
    return (bool)($t && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t));
}

/* ── Reminders ─────────────────────────────────────────────── */

/** Minutes before the item. Untimed items are reminded relative to 8am. */
function planner_remind_options(bool $timed): array {
    return $timed
        ? [0 => 'At the time', 15 => '15 minutes before', 60 => '1 hour before', 1440 => '1 day before']
        : [0 => 'Morning of the day (8am)', 1440 => 'The day before (8am)'];
}

function planner_remind_at(?string $date, ?string $time, ?int $before): ?string {
    if ($date === null || $before === null) return null;
    $at = new DateTimeImmutable($date . ' ' . ($time ?: '08:00:00'), planner_tz());
    return $at->modify('-' . $before . ' minutes')->format('Y-m-d H:i:s');
}

/**
 * Next date of a repeating task, never in the past. $anchor_day is the day
 * of the month the repeat was set up on, so the 31st comes back as the 31st
 * (or the month's last day), not the 28th forever after February (P11).
 */
function planner_next_date(string $date, string $every, string $today, ?int $anchor_day = null): string {
    $d = new DateTimeImmutable($date, planner_tz());
    $day = $anchor_day ?: (int)$d->format('j');
    do {
        if ($every === 'month') {
            // Keep the day of the month; clamp 31 to the month's last day.
            $first = $d->modify('first day of next month');
            $d = $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min($day, (int)$first->format('t')));
        } else {
            $d = $d->modify($every === 'week' ? '+1 week' : '+1 day');
        }
    } while ($d->format('Y-m-d') < $today);
    return $d->format('Y-m-d');
}

/* ── Formatting ────────────────────────────────────────────── */

function planner_fmt_time(?string $time): string {
    if (!$time) return '';
    $t = DateTimeImmutable::createFromFormat('H:i', substr($time, 0, 5), planner_tz());
    return $t ? strtolower($t->format('g:ia')) : '';
}

function planner_fmt_day(?string $date, string $today): string {
    if (!$date) return '';
    $diff = planner_days_between($today, $date);
    if ($diff === 0) return 'Today';
    if ($diff === 1) return 'Tomorrow';
    if ($diff === -1) return 'Yesterday';
    $d = new DateTimeImmutable($date, planner_tz());
    return $d->format(abs($diff) < 180 ? 'D j M' : 'D j M Y');
}

/** planner_fmt_day() for the middle of a sentence: "sent today", "sent Wed 14 Oct". */
function planner_fmt_day_inline(?string $date, string $today): string {
    $d = planner_fmt_day($date, $today);
    return in_array($d, ['Today', 'Tomorrow', 'Yesterday'], true) ? strtolower($d) : $d;
}

/** "today", "tomorrow", "on Wed 21 Oct": when something happens, for a message. */
function planner_when(string $date, string $today): string {
    $diff = planner_days_between($today, $date);
    if ($diff <= 0) return 'today';
    if ($diff === 1) return 'tomorrow';
    return 'on ' . planner_fmt_day($date, $today);
}

/** "3 days left", "Due today", "2 days late", with a tone the page colours. */
function planner_countdown(?string $date, string $today, string $due_word = 'Due'): ?array {
    if (!$date) return null;
    $diff = planner_days_between($today, $date);
    if ($diff < 0) {
        $n = -$diff;
        return ['label' => $n . ' day' . ($n > 1 ? 's' : '') . ' late', 'tone' => 'late'];
    }
    if ($diff === 0) return ['label' => $due_word . ' today', 'tone' => 'today'];
    if ($diff === 1) return ['label' => $due_word . ' tomorrow', 'tone' => 'soon'];
    return ['label' => $diff . ' days left', 'tone' => $diff <= 3 ? 'soon' : 'later'];
}

function planner_money(float $amount, string $symbol): string {
    // Whole naira reads faster on a phone; keep kobo only when there are some.
    $dec = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
    return $symbol . number_format($amount, $dec);
}

function planner_first_name(?string $name): string {
    $name = trim((string)$name);
    if ($name === '') return '';
    return explode(' ', $name)[0];
}

function planner_initials(?string $name): string {
    $parts = preg_split('/\s+/', trim((string)$name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) { $out .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $out;
}

/** wa.me link with a ready message. No number: WhatsApp asks who to send to. */
function planner_wa_link(?string $phone, string $text): string {
    $num = preg_replace('/\D/', '', (string)$phone);
    if (str_starts_with($num, '0')) $num = '234' . substr($num, 1);
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

/* ── Item shape ────────────────────────────────────────────── */

/** Every item the page shows, whatever it comes from. */
function planner_item(array $f): array {
    return $f + [
        'id' => '', 'kind' => 'task', 'source' => null, 'task_id' => null,
        'title' => '', 'subtitle' => '', 'date' => null, 'time' => null, 'end_time' => null,
        'starred' => false, 'done' => false, 'customer' => null, 'assignee' => null,
        'countdown' => null, 'url' => null, 'wa' => null, 'wa_label' => null,
        'tick' => null, 'calendar' => true, 'notes' => '', 'repeat' => null,
        'remind_before' => null, 'parent_id' => null, 'parent_title' => null,
        'steps' => null, 'invoice_id' => null, 'created_by' => null, 'plan' => null,
        'pressure' => false, 'amount' => null,
    ];
}

/* ── Tasks ─────────────────────────────────────────────────── */

/**
 * Task rows for one business. $opt keys (all optional):
 *   from, to        due dates, inclusive
 *   late            also undone tasks due before `from`
 *   undated         also tasks with no date
 *   done_since      also tasks ticked off on or after this datetime
 *   customer_id     only this customer's
 *   ids             only these ids
 *   for_user        [id, is_manager]: staff see their own and unassigned
 *                   work in the morning plan; managers see everything
 *   jobs            true: only job plan parents; false (default): no parents
 */
function planner_fetch_tasks(PDO $db, int $biz_id, array $opt = []): array {
    $where  = ['t.business_id = ?', 't.deleted_at IS NULL'];
    $params = [$biz_id];

    $where[] = !empty($opt['jobs']) ? 't.plan IS NOT NULL AND t.parent_id IS NULL' : 't.plan IS NULL';

    if (!empty($opt['ids'])) {
        $ids = array_map('intval', $opt['ids']);
        $where[] = 't.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($opt['customer_id'])) {
        $where[] = 't.customer_id = ?';
        $params[] = (int)$opt['customer_id'];
    }
    if (!empty($opt['for_user'])) {
        [$uid, $manager] = $opt['for_user'];
        if ($manager) {
            $where[] = '(t.assigned_to = ? OR t.assigned_to IS NULL)';
            $params[] = (int)$uid;
        } else {
            $where[] = '(t.assigned_to = ? OR (t.assigned_to IS NULL AND t.created_by = ?))';
            array_push($params, (int)$uid, (int)$uid);
        }
    }

    if (isset($opt['from']) || isset($opt['to']) || !empty($opt['undated']) || !empty($opt['done_since'])) {
        $any = [];
        if (isset($opt['from'], $opt['to'])) {
            $any[] = '(t.due_date BETWEEN ? AND ?)';
            array_push($params, $opt['from'], $opt['to']);
            if (!empty($opt['late'])) {
                $any[] = '(t.due_date < ? AND t.done_at IS NULL)';
                $params[] = $opt['from'];
            }
        }
        if (!empty($opt['undated'])) {
            $any[] = '(t.due_date IS NULL AND t.done_at IS NULL)';
        }
        if (!empty($opt['done_since'])) {
            $any[] = '(t.done_at >= ?)';
            $params[] = $opt['done_since'];
        }
        if ($any) $where[] = '(' . implode(' OR ', $any) . ')';
    }

    $sql = "
        SELECT t.*,
               c.full_name AS customer_name, COALESCE(NULLIF(c.whatsapp, ''), c.phone) AS customer_phone,
               u.full_name AS assignee_name,
               p.title AS parent_title,
               (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id AND s.deleted_at IS NULL) AS step_total,
               (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = t.id AND s.deleted_at IS NULL AND s.done_at IS NOT NULL) AS step_done
        FROM tasks t
        LEFT JOIN customers c ON c.id = t.customer_id AND c.business_id = t.business_id
        LEFT JOIN users u     ON u.id = t.assigned_to AND u.business_id = t.business_id
        LEFT JOIN tasks p     ON p.id = t.parent_id AND p.deleted_at IS NULL
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.due_date IS NULL, t.due_date, t.due_time IS NULL, t.due_time, t.is_starred DESC, t.sort_order, t.id
        LIMIT 500";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** A tasks row (with the joins above) as a page item. */
function planner_task_item(array $t, string $today, array $business, ?array $next_step = null): array {
    $done = $t['done_at'] !== null;
    $is_job = $t['plan'] !== null && $t['parent_id'] === null;
    $time = $t['due_time'] ? substr($t['due_time'], 0, 5) : null;
    $first = planner_first_name($t['customer_name'] ?? '');

    $sub = [];
    if ($t['parent_title']) $sub[] = $t['parent_title'];
    if ($t['customer_name'] && !$t['parent_title']) $sub[] = $t['customer_name'];

    // Countdowns carry the pressure, so they go on promises: deliveries,
    // whole jobs, and anything already late. Not on every ordinary to-do.
    $countdown = null;
    if (!$done && $t['due_date']) {
        if ($is_job || $t['step_key'] === 'deliver') {
            $countdown = planner_countdown($t['due_date'], $today, $t['step_key'] === 'deliver' ? 'Deliver' : 'Due');
        } elseif ($t['due_date'] < $today) {
            $countdown = planner_countdown($t['due_date'], $today);
        }
    }

    $wa = null;
    if ($t['customer_id'] && $t['customer_phone'] !== null && $t['kind'] === 'appointment' && !$done && $t['due_date'] && $t['due_date'] >= $today) {
        $when = planner_when($t['due_date'], $today) . ($time ? ' at ' . planner_fmt_time($time) : '');
        $wa = planner_wa_link(
            $t['customer_phone'],
            "Hello {$first}, this is a reminder of your appointment with {$business['name']} {$when}: {$t['title']}. See you then."
        );
    }

    return planner_item([
        'id'            => 'task:' . $t['id'],
        'kind'          => $is_job ? 'job' : $t['kind'],
        'task_id'       => (int)$t['id'],
        'title'         => $t['title'],
        'subtitle'      => implode(' · ', $sub),
        'date'          => $t['due_date'],
        'time'          => $time,
        'end_time'      => $t['end_time'] ? substr($t['end_time'], 0, 5) : null,
        'starred'       => (bool)$t['is_starred'],
        'done'          => $done,
        'customer'      => $t['customer_id'] ? ['id' => (int)$t['customer_id'], 'name' => $t['customer_name']] : null,
        'assignee'      => $t['assigned_to'] ? ['id' => (int)$t['assigned_to'], 'name' => $t['assignee_name'], 'initials' => planner_initials($t['assignee_name'])] : null,
        'countdown'     => $countdown,
        'wa'            => $wa,
        'wa_label'      => $wa ? 'Remind ' . $first : null,
        'tick'          => 'task',
        'calendar'      => !$is_job,
        'notes'         => (string)$t['notes'],
        'repeat'        => $t['repeat_every'],
        'remind_before' => $t['remind_before'] !== null ? (int)$t['remind_before'] : null,
        'parent_id'     => $t['parent_id'] !== null ? (int)$t['parent_id'] : null,
        'parent_title'  => $t['parent_title'],
        'steps'         => $is_job ? ['done' => (int)$t['step_done'], 'total' => (int)$t['step_total'], 'next' => $next_step] : null,
        'invoice_id'    => $t['invoice_id'] !== null ? (int)$t['invoice_id'] : null,
        'created_by'    => (int)$t['created_by'],
        'plan'          => $t['plan'],
        'pressure'      => $t['step_key'] === 'deliver',
        'url'           => $t['invoice_id'] ? 'invoices/view/' . (int)$t['invoice_id'] : ($t['customer_id'] ? 'customers/view/' . (int)$t['customer_id'] : null),
    ]);
}

/** Job plans still open, each with its next unfinished step. */
function planner_open_jobs(PDO $db, int $biz_id, string $today, array $business, array $for_user = []): array {
    $opt = ['jobs' => true, 'undated' => true, 'from' => '2000-01-01', 'to' => '2999-12-31'];
    if ($for_user) $opt['for_user'] = $for_user;
    $rows = array_filter(planner_fetch_tasks($db, $biz_id, $opt), fn($r) => $r['done_at'] === null);
    if (!$rows) return [];

    $ids = array_column($rows, 'id');
    $stmt = $db->prepare("
        SELECT parent_id, title, due_date, due_time FROM tasks
        WHERE parent_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
          AND deleted_at IS NULL AND done_at IS NULL
        ORDER BY due_date IS NULL, due_date, due_time IS NULL, due_time, sort_order, id");
    $stmt->execute(array_map('intval', $ids));
    $next = [];
    foreach ($stmt->fetchAll() as $s) {
        $next[$s['parent_id']] ??= [
            'title' => $s['title'],
            'when'  => trim(planner_fmt_day($s['due_date'], $today) . ' ' . planner_fmt_time($s['due_time'])),
        ];
    }
    return array_values(array_map(fn($r) => planner_task_item($r, $today, $business, $next[$r['id']] ?? null), $rows));
}

/** All steps of one job plan, in order. */
function planner_job_steps(PDO $db, int $biz_id, int $parent_id, string $today, array $business): array {
    $stmt = $db->prepare("SELECT id FROM tasks WHERE business_id = ? AND parent_id = ? AND deleted_at IS NULL ORDER BY sort_order, id");
    $stmt->execute([$biz_id, $parent_id]);
    $ids = array_column($stmt->fetchAll(), 'id');
    if (!$ids) return [];
    $rows = planner_fetch_tasks($db, $biz_id, ['ids' => $ids]);
    usort($rows, fn($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
    return array_map(fn($r) => planner_task_item($r, $today, $business), $rows);
}

/* ── Automatic items ───────────────────────────────────────── */

/** Keys the owner ticked off, still in force today. */
function planner_dismissed_keys(PDO $db, int $biz_id, string $today): array {
    $stmt = $db->prepare("SELECT item_key FROM planner_dismissals WHERE business_id = ? AND (until_date IS NULL OR until_date >= ?)");
    $stmt->execute([$biz_id, $today]);
    return array_flip(array_column($stmt->fetchAll(), 'item_key'));
}

/**
 * Dates Nfelia already knows, as items. $from null means "everything up to
 * $to", which is how the Tasks tab gets late items. Options:
 *   money    include invoices due and debts (owner/admin only)
 *   undated  include items with no real date (shop orders waiting, restock)
 */
function planner_auto_items(PDO $db, int $biz_id, ?string $from, string $to, array $business, array $opt = []): array {
    $today  = planner_today();
    $cur    = $business['currency_symbol'] ?? '₦';
    $money  = !empty($opt['money']);
    $hidden = planner_dismissed_keys($db, $biz_id, $today);
    $items  = [];

    $range = function (string $col) use ($from, $to): array {
        return $from === null ? ["{$col} <= ?", [$to]] : ["{$col} BETWEEN ? AND ?", [$from, $to]];
    };

    // Promised deliveries on invoices. Not dismissable: the pressure is the
    // point. Ticking marks the order delivered.
    [$cond, $p] = $range('i.deliver_by');
    $stmt = $db->prepare("
        SELECT i.id, i.doc_number, i.deliver_by, i.deliver_mode, i.token,
               c.id AS cid, c.full_name, COALESCE(NULLIF(c.whatsapp, ''), c.phone) AS phone,
               (SELECT ii.description FROM invoice_items ii WHERE ii.invoice_id = i.id ORDER BY ii.sort_order, ii.id LIMIT 1) AS first_item,
               (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) AS item_count
        FROM invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.business_id = ? AND i.deliver_by IS NOT NULL AND i.delivered_at IS NULL
          AND i.status <> 'cancelled' AND {$cond}
          AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.invoice_id = i.id AND d.deleted_at IS NULL AND d.status IN ('delivered','signed_off'))
          AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.invoice_id = i.id AND t.step_key = 'deliver' AND t.deleted_at IS NULL)
        ORDER BY i.deliver_by LIMIT 100");
    $stmt->execute(array_merge([$biz_id], $p));
    foreach ($stmt->fetchAll() as $r) {
        $first = planner_first_name($r['full_name']);
        $what = (string)$r['first_item'];
        if ((int)$r['item_count'] > 1) $what .= ' +' . ((int)$r['item_count'] - 1) . ' more';
        $items[] = planner_item([
            'id' => 'auto:deliver:' . $r['id'], 'kind' => 'auto', 'source' => 'deliver',
            'title' => 'Deliver to ' . ($r['full_name'] ?: 'customer'),
            'subtitle' => trim($what . ' · ' . $r['doc_number'], ' ·'),
            'date' => $r['deliver_by'],
            'customer' => $r['cid'] ? ['id' => (int)$r['cid'], 'name' => $r['full_name']] : null,
            'countdown' => planner_countdown($r['deliver_by'], $today, 'Deliver'),
            'url' => 'invoices/view/' . $r['id'], 'invoice_id' => (int)$r['id'],
            'wa' => $r['phone'] ? planner_wa_link($r['phone'], "Hello {$first}, your order {$r['doc_number']} from {$business['name']} is on its way. We will deliver " . planner_when($r['deliver_by'], $today) . '.') : null,
            'wa_label' => $r['phone'] ? 'Update ' . $first : null,
            'tick' => 'delivered', 'pressure' => true,
        ]);
    }

    // Delivery notes with a planned date, unless the invoice already carries
    // a promise (that one is shown above).
    [$cond, $p] = $range('DATE(d.estimated_delivery)');
    $stmt = $db->prepare("
        SELECT d.id, d.doc_number, d.recipient_name, d.estimated_delivery, d.customer_id
        FROM deliveries d
        LEFT JOIN invoices i ON i.id = d.invoice_id
        WHERE d.business_id = ? AND d.deleted_at IS NULL
          AND d.status IN ('pending_dispatch','dispatched','in_transit')
          AND d.estimated_delivery IS NOT NULL AND {$cond}
          AND (i.id IS NULL OR i.deliver_by IS NULL OR i.delivered_at IS NOT NULL)
        ORDER BY d.estimated_delivery LIMIT 100");
    $stmt->execute(array_merge([$biz_id], $p));
    foreach ($stmt->fetchAll() as $r) {
        $date = substr($r['estimated_delivery'], 0, 10);
        $key = 'dn:' . $r['id'] . ':' . $date;
        if (isset($hidden[$key])) continue;
        $time = substr($r['estimated_delivery'], 11, 5);
        $items[] = planner_item([
            'id' => 'auto:' . $key, 'kind' => 'auto', 'source' => 'delivery',
            'title' => 'Delivery to ' . $r['recipient_name'],
            'subtitle' => $r['doc_number'],
            'date' => $date, 'time' => $time !== '00:00' ? $time : null,
            'countdown' => $date < $today ? planner_countdown($date, $today) : null,
            'url' => 'deliveries/view/' . $r['id'], 'tick' => 'dismiss',
        ]);
    }

    if ($money) {
        // Invoices with money still to come in.
        [$cond, $p] = $range('i.due_date');
        $stmt = $db->prepare("
            SELECT i.id, i.doc_number, i.due_date, i.total, i.balance_due, i.token,
                   c.id AS cid, c.full_name, COALESCE(NULLIF(c.whatsapp, ''), c.phone) AS phone
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ? AND i.status IN ('sent','partially_paid','overdue')
              AND i.balance_due > 0 AND i.due_date IS NOT NULL AND {$cond}
            ORDER BY i.due_date LIMIT 100");
        $stmt->execute(array_merge([$biz_id], $p));
        foreach ($stmt->fetchAll() as $r) {
            $key = 'inv:' . $r['id'] . ':' . $r['due_date'];
            if (isset($hidden[$key])) continue;
            $first = planner_first_name($r['full_name']);
            $bal = planner_money((float)$r['balance_due'], $cur);
            $items[] = planner_item([
                'id' => 'auto:' . $key, 'kind' => 'auto', 'source' => 'invoice_due',
                'title' => $bal . ' due from ' . ($r['full_name'] ?: 'customer'),
                'subtitle' => 'Invoice ' . $r['doc_number'],
                'date' => $r['due_date'],
                'customer' => $r['cid'] ? ['id' => (int)$r['cid'], 'name' => $r['full_name']] : null,
                'countdown' => $r['due_date'] <= $today ? planner_countdown($r['due_date'], $today) : null,
                'url' => 'invoices/view/' . $r['id'], 'invoice_id' => (int)$r['id'], 'amount' => (float)$r['balance_due'],
                'wa' => planner_wa_link($r['phone'],
                    "Hello {$first}, a gentle reminder from {$business['name']}: {$bal} is due on invoice {$r['doc_number']}"
                    . ($r['due_date'] < $today ? ' (it was due ' . planner_fmt_day_inline($r['due_date'], $today) . ')' : '')
                    . ".\n\nView it here: " . APP_URL . '/doc/' . $r['token'] . "\n\nThank you!"),
                'wa_label' => 'Remind ' . ($first ?: 'customer'),
                'tick' => 'dismiss',
            ]);
        }

        // Debts with a promised date. Invoice debts are covered above.
        [$cond, $p] = $range('d.due_date');
        $stmt = $db->prepare("
            SELECT d.id, d.type, d.balance, d.due_date, d.description,
                   COALESCE(NULLIF(d.counterparty_name, ''), c.full_name, s.full_name) AS party,
                   COALESCE(NULLIF(d.counterparty_phone, ''), NULLIF(c.whatsapp, ''), c.phone, NULLIF(s.whatsapp, ''), s.phone) AS phone,
                   d.customer_id
            FROM debts d
            LEFT JOIN customers c ON c.id = d.customer_id
            LEFT JOIN suppliers s ON s.id = d.supplier_id
            WHERE d.business_id = ? AND d.status IN ('active','settling')
              AND d.due_date IS NOT NULL AND d.source <> 'invoice' AND {$cond}
            ORDER BY d.due_date LIMIT 100");
        $stmt->execute(array_merge([$biz_id], $p));
        foreach ($stmt->fetchAll() as $r) {
            $key = 'debt:' . $r['id'] . ':' . $r['due_date'];
            if (isset($hidden[$key])) continue;
            $bal = planner_money((float)$r['balance'], $cur);
            $party = $r['party'] ?: 'Someone';
            $first = planner_first_name($party);
            $in = $r['type'] === 'receivable';
            $items[] = planner_item([
                'id' => 'auto:' . $key, 'kind' => 'auto', 'source' => $in ? 'debt_in' : 'debt_out',
                'title' => $in ? "{$party} promised to pay {$bal}" : "Pay {$party} {$bal}",
                'subtitle' => (string)$r['description'],
                'date' => $r['due_date'],
                'customer' => $r['customer_id'] ? ['id' => (int)$r['customer_id'], 'name' => $party] : null,
                'countdown' => $r['due_date'] <= $today ? planner_countdown($r['due_date'], $today) : null,
                'url' => 'debts/view/' . $r['id'], 'amount' => (float)$r['balance'],
                'wa' => $in && $r['phone'] ? planner_wa_link($r['phone'], "Hello {$first}, a gentle reminder from {$business['name']} about the balance of {$bal}"
                    . ($r['description'] ? ' for ' . $r['description'] : '') . '. Kindly let me know when you send it. Thank you!') : null,
                'wa_label' => $in && $r['phone'] ? 'Remind ' . $first : null,
                'tick' => 'dismiss',
            ]);
        }
    }

    // Quotes waiting on an answer: follow up after 3 days, or the day
    // before the quote expires if that comes first.
    $stmt = $db->prepare("
        SELECT q.id, q.doc_number, q.issue_date, q.expiry_date, q.total, q.token,
               c.id AS cid, c.full_name, COALESCE(NULLIF(c.whatsapp, ''), c.phone) AS phone
        FROM quotations q
        LEFT JOIN customers c ON c.id = q.customer_id
        WHERE q.business_id = ? AND q.status IN ('sent','in_negotiation','pending_signature')
          AND (q.expiry_date IS NULL OR q.expiry_date >= ?)
        ORDER BY q.issue_date LIMIT 100");
    $stmt->execute([$biz_id, $today]);
    foreach ($stmt->fetchAll() as $r) {
        $follow = planner_add_days($r['issue_date'], 3);
        $expires = false;
        if ($r['expiry_date'] && planner_add_days($r['expiry_date'], -1) < $follow) {
            $follow = max($r['issue_date'], planner_add_days($r['expiry_date'], -1));
            $expires = true;
        }
        if ($follow > $to || ($from !== null && $follow < $from)) continue;
        $key = 'quote:' . $r['id'] . ':' . $follow;
        if (isset($hidden[$key])) continue;
        $first = planner_first_name($r['full_name']);
        $items[] = planner_item([
            'id' => 'auto:' . $key, 'kind' => 'auto', 'source' => 'quote',
            'title' => $expires
                ? 'Quote for ' . ($r['full_name'] ?: 'customer') . ' ends ' . planner_fmt_day_inline($r['expiry_date'], $today)
                : 'Follow up ' . ($r['full_name'] ?: 'customer') . ' on your quote',
            'subtitle' => $r['doc_number'] . ' · ' . planner_money((float)$r['total'], $cur) . ' · sent ' . planner_fmt_day_inline($r['issue_date'], $today),
            'date' => $follow,
            'customer' => $r['cid'] ? ['id' => (int)$r['cid'], 'name' => $r['full_name']] : null,
            'url' => 'quotations/view/' . $r['id'],
            'wa' => planner_wa_link($r['phone'], "Hello {$first}, just checking in on the quote {$r['doc_number']} from {$business['name']}."
                . ($expires ? ' It is valid until ' . planner_fmt_day($r['expiry_date'], $today) . '.' : '')
                . "\n\n" . APP_URL . '/doc/' . $r['token'] . "\n\nAny questions? I'm happy to help."),
            'wa_label' => 'Follow up',
            'tick' => 'dismiss',
        ]);
    }

    if (!empty($opt['undated'])) {
        // Shop orders waiting for a yes or no. Confirming is done on the
        // order itself, so there is no tick here.
        $stmt = $db->prepare("
            SELECT id, order_number, customer_name, total, created_at
            FROM orders WHERE business_id = ? AND status IN ('pending','pending_verification')
            ORDER BY created_at LIMIT 50");
        $stmt->execute([$biz_id]);
        foreach ($stmt->fetchAll() as $r) {
            $placed = (new DateTimeImmutable($r['created_at'], planner_tz()))->format('Y-m-d');
            $wait = planner_days_between($placed, $today);
            $items[] = planner_item([
                'id' => 'auto:order:' . $r['id'], 'kind' => 'auto', 'source' => 'order',
                'title' => 'Confirm ' . $r['customer_name'] . "'s order",
                'subtitle' => $r['order_number'] . ' · ' . planner_money((float)$r['total'], $cur),
                'date' => $today,
                'countdown' => $wait > 0 ? ['label' => 'Waiting ' . $wait . ' day' . ($wait > 1 ? 's' : ''), 'tone' => 'late'] : ['label' => 'New', 'tone' => 'today'],
                'url' => 'orders/view/' . $r['id'], 'calendar' => false,
            ]);
        }

        // Products at or under their low-stock level. A tick hides one for a
        // week; adding stock clears it for good.
        $stmt = $db->prepare("
            SELECT id, name, stock_qty, unit FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_service = 0
              AND stock_qty <= low_stock_threshold
            ORDER BY stock_qty, name LIMIT 30");
        $stmt->execute([$biz_id]);
        foreach ($stmt->fetchAll() as $r) {
            $key = 'restock:' . $r['id'];
            if (isset($hidden[$key])) continue;
            $qty = rtrim(rtrim(number_format((float)$r['stock_qty'], 2, '.', ''), '0'), '.');
            $items[] = planner_item([
                'id' => 'auto:' . $key, 'kind' => 'auto', 'source' => 'restock',
                'title' => 'Restock ' . $r['name'],
                'subtitle' => ((float)$r['stock_qty'] <= 0 ? 'Sold out' : $qty . ' left'),
                'date' => $today, 'url' => 'products/view/' . $r['id'],
                'tick' => 'dismiss', 'calendar' => false,
            ]);
        }
    }

    return $items;
}

/** How long a hand tick hides an automatic item. NULL: for good. */
function planner_dismiss_until(string $key, string $today): ?string {
    // Hidden while until_date >= today, so today plus six is seven days (P16)
    return str_starts_with($key, 'restock:') ? planner_add_days($today, 6) : null;
}

/** Keys the page may send back for a dismissal. */
function planner_valid_auto_key(string $key): bool {
    return (bool)preg_match('/^(inv|debt|quote|dn):\d+:\d{4}-\d{2}-\d{2}$|^restock:\d+$/', $key);
}

/* ── Sorting into sections ─────────────────────────────────── */

/**
 * The Tasks tab: late, today, the next 7 days, no date, done today.
 * Inside a day: appointments by time, then starred, then the rest.
 */
function planner_sections(array $items, string $today): array {
    $out = ['late' => [], 'today' => [], 'soon' => [], 'undated' => [], 'done' => []];
    $week = planner_add_days($today, 7);
    foreach ($items as $it) {
        if ($it['done']) { $out['done'][] = $it; continue; }
        if (!$it['date'])            $out['undated'][] = $it;
        elseif ($it['date'] < $today) $out['late'][] = $it;
        elseif ($it['date'] === $today) $out['today'][] = $it;
        elseif ($it['date'] <= $week) $out['soon'][] = $it;
    }
    $key = fn(array $x) => [
        $x['date'] ?? '9999-12-31',
        !empty($x['pressure']) ? 0 : ($x['time'] ? 1 : 2),
        $x['time'] ?? '99:99',
        $x['starred'] ? 0 : 1,
        mb_strtolower($x['title']),
    ];
    foreach (['late', 'today', 'soon', 'undated'] as $k) {
        usort($out[$k], fn($a, $b) => $key($a) <=> $key($b));
    }
    return $out;
}

/* ── Job plans ─────────────────────────────────────────────── */

/**
 * Ready-made step plans. Offsets are days before the finish date; when the
 * finish date is too close, steps are squeezed between today and then.
 * {name} is the customer's first name.
 */
function planner_templates(): array {
    return [
        'sewing' => [
            'label' => 'Sewing job', 'hint' => 'Measurements to delivery',
            'title' => 'Sewing job for {name}', 'finish' => 'Deliver by',
            'steps' => [
                ['Take measurements', 11, 'task'],
                ['Buy fabric and materials', 9, 'task'],
                ['Fitting with {name}', 4, 'appointment'],
                ['Ready for pickup', 1, 'task'],
                ['Deliver to {name}', 0, 'task', 'deliver'],
            ],
        ],
        'order' => [
            'label' => 'Customer order', 'hint' => 'Payment, packing, delivery',
            'title' => 'Order for {name}', 'finish' => 'Deliver by',
            'steps' => [
                ['Confirm payment', 3, 'task'],
                ['Get the goods ready', 1, 'task'],
                ['Deliver to {name}', 0, 'task', 'deliver'],
            ],
        ],
        'event' => [
            'label' => 'Event', 'hint' => 'Deposit to the big day',
            'title' => 'Event for {name}', 'finish' => 'Event day',
            'steps' => [
                ['Collect the deposit', 21, 'task'],
                ['Book vendors', 14, 'task'],
                ['Confirm guest numbers with {name}', 7, 'task'],
                ['Collect the balance', 3, 'task'],
                ['Set-up', 1, 'task'],
                ['Event day', 0, 'appointment'],
            ],
        ],
        'supplier' => [
            'label' => 'Supplier order', 'hint' => 'Pay, track, restock',
            'title' => 'Supplier order', 'finish' => 'Arrives by',
            'steps' => [
                ['Pay the supplier', 7, 'task'],
                ['Check it is on the way', 3, 'task'],
                ['Goods arrive', 0, 'task'],
                ['Add the new stock to Nfelia', 0, 'task'],
            ],
        ],
        'custom' => [
            'label' => 'My own steps', 'hint' => 'Start from a blank plan',
            'title' => 'Job for {name}', 'finish' => 'Finish by',
            'steps' => [['', 0, 'task'], ['', 0, 'task'], ['', 0, 'task']],
        ],
    ];
}

/** Step dates for a template, never before today, never after the finish. */
function planner_plan_dates(array $offsets, string $finish, string $today): array {
    $max = $offsets ? max($offsets) : 0;
    $room = max(0, planner_days_between($today, $finish));
    return array_map(function ($off) use ($max, $room, $finish) {
        $back = ($max === 0 || $room >= $max) ? $off : (int)round($off * $room / $max);
        return planner_add_days($finish, -$back);
    }, $offsets);
}

/* ── "Deliver by" on invoices ──────────────────────────────── */

/**
 * Reads views/partials/deliver_fields.php from a form post. Returns
 * [columns for invoices, null] or [null, error message]. Immediate means
 * today; editing an immediate invoice later keeps its original day.
 */
function planner_delivery_from_post(array $post, ?array $current = null): array {
    $mode = $post['deliver_mode'] ?? '';
    if (!in_array($mode, ['immediate', 'date'], true)) {
        return [null, 'Choose when the customer gets it: Immediate, or pick a date.'];
    }
    if ($mode === 'immediate') {
        $date = ($current && ($current['deliver_mode'] ?? '') === 'immediate' && !empty($current['deliver_by']))
            ? $current['deliver_by'] : planner_today();
    } else {
        $date = (string)($post['deliver_by'] ?? '');
        if (!planner_valid_date($date)) return [null, 'Pick the delivery date.'];
    }
    $delivered_at = $current['delivered_at'] ?? null;
    if ($mode === 'immediate' && !empty($post['delivered_now']) && !$delivered_at) {
        $delivered_at = planner_now()->format('Y-m-d H:i:s');
    }
    return [[
        'deliver_mode' => $mode,
        'deliver_by'   => $date,
        'deliver_show' => !empty($post['deliver_show']) ? 1 : 0,
        'delivered_at' => $delivered_at,
    ], null];
}

/** The line customers see on their invoice, or '' when it is hidden. */
function planner_delivery_line(array $invoice): string {
    if (empty($invoice['deliver_show']) || empty($invoice['deliver_by'])) return '';
    if (!empty($invoice['delivered_at'])) return 'Delivered: ' . date('j M Y', strtotime($invoice['delivered_at']));
    return 'Delivery: ' . ($invoice['deliver_mode'] === 'immediate' ? 'Immediate' : date('D j M Y', strtotime($invoice['deliver_by'])));
}

/* ── Keeping tasks in step with what happened ─────────────── */

/**
 * Open tasks of people who no longer work here (removed, or not covered by
 * this month's payment) go to the owner, so their reminders still go out and
 * someone can open and change them (P1). Returns how many moved.
 */
function planner_hand_back_tasks(PDO $db, int $biz_id): int {
    $stmt = $db->prepare("SELECT id FROM users WHERE business_id = ? AND role = 'owner' AND is_active = 1 ORDER BY id LIMIT 1");
    $stmt->execute([$biz_id]);
    $owner = (int)$stmt->fetchColumn();
    if (!$owner) return 0;
    $stmt = $db->prepare("
        UPDATE tasks SET assigned_to = ?
        WHERE business_id = ? AND done_at IS NULL AND deleted_at IS NULL
          AND (assigned_to IN (SELECT id FROM users WHERE business_id = ? AND is_active = 0)
               OR (assigned_to IS NULL AND created_by IN (SELECT id FROM users WHERE business_id = ? AND is_active = 0)))
    ");
    $stmt->execute([$owner, $biz_id, $biz_id, $biz_id]);
    return $stmt->rowCount();
}

/** A job whose steps are all done (or deleted, with at least one done) is done too (P7). */
function planner_settle_job(PDO $db, int $biz_id, int $parent_id, ?int $user_id = null): void {
    $stmt = $db->prepare("SELECT SUM(done_at IS NULL), SUM(done_at IS NOT NULL) FROM tasks WHERE parent_id = ? AND business_id = ? AND deleted_at IS NULL");
    $stmt->execute([$parent_id, $biz_id]);
    [$open, $done] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0]);
    if ($open === 0 && $done > 0) {
        $db->prepare("UPDATE tasks SET done_at = COALESCE(done_at, NOW()), done_by = COALESCE(done_by, ?) WHERE id = ? AND business_id = ? AND deleted_at IS NULL")
           ->execute([$user_id, $parent_id, $biz_id]);
    } elseif ($open > 0) {
        $db->prepare("UPDATE tasks SET done_at = NULL, done_by = NULL WHERE id = ? AND business_id = ?")->execute([$parent_id, $biz_id]);
    }
}

/**
 * An invoice's goods reached the customer (or, with $undo, didn't): the
 * promise stops counting down, its delivery step is ticked and its job
 * finishes when nothing is left (P2). Called when the invoice is marked
 * delivered, its delivery note is delivered or signed for, or its shop order
 * is fulfilled.
 */
function planner_invoice_delivered(PDO $db, int $biz_id, int $invoice_id, bool $undo = false, ?int $user_id = null): void {
    $db->prepare("UPDATE invoices SET delivered_at = " . ($undo ? 'NULL' : 'COALESCE(delivered_at, NOW())') . " WHERE id = ? AND business_id = ?")
       ->execute([$invoice_id, $biz_id]);
    $db->prepare("UPDATE tasks SET done_at = " . ($undo ? 'NULL' : 'COALESCE(done_at, NOW())') . ", done_by = " . ($undo ? 'NULL' : 'COALESCE(done_by, ?)') . "
                  WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND deleted_at IS NULL")
       ->execute($undo ? [$invoice_id, $biz_id] : [$user_id, $invoice_id, $biz_id]);
    $stmt = $db->prepare("SELECT DISTINCT parent_id FROM tasks WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND deleted_at IS NULL AND parent_id IS NOT NULL");
    $stmt->execute([$invoice_id, $biz_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parent_id) {
        planner_settle_job($db, $biz_id, (int)$parent_id, $user_id);
    }
}

/**
 * A cancelled invoice promises nothing: its open delivery step goes, and
 * its job finishes if nothing else is left on it (P2).
 */
function planner_invoice_cancelled(PDO $db, int $biz_id, int $invoice_id): void {
    $stmt = $db->prepare("SELECT DISTINCT parent_id FROM tasks WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND done_at IS NULL AND deleted_at IS NULL");
    $stmt->execute([$invoice_id, $biz_id]);
    $parents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare("UPDATE tasks SET deleted_at = NOW() WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND done_at IS NULL AND deleted_at IS NULL")
       ->execute([$invoice_id, $biz_id]);
    foreach ($parents as $parent_id) {
        if ($parent_id) planner_settle_job($db, $biz_id, (int)$parent_id);
    }
}

/**
 * The delivery date of an invoice moved: its delivery step moves with its
 * reminder, and the job's finish date follows its latest step (P6).
 */
function planner_move_delivery(PDO $db, int $biz_id, int $invoice_id, string $date): void {
    $stmt = $db->prepare("SELECT id, parent_id, due_time, remind_before FROM tasks
                          WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND done_at IS NULL AND deleted_at IS NULL");
    $stmt->execute([$invoice_id, $biz_id]);
    $now = planner_now()->format('Y-m-d H:i:s');
    foreach ($stmt->fetchAll() as $step) {
        $remind_at = planner_remind_at($date, $step['due_time'], $step['remind_before'] !== null ? (int)$step['remind_before'] : null);
        $db->prepare("UPDATE tasks SET due_date = ?, remind_at = ?, reminded_at = ? WHERE id = ? AND business_id = ?")
           ->execute([$date, $remind_at, $remind_at !== null && $remind_at <= $now ? $now : null, $step['id'], $biz_id]);
        if ($step['parent_id']) {
            planner_job_finish_follows_steps($db, $biz_id, (int)$step['parent_id']);
        }
    }
}

/** A job's finish date is its latest step's date. */
function planner_job_finish_follows_steps(PDO $db, int $biz_id, int $parent_id): void {
    $stmt = $db->prepare("SELECT MAX(due_date) FROM tasks WHERE parent_id = ? AND business_id = ? AND deleted_at IS NULL");
    $stmt->execute([$parent_id, $biz_id]);
    if ($last = $stmt->fetchColumn()) {
        $db->prepare("UPDATE tasks SET due_date = ? WHERE id = ? AND business_id = ?")->execute([$last, $parent_id, $biz_id]);
    }
}

/* ── One person's day (morning plan, dashboard) ────────────── */

/**
 * Today's plan for one person: late items, today's items, and deliveries due
 * in the next two days, so a promise is nudged two days before, on the day
 * and every morning it is late.
 */
function planner_day_for_user(PDO $db, array $user, array $business, string $today): array {
    $manager = planner_is_manager($user);
    $tasks = planner_fetch_tasks($db, (int)$business['id'], [
        'from' => $today, 'to' => planner_add_days($today, 2), 'late' => true,
        'for_user' => [(int)$user['id'], $manager],
    ]);
    // After today, only the delivery steps of job plans: the plan's other
    // steps wait for their own morning.
    $tasks = array_filter($tasks, fn($t) => $t['due_date'] <= $today || $t['step_key'] === 'deliver');
    $items = array_map(fn($t) => planner_task_item($t, $today, $business), $tasks);
    if ($manager) {
        $items = array_merge($items, planner_auto_items($db, (int)$business['id'], null, planner_add_days($today, 2), $business, ['money' => true, 'undated' => true]));
    }
    $late = $now = $soon = [];
    foreach ($items as $it) {
        if ($it['done'] || !$it['date']) continue;
        if ($it['date'] < $today) $late[] = $it;
        elseif ($it['date'] === $today) $now[] = $it;
        elseif ($it['pressure']) $soon[] = $it;
    }
    $s = planner_sections(array_merge($late, $now), $today);
    usort($soon, fn($a, $b) => [$a['date'], mb_strtolower($a['title'])] <=> [$b['date'], mb_strtolower($b['title'])]);
    return ['late' => $s['late'], 'today' => $s['today'], 'soon' => $soon];
}

/**
 * The morning plan in one line, for the bell, the phone and the email:
 * "Today: 3 tasks (1 late), 1 delivery, ₦45,000 due". Money is what
 * customers owe today or already owe, never what the business owes.
 */
function planner_day_summary(array $day, string $symbol): array {
    $tasks = $late = $deliveries = $restock = $orders = 0;
    $money = 0.0;
    foreach (['late', 'today', 'soon'] as $section) {
        foreach ($day[$section] as $it) {
            if ($it['pressure']) { $deliveries++; continue; }
            if (in_array($it['source'], ['invoice_due', 'debt_in'], true)) { $money += (float)$it['amount']; continue; }
            // Restocking and shop orders are their own things, not tasks (P17)
            if ($it['source'] === 'restock') { $restock++; continue; }
            if ($it['source'] === 'order') { $orders++; continue; }
            $tasks++;
            if ($section === 'late') $late++;
        }
    }
    $parts = [];
    if ($tasks) $parts[] = $tasks . ' task' . ($tasks > 1 ? 's' : '') . ($late ? " ({$late} late)" : '');
    if ($orders) $parts[] = $orders . ' order' . ($orders > 1 ? 's' : '') . ' to confirm';
    if ($restock) $parts[] = $restock . ' to restock';
    if ($deliveries) $parts[] = $deliveries . ' ' . ($deliveries > 1 ? 'deliveries' : 'delivery');
    if ($money > 0) $parts[] = planner_money($money, $symbol) . ' due';
    $count = count($day['late']) + count($day['today']) + count($day['soon']);
    return ['count' => $count, 'line' => $parts ? 'Today: ' . implode(', ', $parts) : ''];
}

/** When a reminder is for, in a few words: "Today at 2:00pm", "Due tomorrow", "2 days late". */
function planner_reminder_when(array $it, string $today): string {
    if (!$it['date']) return '';
    if ($it['date'] < $today) return planner_countdown($it['date'], $today)['label'];
    $day = planner_fmt_day($it['date'], $today);
    if ($it['time'] && $it['end_time']) return $day . ', ' . planner_fmt_time($it['time']) . ' to ' . planner_fmt_time($it['end_time']);
    if ($it['time']) return $day . ' at ' . planner_fmt_time($it['time']);
    return $it['kind'] === 'appointment' ? $day : 'Due ' . planner_fmt_day_inline($it['date'], $today);
}

/**
 * Can this business use the app right now? The same test as
 * app/middleware/subscription_check.php, so reminders stop exactly when
 * access does. $b is a businesses row with the subscription's status,
 * trial_ends_at, current_period_end and grace_ends_at (null with no
 * subscription row, which the app lets through).
 */
function planner_business_can_use(array $b, DateTimeImmutable $now): bool {
    if (empty($b['is_active'])) return false;
    if (empty($b['sub_status'])) return true;
    require_once __DIR__ . '/billing.php';
    return billing_access([
        'status'             => $b['sub_status'],
        'trial_ends_at'      => $b['trial_ends_at'] ?? null,
        'current_period_end' => $b['current_period_end'] ?? null,
        'grace_ends_at'      => $b['grace_ends_at'] ?? null,
        'expiry_exempt'      => $b['expiry_exempt'] ?? 0,
    ], $now->format('Y-m-d')) !== 'locked';
}

/** White or ink text, whichever reads better on a business's own colour. */
function planner_text_on(string $hex): string {
    if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) return '#FFFFFF';
    $lum = 0.0;
    foreach ([0.2126, 0.7152, 0.0722] as $i => $w) {
        $c = hexdec(substr($m[1], $i * 2, 2)) / 255;
        $lum += $w * ($c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
    }
    // Contrast against white versus against ink (#1F0A14, luminance 0.0068).
    return 1.05 / ($lum + 0.05) >= ($lum + 0.05) / 0.0568 ? '#FFFFFF' : '#1F0A14';
}
