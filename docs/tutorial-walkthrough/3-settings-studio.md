# 3. Settings and Shop Studio

What we found while following a hair vendor in Lagos through setting up her
business: Settings (business details, logo, branding, payment accounts,
document numbers, signatures, team, billing) and Shop Studio. Each lead from
the read-through was checked against the code. Some were right, some were
partly right, and some need a decision from Tom.

`assets/css/src/orders.css` changed, so `assets/css/app.css` needs
`npm run build:css` before the signature pad fix shows.

## Fixed

| # | Claim | Change | Files |
|---|-------|--------|-------|
| 1 | The logo field offers GIF, but `save_image()` refuses it, and the error says "Max 1MB" (1024 is a width in pixels; the real cap is 20MB). | The field takes JPG, PNG or WebP only. It shows "JPG, PNG or WebP, up to N MB", where N is 20MB or the server's upload limit if that is lower (`SettingsController::logo_max_mb()`). A picture that can't be used now says "we couldn't use that logo. Pick a JPG, PNG or WebP picture up to N MB." | `app/controllers/SettingsController.php`, `views/settings/index.php` |
| 2 | A logo over PHP's upload limit is dropped without a word, and "Business profile updated." still shows. | Any upload error stops the save with "Nothing was saved: that logo is over N MB…" or "…your logo didn't arrive". A file too big for the whole form (`post_max_size`) used to empty the form, so the person got "Security session expired" and was sent away. Now it says the file is too big, anywhere in the app (see "Follow-ups"). The page also checks the size as soon as a picture is picked. | `app/controllers/SettingsController.php`, `views/settings/index.php` |
| 3 | "Processing… Ready to Save" is a fake 600ms `setTimeout`. There's no way to remove a logo, although `remove_logo` works. | The fake state is gone. The picked picture shows at once, with "Save changes below to use this logo." The button reads "Upload logo" or "Change logo". Owners get a "Remove logo" box, the same pattern as "Remove" on the avatar. | `views/settings/index.php` |
| 4 | In the add-staff form, Role and Password share one grid cell. | Each field has its own cell in a 2-column grid: Name and Email, then Role and Password. | `views/settings/index.php` |
| 5 | "Admin — Full access except billing" isn't true. Admins can't change business details, payment accounts, the prefix or the team. | The options read "Staff — Day-to-day work" and "Admin — Also money, refunds and the shop". The note under them lists what admins can do (expenses, debts, profit, refunds, cancelling orders, the shop, signing) and what only the owner can change. Checked against `require_boss()` and the owner-only checks. | `views/settings/index.php` |
| 6 | A failed role change goes back to `dataset.prev`, which is never set, so it always shows "staff". | The select carries `data-prev` with the member's role. It goes back to that role on an error, and now also when the network fails. | `views/settings/index.php` |
| 7 | The team list puts staff above the owner (`ORDER BY role DESC`). | Confirmed: `role` is `ENUM('owner','admin','staff')`, so DESC sorts staff, admin, owner. The query now uses `ORDER BY FIELD(role, 'owner', 'admin', 'staff'), full_name`. | `app/controllers/SettingsController.php` |
| 8 | Team rows overflow at 384px. | The row wraps (`flex-wrap`, gaps), the name and email can shrink, a long email breaks instead of spilling, and the actions wrap. These classes are already in the built `app.css`. | `views/settings/index.php` |
| 9 | Signatures are stretched on phones. | The fault was in the drawing pad. The 600×200 canvas showed at full width × 160px (about 2:1 on a phone), so a signature drawn there came out squashed when shown at its real 3:1. The pad now keeps 3:1 (`height: auto; aspect-ratio: 3 / 1`). See "Not a bug" for the saved picture. | `assets/css/src/orders.css` |
| 10 | Studio's shop-name field allows 40 characters but the limit is 30. | The field now stops at `SHOP_NAME_MAX`. Its help text reads "Use 3 to 30 letters, numbers or hyphens; spaces become hyphens." | `views/studio/index.php` |
| 11 | Studio only has "Copy link". | "Share on WhatsApp" sits next to it: `wa.me/?text=Have a look at my shop: <link>`. Studio is a standalone page, so it now loads `assets/js/wa.js`, which opens the WhatsApp app on phones as the rest of the app does. | `views/studio/index.php` |
| 12 | The examples are aimed at Cameroon (MTN MoMo or Orange Money, 670 32 59 25, "Quality Auto Parts…"). Nigerian account numbers aren't checked. | Examples follow the business's country (`SettingsController::pay_examples()`). Nigeria: "GTBank", "Opay, Moniepoint or PalmPay", "0803 123 4567". Cameroon keeps its examples. Elsewhere: "MTN MoMo or Airtel Money", with no number example. The tagline example is now neutral. For a Nigerian business, a bank account needs exactly 10 digits. An 11-digit number starting with 0 gets "That looks like a phone number… for Opay or PalmPay, leave off the first 0." | `app/controllers/SettingsController.php`, `views/settings/payment_accounts.php`, `views/settings/index.php` |
| 13 | The default secondary colour is #C27A62 in the view and #c5a55a in the controller. | Both fall back to #B69F5E, the colour used at sign-up and by `theme_tokens()` and `brand_doc_colours()`. Not much changes in practice: the column is NOT NULL, and the hex field has a `pattern`, so only a missing or bad value ever reached these fallbacks. | `app/controllers/SettingsController.php`, `views/settings/index.php` |
| 14 | During the trial, billing shows "Current Plan ₦10,000/month", as if she were already paying, and the history shows raw event names. | A trial reads "Free trial · 9 days left" (or "ends today"), then "Then ₦10,000 a month." The days are no longer repeated in the box below. History rows read "Paid by card", "Card payment failed", "Card renewals stopped" or "Month opened by Nfelia", and the status reads "Done" or "Failed". | `views/settings/index.php` |
| 15 | Settings has no WhatsApp field. The shop's WhatsApp buttons use the onboarding number first, so changing Phone in Settings doesn't change them. | Business details now has a WhatsApp field, checked like the phone (`phone_problem`). Its hint reads "Your shop's WhatsApp buttons open this number. Leave it empty to use your phone." It is saved to `businesses.whatsapp` and the session. | `app/controllers/SettingsController.php`, `views/settings/index.php` |

