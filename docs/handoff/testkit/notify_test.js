// SUPERSEDED for notifications by tests/notifications/ (see docs/handoff/Notifications.md):
// its shop-order checks post to the retired /public/cart-checkout. The Share PDF
// and phone-contact checks below still describe how those work.
// Notifications, Share PDF and phone contacts, end to end, against the local
// copy on :8099 (see up.sh). Phones are stood in for by push_endpoint.php on
// :8097: every subscription points there, one path per person, and each
// notification is decrypted here exactly as the phone would.
//   node notify_test.js APPDIR OUTDIR
// The database is put back as it was at the end, and so is APPDIR/.env (the
// test switches phone alerts on with a throwaway key pair).
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execSync, spawn } = require('child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const BASE = 'http://127.0.0.1:8099';
const APP = process.argv[2];
const OUT = process.argv[3] || path.join(__dirname, 'build', 'notify');
if (!APP) { console.error('usage: node notify_test.js APPDIR OUTDIR'); process.exit(2); }
const PUSH = path.join(OUT, 'push');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';
const PHONE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: ANDROID_UA, timezoneId: 'Africa/Lagos' };
const DESK = { viewport: { width: 1280, height: 900 }, timezoneId: 'Africa/Lagos' };

// SQL goes in on stdin, so nothing in it (a password hash's $ signs) meets the
// shell; on Lagos time and in UTF-8 (₦), like the app's own connection.
const q = (sql) => execSync('mysql -N --default-character-set=utf8mb4 smartqaq_nfelia_core', { input: "SET time_zone = '+01:00';\n" + sql }).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
const errors = [];
function watch(page, tag) {
  page.on('pageerror', (e) => errors.push(tag + ' pageerror: ' + e.message));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(tag + ' console: ' + m.text());
  });
}
const still = '*{animation:none!important;transition:none!important}';
const shot = (page, name) => page.screenshot({ path: path.join(OUT, name + '.png') });
const pause = (ms) => new Promise((r) => setTimeout(r, ms));

async function signIn(ctx, email = 'ivie@demo.nfelia') {
  const page = await ctx.newPage();
  watch(page, email);
  await page.goto(BASE + '/login');
  await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}
const csrf = (page) => page.evaluate(() => window.Nfelia.csrf);
const form = (o) => Object.entries(o).flatMap(([k, v]) => (Array.isArray(v) ? v : [v]).map((x) => encodeURIComponent(k) + '=' + encodeURIComponent(x))).join('&');
// The session cookie is Secure: Chrome sends it to 127.0.0.1, Playwright's own
// request client does not. So signed-in requests go through the page.
const call = (page, url, opts) => page.evaluate(async ([u, o]) => {
  const r = await fetch(u, { method: 'POST', credentials: 'same-origin', ...o });
  const text = await r.text();
  let json = null;
  try { json = JSON.parse(text); } catch (e) {}
  return { status: r.status, url: r.url, json };
}, [BASE + url, opts]);
const post = (page, url, body, ajax = false) => call(page, url, {
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...(ajax ? { 'X-Requested-With': 'XMLHttpRequest' } : {}) },
  body: form(body),
});

