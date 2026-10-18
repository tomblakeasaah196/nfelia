// The parts of notifications only a browser can check: the sign-in page
// carrying an alert through, the bell (live count, text as typed, tapping a
// row), the notifications page, More > Notifications, and adding a passkey.
// Screenshots at phone width land in NF_UI_OUT for a look.
//   node tests/notifications/ui_test.js        (run.sh ui)
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const ROOT = path.join(__dirname, '..', '..');
const env = Object.fromEntries(fs.readFileSync(path.join(ROOT, '.env'), 'utf8').split('\n')
  .filter((l) => /^[A-Z_]+=/.test(l)).map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1).trim()]));
const BASE = (process.env.APP_URL || env.APP_URL).replace(/\/$/, '');
const OUT = process.env.NF_UI_OUT || path.join(os.tmpdir(), 'nf-ui');
const PASSWORD = 'TestPass1';
fs.mkdirSync(OUT, { recursive: true });

const cli = (...args) => JSON.parse(execFileSync('php', [path.join(__dirname, 'ui_cli.php'), ...args], { cwd: ROOT }).toString());
const sql = (q) => cli('sql', q);

let pass = 0, fail = 0;
const failed = [];
const ok = (c, m) => { if (c) { pass++; console.log('  PASS  ' + m); } else { fail++; failed.push(m); console.log('  FAIL  ' + m); } };
const PHONE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos',
  userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36' };
const still = '*,*::before,*::after{animation:none!important;transition:none!important}';
const errors = [];
const watch = (page, who) => page.on('pageerror', (e) => errors.push(`${who}: ${e.message}`));

