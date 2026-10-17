// Step 3 in a browser: product sheets with their own address, a product's
// page (buy box, photos, share, the Status image), the tracking link after
// checkout, the bag's recent orders, "order again" and the review form.
// Needs the local server and the storefront seed. Prints PASS/FAIL lines;
// screenshots go to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/selling_interact.js <out dir>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const OUT = process.argv[2] || 'shots';
const results = [];
const check = (label, ok, detail = '') => {
  results.push(!!ok);
  console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300)));
};
const sql = (q) => execSync(`mysql -uroot -N smartqaq_nfelia_core -e "${q}"`).toString().trim();

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const proxy = process.env.HTTPS_PROXY || process.env.https_proxy;
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP *.nfelia.test 127.0.0.1, MAP nfelia.test 127.0.0.1'],
    proxy: proxy ? { server: proxy, bypass: '*.nfelia.test,nfelia.test,127.0.0.1,localhost' } : undefined,
  });
  const errors = [];
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const newPage = async (url) => {
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(`${url}: ${e.message}`));
    await page.goto(url, { waitUntil: 'load' });
    return page;
  };
  const SHOP = 'http://bisi-indigo.nfelia.test:8000';
  const dress = sql("SELECT id FROM products WHERE name = 'Olokun wrap dress'");

  // The sheet takes the product's address, and Back closes it
  let page = await newPage(SHOP + '/');
  await page.click(`#sf-catalogue [data-item="${dress}"] .sf-item__name a`);
  await page.waitForSelector('#sf-product[open]');
  check('the sheet shows the product page address', page.url() === `${SHOP}/p/${dress}-olokun-wrap-dress`, page.url());
  check('and its name in the title', (await page.title()).startsWith('Olokun wrap dress — Bisi Indigo'), await page.title());
  check('the sheet offers Share and a Status image', await page.isVisible('#sf-product [data-share-item]') && await page.isVisible('#sf-product [data-status-item]'));
  await page.goBack();
  await page.waitForSelector('#sf-product', { state: 'hidden' });
  check('Back closes the sheet and the shop address returns', page.url() === SHOP + '/' && (await page.title()).startsWith('Bisi Indigo'), page.url());
  await page.close();

  // A product's page
  page = await newPage(`${SHOP}/p/${dress}-olokun-wrap-dress`);
  await page.screenshot({ path: path.join(OUT, 'pdp-390.png'), fullPage: true });
  const thumbs = await page.$$('[data-thumb]');
  if (thumbs.length > 1) {
    await thumbs[1].click();
    await page.waitForTimeout(600);
    check('a thumbnail shows its photo', (await page.$eval('[data-gallery]', (g) => g.scrollLeft)) > 0);
  }
  const variants = await page.$$('.sf-buy input[type=radio]');
  if (variants.length > 1) { await variants[1].check(); }
  await page.click('[data-buy-step="1"]');
  await page.click('.sf-buy button[type=submit]');
  check('the buy box adds to the bag', (await page.textContent('[data-bag-count]')) === '2', await page.textContent('[data-bag-count]'));
  const line = await page.evaluate(() => JSON.parse(localStorage.getItem('nfelia_cart_bisi-indigo'))[0]);
  check('with the choice picked', variants.length < 2 || /: /.test(line.variant), JSON.stringify(line));
  const waHref = await page.getAttribute('[data-buy-wa]', 'href');
  check('WhatsApp ask follows the choice', variants.length < 2 || decodeURIComponent(waHref).includes(line.variant), waHref);

  await page.click('.sf-pdp [data-share-item]');
  await page.waitForTimeout(300);
  check('Share without a share sheet shows the link', (await page.textContent('[data-toast]')).includes(`/p/${dress}-olokun-wrap-dress`), await page.textContent('[data-toast]'));

  await page.click('.sf-pdp [data-status-item]');
  await page.waitForSelector('#sf-status [data-status-img]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const status = await page.$eval('#sf-status [data-status-img]', (img) => ({ src: img.src, w: img.naturalWidth, h: img.naturalHeight, hidden: img.hidden }));
  check('the Status image is made on the phone', !status.hidden && status.src.startsWith('blob:') && status.w === 1080 && status.h === 1920, JSON.stringify(status));
  check('it can be saved', (await page.getAttribute('#sf-status [data-status-save]', 'download')) === `bisi-indigo-${dress}.jpg`);
  await page.screenshot({ path: path.join(OUT, 'status-dialog.png') });
  const shot = await page.$eval('#sf-status [data-status-img]', async (img) => {
    const r = await fetch(img.src);
    const b = new Uint8Array(await r.arrayBuffer());
    let s = '';
    for (let i = 0; i < b.length; i += 0x8000) { s += String.fromCharCode.apply(null, b.subarray(i, i + 0x8000)); }
    return btoa(s);
  });
  fs.writeFileSync(path.join(OUT, 'status-image.jpg'), Buffer.from(shot, 'base64'));
  await page.click('#sf-status [data-close]');

  // Checkout from the product's page, then the tracking link
  await page.click('[data-bagbar] [data-open-bag]');
  await page.click('#sf-bag [data-go="details"]');
  await page.fill('#sf-bag [name="customer_name"]', 'Ngozi Eze');
  await page.fill('#sf-bag [name="customer_phone"]', '0806 123 9999');
  await page.click('#sf-bag [data-step="details"] button[type=submit]');
  await page.click('#sf-bag [data-step="delivery"] button[type=submit]');
  await page.click('#sf-bag [data-place]');
  await page.waitForSelector('#sf-bag [data-done-track]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const track = await page.getAttribute('#sf-bag [data-done-track]', 'href');
  check('after ordering: a link to follow it', /^\/order\/[a-f0-9]{32}$/.test(track || ''), track);
  await page.screenshot({ path: path.join(OUT, 'done-step.png') });
  await page.click('#sf-bag [data-step="done"] [data-close]');
  await page.click('.sf-bagbtn');
  check('the bag lists the recent order', await page.isVisible('[data-recent] a[href="' + track + '"]'));
  await page.close();

  // The tracking page and "order again"
  page = await newPage(SHOP + track);
  await page.screenshot({ path: path.join(OUT, 'order-390.png'), fullPage: true });
  await page.click('[data-reorder]');
  await page.waitForSelector('#sf-bag[open]');
  check('order again fills the bag', (await page.$$('#sf-bag [data-bag-list] li')).length === 1 && (await page.textContent('[data-bag-count]')) === '2');
  await page.close();

  // The review form, finished order
  const orderId = sql(`SELECT id FROM orders WHERE public_token = '${track.split('/').pop()}'`);
  sql(`UPDATE orders SET status = 'fulfilled' WHERE id = ${orderId}`);
  page = await newPage(SHOP + track);
  const reviewHref = await page.getAttribute('a[href^="/review/"]', 'href');
  check('a finished order asks for a review', /^\/review\/[a-f0-9]{32}$/.test(reviewHref || ''), reviewHref);
  await page.goto(SHOP + reviewHref);
  await page.click('.sf-rate__star >> nth=3');
  check('stars light up to the one tapped', await page.isVisible('.sf-rate__words [data-n="4"]') && !(await page.isVisible('.sf-rate__words [data-n="5"]')));
  const lit = await page.$$eval('.sf-rate__star svg', (svgs) => svgs.map((s) => getComputedStyle(s).fill));
  check('four filled, one empty', new Set(lit.slice(0, 4)).size === 1 && lit[0] !== lit[4], lit.join(' | '));
  await page.screenshot({ path: path.join(OUT, 'review-390.png'), fullPage: true });
  await page.fill('textarea[name="comment"]', 'Beautiful dye, and it fits.');
  await page.click('.sf-reviewform button[type=submit]');
  await page.waitForSelector('h1:has-text("Thank you")');
  check('the review is posted', sql(`SELECT CONCAT(rating, status) FROM reviews WHERE order_id = ${orderId}`) === '4published');
  await page.close();

  // Desktop product page
  page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  page.on('pageerror', (e) => errors.push(`desktop: ${e.message}`));
  await page.goto(`${SHOP}/p/${dress}-olokun-wrap-dress`, { waitUntil: 'load' });
  await page.screenshot({ path: path.join(OUT, 'pdp-1280.png'), fullPage: true });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
  check('desktop product page has no sideways scroll', !overflow);
  await page.close();
  for (const width of [375, 390]) {
    page = await browser.newPage({ viewport: { width, height: 800 } });
    for (const url of [`${SHOP}/p/${dress}-olokun-wrap-dress`, SHOP + track]) {
      await page.goto(url, { waitUntil: 'load' });
      const wide = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
      check(`${width}px: no sideways scroll on ${url.includes('/order/') ? 'the tracking page' : 'the product page'}`, !wide);
    }
    await page.close();
  }

  check('no script errors', errors.length === 0, errors.join(' | '));
  await browser.close();
  console.log(`\n${results.filter(Boolean).length}/${results.length} passed`);
  process.exit(results.every(Boolean) ? 0 : 1);
})();
