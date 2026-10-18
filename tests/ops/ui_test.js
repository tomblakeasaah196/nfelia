// Nfelia Ops in a real browser: signing in with a password, adding a
// passkey and signing in with it (a virtual authenticator), the phone menu,
// the invite dialog. Screenshots at phone and computer width land in
// NF_UI_OUT/ops for a look.
//   bash tests/notifications/run.sh ops/ui
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
const APP = new URL((process.env.APP_URL || env.APP_URL).replace(/\/$/, ''));
const OPS = `${APP.protocol}//ops.${APP.hostname.replace(/^www\./, '')}${APP.port ? ':' + APP.port : ''}`;
const OUT = path.join(process.env.NF_UI_OUT || path.join(os.tmpdir(), 'nf-ui'), 'ops');
const PASSWORD = 'OpsPass1234';
fs.mkdirSync(OUT, { recursive: true });

const cli = (...args) => JSON.parse(execFileSync('php', [path.join(ROOT, 'tests/notifications/ui_cli.php'), ...args], { cwd: ROOT }).toString());
const sql = (q) => cli('sql', q);

let pass = 0, fail = 0;
const failed = [];
const ok = (c, m) => { if (c) { pass++; console.log('  PASS  ' + m); } else { fail++; failed.push(m); console.log('  FAIL  ' + m); } };
const PHONE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' };
const COMPUTER = { viewport: { width: 1280, height: 860 }, timezoneId: 'Africa/Lagos' };
const still = '*,*::before,*::after{animation:none!important;transition:none!important}';
const errors = [];
const watch = (page, who) => page.on('pageerror', (e) => errors.push(`${who}: ${e.message}`));

