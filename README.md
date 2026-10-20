# Nfelia

Business operations platform for micro-businesses — quotations, invoices,
receipts, deliveries, inventory, expenses and subscriptions, with Paystack
billing and mobile-money receipt capture.

Live at **https://nfelia.com**

## Stack

- PHP 8.3, no framework: front controller + controllers / models / helpers
- MariaDB 10.11 (was MySQL 8.0 before the 2026 host migration)
- Composer for `dompdf`; **PHPMailer is hand-vendored in `lib/` and is
  tracked in git** — do not add it to Composer without removing `lib/`
- Tailwind-style markup in `views/`, served server-side
- cPanel shared hosting behind Cloudflare (proxied web, DNS-only mail)

## Layout

```
index.php          front controller — all routing starts here
config/            app, database, mail, env loader
app/
  controllers/     request handlers
  models/          data access
  helpers/         auth, tokens, mail, shared functions
  middleware/
views/             templates, including PDF templates and email bodies
api/
  ajax/            XHR endpoints
  webhooks/        Paystack webhook receiver
lib/PHPMailer/     hand-vendored, tracked
bin/               operational scripts (never deployed to the docroot)
db/
  schema.sql       structure-only snapshot of the production schema
  migrate.php      forward-only migration runner
  migrations/      YYYYMMDDhhmmss_description.sql
webhook/deploy.php authenticated deploy endpoint
docs/              internal notes; never served
```

## Local setup

```
cp .env.example .env     # then fill it in
composer install
mariadb -e "CREATE DATABASE smartqaq_nfelia_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb smartqaq_nfelia_core < db/schema.sql
php db/migrate.php       # applies the migrations newer than the schema snapshot
```

There is no local web server config in the repo; point any PHP 8.3 server at
the project root with `index.php` as the front controller.

## Configuration

Everything comes from `.env` — see `.env.example` for the full list with
notes. Two rules that have each caused an outage:

1. **Never pin a server-specific collation.** `utf8mb4_0900_ai_ci` is MySQL 8
   only. Pinning it in the PDO init command took the whole site down when the
   host moved us to MariaDB. CI now fails the build if it reappears.
2. **Never wrap `.env` values in quotes.** The loader strips one layer now,
   but an older copy did not, and a quoted password authenticated with the
   quote characters included.

## Deploying

Push to `main`. See [DEPLOY.md](DEPLOY.md).

## Operations documentation

`docs/ops/` is the operations set, written on 20 October 2026 from the
live server:

- [INFRASTRUCTURE.md](docs/ops/INFRASTRUCTURE.md): everything that exists today, with real values
- [RUNBOOK.md](docs/ops/RUNBOOK.md): how to deploy, back up, restore, rotate secrets, and what to check where
- [SECURITY.md](docs/ops/SECURITY.md): the audit, what was fixed, what is still open
- [PERFORMANCE.md](docs/ops/PERFORMANCE.md): what was slow, what changed, what is left
- [VPS-MIGRATION.md](docs/ops/VPS-MIGRATION.md): the move to a dedicated server, with the configs under `docs/ops/vps/`

## Operational checks

```
php bin/db_verify.php
```

Verifies PHP extensions, `.env` completeness, the database connection,
collation uniformity, cross-table joins, writable paths and `.env`
permissions. Run it after any migration or host change.
