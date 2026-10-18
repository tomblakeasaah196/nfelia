# Notifications audit (2026-10-18)

Everything in Nfelia that tells someone something: the bell, phone push and
email. Read before changing any of it. Line numbers are as of commit ad76b6e.

## 1. How it works today

**Three channels, three separate code paths.** Nothing ties them together:
each caller writes its own bell row, decides on its own whether to push, and
sends its own email.

| Channel | Where | Notes |
|---|---|---|
| Bell (in-app) | `notifications` table; `api/ajax/notifications.php`; bell in `views/layouts/app.php:210-283` | Last 10 only, no full page. Count fetched once per page load |
| Push | `app/helpers/webpush.php` (RFC 8291/8292, no library); `sw.js` push + notificationclick; `assets/js/push.js`; subscribe at `POST /tasks/push-subscribe` | Sent from the request (`after_response`) or cron. No retry |
| Email | `app/helpers/mail.php` (`send_mail`, `send_branded_mail`), templates in `views/emails/` | Mostly sent inside the request, no retry, result often ignored |

**Schema.**

```
notifications
  id, business_id, user_id NULL, type VARCHAR(50), title, message,
  action_url VARCHAR(500) NULL, is_read, created_at
push_subscriptions
  id, business_id, user_id, endpoint, endpoint_hash UNIQUE, p256dh, auth,
  user_agent, failures, last_sent_at, created_at
users (choices)
  notify_money=1, notify_reminders=1, notify_low_stock=0, quiet_hours=0,
  quiet_from=21:00, quiet_to=07:00, morning_email=1, reminder_email=1
```

**Who sees a bell row.** `user_id IS NULL OR user_id = me OR type <> 'general'`
(`api/ajax/notifications.php:19`). So almost every row is seen by the whole
team, staff included, and `is_read` is shared: one person reading it clears it
for everyone.

**Push rules** (`app/helpers/notify.php`). Money in buzzes owner and admins who
want it, never whoever recorded it, never in quiet hours. Reminders, morning
plan and low stock come from `cron/planner.php` every 5 minutes.

**Links.** `action_url` comes in three shapes: `orders/view/1`,
`/orders/view/1` and `https://nfelia.com/orders/view/1`. The bell copes with
all three; the absolute ones break if the domain moves (it has moved once).

**Signed out.** `app/middleware/auth_check.php` sends anyone signed out to
`/login` and forgets where they were going. Every way of signing in
(password, passkey, emailed link) then lands on `/dashboard`. The one path
that keeps the page is the "keep me signed in" cookie, which restores the
session in place.

## 2. Every trigger

B = bell, P = push, E = email. "Team" = the whole business (user_id NULL).
"Nfelia" = the bell of business 1, seen by everyone in business 1.

### Money in

| Event | Where | Who | Ch | Link | Problems |
|---|---|---|---|---|---|
| Payment recorded on an invoice | InvoiceController.php:427, :443 | Team; phones of owner + admins | B P | invoices/view/{id} | Bell hard-codes ₦; insert inside the payment transaction with no catch (a failed bell row cancels the payment); double submit can overpay and alert twice |
| Sale on the spot (receipt) | ReceiptController.php:467, :473 | Team; owner + admin phones | B P | receipts/view/{id} | Same transaction risk; ₦0 receipts alert |
| Shop order approved with payment proof | OrderController.php:297, :300 | Team; owner + admin phones | B P | orders/view/{id} | Not in a transaction; flash says "error" though the order was approved |
| Customer pays off a debt | DebtController.php:261-310 | nobody | none | | Money in, but silent |

### Shop, orders, bookings

