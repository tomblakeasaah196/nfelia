<?php
/**
 * Errors, slow pages and failed jobs, written down where the Nfelia team
 * sees them (Ops → Errors) instead of only in the server's log.
 *
 *   errors_install('web')                           once, as early as possible
 *   errors_report_throwable($e, ['job' => 'x'])      a caught error worth knowing
 *   errors_report('job', 'warning', 'Paystack webhook refused: …')
 *
 * Each one is grouped with the same message from the same place
 * (error_groups) and kept with who, where and when (error_events), under a
 * short code the person it happened to can quote ("E7KQ2M9X"). The first
 * time a problem appears, when one marked fixed comes back, or when one
 * suddenly spikes, the Ops team is told (app/ops/alerts.php).
 *
 * Written on a database connection of its own: an error inside a
 * transaction that is then rolled back is still recorded. Never throws. If
 * the database is the problem, the server log still gets the line, and the
 * cron reads the log into Ops later (app/ops/monitor.php). Lines written
 * here carry their code ("Nfelia error [E7KQ2M9X]: …"), so that reading
 * skips them.
 *
 * Nothing private is kept: emails, phone numbers and long tokens are masked
 * in messages, tokens are taken out of page addresses, query strings keep
 * only their names, and stack traces never hold arguments.
 */

const ERRORS_SLOW_MS     = 3000;   // a page slower than this is written down
const ERRORS_PER_REQUEST = 25;     // a page that goes wrong in a loop stops here
const ERRORS_KEEP_EVENTS = 50;     // the latest occurrences kept per problem
const ERRORS_REF_CHARS   = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O or 1/I

/** Where errors on this request come from, and who it is for. */
function errors_install(string $source): void
{
    $GLOBALS['nf_errors'] = ['source' => $source, 'started' => (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))];
    if (PHP_SAPI === 'cli') {
        // The cron's own error_log() lines go where the web's do, so the
        // log reader finds them (the cron's output goes nowhere). The host's
        // CLI setting is a bare "error_log", a file in whatever folder the
        // cron starts in: the home folder, which every site's crons on the
        // account share. So anything not already inside Nfelia is replaced.
        @ini_set('log_errors', '1');
        $ini = (string)ini_get('error_log');
        $ours = $ini !== '' && str_starts_with($ini, '/') && defined('BASE_PATH') && str_starts_with($ini, rtrim(BASE_PATH, '/') . '/');
        if (!$ours && defined('LOG_PATH') && (is_dir(LOG_PATH) || @mkdir(LOG_PATH, 0755, true))) {
            @ini_set('error_log', LOG_PATH . '/php_errors.log');
        }
        set_exception_handler(function (Throwable $e) {
            $ref = errors_report_throwable($e, ['level' => 'fatal']);
            fwrite(STDERR, '[' . ($ref ?? 'not recorded') . '] ' . get_class($e) . ': ' . $e->getMessage() . "\n");
            exit(255);
        });
    }
    set_error_handler('errors_on_php_error');
    register_shutdown_function('errors_on_shutdown');
}

/** More about this request, as it becomes known: business_id, user_id, source. */
function errors_context_set(array $context): void
{
    $GLOBALS['nf_errors'] = array_merge($GLOBALS['nf_errors'] ?? [], $context);
}

/** A warning, notice or deprecation: written down, and PHP's own log line replaced by one with its code. */
function errors_on_php_error(int $no, string $message, string $file = '', int $line = 0): bool
{
    if (!(error_reporting() & $no) || $no === E_USER_ERROR) {
        return false;   // silenced with @, or fatal: PHP handles it (and the shutdown below records it)
    }
    $level = match ($no) {
        E_NOTICE, E_USER_NOTICE           => 'notice',
        E_DEPRECATED, E_USER_DEPRECATED   => 'deprecated',
        default                           => 'warning',
    };
    $ref = errors_capture([
        'kind' => 'php', 'level' => $level, 'message' => $message, 'file' => $file, 'line' => $line,
        'trace' => errors_trace(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1)),
    ]);
    return $ref !== null;   // not recorded: PHP logs it as it always did
}

