<?php
/**
 * Sign-in hardening, against the running app: wrong passwords are counted
 * and paused (per account and per connection), the owner is told, new
 * passwords must meet the rules, changing a password signs out every other
 * browser and kept device, "keep me signed in" tokens are stored as
 * hashes, and a profile or business change made on one device (a name, a
 * photo, the logo, the country) shows on the others at their next page
 * (app/helpers/auth.php, app/helpers/signin.php,
 * app/controllers/AuthController.php, app/middleware/auth_check.php,
 * app/controllers/SettingsController.php, views/layouts/app.php).
 *   bash tests/notifications/run.sh auth/signin
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();

const ADA = 20, KOLA = 30;
const ADA_EMAIL = 'ada@adashair.test', KOLA_EMAIL = 'kola@kolaprints.test';
const REMEMBER = '__Host-nfelia_remember';

function fresh_counts(): void { exec_sql("DELETE FROM auth_throttle"); }
function counted(string $bucket): int { return (int)q("SELECT COUNT(*) FROM auth_throttle WHERE bucket = ?", [$bucket]); }
function signed_in(Http $h): bool { $h->get('dashboard'); return $h->status === 200; }
function paused(Http $h): bool { return str_contains($h->body, 'Too many sign-in attempts'); }
/** The browser's cookie jar, as curl keeps it. */
function jar_cookie(Http $h, string $name): string
{
    $jar = (fn() => $this->jar)->call($h);
    foreach (file($jar) ?: [] as $line) {
        $f = explode("\t", trim($line));
        if (count($f) >= 7 && $f[5] === $name) return $f[6];
    }
    return '';
}
function restore_password(int $user_id): void
{
    exec_sql("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash(T_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]), $user_id]);
}

/* ══ The rules, on their own ═══════════════════════════════════ */
section('What a new password needs');
eq(password_problem('Strong#Pass9'), null, 'nine characters, a capital, a number and a symbol pass');
eq(password_problem('Ọmọ#Dára9!'), null, 'letters from any script count');
ok(str_contains((string)password_problem('strong#pass9'), 'capital'), 'no capital: says so');
ok(str_contains((string)password_problem('Strong#Pass'), 'number'), 'no number: says so');
ok(str_contains((string)password_problem('StrongPass9'), 'symbol'), 'no symbol: says so');
ok(str_contains((string)password_problem('St#9'), '9 characters'), 'too short: says so');
ok(str_contains((string)password_problem('Strong#Pass9', 'Strong#Pass8'), 'not the same'), 'the two must match');
eq(password_problem(str_repeat('Aa1#', 60)), 'Use at most 200 characters.', 'and not absurdly long (bcrypt reads 72 bytes)');
eq(password_pwned('password'), null, 'the breach check is off on a test copy (PASSWORD_BREACH_CHECK=off): no call, no verdict');
eq(array_keys(password_rules()), ['length', 'upper', 'number', 'symbol'], 'the checklist lists the four rules in order');

/* ══ Counting wrong passwords ══════════════════════════════════ */
section('Wrong passwords are counted; the right one is not, and forgets them');
fresh_counts();
$h = new Http();
$h->get('login');
foreach ([1, 2, 3] as $i) {
    $h->post('login', ['email' => ADA_EMAIL, 'password' => 'wrong-' . $i])->follow();
    ok(str_contains($h->body, 'Invalid email or password'), "wrong try $i: the usual message");
}
eq(counted('login_email'), 3, 'three wrong tries counted against the account');
eq(counted('login_ip'), 3, 'and against the connection');
$h->post('login', ['email' => ADA_EMAIL, 'password' => T_PASSWORD])->follow();
ok(signed_in($h), 'the right password signs in');
eq(counted('login_email'), 0, 'and the account\'s count starts again');
$h->post('logout');

section('Ten wrong passwords pause the account for 15 minutes, and the owner is told');
fresh_counts();
clean_slate();
$g = new Http();
$g->get('login');
for ($i = 1; $i <= 10; $i++) {
    $g->post('login', ['email' => ADA_EMAIL, 'password' => 'guess-' . $i]);
}
$g->post('login', ['email' => ADA_EMAIL, 'password' => T_PASSWORD])->follow();
ok(paused($g), 'the right password is refused with "Too many sign-in attempts"');
ok(!signed_in($g), 'and nobody is signed in');
eq(count(bell(ADA, 'security.signin_blocked')), 1, 'Ada has a security alert in her bell');
$alert = bell(ADA, 'security.signin_blocked')[0];
ok(str_contains($alert['title'], '10 times'), 'it says how many tries: ' . $alert['title']);
$g->post('login', ['email' => ADA_EMAIL, 'password' => 'guess-11']);
eq(count(bell(ADA, 'security.signin_blocked')), 1, 'more guesses in the same hour do not repeat the alert');
$g->post('login', ['email' => 'ADA@adashair.test', 'password' => T_PASSWORD])->follow();
ok(paused($g), 'a different spelling of the address is the same account');
$k = new Http();
$k->get('login');
$k->post('login', ['email' => KOLA_EMAIL, 'password' => T_PASSWORD])->follow();
ok(signed_in($k), 'another account from the same connection signs in as usual');
$k->post('logout');
exec_sql("UPDATE auth_throttle SET created_at = created_at - INTERVAL 16 MINUTE");
$g->post('login', ['email' => ADA_EMAIL, 'password' => T_PASSWORD])->follow();
ok(signed_in($g), 'sixteen minutes later, the right password works again');
$g->post('logout');

section('Fifty wrong passwords from one connection pause it for every account');
fresh_counts();
$s = new Http();
$s->get('login');
for ($i = 1; $i <= 50; $i++) {
    $s->post('login', ['email' => "nobody{$i}@example.test", 'password' => 'guess']);
}
eq(counted('login_ip'), 50, 'fifty counted against the connection');
$s->post('login', ['email' => KOLA_EMAIL, 'password' => T_PASSWORD])->follow();
ok(paused($s), 'even the right password for a real account waits');
ok(!signed_in($s), 'nobody is signed in');
fresh_counts();

/* ══ New passwords ═════════════════════════════════════════════ */
section('Signing up and adding staff hold new passwords to the rules');
$r = new Http();
$r->get('register');
$r->post('register', ['business_name' => 'Weak Co', 'full_name' => 'Wale Weak', 'email' => 'wale@weak.test',
                      'password' => 'weakpass', 'password_confirm' => 'weakpass'])->follow();
ok(str_contains($r->body, 'Your password needs'), 'a weak password is refused with what it lacks');
eq((int)q("SELECT COUNT(*) FROM users WHERE email = 'wale@weak.test'"), 0, 'and no account is made');
$r->post('register', ['business_name' => 'Strong Co', 'full_name' => 'Wale Strong', 'email' => 'wale@strong.test',
                      'password' => 'Strong#Pass9', 'password_confirm' => 'Strong#Pass9'])->follow();
eq((int)q("SELECT COUNT(*) FROM users WHERE email = 'wale@strong.test'"), 1, 'a strong one makes the account');

$a = signin(ADA_EMAIL);
$a->get('settings?tab=staff');
$a->post('settings/add_staff', ['staff_name' => 'Efe New', 'staff_email' => 'efe@adashair.test', 'staff_password' => 'welcome123', 'staff_role' => 'staff'])->follow();
eq((int)q("SELECT COUNT(*) FROM users WHERE email = 'efe@adashair.test'"), 0, 'a staff password without a capital or symbol is refused');
$a->post('settings/add_staff', ['staff_name' => 'Efe New', 'staff_email' => 'efe@adashair.test', 'staff_password' => 'Welcome#123', 'staff_role' => 'staff'])->follow();
eq((int)q("SELECT COUNT(*) FROM users WHERE email = 'efe@adashair.test'"), 1, 'one that meets them is saved');

/* ══ Changing the password ends every other session ════════════ */
section('Changing the password signs out every other browser and kept device');
$one = signin(ADA_EMAIL);
$two = signin(ADA_EMAIL);
$kept = new Http();
$kept->get('login');
$kept->post('login', ['email' => ADA_EMAIL, 'password' => T_PASSWORD, 'remember_me' => 1])->follow();
ok(signed_in($one) && signed_in($two) && signed_in($kept), 'three browsers are in, one of them kept');
eq((int)q("SELECT COUNT(*) FROM sessions WHERE user_id = ?", [ADA]), 1, 'one kept device on record');
$one->get('settings?tab=profile');
$one->post('settings/change_password', ['current_password' => T_PASSWORD, 'new_password' => 'Changed#Pass9', 'confirm_password' => 'Changed#Pass9'])->follow();
ok(str_contains($one->body, 'Password changed'), 'the password is changed');
ok(signed_in($one), 'the browser that changed it stays in');
ok(!signed_in($two), 'the other browser is signed out on its next page');
eq((int)q("SELECT COUNT(*) FROM sessions WHERE user_id = ?", [ADA]), 0, 'the kept device is forgotten');
$kept->close_browser();
ok(!signed_in($kept), 'and cannot come back from its cookie');
eq((int)q("SELECT session_epoch FROM users WHERE id = ?", [ADA]), 1, 'the account\'s session epoch moved on');
$one->post('logout');
restore_password(ADA);

section('A password reset from the emailed link ends every session too');
$three = signin(ADA_EMAIL);
ok(signed_in($three), 'signed in before the reset');
$token = account_token_issue(ADA, 'reset', 60);
$f = new Http();
$f->get('reset-password/' . $token);
$f->post('reset-password', ['token' => $token, 'password' => 'Reset#Pass9', 'password_confirm' => 'Reset#Pass9'])->follow();
ok(!signed_in($three), 'the browser that was signed in is out');
eq((int)q("SELECT session_epoch FROM users WHERE id = ?", [ADA]), 2, 'the epoch moved on again');
$f->post('reset-password', ['token' => $token, 'password' => 'Reset#Pass9', 'password_confirm' => 'Reset#Pass9'])->follow();
ok(str_contains($f->body, 'invalid or expired'), 'the link works once');
restore_password(ADA);

/* ══ Keep me signed in ═════════════════════════════════════════ */
section('"Keep me signed in" tokens are kept as hashes');
exec_sql("DELETE FROM sessions WHERE user_id = ?", [ADA]);
$m = new Http();
$m->get('login');
$m->post('login', ['email' => ADA_EMAIL, 'password' => T_PASSWORD, 'remember_me' => 1])->follow();
$cookie = jar_cookie($m, REMEMBER);
eq(strlen($cookie), 128, 'the browser holds a 128-character token');
$row = rows("SELECT session_token, ip_address FROM sessions WHERE user_id = ?", [ADA])[0] ?? [];
eq($row['session_token'] ?? '', hash('sha256', $cookie), 'the database holds only its SHA-256');
eq($row['ip_address'] ?? '', '127.0.0.1', 'with the visitor\'s own address');
$m->close_browser();
ok(signed_in($m), 'the kept device comes back after the browser was closed');

section('A token saved before hashing still works, and is rewritten');
exec_sql("UPDATE sessions SET session_token = ? WHERE user_id = ?", [$cookie, ADA]);
$m->close_browser();
ok(signed_in($m), 'the old raw token is accepted');
eq(q("SELECT session_token FROM sessions WHERE user_id = ?", [ADA]), hash('sha256', $cookie), 'and replaced by its hash at once');

section('Signing out forgets the kept device');
$m->get('dashboard');
$m->post('logout');
eq((int)q("SELECT COUNT(*) FROM sessions WHERE user_id = ?", [ADA]), 0, 'the row is gone');
$m->close_browser();
ok(!signed_in($m), 'and the cookie no longer signs in');

/* ══ Two devices ═══════════════════════════════════════════════ */
const PHOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC';

section('A new name and photo saved on the phone show on the computer at its next page');
$computer = signin(ADA_EMAIL);
$phone    = signin(ADA_EMAIL);
$computer->get('dashboard');
ok(str_contains($computer->body, '<b>Ada Obi</b>'), 'the computer shows her name as it was');
$phone->get('settings?tab=profile');
$phone->post('settings/update_profile', ['full_name' => 'Ada Obi-Nwosu', 'email' => ADA_EMAIL, 'avatar_base64' => PHOTO])->follow();
ok(str_contains($phone->body, 'Profile updated'), 'the phone saves them');
$photo = (string)q("SELECT avatar_path FROM users WHERE id = ?", [ADA]);
$computer->get('dashboard');
ok(str_contains($computer->body, '<b>Ada Obi-Nwosu</b>'), 'the computer shows the new name, without signing in again');
ok($photo !== '' && str_contains($computer->body, $photo), 'and the new photo');
$computer->get('settings?tab=profile');
ok(str_contains($computer->body, 'value="Ada Obi-Nwosu"'), 'its profile form holds the new name, so saving there cannot put the old one back');

section('A photo removed on the phone goes from the computer too');
$phone->post('settings/update_profile', ['full_name' => 'Ada Obi-Nwosu', 'email' => ADA_EMAIL, 'remove_avatar' => 1])->follow();
$computer->get('dashboard');
ok(!str_contains($computer->body, $photo), 'the computer no longer shows it');
@unlink(BASE_PATH . '/' . $photo);

section('A change to the business made on the phone shows on every computer, the team\'s too');
$chidi = signin('chidi@adashair.test');
$phone->get('settings?tab=business');
$phone->post('settings/update_business', ['business_name' => 'Ada Beauty Studio', 'phone' => '08035550100', 'email' => 'hello@adashair.test',
                                          'primary_color' => '#00764A', 'secondary_color' => '#B69F5E', 'currency_symbol' => '₦'])->follow();
ok(str_contains($phone->body, 'Business profile updated'), 'the phone saves a new business name and colour');
$computer->get('dashboard');
ok(str_contains($computer->body, '<b>Ada Beauty Studio</b>'), 'Ada\'s computer shows the new name');
ok(str_contains($computer->body, '--accent: ' . theme_tokens('#00764A', null)['accent'] . ';'), 'in the new colour');
$chidi->get('dashboard');
ok(str_contains($chidi->body, '<b>Ada Beauty Studio</b>'), 'and so does Chidi\'s, who was already signed in');

section('What the business sells can change after onboarding, and names its deeds');
$type = fn() => (string)q("SELECT business_type FROM businesses WHERE id = 2");
$was  = $type();
$phone->get('settings?tab=business');
ok(str_contains($phone->body, 'name="business_type"') && str_contains($phone->body, 'value="' . $was . '" selected'), 'Settings shows what it was set to at onboarding');
$sent = ['business_name' => 'Ada Beauty Studio', 'phone' => '08035550100', 'email' => 'hello@adashair.test',
         'primary_color' => '#00764A', 'secondary_color' => '#B69F5E', 'currency_symbol' => '₦'];
$phone->post('settings/update_business', $sent + ['business_type' => 'service'])->follow();
ok($type() === 'service' && doc_label(get_db(), 'deed', 2) === 'Certificate of completion', 'set to services: its deeds are certificates of completion');
$phone->post('settings/update_business', $sent + ['business_type' => 'mixed'])->follow();
ok($type() === 'mixed' && doc_label(get_db(), 'deed', 2) !== 'Certificate of completion', 'products and services: deeds of acquisition again');
$phone->post('settings/update_business', $sent + ['business_type' => 'spaceships'])->follow();
$phone->post('settings/update_business', $sent)->follow();
ok($type() === 'mixed', 'something else, or nothing sent, leaves it as it was');
exec_sql("UPDATE businesses SET business_type = ? WHERE id = 2", [$was]);

section('The category can change in Settings, and the shop keeps its look');
$cat  = fn() => (string)q("SELECT category FROM businesses WHERE id = 2");
$look = fn() => storefront_default_look(rows("SELECT category, storefront_look FROM businesses WHERE id = 2")[0]);
exec_sql("UPDATE businesses SET category = 'fashion', storefront_look = NULL WHERE id = 2");   // a shop still on the look its category suggests
$phone->get('settings?tab=business');
ok(str_contains($phone->body, 'name="category"') && str_contains($phone->body, 'value="fashion" selected'), 'Settings shows the category chosen at onboarding');
eq($look(), 'atelier', 'a fashion shop that never chose a look shows Atelier');
$phone->post('settings/update_business', $sent + ['category' => 'beauty'])->follow();
eq($cat(), 'beauty', 'Ada makes it Hair & Beauty');
eq($look(), 'atelier', 'and her shop keeps Atelier: Shop Studio decides the look');
$phone->post('settings/update_business', $sent + ['category' => 'spaceships'])->follow();
$phone->post('settings/update_business', $sent)->follow();
eq($cat(), 'beauty', 'something else, or nothing sent, leaves it as it was');
exec_sql("UPDATE businesses SET category = 'other', storefront_look = 'glow' WHERE id = 2");

section('The currency follows the country: Settings shows it and never takes one from the form');
$sym = fn() => (string)q("SELECT currency_symbol FROM businesses WHERE id = 2");
$phone->get('settings?tab=business');
ok(!str_contains($phone->body, 'name="currency_symbol"') && str_contains($phone->body, 'From your country, Nigeria.'), 'Settings shows the naira, from Nigeria, with nothing to pick');
$phone->post('settings/update_business', ['currency_symbol' => 'FCFA'] + $sent)->follow();
eq($sym(), '₦', 'a currency sent with the form is ignored');
exec_sql("UPDATE businesses SET currency_symbol = 'GH₵' WHERE id = 2");   // set apart from its country, as the old currency list allowed
$phone->post('settings/update_business', $sent)->follow();
eq($sym(), '₦', 'and one that had drifted from the country comes back to it at the next save');

section('A form too big for the server says so, not "Security session expired" (csrf_verify)');
// The server empties a form over post_max_size, token and all: csrf_verify is
// run in its own PHP with a 1 MB limit, as it would see such a form
$probe = sys_get_temp_dir() . '/nfelia_csrf_probe_' . getmypid() . '.php';
file_put_contents($probe, '<?php require ' . var_export(BASE_PATH . '/tests/bootstrap.php', true) . ';
$_SESSION = ["csrf_token" => "t"];
$_POST = [];
$_SERVER = ["REQUEST_METHOD" => "POST", "CONTENT_LENGTH" => $argv[1], "HTTP_ACCEPT" => $argv[2]] + $_SERVER;
register_shutdown_function(function () { echo "|", $_SESSION["flash"]["message"] ?? ""; });
csrf_verify();');
$verify = fn(int $bytes, string $accept = 'text/html') => (string)shell_exec(implode(' ', array_map('escapeshellarg',
    [PHP_BINARY, '-d', 'post_max_size=1M', '-d', 'upload_max_filesize=1M', $probe, (string)$bytes, $accept])) . ' 2>&1');
$too_big = 'Nothing was saved: that file is too big (the most is 1 MB). Pick a smaller one and try again.';
eq($verify(2 * 1048576), '|' . $too_big, 'a page is told the file was too big (and goes back to it: back_path)');
eq($verify(2 * 1048576, 'application/json'), json_encode(['error' => $too_big]) . '|', 'a script is told the same');
eq($verify(300), '|Security session expired. Please try again.', 'a small form without its token is still refused as before');
@unlink($probe);

section('A redrawn auto-logo gets a name of its own, so no browser keeps showing the old one');
$logo = fn() => (string)q("SELECT logo_path FROM businesses WHERE id = 2");
$drawn = fn(string $rel) => (string)@file_get_contents(BASE_PATH . '/' . $rel);
$chidi->post('settings/generate-logo')->follow();
eq($logo(), '', 'staff cannot make one: the page offers it to the owner only');
$phone->post('settings/generate-logo')->follow();
$first = $logo();
ok(preg_match('#^uploads/logos/2/logo_autogen_\d+_[a-f0-9]{8}\.svg$#', $first) === 1, 'the owner makes one: ' . $first);
ok(str_contains($drawn($first), '>AB</text>'), 'from the initials of Ada Beauty Studio');
$computer->get('dashboard');
ok(str_contains($computer->body, $first), 'and the computer shows it');
$phone->post('settings/update_business', ['business_name' => 'Kemi Styles', 'phone' => '08035550100', 'email' => 'hello@adashair.test',
                                          'primary_color' => '#00764A', 'secondary_color' => '#B69F5E', 'currency_symbol' => '₦', 'remove_logo' => 1])->follow();
ok(!is_file(BASE_PATH . '/' . $first), 'removed with a rename, its file goes');
$phone->post('settings/generate-logo')->follow();
$second = $logo();
ok($second !== '' && $second !== $first, 'drawn again, it is saved under a new name');
ok(str_contains($drawn($second), '>KS</text>'), 'with the new initials');
$computer->get('dashboard');
ok(str_contains($computer->body, $second) && !str_contains($computer->body, $first), 'which the computer shows instead');
$phone->post('settings/generate-logo')->follow();
$third = $logo();
ok($third !== $second && !is_file(BASE_PATH . '/' . $second), 'drawn over a logo, the one it replaces goes too');
@unlink(BASE_PATH . '/' . $third);

section('A country picked on the phone shows on the computer at its next page');
$phone->get('dashboard');
$phone->post('api/set-country', ['country_id' => 2]);
eq($phone->data()['success'] ?? null, true, 'the phone picks Ghana');
$computer->get('more');
ok(str_contains($computer->body, '🇬🇭 GH₵'), 'the computer shows Ghana and the cedi');
ok(str_contains($computer->body, 'setCountry(2)" @click="countryOpen = false" aria-current="true"'), 'and ticks Ghana in its country menu');
$fresh = signin(ADA_EMAIL);
$fresh->get('more');
ok(str_contains($fresh->body, '🇬🇭 GH₵'), 'so does a browser signed in afresh, with no country cookie of its own');

done();
