<?php
/**
 * Passkeys for Ops: the same library and checks as the app's
 * (app/helpers/passkeys.php), for a different site. They belong to
 * ops.nfelia.com, are kept in ops_passkeys, and carry "nf-ops-" user
 * handles, so a passkey for Ops can never open a business and the other
 * way round.
 */

use lbuchs\WebAuthn\WebAuthn;

function ops_passkey_server(): WebAuthn
{
    static $server = null;
    if ($server) {
        return $server;
    }
    if (!ops_passkey_plain_http()) {
        return $server = new WebAuthn('Nfelia Ops', ops_host(), ['none'], true);
    }
    // A local or test copy over plain http (ops.localhost): the library takes
    // http only when the site is called "localhost", and the browser insists
    // on "ops.localhost". So it is built for "localhost" and given the
    // fingerprint of ops.localhost to compare with; ops_passkey_check_origin()
    // still demands the exact page address. Never the case on ops.nfelia.com.
    $server = new WebAuthn('Nfelia Ops', 'localhost', ['none'], true);
    (new ReflectionProperty(WebAuthn::class, '_rpIdHash'))->setValue($server, hash('sha256', ops_host(), true));
    return $server;
}

/** The site Ops passkeys belong to, as the browser sees it: ops.nfelia.com. */
function ops_passkey_rp_id(): string
{
    return ops_host();
}

/** A local or test copy served over plain http, never the live site. */
function ops_passkey_plain_http(): bool
{
    return in_array(APP_ENV, ['local', 'testing'], true)
        && parse_url(APP_URL, PHP_URL_SCHEME) === 'http'
        && parse_url(APP_URL, PHP_URL_HOST) === 'localhost';
}

function ops_passkey_handle(int $staff_id): string
{
    return 'nf-ops-' . $staff_id;
}

function ops_passkey_ids(int $staff_id): array
{
    $stmt = get_db()->prepare("SELECT credential_id FROM ops_passkeys WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function ops_passkey_list(int $staff_id): array
{
    $stmt = get_db()->prepare("SELECT id, name, created_at, last_used_at FROM ops_passkeys WHERE staff_id = ? ORDER BY created_at DESC");
    $stmt->execute([$staff_id]);
    return $stmt->fetchAll();
}

function ops_passkey_challenge_keep(string $purpose): void
{
    $_SESSION['ops_passkey'] = ['purpose' => $purpose, 'value' => ops_passkey_server()->getChallenge()->getBinaryString(), 'at' => time()];
}

function ops_passkey_challenge_take(string $purpose): ?string
{
    $c = $_SESSION['ops_passkey'] ?? null;
    unset($_SESSION['ops_passkey']);
    if (!is_array($c) || $c['purpose'] !== $purpose || time() - (int)$c['at'] > 300) {
        return null;
    }
    return $c['value'];
}

function ops_passkey_create_options(array $staff): stdClass
{
    $exclude = array_map('passkey_b64url_decode', ops_passkey_ids((int)$staff['id']));
    $args = ops_passkey_server()->getCreateArgs(
        ops_passkey_handle((int)$staff['id']), $staff['email'], $staff['full_name'],
        PASSKEY_TIMEOUT, 'required', 'required', null, $exclude
    );
    $args->publicKey->rp->id = ops_passkey_rp_id();
    ops_passkey_challenge_keep('create');
    return $args;
}

function ops_passkey_get_options(): stdClass
{
    $args = ops_passkey_server()->getGetArgs([], PASSKEY_TIMEOUT, true, true, true, true, true, 'required');
    $args->publicKey->rpId = ops_passkey_rp_id();
    ops_passkey_challenge_keep('signin');
    return $args;
}

function ops_passkey_check_origin(string $client_data_json): void
{
    $c = json_decode($client_data_json, true);
    if (!is_array($c) || ($c['origin'] ?? '') !== ops_origin() || !empty($c['crossOrigin'])) {
        throw new RuntimeException('This passkey was used from another site.');
    }
}

/** @return array{id:int, name:string} */
function ops_passkey_register(array $staff, array $in): array
{
    $challenge = ops_passkey_challenge_take('create');
    if ($challenge === null) {
        throw new RuntimeException('That took too long. Try again.');
    }
    $client = passkey_b64url_decode((string)($in['clientDataJSON'] ?? ''));
    ops_passkey_check_origin($client);
    $data = ops_passkey_server()->processCreate($client, passkey_b64url_decode((string)($in['attestationObject'] ?? '')), $challenge, true, true, false);

    $credential_id = passkey_b64url_encode($data->credentialId);
    if ($credential_id === '' || strlen($credential_id) > 512) {
        throw new RuntimeException('This passkey cannot be used with Nfelia Ops.');
    }
    $transports = array_values(array_intersect((array)($in['transports'] ?? []), ['usb', 'nfc', 'ble', 'hybrid', 'internal', 'smart-card']));
    $name = passkey_device_name((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    get_db()->prepare("INSERT INTO ops_passkeys (staff_id, credential_id, public_key, sign_count, name, transports) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([(int)$staff['id'], $credential_id, $data->credentialPublicKey, (int)$data->signatureCounter, $name, implode(',', $transports) ?: null]);
    return ['id' => (int)get_db()->lastInsertId(), 'name' => $name];
}

/** Check a sign-in with a passkey. Returns the person it belongs to. */
function ops_passkey_verify(array $in): array
{
    $challenge = ops_passkey_challenge_take('signin');
    if ($challenge === null) {
        throw new RuntimeException('That took too long. Try again.');
    }
    $stmt = get_db()->prepare("SELECT * FROM ops_passkeys WHERE credential_id = ?");
    $stmt->execute([(string)($in['id'] ?? '')]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('This passkey is not set up for Nfelia Ops.');
    }
    $handle = (string)($in['userHandle'] ?? '');
    if ($handle !== '' && passkey_b64url_decode($handle) !== ops_passkey_handle((int)$row['staff_id'])) {
        throw new RuntimeException('This passkey is not set up for Nfelia Ops.');
    }
    $client = passkey_b64url_decode((string)($in['clientDataJSON'] ?? ''));
    ops_passkey_check_origin($client);
    $server = ops_passkey_server();
    $server->processGet(
        $client,
        passkey_b64url_decode((string)($in['authenticatorData'] ?? '')),
        passkey_b64url_decode((string)($in['signature'] ?? '')),
        $row['public_key'], $challenge, (int)$row['sign_count'], true, true
    );
    get_db()->prepare("UPDATE ops_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?")
        ->execute([$server->getSignatureCounter() ?? (int)$row['sign_count'], $row['id']]);

    $staff = ops_staff_by_id((int)$row['staff_id']);
    if (!$staff || !(int)$staff['is_active'] || $staff['password_hash'] === null) {
        throw new RuntimeException('This account is not active in Nfelia Ops.');
    }
    return $staff;
}
