# Nfelia security: posture, audit and what is left

Audit of 20 October 2026 (code at `main` 5c6f279 plus this branch), done
by reading every entry point as an attacker would: outsider, shopper,
staff member of a tenant, hostile tenant, compromised Ops account. It
follows `docs/AUDIT_2026-10.md` (18 October), whose three fix PRs are
confirmed in place; nothing from it is repeated here.

Status words: **fixed** (this branch), **live** (needs a change in a
dashboard or on the server, not code), **decision** (one of the open
questions), **later**.

---

## 1. What an attacker meets now

| Layer | Control |
|---|---|
| Edge | Cloudflare proxy on every web host; TLS to the edge and to the origin |
| Sign-in | bcrypt (cost 12), passkeys (WebAuthn, bound to `APP_URL`), one-time emailed links (hashed, 15 min), 10 wrong passwords per account or 50 per connection in 15 minutes pause sign-in and alert the owner, passwords of 9+ characters with a capital, a number and a symbol, checked against known breaches |
| Sessions | `__Host-` cookies, Secure, HttpOnly, SameSite=Lax, strict mode, id regenerated at sign-in and unlock, 2-hour idle lock, 12-hour life, a session epoch that ends every other session when the password changes; remember-me tokens stored as SHA-256 |
| Requests | CSRF token on every state change (forms and `X-CSRF-Token`), same-site shop subdomains give no implicit protection and none is assumed |
| Data | every read and write scoped by `business_id`; posted ids checked with `owned_by_business()`; roles enforced in controllers, not menus; Ops has its own accounts, roles, CSRF and append-only audit log |
| Input | prepared statements everywhere; dynamic `ORDER BY` from whitelists; output through `e()`; API text rendered with `textContent` |
| Files | pictures re-encoded by GD; private files under `storage/`, served only after an ownership check; uploads never execute; uploaded PDFs sandboxed |
| Headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP baseline (`frame-ancestors 'self'; base-uri 'self'; object-src 'none'`), `Permissions-Policy`, COOP |
| Payments | Paystack webhooks verified with HMAC-SHA512 over the raw body; references claimed once; amount and currency checked |
| Limits | emailed links, passkey sign-in, browser error reports, public PDFs, verify codes, shop forms, sign-ups: all keyed on the visitor's real address |

**The repository is public** (owner's decision, for free Actions minutes;
see RUNBOOK §6 for going private). An attacker therefore has the code,
the server paths, the panel username, the database name and the deploy
flow. The address of the server is public anyway (it is in the SPF
record). Nothing above relies on any of that staying unknown: every
secret lives only in the docroot `.env` and in the Actions secrets, and
the audit assumed a reader of the source. The two things the public code
makes cheaper for an attacker are exactly two of the open decisions
below: reaching the origin directly (§5) and injecting a script (§6).

---

## 2. Findings and status

### 2.1 Fixed in this branch

