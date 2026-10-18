// Approving a website order takes its goods off stock, like sending an
// invoice does, and cancelling that invoice (after refunding a paid one)
// puts them back once. Services
// have no stock and are left alone. Reseeds the local database first
// (seed.php, planner_seed.sql.txt, landing_seed.php), so run it only there.
// Needs the local server with APP_URL=http://localhost:8000.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/order_stock_test.js <out dir>
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = process.argv[2] || 'shots';
const KIT = __dirname;
const APP = 'http://localhost:8000';
const results = [];
const check = (label, ok, detail = '') => { results.push(!!ok); console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300))); };
const sql = (q) => execSync(`mysql -N smartqaq_nfelia_core -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const stock = (id) => Number(sql(`SELECT stock_qty FROM products WHERE id = ${id}`));

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  execSync(`php ${KIT}/seed.php ${path.join(OUT, 'app')}`);
  execSync(`mysql smartqaq_nfelia_core < ${KIT}/planner_seed.sql.txt`);
  execSync(`php ${KIT}/landing_seed.php ${OUT}`);
  // A second website order, not paid yet: two bundles and a service.
  sql(`INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
       payment_method, currency_code, subtotal, total, status, created_at) VALUES (104, 1, NULL, 'IVL-ORD-021', '${'a'.repeat(32)}', 'cart_pop', 'pickup',
       'Funmi Bakare', '0708 555 0177', 'bank_transfer', 'NGN', 155000, 155000, 'pending', NOW())`);
  sql(`INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES
       (104, 2, 'Body Wave 18 bundle', 65000, 2, 130000), (104, 6, 'Wig install and styling', 25000, 1, 25000)`);

  const wig = stock(3), bundle = stock(2), service = stock(6);
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  page.on('dialog', (d) => d.accept());
  await page.goto(APP + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

  const approve = async (orderId) => {
    await page.goto(`${APP}/orders/view/${orderId}`);
    await page.locator('input[name=deliver_mode][value=immediate]').check({ force: true });
    await Promise.all([page.waitForNavigation(), page.locator('button:has-text("Approve Order")').click()]);
    return Number(sql(`SELECT IFNULL(invoice_id, 0) FROM orders WHERE id = ${orderId}`));
  };

  // Paid order (transfer slip attached): one frontal wig.
  const inv103 = await approve(103);
  check('approving the paid order makes its invoice', inv103 > 0, inv103);
  check('approving it takes the wig off stock', stock(3) === wig - 1, `${wig} -> ${stock(3)}`);
  const moves103 = sql(`SELECT CONCAT(type, ' ', quantity, ' ', stock_before, '->', stock_after) FROM stock_movements WHERE reference_type = 'invoice' AND reference_id = ${inv103}`);
  check('the stock history records the sale', moves103 === `sale 1.00 ${wig.toFixed(2)}->${(wig - 1).toFixed(2)}`, moves103);

  // Unpaid order: two bundles and a service.
  const inv104 = await approve(104);
  check('approving the unpaid order makes its invoice', inv104 > 0, inv104);
  check('both bundles come off stock', stock(2) === bundle - 2, `${bundle} -> ${stock(2)}`);
  check('the service is left alone', stock(6) === service, `${service} -> ${stock(6)}`);
  check('only goods get a stock history line', sql(`SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'invoice' AND reference_id = ${inv104}`) === '1');

  // The order was paid, so its invoice can't be cancelled until the money
  // is refunded (Tom's decision, audit M14).
  const cancel = async (id) => {
    await page.goto(`${APP}/invoices/view/${id}`);
    // The button sits in the invoice's "More" sheet; submit its form as the button would.
    await Promise.all([page.waitForNavigation(), page.evaluate((id) => document.querySelector(`form[action$="/invoices/cancel/${id}"]`).submit(), id)]);
  };
  await cancel(inv103);
  check('a paid invoice is not cancelled before a refund', sql(`SELECT status FROM invoices WHERE id = ${inv103}`) === 'paid' && stock(3) === wig - 1,
        `${sql(`SELECT status FROM invoices WHERE id = ${inv103}`)} stock ${stock(3)}`);
  const paid = sql(`SELECT amount_paid FROM invoices WHERE id = ${inv103}`);
  await page.evaluate(async ([url, amount]) => {
    const token = document.querySelector('input[name=csrf_token]').value;
    await fetch(url, { method: 'POST', body: new URLSearchParams({ csrf_token: token, amount, refund_date: new Date().toISOString().slice(0, 10), method: 'bank_transfer', note: 'Out of stock' }) });
  }, [`${APP}/invoices/record_refund/${inv103}`, paid]);
  check('the refund takes the money off the invoice', Number(sql(`SELECT amount_paid FROM invoices WHERE id = ${inv103}`)) === 0);

  // Cancelling the invoice puts the wig back, once, and cancels its order.
  await cancel(inv103);
  check('cancelling the invoice puts the wig back once', stock(3) === wig, `${wig} -> ${stock(3)}`);
  check('the invoice is cancelled', sql(`SELECT status FROM invoices WHERE id = ${inv103}`) === 'cancelled');
  check('its order is cancelled too', sql(`SELECT status FROM orders WHERE id = 103`) === 'cancelled');

  // Approving twice is refused, so nothing comes off twice.
  const before = stock(2);
  await page.goto(`${APP}/orders/view/104`);
  const again = await page.evaluate(async (url) => {
    const token = document.querySelector('input[name=csrf_token]').value;
    const r = await fetch(url, { method: 'POST', body: new URLSearchParams({ csrf_token: token, deliver_mode: 'immediate' }) });
    return r.status;
  }, `${APP}/orders/approve/104`);
  check('a second approval of the same order takes nothing more', stock(2) === before, `${before} -> ${stock(2)} (HTTP ${again})`);

  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} passed`);
  process.exit(passed === results.length ? 0 : 1);
})();
