// Screenshot saved email HTML at phone and desktop width: node email_shot.js page.html out-prefix
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const [file, out] = process.argv.slice(2);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  for (const [tag, width, scale] of [['m', 375, 2], ['d', 760, 1]]) {
    const page = await browser.newPage({ viewport: { width, height: 800 }, deviceScaleFactor: scale });
    await page.goto('file://' + path.resolve(file));
    await page.screenshot({ path: `${out}_${tag}.png`, fullPage: true });
    const r = await page.evaluate(() => {
      const small = [], weights = new Set();
      document.querySelectorAll('body *').forEach((el) => {
        if (![...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim())) return;
        const cs = getComputedStyle(el);
        if (parseFloat(cs.fontSize) < 12) small.push(el.tagName + ' ' + cs.fontSize + ' "' + el.textContent.trim().slice(0, 30) + '"');
        weights.add(cs.fontWeight);
      });
      return { overflow: document.documentElement.scrollWidth - innerWidth, small, weights: [...weights].sort() };
    });
    console.log(tag, width + 'px', 'overflow:', r.overflow, 'weights:', r.weights.join('/'), 'under 12px:', r.small.length ? r.small.join(' | ') : 'none');
    await page.close();
  }
  await browser.close();
})();
