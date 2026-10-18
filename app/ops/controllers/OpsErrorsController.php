<?php
/**
 * Errors (Admin and Support): every problem recorded by app/helpers/errors.php
 * and the server log, grouped, with a status the team shares.
 *
 *   GET  errors                      the list (?q= text or an error code, ?status, ?kind, ?level, ?sort, ?page)
 *   GET  errors/{id}                 one problem: what it means, when, who, each time it happened
 *   GET  errors/code/{E1234567}      the problem behind an error code a client quoted
 *   POST errors/{id}/status          handling, fixed, ignored, or open again
 *   POST errors/{id}/assign          who is looking at it
 *   POST errors/{id}/notes           a note for the team
 *   POST errors/bulk                 fixed or ignored, for several at once
 */
class OpsErrorsController
{
    private const PAGE = 25;
    private const STATUSES = ['open' => 'Open', 'handling' => 'Being handled', 'fixed' => 'Fixed', 'ignored' => 'Ignored'];
    private const KINDS = ['php' => 'Server', 'js' => 'Browser', 'job' => 'Background job', 'log' => 'Server log', 'slow' => 'Slow page'];
    private const LEVELS = ['fatal' => 'Crash', 'error' => 'Error', 'warning' => 'Warning', 'notice' => 'Notice', 'deprecated' => 'Outdated code', 'slow' => 'Slow'];

    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $q      = trim((string)($_GET['q'] ?? ''));
        // An error code a client read out: straight to it.
        if (preg_match('/^E[A-Z2-9]{7}$/', strtoupper($q))) {
            $this->code(strtoupper($q));
        }
        $status = (string)($_GET['status'] ?? 'active');
        $kind   = (string)($_GET['kind'] ?? '');
        $level  = (string)($_GET['level'] ?? '');
        $sort   = in_array($_GET['sort'] ?? '', ['recent', 'count', 'new'], true) ? $_GET['sort'] : 'recent';
        $page   = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1 = 1'];
        $args  = [];
        if ($status === 'active') {
            $where[] = "g.status IN ('open', 'handling')";
        } elseif (isset(self::STATUSES[$status])) {
            $where[] = 'g.status = ?';
            $args[] = $status;
        }
        if (isset(self::KINDS[$kind]))   { $where[] = 'g.kind = ?';  $args[] = $kind; }
        if (isset(self::LEVELS[$level])) { $where[] = 'g.level = ?'; $args[] = $level; }
        if ($q !== '') {
            $where[] = '(g.title LIKE ? OR g.file LIKE ? OR g.route LIKE ?)';
            array_push($args, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        $order = match ($sort) {
            'count' => 'day_events DESC, g.events DESC',
            'new'   => 'g.first_seen_at DESC',
            default => 'g.last_seen_at DESC',
        };
        $sql = "FROM error_groups g WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT COUNT(*) " . $sql);
        $stmt->execute($args);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PAGE));
        $page  = min($page, $pages);

        $stmt = $this->db->prepare("
            SELECT g.*, s.full_name AS assignee,
                   (SELECT COUNT(*) FROM error_events e WHERE e.group_id = g.id AND e.created_at > NOW() - INTERVAL 1 DAY) AS day_events,
                   (SELECT COUNT(DISTINCT e.business_id) FROM error_events e WHERE e.group_id = g.id AND e.business_id IS NOT NULL) AS clients
            FROM error_groups g LEFT JOIN ops_staff s ON s.id = g.assigned_to
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$order} LIMIT " . self::PAGE . " OFFSET " . (($page - 1) * self::PAGE));
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        ops_render('errors/index', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'filter' => ['q' => $q, 'status' => $status, 'kind' => $kind, 'level' => $level, 'sort' => $sort],
            'statuses' => self::STATUSES, 'kinds' => self::KINDS, 'levels' => self::LEVELS,
        ], 'Errors');
    }

    public function show(string $id): void
    {
        $group = $this->group((int)$id);
        $db = $this->db;

        $stmt = $db->prepare("
            SELECT e.*, b.name AS business_name, u.full_name AS user_name
            FROM error_events e
            LEFT JOIN businesses b ON b.id = e.business_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.group_id = ? ORDER BY e.id DESC LIMIT " . ERRORS_KEEP_EVENTS);
        $stmt->execute([$group['id']]);
        $events = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT e.business_id, b.name, COUNT(*) AS n, MAX(e.created_at) AS last_at
            FROM error_events e JOIN businesses b ON b.id = e.business_id
            WHERE e.group_id = ? GROUP BY e.business_id ORDER BY n DESC LIMIT 20");
        $stmt->execute([$group['id']]);
        $clients = $stmt->fetchAll();

        // The last 14 days, a bar a day (Lagos days, as the database counts them).
        $stmt = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM error_events WHERE group_id = ? AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d");
        $stmt->execute([$group['id']]);
        $by_day = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $today = new DateTimeImmutable((string)$db->query("SELECT CURDATE()")->fetchColumn());
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $today->modify("-{$i} days")->format('Y-m-d');
            $days[$d] = (int)($by_day[$d] ?? 0);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM error_events WHERE group_id = ? AND created_at > NOW() - INTERVAL 1 DAY");
        $stmt->execute([$group['id']]);
        $day_events = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT n.*, s.full_name FROM error_notes n LEFT JOIN ops_staff s ON s.id = n.staff_id WHERE n.group_id = ? ORDER BY n.id");
        $stmt->execute([$group['id']]);
        $notes = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT a.*, s.full_name, NULL AS business_name FROM ops_audit a LEFT JOIN ops_staff s ON s.id = a.staff_id
                              WHERE a.target_type = 'error' AND a.target_id = ? ORDER BY a.id DESC LIMIT 20");
        $stmt->execute([$group['id']]);
        $history = $stmt->fetchAll();

        $team = $db->query("SELECT id, full_name, role FROM ops_staff WHERE is_active = 1 AND password_hash IS NOT NULL ORDER BY full_name")->fetchAll();
        $team = array_values(array_filter($team, fn($t) => ops_can('errors', $t)));

        ops_render('errors/show', [
            'g' => $group, 'events' => $events, 'clients' => $clients, 'days' => $days, 'day_events' => $day_events,
            'notes' => $notes, 'history' => $history, 'team' => $team, 'focus' => strtoupper((string)($_GET['ref'] ?? '')),
            'explain' => ops_error_explain($group), 'copy' => ops_error_copy_text($group, $events, $clients, $day_events),
            'statuses' => self::STATUSES, 'kinds' => self::KINDS, 'levels' => self::LEVELS,
        ], mb_substr($group['title'], 0, 60));
    }

    public function code(string $ref): never
    {
        $stmt = $this->db->prepare("SELECT group_id FROM error_events WHERE ref = ?");
        $stmt->execute([strtoupper($ref)]);
        $group_id = $stmt->fetchColumn();
        if (!$group_id) {
            ops_flash('error', 'No error has the code ' . strtoupper($ref) . '. Occurrences are kept for 30 days, and the latest 50 of each problem.');
            ops_redirect('errors');
        }
        ops_redirect('errors/' . (int)$group_id . '?ref=' . strtoupper($ref) . '#' . strtoupper($ref));
    }

    public function status(string $id): void
    {
        $group = $this->group((int)$id);
        $to = (string)($_POST['status'] ?? '');
        if (!isset(self::STATUSES[$to]) || $to === $group['status']) {
            ops_redirect('errors/' . $group['id']);
        }
        $this->set_status([$group], $to);
        if ($to === 'handling' && !$group['assigned_to']) {
            $this->db->prepare("UPDATE error_groups SET assigned_to = ? WHERE id = ?")->execute([$this->me['id'], $group['id']]);
        }
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note !== '') {
            $this->add_note((int)$group['id'], $note);
        }
        ops_flash('success', match ($to) {
            'fixed'    => 'Marked fixed. If it happens again, it opens again and the team is told.',
            'ignored'  => 'Ignored. It stays counted, but nobody is told about it again.',
            'handling' => 'Marked as being handled by ' . ($group['assigned_to'] ? 'the person assigned' : 'you') . '.',
            default    => 'Open again.',
        });
        ops_redirect('errors/' . $group['id']);
    }

    public function assign(string $id): void
    {
        $group = $this->group((int)$id);
        $to = (int)($_POST['staff_id'] ?? 0);
        $person = $to ? ops_staff_by_id($to) : null;
        if ($to && (!$person || !(int)$person['is_active'] || !ops_can('errors', $person))) {
            ops_redirect('errors/' . $group['id']);
        }
        $this->db->prepare("UPDATE error_groups SET assigned_to = ? WHERE id = ?")->execute([$to ?: null, $group['id']]);
        ops_audit('error.assign', ($person ? 'Gave "' . mb_substr($group['title'], 0, 80) . '" to ' . $person['full_name'] : 'Took nobody on "' . mb_substr($group['title'], 0, 80) . '"'),
                  ['type' => 'error', 'id' => (int)$group['id']]);
        if ($person && (int)$person['id'] !== (int)$this->me['id']) {
            require_once BASE_PATH . '/app/ops/alerts.php';
            ops_alert_one($this->db, (int)$person['id'], 'error.assigned', $this->me['full_name'] . ' gave you an error', mb_substr($group['title'], 0, 160),
                          'errors/' . $group['id'], 'error-assigned:' . $group['id'] . ':' . time());
        }
        ops_flash('success', $person ? $person['full_name'] . ' has it.' : 'Nobody has it now.');
        ops_redirect('errors/' . $group['id']);
    }

    public function note(string $id): void
    {
        $group = $this->group((int)$id);
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') {
            $this->add_note((int)$group['id'], $body);
        }
        ops_redirect('errors/' . $group['id'] . '#notes');
    }

    public function bulk(): void
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))), 0, 200);
        $to  = (string)($_POST['status'] ?? '');
        if (!$ids || !in_array($to, ['fixed', 'ignored', 'open'], true)) {
            ops_flash('error', 'Tick at least one, then choose what to do.');
            ops_redirect(ops_back('errors'));
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM error_groups WHERE id IN ({$in}) AND status <> ?");
        $stmt->execute([...$ids, $to]);
        $groups = $stmt->fetchAll();
        $this->set_status($groups, $to);
        ops_flash('success', count($groups) . ' marked ' . strtolower(self::STATUSES[$to]) . '.');
        ops_redirect(ops_back('errors'));
    }

    /* ── Shared ───────────────────────────────────────────── */

    private function group(int $id): array
    {
        $stmt = $this->db->prepare("SELECT g.*, s.full_name AS assignee FROM error_groups g LEFT JOIN ops_staff s ON s.id = g.assigned_to WHERE g.id = ?");
        $stmt->execute([$id]);
        $g = $stmt->fetch();
        if (!$g) {
            ops_not_found();
        }
        return $g;
    }

    private function set_status(array $groups, string $to): void
    {
        $set = $this->db->prepare("UPDATE error_groups SET status = ?, status_by = ?, status_changed_at = NOW() WHERE id = ?");
        foreach ($groups as $g) {
            $set->execute([$to, $this->me['id'], $g['id']]);
            ops_audit('error.' . $to, self::STATUSES[$to] . ': "' . mb_substr($g['title'], 0, 120) . '"',
                      ['type' => 'error', 'id' => (int)$g['id'], 'data' => ['from' => $g['status'], 'to' => $to, 'release' => app_release()]]);
        }
    }

    private function add_note(int $group_id, string $body): void
    {
        $this->db->prepare("INSERT INTO error_notes (group_id, staff_id, body) VALUES (?, ?, ?)")->execute([$group_id, $this->me['id'], mb_substr($body, 0, 4000)]);
        ops_audit('error.note', 'Wrote a note on an error', ['type' => 'error', 'id' => $group_id]);
    }
}

