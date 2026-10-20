# Moving Nfelia to its own server

A checklist, not an investigation. Everything the current host provides
is listed in [INFRASTRUCTURE.md](INFRASTRUCTURE.md); every file this plan
installs is in [`docs/ops/vps/`](vps/). Done in order, a weekend is
plenty; the switch itself is one DNS edit in Cloudflare and takes effect
in seconds.

Decisions this plan assumes (change them and the matching section): Ubuntu
24.04 LTS; nginx + PHP-FPM 8.3; MariaDB 10.11 (the same major as today, so
the database moves by dump and restore with no code change); deploys over
SSH from GitHub Actions into release directories; mail stays on the cPanel
server (the app only changes its SMTP host) until a transactional mail
service is chosen; Cloudflare stays in front, with the origin accepting
Cloudflare only; backups to Cloudflare R2.

---

## 0. Before the day

- [ ] Server: 2 vCPU, 4 GB RAM, 40+ GB SSD, Ubuntu 24.04, in Europe (Cloudflare
      terminates in Lagos either way; Hetzner, OVH, DigitalOcean all fine). Note its IPv4 and IPv6.
- [ ] Backups off the server already running from the old host (RUNBOOK §2.2):
      the latest dated folder in R2 is what moves.
- [ ] `bin/env-report.sh` run on the old host and kept: it is the reference.
- [ ] Cloudflare: an **Origin CA certificate** for `nfelia.com` and `*.nfelia.com`
      (SSL/TLS → Origin Server → Create; RSA, 15 years); save the certificate and key.
      Download the **Authenticated Origin Pulls** CA from Cloudflare's docs.
- [ ] A fine-grained GitHub token or deploy key for the server (read-only, this repo).
- [ ] A new SSH key pair for GitHub Actions to reach the server (`ssh-keygen -t ed25519 -C nfelia-deploy`).
- [ ] Tell the team the hour; no uploads or payments for ~30 minutes during the switch.

---

## 1. The server (once, ~40 minutes)

As root on the new server. Each step has its file under `docs/ops/vps/`.

```bash
# 1.1 Base
apt update && apt -y full-upgrade && apt -y install ufw fail2ban unattended-upgrades curl git unzip rclone \
    nginx mariadb-server redis-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl php8.3-bcmath php8.3-opcache php8.3-redis php8.3-imagick
timedatectl set-timezone Africa/Lagos
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap sw 0 0' >> /etc/fstab
dpkg-reconfigure -plow unattended-upgrades

# 1.2 The application user and layout
adduser --disabled-password --gecos 'Nfelia' nfelia
mkdir -p /var/www/nfelia/{releases,shared/storage,shared/uploads} /var/backups/nfelia
chown -R nfelia:nfelia /var/www/nfelia /var/backups/nfelia
chmod 750 /var/www/nfelia && usermod -aG nfelia www-data
install -d -m 700 -o nfelia -g nfelia /home/nfelia/.ssh
# the Actions deploy key, restricted to the deploy script:
echo 'command="/var/www/nfelia/current/bin/deploy-vps.sh",no-port-forwarding,no-agent-forwarding,no-pty ssh-ed25519 AAAA... nfelia-deploy' > /home/nfelia/.ssh/authorized_keys
chmod 600 /home/nfelia/.ssh/authorized_keys && chown -R nfelia:nfelia /home/nfelia/.ssh

# 1.3 Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 1.4 PHP
cp docs/ops/vps/php/99-nfelia.ini /etc/php/8.3/fpm/conf.d/99-nfelia.ini
cp docs/ops/vps/php/99-nfelia.ini /etc/php/8.3/cli/conf.d/99-nfelia.ini
cp docs/ops/vps/php/nfelia-pool.conf /etc/php/8.3/fpm/pool.d/nfelia.conf && rm -f /etc/php/8.3/fpm/pool.d/www.conf
systemctl enable --now php8.3-fpm

# 1.5 MariaDB
cp docs/ops/vps/mariadb/60-nfelia.cnf /etc/mysql/mariadb.conf.d/60-nfelia.cnf && systemctl restart mariadb
mysql_secure_installation
mysql -e "CREATE DATABASE nfelia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER 'nfelia'@'localhost' IDENTIFIED BY 'CHOOSE-A-LONG-PASSWORD';
          GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES, EXECUTE, CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT ON nfelia.* TO 'nfelia'@'localhost';"

# 1.6 nginx + TLS
install -d -m 700 /etc/nginx/ssl
# paste the Origin CA certificate and key, and the origin-pull CA:
#   /etc/nginx/ssl/nfelia.com.pem  /etc/nginx/ssl/nfelia.com.key  /etc/nginx/ssl/cloudflare-origin-pull-ca.pem
cp docs/ops/vps/nginx/cloudflare.conf /etc/nginx/conf.d/cloudflare.conf
cp docs/ops/vps/nginx/nfelia.conf /etc/nginx/sites-available/nfelia.conf
ln -sf /etc/nginx/sites-available/nfelia.conf /etc/nginx/sites-enabled/nfelia.conf && rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# 1.7 Firewall: SSH from anywhere (keys only), web only from Cloudflare
cp docs/ops/vps/firewall/cloudflare-ufw.sh /usr/local/sbin/cloudflare-ufw.sh && chmod 755 /usr/local/sbin/cloudflare-ufw.sh
ufw default deny incoming && ufw default allow outgoing && ufw allow 22/tcp && /usr/local/sbin/cloudflare-ufw.sh && ufw --force enable
cp docs/ops/vps/systemd/cloudflare-ufw.{service,timer} /etc/systemd/system/ && systemctl enable --now cloudflare-ufw.timer
cp docs/ops/vps/fail2ban/jail.local /etc/fail2ban/jail.local && systemctl restart fail2ban
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config && systemctl reload ssh

# 1.8 The timers (cron and backups)
cp docs/ops/vps/systemd/nfelia-cron.{service,timer} docs/ops/vps/systemd/nfelia-backup.{service,timer} /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now nfelia-cron.timer nfelia-backup.timer
```

