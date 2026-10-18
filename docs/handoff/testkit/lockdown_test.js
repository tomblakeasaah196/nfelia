// PR 1 of the October 2026 audit (docs/AUDIT_2026-10.md): one business can't
// reach another's records, signatures and payment proofs are private, roles
// are checked by the pages themselves, billing expires and applies each
// payment once, and the shop's public forms have spam limits.
//
// Reseeds the local database first (seed.php, planner_seed.sql.txt,
// landing_seed.php), so run it only there. Needs:
//   - the local server, with APP_URL=http://localhost:8000 in .env
//   - the Paystack stand-in:  php -S 127.0.0.1:8096 docs/handoff/testkit/paystack_mock.php
//     and in .env: PAYSTACK_API_BASE=http://127.0.0.1:8096, PAYSTACK_SECRET_KEY=sk_test_local
//   - VAPID keys in .env (php bin/vapid-keys.php) for the phone-notification checks
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/lockdown_test.js <out dir>
const { chromium } = require('playwright');
const { execSync, execFileSync } = require('child_process');
const crypto = require('crypto');
const path = require('path');
const fs = require('fs');

const OUT = path.resolve(process.argv[2] || 'shots');
const KIT = __dirname;
const ROOT = path.resolve(KIT, '../../..');
const APP = 'http://localhost:8000';
const SHOP = 'http://ivieluxe.localhost:8000';
const SECRET = 'sk_test_local';
const MOCK = path.join(require('os').tmpdir(), 'paystack_mock');
const results = [];
const check = (label, ok, detail = '') => { results.push(!!ok); console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300))); };
const sql = (q) => execFileSync('mysql', ['-N', 'smartqaq_nfelia_core', '-e', q]).toString().trim();   // no shell: hashes keep their $
const today = () => sql('SELECT CURDATE()');
// Wait until the database shows what a click was meant to do (a payment goes
// through the Paystack stand-in and a redirect or two first)
const until = async (fn, ms = 15000) => { const end = Date.now() + ms; while (Date.now() < end) { if (fn()) return true; await new Promise((r) => setTimeout(r, 200)); } return false; };
const day = (n) => sql(`SELECT DATE_ADD(CURDATE(), INTERVAL ${n} DAY)`);
const php = (code) => {
  const file = path.join(OUT, 'eval.php');
  fs.writeFileSync(file, `<?php date_default_timezone_set('Africa/Lagos'); define('MAX_SEATS', 5); define('PLAN_BASE_PRICE', 1000000); define('PLAN_EXTRA_SEAT_PRICE', 500000);
require '${ROOT}/app/helpers/billing.php'; ${code}`);
  return execSync(`php ${file}`).toString().trim();
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  execSync(`php ${KIT}/seed.php ${path.join(OUT, 'app')}`);
  execSync(`mysql smartqaq_nfelia_core < ${KIT}/planner_seed.sql.txt`);
  execSync(`php ${KIT}/landing_seed.php ${OUT}`);
  fs.rmSync(MOCK, { recursive: true, force: true });
  const hash = sql("SELECT password_hash FROM users WHERE email = 'ivie@demo.nfelia'");
  sql(`DELETE FROM auth_throttle; DELETE FROM push_subscriptions; DELETE FROM subscription_payments;
       UPDATE businesses SET subdomain = 'kemievents', subdomain_status = 'active', storefront_look = 'shea' WHERE id = 2;
       UPDATE businesses SET booking_settings = '{"hours":{"mon":{"open":"09:00","close":"19:00"},"tue":{"open":"09:00","close":"19:00"},"wed":{"open":"09:00","close":"19:00"},"thu":{"open":"09:00","close":"19:00"},"fri":{"open":"09:00","close":"19:00"},"sat":{"open":"09:00","close":"19:00"},"sun":{"open":"09:00","close":"19:00"}},"notice":0,"ahead":60,"step":30,"at_once":1}' WHERE id = 1;
       DELETE FROM bookings;
       INSERT INTO users (business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES
         (1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', '${hash}', 1, 1), (1, 'staff', 'Amaka Obi', 'amaka@demo.nfelia', '${hash}', 1, 1);
       UPDATE subscriptions SET extra_seats = 2 WHERE business_id = 1;
       INSERT INTO products (business_id, name, sell_price, cost_price, stock_qty, is_service) VALUES (2, 'Gold chiavari chair hire', 2500, 1000, 200, 0)`);
  const kemiProduct = sql("SELECT id FROM products WHERE business_id = 2 LIMIT 1");
  const kemiInvoice = sql("SELECT id FROM invoices WHERE business_id = 2 LIMIT 1");
  const tunde = sql("SELECT id FROM users WHERE email = 'tunde@demo.nfelia'");
  const amaka = sql("SELECT id FROM users WHERE email = 'amaka@demo.nfelia'");
  const del = sql('SELECT id FROM deliveries WHERE business_id = 1 ORDER BY id LIMIT 1');

  const browser = await chromium.launch();
  const errors = [];
  const session = async (email) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(email + ': ' + e.message));
    page.on('dialog', (d) => d.accept());
    await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({ body: 'window.tailwind = { config: {} };', contentType: 'text/javascript' }));
    await page.route(/fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare|unpkg\.com|cdn\.jsdelivr/, (r) => r.fulfill({ body: '' }));
    if (email) {
      await page.goto(APP + '/login');
      await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    }
    return page;
  };
  // A form post the way the app's pages send it (fields and files)
  const post = (page, url, fields = {}, files = {}) => page.evaluate(async ([url, fields, files]) => {
    const fd = new FormData();
    const token = (window.Nfelia && window.Nfelia.csrf) || (document.querySelector('input[name=csrf_token]') || {}).value || '';
    if (!('csrf_token' in fields)) fd.append('csrf_token', token);
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => fd.append(k, x)); else fd.append(k, v);
    }
    for (const [k, f] of Object.entries(files)) {
      const c = document.createElement('canvas'); c.width = f.w; c.height = f.h;
      const g = c.getContext('2d'); g.fillStyle = '#c33'; g.fillRect(0, 0, f.w, f.h); g.fillStyle = '#fff'; g.fillText('paid', 10, 20);
      const blob = await new Promise((res) => c.toBlob(res, 'image/png'));
      fd.append(k, blob, 'proof.png');
    }
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return { status: r.status, url: r.url, redirected: r.redirected, text: await r.text(), type: r.headers.get('content-type') || '' };
  }, [url, fields, files]);
  const get = (page, url) => page.evaluate(async (url) => {
    const r = await fetch(url, { credentials: 'same-origin' });
    return { status: r.status, url: r.url, type: r.headers.get('content-type') || '', cache: r.headers.get('cache-control') || '' };
  }, url);
  const lines = (desc, qty, price, product = '') => ({ 'item_description[]': [desc], 'item_quantity[]': [String(qty)], 'item_unit_price[]': [String(price)], 'item_discount[]': ['0'], 'item_product_id[]': [String(product)] });

  const ivie = await session('ivie@demo.nfelia');
  await ivie.goto(APP + '/invoices/create');

  // ── A · One business can't use another's records (M1, S15) ──
  const maxInv = () => sql('SELECT COALESCE(MAX(id), 0) FROM invoices');
  let before = maxInv();
  let r = await post(ivie, APP + '/invoices/create', { customer_id: '20', issue_date: today(), deliver_mode: 'immediate', ...lines('Test', 1, 1000) });
  check('A1 an invoice for another business\'s client is refused', maxInv() === before && /Please select a client/.test(r.text), r.text.slice(0, 200));
  r = await post(ivie, APP + '/invoices/create', { customer_id: '1', bank_account_id: '2', issue_date: today(), deliver_mode: 'immediate', ...lines('Test', 1, 1000) });
  check('A2 an invoice with another business\'s bank account is refused', maxInv() === before && /one of your bank accounts/.test(r.text));
  r = await post(ivie, APP + '/invoices/create', { customer_id: '1', issue_date: today(), deliver_mode: 'immediate', ...lines('Chair hire', 1, 2500, kemiProduct) });
  const inv = maxInv();
  check('A3 another business\'s product on a line is dropped, the line kept', inv !== before && sql(`SELECT COALESCE(product_id, 'none') FROM invoice_items WHERE invoice_id = ${inv}`) === 'none');
  const maxQuo = sql('SELECT COALESCE(MAX(id), 0) FROM quotations');
  await post(ivie, APP + '/quotations/create', { customer_id: '20', issue_date: today(), ...lines('Test', 1, 1000) });
  check('A4 a quotation for another business\'s client is refused', sql('SELECT COALESCE(MAX(id), 0) FROM quotations') === maxQuo);
  const maxRec = sql('SELECT COALESCE(MAX(id), 0) FROM receipts');
  await post(ivie, APP + '/receipts/create', { customer_id: '20', issue_date: today(), method: 'cash', 'item_description[]': ['Test'], 'item_quantity[]': ['1'], 'item_unit_price[]': ['1000'], 'item_product_id[]': [''] });
  check('A5 a receipt for another business\'s client is refused', sql('SELECT COALESCE(MAX(id), 0) FROM receipts') === maxRec);
  const deliv = { recipient_name: 'Lara Coker', delivery_address: '12 Admiralty Way, Lekki', 'item_description[]': ['Deep Wave 26"'], 'item_quantity[]': ['1'], 'item_unit[]': ['set'], 'item_product_id[]': [''], 'item_notes[]': [''] };
  r = await post(ivie, APP + `/deliveries/edit/${del}`, { ...deliv, invoice_id: kemiInvoice });
  check('A6 a delivery can\'t point at another business\'s invoice', sql(`SELECT COALESCE(invoice_id, 0) FROM deliveries WHERE id = ${del}`) !== kemiInvoice && /one of your invoices/.test(r.text));
  const tok1 = sql('SELECT token FROM invoices WHERE id = 1');
  sql('UPDATE invoices SET customer_id = 20 WHERE id = 1');
  await ivie.goto(APP + '/doc/' + tok1);
  check('A7 a public invoice page never shows another business\'s client', !(await ivie.content()).includes('Ngozi Okafor'));
  sql('UPDATE invoices SET customer_id = 1 WHERE id = 1');

  // ── B · Signatures and payment proofs are private (S1, W1, W6, X9) ──
  sql(`UPDATE deliveries SET status = 'dispatched', signoff_signature_path = NULL WHERE id = ${del}`);
  const [dTok, sTok] = sql(`SELECT token, signoff_token FROM deliveries WHERE id = ${del}`).split('\t');
  const anon = await session(null);
  await anon.goto(APP + '/sign/' + sTok);
  r = await post(anon, APP + '/sign/' + sTok, { signoff_name: 'Lara Coker', signature_data: 'data:image/png;base64,' + Buffer.from('not a picture').toString('base64') });
  check('B1 a signature that isn\'t a real picture is refused', /didn.t come through/.test(r.text) && sql(`SELECT status FROM deliveries WHERE id = ${del}`) === 'dispatched', r.text);
  await anon.goto(APP + '/sign/' + sTok);
  await anon.fill('input[x-model=signoffName]', 'Lara Coker');
  await anon.locator('#signature-pad').scrollIntoViewIfNeeded();
  const pad = await anon.locator('#signature-pad').boundingBox();
  await anon.mouse.move(pad.x + 20, pad.y + 40); await anon.mouse.down(); await anon.mouse.move(pad.x + 160, pad.y + 100, { steps: 10 }); await anon.mouse.up();
  await Promise.all([anon.waitForResponse((x) => x.request().method() === 'POST'), anon.locator('button:has-text("Confirm Receipt")').click()]);
  const sig = sql(`SELECT signoff_signature_path FROM deliveries WHERE id = ${del}`);
  check('B2 a signature is saved privately under a random name', /^storage\/private\/signatures\/1\/[0-9a-f]{32}\.png$/.test(sig), sig);
  check('B3 its file can\'t be opened directly', (await get(anon, APP + '/' + sig)).status === 403);
  r = await get(anon, APP + '/delivery/' + dTok + '?signature=1');
  check('B4 the delivery\'s own link shows it, never cached', r.status === 200 && r.type === 'image/png' && /no-store/.test(r.cache), JSON.stringify(r));
  r = await get(ivie, APP + `/deliveries/signature/${del}`);
  check('B5 the owner sees it in the app', r.status === 200 && r.type === 'image/png');
  const kemi = await session('kemi@demo.nfelia');
  await kemi.goto(APP + '/dashboard');
  check('B6 another business can\'t', (await get(kemi, APP + `/deliveries/signature/${del}`)).status === 404);
  fs.mkdirSync(path.join(ROOT, 'uploads/delivery_signoffs'), { recursive: true });
  fs.copyFileSync(path.join(ROOT, sig), path.join(ROOT, `uploads/delivery_signoffs/${del}_signoff.png`));
  sql(`UPDATE deliveries SET signoff_signature_path = 'uploads/delivery_signoffs/${del}_signoff.png' WHERE id = ${del}`);
  check('B7 an old signature in uploads/ is closed to the web', (await get(anon, APP + `/uploads/delivery_signoffs/${del}_signoff.png`)).status === 403);
  check('B8 and still shows on its delivery page', (await get(anon, APP + '/delivery/' + dTok + '?signature=1')).status === 200);
  fs.rmSync(path.join(ROOT, `uploads/delivery_signoffs/${del}_signoff.png`));
  check('B9 nothing under uploads/ runs as a script', (await get(anon, APP + '/uploads/logos/1/x.php')).status === 403);

  const shop = await session(null);
  await shop.goto(SHOP + '/');
  const order = (phone, extra = {}, files = {}) => post(shop, SHOP + '/checkout', {
    channel: 'cart_pop', fulfillment_type: 'pickup', customer_name: 'Funke Ade', customer_phone: phone,
    items: JSON.stringify([{ product_id: 1, quantity: 1 }]), payment_method: 'bank_transfer', ...extra }, files);
  r = await order('0803 100 0001', {}, { payment_proof: { w: 500, h: 900 } });
  const proof = sql('SELECT payment_proof_path FROM orders ORDER BY id DESC LIMIT 1');
  const orderId = sql('SELECT id FROM orders ORDER BY id DESC LIMIT 1');
  check('B10 a payment proof is saved privately as a JPEG', /^storage\/private\/order_proofs\/1\/[0-9a-f]{32}\.jpg$/.test(proof), proof + ' ' + r.text.slice(0, 200));
  check('B11 it can\'t be opened directly', (await get(anon, APP + '/' + proof)).status === 403);
  r = await get(ivie, APP + '/orders/proof/' + orderId);
  check('B12 the owner sees it on the order', r.status === 200 && r.type === 'image/jpeg');
  await order('0803 100 0002', {}, { payment_proof: { w: 600, h: 5000 } });
  const tall = sql('SELECT payment_proof_path FROM orders ORDER BY id DESC LIMIT 1');
  const dims = execSync(`php -r 'echo implode("x", array_slice(getimagesize("${path.join(ROOT, tall)}"), 0, 2));'`).toString();
  check('B13 a very tall picture is saved at most three times its width tall', Number(dims.split('x')[1]) <= 3600, dims);
  const book = (time, extra = {}, files = {}) => post(shop, SHOP + '/book/6', { channel: 'site', customer_name: 'Yemi Ola', customer_phone: '0802 300 0' + time.replace(':', ''), date: day(3), time, ...extra }, files);
  r = await book('10:00');
  const proofDir = path.join(ROOT, 'storage/private/booking_proofs/1');
  const proofsBefore = fs.existsSync(proofDir) ? fs.readdirSync(proofDir).length : 0;
  r = await book('10:00', { bank_id: '1' }, { payment_proof: { w: 400, h: 800 } });
  const proofsAfter = fs.existsSync(proofDir) ? fs.readdirSync(proofDir).length : 0;
  check('B14 a booking for a taken time leaves no proof file behind', r.status === 409 && proofsAfter === proofsBefore, r.status + ' ' + proofsBefore + '>' + proofsAfter);

  // ── C · Roles, the founder panel, CSRF and injection (X2–X8, B23) ──
  const staff = await session('tunde@demo.nfelia');
  check('C0 the staff member is signed in', staff.url().includes('/dashboard'), staff.url());
  for (const page of ['/expenses', '/reports/profit-loss', '/debts', '/posts']) {
    await staff.goto(APP + page);
    check('C1 staff are sent back to Home from ' + page, staff.url().endsWith('/dashboard'), staff.url());
  }
  for (const page of ['/admin', '/countries', '/momo/review']) {
    await staff.goto(APP + page);
    check('C2 staff of the founder\'s business are sent back to Home from ' + page, staff.url().endsWith('/dashboard'), staff.url());
  }
  await staff.goto(APP + '/dashboard');
  check('C3 the founder panel isn\'t in a staff member\'s menu', !(await staff.content()).includes('Founder panel'));
  r = await post(staff, APP + '/api/set-country', { country_id: '1' });
  check('C4 staff can\'t change the business\'s country', r.status === 403);
  r = await post(staff, APP + '/billing/initialize', { kind: 'month' });
  check('C5 staff can\'t start a payment', r.status === 403);
  await staff.goto(APP + '/logout/logout');
  await staff.goto(APP + '/dashboard');
  check('C6 a link can\'t sign anyone out', staff.url().includes('/dashboard'), staff.url());
  await ivie.goto(APP + '/dashboard');
  r = await post(ivie, APP + '/settings/update-role/' + tunde, { role: 'admin', csrf_token: 'wrong' });
  check('C7 changing a role needs the CSRF token', r.status === 403 && sql(`SELECT role FROM users WHERE id = ${tunde}`) === 'staff');
  r = await post(ivie, APP + `/deliveries/edit/${del}`, { ...deliv, provider: '<img src=x onerror=alert(1)>' });
  sql(`UPDATE deliveries SET status = 'dispatched' WHERE id = ${del}`);
  await post(ivie, APP + `/deliveries/edit/${del}`, { ...deliv, provider: '<img src=x onerror=alert(1)>' });
  check('C8 a delivery provider must be one of the listed ones', sql(`SELECT COALESCE(provider, 'none') FROM deliveries WHERE id = ${del}`) === 'none');
  const qTok = sql('SELECT token FROM quotations WHERE id = 40');
  await anon.goto(APP + '/doc/' + qTok);
  r = await post(anon, APP + '/doc/' + qTok, { action: 'decline', reason: 'Too dear' });
  const r2 = await post(anon, APP + '/doc/' + qTok, { action: 'comment', message: 'Changed my mind' });
  check('C9 a client can answer a quotation once', r.status === 200 && r2.status === 409 && sql('SELECT status FROM quotations WHERE id = 40') === 'declined', r.status + ' ' + r2.status);
  r = await post(ivie, APP + '/purge/destroy', { module: 'users', ids: '[1]', password: 'DemoPass1', pin: '1234' });
  check('C10 purge refuses a table that isn\'t on its list', r.status === 422);

  // ── D · Billing (X1, B1–B23) ──
  const access = (sub, on) => php(`echo billing_access(${sub}, '${on}');`);
  check('D1 a trial runs to the end of its last day', access("['status'=>'trial','trial_ends_at'=>'2026-10-20']", '2026-10-20') === 'trial' && access("['status'=>'trial','trial_ends_at'=>'2026-10-20']", '2026-10-21') === 'locked');
  const paid = "['status'=>'active','current_period_end'=>'2026-10-10']";
  check('D2 a paid month gives 3 days\' grace, then locks', access(paid, '2026-10-10') === 'paid' && access(paid, '2026-10-13') === 'grace' && access(paid, '2026-10-14') === 'locked');
  check('D3 a business past its date when this shipped is left as it is', access("['status'=>'active','current_period_end'=>'2026-09-01','expiry_exempt'=>1]", '2026-10-18') === 'paid');
  check('D4 a cancelled month runs to its end', access("['status'=>'cancelled','current_period_end'=>'2026-10-20']", '2026-10-20') === 'paid' && access("['status'=>'cancelled','current_period_end'=>'2026-10-20']", '2026-10-21') === 'locked');
  check('D5 31 January plus a month is 28 February', php("echo billing_add_month('2027-01-31');") === '2027-02-28');
  // Kemi's business: Ivie Luxe is business 1, which locally stands in for
  // Nfelia's own business, and that one never locks
  sql(`UPDATE subscriptions SET status = 'active', current_period_end = '${day(-10)}', expiry_exempt = 0 WHERE business_id = 2`);
  await kemi.goto(APP + '/dashboard');
  check('D6 a month 10 days over locks the app', kemi.url().includes('tab=billing'), kemi.url());
  await shop.goto('http://kemievents.localhost:8000/');
  check('D7 and closes the shop', (await shop.content()).includes('This shop is closed for now'));
  await shop.goto(SHOP + '/');
  sql(`UPDATE subscriptions SET current_period_end = '${day(-1)}' WHERE business_id = 2`);
  await kemi.goto(APP + '/dashboard');
  check('D8 a day over: still open, with a "pay by" warning', kemi.url().includes('/dashboard') && /Pay by/.test(await kemi.content()), kemi.url());
  sql(`UPDATE subscriptions SET current_period_end = '${day(-10)}', expiry_exempt = 1 WHERE business_id = 2`);
  await kemi.goto(APP + '/dashboard');
  check('D9 a business left as it is stays open', kemi.url().includes('/dashboard'));
  sql(`UPDATE subscriptions SET current_period_end = '${day(20)}', expiry_exempt = 0 WHERE business_id = 2`);
  sql(`UPDATE subscriptions SET status = 'active', current_period_end = '${day(-10)}', expiry_exempt = 1 WHERE business_id = 1`);
  await ivie.goto(APP + '/dashboard');
  check('D9b Nfelia\'s own business never locks', ivie.url().includes('/dashboard'));

  const amakaPage = await session('amaka@demo.nfelia');
  check('D10a the second staff member is signed in', amakaPage.url().includes('/dashboard'), amakaPage.url());
  await ivie.goto(APP + '/settings?tab=billing');
  await ivie.locator(`input[type=checkbox][value="${amaka}"]`).uncheck();
  const refBefore = sql('SELECT COALESCE(last_payment_ref, 0) FROM subscriptions WHERE business_id = 1');
  await ivie.locator('button:has-text("Pay for the month")').click();
  await until(() => sql('SELECT COALESCE(last_payment_ref, 0) FROM subscriptions WHERE business_id = 1') !== refBefore);
  await ivie.waitForURL(/tab=billing/);
  const ref1 = sql("SELECT last_payment_ref FROM subscriptions WHERE business_id = 1");
  const sub1 = sql('SELECT status, current_period_start, current_period_end, extra_seats, expiry_exempt FROM subscriptions WHERE business_id = 1');
  check('D10 paying for a month opens a new month from today', sub1 === ['active', today(), php(`echo billing_add_month('${today()}');`), '1', '0'].join('\t'), sub1);
  check('D11 staff the owner left unticked are switched off', sql(`SELECT is_active FROM users WHERE id = ${amaka}`) === '0' && sql(`SELECT is_active FROM users WHERE id = ${tunde}`) === '1');
  await amakaPage.goto(APP + '/dashboard');
  check('D12 and signed out on their next page', amakaPage.url().includes('/login'), amakaPage.url());
  await ivie.goto(APP + '/billing/callback?reference=' + ref1);
  check('D13 the same payment reference applies once', sql(`SELECT COUNT(*) FROM subscription_history WHERE paystack_reference = '${ref1}'`) === '1'
    && sql('SELECT current_period_end FROM subscriptions WHERE business_id = 1') === sub1.split('\t')[2] && /already on your account/.test(await ivie.content()));
  await kemi.goto(APP + '/settings?tab=billing');
  r = await post(kemi, APP + '/billing/initialize', { kind: 'month', extra_seats: '0' });
  const kRef = new URL(JSON.parse(r.text).authorization_url).searchParams.get('reference');
  const kemiEnd = sql('SELECT current_period_end FROM subscriptions WHERE business_id = 2');
  await ivie.goto(APP + '/billing/callback?reference=' + kRef);
  check('D14 another business\'s payment can\'t be claimed', /another account/.test(await ivie.content())
    && sql('SELECT current_period_end FROM subscriptions WHERE business_id = 1') === sub1.split('\t')[2] && sql('SELECT current_period_end FROM subscriptions WHERE business_id = 2') === kemiEnd);
  r = await post(ivie, APP + '/billing/initialize', { kind: 'month', extra_seats: '1', 'covered[]': [tunde] });
  const ref2 = new URL(JSON.parse(r.text).authorization_url).searchParams.get('reference');
  fs.writeFileSync(path.join(MOCK, 'override.json'), JSON.stringify({ [ref2]: { amount: 100 } }));
  await ivie.goto(APP + '/billing/callback?reference=' + ref2);
  check('D15 a payment for less than was asked is refused', /doesn.t match/.test(await ivie.content()) && sql('SELECT current_period_end FROM subscriptions WHERE business_id = 1') === sub1.split('\t')[2]);
  const hook = (event, data, sign = true) => {
    const body = JSON.stringify({ event, data });
    return fetch(APP + '/api/webhooks', { method: 'POST', body, headers: { 'Content-Type': 'application/json', 'X-Paystack-Signature': sign ? crypto.createHmac('sha512', SECRET).update(body).digest('hex') : 'x' } })
      .then(async (res) => ({ status: res.status, text: await res.text() }));
  };
  const verified = JSON.parse(execSync(`curl -s http://127.0.0.1:8096/transaction/verify/${ref1}`).toString()).data;
  r = await hook('charge.success', verified);
  check('D16 the webhook for a payment the callback applied does nothing', /already/.test(r.text) && sql(`SELECT COUNT(*) FROM subscription_history WHERE paystack_reference = '${ref1}'`) === '1', r.text);
  check('D17 a webhook with a wrong signature is refused', (await hook('charge.success', verified, false)).status === 401);
  const meta = { metadata: { business_id: 1 }, customer: { customer_code: 'CUS_mock' } };
  await hook('subscription.create', { ...meta, subscription_code: 'SUB_one', email_token: 'tok', plan: { plan_code: 'PLN_x' } });
  r = await hook('subscription.disable', { ...meta, subscription_code: 'SUB_one' });
  check('D18 a card subscription\'s create and disable events both count', sql('SELECT status FROM subscriptions WHERE business_id = 1') === 'cancelled', r.text);
  sql("UPDATE subscriptions SET status = 'active' WHERE business_id = 1");
  await hook('subscription.disable', { ...meta, subscription_code: 'SUB_other' });
  check('D19 ending a subscription that isn\'t on file changes nothing', sql('SELECT status FROM subscriptions WHERE business_id = 1') === 'active');
  await ivie.goto(APP + '/settings?tab=billing');
  await ivie.locator('button:has-text("Pay for the seats")').click();
  await until(() => sql('SELECT extra_seats FROM subscriptions WHERE business_id = 1') === '2');
  check('D20 seats can be added to the month already paid', sql('SELECT extra_seats FROM subscriptions WHERE business_id = 1') === '2');
  // The payment's redirect back to Billing may still be landing: let it
  for (let i = 0; i < 3; i++) {
    try { await ivie.goto(APP + '/settings?tab=billing'); break; }
    catch (e) { if (!/interrupted/.test(e.message) || i === 2) throw e; await ivie.waitForLoadState('load'); }
  }
  await ivie.locator('button:has-text("Cancel Subscription")').click();
  await ivie.locator('button:has-text("Confirm Cancellation")').click();
  await until(() => sql('SELECT status FROM subscriptions WHERE business_id = 1') === 'cancelled');
  await ivie.waitForURL(/tab=billing/);
  await ivie.goto(APP + '/dashboard');
  check('D21 cancelling keeps Nfelia open to the end of the month', sql('SELECT status FROM subscriptions WHERE business_id = 1') === 'cancelled' && ivie.url().includes('/dashboard'));
  sql("UPDATE subscriptions SET status = 'active', cancelled_at = NULL WHERE business_id = 1");

  // Mobile Money: Kemi's business moves to a Mobile Money country
  sql(`INSERT INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, momo_number, monthly_price, extra_seat_price, trial_days, is_active)
       VALUES (2, 'Cameroon', 'CM', '', 'Central African CFA franc', 'XAF', 'FCFA', 'mobile_money', '677000000', 6500, 3000, 14, 1)
       ON DUPLICATE KEY UPDATE payment_method = 'mobile_money', is_active = 1;
       UPDATE businesses SET country_id = 2 WHERE id = 2`);
  const kemiSub = sql('SELECT status, current_period_end, extra_seats FROM subscriptions WHERE business_id = 2');
  await kemi.goto(APP + '/momo/checkout?kind=month&seats=0&covered=');
  check('D22 the Mobile Money page asks for the country\'s price', (await kemi.content()).includes('6,500'));
  r = await post(kemi, APP + '/momo/submit', { kind: 'month', seats: '0', covered: '', reference_number: 'MP261018.1234.A001' }, { receipt: { w: 400, h: 700 } });
  check('D23 a receipt waiting to be checked unlocks and adds nothing', sql('SELECT status, current_period_end, extra_seats FROM subscriptions WHERE business_id = 2') === kemiSub
    && sql("SELECT COUNT(*) FROM subscription_payments WHERE business_id = 2 AND status = 'pending_verification'") === '1');
  check('D24 the founder is told at once, marked urgent', sql("SELECT COUNT(*) FROM notifications WHERE business_id = 1 AND type = 'momo_submitted' AND title LIKE 'Urgent:%'") === '1');
  r = await post(kemi, APP + '/momo/submit', { kind: 'month', seats: '0', covered: '', reference_number: 'MP261018.1234.A002' }, { receipt: { w: 400, h: 700 } });
  check('D25 one receipt waits at a time', sql("SELECT COUNT(*) FROM subscription_payments WHERE business_id = 2") === '1');
  const payId = sql('SELECT id FROM subscription_payments WHERE business_id = 2');
  const tundePage = await session('tunde@demo.nfelia');
  await tundePage.goto(APP + '/dashboard');
  r = await post(tundePage, APP + '/momo/approve', { payment_id: payId });
  check('D26 only the founder\'s owner can approve it', !/"success":true/.test(r.text) && sql(`SELECT status FROM subscription_payments WHERE id = ${payId}`) === 'pending_verification');
  await ivie.goto(APP + '/momo/review');
  r = await post(ivie, APP + '/momo/approve', { payment_id: payId });
  const kemiEnd2 = sql('SELECT current_period_end FROM subscriptions WHERE business_id = 2');
  check('D27 approving it adds a month after the one already paid', /"success":true/.test(r.text) && kemiEnd2 === php(`echo billing_add_month('${kemiEnd}');`), r.text + ' ' + kemiEnd2);
  r = await post(ivie, APP + '/momo/approve', { payment_id: payId });
  check('D28 a receipt is approved once', !/"success":true/.test(r.text) && sql('SELECT current_period_end FROM subscriptions WHERE business_id = 2') === kemiEnd2);
  await kemi.goto(APP + '/referrals');
  const code1 = sql('SELECT referral_code FROM businesses WHERE id = 2');
  await kemi.goto(APP + '/referrals');
  check('D29 a business keeps its referral code', code1 !== '' && code1 === sql('SELECT referral_code FROM businesses WHERE id = 2') && (await kemi.content()).includes(code1));

  const signup = async (email) => {
    const page = await session(null);
    await page.goto(APP + '/register');
    const res = await post(page, APP + '/register', { business_name: 'Ada Bakes', full_name: 'Ada Obi', email, password: 'BakePass123', password_confirm: 'BakePass123' });
    return { page, res };
  };
  let s1 = await signup('ada.obi.bakes@gmail.com');
  const adaBiz = sql("SELECT business_id FROM users WHERE email = 'ada.obi.bakes@gmail.com'");
  const s2 = await signup('adaobibakes+again@gmail.com');
  check('D30 a Gmail dot or plus variant of an owner\'s address can\'t start another trial', adaBiz !== '' && sql("SELECT COUNT(*) FROM users WHERE email = 'adaobibakes+again@gmail.com'") === '0');
  sql(`UPDATE subscriptions SET trial_ends_at = '${day(-1)}' WHERE business_id = ${adaBiz}; UPDATE businesses SET onboarding_complete = 0, onboarding_step = 1 WHERE id = ${adaBiz}`);
  let loop = '';
  try { await s1.page.goto(APP + '/dashboard'); } catch (e) { loop = e.message; }
  check('D31 an expired trial can still finish onboarding (no redirect loop)', !loop && s1.page.url().includes('onboarding'), loop || s1.page.url());

  // ── E · Shop forms: spam limits, booking holds, phone notifications (W2, S8, P4, P5) ──
  sql('DELETE FROM auth_throttle');
  r = await order('0803 100 0003', { website: 'http://spam.example' });
  check('E1 a form with the robots-only field filled is refused', r.status === 422);
  let first429 = 0;
  for (let i = 1; i <= 11; i++) {
    r = await order('0803 200 00' + String(i).padStart(2, '0'), { payment_method: 'none' });
    if (r.status === 429 && !first429) first429 = i;
  }
  check('E2 one visitor can place 10 orders an hour, not more', first429 === 11, 'first refused: ' + first429);
  sql('DELETE FROM auth_throttle');
  const d5 = day(5);
  sql(`INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due, currency_code, customer_name, customer_phone, channel, payment_method, status, created_at) VALUES
       (1, 6, 'Wig install and styling', 'BKG-9001', '${'b'.repeat(32)}', 'slot', '${d5} 10:00:00', '${d5} 11:00:00', 1, 25000, 25000, 0, 'NGN', 'Old Request', '0803 999 0001', 'site', 'none', 'pending', NOW() - INTERVAL 25 HOUR),
       (1, 6, 'Wig install and styling', 'BKG-9002', '${'c'.repeat(32)}', 'slot', '${d5} 12:00:00', '${d5} 13:00:00', 1, 25000, 25000, 0, 'NGN', 'New Request', '0803 999 0002', 'site', 'none', 'pending', NOW())`);
  const free = await shop.evaluate(async (url) => (await (await fetch(url)).json()).times.map((t) => t.t), SHOP + '/book/6/free?date=' + d5);
  check('E3 a request older than 24 hours no longer holds its time', free.includes('10:00') && !free.includes('12:00'), free.join(' '));
  sql(`INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due, currency_code, customer_name, customer_phone, channel, payment_method, status, created_at) VALUES
       (1, 6, 'Wig install and styling', 'BKG-9003', '${'d'.repeat(32)}', 'slot', '${d5} 10:00:00', '${d5} 11:00:00', 1, 25000, 25000, 0, 'NGN', 'Taker', '0803 999 0003', 'site', 'none', 'pending', NOW())`);
  const oldId = sql("SELECT id FROM bookings WHERE booking_number = 'BKG-9001'");
  await ivie.goto(APP + '/bookings/view/' + oldId);
  r = await post(ivie, APP + '/bookings/confirm/' + oldId);
  check('E4 confirming an old request whose time was taken since is refused', sql(`SELECT status FROM bookings WHERE id = ${oldId}`) === 'pending' && /booked by someone else/.test(r.text));
  const keys = { p256dh: Buffer.concat([Buffer.from([4]), crypto.randomBytes(64)]).toString('base64url'), auth: crypto.randomBytes(16).toString('base64url') };
  const pushPost = (ep) => ivie.evaluate(async ([ep, keys]) => {
    const res = await fetch('/tasks/push-subscribe', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ endpoint: ep, keys }) });
    return res.status;
  }, [ep, keys]);
  await ivie.goto(APP + '/tasks');
  check('E5 a push address that isn\'t a browser push service is refused', await pushPost('https://evil.example/collect') === 422);
  check('E6 a real push service is accepted', await pushPost('https://fcm.googleapis.com/fcm/send/lockdown-test') === 200);
  await ivie.goto(APP + '/more');
  await Promise.all([ivie.waitForURL(/login/), ivie.locator('form[action$="/logout"] button').first().click()]);
  check('E7 signing out stops that person\'s notifications on that phone', sql("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint LIKE '%lockdown-test'") === '0');

  check('Z no script errors on any page', errors.length === 0, errors.join(' | '));
  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} checks passed`);
  process.exit(passed === results.length ? 0 : 1);
})().catch((e) => { console.error(e); process.exit(1); });