(async () => {
  if (/^\d+\.\d+\.\d+\.\d+$/.test(APP.hostname)) {
    console.log('  SKIP  Ops needs APP_URL on a name (http://localhost:8099), not an IP address');
    console.log('RESULT: 0 passed, 0 failed');
    return;
  }
  cli('reset');
  const exe = process.env.CHROMIUM_PATH || (fs.existsSync('/opt/pw-browsers/chromium-1194/chrome-linux/chrome') ? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' : undefined);
  const browser = await chromium.launch(exe ? { executablePath: exe } : {});

  console.log('\nSigning in on a phone');
  const ctx = await browser.newContext(PHONE);
  const page = await ctx.newPage();
  watch(page, 'grace');
  await page.goto(`${OPS}/team`);
  ok(page.url() === `${OPS}/signin`, 'signed out, Team opens the sign-in page');
  await page.addStyleTag({ content: still });
  await page.screenshot({ path: path.join(OUT, 'signin-phone.png') });
  await page.fill('#email', 'grace@nfelia.test');
  await page.fill('#password', PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/signin"] button[type=submit]')]);
  ok(page.url() === `${OPS}/team`, 'then Team itself: ' + page.url().replace(OPS, ''));
  ok(await page.locator('h1', { hasText: 'Team' }).count() === 1, 'the team list');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(overflow <= 0, 'nothing runs off the side of a phone (overflow ' + overflow + 'px)');
  await page.addStyleTag({ content: still });
  await page.screenshot({ path: path.join(OUT, 'team-phone.png'), fullPage: true });

  console.log('\nThe menu on a phone');
  ok(!(await page.locator('.ops-side').isVisible()) || (await page.locator('.ops-side').boundingBox()).x < 0, 'the menu starts closed');
  await page.click('[data-ops-menu]');
  await page.waitForTimeout(100);
  ok((await page.locator('.ops-side').boundingBox()).x >= 0, 'the menu button opens it');
  await page.screenshot({ path: path.join(OUT, 'menu-phone.png') });
  await page.keyboard.press('Escape');
  await page.waitForTimeout(100);
  ok((await page.locator('.ops-side').boundingBox()).x < 0, 'Escape closes it');

  console.log('\nInviting someone');
  await page.click('[data-ops-dialog=invite]');
  ok(await page.locator('#invite[open]').count() === 1, '"Invite someone" opens the dialog');
  ok(await page.evaluate(() => document.activeElement && document.activeElement.id === 'inv-name'), 'with the name field ready to type in');
  await page.screenshot({ path: path.join(OUT, 'invite-phone.png') });
  await page.fill('#inv-name', 'Tobi New');
  await page.fill('#inv-email', 'tobi@nfelia.test');
  await page.check('input[name=role][value=sales]');
  await Promise.all([page.waitForNavigation(), page.click('#invite button[type=submit]')]);
  ok((await page.content()).includes('Invite sent to tobi@nfelia.test'), 'sending it says so');
  ok(sql("SELECT role FROM ops_staff WHERE email = 'tobi@nfelia.test'")[0]?.role === 'sales', 'and Tobi is on the team as Sales');

  console.log('\nA passkey, added and used');
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true,
    hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true } });
  await page.goto(`${OPS}/account`);
  await Promise.all([page.waitForNavigation({ timeout: 10000 }).catch(() => {}), page.click('[data-ops-passkey-add]')]);
  await page.waitForLoadState('load');
  const keys = sql('SELECT name FROM ops_passkeys WHERE staff_id = 1');
  ok(keys.length === 1, 'a passkey is added for Grace: ' + (keys[0]?.name || 'none') + ' ' + (await page.locator('[data-ops-passkey-note]').textContent()));
  ok(await page.locator('[data-ops-passkey-list] li').count() === 1, 'and listed on her account');
  await page.addStyleTag({ content: still });
  await page.screenshot({ path: path.join(OUT, 'account-phone.png'), fullPage: true });
  await page.locator('.ops-top [data-ops-menu]').click();
  await Promise.all([page.waitForNavigation(), page.click('.ops-me button[type=submit]')]);
  ok(page.url() === `${OPS}/signin`, 'signing out goes back to sign-in');
  await Promise.all([page.waitForNavigation({ timeout: 10000 }), page.click('[data-ops-passkey-signin]')]);
  ok(page.url() === `${OPS}/`, 'one touch on "Sign in with a passkey" signs Grace back in: ' + page.url().replace(OPS, ''));
  ok(sql("SELECT summary FROM ops_audit WHERE action = 'signin' ORDER BY id DESC LIMIT 1")[0]?.summary === 'Signed in with a passkey', 'the audit log says how');

  console.log('\nOn a computer');
  const wide = await (await browser.newContext(COMPUTER)).newPage();
  watch(wide, 'computer');
  await wide.goto(`${OPS}/signin`);
  await wide.fill('#email', 'grace@nfelia.test');
  await wide.fill('#password', PASSWORD);
  await Promise.all([wide.waitForNavigation(), wide.click('form[action$="/signin"] button[type=submit]')]);
  ok(await wide.locator('.ops-side').isVisible(), 'the menu sits at the side');
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'home-computer.png'), fullPage: true });
  await wide.goto(`${OPS}/team`);
  await wide.addStyleTag({ content: still });
  await wide.keyboard.press('/');
  await wide.keyboard.type('sam');
  ok(await wide.locator('#team-table tbody tr:visible').count() === 1, '"/" then typing filters the team as you type');
  await wide.fill('#team-search', '');
  await wide.screenshot({ path: path.join(OUT, 'team-computer.png'), fullPage: true });
  await wide.goto(`${OPS}/audit`);
  await wide.addStyleTag({ content: still });
  ok(await wide.locator('.ops-feed li').count() > 3, 'the audit log lists what was done');
  await wide.screenshot({ path: path.join(OUT, 'audit-computer.png'), fullPage: true });

  console.log('\nThe monitor');
  // Break the app a few ways, as tests/ops/boom.php allows on a test copy.
  for (const what of ['exception', 'warning', 'fatal']) await wide.request.get(`${APP.origin}/__boom/${what}`).catch(() => {});
  const crash = await (await browser.newContext(PHONE)).newPage();
  await crash.goto(`${APP.origin}/__boom/exception`);
  const code = (await crash.locator('#ref').textContent().catch(() => '')).trim();
  ok(/^E[A-Z2-9]{7}$/.test(code), 'the error page shows its code: ' + code);
  await crash.addStyleTag({ content: still });
  await crash.screenshot({ path: path.join(OUT, 'error-page-phone.png') });
  await crash.goto(`${APP.origin}/login`);
  await crash.evaluate(() => setTimeout(() => { throw new Error('UI test: thrown in the browser'); }, 0));
  await crash.waitForTimeout(1200);
  ok(sql("SELECT COUNT(*) AS n FROM error_groups WHERE kind = 'js' AND title LIKE '%UI test: thrown in the browser%'")[0]?.n === 1,
     'a script error in a real browser reaches Ops (assets/js/errors.js)');

  await wide.goto(`${OPS}/errors`);
  await wide.addStyleTag({ content: still });
  ok(await wide.locator('table tbody tr').count() >= 3, 'the errors list shows them');
  await wide.screenshot({ path: path.join(OUT, 'errors-computer.png'), fullPage: true });
  await wide.click('details.ops-filters > summary');
  ok(await wide.locator('.ops-filters-panel').isVisible(), 'Filters opens its panel');
  await wide.screenshot({ path: path.join(OUT, 'errors-filters-computer.png') });
  await wide.keyboard.press('Escape');
  await wide.locator('[data-ops-tick]').first().check();
  ok(await wide.locator('[data-ops-bulk-bar]').isVisible(), 'ticking one shows what can be done to it');
  await wide.fill('#err-q', code);
  await Promise.all([wide.waitForNavigation(), wide.press('#err-q', 'Enter')]);
  ok(wide.url().includes('#' + code) && await wide.locator(`li#${code}.is-focus details[open]`).count() === 1, 'typing the code opens its problem, on that occurrence');
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'error-computer.png'), fullPage: true });
  await wide.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: OPS });
  await wide.click('[data-ops-copy]');
  await wide.waitForTimeout(200);
  const copied = await wide.evaluate(() => navigator.clipboard.readText()).catch(() => '');
  ok(copied.includes('Stack trace:') && copied.includes('tests/ops/boom.php'), '"Copy for AI" copies the problem with its stack trace');
  await wide.goto(`${OPS}/health`);
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'health-computer.png'), fullPage: true });
  await page.goto(`${OPS}/errors`);
  await page.addStyleTag({ content: still });
  const over = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(over <= 0, 'the errors list fits a phone (overflow ' + over + 'px)');
  await page.screenshot({ path: path.join(OUT, 'errors-phone.png'), fullPage: true });
  await page.goto(`${OPS}/alerts`);
  await page.addStyleTag({ content: still });
  ok(await page.locator('.ops-inbox li.is-new').count() > 0, 'the alerts inbox has them, unread');
  await page.screenshot({ path: path.join(OUT, 'alerts-phone.png'), fullPage: true });

  console.log('\nClients, and viewing one as its owner');
  await wide.goto(`${OPS}/clients`);
  await wide.addStyleTag({ content: still });
  ok(await wide.locator('table tbody tr').count() >= 3, 'the clients list');
  await wide.screenshot({ path: path.join(OUT, 'clients-computer.png'), fullPage: true });
  await wide.goto(`${OPS}/clients/2`);
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'client-computer.png'), fullPage: true });
  await wide.click('[data-ops-dialog=view-as]');
  await wide.fill('#view-why', 'Checking the invoice layout for a ticket');
  const [tab] = await Promise.all([wide.context().waitForEvent('page'), wide.click('#view-as button[type=submit]')]);
  await tab.waitForLoadState('load');
  watch(tab, 'view-as');
  ok(tab.url() === `${APP.origin}/dashboard`, 'View as opens their Nfelia in a new tab: ' + tab.url());
  ok(await tab.locator('[data-view-as]').count() === 1, 'with the read-only banner');
  await tab.addStyleTag({ content: still });
  await tab.screenshot({ path: path.join(OUT, 'view-as-computer.png') });
  await Promise.all([tab.waitForNavigation(), tab.click('[data-view-as] button[type=submit]')]);
  ok(tab.url().startsWith(`${OPS}/clients/2`), 'End goes back to the client in Ops');
  await page.goto(`${OPS}/clients/2`);
  await page.addStyleTag({ content: still });
  const wideness = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(wideness <= 0, 'a client page fits a phone (overflow ' + wideness + 'px)');
  await page.screenshot({ path: path.join(OUT, 'client-phone.png'), fullPage: true });

  console.log('\nSales: leads, the board, a campaign');
  await wide.goto(`${OPS}/leads`);
  await wide.addStyleTag({ content: still });
  ok(await wide.locator('.ops-table-wrap .ops-empty').count() === 1, 'with no leads yet, the list keeps its frame and says what arrives there');
  await wide.click('[data-ops-dialog=add-lead]');
  ok(await wide.locator('#add-lead[open]').count() === 1, '"Add a lead" opens the dialog');
  await wide.fill('#nl-name', 'Bola Ade');
  await wide.fill('#nl-biz', 'Bola Fabrics');
  await wide.fill('#nl-phone', '0803 555 0199');
  await wide.fill('#nl-email', 'bola@fabrics.test');
  await wide.selectOption('#nl-source', 'event');
  await wide.fill('#nl-fu', '2030-01-15T10:30');
  await wide.fill('#nl-fun', 'Send the price list');
  await wide.screenshot({ path: path.join(OUT, 'lead-add-computer.png') });
  await Promise.all([wide.waitForNavigation(), wide.click('#add-lead button[type=submit]')]);
  ok(await wide.locator('h1', { hasText: 'Bola Fabrics' }).count() === 1, 'adding it opens the lead');
  ok(sql("SELECT next_follow_up_at FROM leads WHERE business_name = 'Bola Fabrics'")[0]?.next_follow_up_at === '2030-01-15 10:30:00', 'the follow-up is kept as typed, in Lagos time');
  await wide.fill('#lead-note', 'Called: sells ankara in Balogun, wants invoices on WhatsApp.');
  await Promise.all([wide.waitForNavigation(), wide.click('#notes button[type=submit]')]);
  ok(await wide.locator('.ops-notes li', { hasText: 'sells ankara in Balogun' }).count() === 1, 'a note is saved and listed');
  await Promise.all([wide.waitForNavigation(), wide.click('.ops-stepper button:has-text("Demo")')]);
  ok(await wide.locator('.ops-stepper li.is-current', { hasText: 'Demo' }).count() === 1, 'one press on the pipeline moves it to Demo');
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'lead-computer.png'), fullPage: true });
  await wide.goto(`${OPS}/leads?view=board`);
  await wide.addStyleTag({ content: still });
  ok(await wide.locator('.ops-board-col').count() === 6 && await wide.locator('.ops-board-col[aria-label=Demo] .ops-lead-card').count() === 1, 'the board has a column per stage, Bola under Demo');
  await wide.screenshot({ path: path.join(OUT, 'leads-board-computer.png'), fullPage: true });
  await page.goto(`${OPS}/leads?view=board`);
  await page.addStyleTag({ content: still });
  const boardOver = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(boardOver <= 0, 'on a phone the board scrolls inside itself, not the page (overflow ' + boardOver + 'px)');
  await page.screenshot({ path: path.join(OUT, 'leads-board-phone.png'), fullPage: true });
  await page.goto(`${OPS}/leads/` + sql("SELECT id FROM leads WHERE business_name = 'Bola Fabrics'")[0].id);
  await page.addStyleTag({ content: still });
  const leadOver = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(leadOver <= 0, 'a lead fits a phone (overflow ' + leadOver + 'px)');
  await page.screenshot({ path: path.join(OUT, 'lead-phone.png'), fullPage: true });

  await wide.goto(`${OPS}/campaigns/new`);
  await wide.fill('#c-name', 'Demo follow-up');
  await wide.fill('#c-subject', '{first_name}, here is what Nfelia does');
  await wide.fill('#c-body', 'Hello {first_name},\n\nThanks for the chat. {business} can send invoices on WhatsApp in a minute.\n\nThe Nfelia team');
  await wide.fill('#c-bt', 'Start free');
  await wide.fill('#c-bu', 'https://nfelia.com/register');
  await wide.check('input[name="leads[]"][value=demo]');
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'campaign-form-computer.png'), fullPage: true });
  await Promise.all([wide.waitForNavigation(), wide.click('form.ops-card button[type=submit]')]);
  ok(await wide.locator('button', { hasText: 'Send to 1 person' }).count() === 1, 'saved, it says who it reaches before anything goes');
  const preview = wide.frameLocator('iframe.ops-preview');
  ok(await preview.locator('text=Bola Fabrics can send invoices').count() === 1, 'and shows the email as Bola will get it');
  await wide.addStyleTag({ content: still });
  await wide.screenshot({ path: path.join(OUT, 'campaign-computer.png'), fullPage: true });
  wide.once('dialog', (d) => d.accept());
  await Promise.all([wide.waitForNavigation(), wide.click('button:has-text("Send to 1 person")')]);
  ok(sql("SELECT status FROM campaigns WHERE name = 'Demo follow-up'")[0]?.status === 'sending', 'Send asks first, then starts it');
  await page.goto(`${OPS}/campaigns/` + sql("SELECT id FROM campaigns WHERE name = 'Demo follow-up'")[0].id);
  await page.addStyleTag({ content: still });
  const campOver = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(campOver <= 0, 'a campaign fits a phone (overflow ' + campOver + 'px)');
  await page.screenshot({ path: path.join(OUT, 'campaign-phone.png'), fullPage: true });

  ok(errors.length === 0, 'no script errors on any page' + (errors.length ? ': ' + errors.join(' | ') : ''));
  await browser.close();
  console.log(`\nScreenshots: ${OUT}`);
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  failed.forEach((f) => console.log('  - ' + f));
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
