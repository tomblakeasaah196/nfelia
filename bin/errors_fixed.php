<?php
/**
 * Marks errors fixed in Nfelia Ops when the commit that fixes them goes live.
 * bin/deploy.sh runs it after every deploy, over the commits that deploy
 * brought in. A commit says what it fixes with a line of its own:
 *
 *   Fixes-Errors: #12, #15, E7KQ2M9X
 *
 * #12 is the number of the problem in Ops (ops.nfelia.com/errors/12); an
 * error code finds its problem too. Problems that are open or being handled
 * become fixed, with a note naming the commit. If one happens again after
 * that, Ops opens it again and tells the team (app/helpers/errors.php).
 *
 *   php bin/errors_fixed.php --env=/path/to/.env --repo=/path/to/checkout --from=<sha> --to=<sha>
 *
 * It never fails a deploy: whatever goes wrong is printed and it exits 0.
 */

/** The problems a set of commit messages says they fix: ['ids' => [12, …], 'refs' => ['E7KQ2M9X', …]]. */
function errors_fixed_refs(string $message): array
{
    $ids = $refs = [];
    // A line of its own; "* " or "- " in front survives a squash merge's list.
    if (preg_match_all('/^[ \t>*-]*Fixes-Errors:[ \t]*(.+)$/mi', $message, $m)) {
        foreach ($m[1] as $list) {
            foreach (preg_split('/[\s,;]+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                if (preg_match('/^#?(\d{1,10})$/', $token, $n)) {
                    $ids[] = (int)$n[1];
                } elseif (preg_match('/^E[A-Z2-9]{7}$/', strtoupper($token))) {
                    $refs[] = strtoupper($token);
                }
            }
        }
    }
    return ['ids' => array_values(array_unique($ids)), 'refs' => array_values(array_unique($refs))];
}

/**
 * Marks fixed what these commits fix. $commits: [['sha' => …, 'subject' => …, 'body' => …], …].
 * Returns the problems it marked: [['id' => 12, 'title' => …, 'sha' => …], …].
 */
function errors_fixed_apply(PDO $db, array $commits, string $release): array
{
    $done = [];
    $find_ref = $db->prepare("SELECT group_id FROM error_events WHERE ref = ?");
    $get      = $db->prepare("SELECT id, title, status FROM error_groups WHERE id = ?");
    $set      = $db->prepare("UPDATE error_groups SET status = 'fixed', status_by = NULL, status_changed_at = NOW() WHERE id = ? AND status IN ('open', 'handling')");
    $note     = $db->prepare("INSERT INTO error_notes (group_id, staff_id, body) VALUES (?, NULL, ?)");
    $audit    = $db->prepare("INSERT INTO ops_audit (staff_id, action, summary, target_type, target_id, data) VALUES (NULL, 'error.fixed', ?, 'error', ?, ?)");
    foreach ($commits as $c) {
        $said = errors_fixed_refs($c['body']);
        $ids = $said['ids'];
        foreach ($said['refs'] as $ref) {
            $find_ref->execute([$ref]);
            if ($id = $find_ref->fetchColumn()) $ids[] = (int)$id;
        }
        foreach (array_unique($ids) as $id) {
            $get->execute([$id]);
            $g = $get->fetch(PDO::FETCH_ASSOC);
            if (!$g) continue;
            $set->execute([$id]);
            if ($set->rowCount() !== 1) continue;
            $short = substr($c['sha'], 0, 7);
            $note->execute([$id, 'Marked fixed when ' . $short . ' went live (release ' . $release . '): ' . mb_substr($c['subject'], 0, 200)]);
            $audit->execute(['Fixed: "' . mb_substr($g['title'], 0, 120) . '"', $id,
                             json_encode(['from' => $g['status'], 'to' => 'fixed', 'release' => $release, 'commit' => $c['sha'], 'by' => 'deploy'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $done[] = ['id' => $id, 'title' => $g['title'], 'sha' => $short];
        }
    }
    return $done;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $opt = [];
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--(env|repo|from|to)=(.*)$/', $a, $m)) $opt[$m[1]] = $m[2];
    }
    $say = fn(string $s) => print('[errors] ' . $s . "\n");
    try {
        foreach (['env', 'repo', 'from', 'to'] as $k) {
            if (($opt[$k] ?? '') === '') throw new RuntimeException("--{$k} is missing");
        }
        if (!preg_match('/^[0-9a-f]{7,40}$/', $opt['from']) || !preg_match('/^[0-9a-f]{7,40}$/', $opt['to'])) {
            throw new RuntimeException('--from and --to must be commit hashes');
        }
        if ($opt['from'] === $opt['to']) {
            $say('no new commits');
            exit(0);
        }
        $log = shell_exec('git -C ' . escapeshellarg($opt['repo']) . ' log --format=%H%x1f%s%x1f%B%x1e '
                          . escapeshellarg($opt['from'] . '..' . $opt['to']) . ' 2>/dev/null');
        $commits = [];
        foreach (explode("\x1e", (string)$log) as $entry) {
            $parts = explode("\x1f", ltrim($entry), 3);
            if (count($parts) === 3) $commits[] = ['sha' => $parts[0], 'subject' => $parts[1], 'body' => $parts[2]];
        }
        if (!array_filter($commits, fn($c) => stripos($c['body'], 'Fixes-Errors:') !== false)) {
            $say('no commit says it fixes an error');
            exit(0);
        }

        $cfg = [];
        foreach (file($opt['env'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $cfg[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
        $db = new PDO('mysql:host=' . ($cfg['DB_HOST'] ?? 'localhost') . ';port=' . ($cfg['DB_PORT'] ?? '3306') . ';dbname=' . ($cfg['DB_NAME'] ?? '') . ';charset=utf8mb4',
                      $cfg['DB_USER'] ?? '', $cfg['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec("SET time_zone = '+01:00'");   // Lagos, as the app keeps it

        $done = errors_fixed_apply($db, $commits, substr($opt['to'], 0, 7));
        foreach ($done as $d) {
            $say('#' . $d['id'] . ' marked fixed (' . $d['sha'] . '): ' . mb_substr($d['title'], 0, 100));
        }
        if (!$done) $say('nothing open to mark fixed');
    } catch (Throwable $e) {
        $say('skipped: ' . $e->getMessage());
    }
    exit(0);
}
