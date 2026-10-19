# Notifications: handoff

Everything Nfelia tells someone (the bell, phone push and email) now goes
through one function, `notify()` in `app/helpers/notify.php`. This note covers
what Tom decided, how it works, how to add an alert, how to test it, and what
to watch when deploying. `Notifications audit.md` describes the system
**before** this rebuild and the bugs it had. Every bug in its section 3 is fixed.

## 1. What Tom decided (8 questions)

| # | Topic | Decision |
|---|---|---|
| 1 | Email | Shop orders and bookings (quote requests included), billing and account, and the morning plan and reminders. Everything else goes to the bell and phone only. |
| 2 | Choices | Each person switches Phone and Email for each kind of alert (More → Notifications). Billing and security emails can't be switched off. |
| 3 | Who hears | By role. The owner and admins hear everything. Staff hear about their work (orders, bookings, their tasks, low stock) and never see amounts or billing. Each person has their own read and unread. |
| 4 | Lock screen | Full details, with a per-person "Hide amounts on the lock screen" switch. |
| 5 | New alerts | Overdue invoices (daily, then weekly). Customer actions (quote declined, shop quote requests, debt payments, quote expired). Team and security (new device, password or email changed, passkey added, staff added or removed, invite email). Task follow-ups (done, taken off you). |
| 6 | Shoppers | An email at each step of their order, plus a pre-written WhatsApp button on the order page. |
| 7 | Nfelia-team alerts | These stayed in business #1's bell until ops.nfelia.com existed. They now go to the team in Nfelia Ops (`Ops.md`), and business #1 is ordinary. |
| 8 | Cancelling | A cancelled plan keeps access until its period ends, with a warning 3 days before. MoMo and admin-activated plans now really lapse. |

## 2. How it works

```php
notify('order.new', $business_id, ['order_id' => 12, 'order_number' => 'ORD-0012', ...], ['actor' => $user_id]);
```

1. **The catalogue.** `notify_events()` has one entry per event: topic,
   audience, urgency, push lifetime, link, push tag, title and body, the
   private title (when amounts are hidden), and the email's rows and button.
   `NOTIFY_TOPICS` holds the 14 topics a person can switch, with their
   defaults, which ones are locked, and which roles see each one.
2. **Who hears.** The audience is `owner`, `boss` (owner and admins), `team`,
   or `user` (`opts['users']`). (Alerts for the Nfelia team go through
   `ops_alert()` in Nfelia Ops instead: `Ops.md`.) The person who caused
   the alert is left out unless `opts['self']` is set. Inactive people never hear.
3. **The bell.** Each person gets their own row in `notifications`, with the
   event, its data, `read_at`, and a `dedupe_key`. The key is unique per
   person, so a retried request or a cron that runs twice can't alert twice.
4. **Phone and email.** These go into `notify_outbox` as each person chose
   (`notification_prefs` stores only the choices that differ from the default),
   and quiet hours hold the phone back. They are sent just after the page has
   answered (`after_response`), and `cron/planner.php` retries a failed send
   every 5 minutes. Push gives up after 4 tries and email after 6, with the
   reason recorded in the row. A phone that has unsubscribed is removed on its
   first 404 or 410.
5. **Money.** The text gets `$m($amount)`. For staff, `$m` is null, so the
   catalogue writes a sentence without the amount. A person with
   `users.hide_amounts` gets the `private` title on the lock screen. The full
   text is still in the bell.

`notify()` never throws and never rolls back the work it reports. Always call
it **after commit**. A failure only goes to the error log.

**Shoppers and other non-users** get email through `notify_email()`, which
uses the same outbox and retries. Shop order steps go through
`shop_order_tell()` in `app/helpers/shop_orders.php` with the
`order_update` template. `shop_order_whatsapp_text()` writes the same words
for the WhatsApp button on `views/orders/view.php`.

**Addresses that don't exist.** When the mail server says so (a 5.1.x answer,
or "no such user"), the email is not retried: its row fails at once with the
server's words, and Ops → Errors notes it once, as a notice. `bounce_tell()`
in `app/helpers/bounces.php` then tells whoever can fix the address, once, by
bell and phone: the owner, for someone on their team (`email.bounced`) or a
customer (`email.bounced_shopper`, which opens the order or booking: there a
WhatsApp message asking for the right address is ready, and the answer can
be saved); the Ops admins, for someone on the Nfelia team. Any other refusal
(4xx, or 5.7.x for relaying) is retried as before.

### Links: every alert opens the exact page

- Every bell row, push and email links to `notifications/open/{id}`
  (`NotificationsController::open`). That page marks the alert read and
  redirects to the event's own `url`, such as `orders/view/700`. The path is
  checked by `notify_safe_path()`: it must be relative, with no `//`, no
  scheme and no `..`.
