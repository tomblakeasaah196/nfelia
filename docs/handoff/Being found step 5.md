# Being found, step 5: search engines, the directory and news (handoff)

Paid shops are now set up to be found: each tells search engines what to read, lists every page in a
sitemap, and carries data Google uses. Owners can write their own search words, post news and offers,
and choose to be listed in a public directory at nfelia.com/shops. Trial shops stay out of search
engines until their first payment, as before.

## What changed

- **Shop Studio → More → "Found on Google"**:
  - the title and description Google shows, with a preview of the search result, a letter count,
    and fall-backs to the headline and the line under it;
  - "List my shop in the Nfelia directory", and the shop's city (guessed from the address, from a
    list of Nigerian cities).

  These live in the draft like everything else and go live on Publish. Publishing copies the listing
  and city to the shop (`businesses.directory_opt_in`, `city`, `city_slug`).
- **Shop data for Google**:
  - the city is in the shop's address data;
  - a price range ("₦8,000 – ₦45,000");
  - opening hours, when the owner has set booking hours.
  - Product, breadcrumb and article data came with steps 3 and 5.
- **robots.txt and sitemaps** (`app/helpers/seo.php`):
  - `name.nfelia.com/robots.txt` lets search engines into a paid shop, except its private and working
    pages (orders, reviews, bookings, checkout), and points to its sitemap. Trial, closed and
    switched-off shops say "Disallow: /".
  - `name.nfelia.com/sitemap.xml` (paid shops only) lists the front page, every product page, the
    news page and every post, with when each last changed.
  - `nfelia.com/robots.txt` keeps the app (sign-in, dashboard, documents) out of search and points to
    `nfelia.com/sitemap.xml`, a sitemap index of the main site's pages (`/sitemap-pages.xml`: home
    and directory pages with shops in them) and every paid shop's sitemap.
- **The shop directory**, `nfelia.com/shops` (`views/directory/index.php`):
  - paid shops that chose to be listed, filtered by city (`/shops/lagos`), by what they sell
    (`/shops/fashion`), or both (`/shops/lagos/fashion`);
  - each shop is a card in its look's colours, linking to the shop;
  - it carries list data for Google, sets no cookie, and pages with no shops are a 404 that search
    engines skip.
- **News and offers** (`shop_posts`; menu: Storefront → News and offers; `PostController`):
  - owners write a post with an optional photo, publish it or keep it as a draft, edit or delete it;
  - each published post has its own page, `name.nfelia.com/news/{id}-{title}`, with article data,
    a shared-link preview with its photo, Share, and "See what the shop sells";
  - `/news` lists them all, and the shop's **news section** (on by default, shown once there is a
    post) shows the three newest.
  - Publishing, editing or deleting a post purges the shop's cache.

## What deploys automatically

Merging to `main` deploys the code and runs migration `20261018170000_seo_directory_news.sql`.

## What the owner (you) must do

1. **Google Search Console, once for every shop:** add a **Domain** property for `nfelia.com`
   (Search Console → Add property → Domain). Google gives a TXT record. In Cloudflare → DNS, add it
   as a TXT record on `nfelia.com`, then press Verify. One Domain property covers `nfelia.com` and
   every `name.nfelia.com`.
2. In Search Console, open the property, go to **Sitemaps**, and submit
   `https://nfelia.com/sitemap.xml`. Google then finds each paid shop's sitemap from it.
3. Nothing else: new paid shops join the sitemap index on their own, and trial shops join after
   their first payment.

## How it was tested

- `docs/handoff/testkit/seo_test.py`: 46 checks. They cover:
  - the Studio card, the city guess, saving and publishing, and the copied columns;
  - the owner's words in the title, the description and link previews, escaped;
  - city, price range and opening hours in the shop data;
  - robots.txt for the main site, paid, trial, closed and unknown shops;
  - posts (a title is needed, photo, escaping, article data and preview, drafts hidden, `/news`, the
    section, other owners kept out, deleting);
  - sitemaps (valid XML, products and posts, switched-off products and drafts left out, trial shops
    without one, the main index without trial shops, the directory pages);
  - the directory (listed paid shops only, by city, by kind, both, unknown cities, no cookie, list
    data, escaping).
- Every earlier kit still passes: 119, 148 (two checks updated: thirteen sections now), 135, 77,
  and the browser tests 20, 23 and 19.
