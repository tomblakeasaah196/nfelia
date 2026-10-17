// Part C checks with real CSS: the invoice form's Delivery card, the order
// approval card, a customer's "Coming up" card, and a staff login (money
// hidden, Mine/Everyone, an assigned task reaching the assignee's bell).
//   mysql smartqaq_nfelia_core < planner_seed.sql.txt
//   node visual_check.js OUTDIR
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const T = require('./tasks_test.js');

const OUT = process.argv[2];
const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql.replace(/"/g, '\\"')}"`).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };

async function session(browser, email, phone) {
  const ctx = await browser.newContext(phone
    ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' }
    : { viewport: { width: 1280, height: 900 }, timezoneId: 'Africa/Lagos' });
  const page = await ctx.newPage();
  await T.routeLibs(page);
  await page.goto(T.BASE + '/login');
  await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return { ctx, page };
}

async function open(page, url) {
  await page.goto(T.BASE + url, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '*{animation:none!important;transition:none!important} ::-webkit-scrollbar{display:none}' });
  await page.waitForTimeout(400);
}

async function shotOf(page, locator, name, pad = 16) {
  await locator.scrollIntoViewIfNeeded();
  await page.waitForTimeout(200);
  const bb = await locator.boundingBox();
  const vw = page.viewportSize().width;
  await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: true,
    clip: { x: 0, y: Math.max(0, bb.y + (await page.evaluate(() => window.scrollY)) - pad - 64), width: vw, height: bb.height + pad * 2 + 64 } });
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  // A staff member for Ivie's shop, with the same demo password (the hash is
  // copied in SQL: a bcrypt hash's "$" signs would not survive the shell).
  q(`DELETE FROM users WHERE id = 3`);
  q(`INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) SELECT 3, 1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', password_hash, 1, 1 FROM users WHERE id = 1`);
  q(`DELETE FROM notifications WHERE type = 'general' AND user_id IS NOT NULL`);

  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const owner = await session(browser, 'ivie@demo.nfelia', true);
  await open(owner.page, '/tasks');
  const cfg = await owner.page.evaluate(() => (window.tailwind && window.tailwind.config ? JSON.parse(JSON.stringify(window.tailwind.config)) : null));
  T.setCss(T.compile(cfg, await owner.page.content()));

  for (const [tag, s] of [['m', owner], ['d', await session(browser, 'ivie@demo.nfelia', false)]]) {
    console.log(`Owner screens (${tag === 'm' ? 'phone' : 'desktop'})`);
    await open(s.page, '/invoices/create');
    const card = s.page.locator('div.bg-brand-white', { has: s.page.locator('h2:has-text("Delivery")') }).first();
    ok(await card.isVisible(), 'invoice form shows the Delivery card');
    ok(await s.page.isVisible('text=When will the customer get it?') && await s.page.isVisible('label:has-text("Immediate")') && await s.page.isVisible('label:has-text("Pick a date")'), 'it asks: Immediate or Pick a date');
    await s.page.click('label:has-text("Pick a date")');
    // Alpine shows the field a moment after the click: wait for it, briefly.
    ok(await s.page.locator('input[name=deliver_by]').waitFor({ state: 'visible', timeout: 2000 }).then(() => true, () => false), 'Pick a date reveals the date field');
    await shotOf(s.page, card, `invoice-form-delivery_${tag}`);

    await open(s.page, '/invoices/view/3');
    const dcard = s.page.locator('div', { has: s.page.locator('text=Deliver today') }).filter({ has: s.page.locator('button:has-text("Mark delivered")') }).last();
    ok(await s.page.isVisible('button:has-text("Mark delivered")') && await s.page.isVisible('text=The customer sees this date on their invoice.'), 'invoice page: Deliver today, Mark delivered, who sees the date');
    await shotOf(s.page, dcard, `invoice-view-delivery_${tag}`);

    await open(s.page, '/orders/view/40');
    const acard = s.page.locator('form[action$="/orders/approve/40"]');
    ok(await acard.isVisible() && await acard.locator('text=When will the customer get it?').isVisible(), 'order approval asks when the customer gets it');
    await shotOf(s.page, acard, `order-approve_${tag}`);

    await open(s.page, '/customers/view/9');
    const cu = s.page.locator('div.bg-brand-white', { has: s.page.locator('h2:has-text("Coming up with Lara")') }).last();
    ok(await cu.isVisible() && await cu.locator('text=Wig install').isVisible(), 'customer page: "Coming up with Lara" lists the wig install');
    ok(await cu.locator('a:has-text("Remind Lara")').count() === 1, 'with a WhatsApp reminder for her');
    await shotOf(s.page, cu, `customer-coming-up_${tag}`);
  }

  console.log('Owner assigns a task to staff');
  const op = owner.page;
  await open(op, '/tasks');
  ok(await op.isVisible('[role=radiogroup][aria-label="Whose tasks"]'), 'with a team, the owner sees Everyone / Mine');
  await op.click('button:has-text("Book appointment")'); await op.waitForSelector('#pl-title');
  await op.click('[role=dialog] button[role=radio]:has-text("Task")');
  await op.fill('#pl-title', 'Steam the new frontal wigs');
  ok(await op.isVisible('#pl-assign'), 'the editor asks who is doing it');
  await op.selectOption('#pl-assign', '3');
  await op.click('button[type=submit]:has-text("Add task")');
  await op.waitForTimeout(1200);
  ok(q("SELECT assigned_to FROM tasks WHERE title='Steam the new frontal wigs'") === '3', 'saved as Tunde\'s task');
  ok(q("SELECT COUNT(*) FROM notifications WHERE user_id=3 AND title='New task for you'") === '1', 'a bell entry addressed to Tunde');
  const ownerBell = await op.evaluate(async (b) => (await (await fetch(b + '/api/notifications?action=list', { credentials: 'same-origin' })).json()).notifications.map((n) => n.title), T.BASE);
  ok(!ownerBell.includes('New task for you'), 'which Ivie does not see in her own bell');

  console.log('Staff login');
  const staff = await session(browser, 'tunde@demo.nfelia', true);
  const sp = staff.page;
  await open(sp, '/tasks');
  const text = await sp.textContent('[x-data="planner()"]');
  ok(!/due from|promised to pay|Pay Mama/.test(text), 'staff never see money items');
  ok(/Deliver to Adaeze Nwosu/.test(text) && /Wig install/.test(text), 'but see the shop\'s deliveries and appointments');
  ok(await sp.isVisible('[role=radiogroup][aria-label="Whose tasks"]'), 'Everyone / Mine is there');
  await sp.click('[role=radiogroup][aria-label="Whose tasks"] button:has-text("Mine")');
  await sp.waitForTimeout(300);
  const mine = await sp.textContent('[x-data="planner()"]');
  ok(/Steam the new frontal wigs/.test(mine) && !/Wig install/.test(mine), 'Mine shows only Tunde\'s own task');
  await sp.screenshot({ path: path.join(OUT, 'staff-mine_m.png') });
  const staffBell = await sp.evaluate(async (b) => (await (await fetch(b + '/api/notifications?action=list', { credentials: 'same-origin' })).json()).notifications.map((n) => n.title + ': ' + n.message), T.BASE);
  ok(staffBell.some((t) => t.startsWith('New task for you: Ivie gave you: Steam the new frontal wigs')), 'his bell says Ivie gave him the task');
  const r = await sp.evaluate(async (b) => (await fetch(b + '/tasks/dismiss', { method: 'POST', body: JSON.stringify({ key: 'inv:3:2026-01-01' }), headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf } })).status, T.BASE);
  ok(r === 403, 'staff cannot clear a money item even by calling the server (403)');

  await browser.close();
  q(`DELETE FROM tasks WHERE title='Steam the new frontal wigs'`);
  q(`DELETE FROM users WHERE id = 3`);
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
})();
