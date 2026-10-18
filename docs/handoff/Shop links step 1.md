# Shop links, step 1: Foundations (handoff)

Every shop now lives at its own address, **name.nfelia.com**, and every owner chooses that name and one
of the ten storefront looks at their next sign-in. This step lays the ground; the looks themselves are
drawn in a later step.

## What changed

- **Shop addresses.** `index.php` serves any `{label}.nfelia.com` request as a shop before a session
  starts, so shoppers never get a cookie. The base host comes from `APP_URL`, so staging needs no code
  change. Routes on a shop: `GET /` (the shop), `POST /checkout`, `POST /quote-request`; anything else
  is a friendly 404. `StorefrontController` (new) extends `PublicController`.
- **Old links retired.** `/biz/{slug}` now says "This shop link no longer works", with no redirect. The
  old `/public/cart-checkout` and `/public/quote-request` answer 410. The `slug` column stays; nothing
  public reads it now.
- **Name rules in one place:** `app/helpers/shop_links.php`. 3 to 30 letters, numbers or hyphens, no
  hyphen at the start, end or twice in a row. Reserved names (www, mail, cpanel, admin, shop…) are
  refused. Names that look like a bank, telco, government body, an official account or Nfelia itself
  wait for approval. Tone marks come off: "Ṣadé" becomes `sade`.
- **Setup screen** at `/shop-setup` (`ShopSetupController`, `views/shop_setup/index.php`,
  `assets/js/shop-link.js`): live name check, up to three free suggestions, address preview, and the ten
  looks with thumbnails, preselected from the business category. `auth_check.php` sends owners there
  until they have a live or pending name and a look. Staff are never sent; billing, settings, support
  and admin stay open.
- **Landing Page** shows the shop link, a "Change link" form (one rename, then "Contact support"), a
  name still being checked, and a "Change look" link back to the setup screen.
- **Admin → Shop names:** approve or turn down flagged names. The founder gets a bell notification
  for each request; the owner gets one for each decision. A turned-down first name sends the owner back
  to setup; a turned-down rename keeps the current link.
- **Shop visibility from the subscription.** Trial (and a first payment still being checked): open, but
  `noindex` (meta tag and `X-Robots-Tag`). Trial ended, past due beyond grace, suspended or cancelled:
  "This shop is closed for now" with a WhatsApp button, `noindex`. The moments match
  `subscription_check.php`.
- **Orders and quote requests are safe.** Names and prices come from `products` (this shop's, active,
  not deleted); unknown or hidden items are dropped; quantity 1 to 999; an empty cart is refused;
  currency comes from the business (NGN when unknown); the shop comes from the address, never the form;
  errors never show internals. WhatsApp uses `businesses.whatsapp`, then the phone, as 234…
- **Hardening.** Owner text inside the shop page's scripts and JSON-LD is escaped, so a tagline can't
  end a script block. Private and protected controller methods are no longer reachable from a URL.
- **Session cookie** is now `__Host-nfelia_session` (Secure, no Domain), so nothing on a shop subdomain
  can set or replace it. **This signs everyone out once;** "keep me signed in" restores those who
  ticked it.
- **Fonts:** `classic_tech` and `elegant_serif` were offered in Settings but missing from
  `FONT_PAIRINGS`; added.
- Migration `db/migrations/20261018090000_shop_links.sql` adds the shop link and look columns to
  `businesses` (idempotent, no data changes).

## What deploys automatically

Merging to `main` runs CI + Deploy: the code reaches the docroot and `php db/migrate.php` applies the
migration. `docs/` and `db/` never reach the docroot.

## What Tom must do

1. **cPanel → Domains:** create `*.nfelia.com` with document root `public_html/nfelia.com` (the same
   folder as nfelia.com).
2. **Certificate:** install a Cloudflare origin certificate covering `nfelia.com` and `*.nfelia.com`
   for the wildcard domain, and exclude `*.nfelia.com` from AutoSSL.
3. **Merge**, then **Cloudflare DNS last:** add the proxied `*` record. Until it exists, links like
   bisi.nfelia.com don't reach the server, and old /biz links already say they no longer work, so do it
   straight after the deploy.
4. Expect everyone to be signed out once. Owners choose their link and look at their next sign-in.
5. Approve or turn down flagged names in **Admin → Shop names** (the bell says when one arrives).

## How it was tested

- `docs/handoff/testkit/shop_links_test.py`: 119 end-to-end checks against `php -S` and MariaDB 10.11
  (routing and 404s, cookies, setup flow, name rules, tampered carts, quote requests, trial noindex,
  closed shops, review queue, renames). Run it as its header says.
- Screenshots of the setup screen (390 and 1280 wide), Admin → Shop names, the Landing Page card and the
  notice pages. `php -l` on every changed file; `ci_step_{2,3,4,5}.sh` pass.

## Worth knowing

- Looks are saved but not drawn yet: shops keep today's design until the step that renders the ten
  looks.
- An old name is free for anyone the moment its shop renames; there is no holding period.
- `views/partials/feedback_modal.php` prints a stray `-->` at the bottom of every app page (line 2,
  from before this step).
- `AdminController::admin_notify()` writes notification types the table's ENUM doesn't have (from
  before this step); the new shop name notices use `general`.
