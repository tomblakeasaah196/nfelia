<?php
/**
 * A new owner from sign-up to Home, against the running app: signing up
 * again while signed in makes no second business; onboarding counts four
 * steps, refuses a missing country and keeps what was typed; the currency
 * is the country's and never what is posted; tax starts at 0; half a bank
 * account is refused; colours left alone stay the sign-up wine and gold;
 * step 3 leads to shop setup, and Home then shows the trial's days left
 * (app/controllers/AuthController.php, app/controllers/OnboardingController.php,
 * views/onboarding/wizard.php, app/controllers/ShopSetupController.php,
 * app/middleware/auth_check.php, app/controllers/DashboardController.php,
 * db/migrations/20261030100500_secondary_colour_default.sql).
 *   bash tests/notifications/run.sh auth/onboarding
 */
require __DIR__ . '/../notifications/lib.php';
t_guard();
fixture_reset();
clean_slate();
exec_sql("DELETE FROM auth_throttle");

const NEW_EMAIL = 'adanna@adannaluxe.test';
$biz_of = fn(): array => rows("SELECT b.* FROM businesses b JOIN users u ON u.business_id = b.id WHERE u.email = ?", [NEW_EMAIL])[0] ?? [];

/* ══ Sign-up ═══════════════════════════════════════════════════ */
section('Signing up');
$h = new Http();
$h->get('register');
$h->post('register', ['business_name' => 'Adanna Luxe Hair', 'full_name' => 'Adanna Obi', 'email' => NEW_EMAIL,
                      'password' => 'Strong#Pass9', 'password_confirm' => 'Strong#Pass9']);
eq($h->to(), 'onboarding/step1', 'a new owner goes to onboarding');
$biz = $biz_of();
eq([$biz['primary_color'] ?? '', $biz['secondary_color'] ?? ''], ['#8B004A', '#B69F5E'], 'in the wine and gold');
eq((float)($biz['default_tax_rate'] ?? -1), 0.0, 'with no tax');

$h->post('register', ['business_name' => 'Second Shop', 'full_name' => 'Adanna Obi', 'email' => 'second@adannaluxe.test',
                      'password' => 'Strong#Pass9', 'password_confirm' => 'Strong#Pass9']);
eq($h->to(), 'dashboard', 'signing up again while signed in goes Home');
eq((int)q("SELECT COUNT(*) FROM businesses WHERE name = 'Second Shop'"), 0, 'and makes no second business');

exec_sql("INSERT INTO businesses (name, slug, doc_prefix) VALUES ('Plain Co', 'plain-co-onboarding-test', 'PCO')");
eq(q("SELECT secondary_color FROM businesses WHERE slug = 'plain-co-onboarding-test'"), '#B69F5E',
   'a business made without colours gets the gold as its second (the column default)');
exec_sql("DELETE FROM businesses WHERE slug = 'plain-co-onboarding-test'");

/* ══ Step 1 ════════════════════════════════════════════════════ */
section('Step 1: about the business');
$h->get('onboarding/step1');
ok(str_contains($h->body, 'Step 1 of 4'), 'the wizard counts four steps');
ok(str_contains($h->body, 'value="beauty"'), 'and offers Hair & Beauty');
$typed = ['business_type' => 'mixed', 'category' => 'beauty', 'phone' => '08031234567', 'address' => '12 Balogun Street, Lagos', 'tagline' => 'Quality hair'];
$h->post('onboarding/step1', $typed + ['country_id' => '']);
eq($h->to(), 'onboarding/step1', 'no country is refused');
$h->follow();
ok(str_contains($h->body, 'Choose your country.'), 'with a message');
ok(str_contains($h->body, '12 Balogun Street, Lagos') && str_contains($h->body, 'value="08031234567"')
   && str_contains($h->body, 'value="beauty" selected') && preg_match('/value="mixed"\s+checked/', $h->body) === 1,
   'and everything typed is still there');
eq((int)$biz_of()['onboarding_step'], 1, 'nothing is saved');

$h->post('onboarding/step1', ['category' => 'not-a-category'] + $typed + ['country_id' => 2]);
eq($biz_of()['category'], 'other', 'a category that is not on the list is saved as Other');
$h->post('onboarding/step1', $typed + ['country_id' => 2]);
eq($h->to(), 'onboarding/step2', 'with a country it carries on');
$biz = $biz_of();
eq([$biz['category'], $biz['business_type'], (int)$biz['country_id']], ['beauty', 'mixed', 2], 'Hair & Beauty, both, in Ghana');
eq($biz['currency_symbol'], 'GH₵', 'and the money is the country\'s');