/** A fatal error (memory, time, a missing class), and slow pages. */
function errors_on_shutdown(): void
{
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        $GLOBALS['nf_fatal'] = $e;   // for index.php's error page: recording it may replace error_get_last()
        // Out of memory: the same limit applies now, so give recording it
        // (and the error page after) some room.
        if (str_contains($e['message'], 'Allowed memory size')) {
            @ini_set('memory_limit', (string)(memory_get_usage() + 32 * 1048576));
        }
        $GLOBALS['nf_error_ref'] = errors_capture(['kind' => 'php', 'level' => 'fatal', 'message' => $e['message'], 'file' => $e['file'], 'line' => $e['line']]);
    }
    $ctx = $GLOBALS['nf_errors'] ?? [];
    if (PHP_SAPI !== 'cli' && empty($ctx['not_slow']) && isset($ctx['started'])) {
        $ms = (int)round((microtime(true) - $ctx['started']) * 1000);
        // Time spent waiting on another company's service (the map search)
        // is not Nfelia's page being slow: only our own share counts.
        $waited = (int)($ctx['waited_ms'] ?? 0);
        if ($ms - $waited >= ERRORS_SLOW_MS) {
            $req = errors_request();
            errors_capture(['kind' => 'slow', 'level' => 'slow', 'duration_ms' => $ms,
                            'message' => 'Took ' . number_format($ms / 1000, 1) . ' s: ' . $req['method'] . ' /' . $req['route']]);
        }
    }
}

/** This request waited this long on an outside service (errors_on_shutdown leaves it out of "slow"). */
function errors_waited(float $seconds): void
{
    if ($seconds > 0) {
        $GLOBALS['nf_errors']['waited_ms'] = (int)($GLOBALS['nf_errors']['waited_ms'] ?? 0) + (int)round($seconds * 1000);
    }
}

/** An exception: recorded with its stack trace. Returns the error code. */
function errors_report_throwable(Throwable $e, array $extra = []): ?string
{
    $message = get_class($e) === 'Exception' || get_class($e) === 'ErrorException' ? $e->getMessage() : get_class($e) . ': ' . $e->getMessage();
    if (!empty($extra['job'])) {
        $message = $extra['job'] . ': ' . $message;
    }
    return errors_capture([
        'kind'    => $extra['kind'] ?? (!empty($extra['job']) ? 'job' : 'php'),
        'level'   => $extra['level'] ?? 'error',
        'message' => $message,
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => errors_trace($e->getTrace()) . ($e->getPrevious() ? "\nCaused by " . get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage() : ''),
    ] + $extra);
}

/** Anything else worth the team knowing: a refused webhook, a job that failed without an exception. */
function errors_report(string $kind, string $level, string $message, array $extra = []): ?string
{
    $at = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
    return errors_capture(['kind' => $kind, 'level' => $level, 'message' => $message,
                           'file' => $at['file'] ?? null, 'line' => $at['line'] ?? null] + $extra);
}

/**
 * Write one down. Returns its code, or null when it could not be recorded
 * (the server log has it then). The same problem twice in one request is
 * recorded once.
 */