| Event | Where | Who | Ch | Link | Problems |
|---|---|---|---|---|---|
| Shopper places an order | PublicController.php:905, :954 | Team bell; owner + admin phones (max 20/hour) | B P | orders/view/{id} (lands on the order detail: confirmed) | Bell insert inside the order transaction; a later error calls rollBack() with no transaction, shows the shopper a 500 for a placed order, and they retry: duplicate order and alerts. Amount has no currency |
| Shopper's own confirmation | PublicController.php:945 | Shopper | E | tracking page | OK |
| Shopper leaves a review | StorefrontController.php:284 | Team | B | orders/view/{id} | The review isn't shown on that page (see bug 3) |
| Shopper books a service | StorefrontController.php:524, :534 | Team; shopper email | B E | bookings/view/{id} | No push, unlike orders |
| Staff confirms, declines, cancels a booking | BookingController.php:264 | Shopper | E | | Email silently never sent (bug 3); no guard against double decide |
| Shop quote request | PublicController.php:647 | nobody | none | | Saved to `quote_requests`, which nothing reads. Lost |
| Shop name needs review | ShopSetupController.php:265 | Nfelia | B | admin/shop-names | OK |
| Shop name approved or refused | AdminController.php:577-619 | Team | B | landing-page / shop-setup | Staff can't open either page |

### Customers acting on documents

| Event | Where | Who | Ch | Link | Problems |
|---|---|---|---|---|---|
| Client comments on a quotation | PublicController.php:113 | Team | B | quotations/view/{id} | No rate limit; inside the transaction |
| Client signs and accepts a quotation | PublicController.php:147 | Team | B | quotations/view/{id} | No status check: a declined, expired or converted quote can be signed again, adding duplicates. A won deal doesn't buzz |
| Client declines a quotation | PublicController.php:157-171 | nobody | none | | Silent |
| Delivery signed | PublicController.php:391 | Team | B | deliveries/view/{id} | Double submit makes two rows |
| Invoice goes overdue | InvoiceController.php:920 | Invoice creator | B | invoices/view/{id} | Only created when someone opens the Invoices list; never by cron. Comes back as a duplicate after a debt write-off. ₦ hard-coded |
| Quote expires | cron/planner.php:241 | nobody | none | | Status changes silently |

### Tasks and the day

| Event | Where | Who | Ch | Link | Problems |
|---|---|---|---|---|---|
| Task reminder | cron/planner.php:122-133 | Assignee or creator | B P E | tasks | Opens the board, not the task; email ignores quiet hours |
| Morning plan, 07:00-11:00 | cron/planner.php:170-178 | Each person | B P E | tasks | OK, once a day |
| Low stock, from 09:00 | cron/planner.php:225 | People who turned it on | B P | products?stock=restock | Off by default, so by default there isn't even a bell row |
| Task assigned to you | TasksController.php:545-547 | Assignee | B P | tasks | Push ignores the person's choice and quiet hours |
| Task finished, task reassigned | TasksController.php | nobody | none | | |

### Billing and account (the business's own subscription)

| Event | Where | Who | Ch | Link | Problems |
|---|---|---|---|---|---|
| Card payment confirmed (return from Paystack) | PaystackController.php:175 | Team | B | settings?tab=billing | ₦ is mojibake ("â¦"); no idempotency check, so a refresh or webhook-first duplicates the note and re-applies the referral discount |
| Renewal / charge.success webhook | PaystackController.php:354 | Team | B | same | Check-then-act dedupe: two concurrent retries both pass |
| Owner cancels | PaystackController.php:321 | Team | B | same | Says "access continues until the end of your billing period"; access actually ends at once (subscription_check.php:57) |
| Subscription disabled (webhook) | PaystackController.php:429 | Team | B | same | Never fires: its dedupe key collides with subscription.create |
| Payment failed (webhook) | PaystackController.php:447 | Team | B | same | Dedupe key uses time(), so every retry duplicates |
| MoMo approved / rejected | MoMoController.php:340/358, :424/448 | Team; owner email | B E | settings?tab=billing / momo/checkout | Insert inside the approval transaction; plain-text email; branded template unused; referral never rewarded for MoMo payers |
| Admin activates / suspends / extends trial | AdminController.php:182-196 | Team | B | none | Title always "Nfelia Admin"; old whitelist rewrites types to 'general'; no email, so a suspended owner who isn't signed in never knows |
| Admin replies to a ticket | AdminController.php:464 | Team | B | none (should be contact-support) | Escaped twice; can cut a UTF-8 character and show empty quotes; no email |
| Referral reward | ReferralController.php:180 | Referrer's team | B | referrals | ₦2,500 hard-coded; Paystack payers only |
| Trial ending in 3 days | subscription_helpers.php:404 (cron) | Team | B | settings?tab=billing | "Tomorrow" really means tonight; bell only |
| Trial ended, grace ended | subscription_helpers.php:79, :97 | nobody | none | | Dead code: never called. (Access is still cut by the middleware) |
| Staff member added | SettingsController.php:459 | Team | B | settings?tab=staff | Tells the owner about their own action; the new member gets no invite email |
| Renewal coming up | | nobody | none | | Not built. MoMo and admin-activated subscriptions never lapse |

