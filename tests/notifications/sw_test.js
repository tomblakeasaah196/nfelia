// The service worker's notification side (sw.js), run in a sandbox with a
// stand-in browser: what a push shows, where a tap goes, the app-icon
// number, and renewing a replaced subscription. And what a page shows when
// the server is up but too busy to answer.   node tests/notifications/sw_test.js
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0, fail = 0;
const failed = [];
const ok = (c, m) => { if (c) { pass++; console.log('  PASS  ' + m); } else { fail++; failed.push(m); console.log('  FAIL  ' + m); } };
const eq = (got, want, m) => ok(JSON.stringify(got) === JSON.stringify(want), m + (JSON.stringify(got) === JSON.stringify(want) ? '' : `  (got ${JSON.stringify(got)}, want ${JSON.stringify(want)})`));

const SCOPE = 'https://nfelia.test/';
const source = fs.readFileSync(path.join(__dirname, '..', '..', 'sw.js'), 'utf8');

/** A fresh worker with a stand-in browser around it, and a record of what it did. */
function worker({ windows = [], navigateFails = false, net = null } = {}) {
  const did = { shown: [], opened: [], focused: [], navigated: [], badge: [], messages: [], fetched: [], subscribed: [] };
  const handlers = {};
  const clientsList = windows.map((url) => ({
    url,
    focus() { did.focused.push(url); return Promise.resolve(this); },
    navigate(to) { did.navigated.push(to); return navigateFails ? Promise.reject(new Error('not controlled')) : Promise.resolve(this); },
    postMessage(m) { did.messages.push(m); },
  }));
  const self = {
    registration: {
      scope: SCOPE,
      showNotification: (title, options) => { did.shown.push({ title, options }); return Promise.resolve(); },
      pushManager: { subscribe: (opts) => { did.subscribed.push(opts); return Promise.resolve({ endpoint: 'https://push.test/new', toJSON: () => ({ endpoint: 'https://push.test/new', keys: { p256dh: 'p', auth: 'a' } }) }); } },
    },
    location: { origin: 'https://nfelia.test' },
    addEventListener: (type, fn) => { handlers[type] = fn; },
    skipWaiting: () => Promise.resolve(),
    clients: {
      matchAll: () => Promise.resolve(clientsList),
      openWindow: (url) => { did.opened.push(url); return Promise.resolve({}); },
      claim: () => Promise.resolve(),
      get: () => Promise.resolve(null),
    },
    navigator: {
      setAppBadge: (n) => { did.badge.push(n); return Promise.resolve(); },
      clearAppBadge: () => { did.badge.push(0); return Promise.resolve(); },
    },
  };
  const context = {
    self, URL, Promise, JSON, Date, setTimeout, console,
    caches: { open: () => Promise.resolve({ add: () => Promise.resolve() }), keys: () => Promise.resolve([]), delete: () => Promise.resolve(true), match: () => Promise.resolve(null) },
    fetch: (url, opts) => { did.fetched.push({ url, opts }); return Promise.resolve({ ok: true }); },
    Request: function (u) { this.url = u; }, Response: function () {}, Headers: function () {},
  };
  if (net) Object.assign(context, net);
  vm.createContext(context);
  vm.runInContext(source, context);
  const fire = async (type, event) => {
    const waits = [];
    event.waitUntil = (p) => waits.push(p);
    handlers[type](event);
    await Promise.all(waits);
  };
  return { did, fire };
}
const pushEvent = (payload) => ({ data: { json: () => (typeof payload === 'string' ? JSON.parse(payload) : payload), text: () => String(payload) } });

/**
 * A stand-in network and caches for opening pages: the server answers
 * $answer (a Response, or 'fail' for no network), offline.html is kept as
 * the worker keeps it, and $kept is the kept copy of /dashboard, if any.
 */
