// Buka's turntable, Glow's stories, Shea's skin finder, Market's price
// board and Volt's spotlight, used like a shopper would on a phone, plus no
// sideways scroll on their pages at three widths. Needs the local server and
// the storefront seed; Market's prices for buying more and its thresholds
// are set here and put back after. Prints PASS/FAIL lines; screenshots go
// to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/looks_interact.js <out dir>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../../..');
const db = (query) => execSync('mysql -uroot -N --raw smartqaq_nfelia_core', { input: query }).toString().trim();
const quote = (text) => "'" + text.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
// A shop's published settings, as an owner would publish them; cached pages
// would hide the change
const publish = (id, json) => {
  db(`UPDATE businesses SET storefront_published = ${json === 'NULL' ? 'NULL' : quote(json)} WHERE id = ${id}`);
  const cache = path.join(ROOT, 'storage/cache');
  for (const f of fs.existsSync(cache) ? fs.readdirSync(cache) : []) { try { fs.unlinkSync(path.join(cache, f)); } catch (e) { /* a folder */ } }
};

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
  await page.click('[data-bagbar] [data-open-bag]');
  await page.waitForTimeout(400);
  const laid = await page.evaluate(() => {
    const step = document.querySelector('#sf-bag [data-step="bag"]');
    const name = document.querySelector('#sf-bag .sf-bagline__name');
    return { display: getComputedStyle(step).display, direction: getComputedStyle(step).flexDirection, name: name ? name.getBoundingClientRect().width : 0 };
  });
  check('Shea: the bag keeps its own layout (lines readable, steps a column)', laid.display === 'flex' && laid.direction === 'column' && laid.name > 120, JSON.stringify(laid));
  await page.context().close();

  // ── Market: the calculator, the order book, the list ──
  const marketWas = db('SELECT storefront_published FROM businesses WHERE id = 6');
  const noodles = Number(db("SELECT id FROM products WHERE business_id = 6 AND name = 'Instant noodles, chicken 70g'"));
  // The seed leaves it on the settings from before Shop Studio
  const market = marketWas === 'NULL' ? { v: 2, look: 'market', colours: { set: 'brand' }, sections: [{ type: 'hero' }] } : JSON.parse(marketWas);
  Object.assign(market.sections.find((x) => x.type === 'hero'), { min_order: 50000, free_from: 200000,
    breaks: [{ id: noodles, from: 5, price: 9300 }, { id: noodles, from: 20, price: 9000 }] });
  publish(6, JSON.stringify(market));
  page = await open('musa-provisions');
  const calcState = () => page.evaluate(() => ({
    open: document.getElementById('sf-calc').open, qty: document.querySelector('[data-calc-input]').value,
    sum: document.querySelector('[data-calc-sum]').textContent, next: (document.querySelector('.sf-ladder__next') || {}).textContent,
    on: (document.querySelector('.sf-ladder__step.is-on .sf-ladder__from') || {}).textContent, go: document.querySelector('[data-calc-add]').textContent }));
  await page.click(`[data-item="${noodles}"] [data-calc-open]`);
  await page.waitForTimeout(300);
  let calc = await calcState();
  check('Market: Add opens the calculator at one, with the steps and how far the next is', calc.open && calc.qty === '1' && calc.sum === '1 × ₦9,600 = ₦9,600'
    && calc.next === '4 more for ₦9,300 each' && calc.on === '1+', JSON.stringify(calc));
  await page.click('#sf-calc [data-key="2"]');
  await page.click('#sf-calc [data-key="5"]');
  calc = await calcState();
  check('Market: the keys type over it, and the sum takes the step reached', calc.qty === '25' && calc.sum === '25 × ₦9,000 = ₦225,000'
    && calc.on === '20+' && calc.go === 'Add 25 to the order', JSON.stringify(calc));
  await page.screenshot({ path: path.join(OUT, 'market-calc.png') });
  await page.click('#sf-calc [data-calc-add]');
  await page.waitForTimeout(150);
  check('Market: Add flies it to the order', (await page.locator('.sf-fly').count()) === 1);
  await page.waitForTimeout(900);
  const row = page.locator(`[data-item="${noodles}"]`);
  check('Market: the row turns into a stepper with how many', (await bag(page)) === '25' && await row.locator('[data-stepper]').isVisible()
    && (await row.locator('[data-stepper] output').textContent()) === '25' && !(await row.locator(':scope [data-calc-open]').first().isVisible()));
  const goal = () => page.evaluate(() => document.querySelector('[data-bagbar] [data-goal-text]').textContent);
  check('Market: the order says it gets free delivery', (await goal()) === 'This order gets free delivery.', await goal());
  await row.locator('[data-stepper] [data-calc-open]').click();
  await page.waitForTimeout(300);
  await page.click('#sf-calc [data-key="3"]');
  calc = await calcState();
  check('Market: the number on the stepper opens the calculator to change it', calc.qty === '3' && calc.go === 'Change to 3' && calc.next === '2 more for ₦9,300 each', JSON.stringify(calc));
  await page.click('#sf-calc [data-calc-add]');
  await page.waitForTimeout(400);
  check('Market: under the smallest order, the order says how much more', (await goal()) === 'Add ₦21,200 more: the smallest order is ₦50,000.', await goal());
  await page.click('[data-bagbar] [data-open-bag]');
  await page.waitForTimeout(500);
  const book = await page.evaluate(() => ({ send: document.querySelector('[data-go="details"]').disabled, text: document.querySelector('[data-go="details"]').textContent.trim(),
    lines: document.querySelectorAll('#sf-bag .sf-bagline').length, head: !!document.querySelector('#sf-bag .sf-book__head'), tear: !!document.querySelector('#sf-bag .sf-book__tear') }));
  check('Market: the order book holds the order, and waits for the smallest', book.send && book.text === 'Send this order' && book.lines === 1 && book.head && book.tear, JSON.stringify(book));
  await page.screenshot({ path: path.join(OUT, 'market-book.png') });
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  await page.click('[data-board-tab="list"]');
  await page.fill('[data-mylist-text]', '2 bags rice\nspag x10\nzobo leaves');
  await page.waitForTimeout(600);
  const read = await page.evaluate(() => [...document.querySelectorAll('[data-mylist-lines] li')].map((li) => li.classList.contains('is-found')
    ? li.querySelector('select').selectedOptions[0].textContent + ' = ' + li.querySelector('.sf-mylist__price').textContent : '? ' + li.querySelector('.sf-mylist__name').textContent));
  check('Market: a list typed the WhatsApp way is read and priced line by line', JSON.stringify(read) === JSON.stringify(['2 × Rice, long grain = ₦156,000', '10 × Spaghetti 500g = ₦215,000', '? zobo leaves']), JSON.stringify(read));
  await page.click('[data-mylist-add]');
  await page.waitForTimeout(700);
  const listed = await page.evaluate(() => ({ open: document.getElementById('sf-bag').open, note: document.querySelector('#sf-bag [name="customer_note"]').value,
    cart: JSON.parse(localStorage.getItem('nfelia_cart_musa-provisions') || '[]').map((l) => l.name + ' ' + l.qty) }));
  check('Market: the list joins the order, and what wasn\'t found goes in the note', listed.open && listed.note === 'Also on my list: zobo leaves'
    && listed.cart.includes('Rice, long grain 2') && listed.cart.includes('Spaghetti 500g 10'), JSON.stringify(listed));
  await page.context().close();
  // A shopper back a day later sees what moved
  const seen = {};
  for (const [id, price] of db('SELECT id, sell_price FROM products WHERE business_id = 6').split('\n').map((l) => l.split('\t'))) { seen[id] = Number(price); }
  seen[noodles] = 9100;
  page = await open('musa-provisions');
  await page.evaluate((was) => localStorage.setItem('nfelia_prices_musa-provisions', JSON.stringify({ t: Date.now() - 26 * 3600 * 1000, p: was })), seen);
  await page.reload({ waitUntil: 'networkidle' });
  const moved = await page.evaluate(() => [...document.querySelectorAll('.sf-move:not([hidden])')].map((m) => m.textContent));
  check('Market: since the last visit, the board marks the price that moved', JSON.stringify(moved) === JSON.stringify(['▲ ₦500 more than when you last looked']), JSON.stringify(moved));
  await page.context().close();
  publish(6, marketWas);

  // ── Volt: the spotlight ──
  page = await open('kola-gadgets');
  const lit = () => page.evaluate(() => ({ device: [...document.querySelectorAll('[data-spot]')].findIndex((d) => d.classList.contains('is-on')),
    card: [...document.querySelectorAll('[data-spot-card]')].findIndex((c) => c.classList.contains('is-on')),
    dot: [...document.querySelectorAll('[data-spot-to]')].findIndex((d) => d.getAttribute('aria-current') === 'true') }));
  await page.click('[data-spot-step="1"]');
  await page.waitForTimeout(700);
  let now = await lit();
  check('Volt: the arrow brings the next device, its card and its dot', now.device === 1 && now.card === 1 && now.dot === 1, JSON.stringify(now));
  const spot = await page.locator('.sf-spot__stage').boundingBox();
  await page.mouse.move(spot.x + spot.width * 0.8, spot.y + spot.height / 2);
  await page.mouse.down();
  for (let x = 0.8; x >= 0.2; x -= 0.1) { await page.mouse.move(spot.x + spot.width * x, spot.y + spot.height / 2); await page.waitForTimeout(16); }
  await page.mouse.up();
  await page.waitForTimeout(700);
  now = await lit();
  check('Volt: a swipe brings the one after', now.device === 2 && now.card === 2, JSON.stringify(now));
  await page.click('[data-spot-to="0"]');
  await page.waitForTimeout(1500);
  const callouts = await page.evaluate(() => [...document.querySelectorAll('[data-spot].is-on .sf-callout')].map((c) => getComputedStyle(c).opacity));
  check('Volt: a dot goes back, and the callouts are drawn in round it', (await lit()).device === 0 && callouts.length > 0 && callouts.every((o) => o === '1'), JSON.stringify(callouts));
  await page.screenshot({ path: path.join(OUT, 'volt-spotlight.png') });
  // One with no choices to make (a phone's open its sheet first)
  const plain = await page.evaluate(() => [...document.querySelectorAll('[data-spot-card]')].findIndex((c) => /Fast charger|Power bank/.test(c.textContent)));
  await page.click(`[data-spot-to="${plain}"]`);
  await page.waitForTimeout(700);
  await page.click('.sf-spot__card.is-on [data-add]');
  await page.waitForTimeout(150);
  check('Volt: Add flies the device from its card to the bag', (await page.locator('.sf-fly').count()) === 1);
  await page.waitForTimeout(900);
  check('Volt: and it is in the bag', (await bag(page)) === '1');
  await page.context().close();

  // ── No sideways scroll ──
  const wide = [];
  for (const shop of ['mama-nkechi', 'glow-by-temi', 'zainab-naturals', 'musa-provisions', 'kola-gadgets']) {
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
  check('Buka, Glow, Shea, Market and Volt pages never scroll sideways', !wide.length, wide.join(', '));

  check('no script errors', errors.length === 0, errors.join(' | '));
  await browser.close();
  const fails = results.filter((r) => !r).length;
  console.log(`\n${results.length - fails}/${results.length} passed`);
  process.exit(fails ? 1 : 0);
})();
