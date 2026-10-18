// PR 3 of the October 2026 audit (docs/AUDIT_2026-10.md): shop websites,
// reminders and the rest. Tasks follow what happened to invoices, orders,
// deliveries and staff; forms and routes hold up; shop pages keep what the
// owner hid hidden and keep their promises to shoppers.
//
// Reseeds the local database first (seed.php, planner_seed.sql.txt,
// landing_seed.php), so run it only there. Needs the local server with
// APP_URL=http://localhost:8000 in .env.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/shops_reminders_test.js <out dir>
const { chromium } = require('playwright');
const { execSync, execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = path.resolve(process.argv[2] || 'shots');
const KIT = __dirname;
const ROOT = path.resolve(KIT, '../../..');
const APP = 'http://localhost:8000';
const SHOP = 'http://ivieluxe.localhost:8000';
const results = [];
const check = (label, ok, detail = '') => { results.push(!!ok); console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300))); };
const sql = (q) => execFileSync('mysql', ['-N', '--default-character-set=utf8mb4', 'smartqaq_nfelia_core', '-e', q]).toString().trim();
const num = (q) => Number(sql(q));
const today = () => sql('SELECT CURDATE()');
const day = (n) => sql(`SELECT DATE_ADD(CURDATE(), INTERVAL ${n} DAY)`);
// Runs PHP with the app's helpers loaded, as index.php loads them
const php = (code) => {
  const file = path.join(OUT, 'probe.php');
  const helpers = ['functions', 'auth', 'billing', 'stock', 'money', 'invoices', 'token', 'mail', 'webpush', 'planner', 'shop_links',
                   'storefront', 'cloudflare', 'shop_orders', 'bookings', 'seo', 'theme', 'nav', 'signin', 'passkeys', 'notify', 'screens'];
  fs.writeFileSync(file, `<?php require '${ROOT}/vendor/autoload.php'; require '${ROOT}/config/database.php'; require '${ROOT}/config/app.php';
foreach (${JSON.stringify(helpers)} as $h) require_once '${ROOT}/app/helpers/' . $h . '.php';
${code}`);
  return execFileSync('php', [file]).toString().trim();
};
// A job plan for an invoice: the job and its delivery step (and another step)
const jobFor = (invoice, date, extraStep = false) => {
  sql(`INSERT INTO tasks (business_id, kind, title, due_date, invoice_id, plan, created_by) VALUES (1, 'task', 'Job for invoice ${invoice}', '${date}', ${invoice}, 'custom', 1)`);
  const parent = sql('SELECT MAX(id) FROM tasks');
  if (extraStep) {
    sql(`INSERT INTO tasks (business_id, parent_id, kind, title, due_date, invoice_id, done_at, created_by, sort_order) VALUES (1, ${parent}, 'task', 'Buy materials', '${today()}', ${invoice}, NOW(), 1, 1)`);
  }
  sql(`INSERT INTO tasks (business_id, parent_id, kind, title, due_date, due_time, invoice_id, step_key, remind_before, created_by, sort_order)
       VALUES (1, ${parent}, 'task', 'Deliver', '${date}', '10:00:00', ${invoice}, 'deliver', 60, 1, 2)`);
  return [parent, sql('SELECT MAX(id) FROM tasks')];
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  execSync(`php ${KIT}/seed.php ${path.join(OUT, 'app')}`);
  execSync(`mysql smartqaq_nfelia_core < ${KIT}/planner_seed.sql.txt`);
  execSync(`php ${KIT}/landing_seed.php ${OUT}`);
  const hash = sql("SELECT password_hash FROM users WHERE email = 'ivie@demo.nfelia'");
  sql(`DELETE FROM auth_throttle; INSERT INTO users (business_id, role, full_name, email, password_hash, is_email_verified, is_active)
       VALUES (1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', '${hash}', 1, 1); UPDATE subscriptions SET extra_seats = 1 WHERE business_id = 1`);
  const tunde = sql("SELECT id FROM users WHERE email = 'tunde@demo.nfelia'");

  const browser = await chromium.launch();
  const errors = [];
  const session = async (email) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push((email || 'shopper') + ': ' + e.message));
    page.on('dialog', (d) => d.accept());
    await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({ body: 'window.tailwind = { config: {} };', contentType: 'text/javascript' }));
    await page.route(/fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare|unpkg\.com|cdn\.jsdelivr/, (r) => r.fulfill({ body: '' }));
    if (email) {
      await page.goto(APP + '/login');
      await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
      await page.goto(APP + '/dashboard');
    }
    return page;
  };
  const send = (page, url, fields = {}, ajax = false) => page.evaluate(async ([url, fields, ajax]) => {
    const fd = new FormData();
    const token = (window.Nfelia && window.Nfelia.csrf) || (document.querySelector('input[name=csrf_token]') || {}).value || '';
    if (!('csrf_token' in fields)) fd.append('csrf_token', token);
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => fd.append(k, x)); else fd.append(k, v);
    }
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: ajax ? { 'X-Requested-With': 'XMLHttpRequest' } : {} });
    return { status: r.status, url: r.url, text: await r.text() };
  }, [url, fields, ajax]);
  const form = (page, url, fields) => send(page, url, fields, false);
  const ajax = (page, url, fields) => send(page, url, fields, true);
  const get = (page, url) => page.evaluate(async (url) => {
    const r = await fetch(url, { credentials: 'same-origin' });
    return { status: r.status, url: r.url, text: await r.text() };
  }, url);
  const tasks = (page, action, body) => page.evaluate(async ([action, body]) => {
    const r = await fetch(window.Nfelia.url + '/tasks/' + action, { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
    return { status: r.status, json: await r.json().catch(() => ({})) };
  }, [action, body]);

  const ivie = await session('ivie@demo.nfelia');
  const anon = await session(null);
  let r;

  /* ── Reminders follow what happened (P1–P17) ────────────────── */
  sql(`INSERT INTO tasks (business_id, kind, title, due_date, assigned_to, created_by) VALUES (1, 'task', 'Call the fabric supplier', '${day(1)}', ${tunde}, 1)`);
  const tundeTask = sql('SELECT MAX(id) FROM tasks');
  await ivie.goto(APP + '/settings?tab=staff');
  await form(ivie, APP + `/settings/remove_staff/${tunde}`, {});
  check('P1 a removed team member\'s open tasks come to the owner', sql(`SELECT assigned_to FROM tasks WHERE id = ${tundeTask}`) === '1'
        && sql(`SELECT is_active FROM users WHERE id = ${tunde}`) === '0', sql(`SELECT IFNULL(assigned_to, 'none') FROM tasks WHERE id = ${tundeTask}`));

  const del041 = sql("SELECT id FROM deliveries WHERE doc_number = 'IVL-DEL-041'");
  const [job9, step9] = jobFor(9, day(1));
  await ivie.goto(APP + `/deliveries/view/${del041}`);
  await form(ivie, APP + `/deliveries/update_status/${del041}`, { status: 'delivered' });
  check('P2 a delivered delivery note keeps its invoice\'s promise, ticks the step and finishes the job',
        sql('SELECT delivered_at IS NOT NULL FROM invoices WHERE id = 9') === '1' && sql(`SELECT done_at IS NOT NULL FROM tasks WHERE id = ${step9}`) === '1'
        && sql(`SELECT done_at IS NOT NULL FROM tasks WHERE id = ${job9}`) === '1', sql(`SELECT status FROM deliveries WHERE id = ${del041}`));

  sql(`INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
       payment_method, currency_code, subtotal, total, status, invoice_id, created_at) VALUES (105, 1, 5, 'IVL-ORD-022', '${'b'.repeat(32)}', 'whatsapp', 'pickup',
       'Temi Adeyemi', '0817 555 0155', 'none', 'NGN', 65000, 65000, 'processing', 5, NOW())`);
  await form(ivie, APP + '/orders/update_status/105', { status: 'fulfilled' });
  check('P2 a fulfilled shop order keeps its invoice\'s promise', sql('SELECT delivered_at IS NOT NULL FROM invoices WHERE id = 5') === '1');

  const [, step8] = jobFor(8, day(3));
  sql("UPDATE invoices SET status = 'sent', amount_paid = 0, balance_due = total WHERE id = 8; DELETE FROM payments WHERE invoice_id = 8");
  await form(ivie, APP + '/invoices/cancel/8', {});
  check('P2 a cancelled invoice\'s delivery step leaves the calendar', sql('SELECT status FROM invoices WHERE id = 8') === 'cancelled'
        && sql(`SELECT deleted_at IS NOT NULL FROM tasks WHERE id = ${step8}`) === '1');

  const [job6, step6] = jobFor(6, day(2));
  await form(ivie, APP + '/invoices/set_delivery/6', { deliver_mode: 'date', deliver_by: day(6), deliver_show: '1' });
  check('P6 moving a delivery date moves the step, its reminder and the job\'s finish date',
        sql(`SELECT due_date FROM tasks WHERE id = ${step6}`) === day(6) && sql(`SELECT DATE(remind_at) FROM tasks WHERE id = ${step6}`) === day(6)
        && sql(`SELECT due_date FROM tasks WHERE id = ${job6}`) === day(6), sql(`SELECT CONCAT(due_date, ' ', IFNULL(remind_at, '')) FROM tasks WHERE id = ${step6}`));

  const [job7, step7] = jobFor(7, day(2), true);
  await ivie.goto(APP + '/tasks');
  r = await tasks(ivie, 'delete', { id: Number(step7) });
  check('P7 deleting a job\'s last open step finishes the job', sql(`SELECT done_at IS NOT NULL FROM tasks WHERE id = ${job7}`) === '1', JSON.stringify(r));

  sql(`INSERT INTO invoices (business_id, customer_id, user_id, doc_number, status, issue_date, subtotal, total, balance_due, token)
       VALUES (1, 2, 1, 'IVL-INV-200', 'draft', CURDATE(), 1000, 1000, 1000, '${'d'.repeat(32)}')`);
  const draft = sql('SELECT MAX(id) FROM invoices');
  const [, stepDraft] = jobFor(draft, day(4));
  await form(ivie, APP + `/invoices/delete/${draft}`, {});
  check('P8 deleting a draft invoice unlinks its tasks', sql(`SELECT CONCAT(IFNULL(invoice_id, 'none'), ' ', IFNULL(step_key, 'none')) FROM tasks WHERE id = ${stepDraft}`) === 'none none');

  sql(`INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due, currency_code,
       customer_id, customer_name, customer_phone, channel, payment_method, status, created_at) VALUES (1, 6, 'Wig revamp & styling', 'IVL-BKG-040', '${'e'.repeat(32)}',
       'slot', NOW() - INTERVAL 30 MINUTE, NOW() + INTERVAL 30 MINUTE, 1, 15000, 15000, 0, 'NGN', 5, 'Temi Adeyemi', '0817 555 0155', 'site', 'none', 'pending', NOW())`);
  const late = sql("SELECT id FROM bookings WHERE booking_number = 'IVL-BKG-040'");
  await form(ivie, APP + `/bookings/confirm/${late}`, {});
  check('P9 a booking confirmed after its start time sends no reminder after it', sql(`SELECT reminded_at IS NOT NULL FROM tasks t JOIN bookings b ON b.task_id = t.id WHERE b.id = ${late}`) === '1');

  r = await tasks(ivie, 'save', { kind: 'task', title: 'Pay the shop rent', date: sql("SELECT LAST_DAY(CURDATE())"), repeat: 'month' });
  const rent = sql("SELECT id FROM tasks WHERE title = 'Pay the shop rent' ORDER BY id DESC LIMIT 1");
  check('P11 a monthly repeat remembers its day', sql(`SELECT repeat_day FROM tasks WHERE id = ${rent}`) === sql('SELECT DAY(LAST_DAY(CURDATE()))'), JSON.stringify(r));
  check('P11 the 31st comes back as the 31st after February', php("echo planner_next_date('2027-02-28', 'month', '2027-02-28', 31);") === '2027-03-31');
  check('P16 "hidden for a week" is seven days', php("echo planner_dismiss_until('restock:1', '2026-10-18');") === '2026-10-24');
  const line = php(`echo planner_day_summary(['late' => [], 'today' => [
      ['pressure' => false, 'source' => 'restock', 'amount' => 0], ['pressure' => false, 'source' => 'order', 'amount' => 0], ['pressure' => false, 'source' => 'task', 'amount' => 0]],
      'soon' => []], '₦')['line'];`);
  check('P17 the morning line counts restock and orders on their own', line === 'Today: 1 task, 1 order to confirm, 1 to restock', line);

  await ivie.goto(APP + '/tasks');
  await ivie.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
  await ivie.goto(APP + '/tasks');
  const before = num("SELECT COUNT(*) FROM tasks WHERE title = 'Collect the lace'");
  await ivie.fill('#pl-quick', 'Collect the lace');
  await ivie.locator('button:has-text("Pick a date")').first().click();
  await ivie.evaluate(() => { const el = document.querySelector('input[type=date][x-model="quick.date"]'); if (el) { el.value = ''; el.dispatchEvent(new Event('input', { bubbles: true })); } });
  await ivie.keyboard.press('Enter');
  await ivie.waitForTimeout(600);
  check('P15 "Pick a date" with no date doesn\'t save an undated task', num("SELECT COUNT(*) FROM tasks WHERE title = 'Collect the lace'") === before);

  /* ── Forms, routes and records (D3–D13) ───────────────────────── */
  const newbie = await session(null);
  await newbie.goto(APP + '/register');
  await form(newbie, APP + '/register', { full_name: 'Ọlá Bankole', business_name: 'Ọja Ventures', email: 'ola.oja@example.test', password: 'Strong-Pass-2026', password_confirm: 'Strong-Pass-2026' });
  check('D3 a business name starting with "Ọ" signs up, with a plain prefix and link', sql("SELECT CONCAT(doc_prefix, ' ', slug) FROM businesses WHERE name = 'Ọja Ventures'") === 'OJV oja-ventures',
        sql("SELECT CONCAT(doc_prefix, ' ', slug) FROM businesses WHERE name = 'Ọja Ventures'"));
  r = await get(newbie, APP + '/onboarding/step1');
  check('D11 a new owner\'s pages show no PHP warnings', r.status === 200 && !/Warning|Undefined array key/.test(r.text), r.status);

  r = await form(ivie, APP + '/customers/create', { full_name: 'Long Phone', phone: '0803 123 4567 0803 123 4567 0803', email: '' });
  check('D4 a phone number too long for its column is refused with a message', num("SELECT COUNT(*) FROM customers WHERE full_name = 'Long Phone'") === 0 && r.status === 200
        && r.text.includes('Enter a phone number like'), r.status);

  const quote = sql("SELECT id FROM quotations WHERE business_id = 1 ORDER BY id LIMIT 1");
  const qtoken = sql(`SELECT token FROM quotations WHERE id = ${quote}`);
  sql(`INSERT INTO quotation_activities (business_id, quotation_id, actor_type, action, message, is_client_read) VALUES (1, ${quote}, 'business', 'comment_added', 'We can do it by Friday.', 0)`);
  await anon.goto(APP + `/doc/${qtoken}`);
  r = await ajax(anon, APP + `/doc/${qtoken}`, { action: 'mark_read' });
  check('D7 the client\'s "read" mark on replies saves', num(`SELECT COUNT(*) FROM quotation_activities WHERE quotation_id = ${quote} AND actor_type = 'business' AND is_client_read = 0`) === 0, r.text);

  const products = num('SELECT COUNT(*) FROM products WHERE business_id = 1 AND deleted_at IS NULL');
  await ivie.goto(APP + '/products/edit/4');
  await Promise.all([ivie.waitForNavigation(), ivie.locator('button:has-text("Duplicate This Product")').click()]);
  check('D8 "Duplicate this product" on the edit page makes a copy', num('SELECT COUNT(*) FROM products WHERE business_id = 1 AND deleted_at IS NULL') === products + 1);

  sql("UPDATE deliveries SET status = 'dispatched' WHERE business_id = 1");
  const open = num("SELECT COUNT(*) FROM deliveries WHERE business_id = 1 AND deleted_at IS NULL AND status IN ('pending_dispatch','dispatched','in_transit')");
  sql(`UPDATE deliveries SET deleted_at = NOW() WHERE id = ${del041}`);
  r = await get(ivie, APP + '/dashboard');
  const shown = open - 1;
  check('D9 Home\'s open deliveries leave out deleted ones', shown === 0 ? !/on its way|on their way/.test(r.text) : r.text.includes(String(shown)), `${open} open before`);

  const codes = [];
  for (const u of ['/account', '/referrals/apply_discount', '/countries/get_active_countries', '/billing/stop_card_renewals', '/dashboard/__construct']) {
    codes.push((await get(ivie, APP + u)).status);
  }
  codes.push((await get(anon, APP + '/public/delivery')).status, (await get(anon, APP + '/deed')).status);
  check('D10 routes that used to crash are a plain 404', codes.every((c) => c === 404), codes.join(','));

  sql('UPDATE customers SET deleted_at = NOW() WHERE id = 9');
  r = await get(ivie, APP + '/invoices/view/9');
  check('D12 a deleted client shows as plain text, not a link that 404s', r.text.includes('Lara Coker (deleted)') && !r.text.includes('/customers/view/9"'));
  check('D13 the dead backup controllers are gone', !fs.existsSync(path.join(ROOT, 'app/controllers/backupSettingsController.php'))
        && !fs.existsSync(path.join(ROOT, 'app/controllers/backupOnboardingController.php')));

  /* ── Shop websites (W3–W17) ───────────────────────────────────── */
  const shopSettings = (edit) => php(`$db = get_db(); $b = $db->query("SELECT * FROM businesses WHERE id = 1")->fetch();
       $s = storefront_settings($b, 'published');
       foreach ($s['sections'] as &$sec) { ${edit} } unset($sec);
       $db->prepare("UPDATE businesses SET storefront_published = ? WHERE id = 1")->execute([json_encode($s)]);`);
  shopSettings("if ($sec['type'] === 'contact') { $sec['show_phone'] = false; $sec['show_address'] = false; }");
  r = await get(anon, SHOP + '/').catch(() => null);
  await anon.goto(SHOP + '/');
  const home = await anon.content();
  const ld = (home.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/) || [])[1] || '';
  check('W3 a hidden phone and address stay out of Google\'s data and the header', ld !== '' && !ld.includes('"telephone"') && !ld.includes('streetAddress')
        && !home.includes('Shop 21'), ld.slice(0, 200));

  fs.mkdirSync(path.join(ROOT, 'uploads/products/1'), { recursive: true });
  fs.mkdirSync(path.join(ROOT, 'uploads/cache/640/products/1'), { recursive: true });
  fs.copyFileSync(path.join(ROOT, 'android-chrome-192x192.png'), path.join(ROOT, 'uploads/products/1/kit-photo.png'));
  fs.writeFileSync(path.join(ROOT, 'uploads/cache/640/products/1/kit-photo.png.webp'), 'copy');
  sql("INSERT INTO product_images (business_id, product_id, image_path, is_cover, sort_order) VALUES (1, 4, 'uploads/products/1/kit-photo.png', 0, 9)");
  const imgId = sql('SELECT MAX(id) FROM product_images');
  await ivie.goto(APP + '/products/edit/4');
  r = await ajax(ivie, APP + `/products/delete_image/${imgId}`, {});
  check('W5 deleting a photo deletes its resized copies too', !fs.existsSync(path.join(ROOT, 'uploads/products/1/kit-photo.png'))
        && !fs.existsSync(path.join(ROOT, 'uploads/cache/640/products/1/kit-photo.png.webp')), r.text);

  sql("UPDATE businesses SET subdomain_status = 'pending', subdomain_requested = 'ivie-luxe' WHERE id = 1");
  const listed = php("echo count(array_filter(seo_live_shops(get_db(), false), fn($s) => (int)$s['id'] === 1));");
  r = await get(ivie, APP + '/orders/view/101');
  check('W7 a live shop stays listed and keeps tracking links while its rename waits', listed === '1' && r.text.includes('ivieluxe.') && r.text.includes('/order/'), listed);
  sql("UPDATE businesses SET subdomain_status = 'active', subdomain_requested = NULL WHERE id = 1");

  await anon.goto(SHOP + '/');
  const checkout = (items) => ajax(anon, SHOP + '/checkout', { channel: 'whatsapp', fulfillment_type: 'pickup', customer_name: 'Bisi Ola', customer_phone: '0803 222 0101',
    items: JSON.stringify(items), payment_method: 'none' });
  r = await checkout([{ product_id: 3, quantity: 1 }, { product_id: 5, quantity: 1 }]);
  const unavailable = (() => { try { return JSON.parse(r.text).unavailable; } catch (e) { return null; } })();
  check('W8 checkout says which items went instead of placing a smaller order', r.status === 409 && JSON.stringify(unavailable) === '[5]', r.status + ' ' + r.text.slice(0, 200));

  r = await checkout([{ product_id: 3, quantity: 1 }]);
  const tok = (() => { try { return new URL(JSON.parse(r.text).order_url).pathname; } catch (e) { return ''; } })();
  const orderNo = (() => { try { return JSON.parse(r.text).order_number; } catch (e) { return '?'; } })();
  await anon.goto(SHOP + tok);
  const tracking = await anon.content();
  check('W15 a shop with no transfer says "waiting to be confirmed", not "checking your transfer"', tracking.includes('Waiting to be confirmed') && !tracking.includes('checking your transfer'), tok);

  sql(`INSERT INTO shop_name_holds (name, business_id, held_until) VALUES ('ivie-old', 1, NOW() + INTERVAL 30 DAY)`);
  sql('UPDATE businesses SET landing_enabled = 0 WHERE id = 1');
  const oldShop = await session(null);
  const oldRes = await oldShop.goto('http://ivie-old.localhost:8000' + tok);
  check('W12 an order link on the old name works while the shop page is off', oldRes.status() === 200 && (await oldShop.content()).includes(orderNo), oldRes.status());
  sql('UPDATE businesses SET landing_enabled = 1 WHERE id = 1');

  shopSettings("if ($sec['type'] === 'quote') { $sec['on'] = false; }");
  const quotes = num('SELECT COUNT(*) FROM quote_requests WHERE business_id = 1');
  r = await ajax(anon, SHOP + '/quote-request', { name: 'Bisi Ola', phone: '0803 222 0101', message: 'Bridal set for 6' });
  check('W13 quote requests stop when the form is off', r.status === 403 && num('SELECT COUNT(*) FROM quote_requests WHERE business_id = 1') === quotes, r.status);

  const done = sql("SELECT id FROM orders WHERE status = 'fulfilled' AND business_id = 1 ORDER BY id LIMIT 1");
  sql(`UPDATE reviews SET status = 'invited', rating = NULL, comment = NULL, submitted_at = NULL WHERE order_id = ${done}`);
  r = await get(ivie, APP + `/orders/view/${done}`);
  // The WhatsApp message sits URL-encoded in the button's link
  check('W14 the owner\'s order page never shows the review link', !r.text.includes('/review/') && !r.text.includes('%2Freview%2F')
        && r.text.includes('Open%20your%20order%20page%20and%20tap%20Rate'), done);
  const rtoken = sql(`SELECT token FROM reviews WHERE order_id = ${done}`);
  await anon.goto(SHOP + `/review/${rtoken}`);
  await send(anon, SHOP + `/review/${rtoken}`, { rating: '5', comment: 'Lovely' });
  check('W14 a review keeps where it came from', sql(`SELECT ip IS NOT NULL AND user_agent LIKE '%Chrome%' FROM reviews WHERE order_id = ${done}`) === '1',
        sql(`SELECT CONCAT(status, ' ', IFNULL(ip, '-')) FROM reviews WHERE order_id = ${done}`));

  const names = php(`echo json_encode(array_map('shop_name_needs_review', ['bvnupdate', 'mtnbonus', 'secure-login', 'nfe1ia', 'glow-by-temi', 'harmony-hair', 'bankole-fabrics']));`);
  check('W16 lookalike and account-bait names wait for review, ordinary ones don\'t', names === '[true,true,true,true,false,false,false]', names);

  sql("UPDATE businesses SET city = 'Atlantis', city_slug = 'atlantis', directory_opt_in = 1 WHERE id = 1");
  const sitemap = php("echo main_pages_sitemap(get_db());");
  check('W17 a typed city that isn\'t on the list makes no directory page', !sitemap.includes('/shops/atlantis'));

  check('No JavaScript errors on the pages opened', errors.length === 0, errors.join(' | '));
  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} passed`);
  process.exit(passed === results.length ? 0 : 1);
})();
