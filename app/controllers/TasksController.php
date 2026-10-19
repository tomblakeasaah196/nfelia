<?php
/**
 * Nfelia — Tasks & Calendar
 *
 * One page, two tabs. The Tasks tab is the day: late, today, the next week,
 * jobs in progress. The Calendar tab is the month. Both mix the owner's own
 * tasks and appointments with the dates Nfelia already knows (see
 * app/helpers/planner.php).
 *
 * The page talks JSON. Every change returns the refreshed Tasks tab, so the
 * screen always shows what the database holds, with no client-side guessing.
 *
 * Staff see the whole plan except money (invoices due, debts), which stays
 * with owner and admin, matching who can open Debts. Staff edit their own
 * and assigned work; only owner/admin delete someone else's.
 */
class TasksController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  PAGE
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();
        $today    = planner_today();

        $tab = ($_GET['tab'] ?? '') === 'calendar' ? 'calendar' : 'tasks';

        // Prefill from other pages: /tasks?new=appointment&customer=12,
        // /tasks?plan=sewing&invoice=7, and /tasks?reminders=1 from the
        // reminder emails, which opens the Reminders sheet.
        $prefill = [
            'reminders' => !empty($_GET['reminders']),
            'new'      => in_array($_GET['new'] ?? '', ['task', 'appointment'], true) ? $_GET['new'] : null,
            'plan'     => array_key_exists($_GET['plan'] ?? '', planner_templates()) ? $_GET['plan'] : null,
            'customer' => (int)($_GET['customer'] ?? 0) ?: null,
            'invoice'  => (int)($_GET['invoice'] ?? 0) ?: null,
            'order'    => null,
            'date'     => planner_valid_date($_GET['date'] ?? null) ? $_GET['date'] : null,
            'task'     => $this->task_to_open((int)($_GET['task'] ?? 0), $user, $business, $today),
        ];

        // /tasks?new=task&order=7 from an order page: the task is for that
        // order and its customer
        if (!empty($_GET['order']) && ($order = order_find($this->db, $this->biz_id, (int)$_GET['order']))) {
            $prefill['order'] = ['id' => (int)$order['id'], 'number' => $order['order_number']];
            $prefill['customer'] = $prefill['customer'] ?: ((int)$order['customer_id'] ?: null);
        }

        // The same choices as More > Notifications (app/helpers/notify.php).
        $mine  = notify_prefs_for($this->db, [(int)$user['id']])[(int)$user['id']];
        $prefs = ['morning_email' => $mine['morning']['email'], 'reminder_email' => $mine['reminders']['email'],
                  'morning_push' => $mine['morning']['push'], 'reminder_push' => $mine['reminders']['push']];

        $data = [
            'today'     => $today,
            'tab'       => $tab,
            'board'     => $this->board($user, $business, $today),
            'customers' => $this->customers(),
            'staff'     => $this->staff(),
            'invoices'  => $this->open_invoices(),
            'templates' => $this->templates_for_page(),
            'remind'    => ['timed' => planner_remind_options(true), 'untimed' => planner_remind_options(false)],
            'me'        => ['id' => (int)$user['id'], 'manager' => planner_is_manager($user), 'name' => $user['full_name']],
            'prefs'     => ['morning_email' => (bool)$prefs['morning_email'], 'reminder_email' => (bool)$prefs['reminder_email'],
                            'morning_push' => (bool)$prefs['morning_push'], 'reminder_push' => (bool)$prefs['reminder_push']],
            'push'      => ['enabled' => push_enabled(), 'key' => VAPID_PUBLIC_KEY],
            'prefill'   => $prefill,
            'business'  => ['name' => $business['name']],
        ];

        $title     = 'Tasks & Calendar';
        $view_file = BASE_PATH . '/views/tasks/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  JSON: calendar month
     * ────────────────────────────────────────── */
    public function feed() {
        $user     = auth_user();
        $business = auth_business();
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';
        if (!planner_valid_date($from) || !planner_valid_date($to) || $to < $from || planner_days_between($from, $to) > 62) {
            json_response(['error' => 'Pick a month to show.'], 422);
        }
        $today = planner_today();

        $tasks = planner_fetch_tasks($this->db, $this->biz_id, ['from' => $from, 'to' => $to]);
        $items = array_map(fn($t) => planner_task_item($t, $today, $business), $tasks);
        $auto  = planner_auto_items($this->db, $this->biz_id, $from, $to, $business, ['money' => planner_is_manager($user)]);
        $items = array_values(array_filter(array_merge($items, $auto), fn($i) => $i['calendar'] && $i['date']));

        json_response(['success' => true, 'items' => $items]);
    }

    /* ──────────────────────────────────────────
     *  JSON: the steps of one job plan
     * ────────────────────────────────────────── */
    public function job($id = null) {
        $business = auth_business();
        $parent = $this->find_task((int)$id);
        if (!$parent || $parent['plan'] === null) {
            json_response(['error' => 'That job plan was not found.'], 404);
        }
        json_response([
            'success' => true,
            'steps'   => planner_job_steps($this->db, $this->biz_id, (int)$id, planner_today(), $business),
        ]);
    }

    /* ──────────────────────────────────────────
     *  JSON: create or update a task / appointment
     * ────────────────────────────────────────── */
    public function save() {
        $in   = $this->json_in();
        $user = auth_user();
        $id   = (int)($in['id'] ?? 0);

        $existing = null;
        if ($id) {
            $existing = $this->find_task($id);
            if (!$existing) json_response(['error' => 'That task was not found. It may have been deleted.'], 404);
            if (!$this->can_edit($existing, $user)) json_response(['error' => 'Only the owner or the person it belongs to can change this.'], 403);
        }

        $kind  = ($in['kind'] ?? 'task') === 'appointment' ? 'appointment' : 'task';
        $title = trim((string)($in['title'] ?? ''));
        $date  = ($in['date'] ?? '') !== '' ? (string)$in['date'] : null;
        $time  = ($in['time'] ?? '') !== '' ? substr((string)$in['time'], 0, 5) : null;
        $end   = ($in['end_time'] ?? '') !== '' ? substr((string)$in['end_time'], 0, 5) : null;

        $errors = [];
        if ($title === '') $errors['title'] = 'Write what needs doing.';
        if (mb_strlen($title) > 200) $errors['title'] = 'Keep it under 200 characters.';
        if ($date !== null && !planner_valid_date($date)) $errors['date'] = 'Pick a real date.';
        if ($kind === 'appointment' && $date === null) $errors['date'] = 'Pick the day of the appointment.';
        if ($time !== null && !planner_valid_time($time)) $errors['time'] = 'Pick a time like 2:00pm.';
        if ($date === null) { $time = null; }
        if ($kind !== 'appointment' || $time === null) { $end = null; }
        if ($end !== null && (!planner_valid_time($end) || $end <= $time)) $errors['end_time'] = 'The end time must be after the start.';

        $customer_id = (int)($in['customer_id'] ?? 0) ?: null;
        if ($customer_id && !$this->owns('customers', $customer_id)) $errors['customer_id'] = 'Pick a customer from your list.';

        $assigned_to = (int)($in['assigned_to'] ?? 0) ?: null;
        if ($assigned_to && !$this->is_staff_member($assigned_to)) $errors['assigned_to'] = 'Pick someone on your team.';

        $repeat = in_array($in['repeat'] ?? '', ['day', 'week', 'month'], true) ? $in['repeat'] : null;
        if ($repeat && $date === null) $errors['repeat'] = 'A repeating task needs a date to start from.';

        $remind = isset($in['remind_before']) && $in['remind_before'] !== '' && $in['remind_before'] !== null ? (int)$in['remind_before'] : null;
        if ($remind !== null && !array_key_exists($remind, planner_remind_options($time !== null))) $remind = null;
        if ($date === null) $remind = null;

        if ($errors) json_response(['error' => 'Check the highlighted fields.', 'fields' => $errors], 422);

        $notes   = trim((string)($in['notes'] ?? '')) ?: null;
        $starred = !empty($in['starred']) ? 1 : 0;
        // A monthly repeat keeps the day it was set for: the 31st stays the
        // 31st (or the month's last day), even after a short month (P11)
        $repeat_day = $repeat === 'month' && $date !== null ? (int)substr($date, 8, 2) : null;
        [$remind_at, $reminded_at] = $this->arm($date, $time, $remind);

        if ($existing) {
            $this->db->prepare("
                UPDATE tasks SET kind = ?, title = ?, notes = ?, is_starred = ?, due_date = ?, due_time = ?, end_time = ?,
                       customer_id = ?, assigned_to = ?, repeat_every = ?, repeat_day = ?, remind_before = ?, remind_at = ?, reminded_at = ?
                WHERE id = ? AND business_id = ?
            ")->execute([$kind, $title, $notes, $starred, $date, $time, $end, $customer_id, $assigned_to, $repeat, $repeat_day,
                         $remind, $remind_at, $reminded_at, $id, $this->biz_id]);

            // A delivery step and its invoice keep the same promised date; an
            // order's moves its delivery note too, and goes in its Log
            if ($existing['step_key'] === 'deliver' && $existing['invoice_id'] && $date) {
                $order = order_by_invoice($this->db, $this->biz_id, (int)$existing['invoice_id']);
                $moved = false;
                if ($order && $date !== $existing['due_date']) {
                    try {
                        order_set_when($this->db, $this->biz_id, (int)$order['id'], $date, (int)$user['id']);
                        $moved = true;
                    } catch (DomainException $e) {
                        // done or not confirmed: the step's own date is all that changes
                    }
                }
                if (!$moved) {
                    $this->db->prepare("UPDATE invoices SET deliver_mode = 'date', deliver_by = ? WHERE id = ? AND business_id = ?")
                             ->execute([$date, $existing['invoice_id'], $this->biz_id]);
                }
            }
            // A step's new date can move its job's finish date (P6)
            if ($existing['parent_id'] !== null && $date) {
                planner_job_finish_follows_steps($this->db, $this->biz_id, (int)$existing['parent_id']);
            }
        } else {
            // Added from an order's page: it belongs to that order (its Tasks)
            $order_id = null;
            if (!empty($in['order_id']) && ($order = order_find($this->db, $this->biz_id, (int)$in['order_id']))) {
                $order_id = (int)$order['id'];
            }
            $this->db->prepare("
                INSERT INTO tasks (business_id, kind, title, notes, is_starred, due_date, due_time, end_time, customer_id,
                                   assigned_to, repeat_every, repeat_day, remind_before, remind_at, reminded_at, created_by, order_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$this->biz_id, $kind, $title, $notes, $starred, $date, $time, $end, $customer_id,
                         $assigned_to, $repeat, $repeat_day, $remind, $remind_at, $reminded_at, $user['id'], $order_id]);
            $id = (int)$this->db->lastInsertId();
            if ($order_id) {
                order_log($this->db, $this->biz_id, $order_id, 'task', 'Task added: ' . $title . '.', (int)$user['id'], ['task_id' => $id]);
            }
        }

        $was = (int)($existing['assigned_to'] ?? 0);
        if ($assigned_to && $assigned_to !== (int)$user['id'] && $was !== $assigned_to) {
            $this->tell_assignee($id, $assigned_to, $title, $date, $time, $user);
        }
        // Taken off someone's list by someone else: they hear who has it now.
        if ($was && $was !== (int)$user['id'] && $was !== (int)$assigned_to) {
            $to_name = '';
            if ($assigned_to) {
                $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$assigned_to]);
                $to_name = planner_first_name((string)$stmt->fetchColumn());
            }
            notify('task.unassigned', $this->biz_id, [
                'task_id' => $id, 'title' => $title, 'by' => planner_first_name($user['full_name']), 'to_name' => $to_name,
            ], ['users' => [$was], 'actor' => (int)$user['id'], 'dedupe' => $id . ':off:' . $was . ':' . time()]);
        }

        $this->respond($existing ? 'Saved.' : ($kind === 'appointment' ? 'Appointment added.' : 'Task added.'));
    }

    /* ──────────────────────────────────────────
     *  JSON: tick a task done, or undo
     * ────────────────────────────────────────── */
    public function tick() {
        $in   = $this->json_in();
        $user = auth_user();
        $task = $this->find_task((int)($in['id'] ?? 0));
        if (!$task) json_response(['error' => 'That task was not found. It may have been deleted.'], 404);

        $done = !empty($in['done']);
        $message = $done ? 'Done.' : 'Marked not done.';

        if ($done && $task['done_at'] === null) {
            $this->db->prepare("UPDATE tasks SET done_at = NOW(), done_by = ? WHERE id = ?")->execute([$user['id'], $task['id']]);

            // Someone finished a task another person gave them: that person hears.
            if ($task['assigned_to'] && (int)$task['created_by'] !== (int)$user['id']) {
                notify('task.done', $this->biz_id, [
                    'task_id' => (int)$task['id'], 'title' => $task['title'], 'by' => planner_first_name($user['full_name']),
                ], ['users' => [(int)$task['created_by']], 'actor' => (int)$user['id'], 'dedupe' => $task['id'] . ':done:' . planner_today()]);
            }

            // Repeats: the next one appears when this one is ticked.
            if ($task['repeat_every'] && $task['due_date']) {
                $next = planner_next_date($task['due_date'], $task['repeat_every'], planner_today(),
                                          isset($task['repeat_day']) ? (int)$task['repeat_day'] : null);
                [$remind_at, $reminded_at] = $this->arm($next, $task['due_time'], $task['remind_before'] !== null ? (int)$task['remind_before'] : null);
                $this->db->prepare("
                    INSERT INTO tasks (business_id, kind, title, notes, is_starred, due_date, due_time, end_time, customer_id,
                                       assigned_to, repeat_every, repeat_day, remind_before, remind_at, reminded_at, spawned_from, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$this->biz_id, $task['kind'], $task['title'], $task['notes'], $task['is_starred'], $next,
                             $task['due_time'], $task['end_time'], $task['customer_id'], $task['assigned_to'], $task['repeat_every'],
                             $task['repeat_day'] ?? null, $task['remind_before'], $remind_at, $reminded_at, $task['id'], $task['created_by']]);
                $message = 'Done. Next one: ' . planner_fmt_day($next, planner_today()) . '.';
            }

            // Ticking a whole job finishes its steps; the last step finishes the job.
            if ($task['plan'] !== null && $task['parent_id'] === null) {
                $this->db->prepare("UPDATE tasks SET done_at = NOW(), done_by = ? WHERE parent_id = ? AND done_at IS NULL AND deleted_at IS NULL")
                         ->execute([$user['id'], $task['id']]);
                $this->deliver_invoice_for_job((int)$task['id'], (int)$user['id']);
                $message = 'Job done.';
            } elseif ($task['parent_id'] !== null) {
                $left = $this->db->prepare("SELECT COUNT(*) FROM tasks WHERE parent_id = ? AND done_at IS NULL AND deleted_at IS NULL");
                $left->execute([$task['parent_id']]);
                if ((int)$left->fetchColumn() === 0) {
                    $this->db->prepare("UPDATE tasks SET done_at = NOW(), done_by = ? WHERE id = ? AND done_at IS NULL")->execute([$user['id'], $task['parent_id']]);
                    $message = 'Last step done. Job finished.';
                }
            }

            if ($task['step_key'] === 'deliver' && $task['invoice_id']) {
                order_from_invoice_delivered($this->db, $this->biz_id, (int)$task['invoice_id'], false, (int)$user['id']);
            }
        } elseif (!$done && $task['done_at'] !== null) {
            $this->db->prepare("UPDATE tasks SET done_at = NULL, done_by = NULL WHERE id = ?")->execute([$task['id']]);
            // Undo removes the repeat it created, if nobody has touched it.
            $this->db->prepare("UPDATE tasks SET deleted_at = NOW() WHERE spawned_from = ? AND done_at IS NULL AND deleted_at IS NULL")->execute([$task['id']]);
            if ($task['parent_id'] !== null) {
                $this->db->prepare("UPDATE tasks SET done_at = NULL, done_by = NULL WHERE id = ?")->execute([$task['parent_id']]);
            }
            if ($task['step_key'] === 'deliver' && $task['invoice_id']) {
                order_from_invoice_delivered($this->db, $this->biz_id, (int)$task['invoice_id'], true, (int)$user['id']);
            }
        }

        $this->respond($message);
    }

    /* ──────────────────────────────────────────
     *  JSON: star / unstar
     * ────────────────────────────────────────── */
    public function star() {
        $in   = $this->json_in();
        $task = $this->find_task((int)($in['id'] ?? 0));
        if (!$task) json_response(['error' => 'That task was not found.'], 404);
        $this->db->prepare("UPDATE tasks SET is_starred = 1 - is_starred WHERE id = ?")->execute([$task['id']]);
        $this->respond($task['is_starred'] ? 'Star removed.' : 'Starred.');
    }

    /* ──────────────────────────────────────────
     *  JSON: delete (a job takes its steps with it)
     * ────────────────────────────────────────── */
    public function delete() {
        $in   = $this->json_in();
        $user = auth_user();
        $task = $this->find_task((int)($in['id'] ?? 0));
        if (!$task) json_response(['error' => 'That task was not found. It may already be deleted.'], 404);
        if (!planner_is_manager($user) && (int)$task['created_by'] !== (int)$user['id']) {
            json_response(['error' => 'Only the owner or the person who added it can delete this.'], 403);
        }
        $this->db->prepare("UPDATE tasks SET deleted_at = NOW() WHERE business_id = ? AND (id = ? OR parent_id = ?)")
                 ->execute([$this->biz_id, $task['id'], $task['id']]);
        // A job whose last open step went is finished (P7)
        if ($task['parent_id'] !== null) {
            planner_settle_job($this->db, $this->biz_id, (int)$task['parent_id'], (int)$user['id']);
        }
        $this->respond($task['plan'] !== null ? 'Job plan deleted.' : 'Deleted.');
    }

    /* ──────────────────────────────────────────
     *  JSON: tick off an automatic item, or undo
     * ────────────────────────────────────────── */
    public function dismiss() {
        $in   = $this->json_in();
        $user = auth_user();
        $key  = (string)($in['key'] ?? '');
        if (!planner_valid_auto_key($key)) json_response(['error' => 'That item cannot be ticked off.'], 422);
        if (!planner_is_manager($user) && preg_match('/^(inv|debt):/', $key)) {
            json_response(['error' => 'Only the owner or an admin can clear money items.'], 403);
        }

        if (!empty($in['undo'])) {
            $this->db->prepare("DELETE FROM planner_dismissals WHERE business_id = ? AND item_key = ?")->execute([$this->biz_id, $key]);
            $this->respond('Brought back.');
        }
        $this->db->prepare("
            INSERT INTO planner_dismissals (business_id, item_key, until_date, dismissed_by) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE until_date = VALUES(until_date), dismissed_by = VALUES(dismissed_by)
        ")->execute([$this->biz_id, $key, planner_dismiss_until($key, planner_today()), $user['id']]);
        $this->respond(str_starts_with($key, 'restock:') ? 'Hidden for a week. Adding stock clears it for good.' : 'Ticked off.');
    }

    /* ──────────────────────────────────────────
     *  JSON: mark an invoice's order delivered, or undo. An order moves
     *  with its delivery note, the customer and its Log (orders.php).
     * ────────────────────────────────────────── */
    public function delivered() {
        $in = $this->json_in();
        $invoice_id = (int)($in['invoice_id'] ?? 0);
        if (!$this->owns('invoices', $invoice_id)) json_response(['error' => 'That invoice was not found.'], 404);
        order_from_invoice_delivered($this->db, $this->biz_id, $invoice_id, !empty($in['undo']), (int)auth_user()['id']);
        $this->respond(!empty($in['undo']) ? 'Back on the list.' : 'Marked delivered.');
    }

    /* ──────────────────────────────────────────
     *  JSON: create a job plan from a template
     * ────────────────────────────────────────── */
    public function plan() {
        $in   = $this->json_in();
        $user = auth_user();
        $templates = planner_templates();

        $key    = (string)($in['template'] ?? '');
        $finish = (string)($in['finish'] ?? '');
        $title  = trim((string)($in['title'] ?? ''));
        $customer_id = (int)($in['customer_id'] ?? 0) ?: null;
        $invoice_id  = (int)($in['invoice_id'] ?? 0) ?: null;
        $assigned_to = (int)($in['assigned_to'] ?? 0) ?: null;

        $errors = [];
        if (!isset($templates[$key])) $errors['template'] = 'Pick a plan.';
        if (!planner_valid_date($finish)) $errors['finish'] = 'Pick the finish date.';
        if ($title === '') $errors['title'] = 'Give the job a name.';
        if ($customer_id && !$this->owns('customers', $customer_id)) $errors['customer_id'] = 'Pick a customer from your list.';
        if ($invoice_id && !$this->owns('invoices', $invoice_id)) $errors['invoice_id'] = 'Pick one of your invoices.';
        if ($assigned_to && !$this->is_staff_member($assigned_to)) $errors['assigned_to'] = 'Pick someone on your team.';

        $steps = [];
        foreach (array_slice((array)($in['steps'] ?? []), 0, 20) as $s) {
            $st = trim((string)($s['title'] ?? ''));
            if ($st === '') continue;
            $sd = (string)($s['date'] ?? '');
            $stime = ($s['time'] ?? '') !== '' ? substr((string)$s['time'], 0, 5) : null;
            if (!planner_valid_date($sd)) { $errors['steps'] = 'Every step needs a date.'; break; }
            if ($stime !== null && !planner_valid_time($stime)) { $errors['steps'] = 'Check the step times.'; break; }
            $steps[] = [
                'title' => mb_substr($st, 0, 200), 'date' => $sd, 'time' => $stime,
                'kind' => ($s['kind'] ?? '') === 'appointment' ? 'appointment' : 'task',
                'deliver' => !empty($s['deliver']),
            ];
        }
        if (!$steps && !$errors) $errors['steps'] = 'Add at least one step.';
        if ($errors) json_response(['error' => 'Check the highlighted fields.', 'fields' => $errors], 422);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                INSERT INTO tasks (business_id, kind, title, due_date, customer_id, invoice_id, plan, assigned_to, created_by)
                VALUES (?, 'task', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$this->biz_id, mb_substr($title, 0, 200), $finish, $customer_id, $invoice_id, $key, $assigned_to, $user['id']]);
            $parent_id = (int)$this->db->lastInsertId();

            $ins = $this->db->prepare("
                INSERT INTO tasks (business_id, parent_id, kind, title, due_date, due_time, customer_id, invoice_id, step_key,
                                   remind_before, remind_at, reminded_at, assigned_to, created_by, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $has_deliver = false;
            foreach ($steps as $i => $s) {
                $step_key = null;
                if ($s['deliver'] && $invoice_id && !$has_deliver) { $step_key = 'deliver'; $has_deliver = true; }
                // A fitting or event gets a nudge the day before; the morning
                // plan already covers ordinary steps.
                $remind = $s['kind'] === 'appointment' ? 1440 : null;
                [$remind_at, $reminded_at] = $this->arm($s['date'], $s['time'], $remind);
                $ins->execute([$this->biz_id, $parent_id, $s['kind'], $s['title'], $s['date'], $s['time'], $customer_id,
                               $invoice_id, $step_key, $remind, $remind_at, $reminded_at, $assigned_to, $user['id'], $i + 1]);
            }

            // The invoice carries the same promise as the plan.
            if ($invoice_id) {
                $this->db->prepare("UPDATE invoices SET deliver_mode = 'date', deliver_by = ? WHERE id = ? AND business_id = ? AND delivered_at IS NULL")
                         ->execute([$finish, $invoice_id, $this->biz_id]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Nfelia job plan: ' . $e->getMessage());
            json_response(['error' => 'The plan could not be saved. Try again.'], 500);
        }

        if ($assigned_to && $assigned_to !== (int)$user['id']) {
            $this->tell_assignee($parent_id, $assigned_to, $title, $finish, null, $user);
        }
        $this->respond('Job plan added: ' . count($steps) . ' steps.');
    }

    /* ──────────────────────────────────────────
     *  JSON: my reminder choices
     * ────────────────────────────────────────── */
    public function prefs() {
        $in   = $this->json_in();
        $user = auth_user();
        // Phone and email for each, the same switches as More > Notifications.
        notify_set_pref($this->db, (int)$user['id'], 'morning', array_key_exists('morning_push', $in) ? !empty($in['morning_push']) : null, !empty($in['morning_email']));
        notify_set_pref($this->db, (int)$user['id'], 'reminders', array_key_exists('reminder_push', $in) ? !empty($in['reminder_push']) : null, !empty($in['reminder_email']));
        json_response(['success' => true, 'message' => 'Saved.']);
    }

    /* ──────────────────────────────────────────
     *  JSON: phone notifications on this device
     *  (The PWA uses the same endpoints.)
     * ────────────────────────────────────────── */
    public function push_test() {
        $this->json_in();
        if (!throttle_allows('push_test', (string)auth_user()['id'], 5, 60)) {
            json_response(['error' => 'That is enough tests for now. Try again in an hour.'], 429);
        }
        [$ok, $message] = notify_test($this->biz_id, (int)auth_user()['id'], 'push');
        json_response($ok ? ['success' => true, 'message' => $message] : ['error' => $message], $ok ? 200 : 502);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    /** The Tasks tab: sections, open jobs, and whether staff exist. */
    private function board(array $user, array $business, string $today): array {
        $tasks = planner_fetch_tasks($this->db, $this->biz_id, [
            'from' => $today, 'to' => planner_add_days($today, 7), 'late' => true,
            'undated' => true, 'done_since' => $today . ' 00:00:00',
        ]);
        $items = array_map(fn($t) => planner_task_item($t, $today, $business), $tasks);
        $auto  = planner_auto_items($this->db, $this->biz_id, null, planner_add_days($today, 7), $business,
                                    ['money' => planner_is_manager($user), 'undated' => true]);
        return [
            'sections' => planner_sections(array_merge($items, $auto), $today),
            'jobs'     => planner_open_jobs($this->db, $this->biz_id, $today, $business),
        ];
    }

    private function respond(string $message): never {
        json_response([
            'success' => true,
            'message' => $message,
            'board'   => $this->board(auth_user(), auth_business(), planner_today()),
        ]);
    }

    /** JSON body of a POST, after the CSRF check. */
    private function json_in(): array {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Send this as a POST.'], 405);
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            json_response(['error' => 'Your session expired. Reload the page and try again.'], 419);
        }
        $in = json_decode(file_get_contents('php://input') ?: '[]', true);
        return is_array($in) ? $in : [];
    }

    private function find_task(int $id): ?array {
        if ($id <= 0) return null;
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * /tasks?task=12, from a reminder or an assignment alert: that task (or
     * job), opened on arrival, when this person may see it.
     */
    private function task_to_open(int $id, array $user, array $business, string $today): ?array {
        $task = $this->find_task($id);
        if (!$task || !$this->can_edit($task, $user)) return null;
        $is_job = $task['plan'] !== null && $task['parent_id'] === null;
        $rows = planner_fetch_tasks($this->db, $this->biz_id, ['ids' => [$id], 'jobs' => $is_job]);
        return $rows ? planner_task_item($rows[0], $today, $business) : null;
    }

    private function can_edit(array $task, array $user): bool {
        return planner_is_manager($user)
            || (int)$task['created_by'] === (int)$user['id']
            || (int)($task['assigned_to'] ?? 0) === (int)$user['id'];
    }

    /** Does this business own the row? (customers / invoices only.) */
    private function owns(string $table, int $id): bool {
        if ($id <= 0 || !in_array($table, ['customers', 'invoices'], true)) return false;
        $stmt = $this->db->prepare("SELECT 1 FROM {$table} WHERE id = ? AND business_id = ?");
        $stmt->execute([$id, $this->biz_id]);
        return (bool)$stmt->fetchColumn();
    }

    private function is_staff_member(int $user_id): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
        $stmt->execute([$user_id, $this->biz_id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * remind_at for the queue, and whether it is already spent: a reminder
     * time that has passed is stamped as sent, so editing an old task never
     * fires a stale reminder.
     */
    private function arm(?string $date, ?string $time, ?int $before): array {
        $at = planner_remind_at($date, $time, $before);
        if ($at === null) return [null, null];
        return [$at, $at <= planner_now()->format('Y-m-d H:i:s') ? planner_now()->format('Y-m-d H:i:s') : null];
    }

    /**
     * Ticking a whole job delivers its invoice, as the delivery step would:
     * an order's moves on to done (with its Log and the customer's message),
     * an invoice on its own is marked delivered.
     */
    private function deliver_invoice_for_job(int $parent_id, int $user_id): void {
        $stmt = $this->db->prepare("SELECT DISTINCT invoice_id FROM tasks WHERE parent_id = ? AND business_id = ? AND step_key = 'deliver' AND invoice_id IS NOT NULL AND deleted_at IS NULL");
        $stmt->execute([$parent_id, $this->biz_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $invoice_id) {
            order_from_invoice_delivered($this->db, $this->biz_id, (int)$invoice_id, false, $user_id);
        }
    }

    /** The person a task was given to hears, as they chose (More > Notifications), never in their quiet hours. */
    private function tell_assignee(int $task_id, int $to, string $title, ?string $date, ?string $time, array $from): void {
        notify('task.assigned', $this->biz_id, [
            'task_id' => $task_id, 'title' => $title, 'from' => planner_first_name($from['full_name']),
            'when' => $date ? trim(planner_fmt_day($date, planner_today()) . ' ' . planner_fmt_time($time)) : '',
        ], ['users' => [$to], 'actor' => (int)$from['id'], 'dedupe' => $task_id . ':to:' . $to . ':' . time()]);
    }

    private function customers(): array {
        $stmt = $this->db->prepare("
            SELECT id, full_name AS name, COALESCE(NULLIF(whatsapp, ''), phone) AS phone
            FROM customers WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_walk_in = 0
            ORDER BY full_name LIMIT 2000");
        $stmt->execute([$this->biz_id]);
        return array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'phone' => (string)$c['phone']], $stmt->fetchAll());
    }

    private function staff(): array {
        $stmt = $this->db->prepare("SELECT id, full_name, role FROM users WHERE business_id = ? AND is_active = 1 ORDER BY role = 'owner' DESC, full_name");
        $stmt->execute([$this->biz_id]);
        $rows = $stmt->fetchAll();
        // One person means no team: the page hides every "assign" control.
        if (count($rows) < 2) return [];
        return array_map(fn($u) => ['id' => (int)$u['id'], 'name' => $u['full_name'], 'initials' => planner_initials($u['full_name'])], $rows);
    }

    /** Invoices a job plan can be linked to: not cancelled, not yet delivered. */
    private function open_invoices(): array {
        $stmt = $this->db->prepare("
            SELECT i.id, i.doc_number, i.customer_id, i.deliver_by, c.full_name, o.order_number
            FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
            LEFT JOIN orders o ON o.id = i.order_id AND o.business_id = i.business_id
            WHERE i.business_id = ? AND i.status <> 'cancelled' AND i.delivered_at IS NULL
            ORDER BY i.id DESC LIMIT 200");
        $stmt->execute([$this->biz_id]);
        return array_map(fn($r) => [
            // An order's invoice goes by its order number, the one everyone uses
            'id' => (int)$r['id'], 'label' => ($r['order_number'] ?: $r['doc_number']) . ($r['full_name'] ? ' · ' . $r['full_name'] : ''),
            'customer_id' => $r['customer_id'] !== null ? (int)$r['customer_id'] : null, 'deliver_by' => $r['deliver_by'],
        ], $stmt->fetchAll());
    }

    private function templates_for_page(): array {
        $out = [];
        foreach (planner_templates() as $key => $t) {
            $out[] = [
                'key' => $key, 'label' => $t['label'], 'hint' => $t['hint'], 'title' => $t['title'], 'finish' => $t['finish'],
                'steps' => array_map(fn($s) => ['title' => $s[0], 'offset' => $s[1], 'kind' => $s[2], 'deliver' => ($s[3] ?? '') === 'deliver'], $t['steps']),
            ];
        }
        return $out;
    }
}
