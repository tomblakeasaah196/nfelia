<?php
/**
 * Nfelia Ops, the foundation: its own host, its own sign-in (password and
 * the passkey challenge), roles, invites, password resets, the team, the
 * audit log, and the one-time setup from the old admin page.
 *   bash tests/notifications/run.sh ops/foundation
 */
require __DIR__ . '/lib.php';
require_once dirname(__DIR__, 2) . '/app/ops/passkeys.php';
t_guard();
fixture_reset();
clean_slate();

/* ── Its own place ──────────────────────────────────────────── */

section('ops.<host> is its own place, never cached, framed or indexed');

$h = ops_browser();
$h->get(o());
eq($h->status, 302, 'signed out, Home asks you to sign in');
ok(str_contains($h->headers['set-cookie'] ?? '', '__Host-nfelia_ops='), 'with its own session cookie, __Host-nfelia_ops');
eq(ops_to($h), 'signin', '… on the sign-in page');
$h->get(o('signin'));
eq($h->status, 200, 'the sign-in page opens');
ok(str_contains($h->body, 'Sign in with a passkey') && str_contains($h->body, 'name="password"'), 'with a passkey button, and email and password');
eq($h->headers['x-frame-options'] ?? '', 'DENY', 'never inside a frame');
eq($h->headers['x-robots-tag'] ?? '', 'noindex, nofollow', 'never indexed');
ok(str_contains($h->headers['cache-control'] ?? '', 'no-store'), 'never cached');
$h->get(o('robots.txt'));
ok(str_contains($h->body, 'Disallow: /'), 'robots.txt keeps search engines out');
$h->get(o('no-such-page'));
eq($h->status, 404, 'an unknown page is a 404');
$h->post(o('team/1/role'), ['role' => 'sales']);
ok(in_array($h->status, [302, 401], true) && (int)q("SELECT COUNT(*) FROM ops_staff WHERE id = 1 AND role = 'admin'") === 1, 'a POST signed out changes nothing');

$app = new Http();  // the app's own address
$app->get('team');
ok($app->status !== 200 || !str_contains($app->body, 'Nfelia Ops'), 'the app\'s own address does not serve Ops pages');
ok(in_array('ops', SHOP_RESERVED_NAMES, true), '"ops" can never be a shop\'s name');

/* ── Signing in ─────────────────────────────────────────────── */

section('Email and password');

$h = ops_browser();
$h->get(o('signin'));
$h->post(o('signin'), ['email' => 'grace@nfelia.test', 'password' => 'wrong-password']);
eq(ops_to($h), 'signin', 'a wrong password goes back to sign-in');
$h->get(o('signin'));
ok(str_contains($h->body, 'don&#039;t match an active Ops account'), 'saying the details don\'t match, without saying which');
ok((bool)audit_rows('signin.failed'), 'and the failed try is in the audit log');

$h->post(o('signin'), ['email' => 'Grace@Nfelia.test ', 'password' => T_OPS_PASSWORD]);
eq(ops_to($h), '', 'the right password signs in (email in any case, with spaces) and goes to Home');
$h->get(o());
eq($h->status, 200, 'Home opens');
ok(str_contains($h->body, 'Good') && str_contains($h->body, 'Grace'), 'greeting Grace by name');
ok(str_contains($h->body, 'Add a passkey'), 'and nudging her to add a passkey');
eq((int)q("SELECT COUNT(*) FROM ops_audit WHERE action = 'signin' AND staff_id = 1"), 1, 'the sign-in is in the audit log');
ok(q("SELECT last_signin_at FROM ops_staff WHERE id = 1") !== null, 'and on her account');

$h->post(o('signout'));
eq(ops_to($h), 'signin', 'signing out goes back to sign-in');
$h->get(o());
eq($h->status, 302, 'and Home needs a sign-in again');

