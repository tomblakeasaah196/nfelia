// The landing page in Chromium: the "Sound familiar?" cards and their sheets
// (tap, address, Back, ✕, Escape, backdrop, focus, shared links, no
// JavaScript), the website's address typing in, and the "Like that
// website?" greeting for visitors from a shop's footer. Needs the local
// server with APP_URL=http://localhost:8000 and landing_seed.php (for the
// ivieluxe shop). Prints PASS/FAIL lines; screenshots go to <out dir>.
//   NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/landing_test.js <out dir>
const { chromium } = require('playwright');
const OUT = process.argv[2];
const BASE = 'http://localhost:8000/';
const results = [];
const check = (label, ok, detail = '') => { results.push(!!ok); console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok ? '' : '  -> ' + String(detail).slice(0, 200))); };
const openId = (p) => p.evaluate(() => { const d = document.querySelector('dialog.see-sheet[open]'); return d ? d.id : null; });

(async () => {
  const browser = await chromium.launch({ proxy: { server: process.env.HTTPS_PROXY, bypass: '<-loopback>,localhost,*.localhost,127.0.0.1' } });
  const errs = [];
  for (const [name, vp, mobile] of [['m', { width: 375, height: 812 }, true], ['d', { width: 1280, height: 900 }, false]]) {
    const ctx = await browser.newContext({ viewport: vp, isMobile: mobile, hasTouch: mobile, reducedMotion: 'reduce' });
    const p = await ctx.newPage();
    p.on('pageerror', (e) => errs.push(name + ': ' + e.message));
    p.on('console', (m) => { if (m.type() === 'error') errs.push(name + ' console: ' + m.text()); });
    await p.goto(BASE, { waitUntil: 'networkidle' });

    // Tap a card: its sheet opens, the address carries its name, both screens load.
    const card = p.locator('[data-see="bookings"]');
    await card.scrollIntoViewIfNeeded();
    const scrollBefore = await p.evaluate(() => scrollY);
    await card.click();
    await p.waitForTimeout(400);
    check(`${name}: tapping a card opens its sheet`, await openId(p) === 'bookings', await openId(p));
    check(`${name}: the address becomes #bookings`, p.url().endsWith('#bookings'), p.url());
    const imgs = await p.locator('#bookings img').evaluateAll((els) => els.map((i) => i.complete && i.naturalWidth));
    check(`${name}: both screens load`, imgs.length === 2 && imgs.every((w) => w === 540), imgs);
    check(`${name}: the page behind doesn't scroll away`, Math.abs(await p.evaluate(() => scrollY) - scrollBefore) < 2);
    const box = await p.locator('#bookings').boundingBox();
    check(`${name}: the sheet fits the screen width`, box.x >= 0 && box.x + box.width <= vp.width + 0.5, JSON.stringify(box));
    if (mobile) check('m: on a phone it sits at the bottom', Math.abs(box.y + box.height - vp.height) < 2, JSON.stringify(box));
    await p.screenshot({ path: `${OUT}/sheet_${name}.png` });

    // Back closes it, like an app, and focus returns to the card.
    await p.goBack();
    await p.waitForTimeout(300);
    check(`${name}: Back closes the sheet`, await openId(p) === null, await openId(p));
    check(`${name}: Back leaves the address without the hash`, !p.url().includes('#'), p.url());
    check(`${name}: focus returns to the card`, await p.evaluate(() => document.activeElement && document.activeElement.dataset.see) === 'bookings');

    // ✕ closes it and takes the hash off; Escape too; and the backdrop.
    await p.locator('[data-see="tracking"]').click(); await p.waitForTimeout(300);
    await p.locator('#tracking [data-see-close]').click(); await p.waitForTimeout(300);
    check(`${name}: ✕ closes the sheet`, await openId(p) === null);
    check(`${name}: ✕ takes the hash off`, !p.url().includes('#'), p.url());
    await p.locator('[data-see="owed"]').click(); await p.waitForTimeout(300);
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    check(`${name}: Escape closes the sheet`, await openId(p) === null && !p.url().includes('#'), p.url());
    await p.locator('[data-see="stock"]').click(); await p.waitForTimeout(300);
    await p.mouse.click(5, 5); await p.waitForTimeout(300);
    check(`${name}: tapping outside closes the sheet`, await openId(p) === null && !p.url().includes('#'), p.url());
    check(`${name}: still on the landing page after closing`, p.url() === BASE, p.url());

    // Keyboard: Tab never reaches the page behind the sheet.
    await p.locator('[data-see="invoices"]').focus(); await p.keyboard.press('Enter'); await p.waitForTimeout(300);
    let inside = true;
    for (let i = 0; i < 6; i++) { await p.keyboard.press('Tab'); inside = inside && await p.evaluate(() => { const a = document.activeElement; return !a || a === document.body || !!a.closest('#invoices'); }); }
    check(`${name}: Tab never reaches the page behind the sheet`, inside);
    await p.keyboard.press('Escape'); await p.waitForTimeout(200);

    // A shared link opens the sheet; closing it keeps you on the page.
    const p2 = await ctx.newPage();
    await p2.goto(BASE + '#price-list', { waitUntil: 'networkidle' }); await p2.waitForTimeout(300);
    check(`${name}: a shared link opens that sheet`, await openId(p2) === 'price-list');
    await p2.keyboard.press('Escape'); await p2.waitForTimeout(300);
    check(`${name}: closing a shared link stays on the page`, await openId(p2) === null && p2.url() === BASE, p2.url());
    await p2.close();

    // Every card has a sheet with two screens and three lines.
    const shape = await p.evaluate(() => [...document.querySelectorAll('[data-see]')].map((a) => {
      const d = document.getElementById(a.dataset.see);
      return d ? d.querySelectorAll('img').length + '/' + d.querySelectorAll('.ticks li').length : 'missing';
    }));
    check(`${name}: 10 cards, each with 2 screens and 3 lines`, shape.length === 10 && shape.every((x) => x === '2/3'), shape);

    // Tap targets on the cards and the sheet's close.
    const small = await p.evaluate(() => [...document.querySelectorAll('.see-card, .sheet-close')].filter((e) => e.offsetParent && (e.getBoundingClientRect().height < 44 || e.getBoundingClientRect().width < 44)).length);
    check(`${name}: cards and close buttons are at least 44px`, small === 0, small);
    await ctx.close();
  }

  // The website's address types in when the phone comes into view.
  const ctxM = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await ctxM.newPage();
  await pm.goto(BASE, { waitUntil: 'networkidle' });
  const url0 = await pm.locator('[data-type-url]').textContent();
  await pm.locator('[data-site-visual]').scrollIntoViewIfNeeded();
  await pm.waitForTimeout(250);
  const urlMid = await pm.locator('[data-type-url]').textContent();
  await pm.waitForTimeout(1500);
  const urlEnd = await pm.locator('[data-type-url]').textContent();
  check('the address types in once in view', url0 === 'ivieluxe.nfelia.com' && urlMid.length < urlEnd.length && urlEnd === 'ivieluxe.nfelia.com', [url0, urlMid, urlEnd]);
  check('the tags show after typing', await pm.locator('[data-site-visual].is-typed').count() === 1);
  check('screen readers get the whole address', await pm.locator('[data-type-url]').getAttribute('aria-label') === 'ivieluxe.nfelia.com');
  await pm.screenshot({ path: `${OUT}/site_typed_m.png` });
  await ctxM.close();

  // Reduced motion: the address is whole from the start.
  const ctxR = await browser.newContext({ viewport: { width: 375, height: 812 }, reducedMotion: 'reduce' });
  const pr = await ctxR.newPage(); await pr.goto(BASE, { waitUntil: 'networkidle' });
  await pr.locator('[data-site-visual]').scrollIntoViewIfNeeded(); await pr.waitForTimeout(100);
  check('reduced motion: the address is whole and the tags show', await pr.locator('[data-type-url]').textContent() === 'ivieluxe.nfelia.com' && await pr.locator('[data-site-visual].is-typed').count() === 1);
  await ctxR.close();

  // Footer greeting: real shop only.
  const ctxG = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pg = await ctxG.newPage();
  await pg.goto(BASE + '?from=ivieluxe', { waitUntil: 'networkidle' });
  const greet = await pg.locator('.from-shop').textContent().catch(() => '');
  check('?from=ivieluxe greets with the shop\'s name', /Like Ivie Luxe Hair’s website\?/.test(greet), greet);
  await pg.screenshot({ path: `${OUT}/greeting_m.png` });
  for (const bad of ['nope-shop', '<script>alert(1)</script>', 'ab', '']) {
    await pg.goto(BASE + '?from=' + encodeURIComponent(bad), { waitUntil: 'networkidle' });
    check(`?from=${JSON.stringify(bad)} shows no greeting`, await pg.locator('.from-shop').count() === 0);
  }
  await ctxG.close();

  // The shop's footer link carries its name.
  const ctxS = await browser.newContext();
  const ps = await ctxS.newPage();
  await ps.goto('http://ivieluxe.localhost:8000/', { waitUntil: 'networkidle' });
  const href = await ps.locator('a:has-text("Shop by Nfelia")').getAttribute('href');
  check('the shop\'s "Shop by Nfelia" link carries ?from=ivieluxe', href === 'http://localhost:8000/?from=ivieluxe', href);
  await ctxS.close();

  // No JavaScript: a shared link still shows the sheet, and close goes back to the cards.
  const ctxN = await browser.newContext({ viewport: { width: 375, height: 812 }, javaScriptEnabled: false });
  const pn = await ctxN.newPage();
  await pn.goto(BASE + '#delivery', { waitUntil: 'networkidle' });
  check('no JavaScript: #delivery shows its sheet', await pn.locator('#delivery').isVisible());
  await pn.locator('#delivery [data-see-close]').click(); await pn.waitForTimeout(200);
  check('no JavaScript: close hides it', !(await pn.locator('#delivery').isVisible()) && pn.url().endsWith('#see'), pn.url());
  check('no JavaScript: other sheets stay hidden', await pn.locator('dialog.see-sheet:visible').count() === 0);
  await ctxN.close();

  check('no script errors', errs.length === 0, errs.join(' | '));
  await browser.close();
  const passed = results.filter(Boolean).length;
  console.log(`\n${passed}/${results.length} passed`);
  process.exit(passed === results.length ? 0 : 1);
})();