function errors_capture(array $e): ?string
{
    static $busy = false, $count = 0, $seen = [];
    $bulk = !empty($e['bulk']);   // read from the server log (app/ops/monitor.php): every line counts
    if ($busy || (!$bulk && $count >= ERRORS_PER_REQUEST)) {
        return null;
    }
    $busy = true;
    try {
        $bulk || $count++;
        $req     = errors_request();
        $ctx     = $GLOBALS['nf_errors'] ?? [];
        $kind    = (string)$e['kind'];
        $level   = (string)$e['level'];
        $message = errors_mask(trim((string)($e['message'] ?? '')));
        $file    = errors_short_path($e['file'] ?? null);
        $line    = isset($e['line']) ? (int)$e['line'] : null;
        $route   = $kind === 'slow' ? $req['method'] . ' ' . $req['route'] : ($e['route'] ?? null);
        $title   = mb_substr(errors_normalise(strtok($message, "\n") ?: '(no message)'), 0, 255);
        $fp      = sha1(implode('|', [$kind, $level, $file, $kind === 'slow' ? '' : $line, $kind === 'slow' ? $route : $title]));
        if (!$bulk && isset($seen[$fp])) {
            return $seen[$fp];
        }

        $db      = errors_db();
        $release = $e['release'] ?? app_release();
        $at      = $e['at'] ?? (string)$db->query("SELECT NOW()")->fetchColumn();   // Lagos time, as the database keeps it
        $stmt = $db->prepare("
            INSERT INTO error_groups (fingerprint, kind, level, title, message, file, line, route, first_seen_at, last_seen_at, first_release, last_release, events)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), events = events + 1, last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at)),
                                    message = VALUES(message), last_release = VALUES(last_release)
        ");
        $stmt->execute([$fp, $kind, $level, $kind === 'slow' ? 'Slow page: ' . $route : $title, mb_substr($message, 0, 20000), $file, $line, $route, $at, $at, $release, $release]);
        $is_new   = $stmt->rowCount() === 1;
        $group_id = (int)$db->lastInsertId();

        $came_back = false;
        if (!$is_new) {
            // Marked fixed, and it happened again after that: open again.
            $back = $db->prepare("UPDATE error_groups SET status = 'open', regressed_at = NOW(), regressions = regressions + 1, status_changed_at = NOW(), status_by = NULL
                                  WHERE id = ? AND status = 'fixed' AND status_changed_at < ?");
            $back->execute([$group_id, $at]);
            $came_back = $back->rowCount() === 1;
        }

        $ref = errors_new_ref();
        $db->prepare("
            INSERT INTO error_events (group_id, ref, source, business_id, user_id, method, path, duration_ms, memory_mb, message, trace, user_agent, release_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $group_id, $ref, mb_substr((string)($e['source'] ?? $req['source']), 0, 10),
            $e['business_id'] ?? $ctx['business_id'] ?? $req['business_id'],
            $e['user_id'] ?? $ctx['user_id'] ?? $req['user_id'],
            $e['method'] ?? $req['method'], $e['path'] ?? $req['path'],
            $e['duration_ms'] ?? null, (int)round(memory_get_peak_usage() / 1048576),
            mb_substr($message, 0, 20000), isset($e['trace']) ? mb_substr(errors_mask((string)$e['trace']), 0, 60000) : null,
            mb_substr((string)($e['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, $release, $at,
        ]);
        $seen[$fp] = $ref;

        $group = $db->query("SELECT * FROM error_groups WHERE id = " . $group_id)->fetch();
        if ((int)$group['events'] % 10 === 0) {
            errors_prune($db, $group_id);
        }
        require_once BASE_PATH . '/app/ops/alerts.php';
        // Old lines found in the server log are listed, not announced.
        $recent = !$bulk || (new DateTimeImmutable($at, new DateTimeZone(APP_TIMEZONE)))->getTimestamp() > time() - 3600;
        if (!$recent) {
            // nothing to tell
        } elseif ($is_new || $came_back) {
            ops_error_alert($db, $group, $came_back ? 'back' : 'new', $ref);
        } elseif ((int)$group['events'] % 10 === 0) {
            errors_spike_check($db, $group);
        }

        if (!$bulk) {
            @error_log('Nfelia ' . $level . ' [' . $ref . ']: ' . (strtok($message, "\n") ?: '') . ($file ? ' in ' . $file . ($line ? ':' . $line : '') : ''));
        }
        return $ref;
    } catch (Throwable $x) {
        @error_log('Nfelia errors: could not record an error (' . $x->getMessage() . '): ' . mb_substr((string)($e['message'] ?? ''), 0, 500));
        return null;
    } finally {
        $busy = false;
    }
}

/** The latest ERRORS_KEEP_EVENTS occurrences of a problem stay; older ones go. */
function errors_prune(PDO $db, int $group_id): void
{
    $db->prepare("
        DELETE FROM error_events WHERE group_id = ? AND id < (
            SELECT id FROM (SELECT id FROM error_events WHERE group_id = ? ORDER BY id DESC LIMIT 1 OFFSET " . (ERRORS_KEEP_EVENTS - 1) . ") AS keep
        )
    ")->execute([$group_id, $group_id]);
}

/**
 * Suddenly far more often than usual: at least 20 in the last hour, and five
 * times its hourly average over the day before. Told at most every 6 hours.
 */
function errors_spike_check(PDO $db, array $group): void
{
    if ($group['status'] === 'ignored' || ($group['spiked_at'] && strtotime($group['spiked_at']) > strtotime((string)$db->query("SELECT NOW() - INTERVAL 6 HOUR")->fetchColumn()))) {
        return;
    }
    $stmt = $db->prepare("SELECT SUM(created_at > NOW() - INTERVAL 1 HOUR) AS hour, SUM(created_at <= NOW() - INTERVAL 1 HOUR) AS before_ FROM error_events
                          WHERE group_id = ? AND created_at > NOW() - INTERVAL 25 HOUR");
    $stmt->execute([$group['id']]);
    $n = $stmt->fetch();
    $hour = (int)$n['hour'];
    $usual = (int)$n['before_'] / 24;
    if ($hour >= 20 && $hour >= 5 * max(1, $usual)) {
        $db->prepare("UPDATE error_groups SET spiked_at = NOW() WHERE id = ?")->execute([$group['id']]);
        $db->prepare("INSERT INTO error_notes (group_id, staff_id, body) VALUES (?, NULL, ?)")
           ->execute([$group['id'], "Spiking: {$hour} times in the last hour, against about " . (int)round($usual) . ' an hour before.']);
        ops_error_alert($db, $group, 'spike', null, $hour);
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE REQUEST, AND KEEPING IT PRIVATE
 * ═══════════════════════════════════════════════════════════════════ */

/** What page this is, who for, with tokens taken out. */
function errors_request(): array
{
    $ctx    = $GLOBALS['nf_errors'] ?? [];
    $cli    = PHP_SAPI === 'cli';
    $method = $cli ? 'CLI' : strtoupper(substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 8));
    if ($cli) {
        $path = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['argv'][0] ?? 'cli'));
        return ['source' => $ctx['source'] ?? 'cli', 'method' => $method, 'path' => $path, 'route' => $path, 'business_id' => null, 'user_id' => null];
    }
    $raw   = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $plain = trim((string)parse_url($raw, PHP_URL_PATH), '/');
    [$path, $route] = errors_clean_path($raw);
    $host  = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $source = $ctx['source'] ?? 'web';
    if ($source === 'web' && str_starts_with($plain, 'api/')) $source = 'api';
    $session = session_status() === PHP_SESSION_ACTIVE ? $_SESSION : [];
    return [
        'source'      => $source,
        'method'      => $method,
        'path'        => mb_substr(($host !== '' && $host !== strtolower((string)parse_url(APP_URL, PHP_URL_HOST)) ? $host . ' ' : '') . '/' . $path, 0, 255),
        'route'       => mb_substr($route === '' ? '' : $route, 0, 150),
        'business_id' => isset($session['business']['id']) ? (int)$session['business']['id'] : null,
        'user_id'     => isset($session['user']['id']) ? (int)$session['user']['id'] : null,
    ];
}

/**
 * A page address with nothing private in it: tokens in the path become
 * :token, the query keeps its names only. Returns [path, route], where the
 * route also has ids as :id ("orders/view/:id"), for grouping slow pages.
 */
function errors_clean_path(string $uri): array
{
    $plain = trim((string)parse_url($uri, PHP_URL_PATH), '/');
    $parts = array_map(fn($p) => preg_match('/^[A-Za-z0-9_-]{20,}$/', $p) ? ':token' : (mb_strlen($p) > 60 ? ':long' : $p), explode('/', $plain));
    $path  = implode('/', $parts);
    parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
    unset($query['url']);
    if ($query) {
        $path .= '?' . implode('&', array_map(fn($k) => $k . '=…', array_keys($query)));
    }
    return [$path, implode('/', array_map(fn($p) => ctype_digit($p) ? ':id' : $p, $parts))];
}

/** Emails, phone numbers and long tokens out of anything kept. */
function errors_mask(string $s): string
{
    $s = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '<email>', $s) ?? $s;
    $s = preg_replace('/(?<![\w.])(?:\+?234|0)[789][01]\d{8}(?!\d)/', '<phone>', $s) ?? $s;
    $s = preg_replace('/\b(?=[A-Za-z0-9_-]*\d)(?=[A-Za-z0-9_-]*[A-Za-z])[A-Za-z0-9_-]{32,}\b/', '<token>', $s) ?? $s;
    return $s;
}

/** The shape of a message, so "user 42" and "user 8341" are one problem. */
function errors_normalise(string $message): string
{
    $s = preg_replace('/\b[0-9a-f]{12,}\b/i', '?', $message) ?? $message;
    $s = preg_replace('/(?<![A-Za-z_])\d+(?:\.\d+)?/', '#', $s) ?? $s;
    $s = preg_replace('/\s+VALUES\s*\(.*$/is', ' VALUES (…)', $s) ?? $s;
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

/** A file inside the app as app/…, never the server's full path. */
function errors_short_path(?string $file): ?string
{
    if ($file === null || $file === '') {
        return null;
    }
    $base = rtrim(BASE_PATH, '/') . '/';
    return mb_substr(str_starts_with($file, $base) ? substr($file, strlen($base)) : $file, 0, 255);
}

/** A stack trace with no arguments and short paths. */
function errors_trace(array $frames): string
{
    $out = [];
    foreach (array_slice($frames, 0, 30) as $i => $f) {
        $where = isset($f['file']) ? errors_short_path($f['file']) . ':' . ($f['line'] ?? '?') : '[internal]';
        $call  = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
        $out[] = "#{$i} {$where} {$call}";
    }
    return implode("\n", $out);
}

/** An eight-letter code, easy to read out over the phone. */
function errors_new_ref(): string
{
    $ref = 'E';
    for ($i = 0; $i < 7; $i++) {
        $ref .= ERRORS_REF_CHARS[random_int(0, strlen(ERRORS_REF_CHARS) - 1)];
    }
    return $ref;
}

/**
 * A connection only for writing errors down, so they are kept even when the
 * page's own transaction is rolled back.
 */
function errors_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . ($_ENV['DB_NAME'] ?? '') . ';charset=utf8mb4',
        $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]
    );
    try {
        $pdo->exec("SET time_zone = '+01:00'");
    } catch (PDOException $e) {
        // The time zone is best-effort, as in get_db().
    }
    return $pdo;
}