$x = ops_browser();
$x->get(o('signin'));
$x->post(o('signin'), ['email' => 'femi@nfelia.test', 'password' => T_OPS_PASSWORD]);
eq(ops_to($x), 'signin', 'someone taken off the team cannot sign in, even with the right password');
$x->post(o('signin'), ['email' => 'tolu@nfelia.test', 'password' => T_PASSWORD]);
eq(ops_to($x), 'signin', 'a business account (even the Nfelia business owner\'s) is not an Ops account');

section('Sign-in comes back to the page you were opening');

$h = ops_browser();
$h->get(o('audit?kind=team'));
eq(ops_to($h), 'signin', 'signed out, the audit log asks for a sign-in');
$h->get(o('signin'));
$h->post(o('signin'), ['email' => 'grace@nfelia.test', 'password' => T_OPS_PASSWORD]);
eq(ops_to($h), 'audit?kind=team', 'then opens the audit log, with its filter');
$h->get(o('signout'));
eq($h->status, 405, 'signing out is a POST, so a link cannot sign anyone out');

section('Too many tries are held back');

$t = ops_browser();
$t->get(o('signin'));
for ($i = 0; $i < 6; $i++) {
    $t->post(o('signin'), ['email' => 'sam@nfelia.test', 'password' => 'nope-' . $i]);
}
$t->post(o('signin'), ['email' => 'sam@nfelia.test', 'password' => T_OPS_PASSWORD]);
$t->get(o('signin'));
ok(str_contains($t->body, 'Too many tries'), 'after 6 wrong passwords, even the right one waits 15 minutes');
ok((bool)audit_rows('signin.throttled'), 'and the hold is in the audit log');
exec_sql("DELETE FROM auth_throttle");

section('A business session is no Ops session, and the other way round');

$both = signin('tolu@nfelia.test');
$both = $both->on(ops_hostport());
$both->get(o());
eq($both->status, 302, 'signed in to the app as the Nfelia owner, Ops still asks for a sign-in');
$grace = as_ops('grace@nfelia.test');
$in_app = $grace->on('');
$in_app->get('dashboard');
ok($in_app->status === 302 && str_contains($in_app->location, 'login'), 'signed in to Ops, the app still asks for a sign-in');

section('A passkey sign-in starts with a fresh challenge for ops.<host>');

$p = ops_browser();
$p->get(o('signin'));
$p->json(o('signin/passkey/options'));
$opt = $p->data()['options']['publicKey'] ?? [];
eq($opt['rpId'] ?? null, ops_host(), 'the passkey belongs to ' . ops_host() . ', not the app');
ok(strlen((string)($opt['challenge'] ?? '')) >= 32, 'with a random challenge');
$p->json(o('signin/passkey'), ['id' => 'not-a-passkey', 'clientDataJSON' => '', 'authenticatorData' => '', 'signature' => '']);
ok($p->status === 400 && !($p->data()['ok'] ?? true), 'a made-up passkey is refused');
$p->json(o('signin/passkey/options'), [], ['X-CSRF-Token: wrong']);
eq($p->status, 419, 'without the page\'s token, nothing is answered');

/* ── Roles ──────────────────────────────────────────────────── */

section('Each role sees its own part');

$sam  = as_ops('sam@nfelia.test');
$lola = as_ops('lola@nfelia.test');
$grace->get(o('team'));
eq($grace->status, 200, 'an admin opens Team');
$grace->get(o('audit'));
eq($grace->status, 200, 'and the audit log');
$sam->get(o('team'));
eq($sam->status, 403, 'Support cannot open Team');
ok(str_contains($sam->body, 'Not for your role'), 'and is told why');
$lola->get(o('audit'));
eq($lola->status, 403, 'Sales cannot open the audit log');
$sam->get(o());
ok(!str_contains($sam->body, '>Team<') && !str_contains($sam->body, '>Audit log<'), 'Support\'s menu has neither');
$grace->get(o());
ok(str_contains($grace->body, '>Team<') && str_contains($grace->body, '>Audit log<'), 'the admin\'s menu has both');
$sam->post(o('team/invite'), ['full_name' => 'Sneaky', 'email' => 'sneaky@example.test', 'role' => 'admin']);
eq((int)q("SELECT COUNT(*) FROM ops_staff WHERE email = 'sneaky@example.test'"), 0, 'Support cannot invite anyone, even by posting the form');

