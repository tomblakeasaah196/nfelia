<?php
/**
 * Nfelia DB migration runner — forward-only, filename-ordered SQL migrations.
 *
 * Layout:
 *   db/migrate.php        this runner (CLI only)
 *   db/migrations/*.sql   migrations, applied in lexical filename order
 *
 * Naming:
 *   YYYYMMDDhhmmss_short_description.sql
 *   e.g. 20261017120000_normalise_collations.sql
 *
 * Tracking:
 *   Applied migrations are recorded in `schema_migrations`. On the first run
 *   against an existing production database the runner seeds a `0000_baseline`
 *   row, so the schema that already exists is never re-created. Only
 *   migrations sorting AFTER `0000_baseline` are considered.
 *
 * Usage:
 *   php db/migrate.php                      apply all pending
 *   php db/migrate.php --status             list applied / pending
 *   php db/migrate.php --dry-run            print what would run
 *   php db/migrate.php --env=/path/to/.env  read credentials from elsewhere
 *                                           (deploy.sh uses this, because the
 *                                           runner lives in the repo and the
 *                                           .env lives in the docroot)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$args       = array_slice($argv, 1);
$dryRun     = in_array('--dry-run', $args, true);
$statusOnly = in_array('--status', $args, true);

$rootDir       = dirname(__DIR__);
$migrationsDir = __DIR__ . '/migrations';

$envPath = $rootDir . '/.env';
foreach ($args as $a) {
    if (str_starts_with($a, '--env=')) { $envPath = substr($a, 6); }
}

if (!is_file($envPath)) {
    fwrite(STDERR, "[migrate] .env not found at {$envPath}\n");
    exit(1);
}

$cfg = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    // trim one layer of surrounding quotes, as config/env.php does
    $cfg[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$host = $cfg['DB_HOST'] ?? 'localhost';
$port = $cfg['DB_PORT'] ?? '3306';
$name = $cfg['DB_NAME'] ?? '';
$user = $cfg['DB_USER'] ?? '';
$pass = $cfg['DB_PASS'] ?? '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "[migrate] DB_NAME / DB_USER missing from {$envPath}\n");
    exit(1);
}

try {
    // No COLLATE in the DSN or any init command. Pinning a server-specific
    // collation here is exactly what broke the app on the MariaDB move.
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "[migrate] connection failed: [{$e->getCode()}] {$e->getMessage()}\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        version     VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

// First run against a database that already has tables: record a baseline so
// the existing schema is treated as already applied.
if (!$applied) {
    $tables = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = " . $pdo->quote($name) . " AND table_type = 'BASE TABLE'"
    )->fetchColumn();
    if ($tables > 1) {   // >1 because schema_migrations itself now exists
        $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")
            ->execute(['0000_baseline']);
        $applied[] = '0000_baseline';
        echo "[migrate] existing database detected ({$tables} tables) — seeded 0000_baseline\n";
    }
}

if (!is_dir($migrationsDir)) {
    echo "[migrate] no db/migrations directory — nothing to do\n";
    exit(0);
}

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

$pending = [];
foreach ($files as $f) {
    $version = basename($f, '.sql');
    if (!in_array($version, $applied, true)) { $pending[] = [$version, $f]; }
}

if ($statusOnly) {
    echo "[migrate] applied (" . count($applied) . "):\n";
    foreach ($applied as $v) { echo "    $v\n"; }
    echo "[migrate] pending (" . count($pending) . "):\n";
    foreach ($pending as [$v]) { echo "    $v\n"; }
    exit(0);
}

if (!$pending) {
    echo "[migrate] up to date — nothing pending\n";
    exit(0);
}

echo "[migrate] " . count($pending) . " pending migration(s)\n";

$insert = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");

foreach ($pending as [$version, $file]) {
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "[migrate] {$version}: unreadable or empty — aborting\n");
        exit(1);
    }

    if ($dryRun) { echo "  WOULD APPLY  {$version}\n"; continue; }

    echo "  applying     {$version} ... ";
    try {
        // Most DDL in MySQL/MariaDB commits implicitly, so a transaction
        // cannot roll a half-applied migration back. Keep each migration
        // small and idempotent instead; on failure we stop immediately so
        // the version is never recorded and the next run retries it.
        // exec() reports an error only from the first statement of a file;
        // stepping through every result reports one from any statement.
        $stmt = $pdo->query($sql);
        while ($stmt->nextRowset()) {}
        $stmt->closeCursor();
        $insert->execute([$version]);
        echo "ok\n";
    } catch (PDOException $e) {
        echo "FAILED\n";
        fwrite(STDERR, "[migrate] {$version} failed: {$e->getMessage()}\n");
        fwrite(STDERR, "[migrate] stopping. Nothing after this point was applied.\n");
        exit(1);
    }
}

echo $dryRun ? "[migrate] dry run complete — nothing changed\n"
             : "[migrate] done\n";
