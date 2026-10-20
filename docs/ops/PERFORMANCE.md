# Nfelia performance: findings, what changed, what to turn on

Measured on 20 October 2026 from the server itself and from the code.
Scale today: 2 businesses; planning for 30, built to be comfortable at a
few hundred. Companion: [SECURITY.md](SECURITY.md),
[INFRASTRUCTURE.md](INFRASTRUCTURE.md), [VPS-MIGRATION.md](VPS-MIGRATION.md).

## 1. Where the time goes today

| Measurement | Value | Source |
|---|---|---|
| Time to first byte, sign-in page, from the server through Cloudflare | 152–162 ms | report §6 |
| PHP compiles the app on every request | yes: **OPcache is off** | report §5 |
| Files loaded per request before routing | about 30 helper files, ~0.6 MB of PHP | `index.php` |
| Database queries before the controller runs | 4–5 (membership, subscription, pending payments, passkey count; lock update every 5 min) | middleware + layout |
| Slow pages recorded | `POST /posts/create` 4.6–5.3 s (a 20 MB phone photo re-encoded by GD); `GET /api/notifications` 3.0 s (the bell poll waiting on the session lock held by that save) | `storage/logs/php_errors.log` |
| Public document PDF | ~150 ms to render here, 20 ms once kept | this branch |
| App page weight | `app.css` 140 KB + jQuery 87 KB + Alpine 45 KB + scripts; fonts 2 × woff2 | assets |
| Public/sign-in pages before this branch | the Tailwind CDN compiler, several hundred KB of script, plus Google Fonts | views |
| Static assets at the origin | `cache-control: public, max-age=604800` (LiteSpeed default), versioned URLs | report §6 |
| Served from Cloudflare's cache, 14–20 October | 3.0 % of 25,503 requests (app pages are `DYNAMIC` by design and are most of the traffic; shop pages are cached five minutes at the edge, assets a week or more) | Cloudflare export |
| Database | 52 MB; 92 tables; shared MariaDB | report |

## 2. Done in this branch

| # | Change | Effect |
|---|---|---|
| P-01 | Public and sign-in pages use a 32 KB compiled stylesheet instead of the Tailwind CDN compiler; Nfelia's own fonts are self-hosted; the quote page's Alpine is local | hundreds of KB less and no third-party round trips on the slowest pages customers open |
| P-02 | PDFs rendered once per document version and kept (`storage/pdf/cache`), remote fetching off | repeat downloads go from a render to a file send; no network wait inside a render |
| P-03 | Read-only AJAX calls release the PHP session before running; the three PDF routes release it before rendering | the bell poll and pickers no longer queue behind a slow page in another tab (the 3 s `/api/notifications` entries) |
| P-04 | `assets/` and `icons/` cached a year (immutable, URLs are versioned), `uploads/` a month, service workers `no-cache`, fonts with CORS for shop pages | repeat visits and Cloudflare hits for everything static |
| P-05 | Composite indexes for the lists and reports (payments by date, invoices by status and date and by due date, receipts, expenses, customers, deliveries), two duplicate indexes dropped | matters from ~50 businesses; free now |
| P-06 | jQuery and `app.js` load deferred | no parser stall at the foot of every app page |
| P-07 | Delivery pages load the business's real fonts (they asked Google for a pairing key before) | correct rendering, one fewer failed request |
| P-08 | Expired remember-me rows and old kept PDFs swept nightly | tables and disk stop growing without bound |

## 3. Turn on today (no code)

1. **OPcache** (RUNBOOK §4.1). The single biggest gain available: every
   request currently parses and compiles the whole app. Expect the PHP part
   of a page to drop by half or more. Settings: 192 MB, 20,000 files,
   revalidate every 60 s (the deploy copies files in place, so timestamps
   must be checked).
2. **`DB_HOST=localhost`** (RUNBOOK §4.2): the local socket instead of a
   TCP loop through the public address.