/* ── The team ───────────────────────────────────────────────── */

section('Inviting someone');

$grace->get(o('team'));
ok(str_contains($grace->body, 'Sam Support') && str_contains($grace->body, 'Off the team'), 'Team lists everyone, including who left');
$grace->post(o('team/invite'), ['full_name' => 'Tobi New', 'email' => 'Tobi@Nfelia.test', 'role' => 'sales']);
$tobi = rows("SELECT * FROM ops_staff WHERE email = 'tobi@nfelia.test'")[0] ?? null;
ok($tobi && $tobi['role'] === 'sales' && $tobi['password_hash'] === null, 'Tobi is added as Sales, with no password yet');
$link = link_in_mail('tobi@nfelia.test', '~https?://[^"\s]+/invite/[A-Za-z0-9_-]+~');
ok($link !== '' && str_starts_with($link, ops_origin() . '/invite/'), 'an invite email with a link to ' . ops_host());
eq(mails_to('tobi@nfelia.test')[0]['subject'] ?? '', 'You\'re invited to Nfelia Ops', 'titled as an invite');
ok(str_contains(mails_to('tobi@nfelia.test')[0]['text'] ?? '', 'Grace Admin added you'), 'saying who invited them');
ok((bool)audit_rows('team.invited'), 'the invite is in the audit log');
$grace->post(o('team/invite'), ['full_name' => 'Sam Again', 'email' => 'sam@nfelia.test', 'role' => 'admin']);
$grace->get(o('team'));
ok(str_contains($grace->body, 'Sam Support is already on the team'), 'inviting someone already on the team says so');

$new = ops_browser();
$new->get(o($link));
eq($new->status, 200, 'the invite opens');
ok(str_contains($new->body, 'Join Nfelia Ops') && str_contains($new->body, 'Sales'), 'showing the role they are joining as');
$new->post(o($link), ['full_name' => 'Tobi New', 'password' => 'short', 'password_again' => 'short']);
$new->get(o($link));
ok(str_contains($new->body, 'at least 10 characters'), 'a short password is refused, and the link still works');
$new->post(o($link), ['full_name' => 'Tobi Adeyemi', 'password' => 'GoodPassword1', 'password_again' => 'GoodPassword1']);
eq(ops_to($new), 'account#passkeys', 'accepting signs Tobi in and opens Passkeys');
$new->get(o('account'));
ok(str_contains($new->body, 'Tobi Adeyemi') && str_contains($new->body, 'Welcome to Nfelia Ops'), 'with the name Tobi chose, and a welcome');
ok((bool)audit_rows('team.joined'), 'joining is in the audit log');
$again = ops_browser();
$again->get(o($link));
eq($again->status, 410, 'the invite works once');

section('Changing a role, and the last admin');

$tobi_id = (int)$tobi['id'];
$grace->get(o('team'));
$grace->post(o('team/' . $tobi_id . '/role'), ['role' => 'support']);
eq((string)q("SELECT role FROM ops_staff WHERE id = ?", [$tobi_id]), 'support', 'Grace makes Tobi Support');
$new->get(o('team'));
eq($new->status, 403, 'Tobi\'s new role applies on the next page, without signing in again');
ok(str_contains((string)q("SELECT summary FROM ops_audit WHERE action = 'team.role' ORDER BY id DESC LIMIT 1"), 'Sales → Support'), 'the change is in the audit log, from and to');
$grace->post(o('team/1/role'), ['role' => 'sales']);
eq((string)q("SELECT role FROM ops_staff WHERE id = 1"), 'admin', 'nobody can change their own role');
$grace->post(o('team/2/role'), ['role' => 'admin']);
$grace->post(o('team/2/role'), ['role' => 'support']);
eq((string)q("SELECT role FROM ops_staff WHERE id = 2"), 'support', 'another admin can be made Support again');

section('Taking someone off the team, and bringing them back');

