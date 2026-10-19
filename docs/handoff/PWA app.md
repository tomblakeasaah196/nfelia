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

## 5. Built: passkeys and the lock

| Piece | Where |
|---|---|
| Sign-in shared by password, emailed link and passkey; "keep me signed in" | `app/helpers/signin.php` (`account_find`, `signin_start`, `signin_remember`) |
| The lock: after `LOCK_AFTER_SECONDS` (default 7200) unused | server: `lock_engaged()`/`lock_touch()` in `signin.php`, enforced in `app/middleware/auth_check.php` (pages show `views/lock/outline.php`, scripts get 401 with `X-Nfelia-Locked`) and in `dispatch_ajax` (index.php); browser: `assets/js/lock.js` |
| Passkeys (WebAuthn, `lbuchs/webauthn`) | `app/helpers/passkeys.php`, `api/ajax/auth.php`, table `user_passkeys`; browser: `assets/js/passkey.js` |
| Offer after sign-in, then the install invitation | `views/layouts/app.php` (`#nf-passkey`), `lock.js`, `shell.js` (`nfInstallOffer`) |
| Settings → Profile → Passkeys | `views/partials/passkeys_card.php` |

Rules that matter:

- Passkeys belong to `APP_URL`'s host and are checked against its exact origin. Never derive either
  from the request's Host header: a phishing proxy could then relay sign-ins.
- Requests a page makes on its own send `X-Nfelia-Background: 1`, so they do not hold the lock off.
- Sessions live in `storage/sessions` for 12 hours: longer than the lock, so people come back to the lock,
  not a sign-in page. A "keep me signed in" device that returns after the lock time opens on the lock.
- 8 wrong passwords on the lock in 15 minutes end the session.
- Test with `docs/handoff/testkit/lock_test.js` against a copy whose `.env` has `APP_URL=http://localhost:…`
  (passkeys refuse IP addresses) and `LOCK_AFTER_SECONDS=40`. It uses Chrome's virtual authenticator.

## 6. Built: notifications, PDF sharing, contacts

| Piece | Where |
|---|---|
| Each person's choices: Money in, Reminders and morning plan, Low stock, Quiet hours, the two emails | columns on `users` (migration `20261017190000`); More → Notifications (`views/partials/notify_sheet.php`, `assets/js/notify.js`); saved by `api/ajax/notifications.php?action=prefs`. The bell links there (`/more#notifications`) |
| Who buzzes, and when | `app/helpers/notify.php`: `notify_money_in()` (owner and admins, never whoever recorded it), `notify_quiet()` |
| Money in | invoice payments (`InvoiceController::record_payment`), receipts for sales on the spot (`ReceiptController`), new shop orders (`PublicController::cart_checkout`), orders approved with proof of payment (`OrderController::approve`) |
| Reminders, morning plan, low stock | `cron/planner.php` (low stock once a day from 9am, for people who turned it on; it opens `products?stock=restock`, low or out) |
| Quotes past their date, trials about to end | also `cron/planner.php`, on every run: the one cron line the server needs (`docs/DEPLOYMENT_GUIDE.md` part 9). Tested by `testkit/expiry_test.php APPDIR BASEURL` |
| Share PDF | `views/partials/share_pdf.php` on the invoice, receipt and quote pages; `assets/js/phone.js` |
| Pick from phone contacts | `data-pick-contact` buttons on New customer and the receipt form's new client box; `assets/js/phone.js` |
| WhatsApp links open the WhatsApp app on a phone, not WhatsApp's web page first | `assets/js/wa.js`, loaded by every layout (app, shop, website, public notice, Ops); `data-wa` on the order sheet rows and Send a reminder, whose href notes the message on the server (`orders/wa`, `invoices/send_reminder` answer JSON when asked) |

Rules that matter:

- Every alert also lands in the bell. The choices only decide whether the phone buzzes.
- Pushes go out after the page has answered (`after_response()`, which also releases the session), so
  recording a payment never waits on a push service.
- Anyone can place a shop order, so new orders buzz at most 20 times an hour per shop. The bell gets all.
- Share PDF shows only where the browser can share files (Android Chrome and iPhone Safari do); WhatsApp,
  Copy link and Download PDF stay for everyone. The PDF is fetched before the tap on phones, because some
  phones only open the share sheet straight from a tap.
- The contact picker exists on Android Chrome only. Elsewhere the button stays hidden.
- Test with `docs/handoff/testkit/notify_test.js APPDIR OUTDIR` (67 checks). It stands in for phones with
  `push_endpoint.php` on :8097 and decrypts every notification, runs the cron with a fake clock, and puts
  the database and `.env` back as they were.

## 7. Built: the busiest screens

| Screen | Where |
|---|---|
| Home: greeting (Lagos time), money this month with the thread, Needs you, Today (from Tasks), latest invoices, search | `views/dashboard/index.php`, `DashboardController::index` and `::search` |
| Sales: one tab, three lists (Invoices, Receipts, Quotes) at their own addresses | `views/partials/sales_head.php`, `views/{invoices,receipts,quotations}/index.php` |
| Invoice page: who owes what, Record payment (a sheet), share tiles, More (edit, delivery note, PDF, cancel, delete), delivery, payments, the document | `views/invoices/view.php` |
| Receipt page | `views/receipts/view.php` |
| Clients | `views/customers/index.php` |
| Stock (Services for a service business) | `views/products/index.php` |
| Shared pieces | `assets/css/src/pages.css` (built into `app.css`), `app/helpers/screens.php` (filter addresses, dates in words, how a document stands), `views/partials/{invoice_row,pager}.php` |

Rules that matter:

- **Money in** on Home is invoice payments plus receipts for sales paid on the spot. A receipt for an
  invoice payment is the same money as the payment, so it is never added twice.
- **The thread**: the solid accent part is money in hand, the gold stitches what is still owed. It is the
  brand's thread put to work; use it only for paid against owed.
- Text sizes: 1rem for what a row is about, .875rem for what describes it, .8125rem for tags. No all-caps
  labels and no text under 12px outside the business's own document, which keeps its look.
- The business's document (the preview on the invoice and receipt pages) is the same markup as before,
  framed. Its colours and fonts are the business's; don't restyle it with the app's.
- **The layout runs every page's view in its own scope, after the sidebar.** Never use a plain variable name
  in the layout's own loops (`$items` once replaced every page's line items). Its loops use `nav_` names.
- Search results on Home are built as elements, never as HTML: client names come from the public shop.
- Test with `docs/handoff/testkit/screens_test.js OUTDIR` (133 checks): each screen at 375 and 1280 for
  sideways scroll, text size, capitals and 44px controls; filters, search, sort, the payment sheet, copy
  link, the More sheets, staff without money, and a client named with markup.

## 8. Next

- The client page (`views/customers/view.php`), quote page and the forms still use the older design
  inside the new shell.
- Shop checkout takes item prices from the shopper's browser (see PR C): price items from the products table.
