// Buka's turntable, Glow's stories and Shea's skin finder, used like a
// shopper would on a phone, plus no sideways scroll on their pages at three
// widths. Needs the local server and the storefront seed. Prints PASS/FAIL
// lines; screenshots go to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/looks_interact.js <out dir>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || 'shots';
const results = [];
const check = (label, ok, detail = '') => {
  results.push(!!ok);
  console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300)));
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const proxy = process.env.HTTPS_PROXY || process.env.https_proxy;
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP *.nfelia.test 127.0.0.1, MAP nfelia.test 127.0.0.1'],
    proxy: proxy ? { server: proxy, bypass: '*.nfelia.test,nfelia.test,127.0.0.1,localhost' } : undefined,
  });
  const errors = [];
  const open = async (shop, opts = {}) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, ...opts });
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(`${shop}: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(`${shop}: ${m.text()}`); });
    await page.goto(`http://${shop}.nfelia.test:8000/`, { waitUntil: 'networkidle' });
    return page;
  };
  const bag = (page) => page.evaluate(() => document.querySelector('[data-bag-count]').textContent);

  // ── Buka: the turntable ──
  let page = await open('mama-nkechi');
  const front = () => page.evaluate(() => document.querySelector('.sf-turntable__card.is-on .sf-turntable__name').textContent);
  const first = await front();
  const stage = await page.locator('.sf-turntable__stage').boundingBox();
  const y = stage.y + stage.height / 2;
  await page.mouse.move(stage.x + 300, y);
  await page.mouse.down();
  for (let x = 300; x >= 100; x -= 20) { await page.mouse.move(stage.x + x, y); await page.waitForTimeout(16); }
  check('Buka: a drag turns the table with the finger', await page.evaluate(() => document.querySelector('[data-turntable]').classList.contains('is-dragging')));
  await page.mouse.up();
  await page.waitForTimeout(900);
  const second = await front();
  check('Buka: letting go settles on the next dish, its words below', second !== first && await page.evaluate(() => document.querySelector('[data-turntable]').style.getPropertyValue('--turn') === '1'), second);
  check('Buka: the drag did not open the dish', !(await page.evaluate(() => document.getElementById('sf-product').open)));
  await page.click('[data-turn-step="-1"]');
  await page.waitForTimeout(800);
  check('Buka: the arrow turns it back', (await front()) === first);
  await page.click('[data-turn-to="3"]');
  await page.waitForTimeout(800);
  check('Buka: a dot turns to its dish, the short way', await page.evaluate(() => document.querySelectorAll('.sf-turntable__dot')[3].getAttribute('aria-current') === 'true'
    && Math.abs(parseFloat(document.querySelector('[data-turntable]').style.getPropertyValue('--turn'))) <= 3));
  check('Buka: the swipe hint goes once the table has turned', await page.evaluate(() => document.querySelector('[data-turn-hint]').hidden));
  await page.click('.sf-turntable__card.is-on [data-add]');
  await page.waitForTimeout(150);
  check('Buka: Add sends a picture of the dish to the bag', (await page.locator('.sf-fly').count()) === 1);
  await page.screenshot({ path: path.join(OUT, 'buka-fly.png') });
  await page.waitForTimeout(900);
  check('Buka: and the dish is in the bag', (await bag(page)) === '1' && (await page.locator('.sf-fly').count()) === 0);
  await page.locator('.sf-turntable__plate.is-front .sf-turntable__food').click({ force: true });
  await page.waitForTimeout(400);
  check('Buka: a tap on the dish in front opens it', await page.evaluate(() => document.getElementById('sf-product').open));
  await page.context().close();

  // ── Glow: the stories ──
  page = await open('glow-by-temi');
  const at = () => page.evaluate(() => [...document.querySelectorAll('[data-story]')].findIndex((s) => s.classList.contains('is-on')));
  check('Glow: the stories play by themselves', await page.evaluate(() => document.querySelector('[data-stories]').classList.contains('is-running')));
  await page.waitForTimeout(6000);
  check('Glow: the next story comes when its bar fills', (await at()) === 1, await at());
  const box = await page.locator('.sf-stories__stage').boundingBox();
  await page.mouse.click(box.x + box.width * 0.85, box.y + 220);
  await page.waitForTimeout(300);
  check('Glow: a tap on the right goes on', (await at()) === 2, await at());
  await page.mouse.click(box.x + box.width * 0.1, box.y + 220);
  await page.waitForTimeout(300);
  check('Glow: a tap on the left goes back', (await at()) === 1, await at());
  await page.mouse.move(box.x + box.width * 0.6, box.y + 260);
  await page.mouse.down();
  await page.waitForTimeout(600);
  check('Glow: a finger held on the story pauses it', await page.evaluate(() => document.querySelector('[data-stories]').classList.contains('is-paused')));
  await page.mouse.up();
  await page.waitForTimeout(200);
  check('Glow: and letting go stays on it', (await at()) === 1, await at());
  await page.mouse.move(box.x + 200, box.y + 420);
  await page.mouse.down();
  await page.mouse.move(box.x + 200, box.y + 330, { steps: 5 });
  await page.mouse.move(box.x + 200, box.y + 270, { steps: 5 });
  await page.mouse.up();
  await page.waitForTimeout(400);
  check('Glow: a swipe up opens the story\'s details', await page.evaluate(() => document.getElementById('sf-product').open));
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  await page.locator('.sf-ring', { hasText: 'Nails' }).click();
  await page.waitForTimeout(300);
  check('Glow: a ring jumps to its category\'s first story', /Gel polish|Acrylic/.test(await page.evaluate(() => document.querySelector('.sf-story.is-on .sf-story__name').textContent)));
  check('Glow: only the story showing can be reached', await page.evaluate(() => document.querySelectorAll('.sf-story[inert]').length === document.querySelectorAll('.sf-story').length - 1));
  await page.click('[data-stories-pause]');
  check('Glow: the pause button stops them', await page.evaluate(() => document.querySelector('[data-stories]').classList.contains('is-paused')
    && document.querySelector('[data-stories-pause]').getAttribute('aria-pressed') === 'true'));
  await page.click('.sf-story.is-on [data-add]');
  await page.waitForTimeout(1000);
  check('Glow: Add on a story puts it in the bag', (await bag(page)) === '1');
  await page.evaluate(() => window.scrollTo(0, 900));
  await page.waitForTimeout(400);
  check('Glow: the header turns solid once the stories scroll away', await page.evaluate(() => document.querySelector('.sf-header').classList.contains('is-solid')));
  await page.context().close();
  page = await open('glow-by-temi', { reducedMotion: 'reduce' });
  await page.waitForTimeout(6500);
  check('Glow: nothing plays by itself for people who ask for less motion', (await at()) === 0
    && !(await page.evaluate(() => document.querySelector('[data-stories]').classList.contains('is-running'))));
  await page.context().close();

  // ── Shea: the skin finder ──
  page = await open('zainab-naturals');
  const shown = () => page.evaluate(() => [...document.querySelectorAll('[data-routine]')].findIndex((r) => getComputedStyle(r).display !== 'none'));
  check('Shea: no routine before a pick, only its empty places', (await shown()) === -1 && await page.isVisible('[data-routine-empty]'));
  await page.locator('.sf-skin', { hasText: 'Oily' }).click();
  await page.waitForTimeout(700);
  check('Shea: picking a skin type shows its routine', (await shown()) === 1 && !(await page.isVisible('[data-routine-empty]')), await shown());
  const total = () => page.evaluate(() => [...document.querySelectorAll('[data-routine]')].find((r) => getComputedStyle(r).display !== 'none').querySelector('[data-routine-total]').textContent);
  const before = await total();
  await page.locator('[data-routine]:visible [data-swap]').first().click();
  await page.waitForTimeout(400);
  check('Shea: Swap brings another product and the total follows', (await total()) !== before, [before, await total()]);
  await page.screenshot({ path: path.join(OUT, 'shea-routine.png') });
  await page.locator('[data-routine]:visible [data-add-routine]').click();
  await page.waitForTimeout(1300);
  const cart = await page.evaluate(() => JSON.parse(localStorage.getItem('nfelia_cart_zainab-naturals') || '[]'));
  check('Shea: Add the routine puts every step in the bag, each as it comes', (await bag(page)) === '3' && cart.length === 3 && cart.some((l) => l.variant === 'Size: 100ml'), JSON.stringify(cart));
  await page.context().close();

  // ── No sideways scroll ──
  const wide = [];
  for (const shop of ['mama-nkechi', 'glow-by-temi', 'zainab-naturals']) {
    for (const width of [390, 800, 1280]) {
      for (const where of ['/', '/shop', '/contact']) {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        const p = await context.newPage();
        await p.goto(`http://${shop}.nfelia.test:8000${where}`, { waitUntil: 'networkidle' });
        const over = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (over > 0) { wide.push(`${shop}${where} at ${width}: ${over}px`); }
        await context.close();
      }
    }
  }
  check('Buka, Glow and Shea pages never scroll sideways', !wide.length, wide.join(', '));

  check('no script errors', errors.length === 0, errors.join(' | '));
  await browser.close();
  const fails = results.filter((r) => !r).length;
  console.log(`\n${results.length - fails}/${results.length} passed`);
  process.exit(fails ? 1 : 0);
})();
