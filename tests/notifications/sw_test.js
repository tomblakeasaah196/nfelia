// The service worker's notification side (sw.js), run in a sandbox with a
// stand-in browser: what a push shows, where a tap goes, the app-icon
// number, and renewing a replaced subscription.   node tests/notifications/sw_test.js
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
function worker({ windows = [], navigateFails = false } = {}) {
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

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  failed.forEach((f) => console.log('  - ' + f));
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