// ---------- The stand-in phones ----------
// One key pair and auth secret, as a browser makes them, shared by every
// subscription; the endpoint path says whose phone it is (/ok/u6 is user 6).
const phone = crypto.createECDH('prime256v1');
phone.generateKeys();
const phonePub = phone.getPublicKey();
const authSecret = crypto.randomBytes(16);
function subscribe(userId, bizId) {
  const ep = `http://127.0.0.1:8097/ok/u${userId}`;
  q(`INSERT INTO push_subscriptions (business_id, user_id, endpoint, endpoint_hash, p256dh, auth) VALUES (${bizId}, ${userId}, '${ep}', SHA2('${ep}', 256), '${phonePub.toString('base64url')}', '${authSecret.toString('base64url')}')`);
}
// RFC 8291, the receiving side.
function decrypt(body) {
  const salt = body.subarray(0, 16);
  const serverPub = body.subarray(21, 21 + body[20]);
  const ct = body.subarray(21 + body[20]);
  const hk = (ikm, s, info, n) => Buffer.from(crypto.hkdfSync('sha256', ikm, s, Buffer.from(info, 'binary'), n));
  const ikm = hk(phone.computeSecret(serverPub), authSecret, Buffer.concat([Buffer.from('WebPush: info\0'), phonePub, serverPub]).toString('binary'), 32);
  const d = crypto.createDecipheriv('aes-128-gcm', hk(ikm, salt, 'Content-Encoding: aes128gcm\0', 16), hk(ikm, salt, 'Content-Encoding: nonce\0', 12));
  d.setAuthTag(ct.subarray(ct.length - 16));
  const plain = Buffer.concat([d.update(ct.subarray(0, ct.length - 16)), d.final()]);
  let end = plain.length;
  while (end > 0 && plain[end - 1] === 0) end--;
  return JSON.parse(plain.subarray(0, end - 1).toString());   // drop the 0x02 delimiter
}
/** Every notification the phones received since the last clear: { user, urgency, msg }. */
function received() {
  return fs.readdirSync(PUSH).filter((f) => f.endsWith('.json')).sort((a, b) => parseInt(a) - parseInt(b)).map((f) => {
    const r = JSON.parse(fs.readFileSync(path.join(PUSH, f)));
    return { user: +(r.path.match(/\/u(\d+)/) || [])[1], urgency: r.headers.urgency, msg: decrypt(Buffer.from(r.body, 'base64')) };
  });
}
const clearPhones = () => fs.readdirSync(PUSH).forEach((f) => fs.unlinkSync(path.join(PUSH, f)));
const users = (list) => list.map((p) => p.user).sort((a, b) => a - b).join(',');

/** Runs the cron at a Lagos time today ("12:05"). */
function cron(at) {
  const today = q('SELECT CURDATE()');
  return execSync(`cd '${APP}' && TZ=Africa/Lagos faketime '${today} ${at}:00' php cron/planner.php 2>&1`).toString().trim();
}

