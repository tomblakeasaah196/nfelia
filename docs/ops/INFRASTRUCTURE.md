# Nfelia infrastructure: what exists today

The one place that says where everything is, with real values, so that
moving to a dedicated server is a checklist and not an investigation.
Taken from the server itself on 20 October 2026 (`bin/env-report.sh`,
section numbers below refer to its report), the repository, and the
dashboards. Secrets are never written here: they live in the docroot
`.env` and in the backup of it.

Lines marked **⟵ pending** wait on the database section of the report
(run 3, with the script changed on 20 October to read the schema through
the panel's API). The Cloudflare export of 20 October is folded in;
everything else is confirmed.

Companion documents: [RUNBOOK.md](RUNBOOK.md) (how to do things),
[SECURITY.md](SECURITY.md), [PERFORMANCE.md](PERFORMANCE.md),
[CLOUDFLARE.md](CLOUDFLARE.md) (the zone as exported, and the prompts for
its assistant), [VPS-MIGRATION.md](VPS-MIGRATION.md) (the move, step by step).

---

## 1. The shape of it

```
                 ┌──────────────── Cloudflare (proxy, DNS, cache, TLS edge) ────────────────┐
  browsers ──────┤  nfelia.com  www  ops.nfelia.com  *.nfelia.com (shops)  cpanel  webdisk  │
                 └──────────────────────────────┬───────────────────────────────────────────┘
                                                │ https (origin certificate)
                     ┌──────────────────────────▼──────────────────────────┐
                     │  cPanel shared server  srv-web-ns9.newtoncorp.fr      │
                     │  37.59.83.88  (OVH, run by Newton Corp)              │
                     │  CloudLinux + CageFS, LiteSpeed, ea-php83 8.3.35      │
                     │  account: smartqaq   home: /home/smartqaq             │
                     │    public_html/nfelia.com   ← the app (docroot)       │
                     │    repositories/nfelia      ← git checkout (deploys)  │
                     │    MariaDB (local)          ← smartqaq_nfelia_core    │
                     │    Exim (local)             ← mail.nfelia.com         │
                     └─────────────────────────────────────────────────────┘
  mail clients ───── mail.nfelia.com / webmail.nfelia.com  (DNS-only, straight to 37.59.83.88)
  GitHub Actions ─── POST https://nfelia.com/webhook/deploy.php  → bin/deploy.sh
  Paystack ───────── POST https://nfelia.com/api/webhooks
```

**Three facts that shape every decision**

1. Nfelia is one of **eight sites on the same cPanel account and Unix
   user** (section 2 of the report): `smartls.cm` (the account's main
   domain), `jbspraxis.com`, `lpc.cm`, `bureau.lpc.cm`, `hodlc.lpc.cm`,
   `nfelia.lpc.cm` (the old address), `praxisls.com`, `staging.smartls.cm`.
   Any code running as `smartqaq` on any of them can read Nfelia's `.env`
   and database. WordPress tooling is present in the home folder.
2. The server's address, `37.59.83.88`, is public knowledge (it is in the
   SPF record and the MX host), and the origin answers HTTP and HTTPS
   directly, so Cloudflare's protections can be walked around until the
   origin is restricted (see SECURITY.md).
3. **There are no backups.** cPanel lists none for the account, and nothing
   else runs. `bin/backup.sh` (this branch) and its crontab line fix that.

---

## 2. Hosting account (cPanel)

