# Shop looks, step 7: Buka, Glow and Shea redesigned (handoff)

Three more looks get a first screen of their own, built the way Atelier was in step 6
([Shop look upgrade playbook.md](Shop%20look%20upgrade%20playbook.md)). Each has one memorable thing
that moves when it is touched, and all three answer a tap on Add the same way: a small copy of the
photo flies to the bag, which gives a little bump.

| Look | Trade | Its first screen |
| --- | --- | --- |
| Buka | Food | The dishes on enamel plates, on a checked cloth, on a table that turns |
| Glow | Hair and beauty | Shoppable stories: a story per service, with its price and Book or Add |
| Shea | Skincare | The skin finder: pick your skin type, get a routine, add it in one tap |

## Buka: the turntable (`views/storefront/sections/hero_turntable.php`)

- **The table.** The dish in front is big, on an enamel plate (a rim in the accent colour with
  little flowers in the band colour); the next ones wait round the sides, smaller and turned, so the
  shopper sees there is more. A drag turns the table with the finger and lets go on the nearest dish
  (a quick flick carries on to the next); the arrows, the dots, or a tap on a waiting dish turn it
  one dish at a time, always the short way. A dish that arrives lets off a little steam.
- **Under it**, the dish's name, a line of its description, its price large, and Add. All the
  dishes' words share one place, so nothing below moves when the table turns.
- **Which dishes:** the owner's picks in Studio (Sections › Top of your shop › Dishes on the table,
  up to 8, in the order ticked), else what is on sale with a photo first, newest first. Fewer than six
  go round more than once so the table is never half empty; one dish sits still with no arrows.
  Without a dish, the owner's photo on a plate, else the enamel plate with the shop's initials.
- **Open now.** When the owner has set booking hours (Bookings › Settings), a sign above the headline
  says "Open now, until 9pm" or "Closed now, opens tomorrow at 8am", worked out in the browser at the
  shop's time, as pages are cached; the page itself says today's hours.
- **The rest of the page:** categories on enamel plates with their names under them, and the party
  orders card dark with the checked cloth along its top.

## Glow: the stories (`views/storefront/sections/hero_stories.php`)

- **On a phone** the stories fill the screen under a clear header, with a bar each at the top. The
  owner's photos open them (up to five; the first carries the headline and the buttons), then one
  story per service or product: its category and, for services people book, how long it takes; its
  name, its price; and **Book a slot** (to its booking page) or **Add to bag**, and **Details**.
- **Like Instagram:** each story shows for 5.5 seconds; a tap on the right goes on, on the left goes
  back; a finger held on the story pauses it; a swipe up opens the details. They stop after the last.
  A pause button stops them, and they also rest while a button in them has focus, while they are off
  screen and while the tab is hidden. Only the story showing can be reached by keyboard or screen
  reader (the others are `inert`).
- **Without a photo** a story is drawn: the look's soft colour in a glow of the accent, the
  product's initials large and outlined, and the words in the ink colour. The header and bars turn to
  ink on those.
- **Rings** under the stories, one per category, jump to its first story (or to its place in the
  list), with one more to all the prices. The home page then shows the first prices, not category
  tiles again.
- **On a computer** (from 720 wide) the story stands in the middle at phone shape, the stories
  before and after it smaller beside it (a click goes to them), on a soft glow made from its own
  photo; arrows sit either side.
- **Which stories:** Studio › Top of your shop › In your stories (up to 8), else what is on sale with
  a photo first.

## Shea: the skin finder (`views/storefront/sections/hero_finder.php`)

- **The question first:** "What's your skin like?" with a chip per skin type. Before a pick the
  routine's steps wait as empty places. A pick builds the routine a step at a time: one product per
  step in the order they are used (cleanse, tone, treat, moisturise, protect), joined by a dotted
  thread, each with what the step does, and **Swap** when another product fits the step too.
  **Add the routine** puts every step in the bag (each as it comes when nobody picks: the first of
  its choices) and shows the total.
- **It works before the owner does anything.** The skin types are the shop's own categories that
  name one ("Dry skin", "Oily skin", "Sensitive"), else Dry, Oily, Combination, Sensitive and Dark
  spots. Each product's step is guessed from its name: soap and cleansers cleanse, toners and mists
  tone, serums and masks treat, butters, creams, lotions and oils moisturise, sunscreen protects;
  perfumes, services and anything else stay out. A product whose words mention a skin type goes first
  for that skin, without being kept from the others. Sold-out products and things people book stay
  out.
