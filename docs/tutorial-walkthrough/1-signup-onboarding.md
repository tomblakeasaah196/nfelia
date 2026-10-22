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
| 6 | Some values were saved without being checked. | Category must be a `BUSINESS_CATEGORIES` key (else `other`). Currency must be one the list offers (else the current one). Tax is held between 0 and 100, and the field has `min`/`max`. Colours must be `#rrggbb` (else the current colour). The font must be a `FONT_PAIRINGS` key (else the current font). | `OnboardingController.php`, `wizard.php` |
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

Still to do outside this area, for Hair & Beauty to read well everywhere:
- `directory_categories()` in `app/helpers/seo.php` has no `beauty` entry. Beauty shops list under "Beauty, farms, stays and more".
- `records_category_text()` in `app/helpers/records_book.php` gives no words for `beauty`.
- `views/products/setup.php` and `views/products/form.php` could suggest beauty items (Wigs, Bundles, Closures, Frontals, Installation (Service)) instead of the generic list.

## Not a bug

- **New owners are saved as already verified** (the earlier lead). The column `users.is_email_verified = 1` is never read. Verification lives on `businesses.email_verified_at`, which starts NULL.
- **Claim 15's "call `subscription_banner()`"**. The banner can't be used from web code, as explained in row 15 above. The days-left line was built another way, so the claim still holds.
- **Claim 8's 20MB**. 20MB is the real limit. The server accepts more (`upload_max_filesize` 32M, `docs/ops/INFRASTRUCTURE.md`), so the 20MB in `save_image()` is the cap.

## Needs a decision

1. **The tax rate starts at 7.5%.** Step 2 fills in 7.50, and every new invoice and quote then adds 7.5% VAT. Most market traders are small enough not to charge VAT. For now there's a hint, "Put 0 if you don't charge VAT". *Recommendation:* start at 0 and keep the hint.
2. **The category can't be changed after onboarding.** Settings has no category field, so a hair vendor who picked Fashion or Other before Hair & Beauty existed (or by mistake) is stuck with it, and it sets their first shop look and product suggestions. *Recommendation:* add it to Settings › Business, and offer existing Fashion/Other shops on the Glow look a one-tap switch.
3. **"Step 4 of 4" on the shop setup screen.** Shop setup is also where existing owners are sent at sign-in, so a fixed step badge there would confuse them. The step-3 message ("One last step") carries the count instead. *Recommendation:* leave it as is, or show the badge only when coming straight from onboarding.