- **Signed out:** `auth_check.php` keeps the destination (`signin_next`), and
  every way of signing in returns to it: password, passkey, the emailed link,
  and remember-me, which simply continues to the page. The sign-in page says
  "Sign in to open that."
- An alert for someone else, or for another business, says so plainly instead
  of opening.
- The `notifications` route is exempt from the subscription and shop-setup
  checks, so a "your account is on hold" alert still opens.
- The service worker (`sw.js`) focuses an open Nfelia tab and navigates it,
  or opens a new one. It also keeps the app badge and the bell's count up to
  date, and re-subscribes by itself when the browser replaces the push
  subscription.

### Where things live

| Piece | Where |
|---|---|
| Engine, catalogue, topics, outbox, sending | `app/helpers/notify.php` |
| Push encryption and signing (RFC 8291/8292, no library) | `app/helpers/webpush.php` |
| Bell API (count, list, mark read, choices, test, resubscribe) | `api/ajax/notifications.php` |
| The notifications page and the link that opens an alert | `app/controllers/NotificationsController.php`, `views/notifications/index.php` |
| Bell in the app bar | `views/layouts/app.php` (`nfBell()`), `assets/js/bell.js` |
| More → Notifications (Phone and Email per topic, hide amounts, quiet hours, test buttons) | `views/partials/notify_sheet.php`, `assets/js/notify.js` |
| Emails | `views/emails/notification.php` (all alerts), `order_update.php` (shoppers), `task_reminder.php`, `morning_plan.php` |
| Sign-in return, new-device check | `app/helpers/signin.php` (`signin_next_*`, `signin_note_device`) |
| One rule for "can this business use Nfelia" | `subscription_open()` in `app/helpers/functions.php`, used by the middleware, the shop and the cron |
| Trial ending, renewals, lapsing, the end | `billing_lifecycle()` in `app/helpers/subscription_helpers.php`, run by the cron |
| Timed alerts, retries, clean-up | `cron/planner.php` (every 5 minutes, already on the server) |
| Tables | `db/migrations/20261019090000_notification_engine.sql`, `…100000_known_devices.sql` |

## 3. Every alert

