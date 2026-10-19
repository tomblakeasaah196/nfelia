// The home page's Records Book screens: the "Sound familiar?" card's two
// phone screens (Your records, and a code checked at nfelia.com/verify).
// Real app, the demo shop from records_seed.php, phone size (390 × 844 at
// 2x), then WebP into assets/img/home/see/. The workbook itself is shot by
// shoot_workbook.sh.
//
// Needs: .env with APP_URL=http://localhost:8099 (Chromium keeps the app's
// __Host- cookie only on a secure origin, and localhost counts as one), the
// local server, records_seed.php, a book for Ivie (made last month, so this
// month's is still free), and ImageMagick (or cwebp).
//   php -S 127.0.0.1:8099 -t . tests/notifications/router.php
//   php docs/handoff/testkit/records_seed.php
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/shoot_records.js <out>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../../..');
const OUT = process.argv[2] || '/tmp/nf-records-shots';
const APP = 'http://localhost:8099';
const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql}"`).toString().trim();
const code = q("SELECT code FROM records_books WHERE business_id = 50 AND kind = 'book' AND status = 'ready' ORDER BY id DESC LIMIT 1");
if (!code) throw new Error('Ivie has no Records Book: make one first (see the comment at the top).');
// The newest paid invoice in that book (its order is named on the page too)
const invoice = q(`SELECT i.id FROM invoices i JOIN records_books b ON b.code = '${code}' WHERE i.business_id = 50 AND i.status = 'paid' AND i.order_id IS NOT NULL AND i.issue_date <= COALESCE(b.date_to, DATE(b.created_at)) ORDER BY i.issue_date DESC, i.id DESC LIMIT 1`);
// The invoice's own code: the book's, then I and the id in Crockford base32 (records_row_code()).
const b32 = (n) => { const a = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; let s = ''; do { s = a[n % 32] + s; n = Math.floor(n / 32); } while (n > 0); return s; };

const SHOTS = [
  ['see/records-1', APP + '/records'],
  ['see/records-2', APP + '/verify/' + code + '-I' + b32(Number(invoice))],
];

const toWebp = (png, out, width, quality) => {
  try { execSync(`cwebp -quiet -q ${quality} -resize ${width} 0 ${png} -o ${out}`, { stdio: 'pipe' }); }
  catch (e) { execSync(`convert ${png} -resize ${width}x -quality ${quality} -define webp:method=6 ${out}`); }
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const exe = fs.readdirSync('/opt/pw-browsers').filter((d) => d.startsWith('chromium-')).map((d) => `/opt/pw-browsers/${d}/chrome-linux/chrome`).find((p) => fs.existsSync(p));
  const browser = await chromium.launch(exe ? { executablePath: exe } : {});
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true,
    timezoneId: 'Africa/Lagos', reducedMotion: 'reduce', serviceWorkers: 'block' });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', (e) => errs.push(e.message));
  await page.route(/alpinejs/, (r) => r.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(ROOT + '/assets/vendor/alpine-3.14.3.min.js', 'utf8') }));
  await page.goto(APP + '/login');
  await page.fill('#email', 'ivie@ivieluxe.test'); await page.fill('#password', 'DemoPass12');
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
  if (!page.url().endsWith('/dashboard')) throw new Error('Sign-in failed: is APP_URL http://localhost:8099?');

  for (const [file, url] of SHOTS) {
    errs.length = 0;
    await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.addStyleTag({ content: '*{animation:none!important;transition:none!important;caret-color:transparent!important} ::-webkit-scrollbar{display:none}' });
    await page.waitForTimeout(300);
    const png = path.join(OUT, path.basename(file) + '.png');
    await page.screenshot({ path: png });
    toWebp(png, `${ROOT}/assets/img/home/${file}.webp`, 540, 78);
    console.log(file, errs.length ? 'errors: ' + errs.join('; ') : 'ok');
  }
  await browser.close();
})();
