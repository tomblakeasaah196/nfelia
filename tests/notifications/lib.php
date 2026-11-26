<?php
/**
 * Shared by the notification tests (tests/notifications/*_test.php).
 *
 * The app's own code is loaded (tests/bootstrap.php) for in-process checks;
 * the HTTP tests talk to the app running at APP_URL. Phones are stood in for
 * by push_sink.php and inboxes by smtp_sink.py: run.sh starts both and sets
 * NF_PUSH_DIR, NF_MAIL_DIR and NF_PUSH_URL.
 *
 * These tests wipe and rebuild the database (fixture.php). t_guard() refuses
 * to run unless APP_ENV is local or testing and APP_URL is this machine.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/fixture.php';

define('T_PUSH_DIR', getenv('NF_PUSH_DIR') ?: sys_get_temp_dir() . '/nf-push');
define('T_MAIL_DIR', getenv('NF_MAIL_DIR') ?: sys_get_temp_dir() . '/nf-mail');
define('T_PUSH_URL', rtrim(getenv('NF_PUSH_URL') ?: 'http://127.0.0.1:8098', '/'));
define('T_PASSWORD', 'TestPass1');

/* ── Results ─────────────────────────────────────────────── */

$GLOBALS['nf_results'] = ['pass' => 0, 'fail' => 0, 'failed' => [], 'section' => ''];

function section(string $name): void
{
    $GLOBALS['nf_results']['section'] = $name;
    echo "\n{$name}\n";
}

function ok($cond, string $msg): bool
{
    $t = &$GLOBALS['nf_results'];
    if ($cond) {
        $t['pass']++;
        echo "  PASS  {$msg}\n";
    } else {
        $t['fail']++;
        $t['failed'][] = $t['section'] . ': ' . $msg;
        echo "  FAIL  {$msg}\n";
    }
    return (bool)$cond;
}

