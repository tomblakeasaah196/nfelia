<?php
/**
 * Nfelia Full Verification — READ ONLY. CLI only:  php db_verify.php
 * Run this after the collation conversion to confirm the stack is healthy.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// This script lives in bin/, so the project root is one level up.
define('ROOT', dirname(__DIR__));
$pass = 0; $warn = 0; $fail = 0;
function ok($m)   { global $pass; $pass++; echo "  PASS  $m\n"; }
function warn($m) { global $warn; $warn++; echo "  WARN  $m\n"; }
function bad($m)  { global $fail; $fail++; echo "  FAIL  $m\n"; }

echo "== PHP ==\n";
echo "  version " . PHP_VERSION . "\n";
foreach (['pdo_mysql','openssl','curl','mbstring','json','gd'] as $x) {
    extension_loaded($x) ? ok("ext $x") : bad("ext $x missing");
}

echo "\n== config ==\n";
require_once ROOT . '/config/database.php';
foreach (['APP_URL','APP_KEY','DB_HOST','DB_NAME','DB_USER','DB_PASS',
          'PAYSTACK_SECRET_KEY','PAYSTACK_PUBLIC_KEY','MOMO_RECEIPT_DIR'] as $k) {
    empty($_ENV[$k]) ? bad("$k missing from .env") : ok("$k set");
}
($_ENV['APP_DEBUG'] ?? 'false') === 'false' ? ok('APP_DEBUG off') : warn('APP_DEBUG is ON in production');
$_ENV['DB_HOST'] === 'localhost' ? ok('DB_HOST = localhost (socket)')
    : warn("DB_HOST = {$_ENV['DB_HOST']} — survives a server IP change only if localhost");

echo "\n== database ==\n";
try { $pdo = get_db(); ok('get_db() connected'); }
catch (Throwable $e) { bad('get_db(): ' . $e->getMessage()); exit("\nAborting.\n"); }

$name = $_ENV['DB_NAME'];
$q = $pdo->prepare("SELECT table_collation, COUNT(*) FROM information_schema.tables
                    WHERE table_schema = ? AND table_type = 'BASE TABLE' GROUP BY table_collation");
$q->execute([$name]);
$cols = $q->fetchAll(PDO::FETCH_KEY_PAIR);
count($cols) === 1 && array_key_first($cols) === 'utf8mb4_unicode_ci'
    ? ok('all ' . reset($cols) . ' tables on utf8mb4_unicode_ci')
    : bad('mixed collations: ' . json_encode($cols));

echo "  connection collation: " . $pdo->query("SELECT @@collation_connection")->fetchColumn() . "\n";
echo "  session time_zone   : " . $pdo->query("SELECT @@session.time_zone")->fetchColumn() . "\n";

echo "\n== cross-table join (the illegal-mix test) ==\n";
try {
    $n = $pdo->query("SELECT COUNT(*) FROM users u
                      JOIN businesses b ON b.id = u.business_id")->fetchColumn();
    ok("users JOIN businesses -> $n rows");
} catch (PDOException $e) { bad('join: ' . $e->getMessage()); }
try {
    $pdo->query("SELECT COUNT(*) FROM invoices i
                 JOIN businesses b ON b.id = i.business_id
                 WHERE b.name COLLATE utf8mb4_unicode_ci <> ''")->fetchColumn();
    ok('invoices JOIN businesses with text comparison');
} catch (PDOException $e) { bad('join2: ' . $e->getMessage()); }

echo "\n== writable paths ==\n";
foreach (['uploads' => ROOT . '/uploads',
          'storage/logs' => ROOT . '/storage/logs',
          'momo' => $_ENV['MOMO_RECEIPT_DIR'] ?? ''] as $label => $d) {
    if ($d === '') { bad("$label unset"); continue; }
    if (!is_dir($d)) { bad("$label missing: $d"); continue; }
    $t = rtrim($d,'/') . '/.wtest';
    if (@file_put_contents($t, 'x') !== false) { @unlink($t); ok("$label writable"); }
    else bad("$label NOT writable: $d");
}

echo "\n== secrets hygiene ==\n";
is_readable(ROOT . '/.env') ? ok('.env readable by app') : bad('.env unreadable');
printf("  .env permissions: %o\n", fileperms(ROOT . '/.env') & 0777);
(fileperms(ROOT . '/.env') & 0077) === 0 ? ok('.env not group/world readable') : bad('.env too permissive — chmod 600 it');
str_contains(file_get_contents(ROOT . '/config/database.php'), 'Fasah')
    ? bad('password still hardcoded in config/database.php') : ok('no hardcoded password in database.php');

echo "\n---------------------------------\n";
echo "PASS $pass   WARN $warn   FAIL $fail\n";
exit($fail > 0 ? 1 : 0);
