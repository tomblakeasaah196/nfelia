const { chromium } = require('playwright');
const S = process.argv[2];
let pass = 0, fail = 0;
const check = (name, ok) => { console.log((ok ? '  PASS  ' : '  FAIL  ') + name); ok ? pass++ : fail++; };
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'no-preference', deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await p.goto('http://127.0.0.1:8099/', { waitUntil: 'networkidle' });

  const price = p.locator('.price-card');
  check('below-the-fold section starts hidden for the reveal', (await price.evaluate((el) => getComputedStyle(el).opacity)) === '0');
  await price.scrollIntoViewIfNeeded(); await p.waitForTimeout(700);
  check('it is revealed once scrolled into view', (await price.evaluate((el) => el.classList.contains('is-in') && getComputedStyle(el).opacity === '1')));
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(2200);
  check('hero thread has finished drawing', (await p.locator('.thread .line').evaluate((el) => getComputedStyle(el).strokeDashoffset)) === '0px');

  const toggle = p.locator('[data-wa-toggle]');
  await toggle.click();
  check('WhatsApp menu opens', await p.locator('#wa-panel').isVisible());
  check('toggle reports expanded', (await toggle.getAttribute('aria-expanded')) === 'true');
  const links = await p.locator('.wa-options a').evaluateAll((as) => as.map((a) => a.href));
  check('four ready-made messages', links.length === 4);
  check('each opens wa.me/2348169789106 with text', links.every((h) => h.startsWith('https://wa.me/2348169789106?text=Hello%20Nfelia')));
  check('focus moved into the menu', await p.evaluate(() => document.activeElement.closest('#wa-panel') !== null));
  await p.screenshot({ path: `${S}/wa_open.png` });
  await p.keyboard.press('Escape');
  check('Escape closes it', !(await p.locator('#wa-panel').isVisible()));
  check('focus returns to the button', await p.evaluate(() => document.activeElement.hasAttribute('data-wa-toggle')));

  await p.locator('button[data-dialog="terms"]').first().click();
  check('Terms dialog opens', await p.locator('#terms').evaluate((d) => d.open));
  await p.locator('#terms [data-close]').click();
  check('Terms dialog closes', !(await p.locator('#terms').evaluate((d) => d.open)));

  check('no console or page errors', errors.length === 0);
  if (errors.length) console.log(errors.join('\n'));

  const rp = await b.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  await rp.goto('http://127.0.0.1:8099/', { waitUntil: 'networkidle' });
  check('reduced motion: nothing hidden', (await rp.locator('.price-card').evaluate((el) => getComputedStyle(el).opacity)) === '1');
  check('reduced motion: thread drawn without animation', (await rp.locator('.thread .line').evaluate((el) => getComputedStyle(el).animationName)) === 'none');
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  await b.close();
})();
