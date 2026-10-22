# 2. Catalogue and shop

What we found while mapping a hair vendor's catalogue and her customers'
journey through the shop, for the tutorial videos. Each lead from the first
read-through was checked against the code.

## Fixed

| # | Claim | Change | Files |
|---|---|---|---|
| 1 | No way to hide or show a product: `toggle_status` had no caller | Product page has **Hide** / **Show again**. `is_active` hides an item from more than the shop (also the item pickers on invoices, quotes and orders, and restock alerts), so the button is "Hide", not "Hide from shop", and a line on a hidden item's page says what it means. `toggle_status` now answers a plain form with a message and a redirect (AJAX unchanged). The list tags hidden items "Hidden" instead of "Inactive". | `app/controllers/ProductController.php`, `views/products/view.php`, `views/products/index.php` |
| 2 | Ticking "Auto-generate" while editing erases the SKU | `edit()` reads `auto_sku` and makes a SKU when it is ticked or the box is empty, as `create()` does | `app/controllers/ProductController.php` |
| 3 | Option prices "25,000" saved as 25, "−2000" as 0 | A small `amount()` parser, the same as `OrderController::amount()` (commas and spaces out) plus "−" read as a minus. There is no shared helper to reuse. | `app/controllers/ProductController.php` |
| 4 | Photo label says 2 MB, 20 MB is accepted | Label says "Up to 20 MB each" (`save_image()`'s limit; the server allows 32 MB a file) | `views/products/form.php` |
| 5 | Selling price starred but not required | The empty price is intended: the shop shows "Ask for the price" (`sf_price_html`). Star removed; a hint says "Leave it empty to show “Ask for the price” on your shop." | `views/products/form.php` |
| 6 | Photo Cover and Delete only appear on hover | Buttons sit in a strip at the bottom of each photo, always shown on touch screens; hidden until hover only where there is a mouse (`@media (hover: hover)`), and shown on keyboard focus | `views/products/form.php` |
| 8 | Tiles show the base price, not the cheapest option | `sf_lowest_price()`: the price plus each group's cheapest option (one is always picked). When that differs from the price, tiles, stickers and the product page say "From ₦80,000". Booked products are left as they were. | `views/storefront/parts/helpers.php` |
| 9 | Tracking headline wrong for 'ready' and for no-transfer orders | 'ready' reads "on its way" for a delivery and "ready. Pick it up from …" for pickup, as the steps do; `payment_method = none` reads "… will contact you on the number you gave to confirm it." The steps in `shop_orders.php` were already right. | `views/storefront/pages/order.php` |
| 10 | "Done" promises a transfer check with no proof | "will check your transfer" only when a screenshot was sent; without one: "If you haven't paid yet, pay into the account you picked. … will check for your transfer …" | `assets/js/storefront.js` |
| 11 | A failed proof upload is dropped silently | The order still goes through (nothing lost). The answer carries `proof_failed`, also for files over the server's upload limit, and the Done screen says the screenshot didn't come through and to send it on WhatsApp (or keep it to show the shop). | `app/controllers/PublicController.php` (`place_order`), `assets/js/storefront.js` |
| 12 | Server doesn't require a delivery address | `place_order` refuses a delivery order with no address, in the bag's own words | `app/controllers/PublicController.php` (`place_order`) |
| 13 | Shop orders duplicate customers by phone format | `place_order` uses `order_match_customer()` (last 10 digits). The same bug was in bookings (`StorefrontController::place_booking`), fixed the same way. New customers no longer get the note "Auto-created from storefront order". | `app/controllers/PublicController.php`, `app/controllers/StorefrontController.php` |
| 14 | Transfer order with no screenshot can't be marked paid on confirm | The "is in my account" switch shows, off, for bank and Mobile Money orders with no screenshot. **`OrderController::confirm` had to change too**: it ignored the switch without a proof. With no proof it now passes `paid_now` (the total) and the order's method, so the receipt is an ordinary transfer payment and doesn't say "proof sent with the order". `orders.php` is unchanged. | `views/orders/view.php`, `app/controllers/OrderController.php` |
| 15 | "Ada's" shows as Ada&#039;s after Remove | The controller no longer escapes the flash; the layout does | `app/controllers/ProductController.php` |
| 16 | Product page spills at 384px | The action row wraps. Stock history: Before, Note and By leave their columns on phones, and the note and who made it go under the type, so the table fits without scrolling sideways. The stock/alert line wraps too. | `views/products/view.php` |

New Tailwind classes (`[@media(hover:hover)]:…`, `sm:table-cell`,
`sm:whitespace-nowrap`, `px-3 sm:px-6`) appear once `assets/css/app.css` is
rebuilt.

## Not a bug

- **1, "make hidden items visibly marked":** they already were (dimmed, with
  "Inactive"). Now tagged "Hidden" for the same words everywhere. The shop
  already respects `is_active`: the catalogue, product pages and checkout
  all leave hidden items out.
- **5, the behaviour:** an empty selling price saving as 0 is the "Ask for
  the price" feature, not a bug. Only the star was wrong.
- **9, "contradicts the steps":** the steps (`shop_order_steps`) were right;
  only the headline above them was wrong.
- **16, the table:** it scrolled inside its own box, so the page itself did
  not spill. Fixed anyway, as it was awkward on a phone.

## Decided

- **7. Stock on the shop: built as a shop setting.** The shop never read
  `stock_qty`, but nothing tells "sold out" apart from "doesn't count
  stock": products added without an opening count sit at 0 or below as
  they sell. So it is a switch, **Shop Studio › More › Sold out: "Show
  items as sold out when their stock runs out"**, saved at once like "Show
  your shop".
  - `businesses.shop_sold_out`
    (`db/migrations/20261030102000_shop_sold_out.sql`): off (0) for every
    business that existed, on (1) by default for new ones. Running it
    twice changes nobody's choice.
  - One rule, `shop_sold_out()` in `app/helpers/storefront.php`: the
    switch is on, it is goods (not a service, not booked), and the
    product's stock is 0 or less. Options have no stock of their own
    (they share the product's count), so the product's count decides.
  - When on: the item's tile price reads "Sold out", its labelled Add
    button is switched off and its "+" goes; its page keeps the price, says
    "Sold out" and offers "Ask when it's back" on WhatsApp; the product
    sheet hides the Add box; the bag drops it, and "order again" leaves it
    out. `place_order` treats it like an item taken off the shop, with the
    existing "no longer available" answer (409 with `unavailable` when
    other items came with it). Structured data says `OutOfStock`.
  - When off: nothing changes from before.
  - Tested in `tests/orders/core_test.php` (last three sections): the
    migration, off as before, the Studio switch, the rule, the page and
    structured data, checkout refusing it, and adding stock putting it back
    on sale.
  - Files: the migration, `app/helpers/storefront.php`,
    `app/controllers/PublicController.php` (`place_order`),
    `app/controllers/LandingPageController.php` (`sold_out`),
    `views/studio/index.php`, `assets/js/studio.js`,
    `views/storefront/parts/helpers.php`, `views/storefront/pages/product.php`,
    `views/storefront/pages/order.php`, `views/storefront/shell.php`,
    `assets/js/storefront.js`.
  - Not changed: an order only takes stock off when the shop confirms it,
    so two shoppers can still both order the last one before then.
- **A shop-only hide: no.** "Hide" stays the single Hide. It takes an item
  off the shop *and* off the item pickers, because that is what `is_active`
  does. A vendor who still sells an item in person but not online can use
  Shop Studio's "only show these" on the catalogue section.
- **Quote requests:** handed to the quotes worker. They still match
  customers on the exact phone text; `order_match_customer()` is the fix.

## Round 3: the item form

Video 2 films Adanna adding her first product, then adding stock. Both
screens now use the app's own sections, fields, tags and sheets (as New
order and Catalogue), in sentence case, phone first. Every field name and
value the form posts is unchanged.

**Add / Edit item** (`views/products/form.php`; edit looks the same):

- Photos come first, with one large "Add photos" button and the picked
  photos shown as thumbnails. A new pick replaces the last one, as the
  file box itself does. Upload and its progress bar work as before.
- Then About it (name, category, description), Price, Stock, Options,
  Bookings and Item code. "Add to catalogue" ("Save changes" when
  editing) stays in reach at the bottom, with Cancel beside it.
- Bookings shows only when the business sells services or both
  (`business_type` service or mixed), when "This is a service" is on, or
  when the item is booked already. Its fields are always sent, so a hidden
  section never clears a booking.
- Margin stays "—" until both prices are in, and is red only when the
  price is below the cost.
- Options: "Add options, like length or colour", and when there are none,
  "No options. Add them if this comes in different lengths, colours or
  sizes."
- Units: "Bundles" (saved as `bundle`) is in the list, and also in the
  hair and beauty units on Add many items. A unit that isn't in the list
  (from a CSV import) now stays chosen. Before, saving silently changed it
  to pieces.

**An item's page** (`views/products/view.php`):

- Photo, name, price ("₦95,000 per bundle"), and tags for hidden, stock,
  category and item code. Then Edit, Hide, Clone and Remove as one row of
  tiles, the stock with "Add stock" and "Take out", the price with its
  margin, and the stock history as a list. The history's filters are All,
  Added, Taken out, Sales, Returns and Corrections.
- The stock sheet asks one thing at a time: Add stock, Take out or Set the
  count; how many; the cost each (only when adding); a note. The button
  says what it will do. It then reports, for example, "Added 10 bundles.
  You have 20 bundles of Body Wave 18in now."

**Units in the plural:** one helper, `stock_count()` with `unit_word()` in
`app/helpers/stock.php`, gives "1 bundle", "8 bundles", "1 box",
"3 pieces"; kg, g, ml, L and m stay as they are. It is used on the item
page, the Catalogue list ("Only 3 bundles left"), the stock messages, the
invoice form's item search and the shop's "per bundle". Restock reminders
on Tasks show no unit, so they are unchanged.

Styles are in `assets/css/src/orders.css` (`.nf-iform`, `.nf-if-*`,
`.nf-pv-*`). Tested in `tests/orders/core_test.php`: the plurals, and
Add stock from the item page.