/** ok() that says what it got when it fails. */
function eq($got, $want, string $msg): bool
{
    return ok($got === $want, $msg . ($got === $want ? '' : '  (got ' . json_encode($got, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . ', want ' . json_encode($want, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ')'));
}

function done(): never
{
    $t = $GLOBALS['nf_results'];
    echo "\nRESULT: {$t['pass']} passed, {$t['fail']} failed\n";
    foreach ($t['failed'] as $f) echo "  - {$f}\n";
    exit($t['fail'] ? 1 : 0);
}

/** Never against anything but a local or CI copy: these tests wipe the database. */
function t_guard(): void
{
    $host = (string)parse_url(APP_URL, PHP_URL_HOST);
    if (!in_array(APP_ENV, ['local', 'testing'], true) || !in_array($host, ['127.0.0.1', 'localhost'], true)) {
        fwrite(STDERR, "Refusing to run: APP_ENV must be local or testing and APP_URL this machine (got " . APP_ENV . ", {$host}).\n");
        exit(2);
    }
}

/* ── Database ────────────────────────────────────────────── */

function q(string $sql, array $p = [])
{
    $s = get_db()->prepare($sql);
    $s->execute($p);
    return $s->fetchColumn();
}

function rows(string $sql, array $p = []): array
{
    $s = get_db()->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
}

function exec_sql(string $sql, array $p = []): int
{
    $s = get_db()->prepare($sql);
    $s->execute($p);
    return $s->rowCount();
}

/** Bell rows a person has, newest last, optionally of one event. */
function bell(int $user_id, ?string $event = null): array
{
    return rows("SELECT * FROM notifications WHERE user_id = ?" . ($event ? " AND event = ?" : '') . " ORDER BY id",
                $event ? [$user_id, $event] : [$user_id]);
}

/** Outbox rows, optionally of one event and channel. */
function outbox(?string $event = null, ?string $channel = null, ?int $user_id = null): array
{
    $w = ['1 = 1'];
    $p = [];
    if ($event)   { $w[] = 'event = ?';   $p[] = $event; }
    if ($channel) { $w[] = 'channel = ?'; $p[] = $channel; }
    if ($user_id) { $w[] = 'user_id = ?'; $p[] = $user_id; }
    return rows("SELECT * FROM notify_outbox WHERE " . implode(' AND ', $w) . " ORDER BY id", $p);
}

/** Who got a bell row for an event, as user ids. */
function told(string $event): array
{
    return array_map('intval', array_column(rows("SELECT user_id FROM notifications WHERE event = ? ORDER BY user_id", [$event]), 'user_id'));
}

/** Start a check from nothing: no alerts, nothing waiting, no captured mail or pushes. */
function clean_slate(): void
{
    get_db()->exec("DELETE FROM notifications");
    get_db()->exec("DELETE FROM notify_outbox");
    foreach (glob(T_MAIL_DIR . '/*.eml') ?: [] as $f) unlink($f);
    foreach (glob(T_PUSH_DIR . '/*.json') ?: [] as $f) unlink($f);
    @unlink(T_MAIL_DIR . '/REFUSE');
}

/** Send whatever is waiting now (what the page's after-response or the cron does). */
function deliver(): array
{
    exec_sql("UPDATE notify_outbox SET next_attempt_at = NOW() WHERE status = 'pending'");
    return notify_flush();
}

/* ── Phones (push_sink.php) ──────────────────────────────── */

/**
 * A phone with notifications on for this person: a real key pair, so what
 * arrives can be decrypted exactly as the phone would. $kind is how the
 * push service will answer: ok, gone, busy or bad.
 */
function phone(int $user_id, string $kind = 'ok'): array
{
    $pair = webpush_new_keypair();
    $auth = random_bytes(16);
    $endpoint = T_PUSH_URL . '/' . $kind . '-u' . $user_id . '-' . bin2hex(random_bytes(4));
    $biz = (int)q("SELECT business_id FROM users WHERE id = ?", [$user_id]);
    exec_sql("INSERT INTO push_subscriptions (business_id, user_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, ?, ?, ?)",
             [$biz, $user_id, $endpoint, hash('sha256', $endpoint), b64url_encode($pair['public']), b64url_encode($auth)]);
    return ['user' => $user_id, 'endpoint' => $endpoint, 'path' => parse_url($endpoint, PHP_URL_PATH),
            'private' => webpush_private_pem($pair['d'], $pair['public']), 'public' => $pair['public'], 'auth' => $auth];
}

/** Every request the push service received. */
function pushes_raw(): array
{
    $files = glob(T_PUSH_DIR . '/*.json') ?: [];
    natsort($files);
    return array_map(fn($f) => json_decode(file_get_contents($f), true), array_values($files));
}

/** What this phone was sent, decrypted as the phone would (RFC 8291): message arrays. */
function pushes_to(array $phone): array
{
    $out = [];
    foreach (pushes_raw() as $req) {
        if ($req['path'] !== $phone['path']) continue;
        $msg = decrypt_push($req, $phone);
        if ($msg !== null) $out[] = $msg + ['_headers' => $req['headers']];
    }
    return $out;
}

function decrypt_push(array $req, array $phone): ?array
{
    $b = base64_decode($req['body'] ?? '');
    if (strlen($b) < 21) return null;
    $server_pub = substr($b, 21, ord($b[20]));
    $secret = openssl_pkey_derive(webpush_public_pem($server_pub), $phone['private'], 32);
    if ($secret === false) return null;
    $ikm   = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $phone['public'] . $server_pub, $phone['auth']);
    $salt  = substr($b, 0, 16);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $ct    = substr($b, 21 + strlen($server_pub));
    $plain = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    if ($plain === false) return null;
    $plain = rtrim($plain, "\0");
    return json_decode(substr($plain, 0, -1), true);   // drop the 0x02 record delimiter
}

/* ── Inboxes (smtp_sink.py) ──────────────────────────────── */

/** Every captured email: to, subject, html, text. */
function mails(): array
{
    $files = glob(T_MAIL_DIR . '/*.eml') ?: [];
    natsort($files);
    $out = [];
    foreach ($files as $f) {
        $raw = file_get_contents($f);
        $parsed = t_parse_mail($raw);
        $out[] = $parsed;
    }
    return $out;
}

function mails_to(string $address): array
{
    return array_values(array_filter(mails(), fn($m) => stripos($m['to'], $address) !== false));
}

function t_parse_mail(string $raw): array
{
    [$head, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $header = function (string $name) use ($head) {
        return preg_match('/^' . $name . ':\s*(.*(?:\n[ \t].*)*)/mi', $head, $m) ? trim(preg_replace('/\n[ \t]+/', ' ', $m[1])) : '';
    };
    $html = '';
    if (preg_match('/boundary="?([^";\r\n]+)"?/i', $raw, $bm)) {
        foreach (explode('--' . $bm[1], $raw) as $part) {
            if (!preg_match('/Content-Type:\s*text\/html/i', $part)) continue;
            [$ph, $pb] = array_pad(preg_split("/\r?\n\r?\n/", $part, 2), 2, '');
            $enc = preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $ph, $em) ? strtolower($em[1]) : '';
            $html = $enc === 'base64' ? base64_decode($pb) : ($enc === 'quoted-printable' ? quoted_printable_decode($pb) : $pb);
        }
    } else {
        $html = $body;
    }
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
    return ['to' => $header('To'), 'subject' => iconv_mime_decode($header('Subject'), 0, 'UTF-8'), 'html' => $html, 'text' => $text,
            'unsubscribe' => $header('List-Unsubscribe'), 'unsubscribe_post' => $header('List-Unsubscribe-Post'),
            'rcpt' => $header('X-Sink-Rcpt'), 'reply_to' => $header('Reply-To'), 'from' => $header('From')];
}

/* ── HTTP (the running app) ──────────────────────────────── */

/**
 * A browser of sorts: its own cookie jar, the session's CSRF token picked
 * up from each page. Redirects are not followed, so each step can be seen.
 */
final class Http
{
    public int $status = 0;
    public string $body = '';
    public string $location = '';
    public array $headers = [];
    public string $csrf = '';
    private string $jar;
    /** Cookies kept here rather than by curl ($own_cookies): name => value. */
    private array $cookies = [];

    /**
     * $own_cookies: keep cookies here instead of in curl's jar. curl files a
     * cookie under the Host header's name and won't send a Secure one over
     * plain http to anything but "localhost", where a browser also sends it
     * to name.localhost (Ops at ops.localhost: tests/ops/lib.php).
     */
    public function __construct(private string $host = '', private bool $own_cookies = false)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'nfjar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** The same visitor on another host (a shop: name.<app host>). */
    public function on(string $host): self
    {
        $copy = clone $this;
        $copy->host = $host;
        return $copy;
    }

    public function get(string $path, array $headers = []): self
    {
        return $this->send('GET', $path, null, $headers);
    }

    /** A form post; the session's token goes in as csrf_token unless given. */
    public function post(string $path, array $fields = [], array $headers = []): self
    {
        if (!array_key_exists('csrf_token', $fields) && $this->csrf !== '') $fields['csrf_token'] = $this->csrf;
        $multipart = (bool)array_filter($fields, fn($v) => $v instanceof CURLFile);
        return $this->send('POST', $path, $multipart ? $fields : http_build_query($fields), $headers);
    }

    /** JSON, with the token in X-CSRF-Token, as the app's scripts send it. */
    public function json(string $path, array $data = [], array $headers = []): self
    {
        $given = array_map(fn($h) => strtolower(strtok($h, ':')), $headers);
        $defaults = array_filter(['Content-Type: application/json', 'X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: ' . $this->csrf],
                                 fn($h) => !in_array(strtolower(strtok($h, ':')), $given, true));
        return $this->send('POST', $path, json_encode($data), array_merge(array_values($defaults), $headers));
    }

    public function data(): array
    {
        return json_decode($this->body, true) ?? [];
    }

    /** Where a redirect goes, as a path inside the app ("login?next=…"). */
    public function to(): string
    {
        return ltrim(str_replace(APP_URL, '', $this->location), '/');
    }

    public function send(string $method, string $path, $body = null, array $headers = []): self
    {
        $url = str_starts_with($path, 'http') ? $path : APP_URL . '/' . ltrim($path, '/');
        if ($this->host !== '') $headers[] = 'Host: ' . $this->host;
        if ($this->own_cookies && $this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($this->cookies), $this->cookies));
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
        ] + ($this->own_cookies ? [] : [CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar]));
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = (string)curl_exec($ch);
        $this->status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $size);
        $this->body = substr($raw, $size);
        $this->headers = [];
        foreach (explode("\n", $head) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
                if ($this->own_cookies && strtolower(trim($k)) === 'set-cookie' && preg_match('/^\s*([^=;\s]+)=([^;]*)/', $v, $c)) {
                    $gone = preg_match('/max-age=0|expires=[^;]*19[0-9]{2}/i', $v) || $c[2] === '' || $c[2] === 'deleted';
                    if ($gone) unset($this->cookies[$c[1]]); else $this->cookies[$c[1]] = $c[2];
                }
            }
        }
        $this->location = $this->headers['location'] ?? '';
        if (preg_match('/<meta name="csrf-token" content="([a-f0-9]+)"/', $this->body, $m)
            || preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m)
            || preg_match("/csrf: '([a-f0-9]+)'/", $this->body, $m)) {
            $this->csrf = $m[1];
        }
        return $this;
    }

    /** The browser was closed: the session cookie goes, long-lived ones stay. */
    public function close_browser(): self
    {
        $lines = file($this->jar) ?: [];
        file_put_contents($this->jar, implode('', array_filter($lines, fn($l) => !str_contains($l, 'nfelia_session'))));
        return $this;
    }

    /** Follow redirects from here, as a browser would. */
    public function follow(int $max = 8): self
    {
        while ($max-- > 0 && $this->status >= 300 && $this->status < 400 && $this->location !== '') {
            $this->get($this->location);
        }
        return $this;
    }
}

/** Signed in as this person, ready to act. */
function signin(string $email, string $password = T_PASSWORD): Http
{
    $h = new Http();
    $h->get('login');
    $h->post('login', ['email' => $email, 'password' => $password]);
    $h->follow();
    return $h;
}

/* ── Clock ───────────────────────────────────────────────── */

/** Run the cron at a time today on the PHP side (the database keeps its own clock). */
function cron_at(string $hhmm): string
{
    $today = (string)q("SELECT CURDATE()");
    $root = dirname(__DIR__, 2);
    $cmd = 'cd ' . escapeshellarg($root) . " && TZ=Africa/Lagos faketime '{$today} {$hhmm}:00' php cron/planner.php 2>&1";
    return trim((string)shell_exec($cmd));
}
