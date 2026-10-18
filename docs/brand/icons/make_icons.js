#!/usr/bin/env node
/**
 * Draws every app icon and iOS launch screen from the traced mark
 * (assets/img/brand/nfelia-mark-parts.svg), so they always match each other
 * and the opening in views/partials/nf_splash.php.
 *
 *   node docs/brand/icons/make_icons.js            (from the repo root)
 *
 * Needs Playwright with Chromium (set CHROMIUM_PATH to use a specific one),
 * and ImageMagick's `convert` for favicon.ico. Commit what it writes.
 *
 * The icon: a cream box with a gold thread on a deep wine tile. The box and
 * loop fill Android's safe circle (80% of the tile), so round, squircle and
 * square masks never cut them; the thread runs off towards the edges.
 * Small sizes use the heavier "-icon" drawing so the thread and the gaps in
 * the box still show.
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const ROOT = path.resolve(__dirname, '../../..');
const SVG = fs.readFileSync(path.join(ROOT, 'assets/img/brand/nfelia-mark-parts.svg'), 'utf8');
const DEFS = SVG.match(/<defs>[\s\S]*<\/defs>/)[0];

const WINE = '#6E0039', WINE_LIGHT = '#7C0042', CREAM = '#F7F3EA', GOLD = '#D2B46C';
const ICON_BOX = '-34 -278 1500 1500';            // box and loop centred, inside the safe circle

function tile({ size, grade = true, radius = 0, background = true }) {
  const s = grade ? '-icon' : '';
  const bg = background
    ? `<radialGradient id="g" cx=".5" cy=".42" r=".7"><stop offset="0" stop-color="${WINE_LIGHT}"/><stop offset="1" stop-color="${WINE}"/></radialGradient>
       <rect x="-2000" y="-2000" width="6000" height="6000" fill="url(#g)"/>`
    : '';
  const r = radius ? ` style="border-radius:${radius}px"` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${ICON_BOX}" width="${size}" height="${size}"${r}>${DEFS}${bg}
    <use href="#nf-box${s}" fill="${CREAM}"/><use href="#nf-thread${s}" fill="${GOLD}"/><use href="#nf-band${s}" fill="${GOLD}"/></svg>`;
}
function silhouette({ size, colour }) {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${ICON_BOX}" width="${size}" height="${size}">${DEFS}<use href="#nf-mark-icon" fill="${colour}"/></svg>`;
}

// The opening's first frame: the mark at 62% of the screen width, a little
// above centre, on wine. views/partials/nf_splash.php frames it the same way.
function launch(wCss, hCss) {
  const w = 1300 / 0.62, h = w * hCss / wCss;
  const vb = `${650 - w / 2} ${475 - h / 2 - 60} ${w} ${h}`;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${vb}" width="${wCss}" height="${hCss}" preserveAspectRatio="xMidYMid slice">${DEFS}
    <rect x="-4000" y="-6000" width="10000" height="16000" fill="${WINE}"/>
    <use href="#nf-box" fill="${CREAM}"/><use href="#nf-thread" fill="${GOLD}"/><use href="#nf-band" fill="${GOLD}"/></svg>`;
}

// Long-press shortcuts on Android (site.webmanifest): the app's own line
// icons (nav_icon() in app/helpers/nav.php), in cream on wine, kept inside
// the circle Android cuts them to.
function shortcut(name) {
  const glyph = execFileSync('php', ['-r', `require '${ROOT}/app/helpers/nav.php'; echo nav_icon('${name}');`]).toString()
    .replace('<svg ', '<svg x="27" y="27" width="42" height="42" ')
    .replace('stroke="currentColor"', `stroke="${CREAM}"`);
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="96" height="96"><rect width="96" height="96" fill="${WINE}"/>${glyph}</svg>`;
}

// Portrait screens of every iPhone and iPad that can add to the home screen.
const DEVICES = [
  [320, 568, 2], [375, 667, 2], [414, 736, 3], [375, 812, 3], [414, 896, 2], [414, 896, 3],
  [360, 780, 3], [390, 844, 3], [428, 926, 3], [393, 852, 3], [430, 932, 3], [402, 874, 3], [440, 956, 3],
  [744, 1133, 2], [768, 1024, 2], [810, 1080, 2], [820, 1180, 2], [834, 1112, 2], [834, 1194, 2], [1024, 1366, 2],
];

(async () => {
  const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  async function shoot(svg, out, w, h, scale = 1, transparent = true) {
    const page = await browser.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: scale });
    await page.setContent(`<!doctype html><html><body style="margin:0;background:transparent">${svg}</body></html>`);
    fs.mkdirSync(path.dirname(path.join(ROOT, out)), { recursive: true });
    await page.screenshot({ path: path.join(ROOT, out), omitBackground: transparent, clip: { x: 0, y: 0, width: w, height: h } });
    await page.close();
    console.log(out);
  }
  // Rounded tiles where the browser shows the image as it is.
  await shoot(tile({ size: 192, radius: 43 }), 'android-chrome-192x192.png', 192, 192);
  await shoot(tile({ size: 512, radius: 114, grade: false }), 'android-chrome-512x512.png', 512, 512);
  await shoot(tile({ size: 32, radius: 7 }), 'favicon-32x32.png', 32, 32);
  await shoot(tile({ size: 16, radius: 3 }), 'favicon-16x16.png', 16, 16);
  await shoot(tile({ size: 48, radius: 11 }), 'icons/favicon-48.png', 48, 48);
  // Full squares where the phone applies its own mask.
  await shoot(tile({ size: 512, grade: false }), 'icons/maskable-512.png', 512, 512, 1, false);
  await shoot(tile({ size: 192 }), 'icons/maskable-192.png', 192, 192, 1, false);
  await shoot(tile({ size: 180 }), 'apple-touch-icon.png', 180, 180, 1, false);
  // One colour, for the notification bar and themed icons.
  await shoot(silhouette({ size: 96, colour: '#FFFFFF' }), 'icons/badge-96.png', 96, 96);
  await shoot(silhouette({ size: 512, colour: '#000000' }), 'icons/monochrome-512.png', 512, 512);
  for (const name of ['invoice', 'payment', 'tasks']) {
    await shoot(shortcut(name), `icons/shortcut-${name}.png`, 96, 96, 1, false);
  }
  for (const [w, h, s] of DEVICES) {
    await shoot(launch(w, h), `icons/launch/${w * s}x${h * s}.png`, w, h, s, false);
  }
  await browser.close();
  try {
    execFileSync('convert', ['favicon-16x16.png', 'favicon-32x32.png', 'icons/favicon-48.png', 'favicon.ico'], { cwd: ROOT });
    console.log('favicon.ico');
  } catch (e) {
    console.warn('favicon.ico not rebuilt: ImageMagick `convert` was not found.');
  }
})();