- **Studio › Top of your shop › Skin finder:** the skin types (up to six, as tags to remove or add),
  and every product with its step (marked "Our guess" until changed) and the skin types it is for. A
  product the owner sets is only for the skins they tick; none ticked is every skin.
- With fewer than two products in routines, Shea shows its shelf as before.
- The routines are all in the page and CSS (`:has()`) shows the one picked, so the finder works
  before the script arrives; the script adds Swap, the total and Add the routine.

## Data and code

- `storefront_looks()`: Buka `hero => 'turntable'`, Glow `hero => 'stories'` with `slides => 5`,
  Shea `hero => 'finder'`. Buka's button now says "See the full menu".
- The hero section has three new fields (`storefront_section_types()`): `picks` (products),
  `skins` (up to six short labels, each once) and `routine` (per product: its step and skins, cleaned
  by `sf_clean_routine()`; skins not offered are dropped). `LandingPageController::keep_own_products()`
  keeps `picks` and `routine` to the shop's own products. Old settings need no change: the new fields
  start empty.
- `app/helpers/storefront.php`: `storefront_hero_picks()`, `SF_ROUTINE_STEPS`, `sf_routine_guess()`,
  `sf_skins_guess()`, `storefront_skins()`, `storefront_routines()`, `sf_opening_hours()`, and icons
  for the skin types. `views/storefront/parts/helpers.php`: `sf_default_choice()`, `sf_skin_icon()`,
  `sf_clock()`, `sf_hours_today()`.
- `shell.php`: the header sits over Glow's stories as it does over Atelier's cover.
- `assets/js/storefront.js`: "Tap replies", "The turntable", "The stories", "The skin finder" and
  "Opening hours". `assets/css/storefront.css`: one block per look ("Buka: the turntable", "Glow:
  shoppable stories", "Shea: the skin finder"). Every colour comes from the tokens, so the owner's
  brand reaches all of it, dark or light.
- The old `plate` and `soft` heroes are gone (no look used them any more); `shelf` stays as Shea's
  fallback.

## Motion and its fallbacks

Written into `.ux-profile.md`. In short: nothing moves by itself except Glow's stories (with the same
holds as Atelier's cover) and the steam off Buka's first dish once on load. Under
`prefers-reduced-motion` the table jumps, the stories wait for a tap, the routine just appears and
nothing flies. Glass is only over photos, solid under `prefers-reduced-transparency`.

## What deploys automatically

Merging to `main` deploys it. Live Buka, Glow and Shea shops switch to the new first screens on their
next visit with what they already have: no migration, nothing for owners to do. Owners can then pick
their dishes or stories, and check Shea's guesses, in Shop Studio.

## How it was tested

- `docs/handoff/testkit/storefront_test.py`: 297 checks (was 267). New: the turntable's plates and
  words, dishes with photos first, picks kept to the shop's own and in order, two dishes going round
  three times, one dish with no arrows, the open sign from booking hours; Glow's intro and a story per
  service, bars, `inert`, rings, prices on the home page, the header over the stories only on the
  home page, a booked service's length and Book button; Shea's skin types from categories, empty
  places, routines in step order, swaps, totals with default choices, perfume kept out, Studio's
  guesses, skin types and the owner's word cleaned, strict skins, every added skin getting a
  routine, the shelf below two products. One older check moved from Glow to Stage, as Glow now shows
  all its photos.
- `docs/handoff/testkit/looks_interact.js` (new, Playwright): 28 checks of the turntable (drag,
  settle, arrows, dots, the drag not opening the dish, Add flying to the bag, a tap opening the dish),
  the stories (autoplay, taps, hold, swipe up, rings, pause, `inert`, Add, the header turning solid,
  nothing playing under reduced motion), the finder (pick, swap, Add the routine), no sideways scroll
  on home, `/shop` and `/contact` at 390, 800 and 1280, and no script errors.
- `shop_links_test.py` 120/120; `seo_test.py`, `selling_test.py` and `bookings_test.py` fail exactly
  the checks they failed before this change. `storefront_interact.js` stops at the same place before
  and after (it still looks for the full list on the home page, which moved to `/shop` in step 6).
- In a browser: the three looks at 390, 800 and 1280, light and dark (the Night colours), with and
  without photos, with no products at all (the setup screen's look previews), and Shop Studio's new
  editors (picking dishes, the skin finder) with no console errors.