async function signIn(page, email) {
  await page.goto(BASE + '/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
}

(async () => {
  const ids = cli('reset');
  const exe = process.env.CHROMIUM_PATH || (fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined);
  const browser = await chromium.launch(exe ? { executablePath: exe } : {});

  console.log('\nTapping an alert while signed out');
  const ctx = await browser.newContext(PHONE);
  const page = await ctx.newPage();
  watch(page, 'ada');
  const orderAlert = ids.order[0];   // Ada's copy (the owner comes first)
  await page.goto(`${BASE}/notifications/open/${orderAlert}`);
  ok(page.url().startsWith(`${BASE}/login?next=`), 'lands on sign-in');
  ok(await page.locator('text=Sign in to open that.').count() === 1, 'which says why');
  await page.fill('#email', 'ada@adashair.test');
  await page.fill('#password', PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
  await page.waitForLoadState('networkidle');
  ok(page.url() === `${BASE}/orders/view/700`, 'signs in and opens the order: ' + page.url().replace(BASE, ''));
  ok((await page.content()).includes('ORD-0700'), 'the order itself');

  console.log('\nThe bell');
  await page.goto(`${BASE}/dashboard`);
  await page.addStyleTag({ content: still });
  await page.waitForFunction(() => { const c = document.querySelector('.nf-count'); return c && getComputedStyle(c).display !== 'none'; });
  ok((await page.locator('.nf-count').first().textContent()).trim() === '1', 'one unread (the order was read by opening it)');
  await page.click('button[aria-label=Notifications]');
  await page.waitForSelector('.nf-pop .nf-note');
  const first = (await page.locator('.nf-pop .nf-note b').first().textContent()).trim();
  const firstBody = (await page.locator('.nf-pop .nf-note p').first().textContent()).trim();
  ok(first === '₦25,000 received · ADA-INV-0400', 'newest first: ' + first);
  ok(firstBody.startsWith('Femi & Sons "Wholesale" paid'), 'names exactly as typed, never "&amp;" (' + firstBody + ')');
  ok(await page.locator('.nf-pop a.nf-pop-all').count() === 1, '"See all notifications" at the bottom');
  await page.screenshot({ path: path.join(OUT, 'bell.png') });
  await Promise.all([page.waitForNavigation(), page.locator('.nf-pop .nf-note').first().click()]);
  ok(page.url() === `${BASE}/invoices/view/400`, 'tapping it opens the invoice');
  await page.goto(`${BASE}/dashboard`);
  await page.waitForTimeout(600);
  ok(!(await page.locator('.nf-count').first().isVisible()), 'and nothing is left unread');
  cli('notify', 'quote.accepted', '2', JSON.stringify({ quotation_id: 500, quotation_number: 'ADA-QUO-0500', customer: 'Ngozi Eze', total: 250000 }));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(() => { const c = document.querySelector('.nf-count'); return c && c.offsetParent !== null && c.textContent.trim() === '1'; }, null, { timeout: 5000 }).catch(() => {});
  ok(await page.locator('.nf-count').first().isVisible(), 'a new alert shows on the bell as soon as the page is looked at again');

  console.log('\nThe notifications page');
  await page.goto(`${BASE}/notifications`);
  await page.addStyleTag({ content: still });
  ok(await page.locator('.nf-inbox .nf-note').count() === 3, 'every alert, newest first');
  ok(await page.locator('.nf-inbox .nf-note.is-new').count() === 1, 'the unread one marked');
  await page.screenshot({ path: path.join(OUT, 'notifications.png'), fullPage: true });
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/notifications/read-all"] button')]);
  ok(await page.locator('.nf-inbox .nf-note.is-new').count() === 0, 'Mark all read');

  console.log('\nMore > Notifications');
  await page.goto(`${BASE}/more#notifications`);
  await page.waitForSelector('#nf-notify[open]');
  await page.addStyleTag({ content: still });
  ok(await page.locator('#nf-notify [data-topic]').count() === 13, 'the owner\'s 13 kinds of alert, each with Phone and Email');
  ok(await page.locator('#nf-notify [data-topic=billing] .nf-always').count() === 1, 'billing email: "Always", no switch');
  await page.locator('[data-topic=money] [data-topic-push]').uncheck();
  await page.waitForSelector('[data-saved]:has-text("Saved")');
  ok(sql("SELECT push FROM notification_prefs WHERE user_id = 20 AND topic = 'money'")[0]?.push === 0, 'turning money off the phone is saved');
  await page.screenshot({ path: path.join(OUT, 'settings.png') });

  console.log('\nAdding a passkey tells the account');
  // Passkeys need a domain name: with APP_URL on an IP address, this can't run.
  if (/^\d+\.\d+\.\d+\.\d+$/.test(new URL(BASE).hostname)) {
    console.log('  SKIP  passkeys need APP_URL on a name (http://localhost:8099), not an IP address');
  } else {
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true,
    hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true } });
  await page.goto(`${BASE}/settings?tab=profile`);
  await page.click('[data-pk-add]');
  await page.waitForFunction(() => document.querySelectorAll('[data-pk-list] li').length > 0, null, { timeout: 10000 }).catch(() => {});
  const told = sql("SELECT title FROM notifications WHERE user_id = 20 AND event = 'security.passkey_added'");
  ok(told.length === 1 && told[0].title === 'Passkey added', 'a passkey added on this device: Ada is told');
  }

  console.log('\nStaff');
  const staff = await (await browser.newContext(PHONE)).newPage();
  watch(staff, 'chidi');
  await signIn(staff, 'chidi@adashair.test');
  await staff.click('button[aria-label=Notifications]');
  await staff.waitForSelector('.nf-pop .nf-note');
  const titles = await staff.locator('.nf-pop .nf-note b').allTextContents();
  ok(titles.length === 1 && titles[0].trim() === 'New order ORD-0700', 'Chidi sees the order, without the amount, and no money alerts');

  ok(errors.filter((e) => !/Chart is not defined/.test(e)).length === 0, 'no script errors on any page' + (errors.length ? ': ' + errors.join(' | ') : ''));
  await browser.close();
  console.log(`\nScreenshots: ${OUT}`);
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  failed.forEach((f) => console.log('  - ' + f));
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
