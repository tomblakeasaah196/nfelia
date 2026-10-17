# PR2: Tasks & Calendar (handoff)

You are taking over a half-built feature in **Nfelia** (repo `tomblakeasaah196/nfelia`). The previous
session built the core and ran out of budget. Your job: finish the two remaining parts, prove they work,
open the PR, drive it to green, and report back briefly. The owner is Tom (tomblakeasaah@gmail.com).
Read this whole file before touching code.

---

## 1. Who this is for, and how to talk

- **Product:** Nfelia, invoices/debts/stock/profit app for **Nigerian informal businesses**: hair vendors
  (Lekki Town Square), fashion designers/stylists (Agungi), event planners, IG/WhatsApp sellers.
  Mostly on phones, paid mobile data, low-tech.
- **Voice:** plain, warm Nigerian English. Short sentences. No enterprise words ("protocol", "module",
  "pipeline", "CRM", "SKU"). Pidgin only where it already exists on the landing page.
- **Tom's rules:** be genuinely honest (no invented customers or numbers), "sell first, then build", keep
  things simple, few words, icons over text. **Never merge without Tom saying so.** Never name
  competitors (Bumpa) on the page: an unverifiable "nobody else has it" claim is a misleading-ad risk.
- **Report style:** brief. Lead with what's done and what Tom must do.

## 2. Ground rules

- Branch: **`claude/what-went-wrong-1o93px`** (already holds all PR2 work so far, on top of `main`).
  Commit there, push with `git push -u origin claude/what-went-wrong-1o93px`.
- Commit message footer (required):
  ```
  Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
  Claude-Session: <your session link>
  ```
  PR body ends with `🤖 Generated with [Claude Code](https://claude.com/claude-code)` and the session link.
  No model names in commits/PRs/code.
- GitHub: use the GitHub MCP tools (no `gh`). Open the PR when the work is done and verified, subscribe to
  its activity, fix CI until green, then tell Tom. **Do not merge.**
- **Deploy:** merging to `main` runs GitHub Actions "CI + Deploy": lint (`php -l`), collation guard, secret
  sweep, deploy-exclusion proof, then a webhook that runs `bin/deploy.sh` on cPanel (tar with
  `.deployignore`, `composer install`, `php db/migrate.php`). Migrations are forward-only SQL in
  `db/migrations/` and run automatically. `docs/`, `bin/`, `db/` are never deployed.
- CI forbids: tracked `*.sql` outside `db/migrations/`, `utf8mb4_0900` in non-comment PHP lines,
  real-looking secrets. Run `docs/handoff/testkit/ci_step_{2,3,4,5}.sh` from the repo root before pushing.

## 3. Skills: install and use them

Tom's org has these plugins. Enable/load them first (`SearchPlugins`/`ListSkills`, then the `Skill` tool):
- **`vectorlab-ux-skills`**: `ux-audit` (run before declaring any UI done), `page-patterns`, `motion`,
  `anti-slop`, `typography`, `colour-palette`, `viewports`, `labels`, forms/validation skills.
- **`frontend-design`** (`frontend-design:frontend-design`) for the landing-page work.

If a plugin can't be loaded, apply these hard rules anyway (they're what the previous audit enforced):
- Read `.ux-profile.md` (root) first. Brand burgundy `#8B004A`, ink `#1F0A14`, secondary `#5B4650`,
  money in `#00764A`, owed `#B42318`, WhatsApp green on white `#0E7A3F` (contrast-checked).
- No all-caps eyebrow labels, no rainbow gradients, no glassmorphism, no cartoon people, no invented proof.
- Motion: transform/opacity only, 100/150/200/300ms, ease-out in / ease-in out, honour reduced motion.
- Type: ≤3 weights, none above 700, no text under 12px, line-height ≥1.1.
- Tap targets ≥44px. No sideways scroll at 375px. Breakpoints on public pages: 767.98/768 only.
- Validation fires on blur, clears on input, submit shows all errors and focuses the first.
- Icon-only buttons need `aria-label` plus a tooltip. Destructive actions confirm and name the thing.
- Screenshot at 375 and 1280 and look at them before calling it done.

