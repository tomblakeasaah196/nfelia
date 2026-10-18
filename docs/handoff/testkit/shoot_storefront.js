// Full-page screenshots of every look at phone and desktop width, for
// comparing with the concept artboards. Needs the local server and the
// storefront seed (storefront_seed.php):
//   php -S 127.0.0.1:8000 docs/handoff/testkit/router.php
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/shoot_storefront.js <out dir> [look …]
// Shop hosts (name.nfelia.test) are mapped to 127.0.0.1 inside Chromium;
// Google Fonts goes through the proxy when there is one.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || 'shots';
const ONLY = process.argv.slice(3);
const SHOPS = {
  atelier: 'bisi-indigo', buka: 'mama-nkechi', glow: 'glow-by-temi', volt: 'kola-gadgets', market: 'musa-provisions',
  stage: 'lola-events', harvest: 'ayomide-farms', shea: 'zainab-naturals', haven: 'haven-shortlets', desk: 'chinedu-co',
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const proxy = process.env.HTTPS_PROXY || process.env.https_proxy;
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP *.nfelia.test 127.0.0.1, MAP nfelia.test 127.0.0.1'],
    proxy: proxy ? { server: proxy, bypass: '*.nfelia.test,nfelia.test,127.0.0.1,localhost' } : undefined,
  });
  const errors = [];
  for (const [look, shop] of Object.entries(SHOPS)) {
    if (ONLY.length && !ONLY.includes(look)) continue;
    for (const [width, height] of [[390, 844], [1280, 800]]) {
      const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1, ignoreHTTPSErrors: true });
      page.on('console', (m) => { if (m.type() === 'error') errors.push(`${look} ${width}: ${m.text()}`); });
      page.on('pageerror', (e) => errors.push(`${look} ${width}: ${e.message}`));
      await page.goto(`http://${shop}.nfelia.test:8000/`, { waitUntil: 'networkidle', timeout: 45000 }).catch((e) => errors.push(`${look}: ${e.message}`));
      await page.evaluate(() => document.fonts && document.fonts.ready);
      await page.waitForTimeout(300);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      if (overflow > 0) errors.push(`${look} ${width}: page scrolls sideways by ${overflow}px`);
      await page.screenshot({ path: path.join(OUT, `${look}-${width}.png`), fullPage: true });
      await page.close();
    }
  }
  await browser.close();
  console.log(errors.length ? errors.join('\n') : 'No console errors, no sideways scroll.');
})();