$grace->post(o('team/3/deactivate'));
eq((int)q("SELECT is_active FROM ops_staff WHERE id = 3"), 0, 'Grace takes Lola off the team');
$lola->get(o());
eq($lola->status, 302, 'Lola\'s open session ends at once');
$l2 = as_ops('lola@nfelia.test');
eq(ops_to($l2), 'signin', 'and she cannot sign in again');
$grace->post(o('team/3/reactivate'));
$l3 = as_ops('lola@nfelia.test');
eq(ops_to($l3), '', 'brought back, she signs in with her own password');
$grace->post(o('team/1/deactivate'));
eq((int)q("SELECT is_active FROM ops_staff WHERE id = 1"), 1, 'nobody can take themselves off the team');

section('A new invite replaces the old one');

$grace->post(o('team/invite'), ['full_name' => 'Ife Late', 'email' => 'ife@nfelia.test', 'role' => 'support']);
$ife = (int)q("SELECT id FROM ops_staff WHERE email = 'ife@nfelia.test'");
$first = link_in_mail('ife@nfelia.test', '~https?://[^"\s]+/invite/[A-Za-z0-9_-]+~');
$grace->post(o('team/' . $ife . '/resend'));
$second = link_in_mail('ife@nfelia.test', '~https?://[^"\s]+/invite/[A-Za-z0-9_-]+~');
ok($first !== $second && $second !== '', 'a new invite is emailed');
$i = ops_browser();
$i->get(o($first));
eq($i->status, 410, 'the old link no longer works');
$i->get(o($second));
eq($i->status, 200, 'the new one does');
exec_sql("UPDATE ops_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE staff_id = ?", [$ife]);
$i->get(o($second));
eq($i->status, 410, 'and it stops working after 72 hours');
$grace->get(o('team'));
ok(str_contains($grace->body, 'Invite expired'), 'Team shows the invite has expired');

/* ── Passwords ──────────────────────────────────────────────── */

section('A forgotten password');

clean_slate();
$f = ops_browser();
$f->get(o('forgot'));
$f->post(o('forgot'), ['email' => 'sam@nfelia.test']);
eq(ops_to($f), 'signin', 'asking for a link goes back to sign-in');
$f->post(o('forgot'), ['email' => 'nobody@example.test']);
deliver();
eq(count(mails_to('nobody@example.test')), 0, 'an email not on the team gets nothing (and the page says the same either way)');
$reset = link_in_mail('sam@nfelia.test', '~https?://[^"\s]+/reset/[A-Za-z0-9_-]+~');
ok($reset !== '', 'Sam gets a reset link');
$sam_elsewhere = as_ops('sam@nfelia.test');
$f->get(o($reset));
eq($f->status, 200, 'it opens');
$f->post(o($reset), ['password' => 'BrandNewPass9', 'password_again' => 'BrandNewPass9']);
eq(ops_to($f), '', 'choosing a new password signs Sam in');
$sam_elsewhere->get(o());
eq($sam_elsewhere->status, 302, 'and signs out every other device');
$f2 = ops_browser();
$f2->get(o($reset));
eq($f2->status, 410, 'the link works once');
eq(ops_to(as_ops('sam@nfelia.test', 'BrandNewPass9')), '', 'the new password works');

section('Changing your password, and signing out everywhere');

clean_slate();
$here  = as_ops('grace@nfelia.test');
$there = as_ops('grace@nfelia.test');
$here->get(o('account'));
$here->post(o('account/password'), ['current_password' => 'wrong', 'password' => 'AnotherPass22', 'password_again' => 'AnotherPass22']);
$here->get(o('account'));
ok(str_contains($here->body, 'current password is not right'), 'the current password must be right');
$here->post(o('account/password'), ['current_password' => T_OPS_PASSWORD, 'password' => 'AnotherPass22', 'password_again' => 'AnotherPass22']);
$here->get(o('account'));
eq($here->status, 200, 'changed: this device stays signed in');
$there->get(o());
eq($there->status, 302, 'every other device is signed out');
deliver();
ok(str_contains(mails_to('grace@nfelia.test')[0]['subject'] ?? '', 'password was changed'), 'and Grace is emailed about it');
$here->post(o('account/signout-everywhere'));
$here->get(o());
eq($here->status, 302, '"Sign out everywhere" signs out this device too');
exec_sql("UPDATE ops_staff SET password_hash = ? WHERE id = 1", [password_hash(T_OPS_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4])]);