/**
 * What a problem means, in a sentence a non-engineer on the team can act on.
 * Message patterns first, then the level.
 */
function ops_error_explain(array $g): string
{
    $m = $g['title'] . ' ' . $g['message'];
    $rules = [
        '/syntax error|unexpected (token|end of file)/i'          => 'The code itself has a typo, so the page cannot run at all. A fix needs a new release.',
        '/SQLSTATE\[(23000)\]|Duplicate entry/i'                   => 'The database refused to save a second copy of something that must be unique (a number, an email, a link).',
        '/SQLSTATE\[(42S22|42S02)\]|Unknown column|doesn\'t exist/i' => 'The code asked the database for a column or table that is not there: usually a migration that has not run on this server.',
        '/SQLSTATE|PDOException|mysqli/i'                          => 'A database query failed. The message says which; the stack trace says where.',
        '/Connection refused|Can\'t connect|Too many connections|gone away/i' => 'The database could not be reached or was overloaded at that moment.',
        '/Allowed memory size .* exhausted/i'                      => 'The page ran out of memory: usually a very large list, image or export being loaded all at once.',
        '/Maximum execution time/i'                                => 'The page took longer than the server allows and was stopped.',
        '/failed to open stream|No such file or directory/i'       => 'A file the code expected is missing (an upload, a template, a folder).',
        '/Permission denied|Operation not permitted/i'             => 'The server would not let the app read or write a file or folder. Check the folder\'s permissions.',
        '/Call to (a member function|undefined (method|function))/i' => 'The code used something that was not there, often because a lookup found nothing and the code did not check.',
        '/Trying to access array offset on|on value of type null/i' => 'The code read from something that was empty (a lookup that found nothing).',
        '/Undefined (array key|index)/i'                           => 'The code read a value that was not set. Often harmless, but the page may show a blank where something belongs.',
        '/Undefined variable/i'                                    => 'The code used a name it never set: a bug, usually showing as a blank on the page.',
        '/headers already sent/i'                                  => 'Something was printed before the page could redirect or set a cookie, so that step failed.',
        '/Division by zero|Modulo by zero/i'                       => 'A sum divided by zero, usually a total or percentage of nothing.',
        '/Passing null to parameter|Implicit conversion|Creation of dynamic property|Deprecated/i' => 'Code that newer PHP versions will refuse. Fine for now; worth tidying before the next PHP upgrade.',
        '/signature did not match/i'                               => 'Someone posted to the payment webhook without Paystack\'s signature, or the secret key in .env no longer matches the Paystack dashboard.',
        '/SMTP|mail server/i'                                      => 'An email could not be handed to the mail server.',
        '/push service/i'                                          => 'A phone notification could not be handed to the phone\'s push service.',
        '/Unhandled promise/i'                                     => 'A script in the browser waited on something (usually a request to Nfelia) that failed, and nothing handled the failure.',
        '/is not defined|is not a function|Cannot read propert/i'  => 'A script in the browser used something that was not there, so part of the page stopped working for that person.',
    ];
    foreach ($rules as $re => $sentence) {
        if (preg_match($re, $m)) return $sentence;
    }
    return match (true) {
        $g['kind'] === 'slow'     => 'This page took more than ' . (ERRORS_SLOW_MS / 1000) . ' seconds. On a phone on mobile data, that feels broken.',
        $g['kind'] === 'job'      => 'A background job (the cron, a webhook) hit a problem. The person it was for may not have got what they should.',
        $g['level'] === 'fatal'   => 'The page stopped with an error: the person saw "Something went wrong" instead of what they asked for.',
        $g['level'] === 'error'   => 'Something failed, and what the person was doing may not have happened.',
        $g['level'] === 'warning' => 'Something unexpected happened, but the page carried on. Worth a look if it is frequent.',
        default                   => '',
    };
}

