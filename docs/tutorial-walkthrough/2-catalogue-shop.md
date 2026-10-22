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

## Needs a decision

- **7. Stock on the shop.** Confirmed: the shop never reads `stock_qty`,
  out-of-stock items list and can be ordered, and structured data always
  says `InStock`. Not changed, because nothing tells "sold out" apart from
  "doesn't count stock": every physical product tracks stock, and
  products added without an opening count (quick add, CSV import, a blank
  box) sit at 0 or below as they sell. Blocking at 0 would mark much of a
  shop like this one "Sold out" overnight.
  *Recommendation:* a shop setting, "Show items as sold out when their stock
  runs out", off for existing shops and on for new ones. When on: tiles say
  "Sold out" and can't be added, `place_order` refuses them with the
  existing "no longer available" answer, and structured data says
  `OutOfStock`. Services and booked items are never affected.
- **A shop-only hide.** "Hide" takes an item off the shop *and* off the
  item pickers, because that is what `is_active` does. A vendor who still
  sells an item in person but not online can already use Shop Studio's
  "only show these" on the catalogue section.
  *Recommendation:* keep one Hide for now; add a shop-only switch if
  vendors ask for it.
- **Quote requests** (`send_quote_request`, another worker's area) still
  match customers on the exact phone text, so they can duplicate customers
  as orders did. *Recommendation:* use `order_match_customer()` there too.
