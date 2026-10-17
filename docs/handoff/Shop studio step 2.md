# Shop Studio, step 2: the ten looks and the Studio (handoff)

Shops are now drawn from one renderer in one of ten looks, and owners edit them in Shop Studio, which
replaces the Landing Page screen. Pages are lighter and can be cached at Cloudflare.

## What changed

- **Looks engine.** `app/helpers/storefront.php` holds the ten looks (Atelier, Buka, Glow, Volt,
  Market, Stage, Harvest, Shea, Haven, Desk) as data: colour tokens, font pair, shapes and signature
  details, plus the ten font pairs, colour sets (each look's own, Calm, Bold, Earth, Night), section
  types and contrast checks that darken an accent until text on it reaches 4.5:1. Views live in
  `views/storefront/` (shell, parts, one partial per section). One hand-written stylesheet,
  `assets/css/storefront.css`, and one small script, `assets/js/storefront.js` (bag, checkout, quote
  form). No Tailwind and no Alpine on shop pages; only the chosen font pair is loaded.
- **Sections:** announcement, hero, featured ("New in"), catalogue with category filter, about,
  video, delivery and payment, FAQ, quote form, contact. Reviews, booking and news are registered but
  stay hidden until steps 3 to 5.
- **Shop Studio** (`/landing-page`, nav label "Shop Studio"; `LandingPageController`,
  `views/studio/`, `assets/js/studio.js`, `assets/css/studio.css`): live preview of the draft, tabs
  for Look, Colours (look set, four moods, three palettes from the logo, a free picker that darkens
  unreadable colours, checked again on the server), Fonts and Sections (switches, drag or up/down to
  reorder, tap to edit). The draft saves itself; undo and redo; Publish; the last ten publishes can be
  brought back into the draft. The old Landing Page form is gone.
- **Setup screen** now offers a full-size preview of each look, drawn with the owner's own products.
- **Settings storage.** Migration `20261018120000_storefront_studio.sql`: draft and published settings
  (JSON) on `businesses`, `storefront_versions` (last ten publishes), `hide_nfelia_credit`, and
  `shop_name_holds`. A shop that never opens Studio is drawn from its older columns (tagline,
  about_text, delivery_info…), so nothing breaks on deploy.
- **"Shop by Nfelia" add-on:** the founder switches it per shop in Admin.
- **Photos:** WebP copies at 320, 640, 960 and 1280 wide are made on first request under
  `uploads/cache/` and then served as plain files; pages use `srcset` with width and height. Hero video
  doesn't autoplay on Save-Data or slow connections.
- **Caching:** shop pages send `Cache-Control: public, max-age=0, s-maxage=300` and never a cookie;
  the Studio preview and setup previews send `no-store`. Publishing, and editing products or business
  details, purges that shop's hostname through the Cloudflare API (`app/helpers/cloudflare.php`) when
  `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID` are set; without them it does nothing.
- **Fixes from step 1:** a renamed shop's old name is held for 90 days and sends people to the new
  link; "keep me signed in" uses `__Host-nfelia_remember` (the old cookie is cleared, never trusted);
  the stray `-->` on every app page is gone; admin notices with an unknown type arrive as `general`;
  the unused `views/quotations/business.php` is deleted.

## What deploys automatically

Merging to `main` deploys the code and runs the migration. Nothing else is needed for shops to work.

## What the owner must do (when the shops go live)

1. **Cloudflare → Rules → Cache Rules → Create rule** "Cache shop pages":
   - Custom filter expression:
     `(http.host contains ".nfelia.com" and not http.host in {"www.nfelia.com"})`
   - Cache eligibility: **Eligible for cache**.
   - Edge TTL: **Use cache-control header if present, bypass cache if not**.
   - Browser TTL: **Respect origin**.
2. **Optional, for instant updates:** create an API token (My Profile → API Tokens → Create Token →
   Custom token) with one permission, Zone → Cache Purge → Purge, limited to nfelia.com. Put it in the
   docroot `.env` as `CLOUDFLARE_API_TOKEN=…` with `CLOUDFLARE_ZONE_ID=1a0e9a750a9786c0d9f236c168507e15`.
   Without it, shops catch up within five minutes.
3. Expect one more sign-out for anyone who ticked "keep me signed in".

## How it was tested

- `docs/handoff/testkit/storefront_test.py`: 148 end-to-end checks (all ten looks render with no PHP
  messages, draft, publish, versions and restore, contrast fixing, owner text escaped, other shops'
  photos refused, cache headers, no cookies on shop pages, WebP copies and refused paths, purge no-op,
  name holds and redirects, the add-on, the remember-me cookie, the fix-ups).
- `shop_links_test.py` (step 1) still passes, 119 checks; four checks were updated for the deliberate
  changes above (old names held, checkout settings moved into the script file, preview buttons).
- Full-page screenshots of every look at 390 and 1280 wide (`shoot_storefront.js`) compared with the
  concept board. The sandbox can't reach Google Fonts, so the shots use system fonts.