## 4. What Tom decided (10 questions, all answered)

1. Calendar shows **my plans + business dates** (invoices due, debts due, deliveries, quotes, orders).
2. Task style: **tick box + star** (to do / done; star = important; date/time/notes optional).
3. **Tasks and appointments** (appointments have start/end time). Plus system-generated
   **delivery deadlines for customer orders**, for pressure.
4. Link to a customer when relevant; appointments get a **one-tap WhatsApp reminder to the customer**.
5. **Deliver-by** on invoices and shop orders: **required on our side**, optional for the customer to
   see. Quick options: **Immediate** (right away = today) or **Pick a date**.
6. Pressure: **countdown + nudges**: amber "3 days left", red "Due today", red "2 days late"; stays at
   the top of Today until marked delivered; reminders 2 days before, the morning of, and every morning
   while late.
7. Auto to-dos: **all four**: money coming in, orders & delivery, restock, follow-up quotes.
8. Reminders: **all four channels**: bell, morning plan email (7am), email at each reminder time,
   **phone push**. A **PWA comes next**, so push must be built to plug straight into it.
9. Staff: **assign; everyone sees all** (money items hidden from staff, like the Debts page).
10. **Ready-made step plans by trade** (sewing job, customer order, event, supplier order, custom).

## 5. What is already built (on the branch, tested)

**Data**: `db/migrations/20261017170000_tasks_and_calendar.sql`
- `tasks`: tasks, appointments and job-plan steps (`parent_id`), `is_starred`, `due_date/time`,
  `end_time`, `customer_id`, `invoice_id`, `plan` (template key on the parent), `step_key='deliver'`,
  `repeat_every` (day/week/month; the next one is created **when ticked**), `spawned_from`,
  `remind_before` (minutes), **`remind_at` computed on save**, `reminded_at` (the row is the queue),
  `assigned_to`, `created_by`, `done_at`, soft delete.
- `planner_dismissals`: hand-ticked auto items (restock hides for 7 days, others for good).
- `planner_sends (user_id, kind, send_date)`: once-a-day guard for the morning plan.
- `push_subscriptions`: endpoint (+ sha256 hash unique), p256dh, auth, failures, last_sent_at.
- `invoices`: `deliver_mode` (immediate|date), `deliver_by`, `deliver_show`, `delivered_at`.
- `users`: `morning_email`, `reminder_email` (default 1); **`role` widened to owner/admin/staff**
  (Settings saved 'admin' into an ENUM that lacked it).

**Backend**
- `app/helpers/planner.php`: all the logic. Key functions: `planner_today()` (Africa/Lagos),
  `planner_fetch_tasks()`, `planner_task_item()`, `planner_auto_items()` (deliver-by, delivery notes,
  invoices due, debts, quotes, shop orders, restock), `planner_sections()`, `planner_open_jobs()`,
  `planner_templates()`, `planner_plan_dates()`, **`planner_day_for_user()`** (late + today + deliveries
  due within 2 days, for one person: built for the morning plan), **`planner_notify()`** (bell row,
  `type='general'`, never throws), `planner_remind_at()`, `planner_next_date()`,
  `planner_delivery_from_post()`, `planner_delivery_line()`, `planner_when()`, `planner_wa_link()`.
- `app/helpers/webpush.php`: **Web Push with no library** (RFC 8291 aes128gcm + RFC 8292 VAPID via
  openssl/hash_hkdf). Verified byte-for-byte against the RFC 8291 test vector. API: `push_enabled()`,
  `webpush_send($sub, $msg, $urgency, $ttl)`, **`push_to_user($db, $user_id, ['title','body','url','tag'], $urgency)`**
  (deletes 404/410 subscriptions, drops after 5 failures, never throws). Keys: `VAPID_PUBLIC_KEY`,
  `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` in `.env` → constants in `config/app.php`.
  `bin/vapid-keys.php` prints a new pair. Without keys, push is off and the UI says "Coming soon".
- `app/controllers/TasksController.php`: page + JSON endpoints (CSRF via `X-CSRF-Token` header):
  `feed`, `job/{id}`, `save`, `tick`, `star`, `delete`, `dismiss`, `delivered`, `plan`, `prefs`,
  `push-subscribe`, `push-unsubscribe`, `push-test`. Every change returns the refreshed board.
  Assignment already sends bell + push (via `after_response`).
