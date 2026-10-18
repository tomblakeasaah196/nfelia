// The app foundation, end to end: the shell (app bar, tabs, sidebar, More,
// Back), the Create and Install sheets, toasts, the network banner, offline
// reading through the service worker and the offline page, the opening, text
// size and background, the website's "Get the app", and the install details
// on public pages. Runs against the local copy on :8099 (see up.sh).
//   node pwa_test.js OUTDIR
const fs = require('fs');
const path = require('path');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const BASE = 'http://127.0.0.1:8099';
const OUT = process.argv[2] || path.join(__dirname, 'build', 'pwa');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const PHONE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' };
const SMALL = { viewport: { width: 375, height: 812 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' };
const DESK = { viewport: { width: 1280, height: 900 }, timezoneId: 'Africa/Lagos' };
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
const errors = [];
function watch(page, tag) {
  page.on('pageerror', (e) => errors.push(tag + ' pageerror: ' + e.message));
  page.on('console', (m) => {
    // A deliberately offline page logs its failed requests; those are expected.
    if (m.type() === 'error' && !/ERR_INTERNET_DISCONNECTED|ERR_CONNECTION_REFUSED|Failed to load resource/.test(m.text())) errors.push(tag + ' console: ' + m.text());
  });
}
async function signIn(ctx, email = 'ivie@demo.nfelia') {
  const page = await ctx.newPage();
  watch(page, email);
  await page.goto(BASE + '/login');
  await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}
const still = '*{animation:none!important;transition:none!important}';
const shot = (page, name, full = false) => page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: full });
const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ executablePath: CHROME }).catch(() => chromium.launch());

  // ---------- Public pages: install details and "Get the app" ----------
  console.log('Public pages');
  {
    const ctx = await browser.newContext(DESK);
    const page = await ctx.newPage(); watch(page, 'public');
    for (const url of ['/', '/login', '/register']) {
      await page.goto(BASE + url);
      ok(await page.locator('link[rel=manifest]').count() === 1, `${url}: one manifest link`);
      ok(await page.locator('link[rel=apple-touch-startup-image]').count() === 20, `${url}: 20 iPhone/iPad launch screens`);
      ok(await page.locator('meta[name=apple-mobile-web-app-title][content=Nfelia]').count() === 1, `${url}: home screen title is Nfelia`);
    }
    const manifest = await (await page.request.get(BASE + '/site.webmanifest')).json();
    ok(manifest.name === 'Nfelia' && manifest.start_url === '/dashboard?source=pwa' && manifest.display === 'standalone', 'manifest: name, start page, standalone');
    const srcs = [...manifest.icons.map((i) => i.src), ...manifest.shortcuts.flatMap((s) => s.icons.map((i) => i.src))];
    let icons = 0;
    for (const src of srcs) if ((await page.request.get(BASE + src)).status() === 200) icons++;
    ok(icons === srcs.length, `manifest: all ${srcs.length} icons load`);
    ok(manifest.icons.some((i) => i.purpose === 'maskable') && manifest.icons.some((i) => i.purpose === 'monochrome'), 'manifest: maskable and monochrome icons');
    for (const p of ['/offline.html', '/sw.js', '/icons/launch/1170x2532.png', '/icons/badge-96.png']) {
      ok((await page.request.get(BASE + p)).status() === 200, `${p} is served`);
    }
    // Website: Get the app, on a computer.
    await page.goto(BASE + '/');
    await page.click('.nav-links [data-dialog="get-app"]');
    ok(await page.locator('#get-app[open]').count() === 1, 'website: Get the app opens from the header');
    ok(await page.locator('#get-app [data-app="desktop"]').isVisible(), 'website: a computer is pointed to the phone');
    ok(!(await page.locator('#get-app [data-app="ios"]').isVisible()) && !(await page.locator('#get-app [data-app="menu"]').isVisible()), 'website: phone steps stay hidden on a computer');
    await page.addStyleTag({ content: still });
    await shot(page, 'web_getapp_desktop');
    await page.keyboard.press('Escape');
    await ctx.close();

    const ios = await browser.newContext({ ...SMALL, userAgent: IPHONE_UA });
    const ip = await ios.newPage(); watch(ip, 'public-ios');
    await ip.goto(BASE + '/');
    await ip.locator('.site-footer [data-dialog="get-app"]').scrollIntoViewIfNeeded();
    await ip.click('.site-footer [data-dialog="get-app"]');
    ok(await ip.locator('#get-app [data-app="ios"]').isVisible(), 'website on iPhone: the three Share steps');
    const stepH = await ip.locator('#get-app [data-app="ios"] li').first().evaluate((li) => li.getBoundingClientRect().height);
    ok(stepH < 80, 'website on iPhone: step 1 reads as one short paragraph (' + Math.round(stepH) + 'px)');
    ok(!(await ip.locator('#get-app [data-app="desktop"]').isVisible()), 'website on iPhone: no computer message');
    await ip.addStyleTag({ content: still });
    await shot(ip, 'web_getapp_iphone');
    ok(await overflow(ip) <= 0, 'website at 375: no sideways scroll');
    await ios.close();
  }

  // ---------- The shell on a phone ----------
  console.log('Shell on a phone');
  const phone = await browser.newContext(PHONE);
  let page = await signIn(phone);
  ok(/\/dashboard$/.test(page.url()), 'signed in to Home');
  ok(await page.locator('body[data-just-signed-in]').count() === 1, 'first page after sign-in is marked for the install invitation');
  ok(await page.locator('.nf-tabbar').isVisible(), 'tabs show on a phone');
  ok(!(await page.locator('.nf-side').isVisible()), 'sidebar hidden on a phone');
  ok(await page.locator('.nf-tab[aria-current="page"]').innerText() === 'Home', 'Home tab is current');
  ok(await page.locator('.nf-ab-biz').isVisible(), 'app bar shows the business on Home');
  const tokens = await page.evaluate(() => { const s = getComputedStyle(document.documentElement); return ['--accent', '--accent-text', '--on-accent'].map((k) => s.getPropertyValue(k).trim()); });
  ok(tokens[0].toUpperCase() === '#7A1F4B', 'business colour is the accent (' + tokens.join(' ') + ')');
  ok(await page.evaluate(() => getComputedStyle(document.body).fontFamily.includes('Plus Jakarta Sans')), 'body text in Plus Jakarta Sans');
  ok(await page.evaluate(() => document.fonts.check('600 16px "Plus Jakarta Sans"')), 'Plus Jakarta Sans loaded from the app itself');
  ok(await page.locator('script[src*="cdn.tailwindcss.com"]').count() === 0, 'no Tailwind CDN in the app');
  await page.addStyleTag({ content: still });
  await shot(page, 'phone_home');
  ok(await overflow(page) <= 0, 'Home at 390: no sideways scroll');

  // Toast
  await page.evaluate(() => window.nfToast('Invoice saved', 'success'));
  ok(await page.locator('.nf-toast.is-success').isVisible(), 'nfToast shows a toast');
  await page.waitForTimeout(4600);
  ok(await page.locator('.nf-toast').count() === 0, 'the toast closes itself');

  // Create sheet
  await page.click('.nf-tab-create');
  ok(await page.locator('#nf-create[open]').count() === 1, '+ opens Create');
  ok(await page.locator('#nf-create .nf-create a').count() >= 6, 'Create lists at least six things');
  await shot(page, 'phone_create');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(350);
  ok(await page.locator('#nf-create[open]').count() === 0, 'Escape closes Create');
  await page.click('.nf-tab-create');
  await page.waitForTimeout(350);
  await Promise.all([page.waitForNavigation(), page.click('#nf-create >> text=Add task')]);
  await page.waitForSelector('#pl-title', { timeout: 5000 }).catch(() => {});
  ok(/\/tasks$/.test(page.url()) && await page.locator('#pl-title').isVisible(), 'Create → Add task opens the task editor');
  await page.goto(BASE + '/dashboard');
  await page.evaluate(() => window.scrollTo(0, 400));
  await page.waitForTimeout(350);
  const barBg = await page.evaluate(() => getComputedStyle(document.querySelector('.nf-appbar'), '::before').opacity);
  ok(barBg === '1', 'scrolled: the app bar turns solid');
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('.nf-tabbar')).backdropFilter === 'none'), 'no frosted glass on the tabs');

  // More, a tile, Back
  await Promise.all([page.waitForNavigation(), page.click('.nf-tab >> text=More')]);
  ok(/\/more$/.test(page.url()) && await page.locator('.nf-large-title').innerText() === 'More', 'More tab opens More');
  await page.addStyleTag({ content: still });
  await shot(page, 'phone_more', true);
  ok(await overflow(page) <= 0, 'More at 390: no sideways scroll');
  await Promise.all([page.waitForNavigation(), page.click('.nf-tile >> text=Clients')]);
  ok(await page.locator('.nf-ab-lead.has-back [data-back]').isVisible(), 'below a tab: Back in the app bar');
  ok((await page.locator('.nf-tab[aria-current]').innerText()) === 'More', 'Clients sits under the More tab');
  await Promise.all([page.waitForNavigation(), page.click('[data-back]')]);
  ok(/\/more$/.test(page.url()), 'Back returns to More');

  // Appearance
  await page.click('[data-open="nf-appearance"]');
  await page.waitForTimeout(450); // the sheet slides up
  ok(await page.locator('#nf-appearance[open]').count() === 1, 'More: Text size and background opens');
  await page.locator('#nf-appearance input[value="large"]').check({ force: true });
  ok(await page.evaluate(() => document.documentElement.dataset.text) === 'large', 'Large applies at once');
  const rootPx = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
  ok(Math.abs(rootPx - 17.5) < .01, 'Large text: root size 17.5px');
  await page.locator('#nf-appearance input[value="cream"]').check({ force: true });
  ok(await page.evaluate(() => document.querySelector('meta[name=theme-color]').content) === '#F7F3EA', 'Cream changes the status bar colour');
  await page.addStyleTag({ content: still });
  await shot(page, 'phone_appearance');
  await page.reload();
  ok(await page.evaluate(() => [document.documentElement.dataset.text, document.documentElement.dataset.bg].join()) === 'large,cream', 'the choice is kept for this device');
  await page.click('[data-open="nf-appearance"]');
  await page.waitForTimeout(450);
  await page.locator('#nf-appearance input[value="standard"]').check({ force: true });
  await page.locator('#nf-appearance input[value="white"]').check({ force: true });
  await page.keyboard.press('Escape');
  await page.goto(BASE + '/settings?tab=profile');
  ok(await page.locator('#appearance .nf-seg').isVisible(), 'Settings → Profile has Appearance');
  ok(await page.locator('#appearance input[value="standard"]').isChecked(), 'Settings shows the current text size');
  ok(await overflow(page) <= 0, 'Settings at 390: no sideways scroll');

  // ---------- Service worker: files and pages kept, offline reading ----------
  console.log('Offline');
  await page.goto(BASE + '/dashboard');
  await page.evaluate(() => navigator.serviceWorker.ready);
  await page.reload();
  ok(await page.evaluate(() => !!navigator.serviceWorker.controller), 'the service worker runs the page');
  for (const url of ['/dashboard', '/invoices', '/tasks', '/more']) await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const kept = await page.evaluate(async () => (await (await caches.open('nf-pages')).keys()).map((r) => new URL(r.url).pathname));
  ok(['/dashboard', '/invoices', '/tasks', '/more'].every((p) => kept.includes(p)), 'opened pages are kept: ' + kept.join(' '));
  const files = await page.evaluate(async () => {
    const names = await caches.keys(); const f = names.find((n) => n.startsWith('nf-files-'));
    return f ? (await (await caches.open(f)).keys()).map((r) => new URL(r.url).pathname) : [];
  });
  ok(files.includes('/assets/css/app.css') && files.includes('/offline.html'), 'app files are kept');
  await page.goto(BASE + '/settings');
  await page.waitForTimeout(300);
  const keptSettings = await page.evaluate(async () => !!(await (await caches.open('nf-pages')).match(location.origin + '/settings')));
  ok(!keptSettings, 'Settings is never kept on the phone');

  await phone.setOffline(true);
  await page.goto(BASE + '/invoices').catch(() => {});
  await page.waitForTimeout(600);
  ok(await page.locator('meta[name="nf-cached"]').count() === 1, 'offline: Invoices opens from the kept copy');
  ok(await page.locator('.nf-net').isVisible(), 'offline: the banner says so');
  const banner = await page.locator('.nf-net').innerText();
  ok(/offline/i.test(banner) && /as it was at/.test(banner), 'banner: "' + banner.replace(/\s+/g, ' ') + '"');
  await shot(page, 'phone_offline_kept');
  await page.waitForTimeout(5400);
  ok(await page.locator('.nf-net').count() === 0, 'the banner goes after 5 seconds');
  await page.goto(BASE + '/suppliers').catch(() => {});
  await page.waitForTimeout(400);
  ok(await page.locator('h1').innerText() === 'No connection', 'offline, a page never opened: the offline page');
  await shot(page, 'phone_offline_page');
  // Back on a kept page, sending a form offline is stopped with the text kept.
  await page.goto(BASE + '/more').catch(() => {});
  await page.waitForTimeout(300);
  const before = page.url();
  await page.evaluate(() => {
    const f = document.createElement('form'); f.method = 'POST'; f.action = '/customers/create';
    const i = document.createElement('input'); i.name = 'full_name'; i.value = 'Typed offline'; f.appendChild(i);
    document.querySelector('main').appendChild(f); f.requestSubmit();
  });
  await page.waitForTimeout(500);
  ok(page.url() === before && /Nothing was sent/.test(await page.locator('.nf-net').innerText()), 'offline: a form is not sent, and the person is told');
  await phone.setOffline(false);
  await page.goto(BASE + '/dashboard');
  ok(await page.locator('meta[name="nf-cached"]').count() === 0, 'back online: pages come from the server');

  // Sign out drops every kept page.
  await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
  await page.waitForTimeout(500);
  ok(!(await page.evaluate(() => caches.has('nf-pages'))), 'signing out drops the kept pages');

  // ---------- The opening ----------
  console.log('Opening');
  page = await signIn(phone);
  await page.goto(BASE + '/dashboard?source=pwa');
  ok(await page.evaluate(() => document.documentElement.classList.contains('nf-splashing')), 'app start: the opening shows');
  ok(await page.evaluate(() => document.querySelector('meta[name=theme-color]').content) === '#6E0039', 'app start: status bar is wine');
  await page.waitForTimeout(1250);
  await shot(page, 'phone_opening_mid');
  await page.waitForFunction(() => !document.getElementById('nf-splash'), null, { timeout: 12000 });
  ok(!/source=pwa/.test(page.url()), 'after the opening the address is clean');
  ok(await page.evaluate(() => document.querySelector('meta[name=theme-color]').content) === '#FFFFFF', 'after the opening the status bar is white');
  await page.goto(BASE + '/dashboard?source=pwa');
  ok(await page.locator('#nf-splash').count() === 0, 'same launch: the opening does not repeat');
  ok(await page.evaluate(() => document.querySelector('meta[name=theme-color]').content) === '#FFFFFF' && !/source=pwa/.test(page.url()), 'same launch: white status bar, clean address');
  await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
  await page.evaluate(() => sessionStorage.clear());
  await page.goto(BASE + '/dashboard?source=pwa');
  ok(/\/login\?source=pwa$/.test(page.url()), 'app start signed out: straight to sign-in');
  ok(await page.locator('#nf-splash').count() === 1, 'sign-in plays the opening');
  ok(await page.locator('.flash, [role=alert]').count() === 0 || !/log in to continue/i.test(await page.content()), 'no "please log in" scolding on an app start');
  await page.waitForFunction(() => !document.getElementById('nf-splash'), null, { timeout: 12000 });
  ok(/\/login$/.test(page.url()), 'sign-in address is clean after the opening');
  await phone.close();

  // ---------- Install invitation on iPhone ----------
  console.log('Install');
  const iphone = await browser.newContext({ ...PHONE, userAgent: IPHONE_UA });
  page = await signIn(iphone);
  await page.waitForTimeout(1200);
  ok(await page.locator('#nf-install[open]').count() === 1, 'iPhone: after sign-in, the invitation to add Nfelia');
  ok(await page.locator('#nf-install [data-install-ios]').isVisible(), 'iPhone: the Share steps show');
  ok(!(await page.locator('#nf-install [data-install-go]').isVisible()) && await page.locator('#nf-install [data-install-done]').isVisible(), 'iPhone: Not now and Done, no Install button');
  ok(await page.evaluate(() => document.activeElement && document.activeElement.id === 'nf-install-h'), 'the sheet focuses its title, not a button');
  await page.addStyleTag({ content: still });
  await shot(page, 'phone_install_iphone');
  await page.click('[data-install-later]');
  await page.waitForTimeout(400);
  const snooze = await page.evaluate(() => Number(localStorage.getItem('nf.install.snooze')) - Date.now());
  ok(snooze > 6.9 * 864e5 && snooze <= 7 * 864e5, 'Not now waits 7 days');
  await page.goto(BASE + '/more');
  ok(await page.locator('[data-install-row]').isVisible(), 'More offers Install the app');
  await iphone.close();

  // ---------- Small phone and computer ----------
  console.log('Sizes');
  const small = await browser.newContext(SMALL);
  page = await signIn(small);
  for (const url of ['/dashboard', '/more', '/invoices', '/tasks', '/customers', '/products', '/settings?tab=profile']) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    ok(await overflow(page) <= 0, `375 ${url}: no sideways scroll`);
  }
  await page.goto(BASE + '/invoices');
  await page.addStyleTag({ content: still });
  await shot(page, 'small_invoices');
  await small.close();

  const desk = await browser.newContext(DESK);
  page = await signIn(desk);
  ok(await page.locator('.nf-side').isVisible() && !(await page.locator('.nf-tabbar').isVisible()), 'computer: sidebar, no tabs');
  ok(await page.locator('.nf-side-link[aria-current="page"]').innerText() === 'Home', 'computer: Home is current in the sidebar');
  await page.click('[aria-label="Account"]');
  ok(await page.locator('.nf-pop.nf-menu >> text=Settings').isVisible(), 'computer: account menu opens');
  await page.keyboard.press('Escape');
  await page.addStyleTag({ content: still });
  await shot(page, 'desk_home');
  await page.goto(BASE + '/more');
  ok(!(await page.locator('[data-install-row]').isVisible()), 'computer without an install offer: no Install the app row');
  await page.addStyleTag({ content: still });
  await shot(page, 'desk_more');
  await desk.close();

  // A second business: its colour, its name.
  const kemi = await browser.newContext(PHONE);
  page = await signIn(kemi, 'kemi@demo.nfelia');
  const k = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--accent').trim());
  ok(k.toUpperCase() === '#0F5C4A', 'second business: its own accent ' + k);
  await page.addStyleTag({ content: still });
  await shot(page, 'phone_home_kemi');
  await kemi.close();

  await browser.close();
  console.log(errors.length ? 'ERRORS:\n  ' + errors.join('\n  ') : 'No page errors.');
  console.log(`${pass} passed, ${fail} failed`);
  process.exit(fail || errors.length ? 1 : 0);
})();