| Event | Who | Topic (default) | Sent from |
|---|---|---|---|
| `payment.invoice` / `.receipt` / `.order` / `.debt` | boss | money (phone) | InvoiceController, ReceiptController, OrderController, DebtController |
| `order.new` | team | orders (phone, email) | PublicController (checkout) |
| `quote_request.new` | team | orders | PublicController (the shop's quote form) |
| `booking.new` | team | bookings (phone, email) | StorefrontController |
| `quote.accepted` / `.declined` / `.comment` | boss | customers (phone) | PublicController (quote page) |
| `delivery.signed` | boss | customers | PublicController |
| `review.new` | boss | customers | StorefrontController |
| `invoice.overdue` / `invoice.overdue_many` | boss | overdue (phone) | cron: daily for a week, then weekly up to 8 weeks. More than 3 at once get one summary on the phone, and each one in the bell. |
| `quote.expired` | boss | overdue | cron |
| `stock.low` | team | stock (phone) | cron, once a day per business |
| `task.reminder` | the person | reminders (phone, email) | cron |
| `morning.plan` | each person | morning (phone, email) | cron, 7am, on days with something planned |
| `task.assigned` / `.unassigned` / `.done` | the person | tasks (phone) | TasksController |
| `billing.*` (paid, failed, cancelled, due, ended, renewal_soon, trial_ending, trial_ended, momo_submitted, momo_rejected, activated, suspended, trial_extended) | owner | billing (phone, email always) | PaystackController, MoMoController, AdminController, cron |
| `shop.link_live` / `.link_refused`, `support.reply`, `referral.rewarded` | boss | account (phone, email) | AdminController, ReferralController |
| `team.joined` / `.removed` | boss | team (phone) | SettingsController |
| `security.new_device`, `.password_changed`, `.email_changed`, `.passkey_added`, `.role_changed` | the person | security (phone, email always) | signin.php, SettingsController, api/ajax/auth.php |
| `system.test` | the person | (none) | the test buttons in More → Notifications |

Shoppers get emails (not alerts) when an order is placed, confirmed, being
prepared, on its way, delivered or collected, turned down or cancelled, and
when a booking is confirmed, declined or cancelled. Customers and staff also get account emails: invite,
welcome, the old address told of an email change, and so on.

## 4. Adding an alert

1. Add the event to `notify_events()` next to its group. Give it a topic,
   `to`, `url` (the exact page), `tag`, and `text`. If the text shows money,
   write it so that `$m` being null still gives a sentence. Add `private`,
   and `lines`/`button` if it emails.
2. Call `notify('your.event', $business_id, $data, ['actor' => auth_user_id()])`
   after the commit. When the same thing can happen twice (a webhook, a
   retried form, a cron), pass a `dedupe` key.
3. Add a check to `tests/notifications/flows_test.php`, or `cron_test.php` if
   the cron sends it. Drive the real page over HTTP and assert on the bell
   row, the push the stand-in phone decrypted, and the email. Copy the checks
   around it.
4. A new topic goes in `NOTIFY_TOPICS`. More → Notifications and the API pick
   it up by themselves. The UI test counts the owner's topics (13), so update
   that number.

## 5. Tests

All in `tests/notifications/`. **They wipe the database** that the checkout's
`.env` points at, so they refuse to run unless `APP_ENV` is `local` or
`testing` and `APP_URL` is this machine.

```bash
bash tests/notifications/setup.sh          # empty DB: schema, migrations, doc-number procedure
php -S 127.0.0.1:8099 -t . tests/notifications/router.php &   # APP_URL=http://localhost:8099
bash tests/notifications/run.sh            # engine, flows, cron, sw
bash tests/notifications/run.sh ui         # the browser checks (Playwright + Chromium)
```

| Suite | Checks | What |
|---|---|---|
| `engine_test.php` | 88 | Audiences, roles, amounts hidden from staff, choices and locks, quiet hours, dedupe, outbox retries and give-ups, safe links, a new person's defaults |
| `flows_test.php` (+ `flows_shop.php`) | 292 | Every trigger through the real page or webhook over HTTP: bell row, decrypted push, email, the link opening the exact page, signed out → sign-in → the page, wrong person, a business on hold |
| `cron_test.php` | 59 | Reminders, morning plan, low stock, overdue (daily, weekly, the summary), expired quotes, trial and billing lifecycle at fixed times (faketime), retries, the 3am clean-up |
| `sw_test.js` | 17 | The service worker in a Node VM: push shown, badge, tap focuses or opens, re-subscription |
| `ui_test.js` | 20 | In Chromium at phone width: signed-out tap, bell, the notifications page, More → Notifications, adding a passkey, what staff see. Screenshots go to `NF_UI_OUT`. |

`run.sh` starts the stand-ins itself: `push_sink.php` on port 8098 is a
"phone" that decrypts every push it receives. `smtp_sink.py` on port 2525 is
an inbox that can also be told to refuse mail, and refuses some addresses
for good the way real mail servers do (see its top comment). The fixture world is
Nfelia (business 1), Ada's Hair & Co (2: owner, admin, staff, an inactive
person) and Kola Prints (3). Every password is `TestPass1`.

**CI:** the `notifications` job in `.github/workflows/deploy.yml` runs the
four suites against MariaDB 10.11, and `deploy` waits for it. The browser
suite runs too and uploads its screenshots, but it doesn't block a deploy.
The workflow runs on pull requests to main and on pushes to main.

`docs/handoff/testkit/cron_test.php` and the notification half of
`notify_test.js` are **superseded** by these suites: they post to the retired
checkout and check the old behaviour. `notify_test.js` still covers Share PDF
and phone contacts.

## 6. Deploying

- **Three migrations:** `20261019090000_notification_engine.sql`,
  `20261019100000_known_devices.sql` and
  `20261019110000_walk_in_receipts.sql` (a walk-in receipt has no
  customer). They copy the old opt-outs into the new choices.
  `db/migrate.php` now reports an error in any statement of a migration, not
  just the first.
- **VAPID keys** must be in the production `.env` (`VAPID_PUBLIC_KEY`,
  `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`). Use the same keys as before, or
  every phone has to subscribe again. Generate new ones only if there are
  none: `php bin/vapid-keys.php`.
- **Cron:** nothing new. `cron/planner.php` every 5 minutes now also handles
  billing, overdue invoices and retries. `cron/check_trials.php` needs no
  cron line of its own.
- **Behaviour changes owners will notice:**
  - A trial now runs through the whole of its last day.
  - A cancelled card plan keeps access until the period ends.
  - MoMo and admin-activated plans that are past their period now lapse.
    On the first run after the deploy, each one gets at least 3 days' notice
    (`billing.due`) before it closes.
  - Logging out removes that phone's push subscription.

## 7. Left for later

- ~~ops.nfelia.com~~ Done: Nfelia-team alerts now go to the team in Nfelia
  Ops (`ops_alert()`, see `Ops.md`). The `platform` audience is gone.
- `SettingsController::update_role` has no CSRF check (SameSite=Lax cookies
  limit the risk).
- Dead files: `backupSettingsController.php`, `backupOnboardingController.php`.
- The admin page and the profit and loss report load Chart.js from a CDN, so
  they show a script error when offline.
