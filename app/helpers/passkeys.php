<?php
/**
 * Passkeys: signing in, or unlocking, with a fingerprint, face or phone PIN.
 *
 * The phone makes a key pair and keeps the private half. Nfelia stores the
 * public half (user_passkeys) and checks each sign-in's signature with it,
 * so the database never holds anything that could sign in. Built on
 * lbuchs/webauthn, with an exact check of the page address the browser
 * reports (the library only compares the end of it).
 *
 * Every challenge lives in the session for 5 minutes and works once.
 */

use lbuchs\WebAuthn\WebAuthn;

/** How long the phone waits for the fingerprint or face, in seconds. */
const PASSKEY_TIMEOUT = 120;

function passkey_server(): WebAuthn
{
    static $server = null;
    // Only the 'none' format: Nfelia does not ask which make of phone it is.
    return $server ??= new WebAuthn('Nfelia', passkey_rp_id(), ['none'], true);
}

/** The site passkeys belong to: APP_URL's host, never the request's Host header. */
function passkey_rp_id(): string
{
    return (string)parse_url(APP_URL, PHP_URL_HOST);
}

/** The only page address a passkey may be used from. */
function passkey_origin(): string
{
    $u = parse_url(APP_URL);
    return ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
}

function passkey_b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function passkey_b64url_decode(string $text): string
{
    $bin = base64_decode(strtr($text, '-_', '+/'), true);
    return $bin === false ? '' : $bin;
}

/** The user handle a passkey carries: which person it signs in. */
function passkey_user_handle(int $user_id): string
{
    return 'nf-user-' . $user_id;
}

/** Credential ids (base64url) of one person's passkeys. */
function passkey_ids_for(int $user_id): array
{
    $stmt = get_db()->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function passkey_list(int $user_id): array
{
    $stmt = get_db()->prepare("SELECT id, name, created_at, last_used_at FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function passkey_challenge_keep(string $purpose): void
{
    $_SESSION['passkey_challenge'] = [
        'purpose' => $purpose,
        'value'   => passkey_server()->getChallenge()->getBinaryString(),
        'at'      => time(),
    ];
}

/** The challenge made for this purpose in the last 5 minutes, once. */
function passkey_challenge_take(string $purpose): ?string
{
    $c = $_SESSION['passkey_challenge'] ?? null;
    unset($_SESSION['passkey_challenge']);
    if (!is_array($c) || $c['purpose'] !== $purpose || time() - (int)$c['at'] > 300) {
        return null;
    }
    return $c['value'];
}

/** What the phone needs to make a passkey for this person. */
function passkey_create_options(array $user): stdClass
{
    $exclude = array_map('passkey_b64url_decode', passkey_ids_for((int)$user['id']));
    $args = passkey_server()->getCreateArgs(
        passkey_user_handle((int)$user['id']),
        $user['email'],
        $user['full_name'],
        PASSKEY_TIMEOUT,
        'required',     // kept on the phone, so sign-in needs no email
        'required',     // the fingerprint, face or PIN, not just a tap
        null,
        $exclude        // one passkey per person per phone
    );
    passkey_challenge_keep('create');
    return $args;
}

/** What the phone needs to sign in ($user_id null: anyone's passkey) or unlock. */
function passkey_get_options(?int $user_id, string $purpose): stdClass
{
    $ids = $user_id ? array_map('passkey_b64url_decode', passkey_ids_for($user_id)) : [];
    $args = passkey_server()->getGetArgs($ids, PASSKEY_TIMEOUT, true, true, true, true, true, 'required');
    passkey_challenge_keep($purpose);
    return $args;
}

function passkey_check_origin(string $client_data_json): void
{
    $c = json_decode($client_data_json, true);
    if (!is_array($c) || ($c['origin'] ?? '') !== passkey_origin() || !empty($c['crossOrigin'])) {
        throw new RuntimeException('This passkey was used from another site.');
    }
}

/**
 * Check a new passkey and save it for this person.
 * @return array{id:int, name:string}
 */
function passkey_register(array $user, array $in): array
{
    $challenge = passkey_challenge_take('create');
    if ($challenge === null) {
        throw new RuntimeException('That took too long. Try again.');
    }
    $client = passkey_b64url_decode((string)($in['clientDataJSON'] ?? ''));
    passkey_check_origin($client);
    $data = passkey_server()->processCreate($client, passkey_b64url_decode((string)($in['attestationObject'] ?? '')), $challenge, true, true, false);

    $credential_id = passkey_b64url_encode($data->credentialId);
    if ($credential_id === '' || strlen($credential_id) > 512) {
        throw new RuntimeException('This passkey cannot be used with Nfelia.');
    }
    $transports = array_values(array_intersect((array)($in['transports'] ?? []), ['usb', 'nfc', 'ble', 'hybrid', 'internal', 'smart-card']));
    $name = passkey_device_name((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    get_db()->prepare("
        INSERT INTO user_passkeys (user_id, credential_id, public_key, sign_count, name, transports)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([(int)$user['id'], $credential_id, $data->credentialPublicKey, (int)$data->signatureCounter, $name, implode(',', $transports) ?: null]);

    return ['id' => (int)get_db()->lastInsertId(), 'name' => $name];
}

/**
 * Check a passkey signature. $user_id is the person who must own the passkey
 * (unlocking), or null on the sign-in page. Returns the passkey's row.
 */
function passkey_verify(array $in, string $purpose, ?int $user_id): array
{
    $challenge = passkey_challenge_take($purpose);
    if ($challenge === null) {
        throw new RuntimeException('That took too long. Try again.');
    }
    $stmt = get_db()->prepare("SELECT * FROM user_passkeys WHERE credential_id = ?");
    $stmt->execute([(string)($in['id'] ?? '')]);
    $row = $stmt->fetch();
    if (!$row || ($user_id !== null && (int)$row['user_id'] !== $user_id)) {
        throw new RuntimeException('This passkey is not set up for this account.');
    }
    $handle = (string)($in['userHandle'] ?? '');
    if ($handle !== '' && passkey_b64url_decode($handle) !== passkey_user_handle((int)$row['user_id'])) {
        throw new RuntimeException('This passkey is not set up for this account.');
    }

    $client = passkey_b64url_decode((string)($in['clientDataJSON'] ?? ''));
    passkey_check_origin($client);
    $server = passkey_server();
    $server->processGet(
        $client,
        passkey_b64url_decode((string)($in['authenticatorData'] ?? '')),
        passkey_b64url_decode((string)($in['signature'] ?? '')),
        $row['public_key'],
        $challenge,
        (int)$row['sign_count'],
        true,
        true
    );
    get_db()->prepare("UPDATE user_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?")
        ->execute([$server->getSignatureCounter() ?? (int)$row['sign_count'], $row['id']]);
    return $row;
}

/** A name a person recognises in Settings: "iPhone", "Android phone". */
function passkey_device_name(string $ua): string
{
    return match (true) {
        (bool)preg_match('/iPhone/i', $ua)            => 'iPhone',
        (bool)preg_match('/iPad/i', $ua)              => 'iPad',
        (bool)preg_match('/Android/i', $ua)           => 'Android phone',
        (bool)preg_match('/Macintosh|Mac OS X/i', $ua) => 'Mac',
        (bool)preg_match('/Windows/i', $ua)           => 'Windows computer',
        (bool)preg_match('/CrOS/i', $ua)              => 'Chromebook',
        (bool)preg_match('/Linux/i', $ua)             => 'Linux computer',
        default                                       => 'This device',
    };
}
