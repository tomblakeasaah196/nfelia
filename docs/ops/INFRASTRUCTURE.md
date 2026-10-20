# Nfelia infrastructure: what exists today

The one place that says where everything is, with real values, so that
moving to a dedicated server is a checklist and not an investigation.
Taken from the server itself on 20 October 2026 (`bin/env-report.sh`,
section numbers below refer to its report), the repository, and the
dashboards. Secrets are never written here: they live in the docroot
`.env` and in the backup of it.

Lines marked **⟵ pending** wait on the second report run (database
section) or the Cloudflare export; everything else is confirmed.

Companion documents: [RUNBOOK.md](RUNBOOK.md) (how to do things),
[SECURITY.md](SECURITY.md), [PERFORMANCE.md](PERFORMANCE.md),
[VPS-MIGRATION.md](VPS-MIGRATION.md) (the move, step by step).

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
| `litespeed_finish_request` | present ⟵ pending confirmation (the probe's function line was cut in run 1) | PHP-FPM has `fastcgi_finish_request` |

Extensions loaded: bcmath, calendar, ctype, curl, dom, gd, iconv, imap,
json, mbstring, mysqli, mysqlnd, openssl, pcntl, pdo, pdo_mysql,
pdo_sqlite, phar, posix, simplexml, sockets, sqlite3, tokenizer, xml,
xmlreader, xmlwriter, xsl, zip, zlib.

**Missing and wanted on the VPS:** `opcache`, `fileinfo` (the app works
around it since 2026-10-20), `intl` (transliteration falls back to iconv),
`exif`, `sodium`; and `ffmpeg` if hero videos should be converted again.
GD's WebP support ⟵ pending (the shop photo variants depend on it; the
29 variants on disk suggest yes).

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
| `nfelia.com` | A / AAAA | 172.67.186.133, 104.21.19.149 / 2606:4700:… | yes |
| `www.nfelia.com` | A | (Cloudflare) | yes |
| `ops.nfelia.com` | A | (Cloudflare) | yes |
| `*.nfelia.com` | A | (Cloudflare) | yes (shops) |
| `cpanel.nfelia.com`, `webdisk.nfelia.com` | A | (Cloudflare) | yes (cPanel's own hostnames) |
| `mail.nfelia.com` | A | 37.59.83.88 | **no** (mail must reach the server) |
| `webmail.nfelia.com` | A | 37.59.83.88 | **no** |
| `nfelia.com` | MX | `0 mail.nfelia.com` | |
| `nfelia.com` | TXT | `v=spf1 ip4:37.59.83.88 +a +mx ~all` | |
| `_dmarc.nfelia.com` | TXT | `v=DMARC1; p=none; rua=mailto:dmarc@nfelia.com` | |
| `default._domainkey.nfelia.com` | TXT | DKIM, RSA 2048, valid | |
| `nfelia.com` | CAA | none | |
| `nfelia.lpc.cm` | A | 37.59.83.88 | the old address; `.htaccess` sends it to nfelia.com with a 301 |

The full record list with TTLs and any record not probed here (SRV,
verification TXTs, Google Search Console) ⟵ pending: Cloudflare export.

**On the VPS:** the proxied records change to the new IP in one edit each
(or one if a single A record is used with CNAMEs). `mail`, `webmail`, the
SPF `ip4:` and the PTR move only if mail moves (see §7).

---

## 5. TLS

- Browsers see Cloudflare's edge certificate for `nfelia.com` and
  `*.nfelia.com`. HSTS is **not** enabled at Cloudflare (no
  `strict-transport-security` header live).
- Cloudflare connects to the origin over HTTPS; the origin certificate for
  `nfelia.com` ⟵ pending (run 2 reads it from port 443; the handoff notes
  say a Cloudflare Origin CA certificate for `nfelia.com` and
  `*.nfelia.com` was installed in cPanel, with `*.nfelia.com` excluded from
  AutoSSL). The account's other domains use Let's Encrypt via AutoSSL.
- Encryption mode, minimum TLS version, TLS 1.3 ⟵ pending: Cloudflare export.

**On the VPS:** a Cloudflare Origin CA certificate (15 years, free) on
nginx, or certbot; and Authenticated Origin Pulls so only Cloudflare can
talk to the origin (SECURITY.md).

---

## 6. Cloudflare settings

| Setting | Value |
|---|---|
| Zone ID | `1a0e9a750a9786c0d9f236c168507e15` |
| Plan | ⟵ pending (assumed Free) |
| Cache Rule "Cache shop pages" | documented in `docs/handoff/Shop studio step 2.md`: expression `(http.host contains ".nfelia.com" and not http.host in {"www.nfelia.com"})`, eligible for cache, edge TTL from origin headers, browser TTL respect origin ⟵ pending confirmation it exists |
| Purge API token | **not set** in `.env` (`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID` absent): shop edits show within 5 minutes instead of at once |
| Live edge behaviour seen | app pages `cf-cache-status: DYNAMIC`; `app.css` `MISS` then cached, origin `cache-control: public, max-age=604800`; Ops `BYPASS`; HTTP/3 advertised; NEL reporting on |
| WAF, rate limiting, Bot Fight Mode, Security level, Always Use HTTPS, Brotli, Early Hints, 0-RTT | ⟵ pending: Cloudflare export |

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
| Engine | MariaDB (README: 10.11) ⟵ pending exact version, variables, grants (run 2) |
| Name / user | `smartqaq_nfelia_core` / `smartqaq_nfelia_admin` |
| Collation | `utf8mb4_unicode_ci` everywhere (never pin `utf8mb4_0900_ai_ci`: MySQL-only, took the site down once) |
| Session time zone | `+01:00` set per connection; PHP runs `Africa/Lagos` |
| Schema | `db/schema.sql` (baseline) + 29 migrations in `db/migrations/`, tracked in `schema_migrations`; 92 tables after all migrations; stored procedure `generate_doc_number` (loaded by the tests; production ⟵ pending) |
| Size | 52 MB on disk (cPanel figure) |
| Hosts the user may connect from | ⟵ pending (run 2 prints them; `localhost` must be among them before `DB_HOST` is changed) |

A structure-only live dump is produced by `bin/env-report.sh` into the
home folder; compare it with a fresh local copy (schema + migrations) with
the small Python comparer in RUNBOOK.md, "Checking the schema".

---

## 11. GitHub

| Item | Value |
|---|---|
| Repository | `tomblakeasaah196/nfelia`, **public**, one collaborator (owner, admin) |
| Default branch | `main`, **no protection rules** |
| Actions | `.github/workflows/deploy.yml`: quick checks on push and pull request; the end-to-end job on pull requests and on demand (this branch); deploy on push to `main` through the `production` environment |
| Secrets / variables | secret `DEPLOY_WEBHOOK_SECRET`; variable `DEPLOY_WEBHOOK_URL=https://nfelia.com/webhook/deploy.php` |
| Minutes | unlimited while public; 2,000 a month once private (free plan) |

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
- Nothing external watches the site. VPS-MIGRATION.md adds an uptime check.
