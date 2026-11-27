<?php
/**
 * Bot guard for the public forms that make an account or send an email to
 * whatever address is typed in: sign-up, "Forgot password" and "Email me a
 * sign-in link".
 *
 * On 26 Nov 2026 a bot made 23 accounts called "Test Company", each with a
 * stranger's email address, so each stranger got Nfelia's welcome email
 * ("subscription bombing": a person's inbox flooded from many sites' sign-up
 * forms). Strangers who mark that mail as spam hurt the delivery of every
 * shop's invoices and receipts. Three checks, cheapest first:
 *
 *   1. A field people never see (company_website). A bot that fills in
 *      every field fills that one too.
 *   2. Time. The form must come back at least FORM_MIN_SECONDS (3 by
 *      default) after it was shown; the session remembers when.
 *   3. Cloudflare Turnstile, once TURNSTILE_SECRET_KEY is in .env. The site
 *      key is public and kept here; the secret only ever in .env.
 *
 * Cloudflare also challenges these three pages before they load (security
 * rule "Sign-up and email-sending pages: check for bots", 27 Nov 2026).
 */

const BOT_GUARD_SORRY = 'We could not confirm this came from a person. Please wait a moment and try again.';

/** Cloudflare Turnstile widget "Nfelia sign-up and account forms" (nfelia.com). Public. */
const TURNSTILE_SITE_KEY_DEFAULT = '0x4AAAAAAGU4us5mWBtAdRDW';

function turnstile_site_key(): string
{
    return trim((string)($_ENV['TURNSTILE_SITE_KEY'] ?? '')) ?: TURNSTILE_SITE_KEY_DEFAULT;
}

function turnstile_secret(): string
{
    return trim((string)($_ENV['TURNSTILE_SECRET_KEY'] ?? ''));
}

/** Turnstile runs only with its secret in .env: without it nobody could be checked. */
function turnstile_enabled(): bool
{
    return turnstile_secret() !== '';
}

/** The least time, in seconds, a person takes to fill in a guarded form. */
function form_min_seconds(): int
{
    $v = $_ENV['FORM_MIN_SECONDS'] ?? '';
    return $v === '' ? 3 : max(0, (int)$v);
}

/**
 * The hidden parts of a guarded form, echoed inside its <form> just before
 * the button. Notes when the form was shown.
 */
function bot_guard_fields(string $form): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['form_shown'][$form] = time();
    }
    // Off the screen, not display:none, which some bots skip. Out of the tab
    // order and hidden from screen readers, so no person meets it.
    $html = '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">'
          . '<label>Company website <input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>'
          . '</div>';
    if (turnstile_enabled()) {
        // Two guarded forms on one page (sign-up and its feedback dialog) load the script once
        static $script = false;
        $html .= '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars(turnstile_site_key(), ENT_QUOTES, 'UTF-8') . '"'
               . ' data-action="' . htmlspecialchars($form, ENT_QUOTES, 'UTF-8') . '" data-size="flexible" data-theme="auto" style="margin:12px 0"></div>'
               . ($script ? '' : '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>');
        $script = true;
    }
    return $html;
}

/**
 * Null when the form may go on; otherwise what to tell the person.
 * $post and $session default to the request's own (passed in by the tests).
 */
function bot_guard_check(string $form, ?array $post = null, ?array $session = null, ?int $now = null): ?string
{
    $post    ??= $_POST;
    $session ??= $_SESSION ?? [];
    $now     ??= time();

    if (trim((string)($post['company_website'] ?? '')) !== '') {
        return BOT_GUARD_SORRY;
    }
    // No time noted (the session was lost): the form's own token fails then too.
    $shown = (int)($session['form_shown'][$form] ?? 0);
    if ($shown > 0 && $now - $shown < form_min_seconds()) {
        return BOT_GUARD_SORRY;
    }
    if (turnstile_enabled()) {
        $token = trim((string)($post['cf-turnstile-response'] ?? ''));
        if ($token === '') {
            return 'Please let the security check under the form finish (tick it if it asks), then try again.';
        }
        if (turnstile_verify($token, $form) === false) {
            return BOT_GUARD_SORRY;
        }
        // null: Cloudflare could not be reached. A person is never kept out
        // by Cloudflare being down; the other two checks still held.
    }
    return null;
}

/**
 * Cloudflare's answer for a Turnstile token: true (a person), false (not,
 * or the token was for another form), null (no answer: network, outage).
 */
function turnstile_verify(string $token, string $action): ?bool
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => turnstile_secret(),
            'response' => mb_substr($token, 0, 2048),
            'remoteip' => function_exists('client_ip') ? client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (function_exists('errors_waited')) {
        errors_waited((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME));
    }
    curl_close($ch);

    $answer = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($answer)) {
        error_log('[Nfelia] Turnstile could not be reached (HTTP ' . $status . '); the form was let through on its other checks');
        return null;
    }
    if (empty($answer['success'])) {
        return false;
    }
    return empty($answer['action']) || $answer['action'] === $action;
}