| Item | Value | On the VPS |
|---|---|---|
| Host | `srv-web-ns9.newtoncorp.fr`, OVH network, CloudLinux 9 (kernel 5.14), 8 vCPU, 29 GB RAM, 500 GB disk (54 % used), all shared | one 2–4 vCPU, 4–8 GB VPS of Nfelia's own |
| Panel user / home | `smartqaq` / `/home/smartqaq` | a `nfelia` system user, no panel |
| Web server | LiteSpeed (LSAPI), serves `.htaccess` | nginx + PHP-FPM; `.htaccess` translated (VPS-MIGRATION.md) |
| Docroot | `/home/smartqaq/public_html/nfelia.com` (addon domain `nfelia.com`; `servername` `nfelia.com.smartls.cm`) | `/var/www/nfelia/current` (a symlink to a release) |
| Wildcard | `*.nfelia.com` is a cPanel subdomain on the **same docroot**; shops and `ops.nfelia.com` ride on it | one nginx server block with `server_name nfelia.com *.nfelia.com` |
| Git checkout | `/home/smartqaq/repositories/nfelia`, remote `https://github.com/tomblakeasaah196/nfelia.git`, credential helper `store` (`~/.git-credentials`, mode 600) ⟵ pending: whether that credential can read a private repo | releases cloned by the deploy user over SSH |
| Composer | `/home/smartqaq/composer.phar` 2.10.3, run as `ea-php83 -d allow_url_fopen=On` | `composer` system-wide |
| Node | v22.22.0 under `~/.nvm` (the user installed it; the server never runs npm) | not needed on the server |
| Missing tools | `rsync`, `ffmpeg`, `jq`, `python3` | all available |
| Cron | `*/5 * * * * /usr/local/bin/ea-php83 /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1` (the only line) | a systemd timer (VPS-MIGRATION.md) |
| Account limits in use | 3 of 5 addon domains, 2 of 5 aliases, 25 of 50 mailboxes, 52 MB of database disk | none |
| Backups | **none** (`uapi Backup list_backups` → empty) | nightly `bin/backup.sh` + off-site copy |
| Files lingering in the docroot | `.env.bak-2026-10-17`, `.env.bak-mail-2026-10-17`, `.env.bak-mailpw-…`, `.env.bak-vapid-…`, `.env.bak-webhook-…`, `.htaccess.bak-2026-10-17_0912`, `composer.phar` (3.4 MB), `error_log` (July), `cgi-bin/` | start clean; keep `.env` backups under `~/backups` only |

### 2.1 cPanel-written PHP settings (`.user.ini` and `php.ini` in the docroot)

Both files carry the same cPanel MultiPHP block: `display_errors Off`,
`max_execution_time 120`, `max_input_time 60`, `max_input_vars 1000`,
`memory_limit 512M`, `post_max_size 64M`, `upload_max_filesize 32M`,
`session.gc_maxlifetime 7200`, `session.save_path "/var/cpanel/php/sessions/ea-php82"`
(stale: the site runs ea-php83; the app sets its own path anyway),
`error_log "/home/smartqaq/logs/php.error.log"`, `log_errors On`,
`zlib.output_compression Off`. The same values are repeated in the
repository's `.htaccess` under `<IfModule lsapi_module>`, which is what
LiteSpeed actually applies (the probe confirms 512M / 120 s / 64M / 32M).

---

## 3. PHP

### 3.1 As the web server runs it (LiteSpeed, section 5)

| Setting | Live value | Note |
|---|---|---|
| Version | 8.3.35 (`sapi=litespeed`) | |
| `memory_limit` | 512M | the Records Book asks for 1 GB itself |
| `max_execution_time` / `max_input_time` | 120 / 60 | |
| `post_max_size` / `upload_max_filesize` | 64M / 32M | the app caps pictures at 20 MB |
| `error_log` | `storage/logs/php_errors.log` (from `.htaccess`) | closed to the web |
| `display_errors` | 0 | |
| `allow_url_fopen` | off | the app uses curl |
| `disable_functions` | none | `popen` is what the deploy webhook needs |
| **OPcache** | **off** (no `opcache` ini loaded, `opcache.enable` empty) | every request compiles the app from source; see PERFORMANCE.md, first item |
| `realpath_cache_size` / ttl | 4096K / 120 | fine |
| `session.save_path` (ini) | `/var/cpanel/php/sessions/ea-php83` | the app overrides to `storage/sessions` (config/app.php) |
| `session.gc_probability` (ini) | 0 | the app sets 1/100 with a 12-hour lifetime |
| `date.timezone` | UTC | the app sets `Africa/Lagos` |
| Behind Cloudflare | `REMOTE_ADDR` is the edge, `CF-Connecting-IP` present | the app uses `client_ip()` |
| `litespeed_finish_request` | present (run 2); `exec`, `popen`, `proc_open`, `mail`, `curl` all allowed | PHP-FPM has `fastcgi_finish_request` |

Extensions loaded: bcmath, calendar, ctype, curl, dom, gd, iconv, imap,
json, mbstring, mysqli, mysqlnd, openssl, pcntl, pdo, pdo_mysql,
pdo_sqlite, phar, posix, simplexml, sockets, sqlite3, tokenizer, xml,
xmlreader, xmlwriter, xsl, zip, zlib.

