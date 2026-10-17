# Bookings with deposits, step 4 (handoff)

Services can now be booked on the shop: the shopper picks a free time (or the nights of a stay, or a
whole day), pays the deposit by bank transfer, and the owner confirms it. Confirmed bookings go on Tasks
& Calendar.

## What changed

- **Products can be booked** (`products.booking_mode`, set on the product form under "Bookings"):
  - a **time slot** lasting a set time (braids, nails, a fitting, AC servicing);
  - **by the night** (a room or apartment; the price is per night);
  - a **whole day** (an event package; how many bookings a day).

  The deposit is a share of the price, a fixed amount, or none.
- **Booking hours** (Bookings → Booking hours, `businesses.booking_settings`): opening hours per day,
  how much notice, how far ahead, how often start times come, how many customers at once, and
  check-in and check-out times for stays.
- **Free times** (`app/helpers/bookings.php`): a shop's time slots are shared ("at once" customers at a
  time) and skip the owner's own appointments in Tasks & Calendar; nights and days are per product.
  Past times, closed days and times inside the notice are never offered. A request holds its time
  while the owner checks the deposit.
- **On the shop**:
  - bookable products get **Book** instead of "Add to bag", everywhere they show;
  - a **Bookings section** lists them with their length and deposit (on by default, shown once
    something can be booked; owners can retitle or move it in Shop Studio);
  - product pages and sheets offer "Pick a time and book".
- **Booking page**, `name.nfelia.com/book/{id}-{name}`:
  - a month calendar of free days, then free times for time slots, or two taps (arrive, leave) for
    stays;
  - your details, then the total and deposit with the shop's bank accounts and an optional proof
    screenshot;
  - or "Book on WhatsApp instead".

  The server checks the time again while other bookings wait (a lock on the shop), so two people
  can't take the same time. Prices come from the products table. At most five bookings a day per
  phone number.
- **The shopper's booking page**, `name.nfelia.com/booking/{token}`: where the request stands, when it
  is, the deposit and what's left to pay on the day, "Add to my calendar" (an `.ics` file), and
  WhatsApp. It is private like an order's tracking page. The bag's "recent orders" lists bookings
  too. Shoppers who give an email get one when they book and when the owner confirms, declines or
  cancels (`views/emails/booking_update.php`).
- **Owner's Bookings screen** (`/bookings`, in the menu under Storefront; `BookingController`):
  - requests, coming up and past;
  - each booking shows the deposit proof, "Confirm and add to my calendar", "Decline and free the
    time" with a line for the shopper, then "Mark as done" or "Cancel";
  - WhatsApp shortcuts to tell the customer.

  New requests notify the owner.

## What deploys automatically

Merging to `main` deploys the code and runs migration `20261018160000_bookings.sql`. Nothing else is
needed. Until an owner turns on bookings for a product, nothing changes on their shop.

## What owners do to start

1. Open a service in Products, choose how people book it, how long it takes and the deposit.
2. Check Bookings → Booking hours (defaults: Monday to Friday 9am to 6pm, Saturday 10am to 4pm, 12
   hours' notice, two months ahead).
3. Make sure Settings has their bank account, so the deposit can be paid by transfer.

## How it was tested

- `docs/handoff/testkit/bookings_test.py` (seed: `bookings_seed.php` after `storefront_seed.php`):
  77 checks. They cover:
  - Book buttons, the section and product pages;
  - the booking page's caching and indexing;
  - free days and times, notice and the booking window;
  - overlapping requests refused, "at once" capacity, the owner's own appointments skipped;
  - stays (overlaps refused, back-to-back stays allowed, 30-night limit) and whole days (one per
    package a day);
  - the rate limit, contact checks, closed shops and the proof upload;
  - the private booking page and the calendar file's time in UTC;
  - the owner's screens: confirm (adds the appointment), decline (frees the time, shows the reason,
    escaped), cancel (takes it off the calendar), done, and the form token;
  - booking hours taking effect, the product form, and Shop Studio.
- `docs/handoff/testkit/bookings_interact.js`: 19 checks in Chromium. They cover the sheet's Book
  link, picking a day and a time, totals and deposits, sending, the done screen and its link, the
  bag's recent bookings, a taken time disappearing, a two-tap stay booked on WhatsApp, and no
  sideways scroll at 375 and 1280 wide.
- Earlier kits still pass: 119, 148 (two checks updated: twelve sections now), 135, and the browser
  tests (20 and 23).
