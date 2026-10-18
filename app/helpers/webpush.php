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
 * The PWA will reuse this unchanged: it subscribes through the same service
 * worker (/sw.js) and the same endpoint (POST /tasks/push-subscribe).
 */

function push_enabled(): bool {
    return defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''
        && defined('VAPID_PRIVATE_KEY') && VAPID_PRIVATE_KEY !== '';
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
    foreach (['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'] as $exact) {
        if ($host === $exact) return true;
    }
    foreach (['.push.apple.com', '.notify.windows.com'] as $suffix) {
        if (str_ends_with($host, $suffix)) return true;
    }
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

/**
 * Send one notification to one subscription row (push_subscriptions).
 * Returns the HTTP status; 0 means the request never left.
 * 404 and 410 mean the browser unsubscribed, so the caller deletes the row.
 */
function webpush_send(array $sub, array $message, string $urgency = 'normal', int $ttl = 86400): int {
    if (!push_enabled() || !push_endpoint_allowed((string)$sub['endpoint'])) return 0;

    try {
        $body = webpush_encrypt(
            json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            b64url_decode($sub['p256dh']),
            b64url_decode($sub['auth'])
        );
        $auth = webpush_vapid_header($sub['endpoint'], VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT);
    } catch (Throwable $e) {
        error_log('Nfelia push: ' . $e->getMessage());
        return 0;
    }

    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_PROTOCOLS      => push_endpoint_is_test((string)$sub['endpoint']) ? CURLPROTO_HTTP | CURLPROTO_HTTPS : CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $auth,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: ' . $ttl,
            'Urgency: ' . $urgency,
        ],
    ]);
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status;
}

/**
 * Push to every device a user has turned notifications on for. Never
 * throws: a reminder must still reach the bell and email if push fails.
 * $message: title, body, url (path inside the app), tag (replaces an older
 * notification with the same tag instead of stacking).
 */
function push_to_user(PDO $db, int $user_id, array $message, string $urgency = 'normal'): int {
    if (!push_enabled()) return 0;
    $sent = 0;
    try {
        $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT " . PUSH_MAX_DEVICES);
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll() as $sub) {
            $status = webpush_send($sub, $message, $urgency);
            if ($status >= 200 && $status < 300) {
                $sent++;
                $db->prepare("UPDATE push_subscriptions SET failures = 0, last_sent_at = NOW() WHERE id = ?")->execute([$sub['id']]);
            } elseif ($status === 404 || $status === 410) {
                $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]);
            } elseif ($status !== 0) {
                // Status 0 means the request never left this server (network,
                // DNS), which is no fault of the phone, so it does not count.
                $db->prepare("UPDATE push_subscriptions SET failures = failures + 1 WHERE id = ?")->execute([$sub['id']]);
                $db->prepare("DELETE FROM push_subscriptions WHERE id = ? AND failures >= 5")->execute([$sub['id']]);
            }
        }
    } catch (Throwable $e) {
        error_log('Nfelia push: ' . $e->getMessage());
    }
    return $sent;
}