- `InvoiceController`: delivery saved on create/edit (required), `set-delivery/{id}`, `mark-delivered/{id}`
  (also ticks the job's delivery step). `OrderController::approve` requires a delivery choice and copies
  it to the new invoice (also fixed: every `flash()` call there had its arguments swapped).
- `CustomerController::view`: "Coming up with {name}" list.
- `api/ajax/notifications.php`: bell now shows business-wide rows **plus rows for this user only**.
- `index.php`: loads `webpush.php` + `planner.php`; route `tasks` → `TasksController`.

**Frontend**
- `views/tasks/index.php` + `assets/js/planner.js` (one Alpine component): Tasks tab (quick add with
  When? chips, New appointment, New job plan, Everyone/Mine when staff exist, Jobs in progress cards,
  Late / Today / Coming up / No date / Done today), Calendar tab (6-week grid, dots on phones, labels on
  desktop, day list), editor sheet, job-plan builder (dates counted back from the finish date), job detail,
  Reminders sheet (phone notifications on/test/off; morning email; reminder emails), undo toasts, focus trap.
- `assets/js/push.js` (`window.NfeliaPush`: supported/current/enable/disable/sync) and **`/sw.js`**
  (push + notificationclick only, **no fetch handler**: the PWA adds caching there later).
- `views/partials/deliver_fields.php` used by the invoice form, invoice page and order approval.
- Invoice page "Delivery" card (countdown, Mark delivered, Change date, Plan the job); customer-facing
  invoice and PDF show "Delivery: …" when `deliver_show`.
- Nav: sidebar "Tasks & Calendar"; phone bottom bar **Tasks replaced Clients** (Clients is in Menu).
  Mention this to Tom; easy to revert in `views/layouts/app.php` `$mob_nav`.

**Tests that passed** (local MariaDB + Chromium, scripts in `docs/handoff/testkit/`):
`tasks_interact.js` 54/54, `deliver_test.js` 25/25, RFC 8291 vector + VAPID signature checks, CI steps.

## 6. Traps already hit (don't repeat them)

- **Alpine calls any function an expression returns.** `x-show="toast.undo"` ran the undo. Use `!!fn`.
- **Alpine `x-for` variables shadow component data on assignment.** A loop var named `job` made
  `this.job = …` write into the loop scope. Never name a loop variable after a component property.
- Playwright's `page.request` does **not** send the Secure session cookie over http://127.0.0.1. Send
  requests from inside the page (`page.evaluate(fetch…)`).
- CDNs (Tailwind/Alpine/jQuery) are unreachable in the sandbox. The testkit stubs Tailwind, compiles
  Tailwind v3 locally from the page HTML + `views/**/*.php` + `assets/js/planner.js`, and serves Alpine
  and jQuery from `node_modules`.
- The schema dump has **no stored procedures**; `InvoiceController` calls `CALL generate_doc_number`.
  Production has it. Locally load `testkit/local_proc.sql.txt`. dompdf needs `composer install` in the copy.
- Timezones: the **server PHP clock is +02:00, the DB session is +01:00**. In planner code use
  `planner_now()/planner_today()` (Africa/Lagos) or SQL `NOW()`/`CURDATE()`, never bare `date()`.
- `notifications.type` is an ENUM: use `'general'` (don't ALTER it; prod may hold values the dump lacks).
- `cron/` **is web-reachable** (it's deployed into the docroot). Any new cron script must start with
  `if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }`. Never load `subscription_helpers.php`
  in web code (it redeclares `is_owner()`).
- Existing app screens still use uppercase micro-labels. New pages follow `.ux-profile.md`; small additions
  inside old screens may match their surroundings.

## 7. Remaining work

### A. Reminders (bell, morning plan email, reminder email, phone push)

Create **`cron/planner.php`** (CLI only), meant to run **every 5 minutes**:
```
*/5 * * * * /usr/local/bin/php /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1
```
It loads `config/database.php`, `config/app.php`, `config/mail.php`, `app/helpers/functions.php`,
`auth.php` (for `after_response`, if needed), `mail.php`, `webpush.php`, `planner.php`. Use a lock
(`GET_LOCK('nfelia_planner', 0)`) so two runs never overlap. Two jobs:

1. **Due reminders.** `SELECT … FROM tasks WHERE remind_at <= NOW() AND reminded_at IS NULL AND done_at IS NULL AND deleted_at IS NULL LIMIT 200`.
   - Stamp `reminded_at = NOW()` first (even if sending fails). If `remind_at < NOW() - INTERVAL 1 DAY`,
     stamp only; never send stale reminders (first run after cron setup).
   - Recipient: `assigned_to`, else `created_by`. Skip inactive users and businesses that can't use the
     app (subscription `status` in suspended/cancelled, or trial with `trial_ends_at < CURDATE()`).
   - Send: bell (`planner_notify(…, $user_id, title, message, 'tasks')`); push
     (`push_to_user(…, ['title' => …, 'body' => …, 'url' => 'tasks', 'tag' => 'task-'.$id], 'high')`);
     email if `users.reminder_email = 1` via `send_branded_mail($email, $name, $subject, 'task_reminder', $vars, $business)`.
   - Copy: title = the task title; body e.g. "Today at 2:00pm · Lara Coker" or "Due tomorrow". Appointment
     with a customer: include the customer and the WhatsApp link from `planner_task_item()['wa']` in the email.
2. **Morning plan**, once per user per day, between **07:00 and 11:00 WAT**:
   - For each active user of an eligible business with no `planner_sends` row (`kind='morning'`, today):
     `$day = planner_day_for_user($db, $user, $business, $today)`. If late+today+soon is empty, insert
     the send row and skip (no empty emails).
   - Insert the `planner_sends` row first (`INSERT IGNORE`; continue only if a row was inserted).
   - Bell: "Today: 3 tasks, 1 delivery, ₦45,000 due" → `tasks`. Push: same summary, tag `morning`.
     Email if `users.morning_email = 1`: template `morning_plan` listing Late (red), Today (by time),
     Deliveries in the next 2 days (countdown labels), each line linking into the app; button "Open my day".
   - Deliveries: the countdown labels from the items already give "2 days left / Deliver today / N days late",
     which covers "2 days before, the morning of, every morning while late".
   - `$business` must be a full `businesses` row (name, currency_symbol, logo_path, primary_color, id).
3. Templates: `views/emails/task_reminder.php`, `views/emails/morning_plan.php`. Copy the inline-style
   approach of `views/emails/signin_link.php` (tables, inline CSS, `$biz_color`); keep them short, sentence
   case, no all-caps (fix the uppercase button style when you copy it). Subjects: "Reminder: {title}",
   "Your day: {n} things today" (UTF-8 is already handled by the mailer).
4. Optional and cheap: a small "Today" card on the dashboard using `planner_day_for_user()` (3 items +
   "Open Tasks"). Ask Tom first if unsure; don't add a red banner (he chose countdown + nudges, not strong).
5. **Test it:** run `php cron/planner.php` against the local DB with `python3 docs/handoff/testkit/smtp_sink.py`
   as the SMTP server (point `MAIL_HOST/PORT` at it in the copy's `.env`, no encryption). Assert: reminder
   stamped and bell row with `user_id`; stale reminder stamped, not sent; morning plan sent once (second run
   sends nothing); empty day sends nothing; staff only get their own tasks; email HTML renders (save it and
   screenshot it). For push, generate keys with `bin/vapid-keys.php` into the copy's `.env`, insert a fake
   subscription, and check `webpush_send()` builds the request (the sandbox can't reach push services; a
   404/0 status is expected and must not crash; a 410 must delete the row; mock with a tiny local HTTPS-less
   endpoint if you want a 201).
6. Docs for Tom in the PR body: the cron line; how to make VAPID keys on the server
   (`php ~/repositories/nfelia/bin/vapid-keys.php`, paste into the docroot `.env`, never change them later);
   that iPhone push needs "Add to Home Screen" (the PWA will make this smooth).

### B. Landing page: sell Tasks & Calendar

- File: `views/home/index.php` + `assets/css/home.css` (plain CSS, no Tailwind). Sections: hero, pains,
  `<ol class="fix-list">` rows (`li.fix[data-reveal]` with `.fix-media` → `.phone` or `.laptop` screenshot
  from `assets/img/home/`, and `.fix-copy` → `h3` + `p` + `ul.ticks`), extras grid (`ul.extras`, icons from
  `views/home/_icons.php` via `nf_icon()`; add a `calendar` icon in the same hand-drawn stroke style),
  stories, how, pricing (`.price-includes ul.ticks`), FAQ (`details`), final CTA, WhatsApp widget.
- Add **one fix row**, e.g. h3 "Never miss a delivery date" (or "Your day, planned"); p: deliveries,
  fittings and money due, on one list every morning, with a countdown so nothing slips; ticks:
  "Reminders on your phone and by email", "Ready-made plans for sewing jobs and events". Keep it short.
  Add "Tasks & calendar with reminders" to the price includes; add one FAQ ("Will it remind me?").
  Only claim phone notifications if Tom has set the VAPID keys by launch, otherwise say "by email and in
  the app" and add phones later.
- Screenshot: Ivie's Tasks tab on a phone (390×844 @2x), same frame and size as the other phone shots
  (`assets/img/home/shop-phone.webp` is 780×1688 WebP, ~50KB). Use `testkit/tasks_test.js` (it compiles
  Tailwind) after loading `planner_seed.sql.txt`; crop to the Today section with the delivery countdown
  visible; save WebP with `cwebp` or PIL. Real app, demo data only.
- Then: `testkit/shoot_home.js` (renders `/`), `audit.js`, `interact.js` (both must stay 100% pass),
  look at 375 and 1280, run `ux-audit`.

### C. Ship

1. Re-run everything: `tasks_interact.js`, `deliver_test.js`, the new cron tests, home audits, CI steps.
2. **Also verify visually** (not yet done): the invoice form Delivery card, the order approve card, the
   customer "Coming up" card, and a **staff login** (insert a `role='staff'` user for business 1):
   money items hidden, Mine/Everyone toggle, assigning sends a bell row to the assignee.
3. Commit, push, **open the PR** "Tasks & Calendar" against `main` (check for a PR template first).
   Body: what it does in plain words, what Tom must do after merge (cron line, VAPID keys), the bottom-bar
   change, and the test results. Subscribe to PR activity and fix CI until green. **Don't merge.**
4. Tell Tom in a few lines: done, what to set up, and anything that needs his decision.

## 8. Local environment (fresh container)

1. MariaDB: `apt-get install -y mariadb-server` (if missing), start `mysqld_safe --user=mysql &`, create
   DB `smartqaq_nfelia_core` and user `nf`/`nfpass123` with all privileges.
2. **Ask Tom for `schema-local.sql`** (the production structure dump he uploaded before) and load it.
3. Copy the repo to a scratch app dir; write its `.env` (`APP_URL=http://127.0.0.1:8099`, DB_* above,
   `APP_ENV=local`); `composer install --no-dev`; `php db/migrate.php`; `php docs/handoff/testkit/seed.php`
   (demo businesses: Ivie Luxe Hair `ivie@demo.nfelia`, Kemi Events `kemi@demo.nfelia`, password `DemoPass1`);
   load `local_proc.sql.txt`; then `planner_seed.sql.txt` (resets planner demo data; re-run before tests).
   Check paths inside `seed.php`/test scripts and adjust them to your scratch dir.
4. Serve: `php -S 127.0.0.1:8099 -t <app> docs/handoff/testkit/router.php` (copy router into the app).
5. Test deps: in the testkit dir `npm i tailwindcss@3 alpinejs@3.14.3 jquery@3.7.1 chart.js@4`;
   Playwright is global (`NODE_PATH=/opt/node22/lib/node_modules`); Chromium at `/opt/pw-browsers`.
   The scripts expect `../apptest` next to the kit and a `node_modules` folder beside them; adjust `S`/`APP`.

Be better than the last session: verify every claim with a run, look at every screen you change, keep the
copy honest and short, and leave Tom with a green PR and two clear setup steps.
