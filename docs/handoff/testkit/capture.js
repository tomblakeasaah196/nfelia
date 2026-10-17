// Screenshots of the real app (local copy, demo data) for the landing page.
// CDNs are unreachable here, so each page is captured in two passes:
//   1. load with a Tailwind stub, read the page's own tailwind.config + HTML
//   2. compile Tailwind v3 for exactly that, reload with the CSS injected.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const KIT = __dirname;
const S = path.dirname(KIT);
const BASE = 'http://127.0.0.1:8099';
const OUT = process.argv[2];
const APP = path.join(S, 'apptest');
const lib = (p) => fs.readFileSync(path.join(KIT, 'node_modules', p), 'utf8');
const ALPINE = lib('alpinejs/dist/cdn.min.js');
const CHART = lib('chart.js/dist/chart.umd.js');
const JQUERY = lib('jquery/dist/jquery.min.js');

const STUB = 'window.tailwind = window.tailwind || {};';

async function routeLibs(page, css) {
  await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({
    contentType: 'application/javascript',
    body: css === null ? STUB
      : STUB + `(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(css)};document.head.appendChild(s);})();`,
  }));
  await page.route(/alpinejs/, (r) => r.fulfill({ contentType: 'application/javascript', body: ALPINE }));
  await page.route(/Chart\.js|chart\.umd/i, (r) => r.fulfill({ contentType: 'application/javascript', body: CHART }));
  await page.route(/jquery/, (r) => r.fulfill({ contentType: 'application/javascript', body: JQUERY }));
  await page.route(/html2pdf/, (r) => r.fulfill({ contentType: 'application/javascript', body: '' }));
}

function compile(name, config, html) {
  const dir = path.join(KIT, 'build', name);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'page.html'), html);
  fs.writeFileSync(path.join(dir, 'in.css'), '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n');
  const cfg = {
    content: [path.join(dir, 'page.html'), path.join(APP, 'views/**/*.php')],
    theme: (config && config.theme) || {},
  };
  fs.writeFileSync(path.join(dir, 'tailwind.config.js'), 'module.exports = ' + JSON.stringify(cfg, null, 1) + ';');
  execFileSync('node', [path.join(KIT, 'node_modules/tailwindcss/lib/cli.js'), '-c', path.join(dir, 'tailwind.config.js'),
    '-i', path.join(dir, 'in.css'), '-o', path.join(dir, 'out.css'), '--minify'], { stdio: 'pipe' });
  return fs.readFileSync(path.join(dir, 'out.css'), 'utf8');
}

async function login(context, email) {
  const page = await context.newPage();
  await routeLibs(page, null);
  await page.goto(BASE + '/login');
  await page.fill('#email', email);
  await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.close();
}

async function shoot(context, name, url, opts = {}) {
  // Pass 1: discover config + markup.
  const p1 = await context.newPage();
  await routeLibs(p1, null);
  await p1.goto(BASE + url, { waitUntil: 'networkidle' });
  const config = await p1.evaluate(() => (window.tailwind && window.tailwind.config) ? JSON.parse(JSON.stringify(window.tailwind.config)) : null);
  const html = await p1.content();
  await p1.close();
  const css = compile(name, config, html);

  // Pass 2: the real look.
  const page = await context.newPage();
  await routeLibs(page, css);
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '*{animation:none!important;transition:none!important} ::-webkit-scrollbar{display:none}' });
  if (opts.prepare) await opts.prepare(page);
  await page.waitForTimeout(600);
  const file = path.join(OUT, name + '.png');
  await page.screenshot({ path: file, fullPage: false });
  console.log('shot', name, url, 'css', Math.round(css.length / 1024) + 'KB');
  await page.close();
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const only = process.argv[3] || '';

  const desk = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await login(desk, 'ivie@demo.nfelia');
  await login(phone, 'ivie@demo.nfelia');

  const kemiToken = fs.readFileSync(path.join(S, 'kemi_token.txt'), 'utf8').trim();
  const jobs = [
    ['dashboard-laptop', desk, '/dashboard'],
    ['profit-laptop', desk, '/reports/profit-loss'],
    ['debts-phone', phone, '/debts', { prepare: async (page) => {
      await page.evaluate(() => {
        const tab = [...document.querySelectorAll('a, button')].find((e) => /RECEIVABLE/i.test(e.textContent));
        if (tab) window.scrollTo(0, tab.getBoundingClientRect().top + window.scrollY - 24);
      });
    } }],
    ['debt-view-phone', phone, '/debts/view/3'],
    ['debts-summary-phone', phone, '/debts'],
    ['shop-phone', phone, '/biz/ivie-luxe-hair', { prepare: async (page) => {
      await page.evaluate(() => {
        const h = [...document.querySelectorAll('h2, h3')].find((e) => /offering|product|shop|catalog/i.test(e.textContent));
        if (h) window.scrollTo(0, h.getBoundingClientRect().top + window.scrollY - 90);
      });
    } }],
    ['invoice-phone', phone, '/doc/' + kemiToken],
  ];
  for (const [name, ctx, url, opts] of jobs) {
    if (only && !name.includes(only)) continue;
    await shoot(ctx, name, url, opts);
  }
  await browser.close();
})();
