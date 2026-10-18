// The landing page's screens: two per "Sound familiar?" card, and Ivie's
// website for the "Your own website" section. Real app, demo data, phone
// size (390 × 844 at 2x), then WebP into assets/img/home/.
//
// Needs: .env with APP_URL=http://localhost:8000 (Chromium keeps the app's
// __Host- cookie only on a secure origin, and localhost counts as one), the
// local server, seed.php, planner_seed.sql.txt, then landing_seed.php <out>,
// npm install (Tailwind, for the public documents), cwebp, and Chart.js 4.4.1
// at <out>/libs/node_modules/chart.js (npm install --prefix <out>/libs chart.js@4.4.1).
//   php -S 127.0.0.1:8000 docs/handoff/testkit/router.php
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/shoot_landing.js <out> [name …]
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../../..');
const OUT = process.argv[2];
const ONLY = process.argv.slice(3);
const APP = 'http://localhost:8000';
const SHOP = 'http://ivieluxe.localhost:8000';
const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql}"`).toString().trim();
const T = JSON.parse(fs.readFileSync(path.join(OUT, 'tokens.json'), 'utf8'));
const tok = {
  kemi: q('SELECT token FROM invoices WHERE id = 20'), paid: q('SELECT token FROM invoices WHERE id = 7'),
  del: q('SELECT token FROM deliveries LIMIT 1'), sign: q('SELECT signoff_token FROM deliveries LIMIT 1'),
  debt: q("SELECT id FROM debts WHERE business_id = 1 AND description = 'Balance on IVL-INV-133'"),
  booking: q("SELECT id FROM bookings WHERE booking_number = 'IVL-BKG-007'"),
};

// Tailwind for the public documents, which load it from a CDN at run time.
let twCss = null;
function compileTailwind(name, config, html) {
  const dir = path.join(OUT, 'tw', name);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'page.html'), html);
  fs.writeFileSync(path.join(dir, 'in.css'), '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n');
  fs.writeFileSync(path.join(dir, 'tw.config.js'), 'module.exports = ' + JSON.stringify({ content: [path.join(dir, 'page.html')], theme: (config && config.theme) || {} }) + ';');
  execSync(`${ROOT}/node_modules/.bin/tailwindcss -c ${dir}/tw.config.js -i ${dir}/in.css -o ${dir}/out.css --minify`, { stdio: 'pipe' });
  return fs.readFileSync(path.join(dir, 'out.css'), 'utf8');
}

const scrollToText = (text, offset) => (p) => p.evaluate(([t, o]) => {
  const el = [...document.querySelectorAll('h1,h2,h3,h4,p,span,legend,div')].find((e) => e.children.length < 3 && e.textContent.trim().startsWith(t));
  if (el) window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - o);
}, [text, offset]);
const shopList = (p) => p.evaluate(() => {
  const h = [...document.querySelectorAll('h2')].find((e) => e.textContent.trim() === 'Shop');
  window.scrollTo(0, h.getBoundingClientRect().top + scrollY - 16);
});
const checkout = async (p) => {
  await p.locator('[data-add]').first().click();
  await p.locator('[data-open-bag]').first().click();
  await p.locator('[data-go="details"]').click();
  await p.fill('[name=customer_name]', 'Kemi Bello'); await p.fill('[name=customer_phone]', '0814 555 0210');
  await p.locator('[data-step="details"] button[type=submit]').click();
  await p.locator('[data-step="delivery"] [value="pickup"]').check({ force: true });
  await p.locator('[data-step="delivery"] button[type=submit]').click();
  await p.locator('[data-step="pay"]').waitFor();
};
const signature = async (p) => {
  await p.fill('input[x-model=signoffName]', 'Lara Coker');
  const pad = p.locator('#signature-pad'); await pad.scrollIntoViewIfNeeded();
  const b = await pad.boundingBox();
  const pts = [[20, 120], [40, 60], [55, 110], [70, 50], [90, 115], [105, 80], [130, 95], [150, 70], [175, 100], [200, 75], [230, 105], [260, 85], [290, 95]];
  await p.mouse.move(b.x + pts[0][0], b.y + pts[0][1]); await p.mouse.down();
  for (const [x, y] of pts.slice(1)) await p.mouse.move(b.x + x, b.y + y, { steps: 4 });
  await p.mouse.up();
  await p.evaluate(() => window.scrollBy(0, -170));
};

