// Step 4 in a browser: the product sheet's Book link, the booking calendar
// (a time slot, then a stay), sending a booking on the site and on WhatsApp,
// and the booking's own page. (The owner's screens are checked over HTTP in
// bookings_test.py: browsers won't keep the app's __Host- cookie on a plain
// http test address.) Needs the local server, then storefront_seed.php and
// bookings_seed.php. Prints PASS/FAIL lines; screenshots go to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/bookings_interact.js <out dir>
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
// Dates in Lagos time, as the server counts them
const lagos = (n) => new Date(Date.now() + 3600e3 + n * 86400e3).toISOString().slice(0, 10);

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
  const GLOW = 'http://glow-by-temi.nfelia.test:8000';
  const HAVEN = 'http://haven-shortlets.nfelia.test:8000';
  const braids = sql("SELECT id FROM products WHERE name = 'Knotless braids, medium, waist length'");
  const onebed = sql("SELECT id FROM products WHERE name = 'One-bedroom apartment'");

  // The sheet sends bookable services to their booking page
  let page = await newPage(GLOW + '/');
  await page.click(`#sf-catalogue [data-item="${braids}"] [data-open] >> nth=-1`);
  await page.waitForSelector('#sf-product[open]');
  check('the sheet offers booking, not the bag', await page.isVisible('#sf-product [data-p-book]') && !(await page.isVisible('#sf-product [data-p-add]')));
  check('to the booking page', (await page.getAttribute('#sf-product [data-p-book]', 'href')) === `/book/${braids}-knotless-braids-medium-waist-length`);
  await page.close();

  // A time slot
  page = await newPage(`${GLOW}/book/${braids}-knotless-braids-medium-waist-length`);
  await page.waitForSelector('[data-cal-grid] [data-day]');
  const target = lagos(2);
  if (target.slice(0, 7) !== lagos(0).slice(0, 7)) { await page.click('[data-cal-next]'); await page.waitForSelector(`[data-day="${target}"]`); }
  check('the calendar shows free days', await page.isEnabled(`[data-day="${target}"]`));
  check('past days can\'t be picked', lagos(-1).slice(0, 7) !== lagos(0).slice(0, 7) || !(await page.isEnabled(`[data-day="${lagos(-1)}"]`)));
  await page.click(`[data-day="${target}"]`);
  await page.waitForSelector('[data-times-list] input[value="10:00"]');
  check('picking a day shows its free times', (await page.$$('[data-times-list] input')).length > 5);
  await page.check('[data-times-list] input[value="10:00"]');
  check('the pick is spelled out', (await page.textContent('[data-book-picked]')).includes('at 10:00am'), await page.textContent('[data-book-picked]'));
  check('total and deposit fill in', (await page.textContent('[data-book-total]')) === '₦45,000' && (await page.textContent('[data-book-deposit]')) === '₦13,500');
  await page.screenshot({ path: path.join(OUT, 'book-slot-390.png'), fullPage: true });
  await page.click('[data-book-submit]');
  check('details are asked for before sending', (await page.textContent('[data-book-error]')).includes('name'));
  await page.fill('[name="customer_name"]', 'Kemi Adewale');
  await page.fill('[name="customer_phone"]', '0805 222 3333');
  await page.click('[data-book-submit]');
  await page.waitForSelector('[data-book-done]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const number = (await page.textContent('[data-book-number]').catch(() => '')).trim();
  check('the booking is sent and numbered', /^BKG-\d{4}$/.test(number), number);
  const track = await page.getAttribute('[data-book-track]', 'href');
  check('with a link to follow it', /^\/booking\/[a-f0-9]{32}$/.test(track || ''), track);
  await page.screenshot({ path: path.join(OUT, 'book-done-390.png'), fullPage: true });
  await page.click('.sf-bagbtn');
  check('the bag lists the booking', await page.isVisible(`[data-recent] a[href="${track}"]`) && (await page.textContent(`[data-recent] a[href="${track}"]`)).includes('Booking ' + number));
  await page.close();

  page = await newPage(GLOW + track);
  await page.screenshot({ path: path.join(OUT, 'booking-page-390.png'), fullPage: true });
  check('the booking page says the deposit is being checked', (await page.textContent('.sf-lead')).includes('checking your deposit'));
  await page.close();

  // Someone else now sees that time taken
  page = await newPage(`${GLOW}/book/${braids}-knotless-braids-medium-waist-length`);
  await page.waitForSelector('[data-cal-grid] [data-day]');
  if (target.slice(0, 7) !== lagos(0).slice(0, 7)) { await page.click('[data-cal-next]'); await page.waitForSelector(`[data-day="${target}"]`); }
  await page.click(`[data-day="${target}"]`);
  await page.waitForSelector('[data-times-list] input');
  check('a booked time is gone for the next person', (await page.$$('[data-times-list] input[value="10:00"]')).length === 0 && (await page.$$('[data-times-list] input[value="13:00"]')).length === 1);
  await page.close();

  // A stay, sent on WhatsApp
  page = await newPage(`${HAVEN}/book/${onebed}-one-bedroom-apartment`);
  await page.waitForSelector('[data-cal-grid] [data-day]');
  const inDay = lagos(5), outDay = lagos(7);
  if (inDay.slice(0, 7) !== lagos(0).slice(0, 7)) { await page.click('[data-cal-next]'); await page.waitForSelector(`[data-day="${inDay}"]`); }
  await page.click(`[data-day="${inDay}"]`);
  check('after the first tap it asks for the day you leave', (await page.textContent('[data-book-picked]')).includes('Now tap the day you leave'));
  if (outDay.slice(0, 7) !== inDay.slice(0, 7)) { await page.click('[data-cal-next]'); await page.waitForSelector(`[data-day="${outDay}"]`); }
  await page.click(`[data-day="${outDay}"]`);
  check('two taps make a stay', (await page.textContent('[data-book-picked]')).includes('2 nights') && (await page.textContent('[data-book-total]')) === '₦170,000' && (await page.textContent('[data-book-deposit]')) === '₦85,000');
  await page.screenshot({ path: path.join(OUT, 'book-stay-390.png'), fullPage: true });
  await page.fill('[name="customer_name"]', 'Uche Obi');
  await page.fill('[name="customer_phone"]', '0806 444 5555');
  await page.click('[data-book-wa]');
  await page.waitForSelector('[data-book-done-wa]:not([hidden])', { timeout: 15000 }).catch(() => {});
  const wa = await page.getAttribute('[data-book-done-wa]', 'href').catch(() => '');
  check('booking on WhatsApp opens a message with the number and link', /^https:\/\/wa\.me\/2349012345678\?text=/.test(wa || '') && /BKG-\d{4}/.test(decodeURIComponent(wa)) && decodeURIComponent(wa).includes('/booking/'), wa);
  await page.close();

  for (const width of [375, 1280]) {
    page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.goto(`${HAVEN}/book/${onebed}-one-bedroom-apartment`, { waitUntil: 'load' });
    await page.waitForSelector('[data-cal-grid] [data-day]');
    check(`${width}px: no sideways scroll on the booking page`, !(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)));
    if (width === 1280) { await page.screenshot({ path: path.join(OUT, 'book-1280.png'), fullPage: true }); }
    await page.close();
  }

  check('no script errors', errors.length === 0, errors.join(' | '));
  await browser.close();
  console.log(`\n${results.filter(Boolean).length}/${results.length} passed`);
  process.exit(results.every(Boolean) ? 0 : 1);
})();
