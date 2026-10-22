# Tutorial walkthrough 1: sign-up and onboarding

The journey: Adanna, who sells hair in Lagos ("Adanna Luxe Hair"), signs up for the
14-day free trial, goes through the three onboarding steps and the shop setup, and lands
on Home. Each claim from the read-through was checked against the code. Seventeen were
confirmed and fixed; a few needed more than the claim said, and those notes are below.

## Fixed

| # | Claim | What changed | Files |
|---|-------|--------------|-------|
| 1 | After the commit, a failing referral or account lookup ran the catch's `rollBack()`, which throws when no transaction is open, so a new owner saw an error page although the account existed. | The `try` now covers only the transaction. The referral, sign-in and welcome email run after it, and a failed referral is logged (like a failed email), never shown as a failed sign-up. | `app/controllers/AuthController.php` |
| 2 | Verification unlocks nothing, but the banner, the verification email and the "fully unlocked" message said it did. | The words now say what verifying does: "so we can reach you, and so you can reset your password if you forget it". The welcome email says the same. No gating was added. | `views/layouts/app.php` (banner text only), `views/emails/verification.php`, `views/emails/welcome.php`, `AuthController.php` |
| 3 | The wizard said "Step X of 3", but shop setup is a 4th step that can't be skipped, and "Deployment complete" showed on the shop setup screen. | The wizard says "Step X of 4". Step 3 goes straight to shop setup with "Saved. One last step: your shop link and look." (or to Home with "All set" when no shop setup is needed). | `views/onboarding/wizard.php`, `app/controllers/OnboardingController.php` |
| 3a | (Found while fixing 3.) The passkey and add-to-home-screen offers, meant for the first page after sign-in or onboarding, opened over the shop setup screen. | Shop setup passes the offer on to the next page, so it opens on Home after setup. | `app/controllers/ShopSetupController.php` |
| 4 | Bank fields were required in the form but optional on the server. | Not required now. The fields are marked "(optional)" and a line says "No business account yet? Leave this empty and add it later in Settings, under Payment accounts." Before, a half-filled account was dropped without a word. Now the person is asked for all three fields or none, and what they typed is kept. | `wizard.php`, `OnboardingController.php` |
| 5 | Only the browser required a country. The server quietly fell back to ₦. | The server refuses a missing or unknown country: "Choose your country. It sets your money and your Nfelia price." | `OnboardingController.php` |
| 6 | Some values were saved without being checked. | Category must be a `BUSINESS_CATEGORIES` key (else `other`). Currency is no longer posted at all: it comes from the country (see Decided, 2). Tax is held between 0 and 100, and the field has `min`/`max`. Colours must be `#rrggbb` (else the current colour). The font must be a `FONT_PAIRINGS` key (else the current font). | `OnboardingController.php`, `wizard.php` |
| 7 | A phone error on step 1 wiped everything typed. | A refused save (phone, country, half a bank account, a failed save) now returns to the step with every field filled back in (`back_to_step()` and `$old` in the wizard). | `OnboardingController.php`, `wizard.php` |
| 8 | A failed logo upload was dropped without a word, and "Max 2MB" was wrong (`save_image()` takes 20MB). | The other choices on step 3 are still saved, and the owner is told: "Your logo didn't upload. Use a JPG, PNG or WebP picture up to 20MB. You can add it any time in Settings." The label now reads "JPG, PNG or WebP, up to 20MB". The upload box also shows the name of the picked file, so people can see the pick worked. | `OnboardingController.php`, `wizard.php` |
| 9 | Step 3's colour pickers started at navy and terracotta, so an owner who didn't change them lost the burgundy and gold set at sign-up. | The pickers, and the lettering choice, start from the business's current values. The server keeps those values when nothing valid is sent. | `wizard.php`, `OnboardingController.php` |
| 10 | There was no hair or beauty category, though the Glow look exists. | Added `'beauty' => 'Hair & Beauty'`, mapped to `glow`. **`businesses.category` is an ENUM, so this needed a migration**: `db/migrations/20261030091500_hair_and_beauty_category.sql`. This is one new file outside my assigned list, and it can't collide with other work. Everything that lists categories still works: the product suggestion `match`es have `default` arms, the directory falls back to `other`, and the Records Book text falls back to "". | `config/app.php`, `app/helpers/shop_links.php`, new migration |
| 11 | `views/onboarding/step1.php` and `step3.php` are dead. | Confirmed by grep (only `docs/AUDIT_REPORT.md` names them). Deleted. | `views/onboarding/` |
| 12 | The wizard was full of jargon ("Establish structural parameters", "Aesthetic Deployment", "Finalize Deployment"). | Plain words throughout. Step titles: "About your business", "Money and payments", "How your business looks". Labels: "What do you sell?", "Type of business", "Where customers pay you", "Main colour", "Lettering". There's one intro line per step. Every button is "Save and continue". All-caps styling is gone from labels, the title, the step badge, messages and the button. Error messages now read "We couldn't save that. Please try again." | `wizard.php`, `OnboardingController.php` |
| 13 | "…and stock is fine" showed to service businesses. | Service-only businesses see "All clear: nothing overdue and no orders waiting." | `views/dashboard/index.php` |
| 14 | POST /register while signed in made a second business and switched the session to it. | The signed-in check now runs before the GET/POST split, so both go to Home. | `AuthController.php` |
| 15 | The trial's days left showed only in Billing. | Owners on the trial see "13 days left of your free trial. Open Billing" under the date on Home ("Your free trial ends today." on the last day). The count matches Billing's. `subscription_banner()` can't be called from web code, because `subscription_helpers.php` redeclares `is_owner()` (see `cron/planner.php`). So Home reads the subscription row and uses `billing_access()`, and `subscription_helpers.php` is unchanged. | `app/controllers/DashboardController.php`, `views/dashboard/index.php` |
| 16 | `/more` (and so the bell's `/more#notifications`) and POST `/signing/settings` sent an owner to shop setup, losing a signature save. | `more` is exempt. So are both Settings › Signatures saves, `signing/settings` and `signing/mine` (a drawn signature was lost the same way). `signing/sign`, which signs a document, still waits for setup. | `app/middleware/auth_check.php` |
| 17 | "About two minutes". | With four screens and about twenty fields it isn't two minutes. Now: "Set up your business in a few minutes." | `views/auth/register.php` |

The follow-ups for Hair & Beauty outside onboarding are done too (Decided, 4).

**The wizard looks like sign-up** (Video 1 cuts from one to the other). It now uses sign-up's page and pieces from `assets/css/home.css` (plain CSS, not a Tailwind build): the same pink ground, the real mark (`assets/img/nfelia-mark-email.png`) beside "NFELIA", the same fonts (Bricolage Grotesque and Plus Jakarta Sans), the soft rounded white card, the same rounded fields and the burgundy button. What sign-up doesn't have is in a small block at the end of the auth section of `home.css`, all under `.setup-card`: the "Step N of 4" pill, selects, the Products / Services / Both cards, the colour swatches with their codes, and the logo box. The card is 520px wide (sign-up's is 440px), and pairs of fields sit side by side from 560px. Every field name, value and behaviour is as before. Checked at 384px and 1280px with no sideways scroll. Labels now point at their fields (`for`/`id`), and "What do you sell?" is a fieldset. The wizard no longer loads `public.css`. `tailwind.public.config.js` still lists `wizard.php`, which is harmless: the next `npm run build:public` just drops the classes only the old wizard used.

## Not a bug

- **New owners are saved as already verified** (the earlier lead). The column `users.is_email_verified = 1` is never read. Verification lives on `businesses.email_verified_at`, which starts NULL.
- **Claim 15's "call `subscription_banner()`"**. The banner can't be used from web code, as explained in row 15 above. The days-left line was built another way, so the claim still holds.
- **Claim 8's 20MB**. 20MB is the real limit. The server accepts more (`upload_max_filesize` 32M, `docs/ops/INFRASTRUCTURE.md`), so the 20MB in `save_image()` is the cap.

## Decided

Tom's decisions, and what was built for each.

1. **Tax starts at 0%.** The 7.5 came only from the wizard: the column default was already 0.00, and sign-up never set a rate. Step 2's box now starts at 0 (placeholder "e.g. 7.5"), and its hint reads "Add your VAT rate if you charge it." A business that skips the box is stored with 0. Existing businesses are untouched. (`views/onboarding/wizard.php`)
2. **The currency follows the country.** Step 2 no longer offers a currency to pick. It shows the currency of the country chosen in step 1, read-only, with "It comes from your country, Ghana." The server takes it from that country and ignores any `currency_symbol` posted. A country no longer offered keeps the currency already saved. Step 1 already took the currency from the country, and sign-up has no country, so it keeps the column default until step 1. Settings is another worker's. (`app/controllers/OnboardingController.php`, `wizard.php`)
3. **The second colour defaults to gold.** Sign-up and onboarding already used #B69F5E: onboarding's #C27A62 went in the first round, when the pickers began starting from the business's own colours. The `businesses.secondary_color` column default is now #B69F5E too, from `db/migrations/20261030100500_secondary_colour_default.sql` (`ALTER … SET DEFAULT`). Only the default changes; existing rows keep their colours. `db/schema.sql` (the baseline) is left alone, as with other migrations.
4. **Hair & Beauty everywhere.**
   - The shops directory has its own entry, "Hair and beauty" at `/shops/…/hair-and-beauty` (`app/helpers/seo.php`). "Beauty, farms, stays and more" keeps its name, because beauty shops filed under Other still list there.
   - The Records Book says "Hair and beauty" (`app/helpers/records_book.php`).
   - Product suggestions for this category are Wigs, Bundles, Closures, Frontals and Edge Control (`views/products/setup.php`, `views/products/form.php`).
5. **The step badge stays as it is.** Shop setup shows no "Step 4 of 4". The step-3 message ("Saved. One last step: your shop link and look.") carries the count.

Test: `tests/auth/onboarding_test.php` (`bash tests/notifications/run.sh auth/onboarding`, added to CI in `.github/workflows/deploy.yml`). It walks Adanna through the whole journey and checks:
- signing up again while signed in;
- the column default;
- the refused country, with what was typed kept;
- the category check;
- the currency from Ghana, ignoring a posted ₦;
- tax at 0;
- half a bank account;
- colours and font left alone;
- shop setup as step 4, Glow preselected, and the offers held back;
- More and a signatures save before shop setup;
- the days-left line on Home;
- the Hair & Beauty entries.

## Decided in Settings

- **The category can't be changed after onboarding.** Tom chose to add it to Settings › Business, and it is built there (see `3-settings-studio.md`). Changing it keeps the shop's look.

## Round 3: sentence case

These screens are filmed in Videos 3 and 4, and they still used the older all-caps style. They now use sentence case and readable sizes, like the rest of the app.

- **What changed.** `uppercase` and its wide tracking are gone. Labels, headings, tabs, chips, tags and buttons are in sentence case. Text that was 9–11px is now 12px or more (labels 14px).
- **Wording.** Text written in capitals was rewritten in sentence case, for example "New order", "Total invoiced", "Remove customer" and "Step 1: send this exact amount". A few labels also got plainer words:
  - "Personal Information" is now "Contact details".
  - "I Have Paid — Submit for Verification" is now "I have paid: send my receipt" (it shows "Sending…" while it sends).
  - "Update Customer" is now "Save changes".
- **Touch targets.** Buttons and tabs are at least 44px tall.
- **Tables.** On the client page, cells stay on one line and the table scrolls inside its card.
- **What didn't change.** Every field name, value, id, data- hook and behaviour is the same, including the "Kept with you" tab and its "Add money" sheet.

Files:
- `views/customers/view.php` and `views/customers/form.php`, both still CRLF.
- `views/billing/momo_checkout.php`.
- `views/deeds/view.php`. This public page is styled by `public.css`, which isn't rebuilt this round, so it only uses classes that file already has. Its wording follows `pdf_template.php`: "Deed of acquisition", "Issuing party", "Acquired by", "Subject of acquisition", "Total acquisition value".
- `views/customers/index.php` was already sentence case, so it is unchanged.

Checks:
- Each changed page was rendered with sample data at 384px: no sideways scroll, no text under 12px, nothing in all caps.
- `php -l` passes on every changed file, and `node --check` on the MoMo page's script.