// [file in assets/img/home, url, prepare?]
const SHOTS = [
  ['see/owed-1', APP + '/invoices'],
  ['see/owed-2', APP + '/debts/view/' + tok.debt],
  ['see/profit-1', APP + '/dashboard'],
  ['see/profit-2', APP + '/reports/profit-loss'],
  ['see/stock-1', APP + '/products?stock=low'],
  ['see/stock-2', APP + '/products/view/3'],
  ['see/paid-1', APP + '/orders/view/103', scrollToText('Payment', 70)],
  ['see/paid-2', APP + '/receipts'],
  ['see/invoice-1', APP + '/doc/' + tok.kemi],
  ['see/invoice-2', APP + '/doc/' + tok.paid],
  ['see/delivery-1', APP + '/delivery/' + tok.del],
  ['see/delivery-2', APP + '/sign/' + tok.sign, signature],
  ['see/website-1', SHOP + '/', shopList],
  ['see/track-1', SHOP + '/order/' + T.track],
  ['see/track-2', SHOP + '/order/' + T.track_done],
  ['see/book-1', SHOP + '/book/6-wig-install-and-styling'],
  ['see/book-2', APP + '/bookings/view/' + tok.booking],
  ['see/tasks-1', APP + '/tasks', scrollToText('Jobs in progress', 70)],
  ['see/tasks-2', APP + '/tasks?tab=calendar'],
  ['site-ivieluxe', SHOP + '/'],
  // Last: checking out leaves an item in the bag, which shows on every shop page after it.
  ['see/website-2', SHOP + '/', checkout],
];

(async () => {
  const proxy = process.env.HTTPS_PROXY || process.env.https_proxy;
  const browser = await chromium.launch({ proxy: proxy ? { server: proxy, bypass: '<-loopback>,localhost,*.localhost,127.0.0.1' } : undefined });
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true,
    timezoneId: 'Africa/Lagos', reducedMotion: 'reduce', serviceWorkers: 'block' });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', (e) => errs.push(e.message));
  await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({ contentType: 'application/javascript',
    body: 'window.tailwind = window.tailwind || {};' + (twCss === null ? '' : `(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(twCss)};document.head.appendChild(s);})();`) }));
  await page.route(/alpinejs/, (r) => r.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(ROOT + '/assets/vendor/alpine-3.14.3.min.js', 'utf8') }));
  await page.route(/Chart\.js|chart\.umd/i, (r) => r.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(path.join(OUT, 'libs/node_modules/chart.js/dist/chart.umd.js'), 'utf8') }));
  await page.route(/html2pdf/, (r) => r.fulfill({ contentType: 'application/javascript', body: '' }));
  await page.goto(APP + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  if (!page.url().endsWith('/dashboard')) throw new Error('Sign-in failed: is APP_URL http://localhost:8000?');

  for (const [file, url, prep] of SHOTS) {
    if (ONLY.length && !ONLY.includes(file)) continue;
    errs.length = 0; twCss = null;
    await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    if (await page.evaluate(() => !!document.querySelector('script[src*="cdn.tailwindcss.com"]'))) {
      const cfg = await page.evaluate(() => (window.tailwind && window.tailwind.config ? JSON.parse(JSON.stringify(window.tailwind.config)) : null));
      twCss = compileTailwind(path.basename(file), cfg, await page.content());
      errs.length = 0;
      await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    }
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.addStyleTag({ content: '*{animation:none!important;transition:none!important;caret-color:transparent!important} ::-webkit-scrollbar{display:none}' });
    if (prep) await prep(page);
    await page.waitForTimeout(300);
    const png = path.join(OUT, path.basename(file) + '.png');
    await page.screenshot({ path: png });
    const width = file === 'site-ivieluxe' ? 600 : 540;
    execSync(`cwebp -quiet -q ${file === 'site-ivieluxe' ? 80 : 78} -resize ${width} 0 ${png} -o ${ROOT}/assets/img/home/${file}.webp`);
    console.log(file, errs.length ? 'errors: ' + errs.join('; ') : 'ok');
  }
  await browser.close();
})();
