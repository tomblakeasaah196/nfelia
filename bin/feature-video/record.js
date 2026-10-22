// Records a feature video's scenes in the running app, at phone size.
//
//   node record.js <scenes.json> '<ids json from the seed>' <work folder>
//
// Frames come from Chrome's screencast, each with its time, and every scene
// is marked where it starts and ends (timeline.json): compose.py cuts the
// video from those marks, so page loads never show. A gold circle shows each
// tap; a gold outline shows what changed.
//
// A scene: { open: "/orders/view/{order}" ({key} comes from the seed's ids),
//   before: [steps, not filmed], steps: [steps, filmed], caption, sub, say }
// A step: ["wait", ms] · ["tap", selector] · ["fill", selector, value]
//   · ["type", selector, text, ms between keys] · ["change", selector]
//   · ["spot", selector, padding] · ["scroll", selector, px from the top]
// Selectors are Playwright's: CSS, or button:has-text("Kept with you").
const { chromium } = require('playwright');
const fs = require('fs');
const [scenesFile, idsJson, OUT] = process.argv.slice(2);
const spec = JSON.parse(fs.readFileSync(scenesFile, 'utf8'));
const ids = JSON.parse(idsJson || '{}');
const BASE = (process.env.APP_URL || 'http://localhost:8099').replace(/\/$/, '');
const phone = spec.phone || { width: 384, height: 832, scale: 2.8125 };   // a Samsung Galaxy screen, 1080 px wide

