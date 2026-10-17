// The landing page's Tasks & Calendar phone shot: Ivie's Tasks tab at
// 390×844 @2x (780×1688, like the other phone shots), scrolled so the job's
// countdown, Late and Today with its delivery fill the screen.
//   mysql smartqaq_nfelia_core < planner_seed.sql.txt
//   node shoot_tasks_phone.js OUT.png
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const T = require('./tasks_test.js');

(async () => {
  const out = process.argv[2];
  // Demo data only: one late payment instead of two, so the shot is mostly deliveries.
  execSync(`mysql smartqaq_nfelia_core -e "UPDATE invoices SET due_date = DATE_ADD(CURDATE(), INTERVAL 5 DAY), status = 'partially_paid' WHERE id = 5"`);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos', reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await T.routeLibs(page);
  await page.goto(T.BASE + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(T.BASE + '/tasks', { waitUntil: 'networkidle' });
  const cfg = await page.evaluate(() => (window.tailwind && window.tailwind.config ? JSON.parse(JSON.stringify(window.tailwind.config)) : null));
  T.setCss(T.compile(cfg, await page.content()));
  await page.goto(T.BASE + '/tasks', { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '*{animation:none!important;transition:none!important} ::-webkit-scrollbar{display:none}' });
  await page.waitForTimeout(600);
  // "Jobs in progress" just under the fixed top bar.
  await page.evaluate(() => { const h = document.getElementById('pl-jobs'); window.scrollTo(0, h.getBoundingClientRect().top + window.scrollY - 64 - 12); });
  await page.waitForTimeout(300);
  await page.screenshot({ path: out });
  await browser.close();
})();
