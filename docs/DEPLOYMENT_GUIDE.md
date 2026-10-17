# NFELIA — PRODUCTION DEPLOYMENT GUIDE
**Complete Step-by-Step | cPanel Shared Hosting**
**Version: Post-Audit + Multi-Country + Cameroon MoMo Release**
**Date: 2026-06-04**

---

## BEFORE YOU START — READ THIS

This guide assumes you are replacing your entire cPanel installation with the updated codebase.
Do NOT skip steps. Do NOT change the order. Every step depends on the one before it.

**You will need:**
- cPanel login credentials
- phpMyAdmin access
- Your Paystack dashboard open (for webhook URL confirmation)
- About 20–30 minutes uninterrupted

---

## PART 1 — DATABASE MIGRATIONS (Do this FIRST, before uploading any files)

You must run 3 SQL migration scripts via phpMyAdmin **in this exact order**.
All 3 files are in: `nfelia.lpc.cm/audit/migrations/`

### Step 1.1 — Open phpMyAdmin
1. Log in to cPanel → **phpMyAdmin**
2. Click your database on the left: `smartqaq_nfelia_core`
3. Click the **SQL** tab at the top

### Step 1.2 — Run Migration 001 (Multi-Country + MoMo)

Copy the full contents of:
```
audit/migrations/001_multi_country_momo.sql
```
Paste into the SQL box. Click **Go**.

**What this creates:**
- `countries` table (Nigeria + Cameroon already seeded)
- `subscription_payments` table (MoMo receipt tracking)
- `subscription_status_log` table (audit trail)
- Adds `country_id` to `businesses` table
- Adds `country_id` + `payment_method` to `subscriptions` table
- Extends `notifications.type` enum with new values
- Extends `subscriptions.status` with `pending_verification`

✅ You should see "0 rows affected" or a success message with no red errors.

---

### Step 1.3 — Run Migration 002 (Business Type + Deeds + Email Verification)

Copy the full contents of:
```
audit/migrations/002_business_type_deeds_email_verify.sql
```
Paste into the SQL box. Click **Go**.

**What this creates:**
- Adds `business_type` to `businesses` (product/service/mixed)
- Adds `email_verified_at` + `email_verification_token` to `businesses`
- Adds `provenance_text` to `products` (editorial narrative for deeds)
- Adds `DEA` to `document_counters` doc_type enum
- Creates `deeds` table
- Creates `email_verifications` table
- Marks all existing businesses as email-verified (they were live before this feature)

✅ You should see no red errors.

---

### Step 1.4 — Run Migration 003 (Debt Module Schema Fixes)

Copy the full contents of:
```
audit/migrations/003_debt_schema_fixes.sql
```
Paste into the SQL box. Click **Go**.

**What this fixes:**
- Adds `'expense'` to `debts.source` enum (was missing, caused 500 errors)
- Adds `reference` column to `debt_settlements` (was missing, caused 500 errors)
- Makes `debts.user_id` nullable (auto-synced debts have no user context)
- Adds `expense_id` to `debts` (for proper expense tracking)
- Adds performance indexes

✅ You should see no red errors.

---

### Step 1.5 — Verify All Migrations Ran

In phpMyAdmin, click on your database and confirm these tables exist:
- `countries` ✓
- `subscription_payments` ✓
- `subscription_status_log` ✓
- `deeds` ✓
- `email_verifications` ✓

Also confirm in `countries` table there are 2 rows: Nigeria and Cameroon.
Click on `countries` → **Browse** → you should see both rows.

---

## PART 2 — PREPARE YOUR .env FILE

Before uploading files, open the `.env` file inside:
```
nfelia.lpc.cm/nfelia.lpc.cm/.env
```

Review every line. Update anything that needs changing:

