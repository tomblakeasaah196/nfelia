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
| S-02 | Three limits (reset links, passkey sign-in, browser errors) keyed on Cloudflare's edge address, shared by every visitor: anyone could lock everyone out of password recovery, and the limits never slowed an attacker | `client_ip()` everywhere; also the remember-me table, the home form and the purge audit (which trusted `X-Forwarded-For`) |
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
| L-01 | The repository is **public**: anyone reads the code, the server paths, the zone id, the audit documents | make it private once deploys can fetch with a token | RUNBOOK §6.2 |
| L-02 | `main` is unprotected; a push to `main` is code on the server | branch ruleset, required checks, 2FA | RUNBOOK §6.1 |
| L-03 | Nfelia shares its Unix user with seven other sites (WordPress tooling present) | move Nfelia to its own account or server; until then keep the other sites patched | VPS-MIGRATION.md |
| L-04 | `.env` points the database at the public IP | reassessed after run 3: the user's only grant is bound to the server's hostname, the connection stays on the machine, and nothing is gained before the VPS. **Leave it.** | RUNBOOK §4.2 |
| L-05 | Five `.env` backups, an `.htaccess` backup, `composer.phar` in the docroot | move / remove | RUNBOOK §4.3 |
| L-06 | DMARC at `p=none` | `p=quarantine` after two clean weeks | RUNBOOK §5 |
| L-07 | HSTS off at Cloudflare | decided 20 October: on, 6 months, no `includeSubDomains`, no preload; CLOUDFLARE.md prompt C | §4 below |
| L-08 | The origin answers requests that bypass Cloudflare | decided 20 October: locked in `.htaccess` in this branch; the test before the merge, the monthly check and the rollback are in RUNBOOK §4.4 | §5 below |
| L-09 | Nothing watches the site from outside | an uptime check (free: UptimeRobot, Better Stack) on `/login` and `ops.nfelia.com/signin` | |
| L-10 | **Cloudflare account: 2FA off on both members** (export of 20 October). Whoever signs into that account owns the DNS, and with it the site, the mail and every password reset | an authenticator app and backup codes on both accounts (My Profile → Authentication), then Manage Account → Members → Enforce 2FA | Cloudflare; CLOUDFLARE.md C-1 |
| L-11 | The edge still accepts TLS 1.0 and 1.1 | SSL/TLS → Edge Certificates → Minimum TLS Version → 1.2 | CLOUDFLARE.md prompt B |
| L-12 | DNSSEC off: a forged DNS answer for `nfelia.com` cannot be detected by resolvers | DNS → Settings → Enable DNSSEC, then the DS record at the registrar | CLOUDFLARE.md C-8 |
| L-13 | The mailbox password of `no-reply@nfelia.com` is 9 characters, on a server whose SMTP and IMAP ports face the internet | a 24-character random password, and `MAIL_PASSWORD` in `.env` in the same minute | RUNBOOK §3 |
| L-14 | No Cloudflare rules at all: nothing slows a sign-in burst before PHP, nothing stops strangers from knocking on the deploy webhook | decided 20 October: the sign-in rate limit and the deploy-webhook rule, CLOUDFLARE.md prompt C. Bot Fight Mode was asked for as well; the caution and the test for it are there too | |
| L-15 | A second database user, `smartqaq_nfelia_it`, holds ALL PRIVILEGES on the database and nothing in the code uses it (run 3): one more password that opens everything | once confirmed unused by anything else: cPanel → MySQL Databases → Current Users → delete it | decision |

### 2.3 Decisions pending (the ten questions)

Answered 20 October: HSTS (on, 6 months, no `includeSubDomains`); origin
lock (now, `.htaccess`); CSP (report-only first); edge rules (sign-in
rate limit, deploy-webhook rule, Bot Fight Mode). Still to answer: the
VPS provider and stack; mail after the move; outside monitoring; the
DMARC step; when to go private; the second database user; the countries
for an Ops geo rule if wanted. CLOUDFLARE.md carries the prompts that
apply the Cloudflare side.

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

## 5. Locking the origin to Cloudflare (decided: now, in `.htaccess`)

Before this branch, `curl --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/login`
answered 200: whoever knew the IP (it is in the SPF record) skipped every
Cloudflare rule, the rate limits and the WAF included. On shared hosting
the firewall is not ours, so the lock is the first thing in the docroot
`.htaccess`:

```apache
# Only Cloudflare may talk to this site. Ranges: https://www.cloudflare.com/ips/
Require ip 173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18
Require ip 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17
Require ip 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22
Require ip 2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32
```

Several `Require` lines in one section mean "any of these". Everything
else keeps working because it already arrives through Cloudflare: the
deploy webhook, Paystack, `ops.nfelia.com`, the shops, and the certificate
checks for `nfelia.com` itself (its DNS is proxied). The cron does not use
HTTP. The one exception is `.well-known/`: cPanel's AutoSSL fetches its
validation files for `mail.nfelia.com` and `webmail.nfelia.com` directly,
since those names are not proxied, so `.well-known/.htaccess` (in the
repository, copied by the deploy) re-opens that folder with
`Require all granted`. Without it the mail names would drop out of the
certificate at the next renewal and mail clients would start warning.

The cost is the list: if Cloudflare publishes a new range and this file
does not have it, visitors behind that range see 403. `bin/env-report.sh`
section 6 fetches the published list and compares; RUNBOOK §4.4 has the
test to run on the server before the merge, the monthly check and the
one-line rollback. On the VPS the same is done properly with the firewall
plus Authenticated Origin Pulls (a client certificate only Cloudflare
holds), which needs no list (`docs/ops/vps/`).

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
