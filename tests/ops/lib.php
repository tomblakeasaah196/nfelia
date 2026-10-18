<?php
/**
 * Shared by the Nfelia Ops tests (tests/ops/*_test.php). The same harness
 * as the notification tests (tests/notifications/lib.php: the fixture, the
 * stand-in inbox and phones, Http), pointed at ops.<host>.
 *
 *   bash tests/notifications/run.sh ops/foundation
 *
 * Requests go to APP_URL with "Host: ops.localhost:port", as the shop tests
 * reach shops: curl only sends Secure cookies (the __Host- session) to
 * "localhost" itself over plain http, where a browser also sends them to
 * ops.localhost. One jar can then hold the app's and Ops' sessions at once,
 * which is the harder case: Ops must ignore the app's cookie.
 */
require_once __DIR__ . '/../notifications/lib.php';

/** ops.localhost:8099, as the Host header. */
function ops_hostport(): string
{
    return (string)parse_url(ops_origin(), PHP_URL_HOST) . (parse_url(ops_origin(), PHP_URL_PORT) ? ':' . parse_url(ops_origin(), PHP_URL_PORT) : '');
}

/** A new browser on Ops. */
function ops_browser(): Http
{
    return new Http(ops_hostport(), true);
}

/** A path on Ops for Http (a full Ops link, from an email, works too). */
function o(string $path = ''): string
{
    if (str_starts_with($path, ops_origin())) {
        $path = substr($path, strlen(ops_origin()));
    }
    return '/' . ltrim($path, '/');
}

/** A browser signed in to Ops as this person. */
function as_ops(string $email, string $password = T_OPS_PASSWORD): Http
{
    exec_sql("DELETE FROM auth_throttle WHERE bucket LIKE 'ops%'");   // one network signs in many times here
    $h = ops_browser();
    $h->get(o('signin'));
    $h->post(o('signin'), ['email' => $email, 'password' => $password]);
    return $h;
}

/** Where an Ops redirect goes, as a path inside Ops. */
function ops_to(Http $h): string
{
    return ltrim(str_replace(ops_origin(), '', $h->location), '/');
}

function audit_rows(string $action): array
{
    return rows("SELECT * FROM ops_audit WHERE action = ? ORDER BY id", [$action]);
}

/** The one link in the newest email to this address matching a pattern. */
function link_in_mail(string $address, string $pattern): string
{
    deliver();
    $mail = mails_to($address);
    $last = end($mail) ?: ['html' => ''];
    return preg_match($pattern, html_entity_decode($last['html']), $m) ? $m[0] : '';
}

/** A phone with Ops alerts on for this person (the stand-in push service decrypts what arrives). */
function ops_phone(int $staff_id, string $kind = 'ok'): array
{
    $pair = webpush_new_keypair();
    $auth = random_bytes(16);
    $endpoint = T_PUSH_URL . '/' . $kind . '-s' . $staff_id . '-' . bin2hex(random_bytes(4));
    exec_sql("INSERT INTO ops_push_subscriptions (staff_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, ?, ?)",
             [$staff_id, $endpoint, hash('sha256', $endpoint), b64url_encode($pair['public']), b64url_encode($auth)]);
    return ['user' => $staff_id, 'endpoint' => $endpoint, 'path' => parse_url($endpoint, PHP_URL_PATH),
            'private' => webpush_private_pem($pair['d'], $pair['public']), 'public' => $pair['public'], 'auth' => $auth];
}

/** Who in Ops got an alert of this kind, as staff ids. */
function ops_told(string $kind): array
{
    return array_map('intval', array_column(rows("SELECT staff_id FROM ops_alerts WHERE kind = ? ORDER BY staff_id", [$kind]), 'staff_id'));
}

/** Ops alerts in someone's bell. */
function ops_bell(int $staff_id): array
{
    return rows("SELECT * FROM ops_alerts WHERE staff_id = ? ORDER BY id", [$staff_id]);
}

/** Start a monitor check from nothing. */
function monitor_slate(): void
{
    clean_slate();
    foreach (['error_notes', 'error_events', 'error_groups', 'ops_alerts', 'ops_settings', 'ops_heartbeats'] as $t) {
        exec_sql("DELETE FROM {$t}");
    }
    exec_sql("DELETE FROM auth_throttle");
}

/** The monitor starts reading the server's logs from here: what they already hold is not this run's. */
function logs_read_up_to_now(): void
{
    require_once dirname(__DIR__, 2) . '/app/ops/monitor.php';
    foreach (ops_log_sources() as $path) {
        clearstatcache(true, $path);
        ops_setting_set(get_db(), 'log:' . sha1($path), json_encode(['offset' => (int)filesize($path), 'inode' => (int)fileinode($path)]));
    }
}

/** Break the app on purpose (tests/ops/boom.php). */
function boom(string $what, ?Http $as = null, array $headers = []): Http
{
    $h = $as ?? new Http();
    $h->get('__boom/' . $what, $headers);
    return $h;
}

function group_of(string $ref): ?array
{
    return rows("SELECT g.* FROM error_groups g JOIN error_events e ON e.group_id = g.id WHERE e.ref = ?", [$ref])[0] ?? null;
}

/** The newest alert in someone's bell. */
function last_bell(int $staff_id): array
{
    $all = ops_bell($staff_id);
    return $all ? $all[count($all) - 1] : [];
}
