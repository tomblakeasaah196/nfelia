<?php
/**
 * Nfelia — Web Push (phone and desktop notifications), no library needed.
 *
 * Implements the two standards every browser push service speaks:
 *   RFC 8291  message encryption (aes128gcm, ECDH P-256 + HKDF)
 *   RFC 8292  VAPID, which proves the message comes from us
 * using only PHP's built-in openssl and hash functions, so nothing is added
 * to composer.lock and a deploy cannot break on a missing extension.
 *
 * Keys live in .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY, base64url, as
 * printed by bin/vapid-keys.php). Without them push is simply switched off:
 * push_enabled() is false and the page hides the "Turn on" button.
 *
 * Phones subscribe through the service worker (/sw.js) at POST
 * api/notifications?action=subscribe (assets/js/push.js). Sending, retrying
 * and forgetting dead subscriptions is app/helpers/notify.php's job
 * (notify_send_push()).
 */

function push_enabled(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === '' || !defined('VAPID_PRIVATE_KEY') || VAPID_PRIVATE_KEY === '') {
        return $ok = false;
    }
    // A pair that can't work (pasted wrongly, standard base64, cut short)
    // must not switch push on: phones would subscribe and then every send
    // would fail. Checked once per request.
    $public  = b64url_decode(VAPID_PUBLIC_KEY);
    $private = b64url_decode(VAPID_PRIVATE_KEY);
    $ok = preg_match('/^[A-Za-z0-9_-]+$/', VAPID_PUBLIC_KEY . VAPID_PRIVATE_KEY) === 1
        && strlen($public) === 65 && $public[0] === "\x04" && strlen($private) === 32;
    if (!$ok) {
        error_log('Nfelia push: VAPID keys are not a usable pair (public must be 65 bytes starting 0x04, private 32 bytes, both base64url); push is off');
    }
    return $ok;
}

function b64url_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $txt): string {
    $txt = strtr($txt, '-_', '+/');
    $pad = strlen($txt) % 4;
    if ($pad) { $txt .= str_repeat('=', 4 - $pad); }
    return (string)base64_decode($txt, true);
}

/** A raw 65-byte P-256 point (0x04 || X || Y) as a PEM public key. */
function webpush_public_pem(string $point): string {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** A raw 32-byte private scalar and its public point as a PEM private key (SEC1). */
function webpush_private_pem(string $d, string $point): string {
    $der = hex2bin('30770201010420') . $d
         . hex2bin('a00a06082a8648ce3d030107a144034200') . $point;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** New P-256 key pair as raw bytes: ['d' => 32 bytes, 'public' => 65 bytes]. */
function webpush_new_keypair(): array {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $ec  = openssl_pkey_get_details($key)['ec'];
    $pad = fn(string $v) => str_pad($v, 32, "\0", STR_PAD_LEFT);
    return ['d' => $pad($ec['d']), 'public' => "\x04" . $pad($ec['x']) . $pad($ec['y'])];
}

/**
 * Encrypt a payload for one subscription (RFC 8291). $ua_public / $auth are
 * the browser's p256dh and auth values, raw. $local and $salt exist so the
 * RFC's published test vector can be reproduced; leave them null.
 */
function webpush_encrypt(string $payload, string $ua_public, string $auth, ?array $local = null, ?string $salt = null): string {
    $local = $local ?? webpush_new_keypair();
    $salt  = $salt  ?? random_bytes(16);

    $secret = openssl_pkey_derive(
        webpush_public_pem($ua_public),
        webpush_private_pem($local['d'], $local['public']),
        32
    );
    if ($secret === false) {
        throw new RuntimeException('Push: key agreement failed');
    }

    $ikm   = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $ua_public . $local['public'], $auth);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    // One record, so the padding delimiter is 0x02 ("last record").
    $tag = '';
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Push: encryption failed');
    }

    return $salt . pack('N', 4096) . chr(strlen($local['public'])) . $local['public'] . $cipher . $tag;
}

