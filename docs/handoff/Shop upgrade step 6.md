# Shop upgrade, step 6: brand, pages, map, and Atelier redesigned (handoff)

Shops now wear the owner's own brand: the colours and fonts chosen in Settings lead on the shop and
on the links customers open. Every look has a lighter home page, with the full list at `/shop` and
the request form at `/contact`, and an optional map at the bottom. Atelier, the fashion look, is
redesigned: full-screen photos that take turns, one photo kept faintly behind every page, and frosted
panels over it. How to bring the other nine looks up to the same standard is in
[Shop look upgrade playbook.md](Shop%20look%20upgrade%20playbook.md).

## What changed

### Settings › Branding (`views/settings/index.php`, `assets/js/brand.js`, `assets/css/src/brand.css`)

- **Two brand colours**, primary (buttons, links, prices) and secondary (text and dark grounds), each
  with a colour picker and a hex field.
- **Take colours from my logo**: reads the logo in the browser (also a new one not saved yet), fills
  both colours, and offers up to five colours from the logo for each, to tap instead.
- **Two font pickers**, primary (headlines, the shop's name) and secondary (text people read), from a
  library of 56 Google Fonts (`app/helpers/brand.php`, `brand_font_library()`): all 56 can be
  headlines, 30 are good for reading. Each opens a popover with a search box, kinds (serif, sans
  serif, display, script, monospace) and every name drawn in its own font. The names load with one
  small request (Google's `&text=` subsetting: only the letters of the names). Arrow keys, Home,
  End, Enter, Escape and Tab work; the list is a proper listbox for screen readers.
- **A live preview** of the shop's name, a button and an invoice line in the chosen brand.
- Migration `20261028100000_brand_fonts.sql` adds `businesses.brand_font_head` and `brand_font_body`.
  Owners who had picked one of the old font pairs (other than the default) keep it as their brand
  fonts. `font_pairing` is no longer written.

### The brand reaches the shop and the customer's links

- **Shop**: Settings leads, Shop Studio can override for the shop alone. Studio's Colours tab starts
  with "Your brand", Light or Dark, derived from the two colours (`brand_colour_set()`): the dark
  version puts a dark secondary colour under everything, the light one uses it for the words; the
  rest (surfaces, lines, muted text, bands) is mixed from the two, and every text pairing is checked
  to 4.5:1 and fixed if needed. The Fonts tab starts with "Your brand fonts". A look's own colours and
  fonts are still one tap away.
- **Live shops switch to the brand** when they are next drawn: settings saved before carry a version
  number now (`v: 2`), and older ones are moved once (`sf_upgrade_settings()`): brand colours,
  keeping the light or dark feel the shop had, and brand fonts when the owner has chosen any. A shop
  whose owner never set brand colours keeps its look's colours. Nothing the owner wrote changes.
- **Customer links**: invoice, receipt, quotation, delivery note and delivery sign-off pages use the
  brand fonts (`brand_doc_fonts()`). PDFs keep their print fonts.

### Pages of their own, for every look

- **Home** shows a taste: the cover, "New in", a tile per category (or a few products) with "See
  all", the story, reviews and news when there are any, a band with one button to the request form
  ("Start your request"), and the map.
- **`/shop`**: the full list with search and categories, then delivery and payment.
  `/shop#cat=dresses` opens it at a category. If the list is switched off, `/shop` goes home.
- **`/contact`**: the request form first, then every way to reach the shop, the questions, and the
  map. `/contact#quote=Agbada` starts a request about that product.
- **News and offers** keeps its own page, linked from the menu once there is a post.
- **Navigation**: a top bar with the name and the bag; on phones a menu button opens a sheet with
  the pages, on wider screens the pages are links. The footer repeats them.
- Each page has its own canonical address, title and description; the sitemap lists `/shop` and
  `/contact`; the pages are cached like the home page and purged with it.
- Market keeps its "Prices updated" date beside its price list, and its order button is now a
  clipboard so it doesn't look like the menu.

### Atelier, redesigned (`assets/css/storefront.css`, "Atelier: cover, page photo and glass")

- **Cover**: up to five photos or videos, full screen on a phone, with the headline, the line under
  it and two buttons over a fade. More than one photo take turns every 6.5 seconds with a crossfade
  and a slow drift; a bar per photo shows where you are and can be tapped; a swipe moves on; a pause
  button stops it. It also holds while a button in it has focus, while it is off screen and while
  the tab is hidden. One photo stays put. Only the first photo loads with the page; the next one
  arrives while the first shows. Under reduced motion and Save-Data it never moves by itself.
- **Page photo**: one photo kept behind every page at about 15%, blurred a little: the first cover
  photo, another of them, a different upload, or none (Studio › Top of your shop › Page background).
- **Glass**: the header is clear over the cover and frosted once you scroll; the story, reviews,
  news, delivery, questions, contact ways, form and map sit on frosted panels. Where the browser
  can't blur, or the shopper asks for less transparency, the panels are solid. Text over the page
  photo is still checked to 4.5:1 against its darkest or lightest point.
- **Type**: the brand's primary font for the name, headlines and the story's first line; the
  secondary font for everything else.

### Map and directions (`app/helpers/maps.php`, `views/storefront/sections/location.php`)

- A section at the bottom of the home page and on the contact page: a picture of the map in the
  shop's colours with the pin in its accent colour, the address, the owner's note ("Opposite the
  market gate…"), and **Get directions**, which opens Google Maps at the pin.
- The owner places the pin in Studio › Sections › Map and directions in either way:
  - **Find it on the map**: looks the address up with Geoapify, limited to the shop's country, and
    offers up to three places when it could be more than one. The address typed there becomes the
    shop's address in Settings too.
  - **Paste a Google Maps link** (short `maps.app.goo.gl` links are followed, to Google only), an
    Apple Maps link, or plain coordinates.
  - Then drag the pin on the map to the right door.
- The section is optional, like every section: switch it off and neither the map nor the address
  shows there. The footer address keeps following "show address" in Contact.
- The picture is made once per pin, colour and style by Geoapify's Static Maps API, then kept as
  WebP under `uploads/maps/{business}/` and served by Nfelia: shoppers never call Geoapify, and the
  shop still loads if Geoapify is down. Publishing makes it ahead of the first visitor. A failed
  picture is tried again after 15 minutes; meanwhile the address and Get directions still show.
- Search engines get the address and the pin in the shop's data.
- Credits on every map: "Powered by Geoapify | © OpenMapTiles © OpenStreetMap contributors".

### Shop Studio

- **Announcement bar** is pinned to the top of Sections, with its switch, the message, and "Hide it
  after" (an end date, or "No end date"). After the date shoppers stop seeing it; the preview still
  shows it, marked "Ended on …".
- **Tap the preview to edit**: tapping a section in the preview opens its editor.
- The preview follows the editor to the page a section lives on (`/shop`, `/contact`).
- **Top of your shop**: the photos (up to five, in order, with which shows first and which is the
  page background), and the page background choice.
- **Map and directions** editor as above, with a draggable map (Leaflet 1.9.4, vendored under
  `assets/vendor/leaflet-1.9.4/`, Geoapify tiles).

## What deploys automatically

Merging to `main` deploys the code and runs the migration. Shops switch to their owners' brand on
their next visit. Without Geoapify keys everything works except finding an address and the map
picture: owners place the pin from a Google Maps link, and shops show the address and Get directions.

## What the owner must do

1. **Geoapify** (for the map): sign up at geoapify.com (the free plan has 3,000 credits a day) and
   create a project with two API keys.
   - Key 1 goes in the docroot `.env` as `GEOAPIFY_API_KEY=…`. Only the server uses it.
   - Key 2 goes in as `GEOAPIFY_BROWSER_KEY=…`. Shop Studio's map shows it in the browser, so in its
     settings allow only `https://nfelia.com` (Studio's address).
   Without key 2, Studio uses key 1 in the browser; better to set both.
2. **Content Security Policy**: `.htaccess` already allows `https://maps.geoapify.com` for images
   (Studio's map). Nothing to do, unless the policy is moved elsewhere.
3. **RN Afriluxe** (and any Atelier shop that wants the dark version): after the deploy the shop
   takes its Settings brand, #A56203 and #252018, and EB Garamond with Questrial (from its old
   "corporate serif" pair), in the light version because Atelier's own colours were light. For the
   dark version: Shop Studio › Colours › Your brand › Dark, then Publish. Then Sections › Top of your
   shop: add up to five photos and choose the page background; Sections › Map and directions: place
   the pin.

## How it was tested

- `docs/handoff/testkit/storefront_test.py`: 267 checks (was 148). New: `/shop` and `/contact` on all
  ten looks (no PHP messages, cached, canonical addresses, in the menu, the full list off the home
  page), the sitemap, `/shop` with the list off; Settings' font library (at least 20 per role),
  refusing fonts outside it and a headline-only font for reading, saving; brand dark and light (the
  secondary colour as ground or words, every pairing readable), a Studio pair overriding the brand,
  one font link on the live shop, the invoice link in brand fonts; old settings moved once and a
  later pick kept; the announcement's end date (gone from the shop, marked in the preview, bad dates
  dropped); five photos kept of six, none from another shop, bars and timer, lazy photos, the page
  background (first, another, none), one photo still, other looks showing the first; the map (waits
  for its pin, found by address in the shop's country, two places offered, the address saved, not
  found, staff refused, CSRF, pins read from links, refused links and look-alike hosts, the picture
  as WebP, made once,
  directions, credits, data for search engines, switched off). Eight older checks were updated for
  the deliberate moves above (the list, form, delivery and questions now on `/shop` and `/contact`;
  fourteen sections).
- The map checks use `testkit/geoapify_mock.php`, a stand-in for Geoapify's geocoding, static maps
  and tiles; the test starts it when it isn't running.
- `shop_links_test.py` 120/120, `seo_test.py`, `selling_test.py` and `bookings_test.py` fail exactly
  the checks they failed before this change, no more. `tests/ops/policy_test.php` passes.
- In a browser (Playwright, Chromium): Settings › Branding at 1280 and 390 wide (search, keyboard
  picking, saving, colours from a logo); Shop Studio (brand chips, fonts, the announcement box, the
  photo shelf, the map editor, tap to edit, no console errors); Atelier home, `/shop` and `/contact`
  at 390, 800 and 1280, dark and light; Market and Buka pages. No page scrolls sideways.
