# The Nfelia app (PWA): handoff

Nfelia installs from the browser onto the phone's home screen and runs full screen like a native app.
This note covers what is built, how to change it safely, and what comes next. Read the ground rules in
`PR2 Tasks and Calendar.md` sections 1 and 2 first (voice, honesty, **never merge without Tom saying so**).

## 1. What Tom decided (12 questions)

| # | Topic | Decision |
|---|---|---|
| 1 | Icon | The mark without the name: cream box, gold thread, deep wine tile. |
| 2 | Opening | The brand film once (first launch after install), then the gold thread on every later start. |
| 3 | Install | Invite after sign-in (one tap on Android, picture steps on iPhone; "Not now" waits 7 days). Also in More, and "Get the app" on the website. |
| 4 | Tabs | Home · Sales · + · Tasks · More. |
| 5 | Moving between pages | Smooth transitions on the current pages: page transitions, prefetch, built styles, caching. |
| 6 | Look | White with a very faint wash of the business colour by default; cream stays as an option. Website fonts (Bricolage Grotesque, Plus Jakarta Sans) everywhere; a business's own fonts only on its documents and storefront. Text not too large, and each person controls the size. |
| 7 | Colours | The business colour is the accent, with a tinted background and a contrast guard. Suggest colours from an uploaded logo. |
| 8 | Dark mode | Light first; dark later. |
| 9 | Sign-in | A 120-minute idle lock that blurs the screen and shows a sign-in sheet. Passkeys (fingerprint, face, phone PIN) unlock it automatically and are encouraged after sign-in. **Never promote shared phones**: each staff member signs in as themselves. |
| 10 | Notifications on by default | Money in, straight away. Reminders and the morning plan. Low stock and quiet hours exist but start off. |
| 11 | Offline | Read what you've opened. A banner says "offline" or "unstable network"; it closes itself after 5 seconds or with ✕. |
| 12 | Phone features | Share PDFs to WhatsApp. Add a customer from the phone's contacts (Android). |

## 2. Built: the app foundation

| Piece | Where |
|---|---|
| Shell: app bar, bottom tabs (phones), sidebar (computers), Back, More page | `views/layouts/app.php`, `views/more/index.php`, `app/helpers/nav.php` (one list feeds the sidebar and More) |
| Create sheet (+), Install sheet, toasts, network banner, page transitions | `views/layouts/app.php`, `assets/js/shell.js`, `assets/css/src/shell.css` |
| Business colour, contrast guard, white or cream, text size | `app/helpers/theme.php` (OKLCH maths); the choice per device is the `nf_ui` cookie, set from More → Text size and background, or Settings → Profile |
| Styles built ahead of time (no Tailwind CDN in the app) | `tailwind.config.js`, `assets/css/src/`, built to `assets/css/app.css` |
| Fonts, Alpine and jQuery served by Nfelia itself | `assets/fonts/`, `assets/vendor/` (copied by `bin/build-vendor.js`) |
| Icons, maskable and monochrome icons, Android shortcuts, iPhone/iPad launch screens | `docs/brand/icons/make_icons.js` writes them from `assets/img/brand/nfelia-mark-parts.svg` |
| The opening (film, then thread) | `views/partials/nf_splash.php`, `assets/js/splash.js`; plays when the start URL carries `?source=pwa`, also on sign-in when the app starts signed out |
| Install details on every page someone may install from | `views/partials/pwa_head.php` (app, landing, sign-in, sign-up), `site.webmanifest` |
| Offline reading, cached files, push | `sw.js`, `offline.html` |
| "Get the app" on the website | `views/home/index.php` (header and footer), `assets/js/home.js` |

## 3. Changing it safely

- **Styles:** edit `assets/css/src/shell.css` or a view, then `npm install` (once) and `npm run build`.
  Commit the rebuilt `assets/css/app.css`; the server never runs npm. Tailwind only sees class names
  written out in full in `views/`, `app/`, `api/` and `assets/js/`: never build one by joining strings.
- **Icons and launch screens:** `CHROMIUM_PATH=… node docs/brand/icons/make_icons.js`, then commit what it writes.
- **Service worker:** bump `VERSION` in `sw.js` whenever a file it precaches changes (`offline.html`, the
  two latin fonts, the 192px icon, the badge). Everything else under `/assets` carries `?v=` and refreshes itself.
- **What is kept on the phone:** only pages sent with `X-Nfelia-Page: app` (the layout sends it). Settings,
  Purge, Admin, MoMo and Countries never are. Kept pages are dropped at every sign-in and sign-out.
  Pages are network first; the kept copy shows when the network fails, or after 8 seconds unless a form
  was just sent (its result must come from the server).
- **Prefetch:** only links in the tabs, sidebar, More and the Create sheet, or marked `data-prefetch`.
  Some links change data when opened (`receipts/from_payment` makes a receipt): never widen the rule to all links.
- **Motion:** app screens stay on the 100/150/200/300ms scale, transform and opacity only. The opening is
  the one exception (see `.ux-profile.md`). No frosted glass.

## 4. Testing

`docs/handoff/testkit/pwa_test.js` (95 checks): the shell, sheets, toasts, the banner, offline reading and
the offline page, the opening, the install invitation on iPhone, text size and background, the website's
Get the app, install details on public pages, sideways scroll at 375px, and a second business's colour.
Run the older suites too; re-seed with `planner_seed.sql.txt` before each Tasks suite (they share data).

Not provable in a headless browser: Android's real install prompt, iPhone standalone mode, the film on a
phone (headless Chromium has no H.264), and push delivery. Check those on real phones after deploy.

## 5. Next

- **B. Passkeys and the 120-minute lock.** WebAuthn (e.g. `lbuchs/WebAuthn`), a `user_passkeys` migration,
  offered after sign-in and in Settings, used automatically by the lock and on the sign-in page. The lock
  is enforced on the server too. Kept pages must respect it: offline, show the lock with "Connect to unlock".
- **C. Notifications, PDF sharing, contacts.** Notification settings (defaults above), money-in pushes,
  Web Share for PDFs to WhatsApp, the Android contact picker on Add customer.
- **D. The busiest screens rebuilt.** Home, a Sales hub (invoices, receipts, quotes), invoice and receipt
  pages, Clients, Stock. They still use the old page designs inside the new shell.
