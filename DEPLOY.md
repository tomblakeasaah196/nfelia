# Nfelia — deployment

Push to `main` → GitHub Actions lints and sweeps for secrets → it POSTs an
authenticated request to `webhook/deploy.php` on the server → that runs
`bin/deploy.sh`, which resets the checkout to `origin/main`, copies the tree
into the docroot, installs Composer dependencies and applies pending
migrations.

```
GitHub push  →  Actions (checks)  →  POST /webhook/deploy.php
                                       └→ bin/deploy.sh
                                            1. git reset --hard origin/main
                                            2. tar → docroot (.deployignore)
                                            3. composer install --no-dev
                                            4. php db/migrate.php
```

## Layout on the server

| Path | What it is |
|---|---|
| `/home/smartqaq/repositories/nfelia` | the git checkout; never served |
| `/home/smartqaq/public_html/nfelia.com` | the live docroot |
| `/home/smartqaq/deploy-nfelia.log` | full log of every deploy |
| `/home/smartqaq/.deploy-nfelia.lock` | flock; one deploy at a time |

The docroot copy of `.env` is **authoritative**. It is git-ignored and
deploy-ignored, so no deploy ever overwrites it and the secrets live only on
the server.

## One-time setup

### 1. Clone the repo on the server

```
mkdir -p ~/repositories
cd ~/repositories
git clone https://github.com/tomblakeasaah196/nfelia.git nfelia
cd nfelia && git rev-parse HEAD
```

For a private repo, use a deploy key or a personal access token.

### 2. Generate the shared secret

```
php -r "echo bin2hex(random_bytes(32)), \"\n\";"
```

Put the same value in two places:

- the docroot `.env`, as `DEPLOY_WEBHOOK_SECRET=<value>` (no quotes)
- the GitHub repo, **Settings → Secrets and variables → Actions → Secrets**,
  named `DEPLOY_WEBHOOK_SECRET`

### 3. Add the webhook URL as a repo variable

**Settings → Secrets and variables → Actions → Variables**, named
`DEPLOY_WEBHOOK_URL`:

```
https://nfelia.com/webhook/deploy.php
```

It is a *variable*, not a secret — it appears in logs, and that is fine. The
token never does.

### 4. Create the `production` environment

**Settings → Environments → New environment → `production`.** The deploy job
targets it, so you can later add a required reviewer if you want deploys to
pause for approval.

### 5. First deploy

```
bash ~/repositories/nfelia/bin/deploy.sh
```

Run it by hand once. If it succeeds, push a trivial commit to `main` and
confirm Actions reaches `[webhook] DEPLOY OK`.

## Day-to-day

Commit, push to `main`, watch Actions. To see what happened on the server:

```
tail -80 ~/deploy-nfelia.log
```

### Migrations

Add a file under `db/migrations/` named `YYYYMMDDhhmmss_description.sql`.
It runs once, in filename order, and is recorded in `schema_migrations`.

```
php db/migrate.php --status    # what is applied and what is pending
php db/migrate.php --dry-run   # print without executing
```

On the first run against the existing production database the runner records
`0000_baseline`, so the schema that already exists is never re-created.

Most MySQL/MariaDB DDL commits implicitly, so a failed migration cannot be
rolled back. Keep each one small and idempotent. On failure the runner stops
and does **not** record the version, so the next deploy retries it.

### Rollback

```
cd ~/repositories/nfelia
git log --oneline -10
git reset --hard <good-commit>
bash bin/deploy.sh
```

`bin/deploy.sh` resets to `origin/main`, so a rollback that must survive the
next deploy has to be a revert commit pushed to `main`, not just a local
reset. Migrations are forward-only: a schema change needs a new migration
that undoes it, not a git revert.

## Things this setup deliberately does not do

- **No `--delete`.** `rsync` is not installed, so the copy runs through
  `tar`, which never removes. A file deleted from the repo lingers in the
  docroot and must be removed by hand.
- **No staging environment.** `main` is production.
- **No automated test suite** yet. CI lints, sweeps for secrets and proves
  the deploy exclusions hold — nothing more.

## Gotchas this host taught us

- `rsync` is not installed. Use `tar`.
- `/dev/fd` is not exposed, so `exec > >(tee ...)` fails. `deploy.sh`
  re-execs through a plain pipe instead.
- LiteSpeed's PHP does not set `HOME`, and Composer refuses to start without
  it. `deploy.sh` exports it.
- `tar x` without `--no-overwrite-dir` copies the repo root's `0700` mode
  onto the docroot and the whole site starts returning 403.
- The CLI `php.ini` disables `allow_url_fopen`; Composer needs it, so it is
  passed with `-d` on the command line.
- `.htaccess` only rewrites paths that are **not** real files, so anything
  sitting at the docroot root is served raw. That is how a dump of the
  production database came to be public. `.deployignore` plus the CI check
  exist to stop it happening again.
