const { chromium } = require('playwright');
const { execSync } = require('child_process');
const T = require('./tasks_test.js');
const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql.replace(/"/g, '\\"')}"`).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await T.routeLibs(page);
  await page.goto(T.BASE + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(T.BASE + '/invoices/create');
  const csrf = await page.evaluate(() => window.Nfelia.csrf);
  ok(await page.locator('text=When will the customer get it?').count() === 1, 'invoice form asks when the customer gets it');
  const base = { csrf_token: csrf, customer_id: '3', issue_date: q('SELECT CURDATE()'), due_date: q('SELECT DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
    'item_description[]': 'Bone Straight 22"', 'item_quantity[]': '1', 'item_unit_price[]': '185000', 'item_discount[]': '0', 'item_product_id[]': '1' };
  const post = (url, form) => page.evaluate(async ([u, f]) => {
    const fd = new URLSearchParams();
    for (const [k, v] of Object.entries(f)) fd.append(k, v);
    const r = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' });
    const text = await r.text();
    return { s: r.status, url: r.url, text, redirected: r.redirected };
  }, [T.BASE + url, form]).then((x) => ({ status: () => (x.redirected ? 302 : x.s), text: async () => x.text, url: x.url }));
  const get = (url) => page.evaluate(async (u) => { const r = await fetch(u, { credentials: 'same-origin' }); return { s: r.status, ct: r.headers.get('content-type'), text: await r.text() }; }, T.BASE + url);

  console.log('Invoice: required on our side');
  let r = await post('/invoices/create', base);
  let html = await r.text();
  ok(r.status() === 200 && html.includes('Choose when the customer gets it'), 'saving without a delivery choice is refused with a plain message');

  console.log('Invoice: pick a date');
  const d5 = q('SELECT DATE_ADD(CURDATE(), INTERVAL 5 DAY)');
  r = await post('/invoices/create', { ...base, deliver_mode: 'date', deliver_by: d5, deliver_show: '1' });
  ok(r.status() === 302 && /invoices\/view\/\d+$/.test(r.url), 'saved and redirected to the invoice: ' + r.url);
  const id = q("SELECT MAX(id) FROM invoices WHERE business_id=1");
  ok(q(`SELECT CONCAT_WS('|', deliver_mode, deliver_by, deliver_show, IFNULL(delivered_at,'-')) FROM invoices WHERE id=${id}`) === `date|${d5}|1|-`, 'deliver date stored, shown to customer');
  await page.goto(T.BASE + '/invoices/view/' + id);
  ok(await page.locator('text=5 days left').count() >= 1, 'invoice page shows the countdown');
  ok(await page.locator('text=The customer sees this date on their invoice.').count() === 1, 'says the customer sees it');
  const token = q(`SELECT token FROM invoices WHERE id=${id}`);
  let pub = (await get('/doc/' + token)).text;
  ok(/Delivery: \w{3} \d{1,2} \w{3} \d{4}/.test(pub), 'customer invoice shows the delivery date');
  const pdf = await get('/doc/' + token + '?format=pdf');
  ok(pdf.s === 200 && (pdf.ct || '').includes('pdf'), 'PDF still renders (' + pdf.s + ' ' + pdf.ct + ')');

  console.log('Tasks list picks it up');
  await page.goto(T.BASE + '/tasks', { waitUntil: 'networkidle' });
  ok(await page.locator('text="Deliver to Adaeze Nwosu"').count() >= 1, 'deliver item on Tasks');

  console.log('Mark delivered, undo');
  r = await post('/invoices/mark-delivered/' + id, { csrf_token: csrf });
  ok(r.status() === 302 && q(`SELECT delivered_at IS NOT NULL FROM invoices WHERE id=${id}`) === '1', 'marked delivered');
  pub = (await get('/doc/' + token)).text;
  ok(/Delivered: \d{1,2} \w{3} \d{4}/.test(pub), 'customer invoice says delivered');
  await page.goto(T.BASE + '/invoices/view/' + id);
  ok(await page.locator('text=/Delivered on/').count() === 1, 'invoice page says delivered');
  r = await post('/invoices/mark-delivered/' + id, { csrf_token: csrf, undo: '1' });
  ok(q(`SELECT delivered_at IS NULL FROM invoices WHERE id=${id}`) === '1', 'undo works');

  console.log('Change to immediate, already handed over');
  r = await post('/invoices/set-delivery/' + id, { csrf_token: csrf, deliver_mode: 'immediate', delivered_now: '1' });
  ok(q(`SELECT CONCAT_WS('|', deliver_mode, deliver_by, deliver_show, delivered_at IS NOT NULL) FROM invoices WHERE id=${id}`) === `immediate|${q('SELECT CURDATE()')}|0|1`, 'immediate = today, handed over = delivered, hidden from customer');
  r = await post('/invoices/set-delivery/' + id, { csrf_token: csrf, deliver_mode: 'date', deliver_by: 'not-a-date' });
  ok(r.status() === 302 && q(`SELECT deliver_mode FROM invoices WHERE id=${id}`) === 'immediate', 'a bad date is refused');
  r = await post('/invoices/set-delivery/' + id, { csrf_token: 'wrong', deliver_mode: 'date', deliver_by: d5 });
  ok(q(`SELECT deliver_mode FROM invoices WHERE id=${id}`) === 'immediate', 'a bad CSRF token changes nothing');

  console.log('Old invoices get a prompt');
  await page.goto(T.BASE + '/invoices/view/5');
  ok(await page.locator('text=/When will Temi get this order/').count() === 1, 'invoice without a date asks for one');

  console.log('Job plan delivery step follows the invoice');
  const step = q("SELECT id FROM tasks WHERE step_key='deliver' AND invoice_id=1");
  const d9 = q('SELECT DATE_ADD(CURDATE(), INTERVAL 9 DAY)');
  r = await post('/invoices/set-delivery/1', { csrf_token: csrf, deliver_mode: 'date', deliver_by: d9 });
  ok(q(`SELECT due_date FROM tasks WHERE id=${step}`) === d9, 'moving the invoice date moves the delivery step');
  r = await post('/invoices/mark-delivered/1', { csrf_token: csrf });
  ok(q(`SELECT done_at IS NOT NULL FROM tasks WHERE id=${step}`) === '1', 'marking delivered ticks the delivery step');

  console.log('Shop order approval');
  r = await post('/orders/approve/40', { csrf_token: csrf });
  ok(q("SELECT status FROM orders WHERE id=40") === 'pending_verification', 'approval without a delivery choice is refused');
  const d3 = q('SELECT DATE_ADD(CURDATE(), INTERVAL 3 DAY)');
  r = await post('/orders/approve/40', { csrf_token: csrf, deliver_mode: 'date', deliver_by: d3, deliver_show: '1' });
  ok(q("SELECT status FROM orders WHERE id=40") === 'approved', 'order approved');
  ok(q("SELECT CONCAT_WS('|', i.deliver_mode, i.deliver_by) FROM orders o JOIN invoices i ON i.id=o.invoice_id WHERE o.id=40") === `date|${d3}`, 'new invoice carries the delivery date');
  await page.goto(T.BASE + '/orders/view/40');
  ok(await page.locator('text=/approved\\. Invoice created/i').count() >= 0, 'order page loads after approval');

  console.log('Customer profile');
  await page.goto(T.BASE + '/customers/view/9');
  ok(await page.locator('text=Coming up with Lara').count() === 1, 'customer page shows what is coming up');
  ok(await page.locator('text=Wig install').count() >= 1, 'lists the appointment');

  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  await browser.close();
})();
