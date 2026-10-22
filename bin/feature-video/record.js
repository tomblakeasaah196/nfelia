// Films a video's scenes in the running app, at phone size.
//
//   node record.js <scenes.json> '<ids json from the seed>' <work folder>
//
// Frames come from Chrome's screencast and go straight to disk with their
// times; every filmed scene is marked where it starts and ends
// (timeline.json), and compose.py cuts the video from those marks, so page
// loads between scenes never show. A gold circle shows each tap; a gold
// outline shows what changed.
//
// The spec: { login: {email, password} | false, scenes: [...] }. With
// login false the owner's phone starts signed out (a sign-up video).
// A scene:
//   { open: "/orders/view/{order}"  where to start ({key} from the seed's ids;
//                                   a full URL for the customer's shop; left
//                                   out, the scene carries on where the last
//                                   one ended on that phone)
//     as: "owner" | "customer"      whose phone (the customer's is a second,
//                                   signed-out browser), default owner
//     before: [steps, not filmed], steps: [steps, filmed],
//     caption, sub, say }
//   A scene with "card" instead is a full-screen title card: nothing filmed.
// A step:
//   ["wait", ms] · ["tap", sel, {nav: true}] (circle, then tap; nav waits for
//   the next page) · ["click", sel] (no circle) · ["fill", sel, value]
//   · ["type", sel, text, ms between keys] · ["press", key] · ["select", sel, value]
//   · ["check", sel] · ["uncheck", sel] · ["upload", sel, file or [files]] (beside
//   scenes.json) · ["choose", sel, file or [files]] (taps a button that opens the
//   phone's file picker, and picks) · ["change", sel] · ["spot", sel, padding] · ["unspot"]
//   · ["scroll", sel, px from the top] · ["scrollBy", px, ms] · ["goto", path]
//   · ["waitFor", sel] · ["mail", text in the subject] (shows the newest such
//   email, as sent) · ["eval", "js"] · ["run", "php add-more.php"] (a command,
//   run beside scenes.json, for changes made off camera: put it in "before")
//   · ["keep", key, sel, attr] (remembers a link or value shown on screen as
//   {key}, for a later scene: attr "value" for an input, else an attribute,
//   href by default; sel "url" keeps the page's own address)
// Selectors are Playwright's: CSS, or button:has-text("Save").
const { chromium } = require('playwright');
const fs = require('fs');
const { execSync } = require('child_process');
const path = require('path');
const [scenesFile, idsJson, OUT] = process.argv.slice(2);
const spec = JSON.parse(fs.readFileSync(scenesFile, 'utf8'));
const DIR = path.dirname(path.resolve(scenesFile));
const ids = JSON.parse(idsJson || '{}');
const BASE = (process.env.APP_URL || 'http://localhost:8099').replace(/\/$/, '');
const MAIL_DIR = process.env.NF_MAIL_DIR || '';
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

// The newest email whose subject has this text, as the HTML a mail app shows
function newestMail(text) {
  if (!MAIL_DIR) throw new Error('A "mail" step needs NF_MAIL_DIR: the folder the mail catcher writes to.');
  const files = fs.readdirSync(MAIL_DIR).filter(f => f.endsWith('.eml'))
    .map(f => ({ f, t: fs.statSync(path.join(MAIL_DIR, f)).mtimeMs })).sort((a, b) => b.t - a.t);
  for (const { f } of files) {
    const raw = fs.readFileSync(path.join(MAIL_DIR, f), 'latin1');
    const subject = decodeHeader((raw.match(/^Subject: (.*(?:\r?\n[ \t].*)*)/mi) || [])[1] || '');
    const from = decodeHeader((raw.match(/^From: (.*)/mi) || [])[1] || '').replace(/\s*<.*>/, '').replace(/"/g, '');
    if (!subject.toLowerCase().includes(String(text).toLowerCase())) continue;
    const part = raw.split(/\r?\n--/).find(p => /Content-Type:\s*text\/html/i.test(p)) || raw;
    const [head, ...rest] = part.split(/\r?\n\r?\n/);
    let body = rest.join('\n\n');
    if (/Content-Transfer-Encoding:\s*base64/i.test(head)) body = Buffer.from(body.replace(/\s+/g, ''), 'base64').toString('utf8');
    else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(head)) {
      body = Buffer.from(body.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, h) => String.fromCharCode(parseInt(h, 16))), 'latin1').toString('utf8');
    }
    return { subject, from, html: body };
  }
  throw new Error('No email whose subject has "' + text + '"');
}
function decodeHeader(h) {
  return h.replace(/\r?\n[ \t]/g, ' ').replace(/=\?utf-8\?([bq])\?([^?]*)\?=/gi, (_, enc, s) =>
    enc.toLowerCase() === 'b' ? Buffer.from(s, 'base64').toString('utf8')
      : Buffer.from(s.replace(/_/g, ' ').replace(/=([0-9A-F]{2})/gi, (__, x) => String.fromCharCode(parseInt(x, 16))), 'latin1').toString('utf8'));
}

