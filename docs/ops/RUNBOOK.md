# Nfelia runbook: how to do things

Step by step, copy and paste, for the hosting as it is today (cPanel
shared hosting behind Cloudflare). What each thing *is* lives in
[INFRASTRUCTURE.md](INFRASTRUCTURE.md); the move to a server of Nfelia's
own is [VPS-MIGRATION.md](VPS-MIGRATION.md).

Everything below runs in **cPanel → Terminal** as the account user unless
it says otherwise. `~` is `/home/smartqaq`.

---

## 1. Deploying

### 1.1 The normal way

Merge to `main`. GitHub Actions runs the quick checks and POSTs to
`https://nfelia.com/webhook/deploy.php`, which runs `bin/deploy.sh`
(reset the checkout to `origin/main`, copy into the docroot, Composer,
migrations). Watch the Actions run for `[webhook] DEPLOY OK`, or on the
server:

```bash
tail -40 ~/deploy-nfelia.log
```

### 1.2 Without GitHub Actions (quota used up, Actions down, or a hurry)

The same script, started by hand. Either in the panel:
**cPanel → Git Version Control → Manage (nfelia) → Pull or Deploy →
Update from Remote → Deploy HEAD Commit** (that is what `.cpanel.yml` runs);
or in the Terminal:

```bash
bash ~/repositories/nfelia/bin/deploy.sh
```

Before doing this for a change that skipped CI, run the test suite on a
local copy (§9) or at least `php -l` over the changed files.

### 1.3 Rolling back

```bash
cd ~/repositories/nfelia && git log --oneline -10      # pick the last good commit
git revert --no-edit <bad-commit>                        # a new commit that undoes it
git push origin main                                     # deploys (or deploy by hand, 1.2)
```

A plain `git reset --hard <good>` on the server is undone by the next
deploy, which resets to `origin/main`. Migrations are forward-only: a
schema change needs a new migration that reverses it.

### 1.4 A migration that failed

The runner stops at the failed file and records nothing for it, so the next
deploy retries. See why, fix the SQL in a new commit, deploy again:

```bash
/usr/local/bin/ea-php83 ~/repositories/nfelia/db/migrate.php --env=$HOME/public_html/nfelia.com/.env --status
```

---

## 2. Backups

### 2.1 Switch them on (once)

```bash
mkdir -p ~/backups/nfelia && chmod 700 ~/backups
bash ~/repositories/nfelia/bin/backup.sh          # first run, by hand: read the line it prints
( crontab -l 2>/dev/null | grep -v 'bin/backup.sh'; echo '40 1 * * * /usr/bin/env bash /home/smartqaq/repositories/nfelia/bin/backup.sh >/dev/null 2>&1' ) | crontab -
crontab -l
```

02:40 Lagos time every night. Ops → Health shows a **Backups** row from
the log at `~/backups/nfelia/backup.log`: red if nothing ran, amber if the
last copy stayed on this server only.

### 2.2 Copies off the server (Cloudflare R2)

1. Cloudflare dashboard → R2 → Create bucket `nfelia-backups` (location:
   EEUR or WEUR, nearest to the server). Then R2 → Manage API tokens →
   Create → Object Read & Write, limited to that bucket. Note the Access
   Key ID, the Secret Access Key, and the account's S3 endpoint
   `https://<account-id>.r2.cloudflarestorage.com`.
2. Install rclone in the home folder (no root needed):
   ```bash
   mkdir -p ~/bin && cd ~/bin && curl -fsSL https://downloads.rclone.org/rclone-current-linux-amd64.zip -o rclone.zip \
     && unzip -oj rclone.zip '*/rclone' && rm rclone.zip && chmod 755 rclone && ~/bin/rclone version
   grep -q 'HOME/bin' ~/.bash_profile || echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bash_profile
   ```
