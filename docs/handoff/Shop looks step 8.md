# Shop looks, step 8: Market and Volt redesigned (handoff)

Two more looks get a first screen of their own, built the way Atelier, Buka, Glow and Shea were
([Shop look upgrade playbook.md](Shop%20look%20upgrade%20playbook.md),
[Shop looks step 7.md](Shop%20looks%20step%207.md)). Each still answers Add the way the others do: a
small copy of the photo flies to the bag, which bumps.

| Look | Trade | Its first screen |
| --- | --- | --- |
| Market | Wholesale, retail and distribution | The price board, painted like a shop's signboard, with a trader's calculator, prices for buying more, a carbon order book and "Send us your list" |
| Volt | Gadgets and repairs | Each device under a spotlight, with what a buyer checks first pointed out round it, its condition and battery health |

## Market: the price board (`views/storefront/sections/hero_board.php`)

- **The signboard.** The shop's name painted across the top in the sign colour (stars either side,
  a painter's line round the edge), the headline and line under it, and notes: open now (from booking
  hours, as Buka), the owner's sticker, the smallest order and free delivery. A headline that is only
  the shop's name is painted once. The header above it is slim, so the board starts high.
- **Today's prices**, by category, each row with its unit, its price and a round "+". When prices
  changed today the board says "Prices updated today, 3:46pm". Up to 30 rows, then "See all N prices".
