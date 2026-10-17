#!/usr/bin/env node
// Copies the app's third-party files out of node_modules into assets/, so
// the app serves them itself: they cache with the app, work offline, and
// don't depend on a CDN being reachable. Run with `npm run build:vendor`
// after changing a version in package.json, then commit what changed.
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const nm = (p) => path.join(root, 'node_modules', p);
const copies = [
  ['alpinejs/dist/cdn.min.js', 'assets/vendor/alpine-3.14.3.min.js'],
  ['jquery/dist/jquery.min.js', 'assets/vendor/jquery-3.7.1.min.js'],
  ['@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-latin-wght-normal.woff2', 'assets/fonts/plus-jakarta-sans-latin.woff2'],
  ['@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-latin-ext-wght-normal.woff2', 'assets/fonts/plus-jakarta-sans-latin-ext.woff2'],
  ['@fontsource-variable/bricolage-grotesque/files/bricolage-grotesque-latin-wght-normal.woff2', 'assets/fonts/bricolage-grotesque-latin.woff2'],
  ['@fontsource-variable/bricolage-grotesque/files/bricolage-grotesque-latin-ext-wght-normal.woff2', 'assets/fonts/bricolage-grotesque-latin-ext.woff2'],
];
for (const [from, to] of copies) {
  fs.mkdirSync(path.dirname(path.join(root, to)), { recursive: true });
  fs.copyFileSync(nm(from), path.join(root, to));
  console.log(to);
}