section('Every form needs the page\'s token');

$c = as_ops('grace@nfelia.test');
$c->post(o('team/invite'), ['full_name' => 'Forged', 'email' => 'forged@example.test', 'role' => 'admin', 'csrf_token' => 'forged']);
eq((int)q("SELECT COUNT(*) FROM ops_staff WHERE email = 'forged@example.test'"), 0, 'an invite posted from elsewhere does nothing');
$s = ops_browser();
$s->get(o('signin'));
$s->post(o('signin'), ['email' => 'grace@nfelia.test', 'password' => T_OPS_PASSWORD, 'csrf_token' => 'forged']);
$s->get(o());
eq($s->status, 302, 'a sign-in posted from elsewhere signs nobody in');

/* ── The audit log ──────────────────────────────────────────── */

section('The audit log');

$c->get(o('audit'));
ok(str_contains($c->body, 'Took Lola Sales off the team') && str_contains($c->body, 'Invited Tobi New'), 'lists what was done, by whom');
$c->get(o('audit?staff=2'));
ok(!str_contains($c->body, 'Took Lola Sales off the team'), 'filtered to one person, shows only theirs');
$c->get(o('audit?kind=team'));
ok(str_contains($c->body, 'Invited Tobi New') && !str_contains($c->body, 'Signed in with'), 'filtered to a kind, shows only that kind');
$c->get(o());
ok(str_contains($c->body, 'Recent activity'), 'Home shows an admin the latest activity');

/* ── The first admin ────────────────────────────────────────── */

section('Setting up Ops from the old admin page, once');

clean_slate();
exec_sql("DELETE FROM ops_audit");
exec_sql("UPDATE ops_staff SET role = 'support' WHERE role = 'admin'");
$tolu = signin('tolu@nfelia.test');
$tolu->get('admin');
ok(str_contains($tolu->body, 'Nfelia Ops is ready'), 'with no Ops admin, the Nfelia owner is offered to set it up');
$sade = signin('sade@nfelia.test');
$sade->get('admin');
$sade->post('admin/ops-setup');
eq((int)q("SELECT COUNT(*) FROM ops_staff WHERE email = 'sade@nfelia.test'"), 0, 'staff of the Nfelia business cannot');
$ada = signin('ada@adashair.test');
$ada->post('admin/ops-setup');
eq((int)q("SELECT COUNT(*) FROM ops_staff WHERE email = 'ada@adashair.test'"), 0, 'nor can any other business');
$tolu->post('admin/ops-setup');
$row = rows("SELECT * FROM ops_staff WHERE email = 'tolu@nfelia.test'")[0] ?? null;
ok($row && $row['role'] === 'admin' && $row['password_hash'] === null, 'Tolu becomes the first Ops admin, waiting to choose a password');
$link = link_in_mail('tolu@nfelia.test', '~https?://[^"\s]+/invite/[A-Za-z0-9_-]+~');
ok($link !== '', 'with an invite emailed to the business email');
$tolu->get('admin');
ok(!str_contains($tolu->body, 'Nfelia Ops is ready'), 'and the offer is gone');
$tolu->post('admin/ops-setup');
eq((int)q("SELECT COUNT(*) FROM ops_tokens WHERE staff_id = ? AND purpose = 'invite'", [(int)$row['id']]), 1, 'asking again does nothing');
$t = ops_browser();
$t->get(o($link));
$t->post(o($link), ['full_name' => 'Tolu Nfelia', 'password' => 'FoundersPass1', 'password_again' => 'FoundersPass1']);
$t->get(o('team'));
eq($t->status, 200, 'Tolu signs in to Ops and runs the team');

done();