- **The trader's calculator.** "+" opens a calculator for that product: number keys (the first key
  replaces the 1, as on a real one), the sum as it grows ("25 × ₦9,000 = ₦225,000"), and, when the
  product has prices for buying more, the steps as bars with how many more reach the next ("4 more for
  ₦9,300 each"). Once in the order the "+" becomes a stepper; its number opens the calculator again
  to change it. A product with choices opens its sheet instead, as before.
- **Prices for buying more.** Up to three steps a product ("5+ ₦9,300", "20+ ₦9,000"), shown under
  its name on the board and on `/shop`, worked out in the calculator, the bag and the WhatsApp message,
  and **charged at checkout** (below). A step that isn't cheaper than the one before it, or than the
  price (say the owner raised the price since), is never shown and never charged.
- **Since your last visit.** The phone keeps the prices the shopper saw (a visit ends after six hours);
  on the next visit the board marks what moved: "▲ ₦500" or "▼ ₦300", read out as "more/less than when
  you last looked". A first visit marks nothing.
- **Send us your list.** A second tab takes a list typed or pasted the way people send it on WhatsApp
  ("2 bags rice", "spag x10", "two ctn noodles"). Each line is matched to the price list as it is typed
  (units and filler words are skipped, "indomie" is noodles, "veg" is vegetable) and priced; a match
  can be changed to one of the next two, or to "None of these". "Add these to my order" adds them and
  puts the lines it couldn't find in the order's note ("Also on my list: zobo leaves").
- **The carbon order book.** The bag becomes an order sheet: the shop's name (and address when shown),
  the date, Qty, Description and Amount columns, a line that writes itself in when added, a tear line,
  and "Send this order". Under the smallest order it says "Add ₦21,200 more: the smallest order is
  ₦50,000." and waits; between that and free delivery, "₦X more for free delivery."; then "This order
  gets free delivery." The same goal sits in the bag bar.
- No category tiles under the board: the board is the list.

### Studio (Sections › Top of your shop, on Market)

- **Smallest order you take** and **Free delivery from**: amounts, typed with or without commas.
- **Prices for buying more**: pick a product, then up to three steps, "[5] or more [₦ 9,300] each".
  A step that isn't cheaper than the price or the step before is flagged as the owner leaves it
  ("It has to be less than ₦9,600, or shoppers won't see it.").
- The Button field is hidden (the board has none); the Sticker's example and hint fit the signboard.

### Checkout (`PublicController::place_order`)

- Prices still come from the database. For a Market shop, each product's quantities across all its
  lines are added up and the cheapest step reached is charged (`sf_price_at`, never more than the
  product's own price), from the **published** settings only.
- An order under the smallest is refused with 422 and the words above. Free delivery has no fee to
  take off (orders carry no delivery fee yet), so it is shown in the order and in the WhatsApp message
  ("Free delivery: the order is over ₦200,000"), not charged.
- Only the Market look sells this way (`storefront_breaks`): a shop that changes look charges its
  own prices and takes any order, so it never shows one price and charges another.

## Volt: the spotlight (`views/storefront/sections/hero_spotlight.php`)

- **The stage.** Each device stands in a pool of light on a photo studio's sweep, a beam from a lamp
  above and a shadow under it. Photos on white melt into the light (multiply); a phone shot on a table
  has its edges feathered so it fades into the light instead of standing in a box.
- **Callouts** round the device point out what a buyer checks first, drawn in when it arrives: its
  storage ("128GB", "128GB or 256GB"), RAM and processor, its colours ("2 colours"), its battery health
  (with a little battery) and short facts from its description ("Face ID works"). Up to four.
- **Its card** under it (beside it on a computer): its condition as a badge (Brand new, UK used, US
  used, Foreign used, Nigerian used, Refurbished), a battery meter ("Battery health 86% · strong"), its
  price, Add to bag and Ask on WhatsApp. The badge and the meter also show on `/shop`.
- **The owner's sticker** becomes a round stamp ("Tested before you pay").
- **Moving on:** the arrows, a swipe, or a dot. Nothing moves by itself.
- **The repair desk.** A shop that also repairs gets a strip under it: "Repairs from ₦12,000" with
  its first repairs, to its repairs on `/shop`.
- **It works with what owners already write** (`sf_device_facts`): the condition from the name, the
  options or the words ("UK used", "sealed box" is brand new), battery health from "Battery 86%" or
  "battery health: 86%", specs from the name first, then the options.
- **Which devices:** Studio › Top of your shop › Devices in the spotlight (up to 8, in the order
  ticked; services aren't offered), else what is on sale with a photo first, newest first. One device
  stands still with no arrows; with none (only repairs), Volt keeps its spec sheet.

## Data and code

- `storefront_looks()`: Market `hero => 'board'`, Volt `hero => 'spotlight'`. The old `band` hero is
  gone; `spec` stays as Volt's fallback.
- Hero fields (`storefront_section_types()`): `min_order` and `free_from` (kind `money`), `breaks`
  (`[{id, from, price}]`, cleaned by `sf_clean_breaks`: three steps a product, from 2 to 999, each once,
  fewest first). `LandingPageController::keep_own_products()` keeps `picks`, `routine` and `breaks` to
  the shop's own products. Old settings need no change: the new fields start empty.
- `app/helpers/storefront.php`: `storefront_breaks`, `sf_price_at`, `sf_device_facts`,
  `storefront_devices`; each catalogue item carries its shown `breaks`; the theme gains `--sf-sign`
  (the second colour where it reads on the board, else made readable), and `--sf-sign-shade` and
  `--sf-price-shade`, the painted shadows, only where they stand apart from the letters (so the Night
  colours stay crisp). Studio works them out the same way for its preview. `views/storefront/parts/helpers.php`:
  `sf_buy_more`, `sf_price_move`, `sf_qty_control`, `sf_grade_badge`, `sf_battery_meter`.
- `parts/bag.php`: the calculator dialog, the order book's head, tear line and goal (Market only).
  `parts/header.php`: Market's slim band header.
- `assets/js/storefront.js`: `priceAt` and `repriceCart` (the bag's prices follow the steps),
  `orderGoal`, and the sections "The spotlight (Volt)", "The calculator (Market)", "Since your last
  visit (Market)", "The price board's tabs (Market)" and "Send us your list (Market)".
  `assets/css/storefront.css`: "Market: the painted price board" and "Volt: the spotlight". Every colour
  comes from the tokens, so the owner's brand reaches all of it.
- Studio: `moneyField`, `breaksField`, and `picks()` can be limited to some products.

## Motion and its fallbacks

Written into `.ux-profile.md`. Nothing on either moves by itself. Market: a line written into the order
book (520ms) and the price marks (no motion). Volt: the device slides in from the side it came from
(560ms), then its callouts draw in one after another (about 1.2s in all). Under
`prefers-reduced-motion` all of it just appears and nothing flies.

## What deploys automatically

Merging to `main` deploys it. Live Market and Volt shops switch to the new first screens on their next
visit with what they already have: no migration, nothing for owners to do. Owners can then set a
smallest order, free delivery and prices for buying more (Market), or pick their devices (Volt), in
Shop Studio.

## How it was tested

- `docs/handoff/testkit/storefront_test.py`: 332 checks (was 297). New: the board's markup, rows,
  steppers, tabs, calculator and order book; steps and thresholds cleaned, shown and in the page's
  script; checkout refusing an order under the smallest with the right words, adding up a product's
  lines to reach a step, a step per product, a dearer step never raising a price, and a shop that left
  Market charging its own prices; the painted-once name; Volt's spotlight, callouts from names, options
  and words, condition and battery on the card and on `/shop`, photos first, the repair strip, the
  owner's picks kept to their own, one device still with a stamp, and the spec sheet with no device.
- `docs/handoff/testkit/looks_interact.js`: 45 checks (was 28). New: the calculator (opening at one,
  keys typing over, the step reached, the ladder's next step, Add flying, the stepper, changing it),
  the goal under the smallest order and at free delivery, the order book waiting, a WhatsApp-style list
  read and priced with the missing line in the note, the marks since the last visit, and the
  spotlight's arrows, swipe, dots, callouts and Add; no sideways scroll on Market and Volt too.
- `shop_links_test.py` 120/120; `seo_test.py` 45/46 and `selling_test.py` 130/134 fail exactly the
  checks they fail on `main`. `storefront_interact.js` and `bookings_test.py` behave as before.
- In a browser: both looks at 390, 800 and 1280, with real device photos (on white and on a table),
  and Shop Studio's new editors, with no console errors.