**Missing and wanted on the VPS:** `opcache`, `fileinfo` (the app works
around it since 2026-10-20), `intl` (transliteration falls back to iconv),
`exif`, `sodium`; and `ffmpeg` if hero videos should be converted again.
GD has WebP and AVIF support (run 2), which the shop photo variants use.

### 3.2 On the command line (`/usr/local/bin/ea-php83`, used by the cron and the deploy)

`memory_limit 128M`, `max_execution_time 0`, `allow_url_fopen Off`
(Composer is given `-d allow_url_fopen=On`), `error_log error_log` (a bare
relative name: the cron sets its own log path at start),
`session.save_path /var/cpanel/php/sessions/ea-php83`. The default
`/usr/local/bin/php` is a 933-byte selector script, not PHP 8.3: always
call `ea-php83` by name.

---

## 4. DNS (zone at Cloudflare, nameservers `melissa.ns.cloudflare.com`, `aragorn.ns.cloudflare.com`)

| Name | Type | Value | Proxied |
|---|---|---|---|
| `nfelia.com` | A | 37.59.83.88 in the zone; resolvers see the edge (172.67.186.133, 104.21.19.149, two AAAA) | yes |
| `www.nfelia.com` | A | 37.59.83.88 in the zone | yes |
| `*.nfelia.com` | CNAME | `nfelia.com` (so `ops`, every shop, and cPanel's `cpanel`/`webdisk` names all go through the proxy) | yes |
| `mail.nfelia.com` | A | 37.59.83.88 | **no** (mail must reach the server) |
| `webmail.nfelia.com` | A | 37.59.83.88 | **no** |
| `nfelia.com` | MX | `0 mail.nfelia.com` | |
| `nfelia.com` | TXT | `v=spf1 ip4:37.59.83.88 +a +mx ~all` | |
| `_dmarc.nfelia.com` | TXT | `v=DMARC1; p=none; rua=mailto:dmarc@nfelia.com` | |
| `default._domainkey.nfelia.com` | TXT | DKIM, RSA 2048, valid | |
| `nfelia.com` | CAA | none | |
| `nfelia.lpc.cm` | A | 37.59.83.88 | the old address; `.htaccess` sends it to nfelia.com with a 301 |

The Cloudflare export of 20 October confirms exactly these nine records
(the two A records, the wildcard CNAME, `mail`, `webmail`, MX, SPF, DKIM,
DMARC): no SRV or verification records, every TTL Auto, CNAME flattening
at the root only, and **DNSSEC off** (SECURITY.md L-12).

**On the VPS:** the proxied records change to the new IP in one edit each
(or one if a single A record is used with CNAMEs). `mail`, `webmail`, the
SPF `ip4:` and the PTR move only if mail moves (see §7).

---

## 5. TLS

- Browsers see Cloudflare's Universal SSL certificate for `nfelia.com` and
  `*.nfelia.com` (Google Trust Services, ECDSA, renews itself; the current
  one runs to 2027-01-04). HSTS **off**; Always Use HTTPS **on**; Automatic
  HTTPS Rewrites on; **minimum TLS 1.0** (SECURITY.md L-11: raise to 1.2);
  TLS 1.3 on; 0-RTT off; Opportunistic Encryption and ECH on.
- Encryption mode **Full (strict)**: Cloudflare verifies the origin's
  certificate. For `nfelia.com` the origin presents cPanel's AutoSSL
  Let's Encrypt certificate (CN `www.nfelia.com`, with `nfelia.com`,
  `mail`, `webmail`, `cpanel`, `webdisk`, `autodiscover`… as names; valid
  2026-10-18 to 2027-01-16, renewed by AutoSSL); the `*.nfelia.com` vhost
  carries the Cloudflare Origin CA certificate installed by hand (valid to
  2041). Both satisfy Full (strict).
- `mail.nfelia.com` and `webmail.nfelia.com` are not proxied: mail clients
  see the Let's Encrypt certificate directly.

**On the VPS:** a Cloudflare Origin CA certificate (15 years, free) on
nginx, or certbot; and Authenticated Origin Pulls so only Cloudflare can
talk to the origin (SECURITY.md).