/** VAPID Authorization header for one push service origin (RFC 8292). */
function webpush_vapid_header(string $endpoint, string $public_b64, string $private_b64, string $subject): string {
    $parts = parse_url($endpoint);
    $aud   = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

    $input = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
           . '.' . b64url_encode(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $subject]));

    $public = b64url_decode($public_b64);
    $der = '';
    if (!openssl_sign($input, $der, webpush_private_pem(b64url_decode($private_b64), $public), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Push: signing failed');
    }

    return 'vapid t=' . $input . '.' . b64url_encode(webpush_der_to_raw($der)) . ', k=' . $public_b64;
}

/** openssl gives ECDSA signatures as DER; JWT wants the raw 64-byte R || S. */
function webpush_der_to_raw(string $der): string {
    $pos = 2;                                   // skip SEQUENCE tag + length
    if (ord($der[1]) & 0x80) { $pos += ord($der[1]) & 0x7f; }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);             // INTEGER tag, then length
        $int = substr($der, $pos + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

/** The most devices one person can have notifications on at once. */
const PUSH_MAX_DEVICES = 10;

/**
 * Is this a real browser push service? Only those are ever called, so a
 * made-up "subscription" can't make Nfelia's server knock on other hosts.
 */
function push_endpoint_allowed(string $endpoint): bool {
    if (push_endpoint_is_test($endpoint)) {
        return true;
    }
    $parts = parse_url($endpoint);
    if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['port']) || isset($parts['user'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    // Chrome, Edge, Brave, Opera, Samsung Internet and Vivaldi (FCM), Firefox
    // (Mozilla), Safari and iPhone (Apple), Edge on Windows (WNS). More can be
    // allowed from .env without a deploy: PUSH_EXTRA_HOSTS=host,.suffix
    $exact  = ['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];
    $suffix = ['.push.apple.com', '.notify.windows.com'];
    foreach (array_filter(array_map('trim', explode(',', (string)($_ENV['PUSH_EXTRA_HOSTS'] ?? '')))) as $extra) {
        if (str_starts_with($extra, '.')) $suffix[] = strtolower($extra); else $exact[] = strtolower($extra);
    }
    if (in_array($host, $exact, true)) return true;
    foreach ($suffix as $end) {
        if (str_ends_with($host, $end)) return true;
    }
    // Written down, so a browser nobody thought of can be allowed.
    error_log('Nfelia push: endpoint host not allowed: ' . $host);
    return false;
}

/**
 * A stand-in push service for local tests (docs/handoff/testkit/push_endpoint.php),
 * named in .env as PUSH_TEST_ENDPOINTS=http://127.0.0.1:8098/,... Never set
 * in production.
 */
function push_endpoint_is_test(string $endpoint): bool {
    foreach (array_filter(array_map('trim', explode(',', (string)($_ENV['PUSH_TEST_ENDPOINTS'] ?? '')))) as $prefix) {
        if (str_starts_with($endpoint, $prefix)) return true;
    }
    return false;
}

/** Why a push did not go, from webpush_send(), by its answer code. */
const PUSH_NOT_ALLOWED  = -1;   // the endpoint is not a push service Nfelia may call: the row is no use
const PUSH_BAD_KEYS     = -2;   // the phone's keys don't work: the row is no use
const PUSH_OUR_FAULT    = -3;   // our own signing failed (the VAPID key): nothing wrong with the phone

/** The most bytes a push payload may be: one aes128gcm record of 4096 with the delimiter and tag. */
const PUSH_MAX_PAYLOAD = 4096 - 1 - 16;

/**
 * Send one notification to one subscription row (push_subscriptions).
 * Returns the HTTP status; 0 means the request never left (the network),
 * and the PUSH_* codes above say why nothing was even tried. $detail gets
 * the reason in words (curl's error, or the start of the service's answer),
 * so a failed send can be understood later.
 * 404 and 410 mean the browser unsubscribed, so the caller deletes the row.
 */
function webpush_send(array $sub, array $message, string $urgency = 'normal', int $ttl = 86400, ?string &$detail = null): int {
    $detail = null;
    if (!push_enabled()) { $detail = 'push is switched off on this server'; return PUSH_OUR_FAULT; }
    if (!push_endpoint_allowed((string)$sub['endpoint'])) {
        $detail = 'not a push service Nfelia may call (' . (string)parse_url((string)$sub['endpoint'], PHP_URL_HOST) . ')';
        return PUSH_NOT_ALLOWED;
    }

    // What the phone shows is short; the rest of the message (ids, badge)
    // is small. Should a body ever be too long, cut it rather than send
    // something no phone can decrypt.
    $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { $detail = 'the message could not be encoded'; return PUSH_OUR_FAULT; }
    while (strlen($json) > PUSH_MAX_PAYLOAD && isset($message['body']) && mb_strlen((string)$message['body']) > 20) {
        $message['body'] = mb_substr((string)$message['body'], 0, (int)(mb_strlen((string)$message['body']) * 0.7)) . '…';
        $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (strlen($json) > PUSH_MAX_PAYLOAD) { $detail = 'the message is too long for a push'; return PUSH_OUR_FAULT; }

    $ua_public = b64url_decode((string)$sub['p256dh']);
    $ua_auth   = b64url_decode((string)$sub['auth']);
    if (strlen($ua_public) !== 65 || $ua_public[0] !== "\x04" || strlen($ua_auth) !== 16) {
        $detail = 'the phone\'s keys are not usable';
        return PUSH_BAD_KEYS;
    }
    try {
        $body = webpush_encrypt($json, $ua_public, $ua_auth);
    } catch (Throwable $e) {
        error_log('Nfelia push: ' . $e->getMessage());
        $detail = $e->getMessage();
        return PUSH_BAD_KEYS;
    }
    try {
        $auth = webpush_vapid_header($sub['endpoint'], VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT);
    } catch (Throwable $e) {
        error_log('Nfelia push: ' . $e->getMessage());
        $detail = $e->getMessage();
        return PUSH_OUR_FAULT;
    }

    $headers = [
        'Authorization: ' . $auth,
        'Content-Encoding: aes128gcm',
        'Content-Type: application/octet-stream',
        'TTL: ' . max(0, $ttl),
        'Urgency: ' . (in_array($urgency, ['very-low', 'low', 'normal', 'high'], true) ? $urgency : 'normal'),
    ];
    // A newer message about the same thing replaces an older one still
    // waiting for a phone that is off, and a retry never doubles up.
    $topic = webpush_topic((string)($message['tag'] ?? ''));

    $post = function (bool $with_topic) use ($sub, $body, $headers, $topic, &$detail): array {
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_PROTOCOLS      => push_endpoint_is_test((string)$sub['endpoint']) ? CURLPROTO_HTTP | CURLPROTO_HTTPS : CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => $with_topic && $topic !== null ? [...$headers, 'Topic: ' . $topic] : $headers,
        ]);
        $answer = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $detail = null;
        if ($answer === false || $status === 0) {
            $detail = 'curl ' . curl_errno($ch) . ': ' . curl_error($ch);
            $status = 0;
        } elseif ($status < 200 || $status >= 300) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$answer)) ?? '');
            $detail = $text !== '' ? mb_substr($text, 0, 160) : null;
        }
        curl_close($ch);
        return [$status, (string)$answer];
    };

    [$status, $answer] = $post(true);
    // Apple's push service refused tags like "booking-12" as a Topic
    // (BadWebPushTopic, 26 Nov 2026), and so lost every alert that had one.
    // Should any service still refuse the Topic, the alert goes again at once
    // without it: replacing an older alert is a nicety, the alert is not.
    if ($status === 400 && $topic !== null && stripos($answer, 'Topic') !== false) {
        [$status, $answer] = $post(false);
    }
    return $status;
}