Billing, staff and shop links all land staff on the wrong page or bounce
them, because those pages are owner-only.

### Nfelia team (platform)

All land in business 1's bell, seen by every user of business 1. No push, no
email to the team.

| Event | Where | Link |
|---|---|---|
| MoMo payment to review | MoMoController.php:194 | momo/review (the list, not the payment) |
| New support ticket | SupportController.php:75 | admin/support (the inbox, not the ticket) |
| Prospect message from the home page | AuthController.php:477 | none; the contact number is dropped, so nobody can reply |
| Shop name to review | ShopSetupController.php:265 | admin/shop-names |
| New sign-up, payments, failures, cancellations | | nobody is told |

### Account and security emails (never optional)

Welcome + verify (AuthController.php:194, sent inside the request), reset or
sign-in link (:317), password changed after reset (:381), resend verification
(:581), staff password reset (SettingsController.php:562). Missing: password
changed in Settings, email changed (old address not told), new sign-in on a
new device, passkey added.

## 3. Bugs, most serious first

1. **A bell row can undo the real action.** Bell inserts sit inside the
   transaction with no catch of their own: invoice payment, receipt, shop
   order, quote accept/comment, MoMo approve/reject, staff added, prospect
   message. One failed insert rolls back money.
2. **Shop order shows a 500 after it was placed** (PublicController.php:968
   rolls back after commit), so shoppers retry and create duplicates.
3. **The session never holds the shop address** (`subdomain` is missing from
   signin.php:38, auth_check.php:45, AuthController.php:175). So every
   booking confirm, decline or cancel email is silently skipped, and the
   order page hides the tracking and review panel.
4. **Paystack duplicates and lost events**: callback has no idempotency (and
   re-applies the referral discount), webhook dedupe is check-then-act,
   payment-failed keys use time(), subscription.disable is swallowed.
5. **Bell text is escaped twice.** The API runs `e()` and the bell renders with
   `x-text`, so "Ade & Sons" shows as "Ade &amp;amp; Sons". Rows saved
   already-escaped (MoMo, Support, Admin) come out worse.
6. **The cancel note promises access the app doesn't give.**
7. **₦ mojibake** in Paystack notes (PaystackController.php:176, :355).
8. **Read state is shared by the whole team**, and tapping a bell item never
   marks it read.
9. **Signed-out taps lose the destination**: every sign-in lands on the
   dashboard.
10. **Logout leaves push subscriptions in place**, so a shared phone keeps
    getting the previous person's alerts.
11. **Links**: three formats, a `billing` link that 404s, owner-only targets
    sent to staff, platform links that open a list instead of the item.
12. **Push and email have no retry or record.** A failed send is a line in
    the PHP error log, nothing more.
13. **Delivery status change is broken** (outside notifications):
    DeliveryController.php:314-320 builds `SET status = ?, 1=1`, which
    MariaDB rejects, so every manual delivery status change errors.
14. **Dead files**: `backupSettingsController.php`,
    `backupOnboardingController.php` (not routable; blocked by .htaccess).

## 4. Tests that exist

`docs/handoff/testkit/notify_test.js` (67 checks, Playwright, decrypts real
pushes) and `cron_test.php` (71 checks). `notify_test.js` is stale: it posts
shop orders to the retired `/public/cart-checkout`. Neither runs in CI; CI
only lints.
