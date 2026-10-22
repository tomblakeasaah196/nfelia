// The still parts of a feature video, drawn by the browser in Nfelia's own
// colours (wine and gold) and font: the background with the phone, the
// phone screen's rounded mask, and one caption card per scene.
//
//   node stage.js <scenes.json> <work folder>
//
// Writes bg.png, mask.png, cap1.png ... and layout.json (where the screen
// goes), which compose.py reads.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const [scenesFile, OUT] = process.argv.slice(2);
const spec = JSON.parse(fs.readFileSync(scenesFile, 'utf8'));
const FONT = 'file://' + path.resolve(__dirname, '../../node_modules/@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-latin-wght-normal.woff2');
if (!fs.existsSync(FONT.slice(7))) throw new Error('Run npm ci first: the video uses Plus Jakarta Sans from node_modules.');
// A 9:16 frame; the screen keeps the recording's shape (1080 x 2340)
const L = { W: 1080, H: 1920, sx: 193, sy: 362, sw: 695, sh: 1506, bez: 16, r: 50 };
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

const head = `<style>
@font-face { font-family: 'Plus Jakarta Sans'; src: url('${FONT}') format('woff2'); font-weight: 200 800; }
html, body { margin: 0; padding: 0; }
body { width: ${L.W}px; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-font-smoothing: antialiased; }
</style>`;

const background = `<html><head>${head}<style>
body { height: ${L.H}px; background: radial-gradient(120% 70% at 50% 0%, #A3105E 0%, #8B004A 45%, #5C0031 100%); position: relative; overflow: hidden; }
.glow { position: absolute; left: 50%; top: ${L.sy + 300}px; width: 900px; height: 900px; margin-left: -450px; border-radius: 50%;
        background: radial-gradient(circle, rgba(182,159,94,.28), rgba(182,159,94,0) 65%); }
.phone { position: absolute; left: ${L.sx - L.bez}px; top: ${L.sy - L.bez}px; width: ${L.sw + L.bez * 2}px; height: ${L.sh + L.bez * 2}px;
         border-radius: ${L.r + L.bez}px; background: #1B0A11;
         box-shadow: 0 0 0 2px rgba(255,255,255,.14), 0 40px 90px rgba(20,0,10,.55), inset 0 0 0 2px rgba(255,255,255,.08); }
.screen { position: absolute; left: ${L.sx}px; top: ${L.sy}px; width: ${L.sw}px; height: ${L.sh}px; border-radius: ${L.r}px; background: #F2EFE7; }
</style></head><body><div class="glow"></div><div class="phone"></div><div class="screen"></div></body></html>`;

const mask = `<html><head><style>html,body{margin:0;background:#000} div{width:${L.sw}px;height:${L.sh}px;border-radius:${L.r}px;background:#fff}</style></head><body><div></div></body></html>`;

const n = spec.scenes.length;
const caption = (i) => `<html><head>${head}<style>
body { height: 400px; background: transparent; position: relative; color: #fff; }
.top { position: absolute; left: 72px; right: 72px; top: 64px; display: flex; align-items: center; justify-content: space-between; }
.kicker { font-size: 30px; font-weight: 700; color: #E9D7A2; letter-spacing: .01em; }
.dots { display: flex; gap: 10px; }
.dots i { display: block; width: 18px; height: 18px; border-radius: 9px; background: rgba(255,255,255,.28); }
.dots i.on { width: 46px; background: #E9D7A2; }
.dots i.done { background: rgba(233,215,162,.7); }
h1 { position: absolute; left: 72px; right: 72px; top: 128px; margin: 0; font-size: 66px; line-height: 1.08; font-weight: 800; letter-spacing: -.02em; }
p { position: absolute; left: 72px; right: 72px; top: 226px; margin: 0; font-size: 38px; line-height: 1.25; font-weight: 600; color: rgba(255,255,255,.86); }
.n { color: #E9D7A2; }
</style></head><body>
<div class="top"><span class="kicker">${esc(spec.title || 'New in Nfelia')}</span>${n > 1 ? `<span class="dots">${[...Array(n).keys()].map(k => `<i class="${k === i ? 'on' : k < i ? 'done' : ''}"></i>`).join('')}</span>` : ''}</div>
<h1>${n > 1 ? `<span class="n">${i + 1}.</span> ` : ''}${esc(spec.scenes[i].caption)}</h1>
<p>${esc(spec.scenes[i].sub || '')}</p>
</body></html>`;

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: L.W, height: L.H } });
  const shoot = async (html, file, size, transparent) => {
    await page.setViewportSize(size);
    await page.setContent(html, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: OUT + '/' + file, omitBackground: !!transparent });
  };
  await page.goto('file://' + path.resolve(OUT) + '/');   // so the font loads from file://
  await shoot(background, 'bg.png', { width: L.W, height: L.H });
  await shoot(mask, 'mask.png', { width: L.sw, height: L.sh });
  for (let i = 0; i < n; i++) {
    await shoot(caption(i), `cap${i + 1}.png`, { width: L.W, height: 400 }, true);
    // A caption too long for one line runs into the line under it
    const lines = await page.evaluate(() => Math.round(document.querySelector('h1').getBoundingClientRect().height / 71));
    if (lines > 1) console.warn(`[stage] caption ${i + 1} takes ${lines} lines: shorten "${spec.scenes[i].caption}"`);
  }
  fs.writeFileSync(OUT + '/layout.json', JSON.stringify(L));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