## Not a bug

- **9, the saved picture.** The saved signature in Settings (`.nf-sign-have img`) is 72px high with its width set automatically, so it keeps its shape. Only the pad was wrong.
- **10, "No spaces".** Studio never said it. The copy was in `views/shop_setup/index.php`, which also allowed 40 characters. That page is now fixed too (see "Follow-ups"). Studio's live check already said "Keep it to 30 characters or fewer", so the 40 misled no one. It only let people type past the limit.
- **Studio's map "silently" replacing the Settings address** (`LandingPageController::location_find`). It wasn't silent. The field's hint says "As in Settings: changing it here changes it there too, and on your invoices." Tom decided to keep the one shared address and say so when it changes (see "Decided").

## Decided

Tom's answers, October 2026, built on this branch.

| Question | Decision | Change | Files |
|----------|----------|--------|-------|
| A trial business couldn't add staff, yet the trial said "Full access to everything." | A trial gets one staff seat: the owner and one staff member, 2 seats in all. After the trial, the plan's seats apply again. Staff added during the trial keep their accounts; only adding more is limited. | The rule lives in one place, `billing_seats()`, with `TRIAL_STAFF_SEATS = 1` in `app/helpers/billing.php`. Adding staff, the Team tab, the plan card and `check_subscription()` all read it. No migration is needed: the trial seat comes from the trial's dates, not from a column. The Team tab reads "1 of 2 seats used" and "Your free trial includes 1 staff member. To add more, pay for a month with more seats." The trial line on Billing reads "Everything is open, and your free trial includes 1 staff member." A refused add says why: during the trial, pay for a month with more seats; after it, every paid seat is taken. | `app/helpers/billing.php`, `app/helpers/subscription_helpers.php`, `app/controllers/SettingsController.php`, `views/settings/index.php` |
| Could the category change after onboarding? | Yes, in Settings › Business. Changing it does not change the shop's look: Shop Studio stays in charge of that. | A Category list from `BUSINESS_CATEGORIES`, checked on the server. A shop that never chose a look shows the one its category suggests (`storefront_default_look`). When such a shop changes category, its current look is pinned in `storefront_look`, so the shop looks the same. The hint says the category places the shop in the Nfelia directory, and that the look is changed in Shop Studio. | `app/controllers/SettingsController.php`, `views/settings/index.php` |
| Two currency settings could disagree. | The currency follows the country. | Settings has no country field, so the currency is shown read-only: "₦ — Nigerian Naira. From your country, Nigeria." Owners are pointed to More › Country and currency (`more#sheet:nf-country`, `api/ajax/set_country.php`), which already changes the country and the currency together. The free currency list is gone, and the server never takes `currency_symbol` from the form. A save now sets the symbol from the country, so one that had drifted comes back to it. A business with no country keeps the symbol it has. Onboarding was handled by another worker. | `app/controllers/SettingsController.php`, `views/settings/index.php` |
| Studio's map changes the Settings address at once, before Publish. | Keep one shared address, and say so. | When "Find it on the map" saves the address, the map's status line (the one under the address) adds "Saved as your address in Settings too." It does this even when the address isn't found on the map, since it was still saved. The Studio status at the top was not used, because the next draft save overwrites it. | `assets/js/studio.js` |
| Should GIF logos be accepted? | No. | No change: the logo field takes JPG, PNG or WebP, and says so. | |

## Follow-ups

| Was | Change | Files |
|-----|--------|-------|
| Any form over `post_max_size` arrives empty, token and all, so `csrf_verify()` said "Security session expired" and sent the person to the sign-in page. | `csrf_verify()` sees an empty form whose length is over `post_max_size`. It says "Nothing was saved: that file is too big (the most is N MB). Pick a smaller one and try again." and goes back to the page the form came from (`back_path()`). Scripts get a 413 with the same words. A small form without its token is refused as before. The check that only Settings had is gone, and `ini_bytes()` and `upload_limit_mb()` are shared. | `app/helpers/auth.php`, `app/helpers/functions.php`, `app/controllers/SettingsController.php` |
| Shop setup allowed 40 characters and said "No spaces." | Now at most 30 characters (`SHOP_NAME_MAX`), with "Use 3 to 30 letters, numbers or hyphens; spaces become hyphens." as in Studio. | `views/shop_setup/index.php` |

Tests:
- `tests/notifications/engine_test.php`: `billing_seats()` during, after and without a trial, and `back_path()`.
- `tests/notifications/flows_test.php`: Kola's trial adds one staff member, is refused a second with the reason, and keeps her account when the trial ends.
- `tests/auth/signin_test.php`: the category changes and the look stays; the currency follows the country and ignores the form; an over-size form is told the file is too big, with `csrf_verify` run in its own PHP with a 1 MB limit.

## Still open

- The `businesses.secondary_color` column still defaults to #C27A62 (`db/schema.sql`), although sign-up sets #B69F5E. It only matters for a business made without sign-up.
