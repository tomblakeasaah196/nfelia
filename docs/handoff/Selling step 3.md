# Selling, step 3: product pages, order tracking and reviews (handoff)

Every product now has its own page and link, every order gets a private tracking link, and shoppers
whose orders were delivered or collected can leave a review that shows on the shop.

## What changed

- **Product pages**, `name.nfelia.com/p/12-adire-kaftan` (`StorefrontController::product_page`,
  `views/storefront/pages/product.php`): photos with thumbnails, price, choices, quantity, note, "Add
  to bag", "Ask on WhatsApp", the delivery and payment note, the shop's rating, and "More from the
  shop" (same category first). The number is what counts: `/p/12` or an old name is sent on (301) to
  the current address. Products that are switched off, deleted or left out of "only show these" have
  no page. Pages are cached like the shop (5 minutes, purged on publish and product edits) and carry
  Product and breadcrumb data, `og:type product` with the price, and a JPEG link-preview photo made on
  first request (`uploads/cache/og/…jpg`, at most 1200 wide and under 290 KB, so WhatsApp shows it).
  Trial shops' product pages stay out of search engines like their shop.
- **On the shop**, tapping a product still opens the quick sheet, but the address bar now shows the
  product's page, so it can be copied and shared, and Back closes the sheet. A new tab, or a search
  engine, gets the real page. Shop Studio's preview keeps sheet-only links.
- **Share and Status image** on product pages and sheets: Share opens the phone's share sheet (or
  copies the link); "Image for your Status" draws a 1080 × 1920 picture of the product in the shop's
  colours and fonts with its short link (`name.nfelia.com/p/12`), made on the phone, ready to share to
  WhatsApp Status or save.
- **Order tracking links**, `name.nfelia.com/order/{token}` (`orders.public_token`,
  `app/helpers/shop_orders.php`, `views/storefront/pages/order.php`): the steps (received, payment
  checked or confirmed on WhatsApp, being prepared, on its way, delivered or collected), what was
  ordered, delivery or pickup, payment, the receipt once there is one, "Order again" (what's still on
  sale), and a WhatsApp button. The checkout's last step links to it, the WhatsApp order message
  carries it, shoppers who gave an email get it by email (`views/emails/order_received.php`, sent after
  the checkout answers), and the bag lists the last five orders made on that phone. The page is
  private: never cached, never indexed, no address passed on to other sites, and it keeps working if
  the shop is closed or switched off. Orders from before this step get a link the first time the owner
  opens them.
- **Owner's order screen** shows "Customer's links": copy or send the tracking link on WhatsApp, and
  the review's state (waiting, with "Ask for a review on WhatsApp", or the stars and words).
- **Reviews** (`reviews` table, `/review/{token}`, `views/storefront/pages/review.php`): one per order,
  offered only once the delivery is signed for or the owner marks the order fulfilled. The shopper
  gets the link right after signing, on the tracking page, and from the owner on WhatsApp. Stars (1 to
  5) and up to 600 characters; it shows on the shop as "Bisi A." and can't be changed by anyone. The
  owner gets a notification and the shop's cache is purged.
- **Reviews section** (`views/storefront/sections/reviews.php`): on by default, shown once the first
  review arrives, with the average, the count and the six newest. Owners can switch it off, retitle
  it or move it in Shop Studio. Product pages show the shop's rating. The data carries no star rating
  (search engines ignore a shop's own reviews of itself).
- **Admin → Reviews** (founder): every review, with filters for 1 or 2 stars and hidden ones. Hide a
  review that breaks the rules (abuse, private details, spam); the owner sees it was hidden by Nfelia.

## Fixed on the way (these were broken in production)

Reviews depend on deliveries, and the delivery path didn't work:

- **Delivery lines** were written to `delivery_items.delivery_note_id`, which production ties to the
  retired `delivery_notes` table, while every delivery screen reads `delivery_id`. So approving a shop
  order for delivery failed, and so did saving a delivery note with lines. Migration
  `20261018150000_delivery_items_belong_to_deliveries.sql` adds `delivery_id` (tied to `deliveries`)
  and `notes`, and frees the old column; old lines are kept. Order approval and the purge tool now use
  `delivery_id`.
- **The public delivery note and the sign-off page** crashed (a class they use wasn't loaded), and
  showed no business name. Both fixed.

## What deploys automatically

Merging to `main` deploys the code and runs both migrations. Nothing else is needed.

## What the owner must do

Nothing new. The Cloudflare cache rule from step 2 already covers product pages; order and review
pages send `no-store`, so they are never cached.

## How it was tested

- `docs/handoff/testkit/selling_test.py`: 135 end-to-end checks (product pages in all ten looks,
  redirects, 404s, trial and closed shops, link previews and their JPEG, checkout and tracking links,
  WhatsApp orders, approval, dispatch, signing for a delivery, the review form and its errors,
  escaping, one review per order, fulfilled pickups, the reviews section, admin hide and show,
  orders from before this step, switched-off shops).
- `docs/handoff/testkit/selling_interact.js`: 23 checks in Chromium (the sheet's address and Back,
  the buy box, Share, the Status image, checkout to tracking link, recent orders, "Order again", the
  star rating, no sideways scroll at 375, 390 and 1280 wide, no script errors).
- Step 1 and step 2 kits still pass: `shop_links_test.py` 119, `storefront_test.py` 148 (two checks
  updated: the reviews section is now offered), `storefront_interact.js` 20 (two checks updated: the
  sheet now shows the product's address).