```env
APP_NAME=Nfelia
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nfelia.lpc.cm

# REQUIRED: Generate a real APP_KEY using this command in cPanel Terminal:
# php -r "echo bin2hex(random_bytes(32));"
# Paste the result here:
APP_KEY=PASTE_YOUR_GENERATED_KEY_HERE

# Database — these are already correct if you haven't changed them
DB_HOST=localhost
DB_NAME=smartqaq_nfelia_core
DB_USER=smartqaq_nfelia_it
DB_PASS=REDACTED_SEE_SERVER_DOTENV

# Mail — update if your SMTP settings changed
MAIL_HOST=localhost
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@nfelia.lpc.cm
MAIL_PASSWORD=REDACTED_SEE_SERVER_DOTENV
MAIL_FROM_ADDRESS=no-reply@nfelia.lpc.cm
MAIL_FROM_NAME=Nfelia

# Paystack — keep as-is (live keys already in place)
PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
PAYSTACK_SECRET_KEY=sk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
PAYSTACK_WEBHOOK_SECRET=sk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
PAYSTACK_PLAN_CODE=PLN_j8slp26f5yikdhi
PAYSTACK_SEAT_PLAN_CODE=PLN_4nb1dm2gn0y2cyv

# MoMo Receipt Storage — IMPORTANT
# Set this to a directory OUTSIDE public_html for security.
# Example: /home/smartqaq/momo_receipts
# If left blank, falls back to storage/momo_receipts/ inside the app (still protected)
MOMO_RECEIPT_DIR=/home/smartqaq/momo_receipts

MAX_UPLOAD_SIZE_MB=10
```

**CRITICAL — Generate APP_KEY:**
1. In cPanel → **Terminal** (or via SSH)
2. Run: `php -r "echo bin2hex(random_bytes(32));"`
3. Copy the output (64 hex characters)
4. Paste it as the value of `APP_KEY=` in your `.env` file

---

## PART 3 — CREATE THE MOMO RECEIPTS DIRECTORY

MoMo payment receipts must be stored OUTSIDE your public_html for security.

**Via cPanel File Manager:**
1. Go to cPanel → **File Manager**
2. Navigate to your home directory (`/home/smartqaq/`)
3. Create a new folder: `momo_receipts`
4. Right-click → **Permissions** → set to `750`

**OR via cPanel Terminal:**
```bash
mkdir -p /home/smartqaq/momo_receipts
chmod 750 /home/smartqaq/momo_receipts
```

---

## PART 4 — UPLOAD THE FILES

### Step 4.1 — Create a ZIP of the project folder

On your computer, navigate to your project folder:
```
C:\Users\Tom Blake\nfelia-prod\
```

Select the `nfelia.lpc.cm` folder (the outer one containing the inner `nfelia.lpc.cm` folder).
Create a ZIP archive of it.

### Step 4.2 — Upload via cPanel File Manager

1. Go to cPanel → **File Manager**
2. Navigate to your hosting root — this is where `public_html` lives
3. **DELETE** or rename the existing `nfelia.lpc.cm` folder
   (Right-click → Rename to `nfelia.lpc.cm_backup_YYYYMMDD`)
4. Upload your ZIP file
5. Right-click the ZIP → **Extract**
6. Confirm the extraction path matches where the old folder was

### Step 4.3 — Verify folder structure

After extraction, the structure should look like:
```
/nfelia.lpc.cm/          ← outer domain folder
  nfelia.lpc.cm/          ← inner app folder
    index.php
    .env
    .htaccess
    app/
    views/
    api/
    assets/
    audit/
    config/
    ...
```

---

## PART 5 — SET FILE PERMISSIONS

Via cPanel Terminal or File Manager:

```bash
# Make storage directories writable by PHP
chmod -R 755 /path/to/nfelia.lpc.cm/nfelia.lpc.cm/storage/
chmod -R 755 /path/to/nfelia.lpc.cm/nfelia.lpc.cm/uploads/
chmod -R 755 /path/to/nfelia.lpc.cm/nfelia.lpc.cm/storage/pdf/

# Protect the .env file
chmod 600 /path/to/nfelia.lpc.cm/nfelia.lpc.cm/.env
```

Replace `/path/to/` with your actual server path (usually `/home/smartqaq/`).

---

## PART 6 — PAYSTACK WEBHOOK CONFIGURATION

Your Paystack webhook URL must be set in the Paystack dashboard.

1. Log in to **https://dashboard.paystack.com**
2. Go to **Settings → API Keys & Webhooks**
3. Set Webhook URL to:
   ```
   https://nfelia.lpc.cm/api/webhooks
   ```
4. Confirm the webhook secret matches `PAYSTACK_WEBHOOK_SECRET` in your `.env`

---

## PART 7 — SET UP CAMEROON (MoMo)

Now that the system is live, configure Cameroon's MoMo payment number via the Admin Panel.

1. Log in as **Nfelia Admin** (tomblakeasaah@gmail.com)
2. In the left sidebar → **Countries**
3. Click **Edit** on the Cameroon row
4. Fill in:
   - **Mobile Money Number**: your MTN/Orange Money number (e.g. `+237 6XX XXX XXX`)
   - **Payment Instructions**: e.g. "Send the exact amount via MTN Mobile Money or Orange Money to the number above. Take a screenshot showing your name, amount and date, then upload it as your proof of payment."
   - **Monthly Price**: e.g. `6500` (in FCFA)
   - **Extra Seat Price**: e.g. `3000`
