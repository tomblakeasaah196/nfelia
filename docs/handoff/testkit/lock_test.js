// Passkeys and the lock, end to end, with Chrome's virtual authenticator (a
// fingerprint reader that always says yes). Runs against the localhost copy
// on :8098: passkeys need a domain name, not an IP. Its .env sets
// APP_URL=http://localhost:8098 and LOCK_AFTER_SECONDS=40, so the lock can be
// watched happening.
//   node lock_test.js OUTDIR
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const BASE = 'http://localhost:8098';
const LOCK = 40;
const OUT = process.argv[2] || path.join(__dirname, 'build', 'lock');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';
const PHONE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: ANDROID_UA, timezoneId: 'Africa/Lagos' };
const DESK = { viewport: { width: 1280, height: 900 }, timezoneId: 'Africa/Lagos' };

const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql.replace(/"/g, '\\"')}"`).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
const errors = [];
function watch(page, tag) {
  page.on('pageerror', (e) => errors.push(tag + ' pageerror: ' + e.message));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/401|ERR_INTERNET_DISCONNECTED|Failed to load resource/.test(m.text())) errors.push(tag + ' console: ' + m.text());
  });
}
const still = '*{animation:none!important;transition:none!important}';
const shot = (page, name) => page.screenshot({ path: path.join(OUT, name + '.png') });

