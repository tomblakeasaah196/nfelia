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
| 2 | A logo over PHP's upload limit is dropped without a word, and "Business profile updated." still shows. | Any upload error stops the save with "Nothing was saved: that logo is over N MB…" or "…your logo didn't arrive". A file too big for the whole form (`post_max_size`) used to empty the form, so the person got "Security session expired" and was sent away. Now it gets the same message. The page also checks the size as soon as a picture is picked. | `app/controllers/SettingsController.php`, `views/settings/index.php` |
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
- **10, "No spaces".** Studio never said it. The copy is in `views/shop_setup/index.php`, which also allows 40 characters, and that file was outside this pass. Studio's live check already said "Keep it to 30 characters or fewer", so the 40 there misled no one. It only let people type past the limit.
- **Studio's map "silently" replacing the Settings address** (`LandingPageController::location_find`). It isn't silent. The field's hint says "As in Settings: changing it here changes it there too, and on your invoices." The open question is under "Needs a decision".

## Needs a decision

- **A trial business can't add staff, yet the trial says "Full access to everything."** A trial has 1 seat (`db/schema.sql`), and "Add seats" needs a paid plan. A vendor following the tutorial hits "You've reached your seat limit (1)" on the Team tab. *Recommendation:* give a trial a second seat (the owner plus one staff), so the team features can be tried before paying. If not, change the trial line to "Everything except adding your team. Pay for a month to add staff." Tom's call.
- **Two currency settings that can disagree.** Settings saves a currency *symbol*, but `business_currency_code()` prefers the country's ISO code. A Nigerian business that picks FCFA shows FCFA prices, while its orders, bookings and the product data Google reads still say NGN. *Recommendation:* take the currency from the country, and replace the free currency list with "Country", or show the currency as read-only.
- **Studio's map changes the Settings address at once, before Publish.** Invoices pick up the new address straight away, while the pin waits for Publish. *Recommendation:* keep one shared address. When it changes, also say so in Studio's status line ("Saved as your address in Settings too"). Low priority.
- **Should GIF logos be accepted?** `save_image()` could save a GIF's first frame. *Recommendation:* no. JPG, PNG and WebP cover logos, and the page now says so.

## Seen, outside this pass

- `app/helpers/auth.php` `csrf_verify()`: any upload over `post_max_size` anywhere in the app empties the form and shows "Security session expired". This pass handled the logo case only. A general check there would cover the rest.
- `views/shop_setup/index.php`: the field allows 40 characters (the limit is 30) and the help says "No spaces." (spaces become hyphens).
- `views/onboarding/step3.php`, `OnboardingController` and the `businesses.secondary_color` column still default to #C27A62. Sign-up then sets #B69F5E.