| # | Finding | Fix |
|---|---|---|
| S-01 | Password sign-in had no limit at all (unlimited guessing; each guess costs the server a bcrypt) | counted per account (10) and per connection (50) in 15 minutes; owner alerted once an hour; the right password clears the count |
| S-02 | Three limits (reset links, passkey sign-in, browser errors) keyed on `REMOTE_ADDR`, which behind a proxy is the edge's address, shared by every visitor: anyone could lock everyone out of password recovery, and the limits never slowed an attacker | `client_ip()` everywhere; also the remember-me table, the home form and the purge audit (which trusted `X-Forwarded-For`). Found on 20 October: this LiteSpeed already substitutes the visitor's address into `REMOTE_ADDR`, so on the shared server the three limits happened to work; on any other server (the VPS) they would not have. Fixed for both |
| S-03 | "Keep me signed in" tokens stored in clear for 30 days | stored as SHA-256; old raw tokens accepted once and rewritten |
| S-04 | Changing a password ended nothing else | `users.session_epoch`; every other browser and kept device out on its next request |
| S-05 | Passwords needed only 8 characters, nothing else | 9+, a capital, a number, a symbol, Have I Been Pwned range check; the forms show a live checklist |
| S-06 | No CSP, no Permissions-Policy, obsolete `X-XSS-Protection` | baseline CSP, Permissions-Policy, COOP, `X-XSS-Protection: 0` in `.htaccess` |
| S-07 | Public document PDFs rendered on every request, remote fetching on, no limit | rendered once per version and kept; 60 an hour per connection; remote fetching, PHP and paths outside the app off |
| S-08 | Thirteen public and sign-in pages loaded the Tailwind compiler from a CDN (a third party's script on the sign-in and invoice pages) | a compiled stylesheet of our own; Alpine from our own files; no Google Fonts on Nfelia's own pages |
| S-09 | `vendor/autoload.php` and `composer.json` answered 200 | `vendor/`, `lib/` and the manifest denied |
| S-10 | A bare `error_log` file in the docroot would be public (`.htaccess` only denied `.log`) | denied by name |
| S-11 | After every deploy, sessions, private files and the seal key were world-readable (the permission pass opened them) | kept closed to other users |
| S-12 | Uploaded refund-proof PDFs served inline without a sandbox | `Content-Security-Policy: sandbox` |
| S-13 | CI workflow token had default scopes; two old zip snapshots tracked; unused `PAYSTACK_WEBHOOK_SECRET` in the template | read-only token; removed; removed |
| S-14 | No backups at all | `bin/backup.sh`, nightly, off-site to R2 when configured; Ops → Health watches |

### 2.2 Needs a change outside the code (live)

| # | Finding | What to do | Where |
|---|---|---|---|
| L-01 | The repository is **public**: anyone reads the code, the server paths, the zone id, the audit documents | decided 20 October: the token sequence on 1 November, private as soon as a manual deploy with the token has worked, at the VPS move at the latest | RUNBOOK §6.2 |
| L-02 | `main` is unprotected; a push to `main` is code on the server | branch ruleset, required checks, 2FA | RUNBOOK §6.1 |
| L-03 | Nfelia shares its Unix user with seven other sites (WordPress tooling present) | move Nfelia to its own account or server; until then keep the other sites patched | VPS-MIGRATION.md |
| L-04 | `.env` points the database at the public IP | reassessed after run 3: the user's only grant is bound to the server's hostname, the connection stays on the machine, and nothing is gained before the VPS. **Leave it.** | RUNBOOK §4.2 |
| L-05 | Five `.env` backups, an `.htaccess` backup, `composer.phar` in the docroot | move / remove | RUNBOOK §4.3 |
| L-06 | DMARC at `p=none` | decided 20 October: `p=quarantine` on 3 November, after two weeks of reports | RUNBOOK §5 |
| L-07 | HSTS off at Cloudflare | decided 20 October: on, 6 months, no `includeSubDomains`, no preload; CLOUDFLARE.md prompt C | §4 below |
| L-08 | The origin answers requests that bypass Cloudflare | decided 20 October: locked in `.htaccess` in this branch; the test before the merge, the monthly check and the rollback are in RUNBOOK §4.4 | §5 below |
| L-09 | Nothing watches the site from outside | decided 20 October: UptimeRobot on `/login` and `ops.nfelia.com/signin` from the day of the VPS move, nothing before (VPS-MIGRATION.md §4) | |
| L-10 | **Cloudflare account: 2FA off on both members** (export of 20 October). Whoever signs into that account owns the DNS, and with it the site, the mail and every password reset | an authenticator app and backup codes on both accounts (My Profile → Authentication), then Manage Account → Members → Enforce 2FA | Cloudflare; CLOUDFLARE.md C-1 |
| L-11 | The edge still accepts TLS 1.0 and 1.1 | SSL/TLS → Edge Certificates → Minimum TLS Version → 1.2 | CLOUDFLARE.md prompt B |
| L-12 | DNSSEC off: a forged DNS answer for `nfelia.com` cannot be detected by resolvers | DNS → Settings → Enable DNSSEC, then the DS record at the registrar | CLOUDFLARE.md C-8 |
| L-13 | The mailbox password of `no-reply@nfelia.com` is 9 characters, on a server whose SMTP and IMAP ports face the internet | a 24-character random password, and `MAIL_PASSWORD` in `.env` in the same minute | RUNBOOK §3 |
| L-14 | No Cloudflare rules at all: nothing slows a sign-in burst before PHP, nothing stops strangers from knocking on the deploy webhook | decided 20 October: the sign-in rate limit and the deploy-webhook rule, CLOUDFLARE.md prompt C. Bot Fight Mode: considered, then **left off** (it cannot exempt Paystack or GitHub on the Free plan); prompt D there keeps the test for the day it is reconsidered | |
| L-15 | A second database user, `smartqaq_nfelia_it`, holds ALL PRIVILEGES on the database and nothing in the code uses it (run 3): one more password that opens everything | owner's decision 20 October: **kept as it is**. It does not move to the VPS (the dump carries tables, not users), so it ends with the shared account | |

### 2.3 Decisions pending (the ten questions)

All twelve answered on 20 October:

1. HSTS: on, 6 months, no `includeSubDomains`, no preload.
2. Origin lock: now, in `.htaccess` (this branch).
3. CSP: report-only for two weeks, then enforce (this branch).
4. Edge rules: the sign-in rate limit and the deploy-webhook rule; no
   Ops geo rule; Bot Fight Mode considered and left off.
5. VPS provider: between a Western European server and Database Mart
   (United States); decided at the move, not before.
6. Mail: stays on cPanel for good; the app only changes its SMTP host.
7. Outside monitoring: UptimeRobot from the day of the move, nothing before.
8. The second database user `smartqaq_nfelia_it`: kept as it is.
9. DMARC: `p=quarantine` on 3 November.
10. Repository: token sequence on 1 November, private once a manual deploy
    with the token has worked, at the VPS move at the latest.
11. Bot Fight Mode: off (confirmed).
12. Countries for an Ops geo rule: not wanted (the rule was not chosen).

CLOUDFLARE.md carries the prompts that apply the Cloudflare side;
RUNBOOK.md and VPS-MIGRATION.md carry the rest.

### 2.4 Later (background)

- A per-tenant disk quota for uploads.
- `html2pdf.js` on the receipt and delivery pages still comes from cdnjs;
  serve it from `assets/vendor` (add to `bin/build-vendor.js`).
- Pin GitHub Actions to commit SHAs (`actions/checkout`,
  `shivammathur/setup-php`, `actions/upload-artifact`).
- The landing page starts a session for every anonymous visitor (for the
  feedback form's token); a cookie-free landing page would be cacheable.
- Registration says whether an address already has an account (standard
  trade-off; the sign-up limit per connection bounds the enumeration).

---

## 3. Policies the code enforces

| Policy | Value | Where |
|---|---|---|
| Password rules | 9+ chars, capital, number, symbol, not in a known breach (HIBP k-anonymity; skipped, not blocking, if the API is unreachable; `PASSWORD_BREACH_CHECK=off` on test copies) | `password_problem()`, `password_pwned()` in `app/helpers/auth.php` |
| Password hashing | bcrypt, cost 12 | |
| Sign-in attempts | 10 wrong per account, 50 per connection, 15-minute window; the right password clears the account's count; owner alerted (`security.signin_blocked`) | `AuthController::login` |
| Unlock attempts | 8 per 15 minutes, then signed out | `api/ajax/auth.php` |
| Passkey sign-in | 30 per connection per 15 minutes | |
| Emailed links | 30 per connection and 5 per address per hour; reset links 60 min, sign-in links 15 min, single use | |
| Sessions | idle lock 2 h (`LOCK_AFTER_SECONDS`), life 12 h, remember-me 30 days, epoch ends old sessions on password change/reset | `config/app.php`, `signin.php`, `auth_still_member()` |
| Public PDFs | 60 per connection per hour | `pdf_public_guard()` |
| Shop forms | per visitor 10/h per shop, 120/h across shops, 60/h per shop, 10 emails/day per address, honeypot | `public_form_guard()` |
| Sign-ups | 5 per connection per day; Gmail dot/plus variants count as one | |
| Verify codes | 120 per connection per hour | `VerifyController` |
| Browser error reports | 60 per connection per 10 minutes | |

---

## 4. HSTS (decided: on, 6 months, no includeSubDomains)

What it does: once a browser has seen the header it refuses plain HTTP to
the host for `max-age` seconds. Cloudflare → SSL/TLS → Edge Certificates →
HSTS sets it for every proxied host at once.

- **Without `includeSubDomains`** (safe today): covers `nfelia.com` only.
  Shops at `*.nfelia.com` and `ops.nfelia.com` learn it the first time
  each is visited over HTTPS only if the header is sent there too
  (Cloudflare sends it on every proxied host, so they are covered
  individually).
- **With `includeSubDomains`**: also binds `mail.nfelia.com` and
  `webmail.nfelia.com`, which are **not** proxied and present cPanel's
  certificate. Browsers would then refuse `http://webmail.nfelia.com` and
  warn on a mismatched certificate. Only choose it if webmail is used via
  the server's own hostname instead.
- **`preload`** is permanent (browser lists): only after a month with
  `includeSubDomains` on and nothing broken.

Decided 20 October as recommended: on, `max-age` 6 months, no
`includeSubDomains`, no preload; applied with CLOUDFLARE.md prompt C.
Revisit `includeSubDomains` at the VPS, when the mail hostnames are settled.

---

## 5. Locking the origin to Cloudflare (decided: now; a secret header, not an address list)

Before this branch, `curl --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/login`
answered 200: whoever knew the IP (it is in the SPF record) skipped every
Cloudflare rule, the rate limits and the WAF included.

The first version of the lock was an allow-list of Cloudflare's address
ranges (`Require ip`). The test on the server before the merge refused
requests through Cloudflare as well (403 both ways), which showed why:
this LiteSpeed already presents the visitor's own address as
`REMOTE_ADDR`, taken from Cloudflare's `CF-Connecting-IP` header, so no
request ever arrives "from" a Cloudflare address and an address list can
never match. (The report had shown `remote_addr` equal to the probing
machine's own address; it was read as the edge's by mistake.)

The lock is therefore a shared secret. Cloudflare adds a request header,
`X-Nfelia-Edge: <token>`, to everything it forwards to the origin (a
Transform Rule, CLOUDFLARE.md prompt E), and `.htaccess` refuses any
request without the right value:

```apache
RewriteCond armed ^armed$
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteCond %{HTTP:X-Nfelia-Edge} !^<token>$
RewriteRule .* - [F,L]
```

(`.*`, not `^`: the form proven on this LiteSpeed on 20 October.)

Visitors never see the header; a caller who knows the server's address
does not know the token. The committed file carries placeholders
(`__EDGE_ARMED__`, `__EDGE_TOKEN__`); the token lives in the docroot
`.env` as `EDGE_TOKEN`, nowhere in the public repository, and
`bin/deploy.sh` writes it in on every deploy. With no token in `.env` the
deploy removes the block and says so, and an unsubstituted copy of the
file is inert (the first line never matches), so a missing or mistyped
token never locks everyone out. `.well-known/` stays open because cPanel's
AutoSSL fetches its validation files for `mail.nfelia.com` and
`webmail.nfelia.com` directly (those names are not proxied); without that,
the mail names would fall out of the certificate at the next renewal and
mail clients would start warning. Everything else already arrives through
Cloudflare: the deploy webhook, Paystack, `ops.nfelia.com`, the shops. The
cron does not use HTTP.

RUNBOOK §4.4 has the order of operations (token, Cloudflare rule, `.env`,
deploy, checks), the test that proves the header arrives before the lock
is armed, rotation and the two rollbacks. `bin/env-report.sh` section 5
shows whether the header reaches PHP (`x_nfelia_edge=present`) and
section 6 whether the origin still answers direct requests and in what
state the deployed block is. On the VPS the same is done with the
firewall plus Authenticated Origin Pulls (a client certificate only
Cloudflare holds); the header check can stay as a second layer
(`docs/ops/vps/`).

The edge rules decided the same day (a rate limit on sign-in POSTs, a WAF
rule that lets only GitHub Actions reach `/webhook/deploy.php`) are
applied with CLOUDFLARE.md prompt C.

---

## 6. CSP for scripts (decided: report-only first, in this branch)

The baseline is enforced. The full policy is on trial from this branch:
`.htaccess` sends it as `Content-Security-Policy-Report-Only`, so nothing
is blocked and browsers post what would have been to `/api/csp`
(`api/ajax/csp.php`), which files each report as an error of kind
"Content policy" in Ops → Errors, grouped by directive and foreign host,
with the page and the business. Reports caused by browser extensions are
dropped; the endpoint is limited like the browser-error one.

The policy on trial allows what the code is known to use: our own origin,
`cdnjs.cloudflare.com` (html2pdf.js on four pages), Google Fonts on the
document pages, `i.ytimg.com` thumbnails and `youtube-nocookie.com` frames
for shop videos, `nfelia.com` fonts and images on shop subdomains, `data:`
and `blob:` images. Anything else that turns up in the reports is either a
page nobody listed or an injection.

What stands between the trial and enforcement, counted in the code: 40
inline `<script>` blocks and 52 inline `on…=` handlers in `views/` (they
need `'unsafe-inline'` today: nonces for the blocks, listeners for the
handlers), and Alpine's standard build, which evaluates expressions with
`new Function` (it needs `'unsafe-eval'`; Alpine's CSP build does not, at
the price of simpler expressions). After two clean weeks: move the header
to `Content-Security-Policy` as it is, then drop the two `unsafe-`
keywords as that work lands (RUNBOOK §4.5).

---

## 7. Verified live on 20 October 2026

From the server report: `.env`, `.git`, `composer.lock`, `storage/*`,
`config/*`, `.user.ini`, `php.ini`, `error_log` all denied; `/cron/*` not
served; `/views/404.php` served (the error page); the deploy webhook answers
405 to GET; no database dump at the root; `sw.js`, the manifest and
`offline.html` public; the session cookie `__Host-nfelia_session; path=/;
secure; HttpOnly; SameSite=Lax`; security headers as listed in §1 minus
the ones this branch adds; SPF, DKIM and PTR valid; TTFB ~150 ms from the
server through Cloudflare.

From the Cloudflare export of the same day: encryption Full (strict),
Always Use HTTPS on, no WAF custom rules, no rate limiting, no Page Rules,
Bot Fight Mode off, security level medium; the managed free ruleset and
the DDoS protection blocked 264 of the week's 25,503 requests; minimum
TLS 1.0 and DNSSEC off (L-11, L-12); 2FA off on both account members
(L-10).

Still to verify after merge: the new headers in a live response (the
report-only policy among them); the `vendor/` denial; the origin lock
(direct 403, `.well-known/` open, AutoSSL still passing for the mail
names: RUNBOOK §4.4); HSTS and the edge rules once prompt C has run.

---

## 8. Dependencies

`composer audit` (20 October 2026): no known advisories. dompdf 3.1.6,
PhpSpreadsheet 5.10.0, lbuchs/webauthn 2.2.0, php-qrcode 6.0.1, PHPMailer
7.0.2 (vendored in `lib/`), jQuery 3.7.1, Alpine 3.14.3. Dependabot alerts
on the repository cover Composer and npm once enabled.