3. **Cloudflare purge token** (RUNBOOK §5): shop edits visible at once.
4. Cloudflare → Speed → Optimization: **Early Hints on** (Brotli and
   HTTP/3 are already on, per the export of 20 October; CLOUDFLARE.md
   prompt B does it).

## 4. Known costs left as they are, and why

- **jQuery (87 KB) on every app page** for eight call sites in `app.js`,
  `lock.js` and the invoice/quote builders' inline scripts. Removing it is
  a careful rewrite of the two most important forms; worth doing, not in a
  hardening branch. Deferred loading takes the sting out meanwhile.
- **Per-request bookkeeping queries** (4–5). At 30 businesses this is well
  under 5 ms a page; folding them into one query buys little and risks
  stale subscription state. Revisit past 100 businesses.
- **A phone photo re-encoded by GD takes seconds** on shared CPU
  (`posts/create`, product photos). It happens once per upload and no longer
  blocks the uploader's other requests. On the VPS, `imagick` or a resize
  in the browser before upload would help.
- **Google Fonts on document pages** stay: they carry the business's own
  font pairing, a product feature.
- **The landing page starts a session** for anonymous visitors (feedback
  form token), so it is `DYNAMIC` at Cloudflare. Fine at this traffic.
- **`html2pdf.js` from cdnjs** on receipt and delivery pages (client-side
  "save as PDF"): to be vendored (SECURITY.md 2.4).
- **The shop Cache Rule works as designed.** Its edge TTL is "bypass by
  default", which means Cloudflare follows the origin's headers, and the
  storefront sends `Cache-Control: public, max-age=0, s-maxage=300` (an
  hour for the catalogue feed), so shop pages are kept at the edge for
  five minutes and refreshed from there. The low weekly ratio is the app
  itself: signed-in, per-business pages are `DYNAMIC` by design and are the
  bulk of the traffic. Nothing to change.

## 5. On the VPS

The configuration files in `docs/ops/vps/` carry these settings; the
reasoning:

| Area | Setting | Why |
|---|---|---|
| OPcache | `opcache.enable=1`, 256 MB, 20,000 files, `validate_timestamps=0` (each deploy is a new release directory and reloads FPM) | zero stat calls per request |
| PHP-FPM | `pm=dynamic`, `max_children=12` on 4 GB (about 100 MB a worker; the Records Book runs from the cron, not FPM), `max_requests=500` | no swap, no leaks |
| realpath cache | 4 MB, 600 s | many small includes |
| Sessions | files on local disk to start; Redis (`php-redis`, `session.save_handler=redis`) when a second web node or faster locking is wanted | the app's own lock semantics keep working |
| nginx | `gzip` on, static files served directly with the same cache lifetimes, `try_files` to `index.php?url=` | LiteSpeed's job |
| MariaDB 10.11 | `innodb_buffer_pool_size` = 25 % of RAM (1 GB on 4 GB), `innodb_log_file_size` 256 MB, `innodb_flush_log_at_trx_commit=1` (money), slow log at 1 s, `max_connections=100` | the whole data set stays in memory |
| Cron | a systemd timer every 5 minutes; the backup timer nightly | same behaviour, visible in `journalctl` |
| Cloudflare | Brotli, Early Hints, HTTP/3; Tiered Cache if on a paid plan | edge |
| Uptime | an external check every minute on `/login` | know before customers do |

## 6. How to measure after a change

- Time to first byte: `curl -s -o /dev/null -w 'ttfb=%{time_starttransfer}s\n' https://nfelia.com/login` three times.
- OPcache: section 5 of `bin/env-report.sh` (`opcache: enabled=true … hit_rate`).
- Slow pages: Ops → Errors, kind "slow" (anything over 3 s is recorded with its route).
- Queries on a page: on a local copy, `SET GLOBAL general_log=1` for a minute, open the page, count the lines.
