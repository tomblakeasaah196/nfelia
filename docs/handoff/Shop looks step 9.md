# Shop looks, step 9: Haven and Stage redesigned (handoff)

Two more looks get a first screen of their own, built the way the others were
([Shop look upgrade playbook.md](Shop%20look%20upgrade%20playbook.md),
[Shop looks step 8.md](Shop%20looks%20step%208.md)). Both lead to a booking, so this step also
adds what a stay costs: a refundable caution fee and cheaper prices for longer stays, set on each
place in Products and charged by the booking whatever look the shop uses.

| Look | Trade | Its first screen |
| --- | --- | --- |
| Haven | Short stays | Dates first: "When are you coming?", then every place says if it's free and what the stay costs, with power, the caution fee and the house rules |
| Stage | Events | Plan your event: guests, the day, colours on a drawing of the hall, extras, the plan adding up, then Hold the date or Send this plan |

## Haven: dates first (`views/storefront/sections/hero_stays.php`)

- **The question first**, on a card over the owner's first photo (else the first place's own):
  "When are you coming?", with Check in, Check out and Guests.
- **The calendar** (a sheet on a phone, a dialog on a computer) shows how many places are free each
  night, opens on the first free night, and counts the nights. Stays are up to 30 nights.
- **Then every place answers**: "Free for your 7 nights", the total ("₦525,000 for 7 nights at
  ₦75,000 a night" when a longer-stay price applies), and what to pay now ("Pay ₦207,500 now to book
  it: the deposit and the ₦50,000 caution fee, which comes back"). Free ones that fit the guests
  slide to the top, then free ones that sleep fewer ("Sleeps 2: you are 4."), then taken ones,
  greyed. **Book these dates** opens the booking page with the dates already picked.
- The dates and guests stay in the address, so a shared link opens on the same answer.
- **Each place** shows its area (its category), how many it sleeps ("sleeps 4", "4 guests"), its
  amenities with **power first** (24-hour power, Wi-Fi, air conditioning, pool, parking, security,
  kitchen, smart TV, read from an options group named like Amenities, else its description), its
  longer-stay prices and its caution fee.
- **House rules** under the places, with the check-in and check-out times.
- Free nights come from a new endpoint, `/stays/free` (never cached): `?month=` gives each night's
  number of free places, `?checkin=&checkout=` each place's answer, priced as the booking will
  charge it. The page itself is the same for everyone, so it stays cached.
- Haven's home list now shows only what else it sells; the places are above. With no place booked
  by the night, Haven keeps its facade.

## What a stay costs (for every look)

- **In Products › Bookings**, once "By the night" is picked, two new fields:
  - **Caution fee**: refundable, paid with the booking.
  - **Cheaper for longer stays**: up to three rows, each "from how many nights" and "price a
    night". A row that isn't cheaper than the nightly price is flagged and never shown or charged.
    Rows are kept as typed, so lowering the nightly price later never loses them.
- **The booking** (`StorefrontController::place_booking`) charges the cheapest night the stay
  reaches (`booking_night_price`), takes the deposit on that total, and records the caution fee
  (`bookings.caution_fee`).
- **On the booking page**, step 3 asks for "the deposit and the caution fee" with a "To pay now"
  line. The house rules sit just before paying. Dates and a note can come in the address (`?checkin=`,
  `?checkout=`, `?date=`, `?note=`).
- **On the guest's booking page and in their emails**: the caution fee, refundable. Once given back,
  the page says when, and they get an email ("Your caution fee is on its way back").
- **For the owner**:
  - The booking shows the caution fee. Once the stay is over (or if it didn't go ahead) it says
    "Give back the ₦50,000 caution fee", with **I've given it back** (`POST /bookings/caution/{id}`).
  - The bookings list says "Caution ₦50,000 to give back".
  - The morning after check-out, from 9am, a reminder: `booking.caution` from `cron/planner.php`,
    once per booking, to the boss.
  - The caution fee is never income: the deposit receipt leaves it out.
- **House rules** are written in **Booking hours** (`booking_settings.rules`): a line each, up to
  eight, with numbering and repeats dropped.
- **Migration** `20261031090000_stays_caution_and_long_stays.sql`: `products.caution_fee`,
  `products.stay_rates` (JSON), `bookings.caution_fee`, `bookings.caution_refunded_at`. It runs
  on deploy (`db/migrate.php`) and is safe to run twice. Old places ask for no caution fee and have
  no longer-stay prices.

## Stage: plan your event (`views/storefront/sections/hero_planner.php`)

- **Photos of events** run across the page, with what clients said set between them (the warm
  published reviews, when the Reviews section is on). They come from the owner's photos (up to
  five now), then each package's own. On a computer they sit in two columns beside the planner.
- **How many guests?** A big number with − and +, a slider with each package marked on it, and
  the package that holds them lights up ("Fits your 200 guests"). Smaller ones say "Too small for
  200". A guest can pick another package. Guest numbers are read from each package's words ("Up
  to 80 guests", "500 guests and more", "1,000+ guests"); the owner can change them in Studio.
- **When is it?** For a package the owner books by the day, the planner checks the day ("Free on
  Saturday 7 November", already booked, or closed). Other packages say the shop will check.
- **Your colours**: eight themes (gold and emerald, royal blue and gold…). Tapping one recolours a
  drawing of the hall: the arch, drapes, flowers, lights, tables and sashes. The owner can choose
  which themes to offer.
- **Add extras**: things the shop sells that aren't packages. One that says "For 50 guests"
  suggests enough for everyone and follows the guest count until the guest changes it.
- **The plan adds up** in a bar at the foot of the card, which stays in view on a phone.
  - **Hold the date** shows when the package is booked by the day and the day is free. It opens the
    booking page on that day, with the whole plan (guests, colours, extras) as the booking's note.
    The deposit is the package's own.
  - **Send this plan** goes to WhatsApp with the plan written out, or to the request form with it
    in the message.
- Without packages that say how many guests they hold, Stage keeps its arch.

## Studio

- **Haven**:
  - **Places, in the order they show**: only places booked by the night are offered.
  - A note pointing to Products for each place's caution fee and longer-stay prices, and to
    Booking hours for the house rules.
- **Stage**:
  - **Guests each package holds**: each package shows "From its words: 250", with a box to type
    the owner's own number.
  - **Colour themes guests pick from**.
- The Button field is hidden for Market, Haven and Stage: their first screens have no button.
- The photo hints say what the photos do on each look.

## Data and code

- `storefront_looks()`: Haven `hero => 'stays'`, Stage `hero => 'planner'` with `slides => 5`.
- New hero fields: `guests` (`sf_clean_guests`: `[{id, guests}]`) and `themes` (keys of
  `SF_THEMES`). `keep_own_products` covers `guests`.
- `app/helpers/bookings.php`: `booking_clean_rules`, `booking_clean_rates`, `booking_night_price`.
  `booking_info` now carries `caution` and `rates`, and `booking_summary` mentions the caution fee.
- `app/helpers/storefront.php`: `storefront_stays`, `SF_AMENITIES`, `sf_stay_facts`, `SF_THEMES`,
  `sf_event_guests`, `storefront_plan`, and new icons (bolt, wifi, waves, car, snow, people, bed,
  moon, lock, tv, pot, palette, quote). `parts/helpers.php`: `sf_stay_rates`.
- `assets/js/storefront.js`: the date helpers are shared now. New sections: "Haven's dates" and
  "Stage's planner", plus the booking page's longer-stay price, caution fee, dates and note from the
  address. `assets/css/storefront.css`: "Haven: dates first" and "Stage: plan your event".

## Motion and its fallbacks

Written into `.ux-profile.md`. Nothing moves by itself. Haven's places slide to their new order when
the answer comes (FLIP, 520ms), and each answer fades in. Stage's hall recolours over 450ms, and its
packages light up as the guests change. Under `prefers-reduced-motion` they just change.

## What deploys automatically

Merging to `main` deploys it, and the migration runs with the deploy. Haven shops whose places are
booked by the night, and Stage shops whose packages say how many guests they hold, switch to the new
first screens on their next visit. Others keep their old ones. Owners can then add caution fees,
longer-stay prices and house rules, or guest numbers and themes, as they like.

## How it was tested

- `docs/handoff/testkit/storefront_test.py`: 363 checks (was 332). New:
  - Haven: its markup, sleeps and amenities with power first, longer-stay prices (the one that isn't
    cheaper left out), the caution fee, the house rules, and no list repeated.
  - `/stays/free`: a week priced as charged, nights counted per month, taken and too-long stays.
  - A real booking at the longer-stay price with the deposit and the caution fee, on the booking
    page and the guest's page.
  - The reminder from the cron (under faketime), the owner's banner and list badge, marking it given
    back (and refused before check-out), and what the guest's page says after.
  - House rules cleaned, and the product form saving the caution fee and steps.
  - Stage: packages with their guests, extras with what they cover, themes, the drawing, the owner's
    numbers kept only for their own packages, themes cleaned, a day-booked package with its deposit
    and free days, and the arch fallback.
- `docs/handoff/testkit/looks_interact.js`: 58 checks (was 45). New:
  - Haven: the calendar's focus, picking a week, every place's answer and pay-now line, the dates in
    the address, a taken place sliding to the end, and guests sorting by what a place sleeps.
  - Stage: the package lighting up, extras suggested and following the guests, the colours
    recolouring the hall, Hold the date with the plan in its note, and Send this plan on WhatsApp.
  - No sideways scroll on Haven and Stage at 390, 800 and 1280.
- `bookings_test.py`: 77/77. It used to stop at its first booking, because it still expected the old
  `BKG-0001` numbers. Booking numbers have carried the business's prefix since the orders hub
  (`S4-BKG-001`), so it now reads the number from the booking. With that, its stay checks run again
  against the new pricing. `shop_links_test.py` 120/120; `seo_test.py` (45/46) and
  `selling_test.py` (130/134) fail exactly the checks they fail on `main`.
- The app's own suites that CI runs all pass locally: engine, flows, cron, sw, ops/*, orders/*,
  docs/sign, auth/* and reports/money. The engine test's sample data gained `caution`.
- In a browser: both looks at 390, 800 and 1280, in their own colours and in Night, and both Studio
  editors with no console errors. The screenshots used the test kit's drawn images, because the
  photo library rate-limited the download.
