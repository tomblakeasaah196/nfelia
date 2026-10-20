<?php
/**
 * The monitor's background work, run by cron/planner.php every 5 minutes,
 * and the health checks behind Ops → Health.
 *
 *   ops_monitor_run($db)   read the server log, check health, the 8am summary, the 3am clean-up
 *   ops_health($db)        every check: ['key', 'label', 'state' ok|warn|bad, 'detail', 'link']
 *   ops_watchdog()         from ordinary pages now and then: is the cron still running?
 *
 * The server log (storage/logs/php_errors.log, and cPanel's per-folder
 * error_log files) catches what app/helpers/errors.php cannot: errors from
 * before the app started (a parse error), and every error_log() line the
 * code writes when something it caught went wrong. Each file is read from
 * where the last run stopped.
 */

require_once __DIR__ . '/alerts.php';
require_once BASE_PATH . '/app/helpers/errors.php';

const OPS_LOG_CHUNK      = 2 * 1024 * 1024;   // read at most this much of a log per run
const OPS_LOG_FIRST_READ = 256 * 1024;        // a log seen for the first time: only its end
const OPS_CRON_LATE_MIN  = 20;                // no cron run for this long: something is wrong

/** Everything the cron does for the monitor. Returns counts for its summary line. */
function ops_monitor_run(PDO $db, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $done = ['log_lines' => 0, 'health_alerts' => 0, 'digests' => 0];

    foreach (ops_log_sources() as $path) {
        $done['log_lines'] += ops_log_import($db, $path);
    }
    $done['health_alerts'] = ops_health_alerts($db, $now);
    if ((int)$now->format('G') >= 8 && (int)$now->format('G') < 11) {
        $done['digests'] = ops_digest($db, $now);
    }
    if ((int)$now->format('G') === 3) {
        ops_monitor_cleanup($db);
    }
    return $done;
}

/* ═══════════════════════════════════════════════════════════════════
 *  READING THE SERVER LOG
 * ═══════════════════════════════════════════════════════════════════ */

/** Every log file worth reading that exists and can be read. */
function ops_log_sources(): array
{
    $paths = [LOG_PATH . '/php_errors.log', BASE_PATH . '/error_log', BASE_PATH . '/cron/error_log', BASE_PATH . '/api/ajax/error_log',
              BASE_PATH . '/webhook/error_log'];
    $ini = (string)ini_get('error_log');
    if ($ini !== '' && $ini !== 'syslog') {
        $paths[] = $ini;
    }
    $out = [];
    foreach ($paths as $p) {
        $real = @realpath($p) ?: $p;
        if (!isset($out[$real]) && is_file($real) && is_readable($real)) {
            $out[$real] = $real;
        }
    }
    return array_values($out);
}

function ops_setting(PDO $db, string $name): ?string
{
    $stmt = $db->prepare("SELECT value FROM ops_settings WHERE name = ?");
    $stmt->execute([$name]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string)$v;
}