/** One problem as plain text, to paste into an AI chat or a message. */
function ops_error_copy_text(array $g, array $events, array $clients, int $day_events): string
{
    $lines = [];
    $lines[] = '[' . strtoupper($g['level']) . ' × ' . (int)$g['events'] . '] ' . $g['title'];
    $lines[] = 'Kind: ' . $g['kind'] . ($g['route'] ? ', page ' . $g['route'] : '');
    if ($g['file']) $lines[] = 'Where: ' . $g['file'] . ($g['line'] ? ':' . $g['line'] : '');
    $lines[] = 'First seen: ' . $g['first_seen_at'] . ' (release ' . ($g['first_release'] ?: '?') . '), last seen: ' . $g['last_seen_at'] . ' (release ' . ($g['last_release'] ?: '?') . '), Lagos time';
    $lines[] = 'In the last 24 hours: ' . $day_events . '. Clients affected: ' . count($clients) . '.';
    if ($g['message'] && $g['message'] !== $g['title']) {
        $lines[] = 'Latest message: ' . mb_substr($g['message'], 0, 1500);
    }
    $latest = $events[0] ?? null;
    if ($latest) {
        $lines[] = 'Latest occurrence: ' . $latest['ref'] . ' on ' . ($latest['method'] ? $latest['method'] . ' ' : '') . ($latest['path'] ?: '?') . ' (' . $latest['source'] . ')';
        if ($latest['trace']) {
            $lines[] = "Stack trace:\n" . mb_substr($latest['trace'], 0, 3000);
        }
    }
    $pages = array_values(array_unique(array_filter(array_column(array_slice($events, 0, 20), 'path'))));
    if (count($pages) > 1) {
        $lines[] = 'Pages it happened on: ' . implode(', ', array_slice($pages, 0, 8));
    }
    $lines[] = 'The app: Nfelia, PHP 8.3 without a framework (index.php front controller, app/controllers, app/helpers, views), MariaDB 10.11.';
    return implode("\n", $lines);
}