/**
 * The Topic header for a message's tag: always 22 characters of the
 * URL-safe Base64 alphabet (RFC 8030 §5.4 allows 32), made from the tag, so
 * the same tag always gives the same Topic and a newer alert about the same
 * thing still replaces the older one. Null when there is no tag.
 */
function webpush_topic(string $tag): ?string {
    if ($tag === '') return null;
    return rtrim(strtr(base64_encode(substr(hash('sha256', $tag, true), 0, 16)), '+/', '-_'), '=');
}

/* ═══════════════════════════════════════════════════════════════════
 *  DEVICES: what a phone keeps so it can find its way back
 * ═══════════════════════════════════════════════════════════════════
 * A push subscription can be replaced by the browser while Nfelia is closed
 * (they expire, and push services rotate them). The service worker is told
 * (pushsubscriptionchange), but it has no session and, in Chrome, often no
 * old subscription to prove which row it was. So every phone also keeps a
 * device token, signed by this server, naming the person it was turned on
 * for: with it the new subscription is saved for the right person without
 * anyone opening Nfelia (api/ajax/notifications.php?action=resubscribe).
 */

/**
 * The secrets a device token may be signed with, newest first: APP_KEY, and
 * the VAPID private key (what signed tokens before APP_KEY was set). A token
 * is made with the first and accepted with any, so adding APP_KEY later
 * doesn't silence every phone until it next opens Nfelia.
 */
