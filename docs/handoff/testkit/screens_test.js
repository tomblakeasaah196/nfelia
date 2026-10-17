// The rebuilt screens, end to end: Home, Sales (Invoices, Receipts, Quotes),
// the invoice and receipt pages, Clients and Stock. Against the local copy on
// :8099 (see up.sh); the database is put back as it was at the end.
//   node screens_test.js OUTDIR
// Each screen is checked at 375 and 1280 wide: no sideways scroll, no text
// under 12px, no all-caps labels, every control at least 44px, no script
// errors. The business's own document (invoice and receipt previews) keeps
// its own look and is left out of those checks.
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const BASE = 'http://127.0.0.1:8099';
const OUT = process.argv[2] || path.join(__dirname, 'build', 'screens');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const PHONE = { viewport: { width: 375, height: 812 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' };
const DESK = { viewport: { width: 1280, height: 900 }, timezoneId: 'Africa/Lagos' };

// SQL on stdin, on Lagos time and in UTF-8, like the app's own connection.
const q = (sql) => execSync('mysql -N --default-character-set=utf8mb4 smartqaq_nfelia_core', { input: "SET time_zone = '+01:00';\n" + sql }).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
const errors = [];
function watch(page, tag) {
  page.on('pageerror', (e) => errors.push(tag + ' pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(tag + ' console: ' + m.text()); });
}
const still = '*{animation:none!important;transition:none!important}';
async function signIn(ctx, email = 'ivie@demo.nfelia') {
  const page = await ctx.newPage();
  watch(page, email);
  await page.goto(BASE + '/login');
  await page.fill('#email', email); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}
const money = (n) => '₦' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });

/** Layout rules for the page as it stands, outside the business's own document. */
function audit(page) {
  return page.evaluate(() => {
    const doc = '#invoice-document, #receipt-document, #quotation-document, dialog:not([open]), .nf-tabbar, .nf-side, .nf-appbar';
    const seen = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const small = [], caps = [], tiny = [];
    document.querySelectorAll('main *').forEach((el) => {
      if (el.closest(doc) || !seen(el)) return;
      const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
      const s = getComputedStyle(el);
      if (own && parseFloat(s.fontSize) < 12) small.push(el.tagName + ':' + el.textContent.trim().slice(0, 24) + ' ' + s.fontSize);
      if (own && s.textTransform === 'uppercase') caps.push(el.textContent.trim().slice(0, 24));
      if (el.matches('a, button, select, input:not([type=hidden]), [role=button]')) {
        // A radio or checkbox is tapped through its label.
        const r = (el.matches('input[type=radio], input[type=checkbox]') && el.closest('label') ? el.closest('label') : el).getBoundingClientRect();
        // A link inside running text is the exception the rules allow.
        const inline = el.tagName === 'A' && getComputedStyle(el).display === 'inline' && el.parentElement.closest('p');
        if (!inline && r.height < 43.5) tiny.push((el.textContent.trim() || el.getAttribute('aria-label') || el.tagName).slice(0, 24) + ' ' + Math.round(r.height));
      }
    });
    return { overflow: document.documentElement.scrollWidth - window.innerWidth, width: window.innerWidth, small, caps, tiny };
  });
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const snapshot = path.join(OUT, 'db_before.sql');
  execSync(`mysqldump --single-transaction --routines --triggers smartqaq_nfelia_core > '${snapshot}'`);
  const browser = await chromium.launch({ executablePath: CHROME }).catch(() => chromium.launch());

  try {
    // Test people and data: a staff member, and a client whose name is markup.
    const hash = execSync(`php -r "echo password_hash('DemoPass1', PASSWORD_BCRYPT);"`).toString();
    q(`DELETE FROM users WHERE id = 3`);
    q(`INSERT INTO users (id, business_id, role, full_name, email, password_hash, is_email_verified, is_active) VALUES (3, 1, 'staff', 'Tunde Bello', 'tunde@demo.nfelia', '${hash}', 1, 1)`);
    q(`INSERT INTO customers (business_id, full_name, phone, is_active) VALUES (1, 'Zed <img src=x onerror="window.__xss=1">', '08000000001', 1)`);
    q(`UPDATE subscriptions SET status = 'active', trial_ends_at = NULL WHERE business_id IN (1, 2)`);

    // ---------- Every screen, phone and computer ----------
    console.log('Layout at 375 and 1280');
    const pages = ['/dashboard', '/invoices', '/receipts', '/quotations', '/invoices/view/1', '/invoices/view/2', '/invoices/view/23',
                   `/receipts/view/${q("SELECT MIN(id) FROM receipts WHERE business_id = 1 AND invoice_id IS NOT NULL")}`, '/customers', '/products', '/products?stock=restock'];
    for (const [tag, opt] of [['phone', PHONE], ['computer', DESK]]) {
      const ctx = await browser.newContext(opt);
      const page = await signIn(ctx);
      for (const url of pages) {
        await page.goto(BASE + url);
        await page.addStyleTag({ content: still });
        const a = await audit(page);
        const label = `${tag} ${url}`;
        ok(a.overflow === 0 && a.width === opt.viewport.width, `${label}: no sideways scroll (${a.width}px wide)`);
        ok(a.small.length === 0, `${label}: no text under 12px ${a.small.slice(0, 3).join(' | ')}`);
        ok(a.caps.length === 0, `${label}: no all-caps labels ${a.caps.slice(0, 3).join(' | ')}`);
        ok(a.tiny.length === 0, `${label}: every control at least 44px tall ${a.tiny.slice(0, 4).join(' | ')}`);
        await page.screenshot({ path: path.join(OUT, `${tag}_${url.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '')}.png`), fullPage: true });
      }
      await ctx.close();
    }

    const ctx = await browser.newContext(PHONE);
    // Copy link puts the link on the clipboard: stand in for it.
    await ctx.addInitScript(() => {
      window.__copied = null;
      Object.defineProperty(navigator, 'clipboard', { value: { writeText: async (t) => { window.__copied = t; } } });
    });
    const page = await signIn(ctx);

    // ---------- Home ----------
    console.log('Home');
    await page.goto(BASE + '/dashboard');
    const month = q("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    const moneyIn = Number(q(`SELECT (SELECT COALESCE(SUM(amount),0) FROM payments WHERE business_id = 1 AND payment_date >= '${month}') + (SELECT COALESCE(SUM(amount),0) FROM receipts WHERE business_id = 1 AND invoice_id IS NULL AND issue_date >= '${month}')`));
    const card = await page.locator('.nf-money').innerText();
    ok(card.includes(money(moneyIn)), `money in this month counts invoice payments and sales paid on the spot: ${money(moneyIn)}`);
    const owed = Number(q("SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE business_id = 1 AND status IN ('sent','partially_paid','overdue')"));
    ok(card.includes(money(owed)), `and what is still owed: ${money(owed)}`);
    const needs = await page.locator('[aria-labelledby="nf-attn-h"]').innerText();
    const lowCount = Number(q("SELECT COUNT(*) FROM v_low_stock_products WHERE business_id = 1"));
    ok(needs.includes(`${lowCount} item${lowCount === 1 ? ' is' : 's are'} low or out`), `Needs you counts every low item (${lowCount}), not only the first five`);
    const overdueCount = Number(q("SELECT COUNT(*) FROM invoices WHERE business_id = 1 AND status = 'overdue'"));
    ok(overdueCount === 0 || needs.includes(`${overdueCount} invoice${overdueCount === 1 ? ' is' : 's are'} overdue`), 'and overdue invoices');
    ok(await page.locator('.nf-today .nf-item').count() > 0, 'Today shows the plan from Tasks');
    const hour = Number(q("SELECT HOUR(NOW())"));
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    ok((await page.locator('.nf-hello h1').innerText()).startsWith(greet), `greeting on Lagos time: "${greet}"`);
    // Search: results are text, never markup.
    await page.fill('[data-omni] input', 'Zed');
    await page.waitForSelector('#nf-omni .nf-item', { timeout: 5000 }).catch(() => {});
    const hit = await page.locator('#nf-omni .nf-item').first().innerText().catch(() => '');
    ok(hit.includes('Zed <img src=x onerror="window.__xss=1">'), 'search shows a client named with markup as plain text');
    ok(await page.evaluate(() => window.__xss === undefined) && await page.locator('#nf-omni img').count() === 0, 'and never runs it');
    await page.fill('[data-omni] input', 'Body Wave');
    await page.waitForFunction(() => /Stock/.test(document.querySelector('#nf-omni').innerText), null, { timeout: 5000 }).catch(() => {});
    ok(/₦65,000 · \d+/.test(await page.locator('#nf-omni').innerText()), 'stock results show price and how many are left');
    await page.keyboard.press('Escape');
    ok(await page.locator('#nf-omni').isHidden(), 'Escape closes the results');

    // ---------- Sales ----------
    console.log('Sales');
    for (const [url, name] of [['/invoices', 'Invoices'], ['/receipts', 'Receipts'], ['/quotations', 'Quotes']]) {
      await page.goto(BASE + url);
      ok(await page.locator('.nf-tab[aria-current="page"]').innerText() === 'Sales' && await page.locator('[data-back]').count() === 0, `${name}: the Sales tab is lit, no Back button`);
      ok(await page.locator('.nf-segs a[aria-current="page"]').innerText() === name, `${name}: its segment is current`);
    }
    await page.goto(BASE + '/invoices');
    await page.click('.nf-chips a:has-text("Unpaid")');
    await page.waitForURL(/status=unpaid/);
    const unpaid = Number(q("SELECT COUNT(*) FROM invoices WHERE business_id = 1 AND status IN ('sent','partially_paid','overdue')"));
    const shown = await page.locator('.nf-items .nf-item').count();
    ok(shown === Math.min(unpaid, 20) && /Unpaid/.test(await page.locator('.nf-chips a[aria-current="true"]').innerText()), `Unpaid: ${shown} invoices, as many as are unpaid (${unpaid})`);
    await page.fill('.nf-search input', 'Chioma');
    await Promise.all([page.waitForNavigation(), page.press('.nf-search input', 'Enter')]);
    ok(/status=unpaid/.test(page.url()) && /q=Chioma/.test(page.url()), 'searching keeps the filter');
    const names = await page.locator('.nf-item-main b').allInnerTexts();
    ok(names.length > 0 && names.every((n) => n === 'Chioma Obi'), 'and lists only that client: ' + names.join(', '));
    await page.selectOption('select[name=sort]', 'total_desc');
    await page.waitForURL(/sort=total_desc/);
    ok(/q=Chioma/.test(page.url()) && /status=unpaid/.test(page.url()), 'sorting keeps search and filter');
    await page.goto(BASE + '/invoices?q=nobody-by-this-name');
    ok(/No invoices match/.test(await page.locator('.nf-empty h2').innerText()) && await page.locator('.nf-empty a[href$="/invoices"]').count() === 1, 'no match: says so, with a way back');
    await page.goto(BASE + '/receipts?type=standalone');
    const spot = await page.locator('.nf-items .nf-item').count();
    ok(spot === Math.min(Number(q("SELECT COUNT(*) FROM receipts WHERE business_id = 1 AND invoice_id IS NULL")), 20), 'Receipts: Paid on the spot lists only those');
    await page.goto(BASE + '/quotations?status=waiting');
    ok(await page.locator('.nf-items .nf-item').count() === Number(q("SELECT COUNT(*) FROM quotations WHERE business_id = 1 AND status IN ('sent','in_negotiation','pending_signature')")), 'Quotes: Waiting lists the quotes still out with customers');
    await page.goto(BASE + '/more');
    const tiles = await page.locator('.nf-tile').allInnerTexts();
    ok(!tiles.some((t) => /Receipts|Quotations/.test(t)), 'More no longer repeats Receipts and Quotes (they live in Sales)');

    // ---------- Invoice page ----------
    console.log('Invoice page');
    await page.goto(BASE + '/invoices/view/1');
    const rows = await page.locator('#invoice-document tbody tr').allInnerTexts();
    ok(rows.length === 1 && rows[0].includes('Deep Wave 26'), "the document lists the invoice's own items: " + rows.map((r) => r.replace(/\s+/g, ' ').slice(0, 40)).join(' | '));
    await page.click('[data-copy]');
    await page.waitForFunction(() => window.__copied !== null, null, { timeout: 3000 }).catch(() => {});
    ok(/\/doc\/[a-f0-9]{64}$/.test(await page.evaluate(() => window.__copied)) && /Link copied/.test(await page.locator('.nf-toast').innerText().catch(() => '')), 'Copy link copies the customer link and says so');
    await page.click('[data-open="nf-inv-more"]');
    await page.waitForSelector('#nf-inv-more[open]');
    const dl = page.locator('#nf-inv-more a[download]');
    ok(/\?format=pdf$/.test(await dl.getAttribute('href')) && (await dl.getAttribute('download')) === 'IVL-INV-131.pdf', 'More: Download PDF is the server PDF, named for the invoice');
    ok(await page.locator('#nf-inv-more button:has-text("Cancel the invoice")').count() === 1 && await page.locator('#nf-inv-more :text("Delete the draft")').count() === 0, 'a sent invoice can be cancelled, not deleted');
    await page.click('#nf-inv-more [data-close]');
    await page.waitForFunction(() => !document.getElementById('nf-inv-more').open, null, { timeout: 3000 }).catch(() => {});
    const before = Number(q("SELECT balance_due FROM invoices WHERE id = 1"));
    await page.click('[data-open="nf-pay"]');
    await page.waitForSelector('#nf-pay[open]');
    await page.screenshot({ path: path.join(OUT, 'pay_sheet.png') });
    await page.fill('#nf-pay input[type=number]', String(before + 1));
    await page.click('#nf-pay button[type=submit]');
    ok(/more than the/.test(await page.locator('#nf-pay .nf-err').innerText()), 'more than the balance: refused, in words');
    await page.fill('#nf-pay input[type=number]', '5000');
    await page.selectOption('#nf-pay select', 'pos');
    await Promise.all([page.waitForNavigation(), page.click('#nf-pay button[type=submit]')]);
    ok(Number(q("SELECT balance_due FROM invoices WHERE id = 1")) === before - 5000 && q("SELECT method FROM payments WHERE invoice_id = 1 ORDER BY id DESC LIMIT 1") === 'pos', 'Record payment: saved, POS');
    ok(/₦5,000 recorded/.test(await page.locator('.nf-toast').innerText().catch(() => '')), 'and the page says so after reloading');
    await page.goto(BASE + '/invoices/view/23');
    ok(await page.locator('.nf-doc button:has-text("Mark as sent")').count() === 1 && await page.locator('[data-open="nf-pay"]').count() === 0, 'a draft: Mark as sent, no payment');
    await page.click('[data-open="nf-inv-more"]');
    ok(await page.locator('#nf-inv-more :text("Delete the draft")').count() === 1 && await page.locator('#nf-inv-more :text("Edit the invoice")').count() === 1, 'and it can be edited or deleted');
    await page.goto(BASE + '/invoices/edit/23');
    const lines = await page.evaluate(() => { const el = document.querySelector('[x-data*="lineItems"], form[x-data]'); return el && window.Alpine ? JSON.stringify(Alpine.$data(el).lineItems || []) : ''; });
    ok(/Bone Straight 22/.test(lines), 'the edit form loads the saved items: ' + lines.slice(0, 80));

    // ---------- Receipt page ----------
    console.log('Receipt page');
    const rec = q("SELECT CONCAT_WS('|', r.id, r.doc_number, r.amount, i.doc_number) FROM receipts r JOIN invoices i ON i.id = r.invoice_id WHERE r.business_id = 1 ORDER BY r.id LIMIT 1").split('|');
    await page.goto(BASE + '/receipts/view/' + rec[0]);
    ok((await page.locator('.nf-doc-amt').innerText()) === money(rec[2]), 'the amount, in green: ' + money(rec[2]));
    ok((await page.locator('.nf-doc').innerText()).includes('For ' + rec[3]), 'and the invoice it paid');
    await page.click('[data-open="nf-rec-more"]');
    ok(await page.locator('#nf-rec-more button:has-text("Delete the receipt")').count() === 1, 'More: delete, with a named confirmation');

    // ---------- Clients and Stock ----------
    console.log('Clients and Stock');
    await page.goto(BASE + '/customers?filter=owing');
    const owing = await page.locator('.nf-items .nf-item').count();
    ok(owing === Number(q("SELECT COUNT(*) FROM customers WHERE business_id = 1 AND deleted_at IS NULL AND outstanding_balance > 0")), `Owing lists the ${owing} clients who owe`);
    ok(await page.locator('.nf-items .nf-item .is-owed').count() >= owing, 'each with what they owe');
    await page.goto(BASE + '/customers?q=Zed');
    ok((await page.locator('.nf-item-main b').first().innerText()).includes('<img'), 'a client named with markup shows as text in Clients too');
    await page.goto(BASE + '/products?stock=restock');
    const restock = await page.locator('.nf-items .nf-item').count();
    ok(restock === lowCount, `Low or out: ${restock} items, the same as Home counts`);
    ok(await page.locator('.nf-items .nf-tag.is-warn, .nf-items .nf-tag.is-owed').count() === restock, 'each tagged low or out');
    await ctx.close();

    // ---------- Staff ----------
    console.log('Staff');
    const staff = await browser.newContext(PHONE);
    const sp = await signIn(staff, 'tunde@demo.nfelia');
    await sp.goto(BASE + '/dashboard');
    ok(await sp.locator('.nf-money').count() === 0, 'staff Home: no money');
    ok(!/overdue/.test(await sp.locator('[aria-labelledby="nf-attn-h"]').innerText()), 'and no overdue money in Needs you');
    await staff.close();
  } finally {
    await browser.close();
    execSync(`mysql smartqaq_nfelia_core < '${snapshot}'`);
  }

  if (errors.length) { console.log('Page errors:'); errors.forEach((e) => console.log('  ' + e)); }
  ok(errors.length === 0, 'no script errors on any page');
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
