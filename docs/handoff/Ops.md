# Nfelia Ops: handoff

Nfelia Ops is the Nfelia team's own site at **ops.nfelia.com**. It has the
team's own accounts and roles, an error monitor, the clients, and sales. It
replaces the old founder admin, which lived inside the Nfelia business (#1).
Business #1 is now an ordinary business. This note covers what Tom decided,
where things are, how each part works, what to do when deploying, and what
is left for later.

## 1. What Tom decided

| # | Topic | Decision |
|---|---|---|
| 1 | Where | The same repo and the same database. Ops is routed by host in `index.php`, before shops. |
| 2 | Roles | Admin, Support and Sales. Support will later run the client data export (a branded, signed, multi-tab Excel workbook in the client's colours). Sales handles clients and leads. |
| 3 | Sign-in | A passkey, or an email and password. |
| 4 | Client data | Each client's whole account, plus a logged, read-only "view as". |
| 5 | Error alerts | New errors, and errors that come back after being marked fixed. Sudden spikes also alert. |
| 6 | What it watches | Browser errors, the cron and background jobs, failed emails, pushes and webhooks, slow pages, and anything else that can be fixed: disk, database, mail server, push keys, PHP and the server log. |
| 7 | Business #1 | Everything moved to Ops, and #1 is ordinary. Old /admin links redirect to Ops. |
| 8 | Sales | A pipeline with follow-ups, email campaigns, and leads added by hand for people the team reached out to. |

## 2. Where things are

| Part | Code | Pages |
|---|---|---|
| Frame, sessions, roles, audit | `app/ops/ops.php`, `app/ops/router.php` (the route table), `app/ops/passkeys.php` | Sign-in, Account, Team, Audit log |
| Alerts to the team | `app/ops/alerts.php` (`ops_alert`, `ops_alert_one`) | The bell, Alerts, phone push (`ops-sw.js`) |
| Error capture | `app/helpers/errors.php` (the app), `assets/js/errors.js` (browsers) | |
| Monitor | `app/ops/monitor.php` (log reader, health, morning summary, clean-up, watchdog) | Errors, Health |
| Clients | `app/ops/controllers/OpsClients…`, `…Payments…`, `…ShopNames…`, `…Support…`, `…Finance…`, `…Countries…`; `app/helpers/view_as.php` | Clients, Payments to check, Shop names, Support, Reviews, Feedback, Finance, Referrals, Countries |
| Sales | `app/ops/sales.php`, `OpsLeadsController`, `OpsCampaignsController` | Leads (list and board), Campaigns |
| Views and styles | `views/ops/`, `assets/css/ops.css`, `assets/js/ops.js` (no build step) | |
| Emails | `views/emails/ops_*.php` | invite, reset, security, morning summary, message, campaign |

### Roles

Permissions are in `OPS_PERMISSIONS` (`app/ops/ops.php`). Each route names
the permission it needs.

| Role | Can do |
|---|---|
| Admin | Everything, including Finance, Countries and settings, Team, and the Audit log. |
| Support | Clients (and act on them: plans, payments, shop names, "view as"), Support tickets, Errors and Health, and later the exports. |
| Sales | Clients (to see, and to add notes), Leads and Campaigns. No errors, plan changes or "view as". |

## 3. How it works

**Accounts and sign-in.** Ops has its own `ops_staff` table, separate from
client users, and its own session cookie, `__Host-nfelia_ops`. A session ends
after 12 hours unused, or after 7 days regardless. Signing out everywhere
(Account) bumps `session_epoch`. People join by invite (Team) and choose
their own password, at least 10 characters. Passkeys are registered for
ops.nfelia.com. Every POST needs the CSRF token. Everything done in Ops is
written to `ops_audit`, which is append-only.

**Alerts.** `ops_alert($db, $kind, $title, $body, $url, $dedupe, ['permission' => …])`
tells every active person whose role includes that permission. It writes a
bell row (`ops_alerts`) and, if they have phone alerts on, a push through
the same outbox as client notifications (`notify_outbox.staff_id`). The
dedupe key means an alert is never sent twice.

| Alert | Who hears |
|---|---|
| New support ticket | Support, Admin |
| Mobile money payment to check, shop name to check | Support, Admin |
| New, returning or spiking error; health needs attention; the cron has stopped | Support, Admin (phone for crashes, errors and spikes; warnings and slow pages go to the bell only) |
| Message from the home page | Sales, Admin |
| Follow-up due on a lead | Its owner, or all of Sales if it has none |
| A lead given to you | That person |
| "View as" on a client | The client's owner (in the app, on their phone, and by email, which they can't switch off) |

**Error capture.** `errors_install()` runs first on every web request and
cron run. It records PHP errors, uncaught exceptions, fatal errors, slow
pages (over 3 seconds), failed jobs, and script errors sent from browsers by
`errors.js`. Each one is grouped by a fingerprint of kind, level, file, line
and its normalised message, and gets a short code like `E7KQ2MXA`. The error
page shows the code, so a client can quote it. Emails, phone numbers and
tokens are masked before saving. Captures use their own database connection,
so a rolled-back transaction keeps them. The monitor also reads the server's
error logs every 5 minutes from where it left off, and skips lines the app
has already captured. A problem fixed in one release that returns in a later
one is flagged as "came back". The release comes from `storage/release`,
which the deploy writes.

**Health.** Health checks the cron, Paystack webhooks, emails and pushes
(failed or stuck), push keys, the mail server, errors and slow pages, the
server log, disk, PHP, the database and the release. A check that turns bad
alerts once per window. If the cron itself stops, nothing would notice, so
1 web request in 50 also checks when the cron last finished. After 20
minutes without a run, it alerts.

**Morning summary and clean-up.** At 8am each person who wants it gets an
email of the last day (Account → Alerts). At 3am the clean-up runs:

- error occurrences go after 30 days; while they last, each problem keeps
  its latest 50;
- problems marked fixed or ignored, and not seen for 90 days, go with
  their notes;
- read alerts go after 60 days.

**Clients and "view as".** Each client's page shows its people, plan,
payments, usage, tickets, errors, shop and notes. Plan changes tell the
owner and record who did it. "View as" needs a reason. It makes a one-time
link that lasts 2 minutes and opens the client's Nfelia in a new tab,
read-only, for 30 minutes: every save, send or delete is refused. A banner
shows throughout, and **End** returns to Ops. The owner is told who looked
and why. The client's own data shows no sign-in. The reason stays in the
audit log.

**Sales.** Leads arrive by themselves from three sources:

- Home page messages. One lead per email or phone; a repeat message becomes
  a note on the lead.
- Sign-ups still not set up after 1 to 14 days.
- Trials ending within 3 days.

The team adds everyone else from **Add a lead**. A phone number written
another way still matches the existing lead. The stages are New, Contacted,
Demo, Trial, Paying and Lost; Lost needs a reason. A lead whose business
starts paying moves to Paying by itself. Each lead has an owner and a
follow-up time, and the cron reminds the owner (bell and phone) when it is
due.

Campaigns go to chosen lead stages and client groups:

- Each address gets it once, and anyone who unsubscribed is left out.
- The campaign page shows who it reaches, a preview, and a test to yourself.
- **Send** works only once. The cron then hands out CAMPAIGN_PER_HOUR
  emails an hour (default 200), a few every 5 minutes, so the mail server
  is never flooded.
- Every email has its own unsubscribe link. Opening the link asks first,
  so mail scanners don't unsubscribe anyone. Every email also carries
  one-click `List-Unsubscribe` headers for mail apps.
- Account, billing and security emails still go to people who unsubscribed.

## 4. Deploying: what Tom must do

1. **Before merging, check that no shop already uses the name "ops":**
   `SELECT id, name FROM businesses WHERE subdomain = 'ops';`. Ops takes that
   address before shops, and the name is now reserved. If a shop has it,
   ask that client to choose another name first.
2. **Merge.** CI runs every test suite (section 5), then the deploy:
   - applies the four migrations with `php db/migrate.php`:
     - `20261020090000_ops_platform`: staff, passkeys, tokens, audit log
     - `20261020100000_ops_monitor`: errors, health, settings, alerts, push
     - `20261020110000_ops_clients`: "view as" and notes
     - `20261020120000_ops_sales`: leads, campaigns, opt-outs; existing home
       page messages become leads
   - writes `storage/release`.
3. **DNS.** ops.nfelia.com uses the same `*.nfelia.com` wildcard as the shops
   (cPanel domain, origin certificate, proxied `*` record in Cloudflare;
   see `Shop links step 1.md`). If the wildcard is in place, there is
   nothing to add. If not, add a proxied `ops` record pointing to the same
   document root.
4. **The first admin.** Sign in to nfelia.com as the owner of the Nfelia
   business and open **nfelia.com/admin**. Press **Set up Nfelia Ops**. An
   email arrives with a link to choose a password on ops.nfelia.com. After
   that, add a passkey under Account, and invite the team from Team. The
   setup button disappears once Ops has an admin.
5. **Phone alerts** use the same VAPID keys as the app (`VAPID_PUBLIC_KEY`,
   `VAPID_PRIVATE_KEY`). Health says if they are missing. Each person switches
   phone alerts on under Account → Alerts.
6. **Optional `.env` settings:**
   - `CAMPAIGN_PER_HOUR`: campaign emails per hour (default 200, never below
     12). Keep it under the cPanel account's hourly mail limit, leaving room
     for account emails.
   - `MOMO_RECEIPT_DIR`: where mobile money receipts are kept, if it isn't
     `storage/momo_receipts`. Ops only shows receipts from there.
7. **Campaign mail.** Before the first campaign, check that nfelia.com's SPF
   and DKIM records (cPanel → Email Deliverability) cover the mail server,
   and add a DMARC record if there isn't one. Otherwise campaigns land in
   spam.
8. **Old links.** /admin and the old admin pages (countries, MoMo review)
   redirect to Ops. Bookmarks keep working.

## 5. How it was tested

All suites run in CI (`.github/workflows/deploy.yml`) against MariaDB 10.11.
They use a stand-in mail server and push service, and real cron runs with
the clock set by `faketime`:

```
bash tests/notifications/run.sh engine flows cron sw ops/foundation ops/monitor ops/clients ops/sales
bash tests/notifications/run.sh ui ops/ui      # Playwright
```

| Suite | Checks | Covers |
|---|---|---|
| ops/foundation | 101 | Sign-in, passkeys, invites, resets, sessions, roles on every route, CSRF, the audit log |
| ops/monitor | 117 | Errors broken on purpose in the real app (`tests/ops/boom.php`, test copies only), grouping, masking, alerts, the log reader, health, the summary, clean-up, the watchdog |
| ops/clients | 89 | Clients, plan changes, notes, "view as" end to end, payments, shop names, support, finance, countries, the old /admin links |
| ops/sales | 134 | Home page leads, manual leads and duplicates, the pipeline, owners, follow-ups reminded by the real cron, stalled sign-ups and trials, campaigns (audience, test, send once, rate, unsubscribe, one-click, opt-outs), roles |
| ops/ui | 47 | A real browser on a phone and a computer: sign-in, passkeys, the menu, dialogs, errors, "view as", leads, the board, a campaign; nothing too wide for a phone |

The client-side suites (engine, flows, cron, sw, ui: 462 checks) still pass
with the team alerts moved to Ops.

## 6. Worth knowing

- **IP addresses.** Ops records `REMOTE_ADDR`, as the app does. Behind
  Cloudflare, that is Cloudflare's address unless the server restores the
  visitor's (mod_remoteip with Cloudflare's ranges). Check one audit log
  row after deploying.
- **Breaking things on purpose** (`/__boom/…`) works only when `APP_ENV` is
  local or testing.
- **Local passkeys.** Over plain http on localhost, passkeys use a test-only
  workaround (`ops_passkey_plain_http()`). Production needs nothing.
- **Times.** The database and the forms work in Lagos time. Ops compares
  times in SQL, so the PHP time zone doesn't matter.

## 7. Left for later

- **The client data export** (Support): every client's data in a branded,
  signed Excel workbook with several tabs, in the client's colours,
  emailed to them. It needs PhpSpreadsheet. The `exports` permission
  already exists for it.
- Campaigns are plain text with one button. Images and opens/clicks
  tracking are not built.
- Leads can't yet be imported from a spreadsheet.