(async () => {
  // Shops are subdomains: name.localhost for a local app, and with
  // NF_VIDEO_DOMAIN (make.sh's HTTPS stand-in) the domain itself and its shops
  const dom = process.env.NF_VIDEO_DOMAIN;
  const rules = ['MAP *.localhost 127.0.0.1'].concat(dom ? [`MAP ${dom} 127.0.0.1`, `MAP *.${dom} 127.0.0.1`] : []);
  // A machine whose web traffic goes through a proxy would send the domain to
  // the proxy, which finds the real site: the domain and the local names skip it
  // (given to Chromium itself: Playwright's own proxy option sends localhost
  // through the proxy too)
  const out = process.env.HTTPS_PROXY || process.env.https_proxy;
  const skip = ['localhost', '*.localhost', '127.0.0.1'].concat(dom ? [dom, '*.' + dom] : []).join(';');
  const via = out ? [`--proxy-server=${out}`, `--proxy-bypass-list=${skip}`] : [];
  const browser = await chromium.launch({ args: [`--host-resolver-rules=${rules.join(', ')}`, ...via] });
  const device = {
    ignoreHTTPSErrors: !!dom,
    viewport: { width: phone.width, height: phone.height }, deviceScaleFactor: phone.scale, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
  };
  const errors = [];
  const phones = {};
  const phoneFor = async (who) => {
    if (!phones[who]) {
      const ctx = await browser.newContext(device);
      await ctx.grantPermissions(['clipboard-read', 'clipboard-write']);   // as a tap on a phone allows "Copy link"
      await ctx.addInitScript(FX);
      const page = await ctx.newPage();
      page.on('pageerror', e => errors.push(`${who}: ${e}`));
      phones[who] = { ctx, page, cdp: await ctx.newCDPSession(page) };
    }
    return phones[who];
  };

  const owner = await phoneFor('owner');
  // Never film the real site: with NF_VIDEO_DOMAIN, the domain must answer
  // from tls-proxy.js on this machine, which marks every answer
  if (dom) {
    const r = await owner.page.goto(BASE + '/login');
    if (!r || r.headers()['x-nf-video-local'] !== '1') {
      throw new Error(`Refusing to film: ${BASE} is not answering from this machine (the real site would get the video's sign-ups and orders).`);
    }
  }
  if (spec.login !== false) {
    await owner.page.goto(BASE + '/login');
    await owner.page.fill('#email', spec.login?.email || 'ada@adashair.test');
    await owner.page.fill('#password', spec.login?.password || 'TestPass1');
    await Promise.all([owner.page.waitForNavigation(), owner.page.click('form[action$="/login"] button[type=submit]')]);
  }

  // Frames straight to disk, so a five-minute film never sits in memory
  fs.mkdirSync(OUT + '/frames', { recursive: true });
  const index = [];
  let filming = null;
  const film = async (who) => {
    if (filming === who) return;
    if (filming) await phones[filming].cdp.send('Page.stopScreencast');
    const p = phones[who];
    if (!p.listening) {
      p.listening = true;
      p.cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
        const file = `frames/f${String(index.length).padStart(6, '0')}.jpg`;
        index.push({ file, ts: metadata.timestamp });
        fs.writeFileSync(OUT + '/' + file, Buffer.from(data, 'base64'));
        p.cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
      });
    }
    await p.cdp.send('Page.startScreencast', { format: 'jpeg', quality: 88, maxWidth: 1080, maxHeight: 2400, everyNthFrame: 1 });
    filming = who;
  };

  const marks = [];
  const mark = (name) => marks.push({ name, ts: Date.now() / 1000 });
  const fill = (s) => String(s).replace(/\{(\w+)\}/g, (_, k) => {
    if (!(k in ids)) throw new Error(`the seed gave no "${k}" for ${s}`);
    return ids[k];
  });
  const url = (p) => {
    p = fill(p);
    if (/^https?:/.test(p)) return p;
    const shop = p.match(/^shop:([a-z0-9-]+)(\/.*)?$/);
    if (shop) { const b = new URL(BASE); return `${b.protocol}//${shop[1]}.${b.host}${shop[2] || '/'}`; }
    return BASE + p;
  };

  for (const [i, scene] of spec.scenes.entries()) {
    if (scene.card) continue;   // drawn by stage.js, nothing to film
    const { page } = await phoneFor(scene.as || 'owner');
    const wait = (ms) => page.waitForTimeout(ms);
    const fx = (fn, ...args) => page.evaluate(([f, a]) => window.__fx && window.__fx[f](...a), [fn, args]);
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
      tap: async (sel, opts) => {
        const b = await box(sel);
        await fx('ring', b.x + b.width / 2, b.y + b.height / 2); await wait(420);
        await fx('press'); await wait(140);
        if (opts?.nav) {
          await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator(sel).first().tap()]);
          await wait(350); await fx('unring');   // a sheet that only changed the address keeps the page
        } else {
          await page.locator(sel).first().tap();
          await wait(120); await fx('unring');
        }
      },
      click: (sel) => page.locator(sel).first().click(),
      fill: (sel, value) => page.fill(sel, fill(value)),
      type: (sel, text, ms) => page.locator(sel).first().type(fill(text), { delay: ms ?? 70 }),
      press: (key) => page.keyboard.press(key),
      select: (sel, value) => page.selectOption(sel, value),
      check: (sel) => page.locator(sel).first().check(),
      uncheck: (sel) => page.locator(sel).first().uncheck(),
      upload: (sel, files) => page.setInputFiles(sel, [].concat(files).map(f => path.resolve(DIR, f))),
      choose: async (sel, files) => {
        const b = await box(sel);
        await fx('ring', b.x + b.width / 2, b.y + b.height / 2); await wait(420);
        await fx('press'); await wait(140);
        const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.locator(sel).first().tap()]);
        await chooser.setFiles([].concat(files).map(f => path.resolve(DIR, f)));
        await wait(120); await fx('unring');
      },
      change: (sel) => page.dispatchEvent(sel, 'change'),
      spot: async (sel, pad) => {
        const b = await box(sel), p = pad ?? 6;
        await fx('spot', b.x - p, b.y - p, b.width + p * 2, b.height + p * 2);
      },
      unspot: () => fx('clear'),
      scroll: (sel, top) => page.locator(sel).first().evaluate((el, t) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - t), top ?? 120),
      scrollBy: async (px, ms) => {
        await page.evaluate((y) => window.scrollBy({ top: y, behavior: 'smooth' }), px);
        await wait(ms ?? 700);
      },
      goto: async (p) => { await page.goto(url(p), { waitUntil: 'load' }); await wait(350); },
      waitFor: (sel) => page.locator(sel).first().waitFor(),
      // The email as a plain mail app shows it: sender and subject over the body
      mail: async (text) => {
        const m = newestMail(text);
        await page.setContent(m.html, { waitUntil: 'load' });
        await page.evaluate(([subject, from]) => {
          const bar = document.createElement('div');
          bar.style.cssText = 'font:15px/1.35 system-ui,sans-serif;color:#1B0A11;background:#fff;padding:18px 18px 14px;border-bottom:1px solid #e6e1d8;text-align:left';
          const initial = (from || 'N').trim()[0].toUpperCase();
          bar.innerHTML = '<div style="font-size:20px;font-weight:700;margin-bottom:12px"></div>'
            + '<div style="display:flex;gap:12px;align-items:center"><span data-a style="width:38px;height:38px;border-radius:50%;background:#8B004A;color:#fff;display:grid;place-items:center;font-weight:700"></span>'
            + '<span><b></b><br><span style="color:#6b5a62;font-size:13px">to me · just now</span></span></div>';
          bar.children[0].textContent = subject;
          bar.querySelector('[data-a]').textContent = initial;
          bar.querySelector('b').textContent = from || 'Nfelia';
          document.body.prepend(bar);
          document.body.style.margin = '0';
        }, [m.subject, m.from]);
        await wait(350);
      },
      eval: (js) => page.evaluate(js),
      run: (cmd) => { execSync(cmd, { cwd: DIR, stdio: ['ignore', 'ignore', 'inherit'] }); },
      keep: async (key, sel, attr) => {
        if (sel === 'url') { ids[key] = page.url(); return; }   // the page itself
        const el = page.locator(sel).first();
        ids[key] = attr === 'value' ? await el.inputValue() : await el.getAttribute(attr || 'href');
        if (!ids[key]) throw new Error(`nothing to keep at ${sel}`);
      },
    };
    const steps = async (list) => {
      for (const [name, ...args] of list || []) {
        if (!run[name]) throw new Error('unknown step ' + name);
        try {
          await run[name](...args);
        } catch (e) {
          throw new Error(`scene ${i + 1} ("${scene.caption || ''}"), step ${name} ${JSON.stringify(args)}: ${e.message}`);
        }
      }
    };

    if (scene.open) await page.goto(url(scene.open), { waitUntil: 'load' });
    await steps(scene.before);
    await film(scene.as || 'owner');
    await wait(450);   // settled: fonts, Alpine
    mark('s' + (i + 1));
    await steps(scene.steps);
    mark('e' + (i + 1));
  }
  if (filming) await phones[filming].cdp.send('Page.stopScreencast');
  await new Promise((r) => setTimeout(r, 300));

  index.sort((a, b) => a.ts - b.ts);   // two phones' frames, in time order
  fs.writeFileSync(OUT + '/timeline.json', JSON.stringify({ frames: index, marks }, null, 1));
  console.log(JSON.stringify({ frames: index.length, scenes: spec.scenes.length, errors }));
  await browser.close();
  if (errors.length) process.exit(1);
})().catch((e) => { console.error(e.message || e); process.exit(1); });
