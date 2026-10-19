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
 *   POST errors/export               the ticked ones as a Markdown file for an AI
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

    /** The ticked problems as one Markdown file, with a brief, to hand to an AI. */
    public function export(): void
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))), 0, 50);
        if (!$ids) {
            ops_flash('error', 'Tick at least one, then download.');
            ops_redirect(ops_back('errors'));
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM error_groups WHERE id IN ({$in}) ORDER BY last_seen_at DESC");
        $stmt->execute($ids);
        $groups = $stmt->fetchAll();
        if (!$groups) {
            ops_redirect(ops_back('errors'));
        }

        $events  = $this->db->prepare("SELECT ref, created_at, method, path, source, message, trace, user_agent, release_id, duration_ms FROM error_events WHERE group_id = ? ORDER BY id DESC LIMIT 5");
        $day     = $this->db->prepare("SELECT COUNT(*) FROM error_events WHERE group_id = ? AND created_at > NOW() - INTERVAL 1 DAY");
        $clients = $this->db->prepare("SELECT COUNT(DISTINCT business_id) FROM error_events WHERE group_id = ? AND business_id IS NOT NULL");
        $notes   = $this->db->prepare("SELECT body FROM error_notes WHERE group_id = ? ORDER BY id");
        foreach ($groups as &$g) {
            $events->execute([$g['id']]);
            $g['latest'] = $events->fetchAll();
            $day->execute([$g['id']]);
            $g['day_events'] = (int)$day->fetchColumn();
            $clients->execute([$g['id']]);
            $g['clients'] = (int)$clients->fetchColumn();
            $notes->execute([$g['id']]);
            $g['notes'] = $notes->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($g);

        ops_audit('error.export', 'Downloaded ' . count($groups) . ' error' . (count($groups) === 1 ? '' : 's') . ' for AI',
                  ['data' => ['ids' => array_map('intval', array_column($groups, 'id'))]]);

        // Handed to an AI to fix: being handled, by whoever downloaded them unless someone has them already.
        $open = array_values(array_filter($groups, fn($g) => $g['status'] === 'open'));
        $this->set_status($open, 'handling');
        $claim = $this->db->prepare("UPDATE error_groups SET assigned_to = ? WHERE id = ? AND assigned_to IS NULL");
        foreach ($groups as &$g) {
            if ($g['status'] === 'open') {
                $g['status'] = 'handling';
                $claim->execute([$this->me['id'], $g['id']]);
            }
        }
        unset($g);

        $md = ops_errors_markdown($groups, $this->me['full_name'] ?? '', self::KINDS, self::LEVELS, self::STATUSES);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="nfelia-errors-' . date('Y-m-d-Hi') . '.md"');
        header('Cache-Control: no-store');
        echo $md;
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

/**
 * Several problems as one Markdown file for an AI that has the repo: the brief
 * first (drop what is already fixed, find the root cause, ask the owner two
 * questions, fix, open a pull request and drive it to green), then each
 * problem with its latest occurrences. No client or person names: the file
 * leaves Nfelia.
 */
function ops_errors_markdown(array $groups, string $by, array $kinds, array $levels, array $statuses): string
{
    $fence = function (string $text): string {
        preg_match_all('/`+/', $text, $m);
        $ticks = str_repeat('`', max(3, ...array_map(fn($r) => strlen($r) + 1, $m[0] ?: [''])));
        return $ticks . "\n" . rtrim($text) . "\n" . $ticks;
    };
    $one_line = fn(string $s): string => trim(preg_replace('/\s+/', ' ', $s));
    $n = count($groups);

    $ids = implode(', ', array_map(fn($g) => '#' . (int)$g['id'], $groups));
    $md = [];
    $md[] = '# Nfelia errors to fix: ' . $n . ' problem' . ($n === 1 ? '' : 's') . ' (' . $ids . ')';
    $md[] = '';
    $md[] = 'Downloaded from Nfelia Ops on ' . date('j M Y, H:i') . ' Lagos time' . ($by !== '' ? ' by ' . $by : '') . '. Live release: `' . app_release() . '`.';
    $md[] = 'Ops marked these "Being handled". This file is the whole brief: work through it to the end without waiting to be told more, except for the questions in step 4.';
    $md[] = '';
    $md[] = rtrim(<<<'BRIEF'
## Your job

You are fixing production errors in **Nfelia** (live at nfelia.com), in the GitHub repo `tomblakeasaah196/nfelia`. `main` is production: merging to it deploys. Take each problem below from report to a green pull request:

1. **Get the code.** Work in the repo, on the branch your session gives you, or a new branch from the latest `origin/main`. Read `CLAUDE.md` first and follow it: it says how to ask the owner questions. Read `README.md` and `DEPLOY.md`, and the handoff in `docs/handoff/` for the area a problem is in (`Ops.md` for Nfelia Ops itself, `Orders hub.md`, `PWA app.md` and so on).

2. **Check what is already done.** Before any root-cause work, decide for each problem whether it still needs a fix:
   - `git fetch origin main`, then `git log --oneline <last release>..origin/main` and `git log -S` / `-G` on the file, function or message. Each problem gives the release (commit) it was last seen on. A later commit that changed that code may have fixed it already.
   - Search the history for `Fixes-Errors:` naming its number, and the open pull requests for one that already deals with it.
   - Read the code as it is now on `origin/main` at the file, line and page given. If the line no longer exists or now guards the case, say so.
   - Sort every problem into one of: **Already fixed** (name the commit or PR, and why you are sure), **Not ours** (a browser extension, a bot, a very old browser, or an old cached copy of a script; say what shows it), or **To fix**.
   Put this as a short table in your first reply. Work only on **To fix** from here on. If nothing is left to fix, say so and stop.

3. **Find the root cause** of each one left. Several problems are often one bug showing on different pages or in different ways; group them. Follow the stack trace into the code. For a browser problem, read `assets/js/` (including `errors.js`, which reports them), the view that renders the page, `sw.js` (the app keeps scripts on the phone; read the comment at its top) and the browser it happened in. For a server problem, read the file and line, the trace, and the controller and helper behind the page. Reproduce it if you can. Say what causes it, the evidence, and how sure you are. Fix causes, not symptoms: catching and silencing an error is not a fix unless the error is truly harmless, and then say why.

4. **Ask the owner 2 questions, then wait.** Use the **AskUserQuestion** tool, exactly as `CLAUDE.md` says: 2 to 4 concrete options per question, each with a one-line trade-off, your recommendation first with "(Recommended)" in its label and why in its description. Ask the 2 questions whose answers most change the fix: behaviour the owner will notice, a trade-off that is theirs, how far the fix should go. Never ask what the code, the history or this file can answer. 2 is the standard. Go up to 6 (no more than 4 in one call) only when the problems are separate enough that 2 cannot cover them, and say why you need more. If the AskUserQuestion tool is not available to you, ask in the same shape in chat. Do not change code until the owner has answered.

5. **Fix it.** The smallest change that removes the cause, written like the code around it (naming, comment density, plain words in anything a person reads). The rules this repo has learned the hard way:
   - Never pin a server-specific collation (the MySQL 8 only ones; see "Configuration" in README.md). CI fails the build if one appears.
   - Schema changes are new files in `db/migrations/` (`YYYYMMDDhhmmss_description.sql`): small, idempotent, forward-only. Update `db/schema.sql` only if the change itself requires it.
   - Styles: edit `assets/css/src/` and rebuild with `npm run build:css`. The compiled `assets/css/app.css` is committed. `assets/css/ops.css` is hand-written.
   - Never commit `.env`, keys or database dumps. Secrets live only in the server's `.env`.
   - Add or extend a test that fails without the fix, next to the ones for that area: `tests/ops/`, `tests/orders/`, `tests/notifications/`. Copy their style.

6. **Check it before pushing.** Run `php -l` on every PHP file you changed. If you can run MariaDB 10.11, set up the test copy the way `.github/workflows/deploy.yml` does (the `.env` it writes, then `bash tests/notifications/setup.sh`, then serve with `php -S 127.0.0.1:8099 -t . tests/notifications/router.php`), and run the suites you touched with `bash tests/notifications/run.sh <suite>` (for example `ops/monitor` or `orders/core`). Re-read your diff as a reviewer would.

7. **Commit, and say what it fixes.** End each fixing commit's message with a line of its own:

   `Fixes-Errors: #12, #15`

   using the problem numbers below. When that commit is deployed, `bin/deploy.sh` marks those problems fixed in Ops. If one happens again after that, Ops opens it again and tells the team, so only list a problem the commit really fixes. Put the same line in the pull request description.

8. **Open a pull request to `main`, and drive it to green.** In its description give, for each problem: the root cause, the fix, and how the owner can check it on nfelia.com (which page, what to do, what they should now see). List the problems that were already fixed or not ours, and why. Leave out stack traces, browser strings and anything about clients. Then watch the pull request (subscribe to its activity if you can) and keep going until CI is green: the **Lint, secret sweep, deploy-exclusion proof** and **Notifications and Nfelia Ops, end to end** jobs must pass. Fix every failure your change causes at its root, answer review comments, and never skip or disable a test to get green. **Do not merge:** merging deploys to production, and that is the owner's call.

9. **Report back in a few lines:** the pull request link, what each problem was, what was already fixed or not ours, and anything the owner must do (merge, then check the page). For a problem marked **Not ours**, suggest they press **Ignore** on it in Ops.

BRIEF);
    $md[] = '';
    $md[] = '## The problems';
    $md[] = '';
    $md[] = '| Number | Problem | Kind | Today | In all |';
    $md[] = '|---|---|---|---|---|';
    foreach ($groups as $i => $g) {
        $md[] = '| #' . (int)$g['id'] . ' | ' . str_replace('|', '\|', mb_strimwidth($one_line($g['title']), 0, 90, '…')) . ' | ' . ($kinds[$g['kind']] ?? $g['kind']) . ' | ' . (int)$g['day_events'] . ' | ' . (int)$g['events'] . ' |';
    }

    foreach ($groups as $i => $g) {
        $md[] = '';
        $md[] = '---';
        $md[] = '';
        $md[] = '### #' . (int)$g['id'] . ' · ' . mb_strimwidth($one_line($g['title']), 0, 200, '…');
        $md[] = '';
        $md[] = '- **What:** ' . ($levels[$g['level']] ?? $g['level']) . ', ' . ($kinds[$g['kind']] ?? $g['kind']) . ' (' . $g['kind'] . '/' . $g['level'] . '). Status: ' . ($statuses[$g['status']] ?? $g['status']) . '.';
        if ($g['file']) $md[] = '- **Where in the code:** `' . $g['file'] . ($g['line'] ? ':' . $g['line'] : '') . '`';
        if ($g['route']) $md[] = '- **Page:** `' . $g['route'] . '`';
        $md[] = '- **How often:** ' . (int)$g['day_events'] . ' in the last 24 hours, ' . (int)$g['events'] . ' in all, for ' . (int)$g['clients'] . ' client' . ((int)$g['clients'] === 1 ? '' : 's') . '.';
        $md[] = '- **First seen:** ' . $g['first_seen_at'] . ' (release ' . ($g['first_release'] ?: '?') . '). **Last seen:** ' . $g['last_seen_at'] . ' (release ' . ($g['last_release'] ?: '?') . ').';
        if ((int)$g['regressions']) $md[] = '- **Came back** ' . (int)$g['regressions'] . ' time' . ((int)$g['regressions'] === 1 ? '' : 's') . ' after being marked fixed.';
        $explain = ops_error_explain($g);
        if ($explain !== '') $md[] = '- **In plain words:** ' . $explain;
        $md[] = '- **In Ops:** ' . ops_url('errors/' . (int)$g['id']);
        if ($g['message'] && $g['message'] !== $g['title']) {
            $md[] = '';
            $md[] = '**Full message:**';
            $md[] = '';
            $md[] = $fence(mb_substr($g['message'], 0, 2000));
        }
        $pages = array_values(array_unique(array_filter(array_column($g['latest'], 'path'))));
        if (count($pages) > 1) {
            $md[] = '';
            $md[] = '**Pages it happened on lately:** ' . implode(', ', array_map(fn($p) => '`' . $p . '`', $pages));
        }
        foreach ($g['latest'] as $k => $ev) {
            $md[] = '';
            $md[] = '**' . ($k === 0 ? 'Latest occurrence' : 'Earlier') . ', ' . $ev['ref'] . ':** ' . $ev['created_at']
                  . ' on ' . trim(($ev['method'] && $ev['method'] !== 'CLI' ? $ev['method'] . ' ' : '') . ($ev['path'] ?: '?'))
                  . ' (' . $ev['source'] . ', release ' . ($ev['release_id'] ?: '?') . ($ev['duration_ms'] ? ', ' . number_format($ev['duration_ms'] / 1000, 1) . ' s' : '') . ')';
            if ($ev['user_agent']) $md[] = '- Browser: ' . $one_line($ev['user_agent']);
            if ($ev['message'] && $ev['message'] !== $g['message'] && $ev['message'] !== $g['title']) {
                $md[] = '';
                $md[] = $fence(mb_substr($ev['message'], 0, 1000));
            }
            // The full stack trace for the latest; earlier ones only if it differs.
            if ($ev['trace'] && ($k === 0 || $ev['trace'] !== ($g['latest'][0]['trace'] ?? null))) {
                $md[] = '';
                $md[] = $fence(mb_substr($ev['trace'], 0, $k === 0 ? 4000 : 1500));
            }
        }
        if ($g['notes']) {
            $md[] = '';
            $md[] = '**What the team noted:**';
            foreach ($g['notes'] as $note) {
                $md[] = '- ' . $one_line(mb_substr($note, 0, 1000));
            }
        }
    }
    $md[] = '';
    return implode("\n", $md);
}