function push_secrets(): array {
    $out = [];
    $key = trim((string)($_ENV['APP_KEY'] ?? ''));
    if ($key !== '' && !str_starts_with($key, 'REPLACE_WITH')) $out[] = $key;
    if (defined('VAPID_PRIVATE_KEY') && VAPID_PRIVATE_KEY !== '') $out[] = VAPID_PRIVATE_KEY;
    return $out;
}

/** A token for one person's phone: who, which business, when. Checked by push_device_token_read(). */
function push_device_token(int $user_id, int $business_id): string {
    $secrets = push_secrets();
    if (!$secrets) return '';
    $body = b64url_encode(json_encode(['u' => $user_id, 'b' => $business_id, 't' => time()]));
    return $body . '.' . b64url_encode(hash_hmac('sha256', $body, $secrets[0], true));
}

/** ['user_id', 'business_id', 'issued'] for a token this server signed in the last 400 days, else null. */
function push_device_token_read(string $token): ?array {
    if (!preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $token, $m)) return null;
    $good = false;
    foreach (push_secrets() as $secret) {
        if (hash_equals(b64url_encode(hash_hmac('sha256', $m[1], $secret, true)), $m[2])) { $good = true; break; }
    }
    if (!$good) {
        error_log('Nfelia push: a device token did not verify (APP_KEY or the VAPID key changed?)');
        return null;
    }
    $d = json_decode(b64url_decode($m[1]), true);
    if (!is_array($d) || (int)($d['u'] ?? 0) <= 0 || (int)($d['b'] ?? 0) <= 0) return null;
    if ((int)($d['t'] ?? 0) < time() - 400 * 86400) return null;
    return ['user_id' => (int)$d['u'], 'business_id' => (int)$d['b'], 'issued' => (int)$d['t']];
}

/** "Chrome on Android", from the user agent a phone subscribed with. */
function push_device_label(?string $ua): string {
    $ua = (string)$ua;
    if ($ua === '') return 'A phone';
    if (function_exists('signin_device_label')) return signin_device_label($ua);
    return preg_match('/iPhone|iPad/i', $ua) ? 'iPhone' : (preg_match('/Android/i', $ua) ? 'Android' : 'A browser');
}

/**
 * Save a phone for a person: a new row, or their existing row for this
 * endpoint brought up to date. One phone can hold alerts for several people
 * (a shared phone, or one person's two accounts): each keeps theirs until
 * they turn them off. Returns the row id. The caller has already checked the
 * subscription with push_subscription_ok().
 */