// Drawn in a top-layer popover, so it shows above the app's own sheets
const FX = `
window.__fx = (() => {
  const css = '#nf-fx{position:fixed;inset:0;width:100vw;height:100vh;margin:0;padding:0;border:0;background:transparent;overflow:visible;pointer-events:none}'
    + '#nf-fx::backdrop{background:transparent}'
    + '.fx-ring{position:fixed;width:58px;height:58px;margin:-29px 0 0 -29px;border-radius:50%;border:4px solid #F2B705;background:rgba(242,183,5,.22);box-shadow:0 0 0 3px rgba(255,255,255,.9),0 6px 18px rgba(27,10,17,.25);animation:fxin .26s cubic-bezier(.2,.8,.2,1) both}'
    + '.fx-ring.press{animation:fxpress .3s ease-out both}'
    + '.fx-ring.gone{animation:fxout .25s ease-in both}'
    + '.fx-spot{position:fixed;border:4px solid #F2B705;border-radius:18px;box-shadow:0 0 0 3px rgba(255,255,255,.85),0 0 22px rgba(242,183,5,.75);animation:fxspot 1.1s ease-in-out infinite alternate, fxin .3s ease-out both}'
    + '@keyframes fxin{from{opacity:0;transform:scale(.4)}to{opacity:1;transform:scale(1)}}'
    + '@keyframes fxpress{0%{transform:scale(1)}40%{transform:scale(.78);background:rgba(242,183,5,.55)}100%{transform:scale(1.25);opacity:.85}}'
    + '@keyframes fxout{to{opacity:0;transform:scale(1.5)}}'
    + '@keyframes fxspot{from{box-shadow:0 0 0 3px rgba(255,255,255,.85),0 0 10px rgba(242,183,5,.5)}to{box-shadow:0 0 0 3px rgba(255,255,255,.85),0 0 28px rgba(242,183,5,.95)}}';
  function layer() {
    let l = document.getElementById('nf-fx');
    if (!l) {
      const s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);
      l = document.createElement('div'); l.id = 'nf-fx'; l.setAttribute('popover', 'manual'); document.body.appendChild(l);
    }
    try { l.hidePopover(); } catch (e) {}
    l.showPopover();   // back on top of any sheet that opened since
    return l;
  }
  return {
    ring(x, y) { const d = document.createElement('div'); d.className = 'fx-ring'; d.style.left = x + 'px'; d.style.top = y + 'px'; layer().appendChild(d); },
    press() { document.querySelectorAll('.fx-ring').forEach(d => d.classList.add('press')); },
    unring() { document.querySelectorAll('.fx-ring').forEach(d => { d.classList.add('gone'); setTimeout(() => d.remove(), 260); }); },
    spot(x, y, w, h) {
      const d = document.createElement('div'); d.className = 'fx-spot';
      Object.assign(d.style, { left: x + 'px', top: y + 'px', width: w + 'px', height: h + 'px' });
      layer().appendChild(d);
    },
    clear() { const l = document.getElementById('nf-fx'); if (l) l.innerHTML = ''; },
  };
})();`;

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: phone.width, height: phone.height }, deviceScaleFactor: phone.scale, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
  });
  await ctx.addInitScript(FX);
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));

  await page.goto(BASE + '/login');
  await page.fill('#email', spec.login?.email || 'ada@adashair.test');
  await page.fill('#password', spec.login?.password || 'TestPass1');
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);

  const cdp = await ctx.newCDPSession(page);
  const frames = [];
  cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
    frames.push({ ts: metadata.timestamp, data });
    try { await cdp.send('Page.screencastFrameAck', { sessionId }); } catch (e) {}
  });
  const marks = [];
  const mark = (name) => marks.push({ name, ts: Date.now() / 1000 });
  const wait = (ms) => page.waitForTimeout(ms);
  const fx = (fn, ...args) => page.evaluate(([f, a]) => window.__fx[f](...a), [fn, args]);
  const box = async (sel) => {
    const el = page.locator(sel).first();
    await el.scrollIntoViewIfNeeded();
    const b = await el.boundingBox();
    if (!b) throw new Error('not on screen: ' + sel);
    return b;
  };
  const run = {
    wait: (ms) => wait(ms),
    // A tap as a person makes it: the circle lands, presses, the tap happens
    tap: async (sel) => {
      const b = await box(sel);
      await fx('ring', b.x + b.width / 2, b.y + b.height / 2); await wait(420);
      await fx('press'); await wait(140);
      await page.locator(sel).first().tap();
      await wait(120); await fx('unring');
    },
    fill: (sel, value) => page.fill(sel, value),
    type: (sel, text, ms) => page.locator(sel).first().type(text, { delay: ms ?? 70 }),
    change: (sel) => page.dispatchEvent(sel, 'change'),
    spot: async (sel, pad) => {
      const b = await box(sel), p = pad ?? 6;
      await fx('spot', b.x - p, b.y - p, b.width + p * 2, b.height + p * 2);
    },
    scroll: (sel, top) => page.locator(sel).first().evaluate((el, t) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - t), top ?? 120),
  };
  const steps = async (list) => {
    for (const [name, ...args] of list || []) {
      if (!run[name]) throw new Error('unknown step ' + name);
      await run[name](...args);
    }
  };

  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: 1080, maxHeight: 2400, everyNthFrame: 1 });
  for (const [i, scene] of spec.scenes.entries()) {
    const path = scene.open.replace(/\{(\w+)\}/g, (_, k) => {
      if (!(k in ids)) throw new Error(`the seed gave no "${k}" for ${scene.open}`);
      return ids[k];
    });
    await page.goto(BASE + path, { waitUntil: 'load' });
    await steps(scene.before);
    await wait(450);   // settled: fonts, Alpine
    mark('s' + (i + 1));
    await steps(scene.steps);
    mark('e' + (i + 1));
  }
  await cdp.send('Page.stopScreencast');
  await wait(300);

  fs.mkdirSync(OUT + '/frames', { recursive: true });
  const index = frames.map((f, i) => {
    const file = `frames/f${String(i).padStart(5, '0')}.jpg`;
    fs.writeFileSync(OUT + '/' + file, Buffer.from(f.data, 'base64'));
    return { file, ts: f.ts };
  });
  fs.writeFileSync(OUT + '/timeline.json', JSON.stringify({ frames: index, marks }, null, 1));
  console.log(JSON.stringify({ frames: frames.length, scenes: spec.scenes.length, errors }));
  await browser.close();
  if (errors.length) process.exit(1);
})().catch((e) => { console.error(e); process.exit(1); });
