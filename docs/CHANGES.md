# NFELIA — CHANGES LOG
**Generated: 2026-06-03 | Full System Audit & Multi-Country Expansion**

---

## Summary

37 audit findings addressed. Phase 3 (multi-country) and Phase 4 (MoMo/Cameroon) built from scratch.

---

## Files Changed

### NEW FILES CREATED

| File | Purpose |
|---|---|
| `app/controllers/CountryController.php` | Phase 3: Country management + payment routing helper |
| `app/controllers/MoMoController.php` | Phase 4: MoMo checkout, receipt upload, admin approve/reject |
| `api/ajax/products.php` | C04: Product search for invoice/quotation builders |
| `api/ajax/customers.php` | C04: Customer search AJAX endpoint |
| `api/ajax/invoices.php` | C04: Invoice search and item retrieval AJAX endpoint |
| `api/ajax/set_country.php` | Phase 3: Store user country selection in session + cookie |
| `views/admin/countries.php` | Phase 3: Country management admin panel view |
| `views/admin/momo_review.php` | Phase 4: MoMo payment review dashboard |
| `views/billing/momo_checkout.php` | Phase 4: MoMo payment screen with receipt upload |
| `storage/momo_receipts/.htaccess` | Phase 4: Block direct web access to receipt files |
| `audit/migrations/001_multi_country_momo.sql` | DB migration: countries, subscription_payments, subscription_status_log tables |
| `audit/BRAND_GUIDE.md` | Formalized brand identity documentation |
| `audit/MULTI_COUNTRY_SETUP.md` | Non-technical guide for adding new markets |
| `audit/.env.example` | Environment variable template with all Phase 3/4 additions |
| `audit/CHANGES.md` | This file |

---

### MODIFIED FILES

| File | Finding(s) Fixed | What Changed |
|---|---|---|
| `app/helpers/functions.php` | C02, M05* | Added `generate_doc_number()` PHP-native implementation; `money()` defaults to session currency |
| `app/helpers/token.php` | C02 | `next_doc_number()` delegates to `generate_doc_number()` instead of missing stored procedure |
| `app/helpers/mail.php` | M13 | Removed insecure SSL `verify_peer: false` bypass |
| `app/helpers/subscription_helpers.php` | C07, enum fix | `link` → `action_url` in notification insert; invalid type values mapped to enum-valid alternatives |
| `app/controllers/PaystackController.php` | C01, C06, C07, H07, Phase 3.4 | Removed duplicate `activate_subscription()`; fixed `event_reference` → `paystack_reference` idempotency check; `link` → `action_url`; removed "Paystack" from user copy; added Phase 3.4 payment method routing |
| `app/middleware/subscription_check.php` | C03 | Fixed 3× redirect from `account/billing` → `settings?tab=billing`; added cancelled status handling |
| `app/middleware/auth_check.php` | C03 | Added missing `avatar_path` and business email fields to remember-me session restore |
| `app/controllers/AuthController.php` | H03 | Fixed `ReferralController` require path (missing `/app/`) |
| `app/controllers/SupportController.php` | H04, C07, L04 | Fixed relative view paths → `BASE_PATH`; `link` → `action_url`; `$_SESSION['flash_*']` → `flash()` helper |
| `app/controllers/SettingsController.php` | C07 | `link` → `action_url` in staff notification insert |
| `app/controllers/ReferralController.php` | C07 | `link` → `action_url`; removed hardcoded ₦2,500 from notification copy |
| `app/controllers/PublicController.php` | C07 | `link` → `action_url` in delivery sign-off notification |
| `app/controllers/AdminController.php` | M01, L04, L05 | Fixed MRR calculation (removed spurious ÷100); `$_SESSION['flash_*']` → `flash()`; 6-query revenue chart → single GROUP BY query |
| `app/controllers/DeliveryController.php` | M05 | WhatsApp number formatting is now country-code-aware (handles +prefix, 00-prefix, 0-prefix) |
| `index.php` | C03, Phase 3, Phase 4 | Added `countries` → `CountryController` and `momo` → `MoMoController` routes; added `set-country` AJAX route; fixed default method mappings |
| `views/layouts/app.php` | H01, C03, Phase 3 | Added `<meta name="csrf-token">`; fixed billing warning link; added country selector widget with flag + currency; added Countries + MoMo nav links for admin |
| `views/settings/index.php` | H07, M08 | Removed "Paystack" from 3 user-facing strings; removed unused Paystack.js script tag; fixed `$ph['description']` → `$ph['event_type']` |
| `views/quotations/view.php` | M07 | Fixed `$user['first_name'].'  '.$user['last_name']` → `$user['full_name']` |
| `views/auth/login.php` | M14, CSS bug | Removed Nigeria/₦ price from meta tags and keywords; fixed `.data-text` and `.font-heading` CSS font-family (removed inner quotes) |
| `assets/js/app.js` | H01, M11 | Removed duplicate `const Nfelia`; CSRF now reads from `window.Nfelia.csrf` via `meta[name=csrf-token]`; removed dead `poll_notifications`; product search uses session currency symbol |

---

## Database Changes Required

Run `audit/migrations/001_multi_country_momo.sql` via phpMyAdmin before deploying.

Creates:
- `countries` table (seeded with Nigeria + Cameroon)
- `subscription_payments` table (MoMo receipt tracking)
- `subscription_status_log` table (audit trail)

Alters:
- `businesses` — adds `country_id` column
- `subscriptions` — adds `country_id` and `payment_method` columns
- `notifications` — extends `type` ENUM with new values

---

## Environment Variables Added

See `audit/.env.example`. New additions:
- `MOMO_RECEIPT_DIR` — path outside webroot for secure receipt storage
