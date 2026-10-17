// Clicks through a shop like a shopper: product sheet, bag, the three
// checkout steps (proof of payment and WhatsApp), Market's steppers, the
// filter and search, and the quote form. Needs the local server and the
// storefront seed. Prints PASS/FAIL lines; screenshots go to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/storefront_interact.js <out dir>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || 'shots';
const results = [];
const check = (label, ok, detail = '') => {
  results.push(ok);
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
  const open = async (shop, width = 390) => {
    const page = await browser.newPage({ viewport: { width, height: 844 } });
    page.on('pageerror', (e) => errors.push(`${shop}: ${e.message}`));
    await page.goto(`http://${shop}.nfelia.test:8000/`, { waitUntil: 'domcontentloaded' });
    return page;
  };

  // Product sheet, bag and checkout with proof of payment
  let page = await open('bisi-indigo');
  await page.click('#sf-catalogue [data-item] .sf-item__name a >> nth=0');
  await page.waitForSelector('#sf-product[open]');
  check('product sheet opens from a name', await page.isVisible('#sf-product [data-p-add]'));
  check('opening a product puts it in the address', /#item-\d+$/.test(page.url()), page.url());
  await page.screenshot({ path: path.join(OUT, 'flow-sheet.png') });
  await page.click('#sf-product [data-p-step="1"]');
  await page.click('#sf-product [data-p-add]');
  await page.waitForSelector('#sf-product', { state: 'hidden' });
  check('closing the sheet takes the item out of the address', !/#item-/.test(page.url()), page.url());
  check('bag badge shows 2', (await page.textContent('[data-bag-count]')) === '2');
  check('bag bar appears', await page.isVisible('[data-bagbar]'));
  const stored = await page.evaluate(() => JSON.parse(localStorage.getItem('nfelia_cart_bisi-indigo') || '[]'));
  check('bag is stored under the old key and shape', stored.length === 1 && stored[0].qty === 2 && 'key' in stored[0] && 'variant' in stored[0], JSON.stringify(stored));

  await page.click('[data-bagbar] [data-open-bag]');
  await page.waitForSelector('#sf-bag[open]');
  await page.screenshot({ path: path.join(OUT, 'flow-bag.png') });
  await page.click('#sf-bag [data-go="details"]');
  await page.click('#sf-bag [data-step="details"] button[type=submit]');
  check('details step asks for a name', (await page.textContent('#sf-bag [data-step="details"] [data-step-error]')).includes('name'));
  await page.fill('#sf-bag [name="customer_name"]', 'Ada Shopper');
  await page.fill('#sf-bag [name="customer_phone"]', '0805 555 6666');
  await page.click('#sf-bag [data-step="details"] button[type=submit]');
  await page.check('#sf-bag input[name="fulfillment_type"][value="delivery"]');
  await page.click('#sf-bag [data-step="delivery"] button[type=submit]');
  check('delivery needs an address', (await page.textContent('#sf-bag [data-step="delivery"] [data-step-error]')).includes('address'));
  await page.fill('#sf-bag [name="delivery_address"]', '4 Allen Avenue, Ikeja');
  await page.click('#sf-bag [data-step="delivery"] button[type=submit]');
  await page.screenshot({ path: path.join(OUT, 'flow-pay.png') });
  await page.setInputFiles('#sf-bag input[name="payment_proof"]', {
    name: 'proof.png', mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64'),
  });
  await page.click('#sf-bag [data-place]');
  await page.waitForSelector('#sf-bag [data-step="done"]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const number = await page.textContent('#sf-bag [data-done-number]').catch(() => '');
  check('order is placed and numbered', /^ORD-\d{4}$/.test(number.trim()), number);
  check('bag is empty after the order', (await page.evaluate(() => localStorage.getItem('nfelia_cart_bisi-indigo'))) === '[]');
  await page.screenshot({ path: path.join(OUT, 'flow-done.png') });
  await page.close();

  // WhatsApp order
  page = await open('mama-nkechi');
  await page.click('[data-add] >> nth=0');
  await page.click('.sf-bagbtn');
  await page.click('#sf-bag [data-go="details"]');
  await page.fill('#sf-bag [name="customer_name"]', 'Bayo');
  await page.fill('#sf-bag [name="customer_phone"]', '08030001111');
  await page.click('#sf-bag [data-step="details"] button[type=submit]');
  await page.click('#sf-bag [data-step="delivery"] button[type=submit]');
  await page.click('#sf-bag [data-place-wa]');
  await page.waitForSelector('#sf-bag [data-done-wa]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const wa = await page.getAttribute('#sf-bag [data-done-wa]', 'href').catch(() => '');
  check('WhatsApp order opens a message with the order number', /^https:\/\/wa\.me\/2348095551212\?text=.*ORD-\d{4}/.test(decodeURIComponent(wa || '')), wa);
  await page.close();

  // Market: steppers
  page = await open('musa-provisions');
  const row = '#sf-catalogue [data-item] >> nth=0';
  await page.click(`${row} >> [data-add]`);
  check('Add to order turns into a stepper', await page.isVisible(`${row} >> [data-stepper]`));
  await page.click(`${row} >> [data-step="1"]`);
  check('stepper counts up', (await page.textContent(`${row} >> [data-stepper] output`)) === '2');
  await page.click(`${row} >> [data-step="-1"]`);
  await page.click(`${row} >> [data-step="-1"]`);
  check('stepper back to zero shows Add again', await page.isVisible(`${row} >> [data-add]`));
  await page.fill('[data-search]', 'oloyin');
  const visible = await page.$$eval('#sf-catalogue [data-item]:not([hidden])', (els) => els.length);
  check('search finds a product by a word in its name', visible === 1, visible);
  await page.fill('[data-search]', 'zzzz');
  check('search with no match says so', await page.isVisible('[data-no-match]'));
  await page.close();

  // Filter
  page = await open('kola-gadgets');
  await page.click('#sf-catalogue [data-filter="phones"]');
  const cats = await page.$$eval('#sf-catalogue [data-item]:not([hidden])', (els) => els.map((e) => e.dataset.cat));
  check('category filter shows only that category', cats.length === 2 && cats.every((c) => c === 'phones'), cats);
  await page.close();

  // Quote form
  page = await open('chinedu-co');
  await page.click('[data-estimate-quote]');
  await page.waitForTimeout(500);
  check('estimate prefills the quote request', (await page.inputValue('[data-quote-form] textarea')).includes('AC servicing'));
  await page.fill('[data-quote-form] [name="name"]', 'Ngozi');
  await page.fill('[data-quote-form] [name="phone"]', '08031112222');
  await page.click('[data-quote-form] button[type=submit]');
  await page.waitForFunction(() => /Sent\./.test(document.querySelector('[data-form-status]').textContent), null, { timeout: 15000 }).catch(() => {});
  check('quote request is sent', (await page.textContent('[data-form-status]')).startsWith('Sent.'));
  await page.close();

  // Deep link straight to a product
  page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await page.goto('http://zainab-naturals.nfelia.test:8000/#item-' + (await (async () => {
    const p = await browser.newPage();
    await p.goto('http://zainab-naturals.nfelia.test:8000/');
    const id = await p.getAttribute('[data-item] >> nth=0', 'data-item');
    await p.close();
    return id;
  })()));
  check('a link to #item-N opens that product', await page.isVisible('#sf-product[open]'));
  await page.close();

  await browser.close();
  if (errors.length) { check('no script errors', false, errors.join(' | ')); }
  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} passed`);
  process.exit(failed ? 1 : 0);
})();
