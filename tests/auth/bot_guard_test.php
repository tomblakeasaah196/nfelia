<?php
/**
 * The bot guard on sign-up and the account-link forms (app/helpers/bot_guard.php):
 * the hidden field, the time a person takes, and Turnstile being off until
 * its secret is in .env. No database, no network:
 *   php tests/auth/bot_guard_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../app/helpers/bot_guard.php';

$fails = 0;
function same(string $what, $want, $got): void {
    global $fails;
    if ($want === $got) {
        echo "  ok  {$what}\n";
        return;
    }
    $fails++;
    echo "FAIL  {$what}\n      want " . var_export($want, true) . "\n      got  " . var_export($got, true) . "\n";
}

unset($_ENV['TURNSTILE_SECRET_KEY'], $_ENV['FORM_MIN_SECONDS']);
$now     = 1_800_000_000;
$shown   = ['form_shown' => ['register' => $now - 20]];
$person  = ['full_name' => 'Ivie Okoro', 'email' => 'ivie@example.test', 'company_website' => ''];

same('a person who took 20 seconds goes on', null, bot_guard_check('register', $person, $shown, $now));
same('the hidden field filled in is a bot', BOT_GUARD_SORRY,
     bot_guard_check('register', ['company_website' => 'http://spam.test'] + $person, $shown, $now));
same('a form sent back within a second is a bot', BOT_GUARD_SORRY,
     bot_guard_check('register', $person, ['form_shown' => ['register' => $now - 1]], $now));
same('three seconds is enough', null,
     bot_guard_check('register', $person, ['form_shown' => ['register' => $now - 3]], $now));
same('each form keeps its own time', BOT_GUARD_SORRY,
     bot_guard_check('login-link', $person, ['form_shown' => ['register' => $now - 60, 'login-link' => $now]], $now));
same('no time noted (a lost session) is left to the form token', null, bot_guard_check('register', $person, [], $now));

$_ENV['FORM_MIN_SECONDS'] = '0';
same('FORM_MIN_SECONDS=0 turns the time check off (the end-to-end tests)', null,
     bot_guard_check('register', $person, ['form_shown' => ['register' => $now]], $now));
unset($_ENV['FORM_MIN_SECONDS']);

same('Turnstile is off without its secret', false, turnstile_enabled());
same('so the form carries no widget and loads no script', false, str_contains(bot_guard_fields('register'), 'challenges.cloudflare.com'));
same('the hidden field is always there', true, str_contains(bot_guard_fields('register'), 'name="company_website"'));
same('the public site key is known without .env', '0x4AAAAAAGU4us5mWBtAdRDW', turnstile_site_key());

$_ENV['TURNSTILE_SECRET_KEY'] = 'not-a-real-secret';
same('with its secret, the widget and its script are on the form', true,
     str_contains(bot_guard_fields('register'), 'class="cf-turnstile"') && str_contains(bot_guard_fields('register'), 'turnstile/v0/api.js'));
same('and the widget names the form it is on', true, str_contains(bot_guard_fields('forgot-password'), 'data-action="forgot-password"'));
same('a form sent without Turnstile\'s answer is asked to let it finish', true,
     str_starts_with((string)bot_guard_check('register', $person, $shown, $now), 'Please let the security check'));
same('the secret never appears on the page', false, str_contains(bot_guard_fields('register'), 'not-a-real-secret'));
unset($_ENV['TURNSTILE_SECRET_KEY']);

echo $fails ? "\n{$fails} failed\n" : "\nall passed\n";
exit($fails ? 1 : 0);
