// PR 2 of the October 2026 audit (docs/AUDIT_2026-10.md): the numbers are
// right. Documents get numbers that never clash, money is applied once and
// shows the same on Home and in the profit and loss, client totals follow
// their invoices, stock moves once and comes back exactly, and orders,
// invoices, deliveries, quotes and bookings can't be moved on twice.
//
// Reseeds the local database first (seed.php, planner_seed.sql.txt,
// landing_seed.php), so run it only there. Needs the local server with
// APP_URL=http://localhost:8000 in .env.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/numbers_test.js <out dir>
const { chromium } = require('playwright');
const { execSync, execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = path.resolve(process.argv[2] || 'shots');
const KIT = __dirname;
const ROOT = path.resolve(KIT, '../../..');
const APP = 'http://localhost:8000';
const SHOP = 'http://ivieluxe.localhost:8000';
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const results = [];
const check = (label, ok, detail = '') => { results.push(!!ok); console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 300))); };
const sql = (q) => execFileSync('mysql', ['-N', 'smartqaq_nfelia_core', '-e', q]).toString().trim();
const num = (q) => Number(sql(q));
const stock = (id) => num(`SELECT stock_qty FROM products WHERE id = ${id}`);
const today = () => sql('SELECT CURDATE()');
// Runs PHP with the app's helpers loaded, as index.php loads them
const php = (code) => {
  const file = path.join(OUT, 'probe.php');
  const helpers = ['functions', 'auth', 'billing', 'stock', 'money', 'invoices', 'token', 'mail', 'webpush', 'planner', 'shop_links',
                   'storefront', 'cloudflare', 'shop_orders', 'bookings', 'seo', 'theme', 'nav', 'signin', 'passkeys', 'notify', 'screens'];
  fs.writeFileSync(file, `<?php require '${ROOT}/vendor/autoload.php'; require '${ROOT}/config/database.php'; require '${ROOT}/config/app.php';
foreach (${JSON.stringify(helpers)} as $h) require_once '${ROOT}/app/helpers/' . $h . '.php';
${code}`);
  return execFileSync('php', [file]).toString().trim();
};
// The client totals worked out from scratch, to compare with what is stored
const derivedTotals = (id) => sql(`SELECT
    (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE customer_id = ${id} AND status NOT IN ('draft', 'cancelled')),
    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ${id})
      + (SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE customer_id = ${id} AND invoice_id IS NULL)
      + (SELECT COALESCE(SUM(s.amount), 0) FROM debt_settlements s JOIN debts d ON d.id = s.debt_id WHERE d.customer_id = ${id} AND d.type = 'receivable' AND d.invoice_id IS NULL),
    (SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE customer_id = ${id} AND status IN ('sent', 'partially_paid', 'overdue'))
      + (SELECT COALESCE(SUM(balance), 0) FROM debts WHERE customer_id = ${id} AND type = 'receivable' AND invoice_id IS NULL AND status IN ('active', 'settling'))`);
