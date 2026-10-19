// A page animation the browser drops is never reported as an error.
//
// When the screen changes size between leaving one page and painting the
// next (a phone's address bar or keyboard moving), the browser abandons the
// cross-document view transition and rejects its promises with
// "InvalidStateError: Transition was aborted because of invalid state".
// Some browsers then raise unhandledrejection, which errors.js sent to
// Ops → Errors. shell.js now catches the promises on the page leaving and the
// page arriving, and errors.js skips that one harmless abort.
//
// Self-contained: serves its own pages, with the real assets/js/errors.js and
// the real quietTransition() from assets/js/shell.js. No app copy needed.
//   node transition_test.js
const fs = require('fs');
const http = require('http');
const path = require('path');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const ROOT = path.join(__dirname, '..', '..', '..');
const SHELL = fs.readFileSync(path.join(ROOT, 'assets/js/shell.js'), 'utf8');
const ERRORS = fs.readFileSync(path.join(ROOT, 'assets/js/errors.js'), 'utf8');
const CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };

// The function itself, as shell.js has it.
const quiet = (SHELL.match(/function quietTransition\(vt\) \{[\s\S]*?\n {4}\}/) || [''])[0];

const reports = [];
const page = (title, body, head = '') => `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title>
<style>@view-transition { navigation: auto; }
::view-transition-old(root), ::view-transition-new(root), ::view-transition-group(root) { animation-duration: 2s; }
body { margin: 0; min-height: 150vh; }</style>
<script src="/errors.js" data-endpoint="/collect"></script>
<script>${quiet}
window.addEventListener('pageswap', function (e) { if (e.viewTransition) quietTransition(e.viewTransition); });
window.addEventListener('pagereveal', function (e) { if (e.viewTransition) quietTransition(e.viewTransition); });
window.__seen = [];
window.addEventListener('unhandledrejection', function (e) { window.__seen.push(String(e.reason && e.reason.message)); });
window.addEventListener('pagereveal', function (e) {
  window.__transition = !!e.viewTransition;
  if (e.viewTransition) e.viewTransition.ready.catch(function (err) { window.__abort = err.name + ': ' + err.message; });
});
</script>${head}</head><body>${body}</body></html>`;

const server = http.createServer((req, res) => {
  const url = req.url.split('?')[0];
  if (url === '/collect') {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => { reports.push(body); res.writeHead(204); res.end(); });
    return;
  }
  if (url === '/errors.js') { res.writeHead(200, { 'Content-Type': 'text/javascript' }); return res.end(ERRORS); }
  // Holds back the next page's first paint, so a resize lands between leaving
  // the old page and painting the new one.
  if (url === '/slow.js') { setTimeout(() => { res.writeHead(200, { 'Content-Type': 'text/javascript' }); res.end('/* slow */'); }, 1500); return; }
  const html = {
    '/a.html': page('A', '<a id="go" href="/b.html">Next</a>'),
    '/b.html': page('B', '<p>Arrived</p>', '<script src="/slow.js"></script>'),
    '/blank.html': page('Blank', '<p>Blank</p>'),
  }[url];
  if (!html) { res.writeHead(404); return res.end(); }
  res.writeHead(200, { 'Content-Type': 'text/html' });
  res.end(html);
});

(async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const BASE = 'http://127.0.0.1:' + server.address().port;
  const browser = await chromium.launch(fs.existsSync(CHROME) ? { executablePath: CHROME } : {});
  const pageErrors = [];
  try {
    console.log('The code under test');
    ok(quiet.includes("['ready', 'finished', 'updateCallbackDone']"), 'shell.js has quietTransition() for all three promises');
    ok(/addEventListener\('pageswap', function \(e\) \{ if \(e\.viewTransition\) quietTransition\(e\.viewTransition\); \}\)/.test(SHELL),
       'shell.js quiets the transition on the page leaving');
    ok(/addEventListener\('pagereveal', function \(e\) \{\s*if \(e\.viewTransition\) quietTransition\(e\.viewTransition\);/.test(SHELL),
       'and first thing on the page arriving, before any early return');

    console.log('quietTransition() marks the promises handled');
    // This page rejects promises on purpose, so its page errors are expected.
    const ctx = await browser.newContext();
    const p = await ctx.newPage();
    await p.goto(BASE + '/blank.html');
    // The page carries the real quietTransition() (see page() above).
    const counts = await p.evaluate(async () => {
      if (typeof window.quietTransition !== 'function') return { quieted: null, alone: null };
      const make = () => ({
        ready: Promise.reject(new DOMException('Transition was aborted because of invalid state', 'InvalidStateError')),
        finished: Promise.reject(new DOMException('Transition was aborted because of invalid state', 'InvalidStateError')),
        updateCallbackDone: Promise.resolve(),
      });
      const before = window.__seen.length;
      window.quietTransition(make());
      await new Promise((r) => setTimeout(r, 300));
      const quieted = window.__seen.length - before;
      make();                    // the same, left alone
      await new Promise((r) => setTimeout(r, 300));
      return { quieted, alone: window.__seen.length - before - quieted };
    });
    ok(counts.alone === 2, `left alone, the two rejected promises are unhandled (${counts.alone})`);
    ok(counts.quieted === 0, `through quietTransition(), none are (${counts.quieted})`);

    console.log('errors.js skips only the dropped animation');
    reports.length = 0;
    await p.evaluate(() => {
      Promise.reject(new DOMException('Transition was aborted because of invalid state. Viewport size changed.', 'InvalidStateError'));
      Promise.reject(new DOMException('Transition was aborted because of invalid state', 'InvalidStateError'));
      Promise.reject(new DOMException('The object is in an invalid state.', 'InvalidStateError'));
      Promise.reject(new Error('Something real broke'));
    });
    await new Promise((r) => setTimeout(r, 800));
    const sent = reports.map((b) => { try { return JSON.parse(b).message; } catch (e) { return b; } });
    ok(!sent.some((m) => /Transition was aborted/.test(m)), 'a dropped animation is not reported, in either wording');
    ok(sent.includes('The object is in an invalid state.'), 'another InvalidStateError still is');
    ok(sent.includes('Something real broke'), 'and so is a real error: ' + JSON.stringify(sent));
    await ctx.close();

    console.log('A real navigation, resized before the next page is painted');
    reports.length = 0;
    const phone = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const q = await phone.newPage();
    q.on('pageerror', (e) => pageErrors.push(e.message));
    await q.goto(BASE + '/a.html');
    await q.click('#go');
    await q.waitForTimeout(400);
    await q.setViewportSize({ width: 390, height: 700 });   // the address bar moving
    await q.waitForTimeout(2500);
    const r = await q.evaluate(() => ({ at: location.pathname, transition: window.__transition, abort: window.__abort || '', seen: window.__seen }));
    ok(r.at === '/b.html' && r.transition === true, 'the browser ran a view transition into the next page');
    ok(/InvalidStateError: Transition was aborted/.test(r.abort), 'and dropped it when the size changed: ' + r.abort);
    ok(r.seen.length === 0, 'no unhandled rejection on the page');
    ok(reports.length === 0, 'nothing sent to Ops → Errors');
    ok(pageErrors.length === 0, 'no page errors' + (pageErrors.length ? ': ' + pageErrors.join(' | ') : ''));
    await phone.close();
  } catch (e) {
    ok(false, 'the test stopped: ' + e.message);
  } finally {
    await browser.close();
    server.close();
  }
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