function pages({ answer, kept = null }) {
  const files = new Map([[SCOPE + 'offline.html', fs.readFileSync(path.join(__dirname, '..', '..', 'offline.html'), 'utf8')]]);
  const kept_pages = new Map(kept ? [[SCOPE + 'dashboard', kept]] : []);
  const key = (k) => (typeof k === 'string' ? k : k.url);
  const cache = (m) => ({
    match: (k) => Promise.resolve(m.has(key(k)) ? new Response(m.get(key(k)), { headers: { 'Content-Type': 'text/html; charset=utf-8' } }) : undefined),
    put: (k, res) => res.text().then((t) => { m.set(key(k), t); }),
    delete: (k) => Promise.resolve(m.delete(key(k))),
    keys: () => Promise.resolve([...m.keys()].map((url) => ({ url }))),
    add: () => Promise.resolve(),
  });
  return {
    caches: {
      open: (name) => Promise.resolve(cache(name === 'nf-pages' ? kept_pages : files)),
      match: (k) => cache(files).match(k),
      keys: () => Promise.resolve([]),
      delete: () => Promise.resolve(true),
    },
    fetch: () => (answer === 'fail' ? Promise.reject(new TypeError('Failed to fetch')) : Promise.resolve(answer)),
    Response, Headers, Request,
    setTimeout: (fn, ms) => { const t = setTimeout(fn, ms); t.unref(); return t; },
  };
}
/** Open $url in the app, as a tap on a link would, and what the worker answers. */
async function open(w, url) {
  let answer;
  await w.fire('fetch', { request: { url, method: 'GET', mode: 'navigate', headers: new Headers() }, preloadResponse: Promise.resolve(undefined),
                          resultingClientId: 'tab', respondWith: (p) => { answer = p; } });
  const res = await answer;
  return { status: res.status, html: await res.text() };
}
const BUSY = () => new Response('<h1>503 Service Unavailable</h1>The server is temporarily busy, try again later!', { status: 503 });