function ops_setting_set(PDO $db, string $name, string $value): void
{
    $db->prepare("INSERT INTO ops_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$name, $value]);
}

/**
 * Read what was added to a log since last time and record each entry. A
 * log that was emptied or replaced is read from its start. Returns how many
 * entries were recorded.
 */
function ops_log_import(PDO $db, string $path, int $max = OPS_LOG_CHUNK): int
{
    $key   = 'log:' . sha1($path);
    $state = json_decode((string)ops_setting($db, $key), true) ?: null;
    clearstatcache(true, $path);
    $size  = (int)@filesize($path);
    $inode = (int)@fileinode($path);
    if ($state === null) {
        $offset = max(0, $size - OPS_LOG_FIRST_READ);
    } elseif ((int)$state['inode'] !== $inode || $size < (int)$state['offset']) {
        $offset = 0;   // rotated or emptied
    } else {
        $offset = (int)$state['offset'];
    }
    if ($size <= $offset) {
        ops_setting_set($db, $key, json_encode(['offset' => $size, 'inode' => $inode]));
        return 0;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return 0;
    }
    fseek($fh, $offset);
    $blob = (string)fread($fh, $max);
    fclose($fh);
    // Only whole lines; the rest is read next time.
    $end = strrpos($blob, "\n");
    if ($end === false) {
        if (strlen($blob) < $max) return 0;
        $end = strlen($blob) - 1;
    }
    $blob = substr($blob, 0, $end + 1);
    if ($state === null && $offset > 0 && ($nl = strpos($blob, "\n")) !== false) {
        $blob = substr($blob, $nl + 1);   // started mid-line
        $offset += $nl + 1;
    }

    $count = 0;
    foreach (ops_log_parse($blob) as $entry) {
        if (ops_log_record($db, $entry, basename($path))) {
            $count++;
        }
    }
    ops_setting_set($db, $key, json_encode(['offset' => $offset + strlen($blob), 'inode' => $inode]));
    return $count;
}

/**
 * Split log text into entries: "[timestamp] message" lines, with the lines
 * that follow (a stack trace) belonging to the one before.
 */
function ops_log_parse(string $blob): array
{
    $entries = [];
    $current = null;
    foreach (preg_split("/\r?\n/", $blob) ?: [] as $line) {
        if ($line === '') continue;
        if (preg_match('/^\[([^\]]{6,64})\]\s?(.*)$/s', $line, $m)) {
            if ($current) $entries[] = $current;
            $current = ['ts' => $m[1], 'text' => $m[2]];
        } elseif ($current) {
            $current['text'] .= "\n" . $line;
        } else {
            $entries[] = ['ts' => '', 'text' => $line];
        }
    }
    if ($current) $entries[] = $current;
    return $entries;
}

/** One log entry into the monitor. False when it was skipped. */
function ops_log_record(PDO $db, array $entry, string $file_name): bool
{
    $text = trim($entry['text']);
    // Already recorded when it happened (app/helpers/errors.php writes its code).
    if ($text === '' || preg_match('/^(PHP\s+)?Nfelia [a-z]+ \[E[A-Z2-9]{7}\]/', $text)) {
        return false;
    }
    $at = ops_log_time($entry['ts']);

    $level = 'error';
    $kind  = 'log';
    if (preg_match('/^(?:PHP\s+)?(Fatal error|Parse error|Recoverable fatal error|Catchable fatal error|Compile error|Core error|Warning|Notice|Deprecated|Strict Standards|User (?:warning|notice|error|deprecated))\s*:\s*(.*)$/is', $text, $m)) {
        $kind  = 'php';
        $name  = strtolower($m[1]);
        $level = str_contains($name, 'fatal') || str_contains($name, 'parse') || str_contains($name, 'compile') || str_contains($name, 'core error') || $name === 'user error' ? 'fatal'
               : (str_contains($name, 'deprecated') ? 'deprecated' : (str_contains($name, 'notice') || str_contains($name, 'strict') ? 'notice' : 'warning'));
        $text  = trim($m[2]);
    } elseif (!preg_match('/error|fail|exception|could not|couldn\'t|gave up|refus|denied|mismatch|timed? ?out|unable/i', $text)) {
        $level = 'warning';
    }

    // "in /path/file.php on line 12", or "in /path/file.php:12" (uncaught exceptions put it on a later line)
    $file = null;
    $line = null;
    if (preg_match_all('/\bin\s+(\/[^\s:]+?\.(?:php|phtml|inc))(?:\s+on\s+line\s+|:)(\d+)/i', $text, $mm, PREG_SET_ORDER)) {
        $last = end($mm);
        $file = $last[1];
        $line = (int)$last[2];
    }
    // The first line without its "in /file.php on line 12" (shown as file:line instead).
    [$first, $rest] = array_pad(explode("\n", $text, 2), 2, null);
    $first = trim(preg_replace('/\s+in\s+\/[^\s:]+?\.(?:php|phtml|inc)(?:\s+on\s+line\s+|:)\d+\s*$/i', '', $first) ?? $first);
    $text  = $first . ($rest !== null ? "\n" . $rest : '');

    // Recorded at the time already, from the same place and message? Then it is the same one.
    if ($kind === 'php') {
        $fp = sha1(implode('|', ['php', $level, errors_short_path($file), $line, mb_substr(errors_normalise(errors_mask($first)), 0, 255)]));
        $stmt = $db->prepare("SELECT 1 FROM error_events e JOIN error_groups g ON g.id = e.group_id
                              WHERE g.fingerprint = ? AND e.created_at BETWEEN ? - INTERVAL 10 SECOND AND ? + INTERVAL 10 SECOND LIMIT 1");
        $stmt->execute([$fp, $at, $at]);
        if ($stmt->fetchColumn()) {
            return false;
        }
    }

    return errors_capture([
        'bulk' => true, 'at' => $at, 'kind' => $kind, 'level' => $level, 'message' => $text,
        'file' => $file, 'line' => $line, 'source' => 'log', 'method' => null, 'path' => $file_name, 'release' => null,
        'user_agent' => '', 'business_id' => null, 'user_id' => null,
    ]) !== null;
}

/** A log line's time ("18-Oct-2026 14:00:00 UTC") as the database's Lagos time. */
function ops_log_time(string $ts): string
{
    $lagos = new DateTimeZone(APP_TIMEZONE);
    foreach (['d-M-Y H:i:s e', 'd-M-Y H:i:s T', 'd-M-Y H:i:s', 'D M d H:i:s.u Y', 'Y-m-d H:i:s'] as $format) {
        $d = DateTimeImmutable::createFromFormat($format, trim($ts), new DateTimeZone('UTC'));
        if ($d) {
            return $d->setTimezone($lagos)->format('Y-m-d H:i:s');
        }
    }
    return (new DateTimeImmutable('now', $lagos))->format('Y-m-d H:i:s');
}

/* ═══════════════════════════════════════════════════════════════════
 *  HEALTH
 * ═══════════════════════════════════════════════════════════════════ */

/** Minutes since a database time, or null. */
function ops_minutes_since(?string $db_time): ?int
{
    return $db_time ? (int)floor((time() - ops_ts($db_time)) / 60) : null;
}

/**
 * How the PHP check reads, split out so it can be checked without an extension
 * actually being absent. A break (bad) when PHP is too old or an extension the
 * app can't run without is missing; worth a look (warn) when only a cushioned
 * one is (fileinfo, intl — the app has fallbacks for both); otherwise ok.
 *
 * @param string[] $missing_required   must-have extensions not loaded
 * @param string[] $missing_cushioned  fileinfo/intl not loaded (app copes)
 * @return array{0: string, 1: string} [state, detail]
 */
function ops_php_health(string $version, string $memory_limit, array $missing_required, array $missing_cushioned): array
{
    $missing = array_merge($missing_required, $missing_cushioned);
    $state   = (version_compare($version, '8.3', '<') || $missing_required) ? 'bad' : ($missing_cushioned ? 'warn' : 'ok');
    $detail  = 'PHP ' . $version . ', memory limit ' . $memory_limit
             . ($missing ? '. Missing: ' . implode(', ', $missing) . ($missing_required ? '.' : ' (the app has fallbacks for these).') : '.');
    return [$state, $detail];
}

/**
 * Every check, worst first within its place. Each: key, group, label,
 * state (ok, warn, bad), detail, and link (a path in Ops, or null).
 */
function ops_health(PDO $db): array
{
    $checks = [];
    $add = function (string $key, string $group, string $label, string $state, string $detail, ?string $link = null) use (&$checks) {
        $checks[] = compact('key', 'group', 'label', 'state', 'detail', 'link');
    };
    $beats = [];
    foreach ($db->query("SELECT * FROM ops_heartbeats")->fetchAll() as $b) {
        $beats[$b['name']] = $b;
    }

    // ── Background jobs
    $cron = $beats['planner'] ?? null;
    $ago  = ops_minutes_since($cron['finished_at'] ?? null);
    if (!$cron || $ago === null) {
        $add('cron', 'Background jobs', 'The 5-minute cron', 'bad', 'It has never run. Reminders, billing, retries and this monitor depend on it.');
    } else {
        $state = $ago >= OPS_CRON_LATE_MIN ? 'bad' : ($ago >= 10 ? 'warn' : 'ok');
        $detail = 'Last ran ' . ops_ago($cron['finished_at']) . ' (' . number_format(((int)$cron['duration_ms']) / 1000, 1) . ' s)'
                . ((int)$cron['ok'] ? '' : ', with errors') . '.';
        if ($cron['started_at'] && $cron['finished_at'] && ops_ts($cron['started_at']) > ops_ts($cron['finished_at']) + 60) {
            $state = 'bad';
            $detail .= ' A run started ' . ops_ago($cron['started_at']) . ' and has not finished.';
        }
        $add('cron', 'Background jobs', 'The 5-minute cron', $state === 'ok' && !(int)$cron['ok'] ? 'warn' : $state, $detail, $state === 'ok' && (int)$cron['ok'] ? null : 'errors?kind=job');
    }
    $hook = $beats['paystack_webhook'] ?? null;
    $add('paystack', 'Background jobs', 'Paystack webhooks', $hook && $hook['last_failure_at'] && ops_minutes_since($hook['last_failure_at']) < 1440 ? 'warn' : 'ok',
         $hook ? 'Last received ' . ops_ago($hook['finished_at']) . '. ' . ((int)$hook['failures'] ? (int)$hook['failures'] . ' refused in all' . ($hook['last_failure_at'] ? ', the last ' . ops_ago($hook['last_failure_at']) : '') . '.' : 'None refused.')
               : 'None received yet.', 'errors?q=Paystack');

    // ── Phone notifications and emails
    $out = $db->query("
        SELECT channel,
               SUM(status = 'failed' AND created_at > NOW() - INTERVAL 1 DAY) AS failed,
               SUM(status = 'sent' AND created_at > NOW() - INTERVAL 1 DAY) AS sent,
               SUM(status IN ('pending', 'sending') AND created_at < NOW() - INTERVAL 30 MINUTE AND next_attempt_at < NOW() - INTERVAL 15 MINUTE) AS stuck,
               SUM(status = 'pending' AND attempts > 0) AS retrying
        FROM notify_outbox WHERE created_at > NOW() - INTERVAL 2 DAY GROUP BY channel
    ")->fetchAll(PDO::FETCH_UNIQUE);
    foreach (['email' => 'Emails', 'push' => 'Phone notifications'] as $channel => $label) {
        $r = $out[$channel] ?? ['failed' => 0, 'sent' => 0, 'stuck' => 0, 'retrying' => 0];
        $failed = (int)$r['failed'];
        $sent   = (int)$r['sent'];
        $state  = (int)$r['stuck'] > 0 || ($failed >= 5 && $failed > $sent) ? 'bad' : ($failed > 0 ? 'warn' : 'ok');
        $detail = $sent . ' sent in the last day' . ($failed ? ', ' . $failed . ' failed' : '') . ((int)$r['retrying'] ? ', ' . (int)$r['retrying'] . ' being retried' : '')
                . ((int)$r['stuck'] ? ', ' . (int)$r['stuck'] . ' stuck waiting (is the cron running?)' : '') . '.';
        $add('outbox_' . $channel, 'Notifications', $label, $state, $detail, 'health/sends?channel=' . $channel);
    }
    $add('push_keys', 'Notifications', 'Phone notification keys', push_enabled() ? 'ok' : 'bad',
         push_enabled() ? 'Set (VAPID).' : 'VAPID keys are missing from .env: no phone notifications can be sent.');
    $add('mail', 'Notifications', 'Mail server', defined('MAIL_HOST') && MAIL_HOST !== '' ? 'ok' : 'bad',
         defined('MAIL_HOST') && MAIL_HOST !== '' ? 'Sending through ' . MAIL_HOST . (defined('MAIL_PORT') ? ':' . MAIL_PORT : '') . '.' : 'MAIL_HOST is missing from .env.');

    // ── Errors
    $e = $db->query("
        SELECT SUM(first_seen_at > NOW() - INTERVAL 1 DAY AND kind <> 'slow') AS new_day,
               SUM(status = 'open' AND level IN ('fatal', 'error') AND kind <> 'slow' AND last_seen_at > NOW() - INTERVAL 1 DAY) AS open_errors,
               SUM(kind = 'slow' AND last_seen_at > NOW() - INTERVAL 1 DAY) AS slow,
               SUM(regressed_at > NOW() - INTERVAL 1 DAY) AS back
        FROM error_groups
    ")->fetch();
    $add('errors', 'Errors', 'Errors in the last day', (int)$e['open_errors'] > 0 ? 'warn' : 'ok',
         (int)$e['open_errors'] . ' open, ' . (int)$e['new_day'] . ' new, ' . (int)$e['back'] . ' came back.', 'errors');
    $add('slow', 'Errors', 'Slow pages', (int)$e['slow'] > 0 ? 'warn' : 'ok',
         (int)$e['slow'] . ' page' . ((int)$e['slow'] === 1 ? '' : 's') . ' took over ' . (ERRORS_SLOW_MS / 1000) . ' seconds in the last day.', 'errors?kind=slow');

    // ── The server log
    $sources = ops_log_sources();
    if (!$sources) {
        $add('log', 'Server', 'Server error log', 'warn', 'No log file found at ' . errors_short_path(LOG_PATH . '/php_errors.log') . '. Errors are still recorded here, but crashes before the app starts are not.');
    }
    foreach ($sources as $path) {
        $size  = (int)@filesize($path);
        $mtime = (int)@filemtime($path);
        $add('log:' . basename($path), 'Server', 'Log ' . errors_short_path($path), !is_writable($path) ? 'bad' : ($size > 200 * 1048576 ? 'warn' : 'ok'),
             number_format($size / 1048576, 1) . ' MB, last written ' . ($mtime ? ops_ago(date('Y-m-d H:i:s', $mtime + ops_tz_offset())) : 'never') . (!is_writable($path) ? '. PHP cannot write to it.' : '.'));
    }

    // ── Space and folders
    $free  = @disk_free_space(BASE_PATH);
    $total = @disk_total_space(BASE_PATH);
    if ($free !== false && $total) {
        $share = $free / $total;
        $add('disk', 'Server', 'Disk space', $share < 0.03 ? 'bad' : ($share < 0.10 ? 'warn' : 'ok'),
             number_format($free / 1073741824, 1) . ' GB free of ' . number_format($total / 1073741824, 1) . ' GB (' . round($share * 100) . '%).');
    }
    foreach (['storage/sessions' => 'Sign-in sessions', 'storage/logs' => 'Logs', 'uploads' => 'Uploads (logos, photos, receipts)'] as $dir => $what) {
        $full = BASE_PATH . '/' . $dir;
        $ok = is_dir($full) && is_writable($full);
        $add('dir:' . $dir, 'Server', $what, $ok ? 'ok' : 'bad', $dir . ($ok ? ' can be written to.' : (is_dir($full) ? ' cannot be written to.' : ' does not exist.')));
    }

    // ── PHP and the database. fileinfo and intl are cushioned: the app stands
    //    in for fileinfo (app/helpers/functions.php) and guards every intl call,
    //    so either being missing is worth a look, not a break. The rest, and an
    //    old PHP, break the app and stay red (and so still alert the team).
    $need_ext = array_values(array_filter(['pdo_mysql', 'curl', 'openssl', 'mbstring', 'gd'], fn($x) => !extension_loaded($x)));
    $soft_ext = array_values(array_filter(['intl', 'fileinfo'], fn($x) => !extension_loaded($x)));
    [$php_state, $php_detail] = ops_php_health(PHP_VERSION, (string)ini_get('memory_limit'), $need_ext, $soft_ext);
    $add('php', 'Server', 'PHP', $php_state, $php_detail);
    $add('db', 'Server', 'Database', 'ok', (string)$db->query("SELECT VERSION()")->fetchColumn() . ', clock ' . $db->query("SELECT DATE_FORMAT(NOW(), '%H:%i')")->fetchColumn() . ' Lagos time.');
    $release_file = BASE_PATH . '/storage/release';
    // Backups: bin/backup.sh writes one line per run to <BACKUP_DIR>/backup.log,
    // "<time> OK <stamp> db=... files=... offsite: ...". Older than 36 hours,
    // or a FAILED line last, is worth a look; no log at all is a problem.
    $backup_dir = rtrim((string)($_ENV['BACKUP_DIR'] ?? (dirname(BASE_PATH, 2) . '/backups/nfelia')), '/');
    $backup_log = $backup_dir . '/backup.log';
    $last = '';
    if (is_file($backup_log) && ($lines = @file($backup_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        $last = (string)end($lines);
    }
    if ($last === '') {
        $add('backup', 'Server', 'Backups', 'bad', 'No backup has been recorded at ' . $backup_log . '. Set up bin/backup.sh (docs/ops/RUNBOOK.md, Backups).');
    } else {
        $when = strtotime(substr($last, 0, 25)) ?: 0;
        $age_h = $when ? (time() - $when) / 3600 : 999;
        $failed = str_contains($last, 'FAILED');
        $offsite = str_contains($last, 'copied to');
        $add('backup', 'Server', 'Backups', $failed || $age_h > 72 ? 'bad' : ($age_h > 36 || !$offsite ? 'warn' : 'ok'),
             ($failed ? 'The last run failed: ' : 'Last backup ' . ($age_h < 1 ? 'under an hour' : round($age_h) . ' hours') . ' ago. ')
             . ($failed ? trim(substr($last, 26)) : ($offsite ? 'Copied off the server.' : 'Kept on this server only: no off-site copy is set up.')));
    }
    $add('release', 'Server', 'Release', 'ok', app_release() === 'dev' ? 'Not deployed by bin/deploy.sh (no storage/release).'
         : app_release() . ', deployed ' . ops_ago(date('Y-m-d H:i:s', (int)@filemtime($release_file) + ops_tz_offset())) . '.');

    return $checks;
}

/** Seconds Lagos is ahead of PHP's own clock zone, to show file times as the database does. */
function ops_tz_offset(): int
{
    return (new DateTimeZone(APP_TIMEZONE))->getOffset(new DateTimeImmutable()) - (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTimeImmutable());
}

/** The worst state of a list of checks. */
function ops_health_state(array $checks): string
{
    $states = array_column($checks, 'state');
    return in_array('bad', $states, true) ? 'bad' : (in_array('warn', $states, true) ? 'warn' : 'ok');
}

/**
 * Tell the team about what is broken right now (not what only looks odd):
 * at most once every 6 hours for each.
 */
function ops_health_alerts(PDO $db, DateTimeImmutable $now): int
{
    $told = 0;
    $window = $now->format('Y-m-d') . '-' . intdiv((int)$now->format('G'), 6);
    foreach (ops_health($db) as $c) {
        if ($c['state'] !== 'bad' || $c['key'] === 'cron') {
            continue;   // the cron cannot report its own absence: ops_watchdog() does
        }
        $told += ops_alert($db, 'health', 'Needs attention: ' . $c['label'], $c['detail'], 'health', 'health:' . $c['key'] . ':' . $window) ? 1 : 0;
    }
    return $told;
}

/**
 * Is the cron still running? Asked by ordinary pages now and then (index.php),
 * since a stopped cron cannot say so itself. Told at most every 2 hours.
 */
function ops_watchdog(): void
{
    try {
        $db = errors_db();
        $last = $db->query("SELECT finished_at FROM ops_heartbeats WHERE name = 'planner'")->fetchColumn();
        $ago  = ops_minutes_since($last ?: null);
        if ($last && $ago !== null && $ago >= OPS_CRON_LATE_MIN) {
            ops_alert($db, 'health.cron', 'The cron has stopped',
                      'No background run for ' . ($ago >= 120 ? intdiv($ago, 60) . ' hours' : $ago . ' minutes') . ': reminders, billing, retries and this monitor are waiting.',
                      'health', 'cron-stopped:' . date('Y-m-d') . '-' . intdiv((int)date('G'), 2));
        }
    } catch (Throwable $e) {
        @error_log('Nfelia ops_watchdog: ' . $e->getMessage());
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE MORNING SUMMARY, AND CLEARING UP
 * ═══════════════════════════════════════════════════════════════════ */

/** Once a day from 8am, to Admins and Support who want it. Returns how many were queued. */
function ops_digest(PDO $db, DateTimeImmutable $now): int
{
    $day = $now->format('Y-m-d');
    if (ops_setting($db, 'digest:' . $day) !== null) {
        return 0;
    }
    ops_setting_set($db, 'digest:' . $day, '1');

    $new = $db->query("SELECT id, kind, level, title, events FROM error_groups WHERE first_seen_at >= CURDATE() - INTERVAL 1 DAY AND first_seen_at < CURDATE()
                       AND level NOT IN ('notice', 'deprecated') ORDER BY FIELD(level, 'fatal', 'error', 'warning', 'slow'), events DESC LIMIT 8")->fetchAll();
    $back = $db->query("SELECT id, kind, level, title, events FROM error_groups WHERE regressed_at >= CURDATE() - INTERVAL 1 DAY ORDER BY regressed_at DESC LIMIT 5")->fetchAll();
    $top  = $db->query("SELECT g.id, g.kind, g.level, g.title, COUNT(e.id) AS day_events FROM error_groups g JOIN error_events e ON e.group_id = g.id
                        WHERE g.status IN ('open', 'handling') AND e.created_at >= CURDATE() - INTERVAL 1 DAY AND e.created_at < CURDATE()
                        GROUP BY g.id ORDER BY day_events DESC LIMIT 5")->fetchAll();
    $open = (int)$db->query("SELECT COUNT(*) FROM error_groups WHERE status IN ('open', 'handling') AND level NOT IN ('notice', 'deprecated')")->fetchColumn();
    $problems = array_values(array_filter(ops_health($db), fn($c) => $c['state'] !== 'ok'));

    $roles = array_keys(array_filter(OPS_PERMISSIONS, fn($p) => in_array('*', $p, true) || in_array('errors', $p, true)));
    $stmt  = $db->prepare("SELECT id, full_name, email FROM ops_staff WHERE is_active = 1 AND password_hash IS NOT NULL AND digest_email = 1
                           AND role IN (" . implode(',', array_fill(0, count($roles), '?')) . ")");
    $stmt->execute($roles);
    $queued = 0;
    foreach ($stmt->fetchAll() as $person) {
        $id = notify_email(null, $person['email'], $person['full_name'], 'Nfelia Ops: yesterday, and what needs a look', 'ops_digest', [
            'name' => $person['full_name'], 'day' => $now->modify('-1 day')->format('l j F'),
            'new' => $new, 'back' => $back, 'top' => $top, 'open' => $open, 'problems' => $problems,
        ], 'ops-digest:' . $day . ':' . $person['id'], 'ops.digest');
        if ($id) $queued++;
    }
    return $queued;
}

/** At 3am: occurrences after 30 days, settled problems after 90, read alerts after 60. */
function ops_monitor_cleanup(PDO $db): void
{
    try {
        $db->exec("DELETE FROM error_events WHERE created_at < NOW() - INTERVAL 30 DAY");
        $db->exec("DELETE n FROM error_notes n JOIN error_groups g ON g.id = n.group_id WHERE g.status IN ('fixed', 'ignored') AND g.last_seen_at < NOW() - INTERVAL 90 DAY");
        $db->exec("DELETE FROM error_groups WHERE status IN ('fixed', 'ignored') AND last_seen_at < NOW() - INTERVAL 90 DAY");
        $db->exec("DELETE FROM ops_alerts WHERE read_at IS NOT NULL AND created_at < NOW() - INTERVAL 60 DAY");
        $db->exec("DELETE FROM ops_settings WHERE name LIKE 'digest:%' AND updated_at < NOW() - INTERVAL 7 DAY");
    } catch (Throwable $e) {
        errors_report_throwable($e, ['job' => 'Ops clean-up']);
    }
}