3. Tell rclone about the bucket (the remote's name must be `nfelia-offsite`):
   ```bash
   mkdir -p ~/.config/rclone && chmod 700 ~/.config/rclone
   cat > ~/.config/rclone/rclone.conf <<'CONF'
   [nfelia-offsite]
   type = s3
   provider = Cloudflare
   access_key_id = PASTE_ACCESS_KEY_ID
   secret_access_key = PASTE_SECRET_ACCESS_KEY
   endpoint = https://PASTE_ACCOUNT_ID.r2.cloudflarestorage.com
   acl = private
   CONF
   chmod 600 ~/.config/rclone/rclone.conf
   ~/bin/rclone lsd nfelia-offsite:                     # should list nfelia-backups
   ```
   The bucket name goes into the script's remote path: edit the two lines
   in `bin/backup.sh` that say `nfelia/` to `nfelia-backups/nfelia/`, or set
   `NF_BACKUP_REMOTE` and keep the layout. Cron jobs have a minimal PATH,
   so the crontab line becomes
   `40 1 * * * PATH=/home/smartqaq/bin:/usr/bin:/bin /usr/bin/env bash /home/smartqaq/repositories/nfelia/bin/backup.sh >/dev/null 2>&1`.
4. Run it once by hand and check the log line says `copied to`.

### 2.3 Restoring

Database (into the same database; everything in it is replaced):
```bash
ls ~/backups/nfelia/                                   # pick a dated folder
gzip -dc ~/backups/nfelia/<date>/db.sql.gz | mysql --protocol=socket -u smartqaq_nfelia_admin -p smartqaq_nfelia_core
```
Files:
```bash
tar xzf ~/backups/nfelia/<date>/files.tar.gz -C /      # paths inside are absolute: they land where they came from
```
Settings: `~/backups/nfelia/<date>/env` is the `.env` of that night.
Then `php db/migrate.php --status` to see whether the restored database is
behind the deployed code, and deploy once (1.2) to apply what is missing.
**Practise this once on a local copy before you ever need it.**

---

## 3. Secrets

### 3.1 Editing `.env`

cPanel → File Manager → `public_html/nfelia.com` → show hidden files →
`.env` → Edit. Or in the Terminal with `nano ~/public_html/nfelia.com/.env`.
Rules: no quotes around values, one `KEY=value` per line, no blank `=`
lines. Changes apply on the next request (no restart). Keep a copy under
`~/backups`, never under `public_html`.

### 3.2 Rotating each secret, and what else it touches

| Secret | How to rotate | Also change |
|---|---|---|
| `DB_PASS` | cPanel → MySQL Databases → Current Users → Change Password | `.env`; nothing else |
| `MAIL_PASSWORD` | cPanel → Email Accounts → no-reply@nfelia.com → Manage → Security | `.env` |
| `DEPLOY_WEBHOOK_SECRET` | `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` | `.env` **and** GitHub → Settings → Secrets → Actions → `DEPLOY_WEBHOOK_SECRET`; test with a trivial push |
| `PAYSTACK_SECRET_KEY` | Paystack dashboard → Settings → API Keys → Roll | `.env`; the webhook signature uses it, so do both within a minute |
| `APP_KEY` | `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` | `.env`; phones re-register their push device token on their next visit (the old VAPID-signed tokens still verify) |
| `VAPID_*` | **do not**: every phone subscription dies; if you must, every user turns notifications on again | |
| `SEAL_KEY` / `storage/private/seal.key` | **do not**: earlier signatures stop verifying at nfelia.com/verify | |
| `CLOUDFLARE_API_TOKEN` | Cloudflare → My Profile → API Tokens → Roll | `.env` |
| GitHub token in `~/.git-credentials` | GitHub → Developer settings → Fine-grained tokens | `~/.git-credentials` (see 6.2) |

After rotating `DB_PASS`, `MAIL_PASSWORD` or `APP_KEY`, open the site and
Ops → Health once.

### 3.3 If a secret has leaked

In this order: rotate it (3.2); for `DEPLOY_WEBHOOK_SECRET` also check
`~/deploy-nfelia.log` for deploys you did not make; for the database
password check Ops → Audit log and the `users` table for accounts you do
not know; for a GitHub token, GitHub → Settings → Security log. Then
write down what happened in Ops (an error note) so the team sees it.

---

## 4. The hosting panel

### 4.1 Turn OPcache on (the biggest performance win available today)

cPanel → **Select PHP Version** (CloudLinux PHP Selector) → make sure the
current version is 8.3 → **Extensions** → tick `opcache` → Save. Then
**Options** tab: `opcache.memory_consumption` 192, `opcache.max_accelerated_files`
20000, `opcache.validate_timestamps` On, `opcache.revalidate_freq` 60,
`opcache.interned_strings_buffer` 16. If the Selector says the domain uses
the "native" PHP instead, use cPanel → MultiPHP Manager to put `nfelia.com`
on ea-php83 first. Check afterwards with the environment report (section
5 must show `opcache.enable=1` and a hit rate climbing).

While there, tick `fileinfo`, `intl`, `exif` and `sodium` too (the app
has fallbacks, but these are the real thing).

### 4.2 The database address: leave it

`.env` says `DB_HOST=37.59.83.88` because the user's only grant is
`smartqaq_nfelia_admin@srv-web-ns9.newtoncorp.fr` (run 3): a TCP
connection from the server to its own address is what that host name
matches, and there is no `localhost` entry. The connection never leaves
the machine, so nothing is gained by changing it before the VPS, where
the user is created for `localhost` from the start.

### 4.3 Tidy the docroot

```bash
cd ~/public_html/nfelia.com
mkdir -p ~/backups/env-history && mv .env.bak-* .htaccess.bak-* ~/backups/env-history/ && chmod 600 ~/backups/env-history/*
rm -f composer.phar error_log            # the deploy uses ~/composer.phar; the log is from July
rmdir cgi-bin 2>/dev/null || true
```

### 4.4 The origin lock: test before the merge, check monthly, roll back in one line

The branch puts Cloudflare's address ranges at the top of `.htaccess`
(SECURITY.md §5). LiteSpeed reads the same directives as Apache, but prove
it on this server before the merge, in a folder of its own:

```bash
cd ~/public_html/nfelia.com && mkdir -p lock-test && echo ok > lock-test/index.html
git -C ~/repositories/nfelia fetch -q origin ccr-78f79379-31wjft
git -C ~/repositories/nfelia show origin/ccr-78f79379-31wjft:.htaccess | grep '^Require ip' > lock-test/.htaccess
curl -s -o /dev/null -w 'through Cloudflare: %{http_code}\n' https://nfelia.com/lock-test/
curl -s -o /dev/null -w 'straight to the server: %{http_code}\n' --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/lock-test/
rm -r lock-test
```

Expected: 200, then 403. Anything else (500 means LiteSpeed refused the
syntax): do not merge; send the two lines back.

After the merge and the deploy:

```bash
curl -s -o /dev/null -w 'direct, should be 403: %{http_code}\n' --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/login
curl -s -o /dev/null -w 'via Cloudflare, should be 200: %{http_code}\n' https://nfelia.com/login
curl -s -o /dev/null -w '.well-known direct, should be 404 not 403: %{http_code}\n' --resolve nfelia.com:443:37.59.83.88 https://nfelia.com/.well-known/x
```

Then cPanel → SSL/TLS Status → Run AutoSSL: `mail.nfelia.com` and
`webmail.nfelia.com` must still pass their check.

Monthly: `bash ~/nfelia-env-report.sh`, section 6, prints "in step" or the
ranges to add. By eye: https://www.cloudflare.com/ips/ against the
`Require ip` lines.

Rollback, if visitors ever see 403: File Manager → `.htaccess` → delete
the `Require ip` lines, save (it takes effect at once); then the same in
the repository, or the next deploy puts them back.

### 4.5 The content-policy trial

From the merge, every page carries `Content-Security-Policy-Report-Only`
(SECURITY.md §6). Nothing is blocked. Ops → Errors, kind "Content policy",
collects what the policy would have refused: each group names the
directive and the foreign host, with the pages and the business. After
two weeks: a group that names a page of ours loading something legitimate
means the policy needs that source added; a group that names a host
nobody recognises is worth a look the same day. When the list is
understood, the header's name changes to `Content-Security-Policy` in
`.htaccess` and the policy is enforced.

### 4.4 The old domain

`nfelia.lpc.cm` still points at the server and `.htaccess` redirects it.
Keep it until the last customer link in the wild has aged out, then drop
the cPanel subdomain and the DNS record.

---

## 5. Cloudflare

- **Purge a shop at once** (optional): My Profile → API Tokens → Create →
  Custom → one permission, Zone → Cache Purge → Purge, zone nfelia.com.
  Put it in `.env` as `CLOUDFLARE_API_TOKEN=…` with
  `CLOUDFLARE_ZONE_ID=1a0e9a750a9786c0d9f236c168507e15`.
- **Purge everything** after a change to shop templates: Caching →
  Configuration → Purge Everything.
- **DMARC step-up** (after two weeks of clean reports at dmarc@nfelia.com):
  DNS → `_dmarc` TXT → `v=DMARC1; p=quarantine; rua=mailto:dmarc@nfelia.com; pct=100`.
- **Before anything else: 2FA** on both account members (My Profile →
  Authentication → Two-Factor Authentication, an authenticator app plus
  backup codes), then Manage Account → Members → Enforce 2FA. The export
  of 20 October found it off on both (SECURITY.md L-10).
- **Minimum TLS 1.2**: SSL/TLS → Edge Certificates → Minimum TLS Version
  (or CLOUDFLARE.md prompt B).
- **DNSSEC**: DNS → Settings → Enable DNSSEC, then the DS record it shows
  goes to the registrar of `nfelia.com`.
- **Notifications** (free): Notifications → Add → "SSL for SaaS /
  Universal SSL" expiry, "DDoS Attack L7", "Origin Error Rate"; to the
  owner's mailbox.
- HSTS, origin lock and the rules: SECURITY.md §4 and §5 (decisions first).
- The zone can be read and changed by Cloudflare's own assistant in the
  dashboard with a token you give it; the paste-ready prompts, and what
  the read-only run of 20 October found, are in [CLOUDFLARE.md](CLOUDFLARE.md).

---

## 6. GitHub

### 6.1 Protect `main` (do this now)

Settings → Branches → Add branch ruleset for `main`: require a pull
request before merging, require status checks to pass (`Lint, secret
sweep, deploy-exclusion proof` and `Notifications and Nfelia Ops, end to
end`), block force pushes, block deletions. Settings → Code security →
Dependabot alerts on. Account → Password and authentication → two-factor
on.

### 6.2 Going private without breaking deploys

1. GitHub → Settings → Developer settings → Fine-grained personal access
   tokens → Generate: repository access only `nfelia`; permissions
   Contents read-only, Metadata read; expiry one year (calendar reminder).
2. On the server:
   ```bash
   printf 'https://x-access-token:PASTE_TOKEN@github.com\n' > ~/.git-credentials && chmod 600 ~/.git-credentials
   git -C ~/repositories/nfelia fetch origin main && echo FETCH OK
   ```
3. Repository → Settings → General → Danger Zone → Change visibility →
   Private.
4. Push a small commit to `main` and watch Actions reach `[webhook] DEPLOY OK`.

Minutes: once private, Actions is limited to 2,000 minutes a month on the
free plan. The workflow runs the long end-to-end job once per pull
request only; a run costs about 12 minutes, the quick checks about 3.
Actions → the workflow → "Run workflow" runs the long job on demand.

---

## 7. The environment report

Any time, before and after a change to the panel, and on the first day on
the VPS:

```bash
cd ~/repositories/nfelia && git fetch origin main && git show origin/main:bin/env-report.sh > ~/nfelia-env-report.sh && bash ~/nfelia-env-report.sh
```

(Until this branch is merged, use `origin/ccr-78f79379-31wjft` in place of
`origin/main`.) With a read-only Cloudflare token: `CF_API_TOKEN=… bash ~/nfelia-env-report.sh`.
The report masks secrets; the schema copy it writes stays in `~`.

**Section 10 (database) and the schema copy.** The shell signs in as the
app user over TCP to the address in `.env` (run 3 did); the socket is
refused because the user's only grant is bound to the server's hostname
(INFRASTRUCTURE.md §10), which is how this server is set up and needs no
fixing. The schema copy is asked of the panel's API first (`uapi Mysql
dump_database_schema`; in run 3 its answer held no tables, so the script
now checks and lets mysqldump take over) and never leaves an empty file.
By hand, typing the `.env` password when asked:

```bash
mysqldump --no-data --skip-comments --skip-add-drop-table --routines --triggers --events -h 37.59.83.88 --protocol=tcp -u smartqaq_nfelia_admin -p smartqaq_nfelia_core > ~/nfelia-schema-live.sql
```

### Checking the schema

Production against the repository (schema + all migrations), from a
machine with PHP, MariaDB and the repo:

```bash
bash tests/notifications/setup.sh                               # builds the migrated schema locally (needs a local .env)
mysqldump --no-data --skip-comments --skip-add-drop-table --routines <local db> > /tmp/local.sql
# then compare /tmp/local.sql with the server's ~/nfelia-schema-live-<date>.sql:
diff <(grep -E '^(CREATE TABLE|  `|  (UNIQUE |PRIMARY )?KEY)' /tmp/local.sql | sed -E 's/ AUTO_INCREMENT=[0-9]+//') \
     <(grep -E '^(CREATE TABLE|  `|  (UNIQUE |PRIMARY )?KEY)' live.sql       | sed -E 's/ AUTO_INCREMENT=[0-9]+//')
```

Empty output means production matches the code.

---

## 8. The cron

One line does everything (reminders, billing lifecycle, the outbox, the
Ops monitor, Records Books, clean-up), every five minutes:

```
*/5 * * * * /usr/local/bin/ea-php83 /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1
```

Run it by hand to see a run's counts: the same command without the
redirect. It refuses to overlap itself (`GET_LOCK`). Ops → Health says
when it last finished; after 20 minutes of silence the team is alerted.

---

## 9. A local copy for testing (what CI does)

```bash
git clone https://github.com/tomblakeasaah196/nfelia.git && cd nfelia && composer install
cp .env.example .env    # APP_ENV=testing, APP_URL=http://localhost:8099, a local MariaDB user, MAIL 127.0.0.1:2525, PASSWORD_BREACH_CHECK=off, PUSH_TEST_ENDPOINTS=http://127.0.0.1:8098/
php bin/vapid-keys.php >> .env
bash tests/notifications/setup.sh
php -d opcache.jit_buffer_size=0 -S 127.0.0.1:8099 -t . tests/notifications/router.php &
bash tests/notifications/run.sh engine flows cron sw ops/foundation ops/monitor ops/clients ops/sales ops/uploads orders/core ops/records docs/sign auth/signin
```

About 1,500 checks in 12 minutes. **It wipes the database the `.env` names**,
so never point a copy at production.

---

## 10. When something is wrong

| Symptom | Look at | Likely |
|---|---|---|
| Every page "Database connection failed" | `storage/logs/php_errors.log`; `.env` `DB_*`; is `DB_HOST` reachable | password changed, IP changed, a pinned collation |
| Pages 403 | `ls -la ~/public_html/nfelia.com` (mode 0700 on the docroot after a deploy without `--no-overwrite-dir`) | `chmod 755 ~/public_html/nfelia.com` |
| Every URL 404s after a deploy | file modes 600 from a clone made under `umask 077` | `bash bin/deploy.sh` again (it normalises) |
| Deploy says another is running | `~/.deploy-nfelia.lock` held by a stuck run | wait, or `pkill -f deploy.sh` then rerun |
| No reminders, no emails | Ops → Health (cron row), `crontab -l` | the cron line vanished (cPanel rewrites crontabs on PHP changes) |
| Shop shows old content | Cloudflare cache (5 minutes), or no purge token | wait or purge |
| Sign-in says too many attempts | `auth_throttle` rows for that address; 15 minutes | someone guessing; the owner was alerted |
| Push notifications stopped | VAPID keys changed? Ops → Health push keys | restore the pair from the `.env` backup |
