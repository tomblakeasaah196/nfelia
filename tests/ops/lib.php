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
