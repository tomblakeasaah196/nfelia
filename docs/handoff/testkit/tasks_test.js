// Functional + visual check of /tasks with the real app (Tailwind compiled locally).
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const KIT = __dirname, S = path.dirname(KIT), BASE = 'http://127.0.0.1:8099', APP = path.join(S, 'apptest');
const lib = (p) => fs.readFileSync(path.join(KIT, 'node_modules', p), 'utf8');
const ALPINE = lib('alpinejs/dist/cdn.min.js'), JQUERY = lib('jquery/dist/jquery.min.js');
const STUB = 'window.tailwind = window.tailwind || {};';
const OUT = process.argv[2] || path.join(S, 'tshots');
const EMAIL = process.argv[3] || 'ivie@demo.nfelia';
const PATHQ = process.argv[4] || '/tasks';
const TAG = process.argv[5] || 'ivie';
let CSS = null;
async function routeLibs(page) {
  await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({ contentType: 'application/javascript',
    body: CSS === null ? STUB : STUB + `(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(CSS)};document.head.appendChild(s);})();` }));
  await page.route(/alpinejs/, (r) => r.fulfill({ contentType: 'application/javascript', body: ALPINE }));
  await page.route(/jquery/, (r) => r.fulfill({ contentType: 'application/javascript', body: JQUERY }));
  await page.route(/fonts\.(googleapis|gstatic)/, (r) => r.fulfill({ status: 200, body: '' }));
}
function compile(config, html) {
  const dir = path.join(KIT, 'build', 'tasks'); fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'page.html'), html);
  fs.writeFileSync(path.join(dir, 'in.css'), '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n');
  fs.writeFileSync(path.join(dir, 'tailwind.config.js'), 'module.exports = ' + JSON.stringify({ content: [path.join(dir, 'page.html'), path.join(APP, 'views/**/*.php'), path.join(APP, 'assets/js/planner.js')], theme: (config && config.theme) || {} }) + ';');
  execFileSync('node', [path.join(KIT, 'node_modules/tailwindcss/lib/cli.js'), '-c', path.join(dir, 'tailwind.config.js'), '-i', path.join(dir, 'in.css'), '-o', path.join(dir, 'out.css'), '--minify'], { stdio: 'pipe' });
  return fs.readFileSync(path.join(dir, 'out.css'), 'utf8');
}
module.exports = { routeLibs, compile, setCss: (c) => { CSS = c; }, BASE, OUT };
if (require.main === module) (async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const errors = [];
  for (const [name, vp, mobile] of [['m', { width: 390, height: 844 }, true], ['d', { width: 1280, height: 900 }, false]]) {
    const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: mobile ? 2 : 1, isMobile: mobile, hasTouch: mobile, timezoneId: 'Africa/Lagos' });
    const page = await ctx.newPage();
    page.on('console', (m) => { if (m.type() === 'error') errors.push(name + ' console: ' + m.text()); });
    page.on('pageerror', (e) => errors.push(name + ' pageerror: ' + e.message));
    await routeLibs(page);
    await page.goto(BASE + '/login');
    await page.fill('#email', EMAIL); await page.fill('#password', 'DemoPass1');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    if (CSS === null) {
      await page.goto(BASE + PATHQ, { waitUntil: 'networkidle' });
      const cfg = await page.evaluate(() => window.tailwind && window.tailwind.config ? JSON.parse(JSON.stringify(window.tailwind.config)) : null);
      CSS = compile(cfg, await page.content());
    }
    await page.goto(BASE + PATHQ, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '*{animation:none!important;transition:none!important} ::-webkit-scrollbar{display:none}' });
    await page.waitForTimeout(700);
    await page.screenshot({ path: path.join(OUT, `${TAG}_${name}.png`), fullPage: true });
    const ov = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    console.log(name, 'overflow', ov, 'height', await page.evaluate(() => document.body.scrollHeight));
    await ctx.close();
  }
  console.log(errors.length ? errors.join('\n') : 'no console errors');
  await browser.close();
})();