---

## 6. Cloudflare settings

| Setting | Value |
|---|---|
| Zone ID | `1a0e9a750a9786c0d9f236c168507e15` |
| Plan | **Free** (zone created and activated 2026-06-10; one account, the owner's) |
| Cache Rule "Cache shop pages" | confirmed: expression `(http.host contains ".nfelia.com" and not http.host in {"www.nfelia.com"})`, eligible for cache, browser TTL respect origin, edge TTL "bypass by default" (Cloudflare follows the origin's headers: the storefront sends `s-maxage=300`, so shop pages stay five minutes at the edge), last changed 2026-10-18 |
| Purge API token | **not set** in `.env` (`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID` absent): shop edits show within 5 minutes instead of at once |
| Live edge behaviour seen | app pages `cf-cache-status: DYNAMIC`; `app.css` `MISS` then `HIT`, origin `cache-control: public, max-age=604800`; Ops `BYPASS`; HTTP/3 advertised; NEL reporting on; 3.0 % of the week's requests served from cache |
| WAF custom rules, rate limiting, Page Rules | **none** (quota on Free: 5 custom rules, 1 rate-limiting rule, 3 Page Rules); managed and always on: Cloudflare Managed Free Ruleset, Normalization, DDoS L7 |
| Security level, Browser Integrity Check, challenge TTL | medium, on, 30 min (defaults) |
| Bot Fight Mode, JS detection, AI-bot blocking | all **off** |
| IP access rules, user-agent rules, zone lockdowns | none |
| Speed | Brotli on, HTTP/2 and HTTP/3 on, **Early Hints off**, Rocket Loader off, minify off, 0-RTT off; Polish, Mirage, WebP are paid |
| Caching | level aggressive, browser TTL 4 h, Always Online off, Tiered Cache off, Development Mode off |
| Network | IPv6 on, WebSockets on, max upload 100 MB, Pseudo IPv4 off |
| Email Routing | not enabled (one disabled catch-all → drop rule remains) |
| Workers, Pages, Tunnels, Access, Load Balancers | none |
| Account members | the owner's Google account (Super Administrator) and a second member with Domain Administrator and zone-scoped roles on another zone; **2FA off on both**, account-level "enforce 2FA" off (SECURITY.md L-10) |
| Notifications | none readable, most likely none configured |
| Traffic, 14–20 October | 25,503 requests, 3,403 page views, 1,226 unique visitors, 264 requests blocked by the managed rules, 774 served from cache, 161 MB |

---

## 7. Mail

| Item | Value |
|---|---|
| Server | the same box: cPanel Exim/Dovecot at `mail.nfelia.com` (37.59.83.88) |
| App sends through | `MAIL_HOST=localhost`, port 465, SSL, user `no-reply@nfelia.com`, from `no-reply@nfelia.com` "Nfelia" |
| Mailboxes on nfelia.com | `no-reply@`, `support@`, `dmarc@` (22 more on the other domains) |
| SPF | valid: `v=spf1 ip4:37.59.83.88 +a +mx ~all` |
| DKIM | valid, selector `default` |
| DMARC | `p=none` (monitor only), reports to `dmarc@nfelia.com` → raise to `quarantine` after two clean weeks |
| Reverse DNS | `ip88.ip-37-59-83.eu`, valid (generic OVH name) |
| Campaign limit | `CAMPAIGN_PER_HOUR` default 200; keep under the account's hourly send cap (ask the host) |

**On the VPS, decide:** keep mail on cPanel (then only the app's SMTP
settings change, to the cPanel server's name and a mailbox password) or
move to a mail service (Postmark, Resend, Brevo…) and let the VPS run no
mail server at all. Running Exim yourself on a fresh VPS costs reputation
work (new IP, PTR, warm-up). VPS-MIGRATION.md recommends the service.

---

## 8. The application's settings (`.env` in the docroot, mode 600)

| Key | Present live | Meaning / note |
|---|---|---|
| `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://nfelia.com` | yes | |
| `APP_KEY` | yes (65 chars; 64 expected: one stray character, harmless; signs push device tokens) | |
| `DB_HOST` | `37.59.83.88` ← **should be `localhost`** (README rule; the shell cannot even reach the IP from inside CageFS) | |
| `DB_NAME`, `DB_USER` | `smartqaq_nfelia_core`, `smartqaq_nfelia_admin` | |
| `DB_PASS` | yes | |
| `MAIL_HOST/PORT/ENCRYPTION/USERNAME/PASSWORD/FROM_*` | yes | §7 |
| `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY` | live keys | |
| `PAYSTACK_WEBHOOK_SECRET` | set but **unused** by the code (webhooks are verified with the secret key); removed from the template | |
| `PAYSTACK_PLAN_CODE`, `PAYSTACK_SEAT_PLAN_CODE` | `PLN_j8slp26f5yikdhi`, `PLN_4nb1dm2gn0y2cyv` (old card plans only) | |
| `MAX_UPLOAD_SIZE_MB` | 10 | |
| `MOMO_RECEIPT_DIR` | `/home/smartqaq/momo_receipts` (exists, empty; receipts are in `storage/momo_receipts`, 216 KB) | |
| `DEPLOY_WEBHOOK_SECRET` | yes (64 chars) | same value in the GitHub Actions secret |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT=mailto:support@nfelia.com` | yes | **never rotate**: phones are tied to the pair |
| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID` | **absent** | optional instant purge |
| `RECORDS_MAX_CELLS` | absent (default 600000) | |
| `PASSWORD_BREACH_CHECK` | absent (default on) | new, this branch |
| `BACKUP_DIR` | absent (default `~/backups/nfelia`) | new, this branch |
| stray lines | the file has a few lines that are just `=` | tidy when next edited |

Other secrets outside `.env`: `storage/private/seal.key` (64 bytes, made
2026-10-19; document signatures verify against it: **back it up and move
it with the data**), `~/.git-credentials`, the GitHub Actions secret, the
Paystack dashboard, the Cloudflare account.

---

## 9. Files and storage (72 MB in all)

| Path | Size / count | What | Backed up by |
|---|---|---|---|
| `uploads/heroes`, `products`, `storefront`, `logos`, `avatars` | 17M, 7.3M, 5.7M, 3.9M, 1.2M (84 files) | pictures people uploaded; names carry a time stamp, never overwritten | backup.sh |
| `uploads/cache` | 864K (29 files) | WebP copies of shop photos, remade on demand | not needed |
| `uploads/signatures`, `order_proofs`, `delivery_signoffs`, `expense_receipts` | empty | old locations | |
| `storage/private` | 684K (7 files) + `seal.key` | signatures, payment proofs, refund proofs | backup.sh |
| `storage/momo_receipts` | 216K | Mobile Money receipts | backup.sh |
| `storage/sessions` | 1.1M (265 files) | PHP sessions, 12-hour life | no |
| `storage/logs/php_errors.log` | 195 KB, 1,299 lines | PHP log, read by Ops | no |
| `storage/pdf` | new | kept PDFs, swept after 30 days | no |
| `storage/release` | | the deployed commit | no |
| `~/deploy-nfelia.log` | 60 KB | every deploy | no |

---

## 10. Database

| Item | Value |
|---|---|
| Engine | **MariaDB 10.11.19** (CloudLinux build) on the same box, shared by every account. Server defaults `latin1` / `latin1_swedish_ci` (the database itself is utf8mb4), `time_zone SYSTEM` = CEST, `sql_mode STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`, InnoDB buffer pool **128 MB**, `max_connections 151`, `tmp_table_size` 16 MB, slow log off (`long_query_time` 10 s), performance schema off, event scheduler off (run 3) |
| Name / users | `smartqaq_nfelia_core`; **two** users hold ALL PRIVILEGES on it: `smartqaq_nfelia_admin` (the app) and `smartqaq_nfelia_it` (named in the old deployment guide; nothing in the code uses it; kept by the owner's decision of 20 October: SECURITY.md L-15) |
| Server-wide counters, 5 days | 375 k connections, 31 M statements, 82 connections at the busiest moment of 151, 39 % of temporary tables written to disk, 7,366 aborted connects: every account on the box together, not Nfelia's alone |
| Collation | `utf8mb4_unicode_ci` everywhere (never pin `utf8mb4_0900_ai_ci`: MySQL-only, took the site down once) |
| Session time zone | `+01:00` set per connection; PHP runs `Africa/Lagos` |
| Schema | `db/schema.sql` (baseline) + the migrations in `db/migrations/`, tracked in `schema_migrations` (29 rows live, the baseline included; the last, `20261026090000_variant_prices`, applied 20 October: production is level with `main`; two more wait in this branch, sign-in hardening and the list indexes). **92 tables and 4 views** (`v_dashboard_summary`, `v_low_stock_products`, `v_monthly_pl`, `v_overdue_invoices`), the stored procedure `generate_doc_number` (created 2026-07-27), no triggers, no events |
| Size | 52 MB on disk (cPanel figure); the tables themselves about 6 MB. 5 businesses (3 founder and test accounts, 2 shops: `studio12oclock`, `rnafriluxe`), 6 active users, 10 invoices, 14 customers, 14 products, subscriptions 3 active and 2 trial, 123 error events, 8 remember-me rows all expired (the nightly sweep in this branch removes them) |
| Host the user may connect from | **`srv-web-ns9.newtoncorp.fr` only** (`SHOW GRANTS`, run 3): the grant is bound to the server's own hostname, which is what a TCP connection to `37.59.83.88` from the server itself resolves to. There is no `localhost` entry, which is why runs 1 and 2 were refused over the socket and why `.env` carries the IP. The connection never leaves the machine. Leave it (SECURITY.md L-04, RUNBOOK §4.2); on the VPS the user is created for `localhost`. The two `cpmove_failed_mysql_dbs.*` folders in the home directory (June 2026) say the databases failed to restore when the account moved to this server and were rebuilt by hand, which explains the shape. |

A structure-only live dump is produced by `bin/env-report.sh` into the
home folder: asked of the panel's own API first (`uapi Mysql
dump_database_schema`; its answer in run 3 held no tables, so the script
checks and lets mysqldump over TCP take over). Compare it with a fresh
local copy (schema + migrations) as RUNBOOK.md "Checking the schema"
shows. The copy uploaded on 20 October was the 17 October file (52
tables, before the storefront, bookings, Ops and Records Book
migrations), so the live comparison is still ⟵ pending the next copy.

---

## 11. GitHub

| Item | Value |
|---|---|
| Repository | `tomblakeasaah196/nfelia`, **public**, one collaborator (owner, admin) |
| Default branch | `main`, **no protection rules** |
| Actions | `.github/workflows/deploy.yml`: quick checks on push and pull request; the end-to-end job on pull requests and on demand (this branch); deploy on push to `main` through the `production` environment |
| Secrets / variables | secret `DEPLOY_WEBHOOK_SECRET`; variable `DEPLOY_WEBHOOK_URL=https://nfelia.com/webhook/deploy.php` |
| Minutes | unlimited while public (confirmed 20 October through the API: 94 runs, the last two that morning green, one on the pull request and the deploy on `main`); 2,000 a month only once private (free plan) |

---

## 12. Paystack

Live keys in `.env`; webhook URL to keep in the dashboard:
`https://nfelia.com/api/webhooks`; callback URL `https://nfelia.com/billing/callback`.
Webhooks are verified with HMAC-SHA512 over the raw body using the secret
key. Two legacy plan codes remain for subscriptions made before monthly
payments.

---

## 13. Nfelia Ops

`ops.nfelia.com` is served by the wildcard on the same docroot (confirmed:
it answers and redirects to `/signin`). Its people are in `ops_staff`; its
cookie is `__Host-nfelia_ops`; phone alerts use the same VAPID pair.

---

## 14. Monitoring and logs

- Ops → Errors and Ops → Health (`app/ops/monitor.php`): PHP errors,
  browser errors, slow pages (over 3 s), the cron heartbeat, webhooks,
  outbox, push keys, mail, disk, PHP, database, release, and (this branch)
  backups.
- Logs: `storage/logs/php_errors.log` (recent: slow `POST /posts/create`
  at ~5 s and `GET /api/notifications` at 3 s, the latter now addressed),
  `~/deploy-nfelia.log`, cPanel's `~/error_log` (54 MB, all accounts'
  sites), `~/logs/`.
- Nothing external watches the site, and Cloudflare has no notification
  policies (none readable in the export). VPS-MIGRATION.md adds an uptime
  check; Cloudflare → Notifications can at least mail about certificate
  expiry, DDoS events and origin errors today (CLOUDFLARE.md C-10).