/* ══ Step 2 ════════════════════════════════════════════════════ */
section('Step 2: money and payments');
$h->get('onboarding/step2');
ok(str_contains($h->body, 'name="default_tax_rate" value="0"'), 'tax starts at 0');
ok(str_contains($h->body, 'Add your VAT rate if you charge it.'), 'with a hint for those who charge it');
ok(!str_contains($h->body, 'name="currency_symbol"'), 'the currency is not a choice');
ok(str_contains($h->body, 'GHS (GH₵)') && str_contains($h->body, 'It comes from your country, Ghana.'), 'it shows the country\'s, and says so');

$h->post('onboarding/step2', ['currency_symbol' => '₦', 'default_tax_rate' => '-5', 'bank_name' => 'GTBank', 'account_number' => '', 'account_name' => '']);
eq($h->to(), 'onboarding/step2', 'half a bank account is refused');
$h->follow();
ok(str_contains($h->body, 'value="GTBank"'), 'and what was typed is kept');
$h->post('onboarding/step2', ['currency_symbol' => '₦', 'default_tax_rate' => '-5']);
eq($h->to(), 'onboarding/step3', 'no bank account at all is fine');
$biz = $biz_of();
eq($biz['currency_symbol'], 'GH₵', 'a currency in the post is ignored: it stays the country\'s');
eq((float)$biz['default_tax_rate'], 0.0, 'a tax below 0 is held at 0');
eq((int)q("SELECT COUNT(*) FROM bank_accounts WHERE business_id = ?", [$biz['id']]), 0, 'and no account is made');

/* ══ Step 3 ════════════════════════════════════════════════════ */
section('Step 3: how the business looks, then shop setup');
$h->get('onboarding/step3');
ok(str_contains($h->body, 'id="primary_color_picker" value="#8B004A"') && str_contains($h->body, 'id="secondary_color_picker" value="#B69F5E"'),
   'the colour pickers start at the business\'s own colours');
ok(str_contains($h->body, 'up to 20MB'), 'the logo box gives the real limit');
$h->post('onboarding/step3', ['primary_color' => 'navy', 'secondary_color' => '', 'font_pairing' => 'comic']);
eq($h->to(), 'shop-setup', 'step 3 leads to shop setup, the fourth step');
$biz = $biz_of();
eq([$biz['primary_color'], $biz['secondary_color'], $biz['font_pairing']], ['#8B004A', '#B69F5E', 'modern_sans'],
   'colours and a font that are not real keep what the business had');
$h->follow();
ok(str_contains($h->body, 'One last step'), 'shop setup says it is the last step');
ok(!str_contains($h->body, 'Deployment'), 'with no "Deployment complete"');
ok(preg_match('/value="glow"[^>]*checked/', $h->body) === 1, 'Hair & Beauty starts in the Glow look');
ok(!str_contains($h->body, 'data-just-signed-in'), 'and the passkey and home-screen offers wait');

$h->get('more');
eq($h->status, 200, 'More (the bell\'s settings link) opens before the shop is set up');
ok(str_contains($h->body, 'data-just-signed-in'), 'and the offers come there, on the page after shop setup');
$h->post('signing/settings', ['signing' => ['invoice' => ['on' => 1, 'style' => 'stamp']]]);
eq($h->to(), 'settings#signatures', 'a Settings › Signatures save is not sent to shop setup');
ok(str_contains((string)q("SELECT signing FROM businesses WHERE id = ?", [$biz['id']]), '"invoice":{"on":true'), 'and is kept');

$h->post('shop-setup/save', ['shop_name' => 'adannaluxe', 'look' => 'glow']);
eq($h->to(), 'dashboard', 'saving the shop goes Home');
$h->follow();
ok(str_contains($h->body, '14 days left of your free trial'), 'where the owner sees the trial\'s days left');

/* ══ Hair & Beauty elsewhere ═══════════════════════════════════ */
section('Hair & Beauty has its own words outside onboarding');
require_once BASE_PATH . '/app/helpers/records.php';
eq(directory_category_slug('beauty'), 'hair-and-beauty', 'the shops directory lists it on its own');
eq(STOREFRONT_LOOK_BY_CATEGORY['beauty'] ?? '', 'glow', 'a new shop starts in Glow');
eq(records_category_text(['category' => 'beauty', 'business_type' => 'mixed']), 'Hair and beauty, sells products and services', 'the Records Book names it');

done();
