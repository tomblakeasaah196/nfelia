#!/usr/bin/env bash
# Deploy Nfelia to the live docroot.
#
# Runs identically regardless of who calls it:
#   - webhook/deploy.php   (GitHub Actions, on push to main)
#   - .cpanel.yml          ("Deploy HEAD Commit" in cPanel Git Version Control)
#   - a human              (bash bin/deploy.sh from cPanel Terminal)
#
# Steps:
#   1. git fetch + hard reset to origin/main (no local commit survives here)
#   2. tar repo -> docroot, applying .deployignore
#   3. composer install --no-dev
#   4. php db/migrate.php
#
# Only one deploy runs at a time (flock). The full log is always mirrored to
# /home/smartqaq/deploy-nfelia.log so the last run can be reviewed from the
# cPanel Terminal even if the caller disconnected mid-deploy.

LOGFILE=/home/smartqaq/deploy-nfelia.log

# Re-exec through a plain pipe to tee so output goes BOTH to the caller and
# to LOGFILE. This host does not expose /dev/fd, so process substitution
# (`exec > >(tee ...)`) dies with "/dev/fd/63: No such file or directory".
# PIPESTATUS preserves the script's real exit code through the pipe.
if [ -z "${DEPLOY_LOG_WRAPPED:-}" ]; then
    export DEPLOY_LOG_WRAPPED=1
    set -o pipefail
    bash "$0" "$@" 2>&1 | tee -a "$LOGFILE"
    exit "${PIPESTATUS[0]}"
fi

set -euo pipefail

# The webhook runs us from LiteSpeed's PHP, which does not set HOME, and
# Composer refuses to start without HOME or COMPOSER_HOME.
export HOME="${HOME:-/home/smartqaq}"

REPO=/home/smartqaq/repositories/nfelia
DEPLOYPATH=/home/smartqaq/public_html/nfelia.com
PHP=/usr/local/bin/ea-php83
COMPOSER=/home/smartqaq/composer.phar
LOCKFILE=/home/smartqaq/.deploy-nfelia.lock

# Serialise deploys. If one is already in flight, refuse rather than queue.
exec 9>"$LOCKFILE"
if ! flock -n 9; then
    echo "[deploy] another deploy is already running; refusing to start a second one" >&2
    exit 75   # EX_TEMPFAIL
fi

echo ""
echo "============================================================"
echo "[deploy] $(date -Iseconds) starting"
echo "[deploy] caller uid=$(id -u) whoami=$(whoami)"
echo "============================================================"

# --- 1. Refresh the checkout to whatever is on origin/main right now --------
cd "$REPO"
echo "[deploy] pre-pull  HEAD: $(git rev-parse HEAD)"
git fetch origin main --quiet
git reset --hard origin/main
echo "[deploy] post-pull HEAD: $(git rev-parse HEAD)"
git log -1 --pretty='[deploy] commit: %h %s (%an)'

# --- 2. Copy the tree into the docroot, honouring .deployignore ------------
# rsync is not installed on this cPanel host, so we stream through tar.
# NOTE: this does NOT do rsync's --delete. A file removed from the repo
# lingers in the docroot and must be deleted by hand. Runtime files (.env,
# .htaccess, uploads/, storage/) are safe because they are never copied.
if [ ! -f "$REPO/.deployignore" ]; then
    echo "[deploy] $REPO/.deployignore missing — aborting" >&2
    exit 1
fi
if [ ! -d "$DEPLOYPATH" ]; then
    echo "[deploy] docroot $DEPLOYPATH does not exist — aborting" >&2
    exit 1
fi
# --no-overwrite-dir is load-bearing: without it the archive's "." member
# copies the repo root's 0700 mode onto the docroot, LiteSpeed (group
# nobody) can no longer enter it, and every page returns 403.
tar cf - --exclude-from="$REPO/.deployignore" -C "$REPO" . \
  | ( cd "$DEPLOYPATH" && tar xpf - --no-overwrite-dir )
echo "[deploy] tar -> $DEPLOYPATH done"

# Normalise permissions. tar xp preserves the archive's modes, and the
# archive carries whatever umask was in force when the repo was cloned.
# A clone made under `umask 077` yields mode 600 files, which tar copies
# faithfully into the docroot — the web server can then read nothing and
# every URL 404s. (This happened on 2026-10-17.) Set them explicitly so
# the deploy never depends on anyone's umask.
#   u=rwX  owner reads/writes; x only on directories and already-exec files
#   go=rX  group/other read; traverse directories
chmod -R u=rwX,go=rX "$DEPLOYPATH"

# ...except the secrets, which must stay owner-only.
for secret in "$DEPLOYPATH"/.env "$DEPLOYPATH"/.env.*; do
    [ -f "$secret" ] && chmod 600 "$secret"
done
echo "[deploy] permissions normalised"

# --- 3. Composer dependencies ----------------------------------------------
# -d allow_url_fopen=On because this host's CLI php.ini disables it and
# Composer needs it to reach packagist.
cd "$DEPLOYPATH"
"$PHP" -d allow_url_fopen=On "$COMPOSER" install \
    --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -40
echo "[deploy] composer install done"

# --- 4. Apply pending SQL migrations ---------------------------------------
# migrate.php lives in the repo (deploy-ignored, so never in the docroot),
# but reads the docroot .env so it targets the live database.
"$PHP" "$REPO/db/migrate.php" --env="$DEPLOYPATH/.env"

echo "============================================================"
echo "[deploy] $(date -Iseconds) OK"
echo "============================================================"
