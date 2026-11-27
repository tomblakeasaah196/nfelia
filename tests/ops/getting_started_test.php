<?php
/**
 * Getting-started emails from Customer Support on days 1, 3 and 7 of a
 * free trial, sent by the real cron at 10:00: the one next step for where
 * each business is, in French for an owner in Cameroon, once each, and none
 * for a business that uses Nfelia, was written to from Ops in the last
 * 3 days, or opted out. And the welcome email in French for a sign-up from
 * Cameroon (app/helpers/getting_started.php, cron/planner.php,
 * views/emails/ops_client_message.php, views/emails/welcome_fr.php,
 * app/controllers/AuthController.php).
 *   bash tests/notifications/run.sh ops/getting_started
 */
require __DIR__ . '/lib.php';
t_guard();
fixture_reset();
clean_slate();
exec_sql("DELETE FROM auth_throttle");

exec_sql("INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, monthly_price, extra_seat_price, book_price, trial_days, is_active)
          VALUES (3, 'Cameroon', 'CM', '🇨🇲', 'Central African CFA franc', 'XAF', 'FCFA', 'mobile_money', 5000, 2500, 3500, 15, 1)");

/** A business on its free trial that signed up $days days ago, with its owner. */
function trial_business(int $id, string $name, string $owner, int $days, ?int $country, int $setup = 1, ?string $from = null): string
{
    $email = strtolower(strtok($owner, ' ')) . "@gs{$id}.test";
    exec_sql("INSERT INTO businesses (id, name, slug, doc_prefix, email, country_id, signup_country, onboarding_complete, onboarding_step, is_active, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW() - INTERVAL {$days} DAY)",
             [$id, $name, "gs-{$id}", 'G' . $id, $email, $country, $from, $setup, $setup ? 5 : 1]);
    exec_sql("INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (?, ?, 'owner', ?, ?, 'x', 1, 1)",
             [$id * 10, $id, $owner, $email]);
    exec_sql("INSERT INTO subscriptions (business_id, country_id, plan, status, user_seats, trial_ends_at) VALUES (?, ?, 'trial', 'trial', 1, CURDATE() + INTERVAL 10 DAY)",
             [$id, $country ?? 1]);
    return $email;
}

function product(int $biz): void
{
    exec_sql("INSERT INTO products (business_id, name, sell_price) VALUES (?, 'Box braids', 15000)", [$biz]);
}

$mamie = trial_business(6, 'Mamie Coiffure', 'Mamie Nyanga', 1, 3);                 // day 1, Cameroon, nothing added
$chef  = trial_business(7, 'Chef Zee Kitchen', 'Chef Zainab', 3, 1);                // day 3, Nigeria
$late  = trial_business(8, 'Douala Styles', 'Paul Mbarga', 7, null, 0, 'CM');       // day 7, never set up, signed up from Cameroon
$busy  = trial_business(9, 'Busy Bee', 'Bee Ade', 1, 1);                            // day 1, already selling
product(9);
exec_sql("INSERT INTO orders (business_id, order_number, customer_name, customer_phone) VALUES (9, 'ORD-1', 'Walk-in', '0800')");
$asked = trial_business(10, 'Asked Already', 'Ope Lawal', 3, 1);                    // day 3, emailed from Ops yesterday
exec_sql("INSERT INTO notify_outbox (business_id, event, channel, recipient, payload, status, created_at, next_attempt_at)
          VALUES (10, 'ops.client_email', 'email', ?, '{}', 'sent', NOW() - INTERVAL 1 DAY, NOW())", [$asked]);
$two   = trial_business(11, 'Day Two', 'Tobi Two', 2, 1);                           // day 2: no email that day
$out   = trial_business(12, 'Opted Out', 'Uche Out', 1, 1);                         // day 1, opted out of Nfelia's emails
exec_sql("INSERT INTO email_opt_outs (email) VALUES (?)", [$out]);
$shop  = trial_business(13, 'Shopless', 'Sola Shop', 7, 1);                         // day 7, selling things, no shop online
product(13);

/* ══ The 10:00 run ═════════════════════════════════════════════ */
section('At 10:00, each new owner gets the next step that matters');
$getting_started = fn(): int => (int)q("SELECT COUNT(*) FROM notify_outbox WHERE event LIKE 'onboarding.%'");
cron_at('09:55');
eq($getting_started(), 0, 'nothing goes before 10:00');
$ran = cron_at('10:00');
ok(str_contains($ran, 'getting_started=4'), 'the 10:00 run queues four (' . $ran . ')');
deliver();

$m = mails_to($mamie)[0] ?? ['subject' => '', 'text' => '', 'html' => '', 'from' => '', 'reply_to' => ''];
eq($m['subject'], 'Commencez par ce que vous vendez', 'day 1, nothing added, in Cameroon: in French, start with what you sell');
ok(str_contains($m['text'], 'Bonjour Mamie') && str_contains($m['text'], 'Mamie Coiffure'), 'to her first name, about her business');
ok(str_contains($m['html'], '/products/create') && str_contains($m['text'], 'Ajouter ce que je vends'), 'with a button to add products');
ok(str_contains($m['from'], 'Simi de Nfelia') && str_contains($m['text'], 'Service client'), 'from Simi, Customer Support, in French');
ok(str_contains($m['reply_to'], 'support@nfelia.com'), 'replies go to support');
ok(str_contains($m['text'], 'Répondez simplement à cet e-mail') && str_contains($m['text'], "Cet e-mail vous a été envoyé par l'équipe Nfelia")
   && str_contains($m['html'], '<html lang="fr">'), 'the footers are in French too');
ok(!str_contains($m['text'], 'Just reply to this email'), 'with no English left in');

$m = mails_to($chef)[0] ?? ['subject' => '', 'text' => '', 'html' => '', 'from' => ''];
eq($m['subject'], 'Can we help you get started?', 'day 3, in Nigeria: an offer of a free session, in English');
ok(str_contains($m['text'], 'Hi Chef') && str_contains($m['html'], APP_URL . '/book'), 'with the booking link');
ok(str_contains($m['from'], 'Simi from Nfelia') && str_contains($m['text'], 'Customer Support'), 'from Simi, Customer Support');
ok(str_contains($m['text'], 'This email was sent by the Nfelia team'), 'in Nfelia\'s own frame');

$m = mails_to($late)[0] ?? ['subject' => '', 'html' => '', 'text' => ''];
eq($m['subject'], 'Terminez la configuration de Douala Styles sur Nfelia', 'day 7, never set up, signed up from Cameroon: finish setting up, in French');
ok(str_contains($m['html'], '/onboarding/step1') && str_contains($m['text'], 'réservez une courte séance'), 'with the setup link and the offer of a session');

eq(mails_to($shop)[0]['subject'] ?? '', 'Your shop can be online today', 'day 7, selling things with no shop: put the shop online');
ok(str_contains(mails_to($shop)[0]['html'] ?? '', '/landing-page'), 'with the shop link');

ok(!mails_to($busy), 'nothing to a business that already sells with Nfelia');
ok(!mails_to($asked), 'nothing to one written to from Ops in the last 3 days');
ok(!mails_to($two), 'nothing on day 2');
ok(!mails_to($out), 'nothing to an owner who opted out');
ok(!mails_to('kola@kolaprints.test'), 'nothing to a trial that started today');

eq(array_column(rows("SELECT body FROM ops_notes WHERE subject_type = 'business' AND subject_id = 6"), 'body'),
   ['Getting-started email, day 1 (in French): Commencez par ce que vous vendez'], 'Ops sees what went, on the client\'s page');
eq(array_column(outbox('onboarding.day3', 'email'), 'recipient'), [$chef], 'the outbox keeps it under its day');

section('Once each');
cron_at('10:05');
cron_at('10:55');
eq($getting_started(), 4, 'the hour\'s later runs send nothing again');

/* ══ The welcome email ═════════════════════════════════════════ */
section('A sign-up from Cameroon gets the welcome email in French');
$h = new Http();
$h->get('register');
$h->post('register', ['business_name' => 'Yaoundé Beauté', 'full_name' => 'Aïcha Fouda', 'email' => 'aicha@yaounde.test',
                      'password' => 'Strong#Pass9', 'password_confirm' => 'Strong#Pass9'], ['CF-IPCountry: CM']);
eq($h->to(), 'onboarding/step1', 'the sign-up goes through');
eq(q("SELECT b.signup_country FROM businesses b JOIN users u ON u.business_id = b.id WHERE u.email = 'aicha@yaounde.test'"), 'CM',
   'where they signed up from is kept');
$w = mails_to('aicha@yaounde.test')[0] ?? ['subject' => '', 'text' => ''];
eq($w['subject'], 'Bienvenue sur Nfelia — confirmez votre adresse e-mail', 'the welcome email is in French');
ok(str_contains($w['text'], 'Bienvenue sur Nfelia, Aïcha Fouda') && str_contains($w['text'], '15 jours'), 'with Cameroon\'s 15-day trial');
ok(str_contains($w['text'], 'Confirmer mon adresse'), 'and the confirm button in French');

$h = new Http();
$h->get('register');
$h->post('register', ['business_name' => 'Lekki Lashes', 'full_name' => 'Kemi Ade', 'email' => 'kemi@lekki.test',
                      'password' => 'Strong#Pass9', 'password_confirm' => 'Strong#Pass9'], ['CF-IPCountry: NG']);
eq(mails_to('kemi@lekki.test')[0]['subject'] ?? '', 'Welcome to Nfelia — Verify Your Email', 'a sign-up from Nigeria gets it in English');

done();