(async () => {
  fs.mkdirSync(PUSH, { recursive: true });
  clearPhones();
  const snapshot = path.join(OUT, 'db_before.sql');
  execSync(`mysqldump --single-transaction --routines --triggers smartqaq_nfelia_core > '${snapshot}'`);
  const envFile = path.join(APP, '.env');
  const envBefore = fs.readFileSync(envFile, 'utf8');
  const phones = spawn('php', ['-S', '127.0.0.1:8097', path.join(__dirname, 'push_endpoint.php')], { env: { ...process.env, PUSH_DIR: PUSH }, stdio: 'ignore' });
  const browser = await chromium.launch({ executablePath: CHROME }).catch(() => chromium.launch());

  try {
    // Ivie's shop: Ivie (owner, 1), Ada (admin, 6), Tunde (staff, 3) and a
    // former admin (7). Kemi (2) owns another shop.
    const hash = execSync(`php -r "echo password_hash('DemoPass1', PASSWORD_BCRYPT);"`).toString();
    q(`DELETE FROM users WHERE id IN (3, 6, 7)`);
    q(`INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES
       (3, 1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', '${hash}', 1, 1),
       (6, 1, 'admin', 'Ada Nwosu', 'ada@demo.nfelia', '${hash}', 1, 1),
       (7, 1, 'admin', 'Old Admin', 'oldadmin@demo.nfelia', '${hash}', 1, 0)`);
    q(`UPDATE subscriptions SET status = 'active', trial_ends_at = NULL WHERE business_id IN (1, 2)`);
    q(`UPDATE users SET reminder_email = 0, morning_email = 0`);   // no mail server here
    q(`DELETE FROM push_subscriptions`);
    q(`DELETE FROM auth_throttle WHERE bucket = 'order_push'`);
    [[1, 1], [2, 2], [3, 1], [6, 1], [7, 1]].forEach(([u, b]) => subscribe(u, b));

    // ---------- New people get the defaults ----------
    console.log('Defaults');
    const d = q(`SELECT notify_money, notify_reminders, notify_low_stock, quiet_hours, quiet_from, quiet_to FROM users WHERE id = 6`).split('\t');
    ok(d.join(',') === '1,1,0,0,21:00:00,07:00:00', 'money in and reminders on; low stock and quiet hours off; quiet 9pm to 7am: ' + d.join(','));

    // ---------- Phone alerts not set up on the server ----------
    console.log('Phone card, before phone alerts are set up');
    {
      const ctx = await browser.newContext(PHONE);
      const page = await signIn(ctx);
      await page.goto(BASE + '/more#notifications');
      await page.waitForSelector('#nf-notify[open]', { timeout: 5000 }).catch(() => {});
      ok(await page.locator('#nf-notify[open]').count() === 1, '/more#notifications opens the sheet');
      ok(/not switched on for Nfelia yet/.test(await page.locator('[data-push-note]').innerText()), 'without a server key: "' + await page.locator('[data-push-note]').innerText() + '"');
      ok(!(await page.locator('[data-push-on]').isVisible()), 'and no Turn on button');
      await ctx.close();
    }

    // Phone alerts on for the copy, the way Tom has them on the server.
    fs.writeFileSync(envFile, envBefore + '\n' + execSync(`php '${path.join(APP, 'bin/vapid-keys.php')}'`).toString());

    // ---------- The sheet ----------
    console.log('More > Notifications');
    const ctx = await browser.newContext(PHONE);
    // Stand in for the phone's own push service: Chrome here has none.
    await ctx.addInitScript(() => {
      let real;
      Object.defineProperty(window, 'NfeliaPush', {
        configurable: true,
        get() { return real; },
        set(v) {
          real = Object.assign({}, v, {
            current: () => Promise.resolve(window.__sub || null),
            enable: () => { window.__sub = { endpoint: 'test' }; return Promise.resolve(window.__sub); },
            disable: () => { window.__sub = null; return Promise.resolve(); },
          });
        },
      });
    });
    let page = await signIn(ctx);
    await page.goto(BASE + '/more');
    await page.click('[data-open="nf-notify"]');
    await page.waitForSelector('#nf-notify[open]');
    await page.waitForTimeout(400);
    const sw = (name) => page.locator(`#nf-notify input[name="${name}"]`);
    ok(await sw('notify_money').isChecked() && await sw('notify_reminders').isChecked(), 'Money in and Reminders are on');
    ok(!(await sw('notify_low_stock').isChecked()) && !(await sw('quiet_hours').isChecked()), 'Low stock and Quiet hours are off');
    ok(!(await page.locator('[data-quiet]').isVisible()), 'quiet hours times stay hidden while off');
    ok(await page.locator('[data-push-on]').isVisible() && /Get alerts here/.test(await page.locator('[data-push-note]').innerText()), 'this phone: Turn on');
    await page.addStyleTag({ content: still });
    await shot(page, 'sheet');

    await page.click('[data-push-on]');
    await page.waitForSelector('[data-push-controls]:not([hidden])', { timeout: 3000 }).catch(() => {});
    ok(/^On\./.test(await page.locator('[data-push-note]').innerText()) && !(await page.locator('[data-push-on]').isVisible()), 'Turn on: the phone shows as on, with Send a test and Turn off');
    clearPhones();
    await page.click('[data-push-test]');
    await page.waitForFunction(() => /Sent|Nothing/.test(document.querySelector('[data-saved]').textContent), null, { timeout: 8000 }).catch(() => {});
    ok(/Sent\. Check your phone\./.test(await page.locator('[data-saved]').innerText()), 'Send a test: "' + await page.locator('[data-saved]').innerText() + '"');
    const test = received();
    ok(test.length === 1 && test[0].user === 1 && test[0].msg.title === 'Notifications work', 'and Ivie\'s phone gets it, readable: ' + JSON.stringify(test.map((p) => [p.user, p.msg.title])));
    await page.click('[data-push-off]');
    await page.waitForSelector('[data-push-on]:not([hidden])', { timeout: 3000 }).catch(() => {});
    ok(await page.locator('[data-push-on]').isVisible(), 'Turn off: back to Turn on');

    await sw('notify_low_stock').check();
    await page.waitForFunction(() => document.querySelector('[data-saved]').textContent === 'Saved', null, { timeout: 4000 }).catch(() => {});
    ok(await page.locator('[data-saved]').innerText() === 'Saved', 'a change says Saved');
    ok(q('SELECT notify_low_stock FROM users WHERE id = 1') === '1', 'and is kept');
    await sw('quiet_hours').check();
    ok(await page.locator('[data-quiet]').isVisible(), 'Quiet hours on shows the times');
    await page.fill('#nf-notify input[name=quiet_from]', '22:30');
    await page.locator('#nf-notify input[name=quiet_from]').dispatchEvent('change');
    await sw('notify_money').uncheck();
    await page.waitForTimeout(900);
    ok(q('SELECT CONCAT_WS(",", quiet_hours, quiet_from, quiet_to, notify_money) FROM users WHERE id = 1') === '1,22:30:00,07:00:00,0', 'quiet hours 22:30 to 07:00 and Money in off are kept');
    await page.reload();
    await page.click('[data-open="nf-notify"]');
    await page.waitForSelector('#nf-notify[open]');
    ok(await sw('quiet_hours').isChecked() && await page.locator('#nf-notify input[name=quiet_from]').inputValue() === '22:30' && !(await sw('notify_money').isChecked()), 'and still there after a reload');
    await shot(page, 'sheet-changed');

    // The bell's settings link.
    await page.goto(BASE + '/dashboard');
    await page.click('button[aria-label="Notifications"], [data-bell]', { timeout: 3000 }).catch(() => {});
    const link = page.locator('a[aria-label="Notification settings"]');
    ok(await link.count() === 1, 'the bell has a Notification settings link');
    await link.first().waitFor({ state: 'visible' });
    await link.first().click();
    await page.waitForSelector('#nf-notify[open]', { timeout: 5000 }).catch(() => {});
    ok(new URL(page.url()).pathname === '/more' && await page.locator('#nf-notify[open]').count() === 1, 'which opens the sheet: ' + page.url());
    await page.click('#nf-notify [data-close]');
    await page.waitForFunction(() => !document.getElementById('nf-notify').open, null, { timeout: 3000 }).catch(() => {});
    await page.click('button[aria-label="Notifications"]');
    await link.first().waitFor({ state: 'visible' });
    await page.waitForTimeout(300);
    await link.first().click();
    await page.waitForSelector('#nf-notify[open]', { timeout: 3000 }).catch(() => {});
    ok(await page.locator('#nf-notify[open]').count() === 1, 'and again from the More page itself');

    // The API on its own.
    const api = (body, token) => call(page, '/api/notifications?action=prefs', {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-CSRF-Token': token } : {}) },
      body: JSON.stringify(body),
    });
    let r = await api({ notify_low_stock: false });
    ok(r.status === 403 && q('SELECT notify_low_stock FROM users WHERE id = 1') === '1', 'no CSRF token: 403, nothing changes');
    r = await api({ quiet_from: '25:00', quiet_to: '06:15', notify_money: true }, await csrf(page));
    const prefs = r.json.prefs;
    ok(prefs.quiet_from === '22:30' && prefs.quiet_to === '06:15' && prefs.notify_money === true && prefs.notify_low_stock === true,
       'a bad time is ignored, the rest saved, anything not sent is kept: ' + JSON.stringify(prefs));
    const anon = await browser.newContext();
    r = await anon.request.post(BASE + '/api/notifications?action=prefs', { headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, data: '{"notify_money":false}' });
    ok(r.status() === 401, 'signed out: 401');
    await anon.close();
    q(`UPDATE users SET notify_money = 1, notify_low_stock = 0, quiet_hours = 0, quiet_from = '21:00:00', quiet_to = '07:00:00' WHERE id = 1`);

    // Phone states that need a different browser.
    {
      const blocked = await browser.newContext(PHONE);
      await blocked.addInitScript(() => Object.defineProperty(Notification, 'permission', { get: () => 'denied' }));
      const p = await signIn(blocked);
      await p.goto(BASE + '/more#notifications');
      await p.waitForSelector('#nf-notify[open]');
      ok(/blocked for Nfelia/.test(await p.locator('[data-push-note]').innerText()), 'notifications blocked in the browser: "' + await p.locator('[data-push-note]').innerText() + '"');
      await blocked.close();
      const iphone = await browser.newContext({ ...PHONE, userAgent: IPHONE_UA });
      await iphone.addInitScript(() => { delete window.PushManager; });
      const p2 = await signIn(iphone);
      await p2.goto(BASE + '/more#notifications');
      await p2.waitForSelector('#nf-notify[open]');
      ok(/add Nfelia to your Home Screen first/.test(await p2.locator('[data-push-note]').innerText()), 'iPhone in Safari: "' + await p2.locator('[data-push-note]').innerText() + '"');
      await iphone.close();
      const staff = await browser.newContext(PHONE);
      const p3 = await signIn(staff, 'tunde@demo.nfelia');
      await p3.goto(BASE + '/more#notifications');
      await p3.waitForSelector('#nf-notify[open]');
      ok(await p3.locator('#nf-notify input[name=notify_money]').count() === 0 && await p3.locator('#nf-notify input[name=notify_reminders]').count() === 1, 'staff: no Money in switch (it never reaches them), the rest are there');
      await staff.close();
    }

    // ---------- Money in ----------
    console.log('Money in');
    clearPhones();
    await page.goto(BASE + '/invoices/view/5');
    r = await post(page, '/invoices/record_payment/5', { csrf_token: await csrf(page), amount: 10000, payment_date: q('SELECT CURDATE()'), method: 'bank_transfer' }, true);
    ok(r.json && r.json.success === true, 'Ivie records ₦10,000 on IVL-INV-136');
    await pause(300);
    let got = received();
    ok(users(got) === '6', 'only Ada\'s phone buzzes: not Ivie (she did it), staff, a former admin or another shop: ' + users(got));
    const payer5 = q('SELECT c.full_name FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = 5');
    ok(got[0] && got[0].msg.title === '₦10,000 received' && got[0].msg.body === `${payer5} paid IVL-INV-136. ₦20,000 left to pay.` && got[0].msg.url === 'invoices/view/5' && got[0].urgency === 'high',
       'it reads: ' + JSON.stringify(got[0] && got[0].msg));

    clearPhones();
    q('UPDATE users SET notify_money = 0 WHERE id = 6');
    r = await post(page, '/invoices/record_payment/5', { csrf_token: await csrf(page), amount: 20000, payment_date: q('SELECT CURDATE()'), method: 'cash' }, true);
    await pause(300);
    ok(r.json && r.json.fully_paid === true && received().length === 0, 'Ada turned Money in off: nobody buzzes');
    q("UPDATE users SET notify_money = 1, quiet_hours = 1, quiet_from = DATE_FORMAT(NOW() - INTERVAL 1 HOUR, '%H:00:00'), quiet_to = DATE_FORMAT(NOW() + INTERVAL 2 HOUR, '%H:00:00') WHERE id = 6");
    r = await post(page, '/invoices/record_payment/6', { csrf_token: await csrf(page), amount: 5000, payment_date: q('SELECT CURDATE()'), method: 'cash' }, true);
    await pause(300);
    ok(r.json && r.json.success === true && received().length === 0, 'in Ada\'s quiet hours: no buzz');
    ok(q("SELECT COUNT(*) FROM notifications WHERE type = 'payment_received' AND action_url LIKE '%/invoices/view/6'") !== '0', 'the bell still has it');
    q('UPDATE users SET quiet_hours = 0 WHERE id = 6');

    // Ada records one: Ivie hears about it.
    clearPhones();
    const ada = await browser.newContext(PHONE);
    const pa = await signIn(ada, 'ada@demo.nfelia');
    r = await post(pa, '/invoices/record_payment/6', { csrf_token: await csrf(pa), amount: 15000, payment_date: q('SELECT CURDATE()'), method: 'cash' }, true);
    await pause(300);
    got = received();
    ok(users(got) === '1' && got[0].msg.body.endsWith('Fully paid.'), 'Ada records the rest: Ivie\'s phone buzzes, "' + (got[0] && got[0].msg.body) + '"');
    await ada.close();

    // A sale paid on the spot.
    clearPhones();
    r = await post(page, '/receipts/create', {
      csrf_token: await csrf(page), customer_id: 3, issue_date: q('SELECT CURDATE()'), method: 'cash', note: '',
      'item_description[]': 'Edge control & hair care kit', 'item_quantity[]': 2, 'item_unit_price[]': 12000, 'item_product_id[]': 7,
    });
    const receiptId = (r.url.match(/receipts\/view\/(\d+)/) || [])[1];
    const receiptNo = q(`SELECT doc_number FROM receipts WHERE id = ${+receiptId || 0}`);
    await pause(300);
    got = received();
    const payer3 = q('SELECT full_name FROM customers WHERE id = 3');
    ok(receiptId && users(got) === '6' && got[0].msg.title === '₦24,000 received' && got[0].msg.body === `Receipt ${receiptNo} for ${payer3}.` && got[0].msg.url === `receipts/view/${receiptId}`,
       'a receipt: Ada\'s phone, ' + JSON.stringify(got[0] && got[0].msg));
    ok(q(`SELECT COUNT(*) FROM notifications WHERE type = 'payment_received' AND title = '₦24,000 received' AND action_url LIKE '%/receipts/view/${receiptId}'`) === '1', 'and the bell has it');

    // A shop order: nobody in the shop placed it, so both phones buzz.
    clearPhones();
    const shopper = await browser.newContext();
    r = await shopper.request.post(BASE + '/public/cart-checkout', {
      form: { business_id: 1, channel: 'cart_pop', fulfillment_type: 'pickup', customer_name: 'Ngozi Eze', customer_phone: '08035550123', payment_method: 'none',
              items: JSON.stringify([{ product_id: 2, product_name: 'Body Wave 18" bundle', unit_price: 65000, quantity: 2 }]) },
    });
    const order = await r.json();
    await pause(300);
    got = received();
    ok(order.success && users(got) === '1,6' && got[0].msg.title === 'New order: ₦130,000' && got[0].msg.body === 'Ngozi Eze ordered on your shop.' && got[0].msg.url === `orders/view/${order.order_id}`,
       'a new shop order: Ivie and Ada, ' + JSON.stringify(got[0] && got[0].msg));
    q(`INSERT INTO auth_throttle (bucket, key_hash) SELECT 'order_push', SHA2('1', 256) FROM seq_1_to_18`);
    clearPhones();
    for (const n of [1, 2]) {
      await shopper.request.post(BASE + '/public/cart-checkout', {
        form: { business_id: 1, channel: 'whatsapp', customer_name: 'Ngozi Eze', customer_phone: '0803555012' + n, items: JSON.stringify([{ product_id: 4, unit_price: 48000, quantity: 1 }]) },
      });
    }
    await pause(300);
    got = received();
    ok(users(got) === '1,6' && got[0].msg.body === 'Ngozi Eze ordered on your shop through WhatsApp.', 'the 20th order in an hour still buzzes ("through WhatsApp"); the 21st does not: ' + users(got));
    ok(q(`SELECT COUNT(*) FROM notifications WHERE business_id = 1 AND title LIKE 'New Order%' AND created_at > NOW() - INTERVAL 1 MINUTE`) === '3', 'the bell has every order');
    await shopper.close();

    // Approving an order paid with proof.
    clearPhones();
    q(`UPDATE orders SET status = 'pending_verification', payment_method = 'bank_transfer', payment_proof_path = 'uploads/order_proofs/1/test.png' WHERE id = ${order.order_id}`);
    await page.goto(BASE + `/orders/view/${order.order_id}`);
    r = await post(page, `/orders/approve/${order.order_id}`, { csrf_token: await csrf(page), deliver_mode: 'immediate' });
    await pause(300);
    got = received();
    const orderNo = q(`SELECT order_number FROM orders WHERE id = ${order.order_id}`);
    ok(q(`SELECT status FROM orders WHERE id = ${order.order_id}`) === 'approved' && users(got) === '6' && got[0].msg.title === '₦130,000 received'
       && got[0].msg.body === `Shop order ${orderNo} from Ngozi Eze, paid and approved.`, 'approved with proof of payment: Ada\'s phone, ' + JSON.stringify(got[0] && got[0].msg));
    ok(q(`SELECT COUNT(*) FROM notifications WHERE type = 'payment_received' AND action_url LIKE '%/orders/view/${order.order_id}'`) === '1', 'and the bell has it');

    // ---------- Low stock (cron) ----------
    console.log('Low stock');
    q(`DELETE FROM planner_sends`);
    q(`DELETE FROM notifications WHERE type = 'general' AND user_id IS NOT NULL`);
    q(`UPDATE products SET stock_qty = 1 WHERE id = 5`);
    q(`UPDATE products SET stock_qty = 3 WHERE id = 7`);
    q(`UPDATE users SET notify_low_stock = 1, notify_reminders = 0 WHERE id IN (1, 6)`);
    q(`UPDATE users SET quiet_hours = 1, quiet_from = '11:00:00', quiet_to = '13:00:00' WHERE id = 6`);
    clearPhones();
    let out = cron('12:05');
    got = received();
    const bell = (u) => q(`SELECT CONCAT_WS(' | ', title, message, action_url) FROM notifications WHERE type = 'general' AND user_id = ${u} ORDER BY id DESC LIMIT 1`);
    const lowTitle = '3 items are running low';
    const lowBody = 'HD Lace Closure 5x5 (1 left), Edge control & hair care kit (3 left), Kinky Curly Frontal Wig 20" (2 left).';
    ok(/low_stock=1/.test(out) && /hushed=1/.test(out), 'at 12:05 one person is told, one is in quiet hours: ' + out);
    ok(users(got) === '1' && got[0].msg.title === lowTitle && got[0].msg.body === lowBody && got[0].msg.url === 'products?stock=restock', 'Ivie\'s phone: ' + JSON.stringify(got[0] && got[0].msg));
    ok(bell(1) === `${lowTitle} | ${lowBody} | products?stock=restock`, 'and her bell');
    ok(bell(6) === '', 'nothing for Ada yet, not even the bell: it waits for her quiet hours to end');
    clearPhones();
    out = cron('12:10');
    ok(/low_stock=0/.test(out) && received().length === 0, 'once a day: nothing more at 12:10');
    out = cron('13:05');
    got = received();
    ok(/low_stock=1/.test(out) && users(got) === '6', 'Ada gets hers at 13:05, after her quiet hours');
    q(`DELETE FROM planner_sends`);
    q(`UPDATE products SET stock_qty = 0 WHERE id = 5`);
    clearPhones();
    cron('15:05');
    got = received();
    ok(got[0] && got[0].msg.title === '3 items need restocking' && got[0].msg.body.startsWith('HD Lace Closure 5x5 (none left), '), 'empty shelves come first: ' + JSON.stringify(got[0] && got[0].msg));
    await page.goto(BASE + '/products?stock=restock');
    const listed = await page.locator('main').innerText();
    ok(['HD Lace Closure 5x5', 'Edge control', 'Kinky Curly Frontal Wig'].every((n) => listed.includes(n)) && !listed.includes('Pixie Curls'), 'the link lists exactly those: low or out');
    q(`UPDATE users SET notify_low_stock = 0, quiet_hours = 0 WHERE id IN (1, 6)`);

    // ---------- Reminders (cron) ----------
    console.log('Reminders');
    execSync(`mysql smartqaq_nfelia_core < '${path.join(__dirname, 'planner_seed.sql.txt')}'`);
    q(`DELETE FROM planner_sends`);
    q(`DELETE FROM notifications WHERE type = 'general' AND user_id IS NOT NULL`);
    q(`UPDATE users SET reminder_email = 0, notify_reminders = 0 WHERE id = 1`);
    clearPhones();
    cron('13:05');
    ok(/Wig install/.test(bell(1)) && received().filter((p) => p.user === 1).length === 0, 'Reminders off: the bell has "Wig install", the phone stays still');
    execSync(`mysql smartqaq_nfelia_core < '${path.join(__dirname, 'planner_seed.sql.txt')}'`);
    q(`DELETE FROM notifications WHERE type = 'general' AND user_id IS NOT NULL`);
    q(`UPDATE users SET notify_reminders = 1, quiet_hours = 1, quiet_from = '13:00:00', quiet_to = '14:00:00' WHERE id = 1`);
    clearPhones();
    out = cron('13:05');
    ok(/Wig install/.test(bell(1)) && received().filter((p) => p.user === 1).length === 0 && /hushed=1/.test(out), 'in quiet hours: the bell has it, no buzz: ' + out);
    execSync(`mysql smartqaq_nfelia_core < '${path.join(__dirname, 'planner_seed.sql.txt')}'`);
    q(`UPDATE users SET quiet_hours = 0 WHERE id = 1`);
    clearPhones();
    cron('13:05');
    got = received();
    ok(got.some((p) => p.user === 1 && p.msg.title === 'Wig install'), 'otherwise it buzzes, as before');
    await ctx.close();

    // ---------- Share PDF ----------
    console.log('Share PDF');
    const shareCtx = await browser.newContext(PHONE);
    await shareCtx.addInitScript(() => {
      window.__shared = [];
      window.__shareError = null;
      navigator.canShare = (d) => !!(d && d.files && d.files.every((f) => f.type === 'application/pdf'));
      navigator.share = async (d) => {
        if (window.__shareError) { const e = new Error('x'); e.name = window.__shareError; throw e; }
        const f = d.files[0];
        window.__shared.push({ name: f.name, type: f.type, size: f.size, head: await f.slice(0, 5).text(), title: d.title, text: d.text });
      };
    });
    page = await signIn(shareCtx);
    for (const [url, prefix] of [['/invoices/view/1', 'IVL-INV-131'], [`/receipts/view/${receiptId}`, receiptNo], ['/quotations/view/40', 'IVL-QUO-022']]) {
      await page.goto(BASE + url);
      const btn = page.locator('[data-share-pdf]');
      await btn.waitFor({ state: 'visible', timeout: 4000 }).catch(() => {});
      ok(await btn.isVisible(), `${url}: Share PDF shows where the phone can share files`);
      await btn.click();
      await page.waitForFunction(() => window.__shared.length > 0, null, { timeout: 15000 }).catch(() => {});
      const s = (await page.evaluate(() => window.__shared))[0] || {};
      ok(s.name === prefix + '.pdf' && s.type === 'application/pdf' && s.head === '%PDF-' && s.size > 2000 && s.title.includes(prefix), `${url}: shares ${s.name}, ${s.size} bytes, "${s.title}"`);
      if (url === '/invoices/view/1') {
        await page.addStyleTag({ content: still });
        await shot(page, 'share-pdf');
        ok(/[\s\S]+/.test(s.text || '') , 'with a message: ' + (s.text || '').slice(0, 80));
        await page.evaluate(() => { window.__shared = []; window.__shareError = 'AbortError'; });
        await btn.click();
        await page.waitForTimeout(500);
        ok(await page.locator('.nf-toast').count() === 0, 'closing the share sheet says nothing');
        await page.evaluate(() => { window.__shareError = 'NotAllowedError'; });
        await btn.click();
        await page.waitForSelector('.nf-toast', { timeout: 3000 }).catch(() => {});
        ok(/The PDF is ready\. Tap Share PDF again\./.test(await page.locator('.nf-toast').innerText().catch(() => '')), 'a phone that wants a fresh tap is asked for one');
        await page.evaluate(() => { window.__shareError = null; });
      }
      await page.evaluate(() => { window.__shared = []; });
    }
    await shareCtx.close();
    {
      const desk = await browser.newContext(DESK);
      const p = await signIn(desk);
      await p.goto(BASE + '/invoices/view/1');
      await p.waitForTimeout(500);
      ok(await p.locator('[data-share-pdf]').count() === 1 && !(await p.locator('[data-share-pdf]').isVisible()), 'a computer that cannot share files: no Share PDF, the other buttons stay');
      await desk.close();
    }

    // ---------- Pick from phone contacts ----------
    console.log('Phone contacts');
    const contactsCtx = await browser.newContext(PHONE);
    await contactsCtx.addInitScript(() => {
      window.ContactsManager = function () {};
      Object.defineProperty(navigator, 'contacts', { value: { select: async () => [{ name: ['Amaka Obi'], tel: ['+234 803 555 0147'], email: ['amaka@example.com'] }] } });
    });
    page = await signIn(contactsCtx);
    await page.goto(BASE + '/customers/create');
    const pick = page.locator('[data-pick-contact]');
    ok(await pick.isVisible(), 'New client: Pick from phone contacts shows on Android');
    await pick.click();
    await page.waitForTimeout(300);
    ok(await page.inputValue('[name=full_name]') === 'Amaka Obi' && await page.inputValue('[name=phone]') === '+2348035550147' && await page.inputValue('[name=email]') === 'amaka@example.com',
       'fills name, phone (digits only) and email');
    await page.addStyleTag({ content: still });
    await shot(page, 'contacts-client');
    await page.goto(BASE + '/receipts/create');
    await page.locator('form button', { hasText: '+ New' }).first().click();
    await page.waitForTimeout(400);
    const pick2 = page.locator('[data-pick-contact]');
    ok(await pick2.isVisible(), 'New receipt, new client: the picker shows');
    await pick2.click();
    await page.waitForTimeout(300);
    const box = await page.evaluate(() => Alpine.$data(document.querySelector('form[x-data]')).newClient);
    ok(box.name === 'Amaka Obi' && box.phone === '+2348035550147' && box.email === 'amaka@example.com', 'and fills the form\'s own data, not just the boxes: ' + JSON.stringify(box));
    await contactsCtx.close();
    {
      const desk = await browser.newContext(DESK);
      const p = await signIn(desk);
      await p.goto(BASE + '/customers/create');
      ok(!(await p.locator('[data-pick-contact]').isVisible()), 'elsewhere the button stays hidden');
      await desk.close();
    }
  } finally {
    await browser.close();
    phones.kill();
    fs.writeFileSync(envFile, envBefore);
    execSync(`mysql smartqaq_nfelia_core < '${snapshot}'`);
  }

  if (errors.length) { console.log('Page errors:'); errors.forEach((e) => console.log('  ' + e)); }
  ok(errors.length === 0, 'no script errors on any page');
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
