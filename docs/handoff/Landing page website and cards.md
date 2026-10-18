# Landing page: your own website, and cards that open (handoff)

The landing page now sells the website every business gets, and the long run of feature rows is a
grid of problem cards. Each card opens a sheet with two real screens. On a phone the page went from
about 18 screens to about 14; on a computer from about 11.5 to about 9.

## What Tom decided

| # | Topic | Decision |
|---|---|---|
| 1 | Hero | Headline stays. Only the line under it changes: "Know who owes you, what you sold and made. Oh, and your own website comes free." |
| 2 | What to call it | "Your own website" on the landing page; the app keeps "shop" and "Shop Studio". |
| 3 | Address | Say name.nfelia.com plainly. A business's own .com is not built and is not promised. |
| 4 | Lead story | Everything in one place: website orders flow into Nfelia. |
| 5 | Proof | One screenshot of a website, with the address showing in the browser bar. |
| 6 | Live example shop | No. Screenshots only. |
| 7 | Visitors from a shop | Yes: "Like Ivie Luxe Hair's website? Get one like it…" |
| 8 | Card words | Problem first, in the customer's words; the fix underneath. Sell the peace of Nfelia doing it for them. |
| 9 | In the sheet | Two real screens, three lines, "Start free". |
| 10 | Which sections | Pains, fixes and extras become one grid. The notebook ("Who owes me… Mama Bisi") stays. The website gets its own cards and FAQ answers too. |
| 11 | Card links | Yes: nfelia.com/#bookings opens that card; Back closes it. |

## What changed

- **Hero line** (`views/home/index.php`), the page title, description and link preview, the
  JSON-LD description and the footer line all mention the website.
- **"Customers order on your website. Nfelia does the rest."** (`#website`), straight after the hero:
  Ivie's website in a phone with a browser bar whose address types in once
  (`ivieluxe.nfelia.com`, about a second, off under reduced motion, recorded in `.ux-profile.md`),
  what Nfelia does when someone orders, and four points (look, no commission, trust, Google).
- **"Sound familiar?"** (`#see`): the notebook beside ten cards. Three are marked "Your website"
  (price list, tracking, bookings). Each card is a link to `#<id>`; the sheet is a `<dialog>` with that
  id, opened by `assets/js/home.js` on `hashchange`, so a shared link opens it and Back closes it.
  ✕, Escape and the backdrop close it too and take the hash off; focus goes back to the card. Without
  JavaScript, `:target` shows the sheet and its close link goes back to the cards. Ids: `owed`,
  `price-list`, `payments`, `tracking`, `profit`, `bookings`, `stock`, `invoices`, `delivery`, `tasks`.
- **Screens**: `assets/img/home/see/<card>-1.webp` and `-2.webp` (540 × 1169, about 29 KB each, loaded
  only when a sheet opens) and `assets/img/home/site-ivieluxe.webp`. The five old feature screenshots
  are deleted. The hero's debts phone is unchanged.
- **Pricing** lists "Your own website at yourname.nfelia.com" first, and "No commission". The steps
  say "pick your website address" and "your website fills up as you go".
- **FAQ**: is the website really free and real (and no own .com for now), do you take a cut, will
  Google find it (only after the first payment), and what happens to the website after the trial
  ("closed for now" with the WhatsApp button).
- **"Shop by Nfelia"** at the foot of every shop now links to `nfelia.com/?from=<name>`
  (`views/storefront/sections/contact.php`). The landing page greets that visitor with the shop's name,
  only when the name is a live shop.
- Sign-in and sign-up share `home.css` / `home.js`; their version numbers moved up with the page's.

## Fixed on the way (separate commits)

- **The delivery sign-off page never loaded Alpine**, so in a browser the signature pad did nothing
  and "Receipt Confirmed!" showed before anyone signed. It now loads the app's own copy of Alpine.
  Checked in Chromium: before, the delivery stays dispatched; after, it is signed off and the review
  link comes back.
- **Product, client, supplier and debt titles were escaped twice**: a wig called
  `Kinky Curly Frontal Wig 20"` showed as `20&quot;` in the app bar and the tab.

## Worth knowing (not changed)

- **Approving a website order does not take stock down.** `OrderController::approve` writes the invoice
  as `paid` or `sent` directly; only invoices sent from Sales run the stock deduction in
  `InvoiceController`. The page therefore never says website orders reduce stock.
- The landing page needs no database, except for `?from=`: if the database is down, `get_db()` stops
  that one request with its JSON error.

## What deploys automatically

Merging to `main` deploys everything. No migration, nothing to do in Cloudflare or cPanel. Shop pages
are cached for five minutes, so the new footer link reaches each shop within five minutes (or at its
next publish).

## How it was tested

- `docs/handoff/testkit/landing_test.js`: 51 checks in Chromium at 375 and 1280 wide (opening by tap
  and by link, the address, Back, ✕, Escape, backdrop, focus back to the card, Tab never reaching the
  page behind, both screens loading, each card having two screens and three lines, 44px targets, the
  address typing and reduced motion, the greeting for a real shop and none for unknown, short, empty or
  scripted names, the shop's footer link, no-JavaScript sheets, no script errors).
- No sideways scroll at 375 or 1280; sign-in and sign-up render with no errors.
- `ci_step_{2,3,4,5}.sh` pass; `php -l` on every changed file.

## Retaking the screens (when the app's screens change)

1. `.env` with `APP_URL=http://localhost:8000` (Chromium keeps the `__Host-` session cookie only on a
   secure origin, and localhost counts), the database from `db/schema.sql` + `php db/migrate.php` +
   `local_proc.sql.txt`, then `npm install`.
2. `php docs/handoff/testkit/seed.php <out>/app`, `mysql smartqaq_nfelia_core < docs/handoff/testkit/planner_seed.sql.txt`,
   `php docs/handoff/testkit/landing_seed.php <out>`.
3. `npm install --prefix <out>/libs chart.js@4.4.1` (the profit page loads Chart.js from a CDN).
4. `php -S 127.0.0.1:8000 docs/handoff/testkit/router.php`, then
   `NODE_PATH=/opt/node22/lib/node_modules node docs/handoff/testkit/shoot_landing.js <out>`. It writes
   the WebP files straight into `assets/img/home/`. Look at them before committing.