function push_device_save(PDO $db, int $business_id, int $user_id, string $endpoint, string $p256dh, string $auth, ?string $ua = null): int {
    $hash = hash('sha256', $endpoint);
    $db->prepare("
        INSERT INTO push_subscriptions (business_id, user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE business_id = VALUES(business_id), endpoint = VALUES(endpoint),
            p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = COALESCE(VALUES(user_agent), user_agent), failures = 0
    ")->execute([$business_id, $user_id, $endpoint, $hash, $p256dh, $auth, $ua !== null ? mb_substr($ua, 0, 255) : null]);
    // A phone saved again is alive after all (a new subscription has a new
    // hash, so this only matters for one wrongly declared dead).
    $db->prepare("DELETE FROM push_dead WHERE endpoint_hash = ?")->execute([$hash]);
    // At most PUSH_MAX_DEVICES phones each: the oldest go first, never the one just saved.
    $db->prepare("
        DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash <> ? AND id NOT IN (
            SELECT id FROM (SELECT id FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT " . (PUSH_MAX_DEVICES - 1) . ") newest
        )
    ")->execute([$user_id, $hash, $user_id]);
    $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?");
    $stmt->execute([$hash, $user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * The browser replaced a phone's subscription: everyone's row for the old
 * endpoint becomes the new one in place (keeping which phone it is and
 * when it was added). A person who already has the new endpoint keeps that
 * row. Returns the user ids renewed.
 */
function push_device_renew(PDO $db, string $old_endpoint, string $endpoint, string $p256dh, string $auth): array {
    $stmt = $db->prepare("SELECT id, user_id FROM push_subscriptions WHERE endpoint_hash = ?");
    $stmt->execute([hash('sha256', $old_endpoint)]);
    $renewed = [];
    foreach ($stmt->fetchAll() as $row) {
        try {
            $db->prepare("UPDATE push_subscriptions SET endpoint = ?, endpoint_hash = ?, p256dh = ?, auth = ?, failures = 0 WHERE id = ?")
               ->execute([$endpoint, hash('sha256', $endpoint), $p256dh, $auth, $row['id']]);
        } catch (PDOException $e) {
            // This person already has the new endpoint: the old row just goes.
            $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$row['id']]);
        }
        $renewed[] = (int)$row['user_id'];
    }
    if ($renewed) {
        $db->prepare("DELETE FROM push_dead WHERE endpoint_hash = ?")->execute([hash('sha256', $endpoint)]);
    }
    return $renewed;
}

/**
 * The push service says this endpoint is gone (404/410), or it can never be
 * used: every person's row for it goes, and the phone is told so next time
 * it asks (push_dead), so it makes a new subscription instead of saving the
 * dead one again. Returns the people who lost it, as rows of (user_id,
 * business_id, user_agent).
 */
function push_device_dead(PDO $db, string $endpoint_hash, string $reason): array {
    $stmt = $db->prepare("SELECT user_id, business_id, user_agent FROM push_subscriptions WHERE endpoint_hash = ?");
    $stmt->execute([$endpoint_hash]);
    $lost = array_map(fn($r) => $r + ['reason' => $reason], $stmt->fetchAll());
    $db->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?")->execute([$endpoint_hash]);
    $db->prepare("INSERT INTO push_dead (endpoint_hash, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_at = NOW()")
       ->execute([$endpoint_hash, mb_substr($reason, 0, 80)]);
    return $lost;
}

/** The first part of each hash the push service has declared dead lately (for the phone to compare its own). */
function push_dead_hashes(PDO $db): array {
    return $db->query("SELECT LEFT(endpoint_hash, 16) FROM push_dead WHERE created_at > NOW() - INTERVAL 30 DAY")->fetchAll(PDO::FETCH_COLUMN);
}

/** Is this a subscription a real browser could have made, for a push service Nfelia may call? */
function push_subscription_ok(string $endpoint, string $p256dh, string $auth): bool {
    $real = preg_match('#^https://[^\s]{10,2000}$#', $endpoint) === 1 || push_endpoint_is_test($endpoint);
    return $real && strlen($endpoint) <= 2000 && push_endpoint_allowed($endpoint)
        && strlen(b64url_decode($p256dh)) === 65 && strlen(b64url_decode($auth)) === 16;
}