---

## 2. The application (once)

As `nfelia`:

```bash
cd /var/www/nfelia
git clone --bare https://github.com/tomblakeasaah196/nfelia.git repo     # with the token in the URL if private; see RUNBOOK 6.2
cp repo/../shared/.env.example shared/.env 2>/dev/null || git --git-dir=repo show main:.env.example > shared/.env
```

Fill `shared/.env` from the old host's `.env` (the latest backup's `env`
file): same `APP_KEY`, `VAPID_*`, `PAYSTACK_*`, `DEPLOY_WEBHOOK_SECRET` is
no longer needed (no webhook), `MAIL_HOST=mail.nfelia.com` (or the cPanel
server's hostname) with the same mailbox and password, `DB_HOST=localhost`,
`DB_NAME=nfelia`, `DB_USER=nfelia`, the new `DB_PASS`,
`MOMO_RECEIPT_DIR=/var/www/nfelia/shared/storage/momo_receipts`,
`BACKUP_DIR=/var/backups/nfelia`. `chmod 600 shared/.env`.

First release (the deploy script does the same every time, see
`docs/ops/vps/deploy-vps.sh`):

```bash
bash <(git --git-dir=/var/www/nfelia/repo show main:bin/deploy-vps.sh) main
```

It makes `releases/<sha>`, links `shared/uploads` and `shared/storage` into
it, runs Composer, applies migrations (none yet: the restore comes next),
switches `current`, reloads FPM.

---

## 3. The data

```bash
# on the old host, the final backup (users warned; ideally the planner cron commented out first):
bash ~/repositories/nfelia/bin/backup.sh
# on the new server, pull it from R2 and restore:
rclone copy nfelia-offsite:nfelia-backups/nfelia/<latest> /var/backups/nfelia/incoming
gzip -dc /var/backups/nfelia/incoming/db.sql.gz | mysql nfelia
tar xzf /var/backups/nfelia/incoming/files.tar.gz -C /tmp/restore --strip-components=0
rsync -a /tmp/restore/home/smartqaq/public_html/nfelia.com/uploads/  /var/www/nfelia/shared/uploads/
rsync -a /tmp/restore/home/smartqaq/public_html/nfelia.com/storage/private/        /var/www/nfelia/shared/storage/private/
rsync -a /tmp/restore/home/smartqaq/public_html/nfelia.com/storage/momo_receipts/  /var/www/nfelia/shared/storage/momo_receipts/
chown -R nfelia:nfelia /var/www/nfelia/shared && chmod -R o-rwx /var/www/nfelia/shared/storage
sudo -u nfelia php /var/www/nfelia/current/db/migrate.php --env=/var/www/nfelia/shared/.env --status
```

The database name changes from `smartqaq_nfelia_core` to `nfelia`: the
only place the old name is written is one early migration that already
ran, so nothing else cares. `storage/private/seal.key` must be among the
restored files: check `ls -la /var/www/nfelia/shared/storage/private/seal.key`.

---

## 4. Rehearse before switching

Point your own machine at the new server without touching DNS: add
`NEW.IP nfelia.com ops.nfelia.com adas-hair.nfelia.com` to your hosts file
(or `curl --resolve`). Because the origin only accepts Cloudflare, test
from the server itself:

```bash
curl -sk --resolve nfelia.com:443:127.0.0.1 https://nfelia.com/login -o /dev/null -w '%{http_code}\n'   # 200
sudo -u nfelia NF_DOCROOT=/var/www/nfelia/current NF_REPO=/var/www/nfelia/current bash /var/www/nfelia/current/bin/env-report.sh
```

Open Ops → Health through the hosts-file trick: every row green except
"Backups" until the first timer run.

---

## 5. The switch (15 minutes)

1. Old host: comment out the planner line (`crontab -e`), run the final
   backup (step 3), and restore it on the new server again so no order or
   payment made in the last hours is lost.
2. Cloudflare → DNS: change the **A record of `nfelia.com`** to the new IP
   (and the AAAA if IPv6), and the proxied `www`, `ops`, `*`, `cpanel`,
   `webdisk` records likewise (or delete `cpanel`/`webdisk`: nothing serves
   them now). Proxied records change at once.
3. Watch: `journalctl -u php8.3-fpm -f` and `tail -f /var/log/nginx/nfelia.access.log`.
4. Check in this order: sign in; open a shop; a public invoice and its PDF;
   Ops → Health; send yourself a test email (More → Notifications); a test
   push; Paystack dashboard → webhook → "send test event" (expect 200 from
   `/api/webhooks`); a Records Book build from the cron (`systemctl start nfelia-cron.service`, then its log).
5. GitHub → Settings → Secrets: add `DEPLOY_SSH_KEY`, `DEPLOY_HOST`; switch
   the workflow's deploy job to the SSH step (`docs/ops/vps/github-deploy-step.yml`),
   remove `DEPLOY_WEBHOOK_*`. Merge a trivial change and watch it land.

Rollback within the first day: point the DNS records back; anything
written on the new server since the switch is lost unless you dump it and
replay it, so decide quickly.

---

## 6. After

- [ ] Old host: leave the files in place for two weeks, then remove the
      `nfelia.com` addon domain and the `*.nfelia.com` subdomain from cPanel
      (mail stays), delete the docroot, the repo checkout and the webhook.
      Keep the `.env` backup.
- [ ] Cloudflare: HSTS on (SECURITY.md §4), a rate limit on `/login`,
      Authenticated Origin Pulls on for the zone (SSL/TLS → Origin Server).
- [ ] Repository: private (RUNBOOK §6.2); `webhook/deploy.php` and
      `.cpanel.yml` deleted; `bin/deploy.sh` kept only for history.
- [ ] External uptime check on `https://nfelia.com/login` and
      `https://ops.nfelia.com/signin`.
- [ ] A restore drill from R2 onto a scratch database within the first month.
- [ ] Mail: decide between keeping cPanel's Exim (works as is) and a
      transactional service; if the latter, update `MAIL_*`, SPF and DKIM.

---

## 7. What is different on the server, in one table

| | Shared hosting (today) | VPS |
|---|---|---|
| Web server | LiteSpeed, `.htaccess` | nginx (`docs/ops/vps/nginx/nfelia.conf` carries every rule) |
| PHP | ea-php83 LSAPI, OPcache off | PHP-FPM 8.3, OPcache on, validate_timestamps off |
| Deploy | webhook → tar copy in place | SSH → release directory → symlink switch (atomic, rollback by relinking) |
| Cron | crontab line | `nfelia-cron.timer` (5 min) |
| Backups | none → `backup.sh` + R2 | `nfelia-backup.timer` nightly → R2 |
| Sessions | files in `storage/sessions` | files, or Redis (`session.save_handler=redis` in the pool) |
| Origin access | anyone who knows the IP | ufw: Cloudflare ranges only; nginx: Cloudflare client certificate required |
| Real visitor IP | app trusts `CF-Connecting-IP` when `REMOTE_ADDR` is Cloudflare | nginx `real_ip` sets `REMOTE_ADDR` itself; `client_ip()` keeps working |
| Error log | `storage/logs/php_errors.log` | the same path (shared/storage), plus `journalctl` |
| Mail | localhost:465 | `mail.nfelia.com:465`, same mailbox |
| Other tenants on the box | seven other sites, same user | none |
