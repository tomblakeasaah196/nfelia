// Editing an invoice whose goods are already off the shelf (a sent invoice,
// or one made by approving a website order) moves stock by the difference
// only, and cancelling it afterwards puts back exactly what is still on it.
// Drafts move no stock until they are sent. Reseeds the local database
// first (seed.php, planner_seed.sql.txt, landing_seed.php), so run it only
// there. Needs the local server with APP_URL=http://localhost:8000.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/invoice_edit_stock_test.js <out dir>
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
const moves = (inv) => Number(sql(`SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'invoice' AND reference_id = ${inv}`));
const BUNDLE = 2, WIG = 3, SERVICE = 6;
const line = (product, name, qty, price) => ({ product, name, qty, price });

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  execSync(`php ${KIT}/seed.php ${path.join(OUT, 'app')}`);
  execSync(`mysql smartqaq_nfelia_core < ${KIT}/planner_seed.sql.txt`);
  execSync(`php ${KIT}/landing_seed.php ${OUT}`);
  sql(`INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
       payment_method, currency_code, subtotal, total, status, created_at) VALUES (104, 1, NULL, 'IVL-ORD-021', '${'a'.repeat(32)}', 'cart_pop', 'pickup',
       'Funmi Bakare', '0708 555 0177', 'bank_transfer', 'NGN', 155000, 155000, 'pending', NOW())`);
  sql(`INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES
       (104, ${BUNDLE}, 'Body Wave 18 bundle', 65000, 2, 130000), (104, ${SERVICE}, 'Wig install and styling', 25000, 1, 25000)`);
  const start = { bundle: stock(BUNDLE), wig: stock(WIG), service: stock(SERVICE) };

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  page.on('dialog', (d) => d.accept());
  await page.goto(APP + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

  // Post a form the way the invoice screen does.
  const post = async (url, fields, lines = []) => {
    await page.goto(APP + '/invoices/create');
    return page.evaluate(async ([url, fields, lines]) => {
      const body = new URLSearchParams({ csrf_token: document.querySelector('input[name=csrf_token]').value, ...fields });
      for (const l of lines) {
        body.append('item_description[]', l.name); body.append('item_quantity[]', l.qty);
        body.append('item_unit_price[]', l.price); body.append('item_discount[]', '0'); body.append('item_product_id[]', l.product);
      }
      return (await fetch(url, { method: 'POST', body })).status;
    }, [url, fields, lines]);
  };
  const invoiceFields = { customer_id: '1', issue_date: new Date().toISOString().slice(0, 10), deliver_mode: 'immediate', notes: '' };

  // ── A sent invoice, made by approving a website order ──
  await page.goto(`${APP}/orders/view/104`);
  await page.locator('input[name=deliver_mode][value=immediate]').check({ force: true });
  await Promise.all([page.waitForNavigation(), page.locator('button:has-text("Approve Order")').click()]);
  const inv = Number(sql('SELECT invoice_id FROM orders WHERE id = 104'));
  check('approving the order takes 2 bundles off', stock(BUNDLE) === start.bundle - 2, stock(BUNDLE));
  sql(`UPDATE invoices SET customer_id = 1 WHERE id = ${inv}`);

  await post(`${APP}/invoices/edit/${inv}`, invoiceFields,
    [line(BUNDLE, 'Body Wave 18 bundle', 3, 65000), line(SERVICE, 'Wig install and styling', 1, 25000), line(WIG, 'Kinky Curly Frontal Wig 20', 1, 145000)]);
  check('editing 2 bundles up to 3 takes one more off', stock(BUNDLE) === start.bundle - 3, stock(BUNDLE));
  check('adding a wig line takes the wig off', stock(WIG) === start.wig - 1, stock(WIG));
  check('the service is left alone', stock(SERVICE) === start.service, stock(SERVICE));
  const last = sql(`SELECT GROUP_CONCAT(CONCAT(product_id, ':', type, ':', quantity) ORDER BY product_id) FROM stock_movements WHERE reference_id = ${inv} AND reference_type = 'invoice' AND note LIKE '%edited%'`);
  check('the stock history records each change as a sale', last === `${BUNDLE}:sale:1.00,${WIG}:sale:1.00`, last);

  await post(`${APP}/invoices/edit/${inv}`, invoiceFields,
    [line(SERVICE, 'Wig install and styling', 1, 25000), line(WIG, 'Kinky Curly Frontal Wig 20', 1, 145000)]);
  check('removing the bundle line puts all 3 back', stock(BUNDLE) === start.bundle, stock(BUNDLE));
  check('the wig stays off', stock(WIG) === start.wig - 1, stock(WIG));

  const before = moves(inv);
  await post(`${APP}/invoices/edit/${inv}`, { ...invoiceFields, notes: 'Pickup on Friday' },
    [line(SERVICE, 'Wig install and styling', 1, 25000), line(WIG, 'Kinky Curly Frontal Wig 20', 1, 145000)]);
  check('an edit that changes no quantities moves no stock', moves(inv) === before && stock(WIG) === start.wig - 1, `${before} -> ${moves(inv)}`);

  await page.goto(`${APP}/invoices/view/${inv}`);
  await Promise.all([page.waitForNavigation(), page.evaluate((id) => document.querySelector(`form[action$="/invoices/cancel/${id}"]`).submit(), inv)]);
  check('cancelling puts the wig back', stock(WIG) === start.wig, stock(WIG));
  check('after cancelling, every count is where it started', stock(BUNDLE) === start.bundle && stock(WIG) === start.wig && stock(SERVICE) === start.service,
    JSON.stringify({ bundle: stock(BUNDLE), wig: stock(WIG), service: stock(SERVICE) }));

  // ── A draft: no stock moves until it is sent ──
  await post(`${APP}/invoices/create`, invoiceFields, [line(BUNDLE, 'Body Wave 18 bundle', 2, 65000)]);
  const draft = Number(sql("SELECT MAX(id) FROM invoices WHERE business_id = 1 AND status = 'draft'"));
  check('a new draft moves no stock', stock(BUNDLE) === start.bundle, stock(BUNDLE));
  await post(`${APP}/invoices/edit/${draft}`, invoiceFields, [line(BUNDLE, 'Body Wave 18 bundle', 5, 65000)]);
  check('editing a draft moves no stock', stock(BUNDLE) === start.bundle && moves(draft) === 0, stock(BUNDLE));
  await post(`${APP}/invoices/send/${draft}`, {});
  check('sending the draft takes its 5 bundles off', stock(BUNDLE) === start.bundle - 5, stock(BUNDLE));
  await post(`${APP}/invoices/edit/${draft}`, invoiceFields, [line(BUNDLE, 'Body Wave 18 bundle', 4, 65000)]);
  check('editing it down to 4 puts one back', stock(BUNDLE) === start.bundle - 4, stock(BUNDLE));
  const ret = sql(`SELECT CONCAT(type, ':', quantity) FROM stock_movements WHERE reference_id = ${draft} AND reference_type = 'invoice' ORDER BY id DESC LIMIT 1`);
  check('the stock history records it as a return', ret === 'return:1.00', ret);

  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} passed`);
  process.exit(passed === results.length ? 0 : 1);
})();
