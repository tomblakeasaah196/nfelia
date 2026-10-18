# Shops, reminders and the rest: audit PR 3 (handoff)

The last of the three fix PRs from `docs/AUDIT_2026-10.md`. Shop websites keep the promises they make to
owners and shoppers, reminders follow what really happened to an order, nothing half typed is thrown away,
and the forms and links that crashed now hold up. Items: W3–W5, W7–W18, P1–P3, P6–P19, D3, D4, D7–D13.
It also brings the older test kits up to date with Nfelia Ops.

Already fixed on `main` before this PR, so nothing more here: W9 (a placed order looking like a failure),
P10 (quiet hours for "New task for you"), P12 (`&#039;` in the bell) and P13 (read marks per person), all
from the notifications engine. W6 was in PR 1.

## What Tom decided (and where it lives)

| Topic | Decision | In the code |
|---|---|---|
| Review links | Hidden from owners; each review records the device it came from | `OrderController::view` no longer builds the link; the owner's WhatsApp message sends the shopper's tracking page; `reviews.ip` and `reviews.user_agent` |
| Read marks | Per person | Done on `main` (P13) |
| Staff not paid for at renewal | Their open tasks go to the owner | `planner_hand_back_tasks()` from `SettingsController::billing_keep_only` |

## Calls I made that Tom may want to change

- **A removed staff member's open tasks go to the owner**, not to "anyone". The owner gets the reminders and
  can hand them on. The removal message says how many moved.
- **The WhatsApp button stays on the shop when "Show my phone number" is off.** If the owner gave no
  separate WhatsApp number it uses the business phone, so that number can still be reached through the
  button. There is no switch for WhatsApp on its own yet.
- **Cancelling an invoice deletes its open delivery step** rather than ticking it, so the job doesn't look
  delivered. A job whose steps were all deleted with none done stays open for the owner to tidy.
- **A booking confirmed after its start time gets no reminder at all.**
- **Monthly repeats keep the day they started on**: a task on the 31st falls on 30 April, 28 or 29 February,
  then 31 March again. Existing monthly tasks take their day from their current due date.
- **Tapping outside the task editor, or pressing Escape, keeps what was typed** and says so, with an Open
  button to go back. The editor's own Close button still discards, as before.
- **Tapping a notification while a form is half filled opens it in a new window** instead of replacing the
  page.
- **Reminder emails wait 15 seconds at most** for a mail server, and the outbox sends bell and push before
  email.
- **Checkout with some items no longer on sale** is refused with the list of what's gone. The shop takes them
  out of the cart and asks the shopper to check and order again. (The old kit expected those lines to be
  dropped silently; it now expects this.)
- **The shop directory and its city pages list only cities on Nfelia's list** (`NG_CITIES` in
  `app/helpers/storefront.php`; Ikeja and Lekki count as Lagos). A shop with another city still shows it on its own page; it just
  isn't put in the directory or the sitemap under that city.
- **Replacing a hero photo or video leaves the old file on disk.** Deleted product photos and replaced logos
  go, with their resized copies, and Cloudflare is asked to forget them.
- **A product moved to the bin keeps its photos**, so restoring it brings them back. Purging it removes them.
- **Signing for a delivery still shows the shopper the review link.** Only owners lost it.

## What changed

**Shop websites.**
- W3: the page data for Google, the header and the hero follow "Show my address / phone / email". A hidden
  address leaves only the city.
- W4: Shop Studio won't publish after a failed save. It tries the save again first and says so if that
  fails too.
- W5: deleting a photo or logo removes the resized copies and the share image as well, and asks Cloudflare to
  forget them (`storefront_forget_image()`, `cloudflare_purge_files()`).
- W7: a paid shop whose new name is waiting for review stays in the sitemap and directory, and its tracking
  links can be copied.
- W8: checkout names the items that are gone (409) instead of placing a smaller order quietly.
- W10: after an order, the payment screenshot and note are cleared, so the next order doesn't resend them.
- W11: error pages are never cached.
- W12: when a renamed shop is switched off, its old order, review and booking links still open.
- W13: a quote request is refused when the owner turned the form off.
- W14: owners don't see the review link; each review keeps the IP and browser it came from.
- W15: shops with no bank account say "Waiting to be confirmed", not "checking your transfer".
- W16: names like `bvnupdate`, `mtnbonus`, `secure-login` and `nfe1ia` go to Ops for review.
- W17: the directory city is picked from a known list.
- W18: hero videos must be MP4 or GIF (photos JPG, PNG or WebP). The size limit shown is the one PHP
  enforces.

**Tasks and reminders.**
- P1: removing a staff member, or not paying for them at renewal, hands their open tasks to the owner.
- P2: an order fulfilled, a delivery marked delivered or signed for, or an invoice marked delivered ticks
  the delivery step and finishes the job. Cancelling the invoice removes the step.
- P3: 15 second mail timeout; email goes last in the outbox.
- P6: moving an invoice's delivery date moves the delivery step, its reminder and the job's finish date.
- P7: deleting a job's last open step finishes the job.
- P8: deleting a draft invoice or purging clears tasks' links to it.
- P9: no reminder for a booking confirmed after its start time.
- P11: monthly repeats keep their day (`tasks.repeat_day`).
- P14: tapping outside the editor keeps the draft.
- P15: quick add with "Pick a date" needs a date.
- P16: "Hidden for a week" hides restock for 7 days.
- P17: the morning email counts restock and orders on their own lines.
- P18: a notification tap doesn't replace a half-filled form.
- P19: the trials cron takes the planner lock.

**Forms, routes and records.**
- D3: names starting with letters like "Ọ" or "É" make proper prefixes and links.
- D4: phone numbers are checked and capped on every form that takes them, with a plain message.
- D7: a client's read mark on quote comments saves.
- D8: "Duplicate this product" duplicates.
- D9: Home doesn't count deleted deliveries.
- D10: routes to missing controllers, static helpers or actions that need an id return 404 instead of
  crashing.
- D11: a newly registered owner has a full session, so there are no warnings.
- D12: a deleted client or supplier shows as "(deleted)" instead of a broken link.
- D13: the two backup controllers are gone.

**Older test kits.** `lockdown_test.js`, `shop_links_test.py`, `storefront_test.py`, `bookings_test.py`
and `selling_test.py` now approve payments, shop names, credits, holds and reviews through Nfelia Ops, and
check owners' alerts through the notifications engine.

## What deploys automatically

Merging to `main` deploys everything, including the migration `20261022090000_shops_and_reminders.sql`:
`tasks.repeat_day` (filled in for existing monthly tasks) and `reviews.ip` / `reviews.user_agent`. Nothing to
do by hand. For about a minute while the deploy runs, the new code may meet the old tables; the migration
runs straight after the files land.

## How it was tested

- `docs/handoff/testkit/shops_reminders_test.js`: 34 checks in Chromium against the local app, one or more
  per fix above. Run three times, 34/34 each time.
- The existing kits pass on this branch: `numbers_test.js` 54, `lockdown_test.js` 78,
  `invoice_edit_stock_test.js` 15, `order_stock_test.js` 13, `landing_test.js` 51, `shop_links_test.py` 120,
  `storefront_test.py` 148, `bookings_test.py` 77, `seo_test.py` 46, `selling_test.py` 134, and
  `ci_step_{2,3,4,5}.sh`.
- `main`'s own suites pass as CI runs them (fresh database from the schema and every migration):
  `tests/notifications` engine, flows, cron, sw, ui and `tests/ops` foundation, monitor, clients, sales, ui.
- The migration was applied to the local database twice (it is safe to run again).
