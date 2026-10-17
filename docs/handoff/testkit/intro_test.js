// The brand intro in "Start in three steps" (views/home/index.php, home.js):
// it plays once when in view and rests on the still of its last frame, and
// every way it can't play leaves that still in place.
// Headless Chromium can't decode H.264, so the play path runs on a VP9 copy
// of the clip (made here with ffmpeg) served at the MP4's address, with
// canPlayType told that MP4 is fine.
//   node intro_test.js OUTDIR
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8099';
const CLIP = '**/assets/video/nfelia-intro.mp4';
const OUT = process.argv[2];
let pass = 0, fail = 0;
const check = (n, ok, extra = '') => { console.log((ok ? '  PASS  ' : '  FAIL  ') + n + (extra ? '  ' + extra : '')); ok ? pass++ : fail++; };

const CAN_PLAY = `HTMLMediaElement.prototype.canPlayType = function (t) { return /mp4|webm/.test(t) ? 'probably' : ''; };`;
const net = (c) => `Object.defineProperty(navigator, 'connection', { value: ${JSON.stringify(c)}, configurable: true });`;
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

async function open(b, { reduce = false, init = [], route = null } = {}) {
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, reducedMotion: reduce ? 'reduce' : 'no-preference' });
  const p = await ctx.newPage();
  const errors = [], hits = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  p.on('request', (r) => { if (r.url().includes('/assets/video/')) hits.push(r.url()); });
  for (const s of init) await p.addInitScript(s);
  if (route) await p.route(CLIP, route);
  await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  return { ctx, p, errors, hits };
}

const state = (p) => p.evaluate(() => {
  const box = document.querySelector('[data-intro]');
  const img = box.querySelector('img'), v = box.querySelector('video');
  const r = box.getBoundingClientRect();
  return {
    img: img.getAttribute('src').split('/').pop(), loaded: img.complete && img.naturalWidth,
    video: !!v, on: !!(v && v.classList.contains('is-on')), time: v ? v.currentTime : 0, paused: v ? v.paused : null,
    ratio: r.width / r.height,
  };
});
const show = async (p) => { await p.locator('[data-intro]').scrollIntoViewIfNeeded(); await p.evaluate(() => window.scrollBy(0, 120)); };
const justBelow = (p) => p.evaluate(() => {
  const r = document.querySelector('[data-intro]').getBoundingClientRect();
  window.scrollBy(0, r.top - innerHeight * 1.4);   // 40% of a screen below the fold
});

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const mp4 = path.join(OUT, 'intro.mp4'), webm = path.join(OUT, 'intro-stand-in.webm');
  fs.writeFileSync(mp4, Buffer.from(await (await fetch(BASE + '/assets/video/nfelia-intro.mp4')).arrayBuffer()));
  execSync(`ffmpeg -v error -y -i "${mp4}" -c:v libvpx-vp9 -b:v 0 -crf 40 -an "${webm}"`);
  const body = fs.readFileSync(webm);
  const serve = (route) => route.fulfill({ status: 200, contentType: 'video/webm', body });

  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });

  console.log('Stays a still');
  for (const [name, opts] of [
    ['this browser, which cannot play MP4', {}],
    ['reduced motion', { reduce: true, init: [CAN_PLAY], route: serve }],
    ['Data Saver', { init: [CAN_PLAY, net({ saveData: true, effectiveType: '4g' })], route: serve }],
    ['a 2G connection', { init: [CAN_PLAY, net({ saveData: false, effectiveType: '2g' })], route: serve }],
  ]) {
    const { ctx, p, errors, hits } = await open(b, opts);
    await show(p); await wait(1500);
    const s = await state(p);
    check(`${name}: the logo still shows, fully loaded`, s.img === 'intro-end.webp' && s.loaded === 960 && !s.video, JSON.stringify(s));
    check(`${name}: the clip is never downloaded`, hits.length === 0, hits.join(' '));
    check(`${name}: no console errors`, errors.length === 0, errors.join(' | '));
    await ctx.close();
  }

  console.log('Plays once');
  {
    const { ctx, p, errors, hits } = await open(b, { init: [CAN_PLAY], route: serve });
    let s = await state(p);
    check('before it is near, the box holds the empty stage the clip starts from', s.img === 'intro-start.webp' && !s.video, JSON.stringify(s));
    check('and nothing is downloaded yet', hits.length === 0);
    await justBelow(p); await wait(800);
    s = await state(p);
    check('just below the screen, the clip downloads but waits', hits.length === 1 && s.video && !s.on && s.paused, JSON.stringify(s));
    const before = s.ratio;
    await show(p); await wait(1500);
    s = await state(p);
    check('in view, it plays', s.on && !s.paused && s.time > 0.3, JSON.stringify(s));
    check('the box keeps its 16:9 shape', Math.abs(s.ratio - 16 / 9) < 0.01 && Math.abs(s.ratio - before) < 0.001, s.ratio.toFixed(4));
    await p.locator('#how').screenshot({ path: path.join(OUT, 'intro_playing_m.png') });
    await wait(5000);
    s = await state(p);
    check('when it ends, the clip makes way for the still of its last frame', s.img === 'intro-end.webp' && s.loaded === 960 && !s.video, JSON.stringify(s));
    await p.evaluate(() => window.scrollTo(0, 0)); await wait(400); await show(p); await wait(1500);
    s = await state(p);
    check('scrolling back does not play it again', !s.video && hits.length === 1 && s.img === 'intro-end.webp', JSON.stringify(s));
    check('no console errors', errors.length === 0, errors.join(' | '));
    await p.locator('#how').screenshot({ path: path.join(OUT, 'intro_rest_m.png') });
    await ctx.close();
  }

  console.log('Falls back to the still');
  for (const [name, opts, waitMs, expected] of [
    ['play refused (iPhone Low Power Mode)', { init: [CAN_PLAY, `HTMLMediaElement.prototype.play = function () { return Promise.reject(new DOMException('blocked', 'NotAllowedError')); };`], route: serve }, 1500, []],
    ['the clip is missing', { init: [CAN_PLAY], route: (r) => r.fulfill({ status: 404, body: 'Not found' }) }, 1500, [/status of 404/]],
    ['the clip is too slow to start', { init: [CAN_PLAY], route: async (r) => { await wait(8000); r.fulfill({ status: 200, contentType: 'video/webm', body }).catch(() => {}); } }, 5000, []],
  ]) {
    const { ctx, p, errors } = await open(b, opts);
    await show(p);
    if (name.includes('slow')) {
      await wait(1500);
      const mid = await state(p);
      check(`${name}: meanwhile the stage shows`, mid.img === 'intro-start.webp' && !mid.on, JSON.stringify(mid));
    }
    await wait(waitMs);
    const s = await state(p);
    check(`${name}: the logo still shows`, s.img === 'intro-end.webp' && s.loaded === 960 && !s.video, JSON.stringify(s));
    const unexpected = errors.filter((e) => !expected.some((re) => re.test(e)));
    check(`${name}: no unexpected console errors`, unexpected.length === 0, unexpected.join(' | '));
    await ctx.close();
  }

  await b.close();
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
