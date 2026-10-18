# Lock it down: audit PR 1 (handoff)

The first of the three fix PRs from `docs/AUDIT_2026-10.md`. One business can no longer reach another
business's records, customers' signatures and payment screenshots are private, the money pages check
roles themselves, paid months expire, every payment is applied once, and the shop's public forms have
spam limits. Items: M1, S15, S1, W1, W2, W6, S8, X1–X9, B1–B23, P4, P5, and M24 (PHP's clock), which
billing needed.

## What Tom decided (and where it lives)

| Topic | Decision | In the code |
|---|---|---|
| Paid month ends, no renewal | 3 days' grace, then the app and the shop lock | `billing_access()` in `app/helpers/billing.php` |
| Already past their date when this ships | Left as they are; the rule applies from their next payment | `subscriptions.expiry_exempt`, set by the migration |
| Mobile Money receipt waiting | No access until approved; the founder gets an urgent bell, phone push and email (now the Ops team: `Ops.md`, section 7) | `MoMoController::submit`, `ops_alert()` |
| Renewing with fewer seats than staff | The owner ticks who the month covers, with an "All" button; the rest are switched off when it goes through | Billing tab, `billing_start_month()` |
| Cancelling | Nfelia stays open to the end of the paid month | `billing_access()` (`cancelled`) |
| Unconfirmed booking requests | Hold their time for 24 hours | `BOOKING_HOLD_HOURS`, `booking_holding_sql()` |
| Repeat trials | Sign-ups limited per connection; Gmail dot and plus variants count as one owner | `AuthController::register`, `email_owner_exists()` |

## Calls I made that Tom may want to change

- **Card payments no longer renew by themselves.** Each payment buys one month (or more seats in the
  month already paid). That is what makes "tick who the month covers" possible, and it removes the
  double-billing and free-seat problems (B5, B8, B9). Card subscriptions made before this keep working
  until their next automatic charge; that charge is honoured, then the subscription is stopped and the
  owner is told to pay from Billing each month. A bell note three days before a month ends reminds them.
  To keep automatic renewals instead, that is a separate piece of work (charging the saved card each
  month with the ticked staff).
- ~~**Nfelia's own business never locks**~~ Changed when Nfelia Ops was merged: the team approves
  payments in Ops (ops.nfelia.com), so business 1 is ordinary and locks like any other (`Ops.md`, section 7).
- **Limits on shop forms:** one visitor (IP) 10 orders, quote requests or bookings an hour at one shop,
  120 an hour across all shops (many phones share one mobile network address), 60 an hour per shop, 10
  emails a day to one address. Saved requests per phone (5 or 10 a day, as before) now count the number as
  digits. Sign-ups: 5 a day per connection.
- **Uploaded pictures:** up to 20 MB and 52 megapixels (a 48 MP phone photo fits); the saved copy is at
  most three times as tall as it is wide. Payment screenshots are saved as JPEG.
- **Gmail only** for the "one address" rule; other providers' plus addresses count separately.

## What changed

**Tenant isolation (M1, S15).** `owned_by_business()` (`app/helpers/functions.php`) checks every posted
client, bank account, product, supplier and invoice id on invoices, quotations, receipts, debts, expenses
and deliveries. Client, bank and invoice reads and total updates are scoped by business, including on the
public document pages.

**Private files (S1, W1, W6, X9).** Signatures and payment proofs are saved under `storage/private/` with
random names and sent only by `send_private_file()` from pages that check who is asking: the delivery's
own links (`?signature=1`), and the owner's order, booking and delivery screens. `.htaccess` closes the
old folders (`uploads/delivery_signoffs`, `order_proofs`, `booking_proofs`) and anything under `uploads/`
that looks like a script; old files stay where they are and still show through the same pages.
Signatures must be real PNGs and are redrawn before saving. A booking or order that fails leaves no
file behind. `client_ip()` trusts Cloudflare's visitor header only on requests that came through
Cloudflare.

**Roles and the rest of security (X2–X8).** `require_boss()` on expenses, debts, profit and loss and
news; `is_platform_admin()` (the founder business's owner) on Admin, Countries and Mobile Money review.
Purge only touches its listed tables. A client can answer a quotation once, with limits. Signing out
needs the button's POST. The country (and so the currency and how the business pays) is the owner's to
change, with the CSRF token; changing a role checks it too. Delivery providers come from the list.

**Billing (X1, B1–B23).** See the decisions above, plus: the callback claims the Paystack reference
before applying it and refuses another business's payment, a smaller amount or another currency; the
webhook shares the claim, keys other events by event and code, and refuses everything when the secret
key is missing. Prices and currency come from the business's country. Paying early adds a month. A
referral credit comes off one month, once; referral codes no longer change. Removed staff are signed out
on their next page; a removed person's email can be added again or used to sign up. Admin activate,
suspend and extend-trial are logged, and extend-trial revives an expired trial. MRR and the revenue chart
are in naira. Manual-payment countries see how to pay. An expired trial can finish onboarding, and
support stays open. The ₦ sign shows properly.

**Shop forms and phones (W2, S8, P4, P5).** One guard for orders, quote requests and bookings (limits
above, plus a hidden field only robots fill in). Emails to shoppers greet them by a plain first name or
"there" and list the shop's own product names, never text a stranger typed. Phone notifications only go
to real browser push services, at most ten devices a person; "send a test" is limited. Signing out stops
that person's notifications on that phone (the browser remembers which subscription it holds from the
next time Tasks opens).

## What deploys automatically

Merging to `main` deploys everything, including `.htaccess` and the migration
`20261018200000_billing_lockdown.sql` (new columns; marks businesses already past their paid date as
"left as they are"). Pages that read the new column keep working in the minute before the migration runs.

## To do by hand after deploy

1. **Cloudflare:** purge the cache for `/uploads/delivery_signoffs/*`, `/uploads/order_proofs/*` and
   `/uploads/booking_proofs/*` (or Purge Everything once). Copies cached before this change could
   otherwise be served for a few more hours.
2. **`.env` on the server:** make sure `PAYSTACK_SECRET_KEY` is set (without it the webhook now refuses
   everything, where before it accepted anything). Never set `PAYSTACK_API_BASE` or
   `PUSH_TEST_ENDPOINTS` there; they are for local tests only.
3. **Payment alerts** go to Admin and Support in Nfelia Ops (bell, phone and email, urgent), not to
   business 1 (`Ops.md`, section 7). Make sure the team turns on phone alerts under Ops → Account.

## Not in this PR

- Public document links still never expire (X10, by design).
- Sign-off ignoring the delivery's state (S11), the doc-number transaction bug behind making deliveries,
  expenses and walk-in receipts by hand (M4, S3), and the rest of the money and stock findings: PR 2.
- Shop website polish, reminders and the rest: PR 3.

## How it was tested

- `docs/handoff/testkit/lockdown_test.js`: 78 checks in Chromium, against the local app and the Paystack
  stand-in (`paystack_mock.php` on port 8096): one or more per fix above, including replaying a payment,
  claiming another business's, underpaying, a waiting Mobile Money receipt, approving twice, staff typing
  owner-only addresses, the 24-hour booking hold and the per-visitor limit.
- The existing kits pass: `landing_test.js` 51, `order_stock_test.js` 10, `invoice_edit_stock_test.js` 15,
  `shop_links_test.py` 119, `storefront_test.py` 148, `bookings_test.py` 77, `seo_test.py` 46,
  `selling_test.py` 135 (updated where the rules changed on purpose: a suspended shop is the closed one,
  proofs live in private storage, a valid 1×1 signature image), and `ci_step_{2,3,4,5}.sh`.
- `cron/planner.php` runs clean and sends the "paid month ends soon" note once. `cron_test.php` and the
  kits that need the copy on port 8099 were not run here (no `faketime`).
