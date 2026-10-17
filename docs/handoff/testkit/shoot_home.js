const { chromium } = require('playwright');
const S = process.argv[2];
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  for (const [name, vp, path] of [['m', { width: 375, height: 844 }, '/'], ['d', { width: 1280, height: 900 }, '/']]) {
    const page = await browser.newPage({ viewport: vp, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    await page.goto('http://127.0.0.1:8099' + path, { waitUntil: 'networkidle' });
    for (let y = 0; y < 14000; y += 600) { await page.evaluate((yy) => window.scrollTo(0, yy), y); await page.waitForTimeout(60); }
    await page.evaluate(() => window.scrollTo(0, 0)); await page.waitForTimeout(500);
    await page.screenshot({ path: `${S}/home_${name}.png`, fullPage: true });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    console.log(name, 'horizontal overflow px:', overflow, 'height:', await page.evaluate(() => document.body.scrollHeight));
    await page.close();
  }
  await browser.close();
})();