(async () => {
  console.log('\nA push arrives');
  {
    const w = worker({ windows: ['https://nfelia.test/dashboard'] });
    await w.fire('push', pushEvent({ title: 'New order: ₦65,000', body: 'Ngozi ordered 2 items', url: 'notifications/open/5', tag: 'order-700', id: 5, badge: 3 }));
    const n = w.did.shown[0] || { options: {} };
    eq(n.title, 'New order: ₦65,000', 'shows the title');
    eq(n.options.body, 'Ngozi ordered 2 items', 'and the body');
    eq(n.options.data, { url: SCOPE + 'notifications/open/5', id: 5 }, 'a tap will open the alert\'s own link');
    eq([n.options.tag, n.options.renotify], ['order-700', true], 'a newer alert about the same thing replaces the older one, and still buzzes');
    eq(w.did.badge, [3], 'the app icon shows the unread number');
    eq(w.did.messages, [{ type: 'nf-notify', id: 5, badge: 3 }], 'open pages are told, so the bell updates at once');
  }
  {
    const w = worker();
    await w.fire('push', pushEvent({ title: 'Hi', url: 'https://evil.test/steal' }));
    eq(w.did.shown[0].options.data.url, SCOPE, 'a link to another site is never followed: it opens Nfelia instead');
    await w.fire('push', pushEvent({ title: 'Read', badge: 0 }));
    eq(w.did.badge, [0], 'nothing unread: the number comes off the icon');
  }
  {
    const w = worker();
    await w.fire('push', { data: { json: () => { throw new Error('not json'); }, text: () => 'Plain words' } });
    eq([w.did.shown[0].title, w.did.shown[0].options.body], ['Nfelia', 'Plain words'], 'a message that is not JSON still shows');
  }

  console.log('\nTapping it');
  {
    const w = worker();
    await w.fire('notificationclick', { notification: { close() {}, data: { url: SCOPE + 'notifications/open/5' } } });
    eq(w.did.opened, [SCOPE + 'notifications/open/5'], 'Nfelia closed: opens a window on the alert\'s link (sign-in first if needed, the server sees to that)');
  }
  {
    const w = worker({ windows: ['https://nfelia.test/tasks'] });
    await w.fire('notificationclick', { notification: { close() {}, data: { url: SCOPE + 'notifications/open/9' } } });
    eq([w.did.focused, w.did.navigated, w.did.opened], [['https://nfelia.test/tasks'], [SCOPE + 'notifications/open/9'], []],
       'Nfelia already open: brings it forward and takes it to the alert, no second window');
  }
  {
    const w = worker({ windows: ['https://nfelia.test/tasks'], navigateFails: true });
    await w.fire('notificationclick', { notification: { close() {}, data: { url: SCOPE + 'notifications/open/9' } } });
    eq(w.did.opened, [SCOPE + 'notifications/open/9'], 'if that window can\'t be moved, a new one opens on the alert');
  }
  {
    const w = worker({ windows: ['https://other.test/'] });
    await w.fire('notificationclick', { notification: { close() {}, data: { url: SCOPE + 'notifications/open/9' } } });
    eq([w.did.navigated, w.did.opened], [[], [SCOPE + 'notifications/open/9']], 'a window of another site is left alone');
  }

  console.log('\nThe browser replaces the subscription');
  {
    const w = worker();
    const old = { endpoint: 'https://push.test/old', options: { userVisibleOnly: true, applicationServerKey: 'KEY' } };
    await w.fire('pushsubscriptionchange', { oldSubscription: old, newSubscription: null });
    eq(w.did.subscribed, [old.options], 'subscribes again with Nfelia\'s same key');
    const call = w.did.fetched[0] || {};
    eq(call.url, SCOPE + 'api/notifications?action=resubscribe', 'tells Nfelia');
    eq(JSON.parse(call.opts ? call.opts.body : '{}'), { old_endpoint: 'https://push.test/old', tokens: [], subscription: { endpoint: 'https://push.test/new', keys: { p256dh: 'p', auth: 'a' } } },
       'with the old endpoint (which proves which row it was) and the new subscription');
  }
  {
    const w = worker();
    const fresh = { endpoint: 'https://push.test/given', toJSON: () => ({ endpoint: 'https://push.test/given', keys: {} }) };
    await w.fire('pushsubscriptionchange', { oldSubscription: { endpoint: 'https://push.test/old' }, newSubscription: fresh });
    eq([w.did.subscribed.length, JSON.parse(w.did.fetched[0].opts.body).subscription.endpoint], [0, 'https://push.test/given'], 'uses the new one the browser hands over');
  }

  console.log('\nThe server is up but too busy to answer');
  {
    const r = await open(worker({ net: pages({ answer: BUSY() }) }), SCOPE + 'dashboard?source=pwa');
    ok(r.status === 503 && r.html.includes('<html lang="en" data-reason="busy">') && r.html.includes('Nfelia is busy') && !r.html.includes('temporarily busy'),
       'nothing kept: "Nfelia is busy", which tries again by itself, not the host\'s own error page');
  }
  {
    const kept = '<!DOCTYPE html><html><head><meta name="nf-cached" content="2026-10-22T02:40:00.000Z"><title>Home</title></head><body>Today, as it was</body></html>';
    const r = await open(worker({ net: pages({ answer: BUSY(), kept }) }), SCOPE + 'dashboard?source=pwa');
    ok(r.status === 200 && r.html.includes('Today, as it was') && r.html.includes('<meta name="nf-cached" data-why="busy" content="2026-10-22T02:40:00.000Z">'),
       'kept: the page as it was, marked so its banner says Nfelia is busy');
  }
  {
    const kept = '<!DOCTYPE html><html><head><meta name="nf-cached" content="2026-10-22T02:40:00.000Z"></head><body>Orders, before the new one</body></html>';
    const w = worker({ net: pages({ answer: BUSY(), kept }) });
    await w.fire('fetch', { request: { url: SCOPE + 'orders/create', method: 'POST', mode: 'navigate', headers: new Headers() }, respondWith: () => {} });
    const r = await open(w, SCOPE + 'dashboard');
    ok(r.html.includes('<html lang="en" data-reason="busy">') && !r.html.includes('Orders, before the new one'),
       'just after a form: "Nfelia is busy", never the old copy, which would hide what the form did');
  }
  for (const status of [502, 504, 522]) {
    const r = await open(worker({ net: pages({ answer: new Response('gateway', { status }) }) }), SCOPE + 'orders');
    ok(r.html.includes('data-reason="busy"'), `${status} from the way to the server: the same`);
  }
  {
    const r = await open(worker({ net: pages({ answer: new Response('<p>Something went wrong. Error code EAB12CD3</p>', { status: 500 }) }) }), SCOPE + 'orders');
    eq([r.status, r.html], [500, '<p>Something went wrong. Error code EAB12CD3</p>'], 'the app\'s own error page shows as it is, with the code support asks for');
  }
  {
    const r = await open(worker({ net: pages({ answer: 'fail' }) }), SCOPE + 'orders');
    ok(r.html.includes('<html lang="en">') && !r.html.includes('<html lang="en" data-reason="busy">'), 'no network at all: "No connection", as before');
  }
  {
    const r = await open(worker({ net: pages({ answer: BUSY() }) }), SCOPE + 'login?source=pwa');
    ok(r.html.includes('data-reason="busy"'), 'the sign-in page the app starts on when signed out says so too');
    const once = await open(worker({ net: pages({ answer: BUSY() }) }), SCOPE + 'login-link/0123456789abcdef');
    ok(once.status === 503 && once.html.includes('temporarily busy'), 'a one-time sign-in link is never tried again by itself: the host\'s answer, as it is');
  }

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  failed.forEach((f) => console.log('  - ' + f));
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
