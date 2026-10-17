const { chromium } = require('playwright');
let pass = 0, fail = 0;
const check = (n, ok, extra = '') => { console.log((ok ? '  PASS  ' : '  FAIL  ') + n + (extra ? '  ' + extra : '')); ok ? pass++ : fail++; };
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  for (const url of ['/', '/login', '/register']) {
    for (const w of [375, 1280]) {
      const p = await b.newPage({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' });
      await p.goto('http://127.0.0.1:8099' + url, { waitUntil: 'networkidle' });
      const r = await p.evaluate(() => {
        const weights = new Set(), small = [], tight = [], targets = [];
        document.querySelectorAll('body *').forEach((el) => {
          const cs = getComputedStyle(el);
          if (cs.display === 'none' || cs.visibility === 'hidden') return;
          const hasText = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
          if (hasText) {
            if (!el.closest('.phone, .laptop, .ledger')) weights.add(cs.fontWeight);
            const fs = parseFloat(cs.fontSize); if (fs < 12) small.push(el.tagName + ' ' + fs);
            const lh = parseFloat(cs.lineHeight); if (!isNaN(lh) && lh / fs < 1.095) tight.push(el.tagName + '.' + el.className + ' ' + (lh / fs).toFixed(2));
          }
          if (el.matches('a, button, summary, input:not([type=hidden]), textarea') && !el.closest('p, [hidden], label.check')) {
            const rc = el.getBoundingClientRect();
            if (rc.width && rc.height && (rc.height < 44 || rc.width < 24) && !el.matches('.skip-link')) targets.push(el.tagName + ' "' + (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 24) + '" ' + Math.round(rc.width) + 'x' + Math.round(rc.height));
          }
        });
        return { weights: [...weights].sort(), small, tight: tight.slice(0, 5), targets: targets.slice(0, 8),
                 overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
      });
      const tag = `${url} @${w}`;
      check(`${tag} no sideways overflow`, r.overflow <= 0, String(r.overflow));
      check(`${tag} ≤3 weights, none above 700`, r.weights.length <= 3 && r.weights.every((x) => +x <= 700), r.weights.join('/'));
      check(`${tag} no text under 12px`, r.small.length === 0, r.small.slice(0, 3).join(', '));
      check(`${tag} line-height ≥ 1.1`, r.tight.length === 0, r.tight.join(', '));
      check(`${tag} tap targets ≥ 44px tall`, r.targets.length === 0, r.targets.join(' | '));
      await p.close();
    }
  }
  // Blur-first validation on sign-up.
  const p = await b.newPage({ viewport: { width: 390, height: 900 } });
  await p.goto('http://127.0.0.1:8099/register', { waitUntil: 'networkidle' });
  await p.focus('#full_name'); await p.keyboard.type('Ada'); await p.focus('#business_name');
  check('no error while typing / after a valid blur', await p.locator('#full_name-error').isHidden().catch(() => true));
  await p.focus('#email'); await p.keyboard.type('ada@'); await p.focus('#password');
  check('bad email shows an error on blur', await p.locator('#email-error').isVisible());
  check('error is linked to the field', (await p.getAttribute('#email', 'aria-describedby') || '').includes('email-error') && (await p.getAttribute('#email', 'aria-invalid')) === 'true');
  await p.focus('#email'); await p.keyboard.type('x');
  check('typing a fix clears the error at once', await p.locator('#email-error').isHidden());
  await p.fill('#email', 'ada@shop.ng');
  await p.fill('#password', 'Pass12345'); await p.fill('#password_confirm', 'Pass1234X'); await p.focus('#full_name');
  check('different second password is caught', await p.locator('#password_confirm-error').isVisible());
  await p.fill('#business_name', '');
  await p.click('button[type=submit]');
  check('submit stays clickable and shows every problem', (await p.locator('#business_name-error').isVisible()) && p.url().endsWith('/register'));
  check('focus moves to the first problem', await p.evaluate(() => document.activeElement.id === 'business_name'));
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  await b.close();
})();