async function fingerprint(page) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true },
  });
  return { cdp, authenticatorId };
}
async function signIn(page, opts = {}) {
  await page.goto(BASE + '/login');
  await page.fill('#email', 'ivie@demo.nfelia');
  await page.fill('#password', 'DemoPass1');
  if (opts.remember) await page.check('input[name=remember_me]');
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
}
async function waitForLock(page, ms = (LOCK + 25) * 1000) {
  await page.waitForSelector('#nf-lock[open]', { timeout: ms });
  await page.waitForTimeout(300);
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  q("DELETE FROM user_passkeys WHERE user_id IN (1, 2)");
  q("DELETE FROM auth_throttle WHERE bucket IN ('unlock', 'passkey')");
  const browser = await chromium.launch({ executablePath: CHROME }).catch(() => chromium.launch());

  // ---------- After signing in: the passkey offer ----------
  console.log('Passkey offer');
  const ctx = await browser.newContext(PHONE);
  let page = await ctx.newPage();
  watch(page, 'phone');
  const auth = await fingerprint(page);
  await signIn(page);
  await page.waitForSelector('#nf-passkey[open]', { timeout: 5000 }).catch(() => {});
  ok(await page.locator('#nf-passkey[open]').count() === 1, 'after sign-in: the passkey offer opens');
  ok(/fingerprint/.test(await page.locator('[data-pk-title]').innerText()), 'Android wording: ' + await page.locator('[data-pk-title]').innerText());
  await page.waitForTimeout(400);
  await page.addStyleTag({ content: still });
  await shot(page, 'offer');
  await page.click('[data-pk-go]');
  await page.waitForSelector('#nf-passkey[open]', { state: 'detached', timeout: 1 }).catch(() => {});
  await page.waitForFunction(() => !document.querySelector('#nf-passkey[open]'), null, { timeout: 8000 }).catch(() => {});
  const rows = q("SELECT COUNT(*), MAX(name) FROM user_passkeys WHERE user_id = 1").split('\t');
  ok(rows[0] === '1' && rows[1] === 'Android phone', 'a passkey is saved, named "' + rows[1] + '"');
  ok(/Next time, sign in with your fingerprint/.test(await page.locator('.nf-toast').innerText().catch(() => '')), 'toast confirms it');
  ok(!!(await page.evaluate(() => localStorage.getItem('nf.pk.1'))), 'this phone remembers it has the passkey');
  const creds = (await auth.cdp.send('WebAuthn.getCredentials', { authenticatorId: auth.authenticatorId })).credentials;
  ok(creds.length === 1 && creds[0].isResidentCredential, 'the phone holds one passkey, kept on the device');
  const pub = q("SELECT public_key FROM user_passkeys WHERE user_id = 1");
  ok(/BEGIN PUBLIC KEY/.test(pub.replace(/\\n/g, '\n')) && !/PRIVATE/.test(pub), 'Nfelia stores only the public key');

  // ---------- The lock, from the page's own clock ----------
  console.log(`Lock after ${LOCK}s unused`);
  await page.goto(BASE + '/invoices');
  const listed = await page.locator('body').innerText();
  await waitForLock(page);
  ok(await page.evaluate(() => document.documentElement.classList.contains('nf-locked')), 'the page blurs');
  ok(await page.locator('[data-lock-passkey]').isVisible(), 'the lock offers the fingerprint');
  ok(/your fingerprint/.test(await page.locator('[data-lock-passkey]').innerText()), 'button: "' + (await page.locator('[data-lock-passkey]').innerText()).trim() + '"');
  ok(!(await page.locator('[data-lock-password]').isVisible()) && await page.locator('[data-lock-use-password]').isVisible(), 'password waits behind "Use password instead"');
  ok(/Welcome back, Ivie/.test(await page.locator('#nf-lock h2').innerText()), 'it greets the person by name');
  await page.addStyleTag({ content: still });
  await shot(page, 'lock_phone');
  const api = await page.evaluate(async () => { const r = await fetch('/api/notifications?action=count'); return [r.status, r.headers.get('X-Nfelia-Locked')]; });
  ok(api[0] === 401 && api[1] === '1', 'the server refuses the API while locked');
  const tasksApi = await page.evaluate(async () => { const r = await fetch('/tasks/save', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } }); return r.status; });
  ok(tasksApi === 401, 'and the app\'s own JSON routes too');

  // Unlock with the fingerprint.
  await page.click('[data-lock-passkey]');
  await page.waitForFunction(() => !document.querySelector('#nf-lock[open]'), null, { timeout: 8000 }).catch(() => {});
  ok(!(await page.evaluate(() => document.documentElement.classList.contains('nf-locked'))), 'fingerprint: unlocked, same page');
  ok((await page.evaluate(async () => (await fetch('/api/notifications?action=count')).status)) === 200, 'the API answers again');

  // ---------- The server's lock: a page opened after the time ----------
  console.log('Server lock page');
  await waitForLock(page);
  const resp = await page.goto(BASE + '/customers');
  ok(resp.status() === 401, 'opening a page while locked: 401');
  const lockedHtml = await resp.text();
  ok(!/Lara Coker|Ifeoma Chukwu|Adaeze Nwosu/.test(lockedHtml), 'nothing of the business is in the page');
  ok(/class="nf-outline"/.test(lockedHtml), 'only an outline sits behind the lock');
  // The lock page opens itself with the fingerprint, then loads the page.
  await page.waitForFunction(() => /Clients|Customers/i.test(document.title) && !document.documentElement.classList.contains('nf-locked'), null, { timeout: 10000 }).catch(() => {});
  ok(/customers$/.test(page.url()) && !(await page.evaluate(() => document.documentElement.classList.contains('nf-locked'))), 'fingerprint on arrival, then the page itself');
  ok(/Lara Coker|Ifeoma Chukwu|Adaeze Nwosu/.test(await page.content()), 'now the clients show');

  // ---------- Password unlock ----------
  console.log('Password');
  await waitForLock(page);
  await page.click('[data-lock-use-password]');
  await page.fill('#nf-lock-pw', 'wrong-one');
  await page.click('[data-lock-password] button[type=submit]');
  await page.waitForSelector('[data-lock-error]:not([hidden])', { timeout: 5000 }).catch(() => {});
  ok(/doesn’t match/.test(await page.locator('[data-lock-error]').innerText()), 'wrong password: "' + (await page.locator('[data-lock-error]').innerText()) + '"');
  ok(await page.locator('#nf-lock[open]').count() === 1, 'and it stays locked');
  await page.fill('#nf-lock-pw', 'DemoPass1');
  await page.click('[data-lock-password] button[type=submit]');
  await page.waitForFunction(() => !document.querySelector('#nf-lock[open]'), null, { timeout: 8000 }).catch(() => {});
  ok(!(await page.evaluate(() => document.documentElement.classList.contains('nf-locked'))), 'right password: unlocked');

  // ---------- Sign out from the lock ----------
  console.log('Sign out from the lock');
  await waitForLock(page);
  const [out] = await Promise.all([page.waitForNavigation(), page.click('.nf-lock-out button')]);
  ok(/\/login$/.test(out.url()) && /signed out/.test(await out.text()), 'Not Ivie? Sign out: back to sign-in, told plainly');

  // ---------- Sign in with the passkey ----------
  console.log('Sign in with a passkey');
  // The browser offers the passkey in the email field. Chrome's test
  // fingerprint reader answers that offer by itself, so this signs in alone.
  await page.waitForURL(/\/dashboard$/, { timeout: 8000 }).catch(() => {});
  ok(/\/dashboard$/.test(page.url()), 'passkey picked from the email field: signed in');
  // Now the button, with the email-field offer switched off.
  await page.addInitScript(() => { if (window.PublicKeyCredential) PublicKeyCredential.isConditionalMediationAvailable = () => Promise.resolve(false); });
  await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
  await page.waitForSelector('[data-passkey-signin]:not([hidden])', { timeout: 5000 }).catch(() => {});
  ok(await page.locator('[data-passkey-signin] button').isVisible(), 'sign-in page offers "Sign in with a passkey"');
  ok((await page.getAttribute('#email', 'autocomplete')) === 'username webauthn', 'and suggests passkeys in the email field');
  await page.addStyleTag({ content: still });
  await shot(page, 'signin');
  await Promise.all([page.waitForNavigation({ timeout: 10000 }).catch(() => {}), page.click('[data-passkey-signin] button')]);
  ok(/\/dashboard$/.test(page.url()), 'one tap: signed in');
  ok(q("SELECT DATE(last_used_at) = CURDATE() FROM user_passkeys WHERE user_id = 1") === '1', 'the passkey records when it was used');

  // ---------- Settings: list and remove ----------
  console.log('Settings');
  await page.goto(BASE + '/settings?tab=profile');
  ok(await page.locator('[data-passkeys] li').count() === 1, 'Settings lists the passkey');
  ok(/Android phone/.test(await page.locator('[data-passkeys] li').innerText()) && /Last used today/.test(await page.locator('[data-passkeys] li').innerText()), 'with its device and last use');
  await page.locator('[data-passkeys]').scrollIntoViewIfNeeded();
  await page.addStyleTag({ content: still });
  await shot(page, 'settings');
  page.once('dialog', (d) => d.accept());
  await page.click('[data-pk-remove]');
  await page.waitForFunction(() => document.querySelectorAll('[data-passkeys] li').length === 0, null, { timeout: 5000 }).catch(() => {});
  ok(q("SELECT COUNT(*) FROM user_passkeys WHERE user_id = 1") === '0', 'Remove deletes it');
  ok(!(await page.evaluate(() => localStorage.getItem('nf.pk.1'))), 'and the phone forgets it');
  await page.click('[data-pk-add]');
  await page.waitForFunction(() => document.querySelectorAll('[data-passkeys] li').length === 1, null, { timeout: 8000 }).catch(() => {});
  ok(q("SELECT COUNT(*) FROM user_passkeys WHERE user_id = 1") === '1', 'Add a passkey on this device works from Settings');
  // A second one on the same phone is refused by the phone itself.
  await page.click('[data-pk-add]');
  await page.waitForSelector('[data-pk-note]:not([hidden])', { timeout: 8000 }).catch(() => {});
  ok(/already on this device/.test(await page.locator('[data-pk-note]').innerText()), 'one passkey per phone: "' + (await page.locator('[data-pk-note]').innerText()) + '"');
  await ctx.close();

  // ---------- Kept signed in, back after a while: the lock ----------
  console.log('Keep me signed in');
  const ctx2 = await browser.newContext(PHONE);
  page = await ctx2.newPage();
  watch(page, 'remember');
  await signIn(page, { remember: true });
  q("UPDATE sessions SET last_active_at = NOW() - INTERVAL 3 HOUR WHERE user_id = 1 AND expires_at > NOW()");
  const kept = (await ctx2.cookies()).filter((c) => c.name === 'nfelia_remember');
  await ctx2.clearCookies();
  await ctx2.addCookies(kept);
  const back = await page.goto(BASE + '/dashboard');
  ok(back.status() === 401 && await page.locator('#nf-lock').count() === 1, 'browser restarted 3 hours later: opens on the lock');
  await page.waitForSelector('#nf-lock[open]', { timeout: 5000 }).catch(() => {});
  ok(await page.locator('[data-lock-password]').isVisible(), 'no passkey on this phone: the password, ready');
  await page.fill('#nf-lock-pw', 'DemoPass1');
  await Promise.all([page.waitForNavigation({ timeout: 10000 }).catch(() => {}), page.click('[data-lock-password] button[type=submit]')]);
  ok(/dashboard$/.test(page.url()) && !(await page.evaluate(() => document.documentElement.classList.contains('nf-locked'))), 'unlocked: Home loads');

  // ---------- Guessing the password ends the session ----------
  console.log('Guessing');
  q("DELETE FROM auth_throttle WHERE bucket = 'unlock'");
  const statuses = await page.evaluate(async () => {
    const out = [];
    for (let i = 0; i < 9; i++) {
      const r = await fetch('/api/auth?action=unlock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf }, body: JSON.stringify({ password: 'guess' + i }) });
      out.push(r.status);
    }
    return out;
  });
  ok(statuses.slice(0, 8).every((s) => s === 400) && statuses[8] === 429, '8 wrong tries, then signed out: ' + statuses.join(','));
  const after = await page.goto(BASE + '/dashboard');
  ok(/\/login$/.test(page.url()), 'after that, sign-in is needed again');
  await ctx2.close();

  // ---------- Offline and locked ----------
  console.log('Offline');
  const ctx3 = await browser.newContext(PHONE);
  page = await ctx3.newPage();
  watch(page, 'offline');
  await signIn(page);
  await page.evaluate(() => navigator.serviceWorker.ready);
  await page.goto(BASE + '/invoices');
  await page.goto(BASE + '/tasks');
  await page.waitForTimeout(500);
  await page.evaluate((s) => localStorage.setItem('nf.active', String(Date.now() - (s + 20) * 1000)), LOCK);
  await ctx3.setOffline(true);
  await page.goto(BASE + '/invoices').catch(() => {});
  await page.waitForSelector('#nf-lock[open]', { timeout: 6000 }).catch(() => {});
  ok(await page.locator('meta[name="nf-cached"]').count() === 1 && await page.locator('#nf-lock[open]').count() === 1, 'a kept page, opened offline after the time: locked');
  ok(await page.locator('[data-lock-offline]').isVisible(), 'it says to connect to unlock');
  ok(await page.locator('[data-lock-password] button[type=submit]').isDisabled(), 'and unlocking waits for the network');
  await page.addStyleTag({ content: still });
  await shot(page, 'lock_offline');
  await ctx3.setOffline(false);
  await ctx3.close();

  // ---------- Computer ----------
  const ctx4 = await browser.newContext(DESK);
  page = await ctx4.newPage();
  watch(page, 'desk');
  await signIn(page);
  await waitForLock(page);
  await page.addStyleTag({ content: still });
  await shot(page, 'lock_desk');
  ok(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth) <= 0, 'computer: the lock fits');
  await ctx4.close();

  await browser.close();
  q("DELETE FROM user_passkeys WHERE user_id IN (1, 2)");
  q("DELETE FROM auth_throttle WHERE bucket IN ('unlock', 'passkey')");
  console.log(errors.length ? 'ERRORS:\n  ' + errors.join('\n  ') : 'No page errors.');
  console.log(`${pass} passed, ${fail} failed`);
  process.exit(fail || errors.length ? 1 : 0);
})();