const storedTotals = (id) => sql(`SELECT total_invoiced, total_received, outstanding_balance FROM customers WHERE id = ${id}`);

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  execSync(`php ${KIT}/seed.php ${path.join(OUT, 'app')}`);
  execSync(`mysql smartqaq_nfelia_core < ${KIT}/planner_seed.sql.txt`);
  execSync(`php ${KIT}/landing_seed.php ${OUT}`);
  sql(`DELETE FROM auth_throttle; INSERT INTO suppliers (id, business_id, full_name, phone) VALUES (30, 1, 'Trade Fair Hair Hub', '0802 000 0030');
       UPDATE businesses SET subdomain = 'kemievents', subdomain_status = 'active', storefront_look = 'shea' WHERE id = 2`);

  const browser = await chromium.launch();
  const errors = [];
  const session = async (email) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(email + ': ' + e.message));
    page.on('dialog', (d) => d.accept());
    await page.route(/cdn\.tailwindcss\.com/, (r) => r.fulfill({ body: 'window.tailwind = { config: {} };', contentType: 'text/javascript' }));
    await page.route(/fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare|unpkg\.com|cdn\.jsdelivr/, (r) => r.fulfill({ body: '' }));
    if (email) {
      await page.goto(APP + '/login');
      await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
      await page.goto(APP + '/dashboard');
    }
    return page;
  };
  // A post the way the app's pages send it: a plain form post, or the
  // pages' AJAX (with X-Requested-With)
  const send = (page, url, fields = {}, ajax = false) => page.evaluate(async ([url, fields, ajax]) => {
    const fd = new FormData();
    const token = (window.Nfelia && window.Nfelia.csrf) || (document.querySelector('input[name=csrf_token]') || {}).value || '';
    if (!('csrf_token' in fields)) fd.append('csrf_token', token);
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => fd.append(k, x)); else fd.append(k, v);
    }
    const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: ajax ? { 'X-Requested-With': 'XMLHttpRequest' } : {} });
    return { status: r.status, url: r.url, text: await r.text() };
  }, [url, fields, ajax]);
  const form = (page, url, fields) => send(page, url, fields, false);
  const ajax = (page, url, fields) => send(page, url, fields, true);
  const get = (page, url) => page.evaluate(async (url) => {
    const r = await fetch(url, { credentials: 'same-origin' });
    return { status: r.status, url: r.url, text: await r.text() };
  }, url);
  const lines = (rows) => ({
    'item_description[]': rows.map((r) => r[0]), 'item_quantity[]': rows.map((r) => String(r[1])),
    'item_unit_price[]': rows.map((r) => String(r[2])), 'item_discount[]': rows.map(() => '0'), 'item_product_id[]': rows.map((r) => String(r[3] || '')),
    deliver_mode: 'immediate',   // when the customer gets it: the form always sends it
  });
  const lastId = (table) => sql(`SELECT MAX(id) FROM ${table} WHERE business_id = 1`);
  // A new invoice for client 1 with these lines; sent unless asked not to
  const newInvoice = async (rows, extra = {}, sendIt = true, customer = 1) => {
    await form(ivie, APP + '/invoices/create', { customer_id: String(customer), bank_account_id: '1', issue_date: today(), due_date: today(), tax_rate: '0', discount_amount: '0', ...lines(rows), ...extra });
    const id = lastId('invoices');
    if (sendIt) await form(ivie, APP + `/invoices/send/${id}`, {});
    return id;
  };
  const pay = (id, amount, extra = {}) => form(ivie, APP + `/invoices/record_payment/${id}`, { amount: String(amount), payment_date: today(), method: 'cash', ...extra });

  const ivie = await session('ivie@demo.nfelia');
  const kemi = await session('kemi@demo.nfelia');
  const anon = await session(null);
  let r;

  /* ── Document numbers (M4, M22, D5) ─────────────────────────── */
  const expMax = num("SELECT MAX(CAST(SUBSTRING_INDEX(doc_number, '-', -1) AS UNSIGNED)) FROM expenses WHERE business_id = 1");
  await form(ivie, APP + '/expenses/create', { category: 'rent', description: 'Store room rent', amount: '50000', expense_date: today(), payment_method: 'cash', payment_status: 'paid' });
  const rent = sql("SELECT doc_number FROM expenses WHERE business_id = 1 AND description = 'Store room rent'");
  check('N1 an expense saves, numbered after the ones already there', rent === `IVL-EXP-${String(expMax + 1).padStart(3, '0')}`, `${rent} (highest was ${expMax})`);

  const pixie = stock(4);
  await form(ivie, APP + '/expenses/create', { category: 'stock_purchase', description: 'Pixie curls from Trade Fair', expense_date: today(), payment_method: 'bank_transfer',
    payment_status: 'unpaid', supplier_id: '30', 'item_description[]': ['Pixie Curls 12"'], 'item_quantity[]': ['3'], 'item_unit_cost[]': ['28000'], 'item_product_id[]': ['4'] });
  const buy = sql("SELECT id FROM expenses WHERE business_id = 1 AND description = 'Pixie curls from Trade Fair'");
  check('N2 a stock purchase restocks, with a stock history line', stock(4) === pixie + 3
        && sql(`SELECT CONCAT(type, ' ', quantity) FROM stock_movements WHERE reference_type = 'expense' AND reference_id = ${buy}`) === 'stock_in 3.00', `${pixie} -> ${stock(4)}`);
  const payable = sql(`SELECT CONCAT(id, ' ', balance, ' ', status) FROM debts WHERE expense_id = ${buy}`);
  check('N3 an unpaid purchase is owed to the supplier as soon as it is saved', /^\d+ 84000\.00 active$/.test(payable), payable);
  r = await ajax(ivie, APP + `/debts/settle/${payable.split(' ')[0]}`, { amount: '30000', method: 'bank_transfer', settlement_date: today(), note: 'First part' });
  check('N4 paying part of it updates the expense too', sql(`SELECT CONCAT(amount_paid, ' ', balance_due, ' ', payment_status) FROM expenses WHERE id = ${buy}`) === '30000.00 54000.00 partial'
        && sql(`SELECT balance FROM debts WHERE expense_id = ${buy}`) === '54000.00', r.text);
  await form(ivie, APP + `/expenses/delete/${buy}`, {});
  check('N5 deleting the purchase takes the stock back off', stock(4) === pixie, `${pixie} -> ${stock(4)}`);

  const kit = stock(7);
  await form(ivie, APP + '/receipts/create', { customer_id: '', issue_date: today(), method: 'cash', note: 'Walk-in',
    'item_description[]': ['Edge control & hair care kit'], 'item_quantity[]': ['1'], 'item_unit_price[]': ['12000'], 'item_product_id[]': ['7'] });
  const walkin = sql("SELECT CONCAT(id, ' ', IFNULL(customer_id, 'none'), ' ', amount) FROM receipts WHERE business_id = 1 AND invoice_id IS NULL ORDER BY id DESC LIMIT 1");
  check('N6 a walk-in sale (no client) saves its receipt and takes the stock', / none 12000\.00$/.test(walkin) && stock(7) === kit - 1, `${walkin}; ${kit} -> ${stock(7)}`);
  await form(ivie, APP + `/receipts/delete/${walkin.split(' ')[0]}`, {});
  check('N7 deleting that receipt puts the stock back', stock(7) === kit && sql(`SELECT COUNT(*) FROM receipts WHERE id = ${walkin.split(' ')[0]}`) === '0');

  const before = num('SELECT COUNT(*) FROM invoices WHERE business_id = 1');
  await Promise.all([1, 2].map(() => form(ivie, APP + '/invoices/create', { customer_id: '2', issue_date: today(), due_date: today(), ...lines([['Consultation', 1, 5000]]) })));
  const twoNumbers = sql('SELECT COUNT(DISTINCT doc_number), COUNT(*) FROM invoices WHERE business_id = 1 ORDER BY id DESC').split('\t');
  check('N8 two invoices made at the same moment get different numbers', num('SELECT COUNT(*) FROM invoices WHERE business_id = 1') === before + 2 && twoNumbers[0] === twoNumbers[1], twoNumbers);

  /* ── Payments (M3, M9, M10, M13, M15, M6) ────────────────────── */
  const odd = await newInvoice([['Silk press', 1, 100.10]]);
  await pay(odd, 50.05); await pay(odd, 50.05);
  check('N9 two halves of an odd amount leave the invoice fully paid', sql(`SELECT CONCAT(status, ' ', balance_due) FROM invoices WHERE id = ${odd}`) === 'paid 0.00',
        sql(`SELECT CONCAT(status, ' ', balance_due) FROM invoices WHERE id = ${odd}`));

  const fifty = await newInvoice([['Bridal styling', 1, 50000]], {}, true, 2);
  await Promise.all([pay(fifty, 30000), pay(fifty, 30000)]);
  check('N10 two payments at once can\'t overpay the invoice', num(`SELECT COUNT(*) FROM payments WHERE invoice_id = ${fifty}`) === 1
        && sql(`SELECT amount_paid FROM invoices WHERE id = ${fifty}`) === '30000.00', sql(`SELECT GROUP_CONCAT(amount) FROM payments WHERE invoice_id = ${fifty}`));
  check('N11 the client\'s totals follow their invoices', storedTotals(2) === derivedTotals(2), `${storedTotals(2)} vs ${derivedTotals(2)}`);

  const pid = sql(`SELECT id FROM payments WHERE invoice_id = ${fifty}`);
  await Promise.all([get(ivie, APP + `/receipts/from_payment/${pid}`), get(ivie, APP + `/receipts/from_payment/${pid}`)]);
  check('N12 a receipt for a payment is made once, with the right balance', sql(`SELECT GROUP_CONCAT(balance_remaining) FROM receipts WHERE payment_id = ${pid}`) === '20000.00',
        sql(`SELECT GROUP_CONCAT(balance_remaining) FROM receipts WHERE payment_id = ${pid}`));

  sql(`UPDATE invoices SET due_date = DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE id = ${fifty}`);
  await ivie.goto(APP + '/invoices');
  const overdue = () => sql(`SELECT CONCAT(COUNT(*), ' ', IFNULL(MAX(balance), ''), ' ', IFNULL(MAX(status), '')) FROM debts WHERE invoice_id = ${fifty}`);
  check('N13 an overdue invoice raises one debt for what is left', overdue() === '1 20000.00 active', overdue());
  await pay(fifty, 5000); await pay(fifty, 5000);
  check('N14 payments after that bring the debt down each time', overdue() === '1 10000.00 settling', overdue());
  await form(ivie, APP + `/debts/write_off/${sql(`SELECT id FROM debts WHERE invoice_id = ${fifty}`)}`, {});
  await ivie.goto(APP + '/invoices');
  check('N15 a written-off debt doesn\'t come back', overdue() === '1 10000.00 written_off', overdue());

  const debt5 = sql('SELECT id FROM debts WHERE invoice_id = 5');
  const paid5 = num('SELECT amount_paid FROM invoices WHERE id = 5');
  r = await ajax(ivie, APP + `/debts/settle/${debt5}`, { amount: '10000', method: 'pos', settlement_date: today() });
  check('N16 recording a settlement on an invoice\'s debt saves it as a payment', num('SELECT amount_paid FROM invoices WHERE id = 5') === paid5 + 10000
        && num(`SELECT COUNT(*) FROM debt_settlements WHERE debt_id = ${debt5}`) === 1 && num("SELECT COUNT(*) FROM payments WHERE invoice_id = 5 AND method = 'pos'") === 1, r.text);

  /* ── Invoices (M11, M14, M18, M20, S2, S5, D1, D2) ───────────── */
  const bundles = stock(2);
  const twice = await newInvoice([['Body Wave 18" bundle', 1, 65000, 2]], {}, false);
  await Promise.all([form(ivie, APP + `/invoices/send/${twice}`, {}), form(ivie, APP + `/invoices/send/${twice}`, {})]);
  check('N17 a double tap on Send takes the stock off once', stock(2) === bundles - 1, `${bundles} -> ${stock(2)}`);

  const big = await newInvoice([['Closure install', 1, 10000]], { discount_amount: '50000', tax_rate: '-5' }, false);
  check('N18 a discount bigger than the subtotal, or a negative tax, can\'t make a negative invoice',
        sql(`SELECT CONCAT(discount_amount, ' ', tax_rate, ' ', total) FROM invoices WHERE id = ${big}`) === '10000.00 0.00 0.00', sql(`SELECT CONCAT(discount_amount, ' ', tax_rate, ' ', total) FROM invoices WHERE id = ${big}`));
  const thirds = await newInvoice([['Hair pins', 3, 0.333]], {}, false);
  check('N19 lines are rounded to kobo before they are added up', sql(`SELECT CONCAT(subtotal, ' ', total) FROM invoices WHERE id = ${thirds}`) === '1.00 1.00');

  const bone = stock(1);
  sql(`INSERT INTO invoices (business_id, customer_id, user_id, doc_number, status, issue_date, subtotal, total, balance_due, token)
       VALUES (1, 4, 1, 'IVL-INV-090', 'sent', CURDATE(), 185000, 185000, 185000, '${'c'.repeat(32)}')`);
  const old = lastId('invoices');
  sql(`INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, subtotal) VALUES (${old}, 1, 'Bone Straight 22"', 1, 185000, 185000)`);
  await form(ivie, APP + `/invoices/cancel/${old}`, {});
  check('N20 cancelling an invoice that never took stock puts nothing back', stock(1) === bone && sql(`SELECT status FROM invoices WHERE id = ${old}`) === 'cancelled', `${bone} -> ${stock(1)}`);

  const withDel = await newInvoice([['Pixie Curls 12"', 2, 48000, 4]]);
  await form(ivie, APP + '/deliveries/create', { invoice_id: String(withDel), customer_id: '1', recipient_name: 'Mrs Folake Ade', delivery_address: '3 Admiralty Road, Lekki',
    'item_description[]': ['Pixie Curls 12"'], 'item_quantity[]': ['2'], 'item_unit[]': ['wig'], 'item_product_id[]': ['4'], 'item_notes[]': [''] });
  const del = sql(`SELECT CONCAT(id, ' ', doc_number) FROM deliveries WHERE invoice_id = ${withDel}`);
  check('N21 a delivery note can be made by hand, with the next number', / IVL-DEL-042$/.test(del), del);
  await form(ivie, APP + '/deliveries/create', { invoice_id: String(withDel), customer_id: '1', recipient_name: 'Mrs Folake Ade', delivery_address: '3 Admiralty Road, Lekki',
    'item_description[]': ['Pixie Curls 12"'], 'item_quantity[]': ['2'], 'item_unit[]': ['wig'], 'item_product_id[]': ['4'], 'item_notes[]': [''] });
  check('N22 an invoice gets one live delivery, not two', num(`SELECT COUNT(*) FROM deliveries WHERE invoice_id = ${withDel} AND deleted_at IS NULL`) === 1);
  r = await get(ivie, APP + '/deliveries/search_invoices?q=IVL');
  check('N23 searching invoices for a delivery works', r.status === 200 && r.text.startsWith('['), r.status + ' ' + r.text.slice(0, 100));
  await form(ivie, APP + `/invoices/edit/${withDel}`, { customer_id: '1', bank_account_id: '1', issue_date: today(), due_date: today(), tax_rate: '0', discount_amount: '0', ...lines([['Pixie Curls 12"', 1, 48000, 4]]) });
  check('N24 editing the invoice updates its delivery note', sql(`SELECT GROUP_CONCAT(quantity) FROM delivery_items WHERE delivery_id = ${del.split(' ')[0]}`) === '1.00',
        sql(`SELECT GROUP_CONCAT(quantity) FROM delivery_items WHERE delivery_id = ${del.split(' ')[0]}`));
  r = await form(ivie, APP + `/invoices/edit/${withDel}`, { customer_id: '1', issue_date: today(), tax_rate: '0', discount_amount: '0', ...lines([['', 1, 0]]) });
  check('N25 after a mistake the edit form still posts to its own invoice', r.text.includes(`/invoices/edit/${withDel}"`), r.url);
  r = await form(ivie, APP + `/customers/edit/1`, { full_name: '', phone: '0802 555 0111' });
  check('N26 the same holds for a client\'s form', r.text.includes('/customers/edit/1"'));

  sql('UPDATE businesses SET default_tax_rate = 7.5 WHERE id = 1');
  r = await get(ivie, APP + '/invoices/create');
  check('N27 a new invoice starts at the business\'s VAT rate', /taxRate: 7\.5\b/.test(r.text));
  sql('UPDATE businesses SET default_tax_rate = 0 WHERE id = 1');

  /* ── Orders (M7, S4, S10, S17) ────────────────────────────────── */
  sql(`INSERT INTO orders (id, business_id, customer_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone,
       payment_method, currency_code, subtotal, total, status, created_at) VALUES (104, 1, NULL, 'IVL-ORD-021', '${'a'.repeat(32)}', 'cart_pop', 'delivery',
       'Funmi Bakare', '0708 555 0177', 'bank_transfer', 'NGN', 130000, 130000, 'pending', NOW())`);
  sql(`INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (104, 2, 'Body Wave 18 bundle', 65000, 2, 130000)`);
  const b2 = stock(2);
  const approve = (id) => form(ivie, APP + `/orders/approve/${id}`, { deliver_mode: 'immediate' });
  await Promise.all([approve(104), approve(104)]);
  check('N28 approving the same order twice at once makes one invoice and moves stock once',
        num("SELECT COUNT(*) FROM invoices WHERE business_id = 1 AND notes LIKE '%IVL-ORD-021%'") <= 1 && sql('SELECT invoice_id IS NOT NULL FROM orders WHERE id = 104') === '1' && stock(2) === b2 - 2,
        `${b2} -> ${stock(2)}`);

  sql("INSERT INTO customers (id, business_id, full_name, phone, deleted_at) VALUES (40, 1, 'Kemi Bello (old)', '0814 555 0210', NOW())");
  sql('UPDATE orders SET customer_id = 40 WHERE id = 103');
  await approve(103);
  const inv103 = sql('SELECT invoice_id FROM orders WHERE id = 103');
  check('N29 a paid order\'s payment is recorded on its invoice, with its receipt', sql(`SELECT CONCAT(status, ' ', amount_paid) FROM invoices WHERE id = ${inv103}`) === 'paid 145000.00'
        && num(`SELECT COUNT(*) FROM payments WHERE invoice_id = ${inv103}`) === 1 && sql(`SELECT payment_id IS NOT NULL FROM receipts WHERE id = (SELECT receipt_id FROM orders WHERE id = 103)`) === '1');
  check('N30 approving never files the invoice under a deleted client', sql(`SELECT c.deleted_at IS NULL FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ${inv103}`) === '1');

  await form(ivie, APP + '/orders/update_status/103', { status: 'cancelled' });
  check('N31 a paid order can\'t be cancelled before its money is refunded', sql('SELECT status FROM orders WHERE id = 103') === 'approved' && sql(`SELECT status FROM invoices WHERE id = ${inv103}`) === 'paid');
  const inv104 = sql('SELECT invoice_id FROM orders WHERE id = 104');
  const del104 = sql('SELECT delivery_id FROM orders WHERE id = 104');
  const b3 = stock(2);
  await form(ivie, APP + '/orders/update_status/104', { status: 'cancelled' });
  check('N32 cancelling an unpaid order cancels its invoice, puts the stock back and closes its delivery',
        sql('SELECT status FROM orders WHERE id = 104') === 'cancelled' && sql(`SELECT status FROM invoices WHERE id = ${inv104}`) === 'cancelled' && stock(2) === b3 + 2
        && sql(`SELECT status FROM deliveries WHERE id = ${del104}`) === 'failed', `stock ${b3} -> ${stock(2)}, delivery ${sql(`SELECT status FROM deliveries WHERE id = ${del104}`)}`);
  const sign104 = sql(`SELECT signoff_token FROM deliveries WHERE id = ${del104}`);
  await anon.goto(APP + `/sign/${sign104}`);
  const page104 = await anon.content();
  r = await ajax(anon, APP + `/sign/${sign104}`, { signoff_name: 'Funmi Bakare', signature_data: 'data:image/png;base64,' + PNG });
  check('N33 a cancelled delivery can\'t be signed for', /This delivery was cancelled/.test(page104) && /cancelled/.test(r.text)
        && sql(`SELECT status FROM deliveries WHERE id = ${del104}`) === 'failed', r.text);

  sql("UPDATE products SET booking_mode = 'slot', booking_minutes = 60 WHERE id = 6");
  const otherShop = sql("INSERT INTO products (business_id, name, sell_price, stock_qty) VALUES (2, 'Chair hire', 2500, 100); SELECT LAST_INSERT_ID()");
  await anon.goto(SHOP + '/');
  const checkout = (items) => ajax(anon, SHOP + '/checkout', { channel: 'whatsapp', fulfillment_type: 'pickup', customer_name: 'Bisi Ola', customer_phone: '0803 222 0101',
    items: JSON.stringify(items), payment_method: 'none' });
  r = await checkout([{ product_id: 6, quantity: 1 }]);
  const r2 = await checkout([{ product_id: Number(otherShop), quantity: 1 }]);
  const r3 = await checkout([{ product_id: 5, quantity: 1 }]);    // not on this shop's page (it lists five products)
  check('N34 checkout refuses a bookable service, another shop\'s product and one the shop doesn\'t list',
        r.status === 422 && r2.status === 422 && r3.status === 422, `${r.status} ${r2.status} ${r3.status}`);
  const wigs = stock(3);
  r = await checkout([{ product_id: 3, quantity: wigs + 2 }]);
  const lowOrder = sql('SELECT MAX(id) FROM orders WHERE business_id = 1');
  r = await get(ivie, APP + `/orders/view/${lowOrder}`);
  check('N35 the owner sees an order asks for more than is in stock', r.text.includes(wigs > 0 ? `Only ${wigs} wig in stock` : 'Out of stock'), `order ${lowOrder}, ${wigs} in stock`);

  /* ── Quotes (M8) ──────────────────────────────────────────────── */
  const quote = sql("SELECT id FROM quotations WHERE business_id = 1 AND status <> 'converted' ORDER BY id LIMIT 1");
  await Promise.all([1, 2].map(() => form(ivie, APP + `/quotations/convert/${quote}`, {})));
  check('N36 a quote converted twice at once makes one invoice', num(`SELECT COUNT(*) FROM invoices WHERE quotation_id = ${quote}`) === 1
        && sql(`SELECT status FROM quotations WHERE id = ${quote}`) === 'converted');

  /* ── Bookings (M21, S7, S14) ─────────────────────────────────── */
  const booking = (n, deposit) => {
    sql(`INSERT INTO bookings (business_id, product_id, product_name, booking_number, public_token, kind, starts_at, ends_at, units, unit_price, total, deposit_due, currency_code,
         customer_id, customer_name, customer_phone, channel, payment_method, status, created_at) VALUES (1, 6, 'Wig revamp & styling', 'IVL-BKG-0${n}', '${String(n).repeat(32).slice(0, 32)}',
         'slot', DATE_ADD(CURDATE(), INTERVAL ${n} DAY) + INTERVAL 10 HOUR, DATE_ADD(CURDATE(), INTERVAL ${n} DAY) + INTERVAL 11 HOUR, 1, 15000, 15000, ${deposit}, 'NGN',
         5, 'Temi Adeyemi', '0817 555 0155', 'site', 'bank_transfer', 'pending', NOW())`);
    return sql(`SELECT id FROM bookings WHERE booking_number = 'IVL-BKG-0${n}'`);
  };
  const bk = booking(31, 5000);
  await Promise.all([1, 2].map(() => form(ivie, APP + `/bookings/confirm/${bk}`, { deposit_received: '1' })));
  const bkRow = sql(`SELECT CONCAT(b.status, ' ', r.amount, ' ', IFNULL(r.customer_id, 'none')) FROM bookings b JOIN receipts r ON r.id = b.receipt_id WHERE b.id = ${bk}`);
  check('N37 confirming with the deposit ticked puts it in the books as a receipt', bkRow === 'confirmed 5000.00 5', bkRow);
  check('N38 a double tap on Confirm adds one appointment and one receipt', num("SELECT COUNT(*) FROM tasks WHERE notes LIKE 'Booking IVL-BKG-031%'") === 1
        && num("SELECT COUNT(*) FROM receipts WHERE note LIKE 'Deposit for booking IVL-BKG-031%'") === 1);
  const bk2 = booking(32, 0);
  await Promise.all([form(ivie, APP + `/bookings/confirm/${bk2}`, {}), form(ivie, APP + `/bookings/decline/${bk2}`, { message: 'Fully booked' })]);
  const state = sql(`SELECT status FROM bookings WHERE id = ${bk2}`);
  const tasks = num("SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND notes LIKE 'Booking IVL-BKG-032%'");
  check('N39 confirm and decline at once leave one answer, and the calendar agrees', (state === 'confirmed' && tasks === 1) || (state === 'declined' && tasks === 0), `${state} ${tasks}`);
  check('N40 a fixed deposit stays for a service priced 0', php("echo booking_deposit(['deposit_type' => 'fixed', 'deposit_value' => 5000], 0);") === '5000');

  /* ── Deliveries (S11, S16) ───────────────────────────────────── */
  const seeded = sql("SELECT CONCAT(id, ' ', signoff_token) FROM deliveries WHERE doc_number = 'IVL-DEL-041'").split(' ');
  await anon.goto(APP + `/sign/${seeded[1]}`);
  const signs = await Promise.all([1, 2].map(() => ajax(anon, APP + `/sign/${seeded[1]}`, { signoff_name: 'Lara Coker', signature_data: 'data:image/png;base64,' + PNG })));
  check('N41 a delivery is signed for once', signs.filter((s) => /"success":true/.test(s.text)).length === 1 && sql(`SELECT status FROM deliveries WHERE id = ${seeded[0]}`) === 'signed_off',
        signs.map((s) => s.text).join(' | '));
  const track = sql('SELECT public_token FROM orders WHERE id = 101');
  sql(`UPDATE deliveries SET deleted_at = NOW() WHERE id = ${seeded[0]}`);
  check('N42 a deleted delivery no longer drives the shopper\'s tracking page',
        php(`echo json_encode(shop_order_by_token(get_db(), 1, '${track}')['delivery']);`) === 'null');

  /* ── Stock (S6, S9, S12, S13, S18) ───────────────────────────── */
  const edge = stock(7);
  await form(ivie, APP + '/products/edit/7', { name: 'Edge control & hair care kit', sku: 'EDG-KT', category: 'Care', sell_price: '12000', cost_price: '6000', low_stock_threshold: '5', unit: 'kit', is_service: '1' });
  check('N43 goods that become a service clear their stock with a history line', stock(7) === 0
        && sql("SELECT CONCAT(type, ' ', quantity) FROM stock_movements WHERE product_id = 7 AND reference_type = 'product' ORDER BY id DESC LIMIT 1") === `stock_out ${edge.toFixed(2)}`, `${edge} -> ${stock(7)}`);
  const adjust = (id, type, qty) => form(ivie, APP + `/products/adjust-stock/${id}`, { adj_type: type, quantity: String(qty), unit_cost: '0', note: 'Count' });
  await adjust(4, 'adjustment', 0);
  check('N44 a count can be set to 0', stock(4) === 0);
  await adjust(8, 'stock_out', 99);
  check('N45 taking off more than is there is refused', stock(8) === 4);
  const b4 = stock(2);
  await Promise.all([adjust(2, 'stock_in', 1), adjust(2, 'stock_in', 1)]);
  check('N46 two stock changes at once both count', stock(2) === b4 + 2, `${b4} -> ${stock(2)}`);
  await form(ivie, APP + '/products/edit/2', { name: 'Body Wave 18" bundle', sku: 'BDW-18', category: 'Bundles', sell_price: '65000', cost_price: '-5', low_stock_threshold: '5', unit: 'bundle' });
  check('N47 a negative cost price is refused', sql('SELECT cost_price FROM products WHERE id = 2') === '40000.00');
  sql('UPDATE products SET is_active = 0, stock_qty = 0 WHERE id = 1');
  r = await get(ivie, APP + '/products');
  const stockPage = Number(((r.text.match(/(\d+) need restocking/) || [])[1]));
  check('N48 the Stock page counts what needs restocking as Home does', stockPage === num('SELECT COUNT(*) FROM v_low_stock_products WHERE business_id = 1'), `${stockPage}`);

  /* ── Money in: Home and the profit and loss agree (M2) ───────── */
  const moneyIn = Number(php("echo money_in(get_db(), 1, date('Y-m-01'), date('Y-m-t'));"));
  const expected = num(`SELECT (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE business_id = 1 AND payment_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE()))
                         + (SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE business_id = 1 AND invoice_id IS NULL AND issue_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE()))`);
  r = await get(ivie, APP + '/reports/profit_loss');
  const home = await get(ivie, APP + '/dashboard');
  const shown = '₦' + Math.round(moneyIn).toLocaleString('en-US');                       // the report rounds to whole naira
  const homeShown = php(`echo planner_money(${moneyIn}, '₦');`);                         // Home keeps kobo when there are some
  check('N49 money in counts every payment and direct sale, the same on Home and in the profit and loss',
        moneyIn === expected && r.text.includes(shown) && home.text.includes(homeShown), `${moneyIn} vs ${expected}, ${shown} / ${homeShown}`);

  /* ── Purge (D6) ───────────────────────────────────────────────── */
  sql("INSERT INTO customers (id, business_id, full_name, phone) VALUES (41, 1, 'Test client', '0803 000 0041')");
  sql("INSERT INTO tasks (business_id, kind, title, due_date, customer_id, created_by) VALUES (1, 'task', 'Call the test client', CURDATE(), 41, 1)");
  await ajax(ivie, APP + '/purge/save_pin', { pin: '2468', password: 'DemoPass1' });
  r = await ajax(ivie, APP + '/purge/destroy', { module: 'customers', ids: JSON.stringify([3, 41]), password: 'DemoPass1', pin: '2468' });
  check('N50 purging clients skips one with documents and still purges the rest', /"success":true/.test(r.text) && num('SELECT COUNT(*) FROM customers WHERE id = 41') === 0
        && num('SELECT COUNT(*) FROM customers WHERE id = 3') === 1 && sql("SELECT IFNULL(customer_id, 'none') FROM tasks WHERE title = 'Call the test client'") === 'none', r.text);
  const purgeMe = await newInvoice([['Kinky Curly Frontal Wig 20"', 1, 145000, 3]]);
  const wig = stock(3);
  sql(`UPDATE invoices SET due_date = DATE_SUB(CURDATE(), INTERVAL 2 DAY) WHERE id = ${purgeMe}`);
  await pay(purgeMe, 45000);
  await ivie.goto(APP + '/invoices');
  r = await ajax(ivie, APP + '/purge/destroy', { module: 'invoices', ids: JSON.stringify([purgeMe]), password: 'DemoPass1', pin: '2468' });
  check('N51 purging an invoice takes its payments and debt with it and puts its stock back', /"success":true/.test(r.text) && stock(3) === wig + 1
        && num(`SELECT COUNT(*) FROM payments WHERE invoice_id = ${purgeMe}`) === 0 && num(`SELECT COUNT(*) FROM debts WHERE invoice_id = ${purgeMe}`) === 0, r.text);

  /* ── Phone numbers for WhatsApp (M23) ────────────────────────── */
  sql("INSERT IGNORE INTO countries (id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol, payment_method, monthly_price, extra_seat_price, trial_days, is_active) VALUES (9, 'Cameroon', 'CM', '', 'CFA Franc', 'XAF', 'FCFA', 'mobile_money', 5000, 2500, 14, 1)");
  sql('UPDATE businesses SET country_id = 9 WHERE id = 2');
  sql("UPDATE customers SET whatsapp = NULL, phone = '677 123 456' WHERE id = 20");
  r = await get(kemi, APP + '/customers/view/20');
  check('N52 a Cameroon business\'s client gets a +237 WhatsApp link', r.text.includes('wa.me/237677123456'));
  sql('UPDATE businesses SET country_id = 1 WHERE id = 2');

  /* ── Every client's totals still match their records (M10) ───── */
  // The clients the kit's invoices, payments, orders and bookings were for
  const touched = ['1', '2', '4', '5', '7', sql('SELECT customer_id FROM invoices WHERE id = ' + inv103)];
  const off = touched.filter((id) => storedTotals(id) !== derivedTotals(id));
  check('N53 after all of that, every client the kit touched has totals that match their records', off.length === 0,
        off.map((id) => `${id}: ${storedTotals(id)} vs ${derivedTotals(id)}`).join('; '));

  check('N54 no JavaScript errors on the pages opened', errors.length === 0, errors.join(' | '));
  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} passed`);
  process.exit(passed === results.length ? 0 : 1);
})();
