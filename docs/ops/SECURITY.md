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
| L-04 | `.env` points the database at the public IP | `DB_HOST=localhost` | RUNBOOK §4.2 |
| L-05 | Five `.env` backups, an `.htaccess` backup, `composer.phar` in the docroot | move / remove | RUNBOOK §4.3 |
| L-06 | DMARC at `p=none` | `p=quarantine` after two clean weeks | RUNBOOK §5 |
| L-07 | HSTS off at Cloudflare | decision Q (scope), then Edge Certificates → HSTS | §4 below |
| L-08 | The origin answers requests that bypass Cloudflare | decision Q, then §5 below | |
| L-09 | Nothing watches the site from outside | an uptime check (free: UptimeRobot, Better Stack) on `/login` and `ops.nfelia.com/signin` | |
| L-10 | **Cloudflare account: 2FA off on both members** (export of 20 October). Whoever signs into that account owns the DNS, and with it the site, the mail and every password reset | an authenticator app and backup codes on both accounts (My Profile → Authentication), then Manage Account → Members → Enforce 2FA | Cloudflare; CLOUDFLARE.md C-1 |
| L-11 | The edge still accepts TLS 1.0 and 1.1 | SSL/TLS → Edge Certificates → Minimum TLS Version → 1.2 | CLOUDFLARE.md prompt B |
| L-12 | DNSSEC off: a forged DNS answer for `nfelia.com` cannot be detected by resolvers | DNS → Settings → Enable DNSSEC, then the DS record at the registrar | CLOUDFLARE.md C-8 |
| L-13 | The mailbox password of `no-reply@nfelia.com` is 9 characters, on a server whose SMTP and IMAP ports face the internet | a 24-character random password, and `MAIL_PASSWORD` in `.env` in the same minute | RUNBOOK §3 |
| L-14 | No Cloudflare rules at all: nothing slows a sign-in burst before PHP, nothing stops strangers from knocking on the deploy webhook | decision (which rules), then CLOUDFLARE.md prompt C | |

### 2.3 Decisions pending (the ten questions)

HSTS scope; origin lock now or at the VPS; CSP for scripts (report-only
first); which Cloudflare rules; the VPS provider and stack; mail after
the move; outside monitoring; the DMARC step; when to go private. Asked
on 20 October. CLOUDFLARE.md carries the prompts that apply the Cloudflare
side once answered.

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

## 4. HSTS (decision pending)

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

Recommendation: HSTS on, `max-age` 6 months, no `includeSubDomains` now;
revisit at the VPS when mail hostnames are settled.

---

## 5. Locking the origin to Cloudflare (decision pending)

Today `curl --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/login`
answers 200: whoever knows the IP (it is in the SPF record) skips every
Cloudflare rule. On shared hosting the fix is in `.htaccess`, since the
firewall is not ours:

```apache
# Only Cloudflare may talk to this site. Ranges: https://www.cloudflare.com/ips/
# Review monthly; a change there without a change here locks everyone out.
<RequireAny>
    Require ip 173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18
    Require ip 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17
    Require ip 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22
    Require ip 2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32
</RequireAny>
```

Placed at the top of the docroot `.htaccess` (and copied by the deploy,
since the file is in the repository). Checks before switching it on: the
deploy webhook, Paystack and Let's Encrypt validations all arrive through
Cloudflare (the DNS is proxied), so they keep working; `ops.nfelia.com` and
the shops are proxied too. The cron does not use HTTP. If Cloudflare ever
publishes new ranges, pages fail with 403 until the list is updated: the
monthly review is the cost. On the VPS the same is done properly with the
firewall plus Authenticated Origin Pulls (a client certificate only
Cloudflare holds), which needs no list.

Also add in Cloudflare (free plan allows one rate-limiting rule and five
WAF custom rules): a rate limit on `/login` POSTs (say 30 per minute per
IP) and a WAF rule challenging requests to `/webhook/deploy.php` that do
not carry a `User-Agent` of `github-actions-nfelia/1.0`.

---

## 6. CSP for scripts (decision pending)

The baseline is enforced. A full policy (`script-src 'self' 'nonce-…'`)
needs every inline script in `views/` to carry a nonce, and Alpine's
`x-on`/`x-data` expressions need `'unsafe-eval'` or Alpine's CSP build.
Proposed path: send `Content-Security-Policy-Report-Only` with
`script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self'; report-uri /api/csp`
for two weeks, collect reports in Ops → Errors, remove `'unsafe-inline'`
by adding nonces to the few inline scripts, then enforce.

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

Still to verify after merge: the new headers in a live response; the
`vendor/` denial; the origin lock if chosen; HSTS if chosen.

---

## 8. Dependencies

`composer audit` (20 October 2026): no known advisories. dompdf 3.1.6,
PhpSpreadsheet 5.10.0, lbuchs/webauthn 2.2.0, php-qrcode 6.0.1, PHPMailer
7.0.2 (vendored in `lib/`), jQuery 3.7.1, Alpine 3.14.3. Dependabot alerts
on the repository cover Composer and npm once enabled.