/**
 * The release this server runs: the commit bin/deploy.sh wrote to
 * storage/release ("a1b2c3d 2026-10-20T09:00:00+01:00 …"), or "dev".
 */
function app_release(): string
{
    static $release = null;
    if ($release === null) {
        $file = BASE_PATH . '/storage/release';
        $line = is_file($file) && is_readable($file) ? file_get_contents($file, false, null, 0, 300) : false;
        $release = $line !== false && preg_match('/^([0-9a-f]{6,40})/', trim($line), $m) ? substr($m[1], 0, 12) : 'dev';
    }
    return $release;
}

/** The script that reports browser errors (assets/js/errors.js), for the top of every page's <head>. */
function errors_script_tag(): string
{
    $file = BASE_PATH . '/assets/js/errors.js';
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return '<script src="/assets/js/errors.js' . $v . '" data-endpoint="' . htmlspecialchars(APP_URL . '/api/errors', ENT_QUOTES) . '"></script>';
}

/* ═══════════════════════════════════════════════════════════════════
 *  HEARTBEATS: WHEN EACH BACKGROUND JOB LAST RAN
 * ═══════════════════════════════════════════════════════════════════ */

function heartbeat_start(string $name): void
{
    try {
        errors_db()->prepare("INSERT INTO ops_heartbeats (name, started_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE started_at = NOW()")
                   ->execute([$name]);
    } catch (Throwable $e) {
        @error_log('Nfelia heartbeat ' . $name . ': ' . $e->getMessage());
    }
}

/** A run finished: how it went, and a line about it ("reminders=3 errors=0"). */
function heartbeat_finish(string $name, bool $ok, string $note = '', ?int $duration_ms = null): void
{
    try {
        errors_db()->prepare("
            INSERT INTO ops_heartbeats (name, started_at, finished_at, ok, duration_ms, note, runs, failures, last_failure_at)
            VALUES (?, NOW(), NOW(), ?, ?, ?, 1, ?, IF(? = 0, NOW(), NULL))
            ON DUPLICATE KEY UPDATE finished_at = NOW(), ok = VALUES(ok), duration_ms = VALUES(duration_ms), note = VALUES(note),
                                    runs = runs + 1, failures = failures + VALUES(failures),
                                    last_failure_at = IF(VALUES(ok) = 0, NOW(), last_failure_at)
        ")->execute([$name, (int)$ok, $duration_ms, mb_substr($note, 0, 255), (int)!$ok, (int)$ok]);
    } catch (Throwable $e) {
        @error_log('Nfelia heartbeat ' . $name . ': ' . $e->getMessage());
    }
}