5. Click **Save Changes**
6. Toggle Cameroon to **Active** (green)

---

## PART 8 — POST-DEPLOYMENT VERIFICATION

Test each of these URLs in your browser after deployment:

### Public Pages (no login required)
| URL | Expected Result |
|-----|----------------|
| `https://nfelia.lpc.cm/` | Login/landing page loads |
| `https://nfelia.lpc.cm/biz/nfelia` | Business public profile page loads (was 404 before) |
| Any quotation public link (`/doc/{token}`) | Quotation view renders |
| Any quotation PDF link (`/doc/{token}?format=pdf`) | PDF downloads |
| Any delivery sign-off link (`/sign/{token}`) | Sign-off form renders (was 404 before) |

### Authenticated Pages (log in first)
| URL | Expected Result |
|-----|----------------|
| `/dashboard` | Dashboard loads with stats |
| `/debts` | Debt tracker loads without 500 error |
| `/debts/create` | Manual debt form works |
| `/reports/profit-loss` | P&L report loads |
| `/reports/profit-loss-pdf?month=6&year=2026` | P&L PDF downloads |
| `/settings?tab=billing` | Billing page loads (was 500 before) |
| `/countries` | Country management panel (admin only) |
| `/momo/review` | MoMo review dashboard (admin only) |

### Quick Smoke Test Checklist
- [ ] Login works
- [ ] Dashboard shows financial summary
- [ ] Debts page loads (no 500)
- [ ] Create a test manual debt — should save without error
- [ ] Record a settlement on a debt — should save without error
- [ ] Open a paid invoice → "Issue Deed" button visible
- [ ] P&L loads for current month
- [ ] P&L PDF downloads
- [ ] Visit `/biz/nfelia` — should show the public business page (not 404)
- [ ] Admin: Countries panel shows Nigeria + Cameroon
- [ ] Onboarding (new registration) shows 3 business type cards

---

## PART 9 — CRON JOB (Optional but Recommended)

Set up a daily cron to send trial expiry warnings to users.

In cPanel → **Cron Jobs**, add:
```
0 8 * * * php /home/smartqaq/public_html/nfelia.lpc.cm/cron/check_trials.php
```
This runs at 8am daily and sends email notifications 3 days before trial expiry.

---

## SUMMARY — WHAT CHANGED IN THIS RELEASE

### Bugs Fixed (were breaking production)
1. Billing page 500 — duplicate PHP method removed
2. Creating deliveries/expenses crashed — PHP function restored
3. Suspended users locked out permanently — wrong redirect fixed
4. Invoice/quotation product search broken — 3 missing AJAX files created
5. All subscription/staff/referral notifications silently failing — wrong DB column fixed
6. Webhook idempotency check always failing — wrong column name fixed
7. CSRF token empty on all AJAX calls — meta tag added
8. `/biz/{slug}` public pages always 404 — routing method mismatch fixed
9. `/sign/{token}` e-signature always 404 — routing method mismatch fixed
10. Debt creation always 500 — missing `user_id` in SQL insert fixed
11. Debt settlements always 500 — missing `reference` column added via migration
12. Expense sync causing 500 — invalid `source` enum value fixed via migration
13. MRR in admin showing 1/100th of real value — incorrect division removed
14. Quotation view PHP warnings — wrong field names fixed

### New Features
- **Multi-country system** — countries panel, per-country pricing, currency selector in nav
- **Cameroon / MoMo** — full checkout, receipt upload, admin approve/reject flow
- **Deed of Acquisition** — luxury PDF document issued from paid invoices
- **Business type** — product/service/mixed selection at onboarding
- **Email verification** — non-blocking, sends on registration, banner in app
- **HTML email templates** — branded, dynamic per-business
- **Referral code at registration** — collapsible field, URL auto-populate
- **Invoice PDF** — full dompdf template with dynamic branding
- **Receipt PDF** — clean acknowledgment PDF
- **P&L PDF** — exportable monthly report

### Migrations Required
| File | Run Order | Purpose |
|------|-----------|---------|
| `001_multi_country_momo.sql` | 1st | Countries, MoMo, subscription updates |
| `002_business_type_deeds_email_verify.sql` | 2nd | Deeds, email verify, business type |
| `003_debt_schema_fixes.sql` | 3rd | Fix debt schema that was causing 500s |
