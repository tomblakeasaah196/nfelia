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
#   5. php bin/errors_fixed.php (errors the new commits say they fix)
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
PRE_HEAD=$(git rev-parse HEAD)
echo "[deploy] pre-pull  HEAD: $PRE_HEAD"
git fetch origin main --quiet
git reset --hard origin/main
echo "[deploy] post-pull HEAD: $(git rev-parse HEAD)"
git log -1 --pretty='[deploy] commit: %h %s (%an)'

# --- 2. Copy the tree into the docroot, honouring .deployignore ------------
# rsync is not installed on this cPanel host, so the release is streamed
# through tar into a staging folder, and bin/deploy_sync.php copies into the
# docroot only the files whose content changed, each written under a
# temporary name and renamed over the old one in one step. Untarring
# straight over the docroot rewrote every file in place, and a request
# could read one half-written: on 27 Nov 2026 a page crashed on
# lib/PHPMailer/src/SMTP.php mid-deploy ("Unclosed '{'").
# NOTE: there is no --delete. A file removed from the repo lingers in the
# docroot and must be deleted by hand. Runtime files (.env, uploads/,
# storage/) are safe because .deployignore leaves them out.
if [ ! -f "$REPO/.deployignore" ]; then
    echo "[deploy] $REPO/.deployignore missing — aborting" >&2
    exit 1
fi
if [ ! -d "$DEPLOYPATH" ]; then
    echo "[deploy] docroot $DEPLOYPATH does not exist — aborting" >&2
    exit 1
fi
STAGE="$HOME/.deploy-nfelia-stage"
rm -rf "$STAGE"
mkdir -p "$STAGE"
tar cf - --exclude-from="$REPO/.deployignore" -C "$REPO" . \
  | ( cd "$STAGE" && tar xpf - )
echo "[deploy] tar -> $STAGE (staged)"

# --- Origin lock (docs/ops/SECURITY.md §5) ---------------------------------
# The repo's .htaccess carries placeholders; the real value lives in the
# docroot .env as EDGE_TOKEN and in Cloudflare's request-header rule. With a
# usable token the block is armed; without one it is removed, the site stays
# open as before, and the log says so. Never fails the deploy. Done on the
# staged copy, so the live .htaccess is only replaced when it really changes
# and never sits unarmed for a moment.
EDGE_TOKEN="$(grep -E '^EDGE_TOKEN=' "$DEPLOYPATH/.env" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r"'"'"' ' || true)"
if printf '%s' "$EDGE_TOKEN" | grep -Eq '^[A-Za-z0-9_-]{24,128}$'; then
    sed -i -e "s/__EDGE_TOKEN__/$EDGE_TOKEN/g" -e "s/__EDGE_ARMED__/armed/g" "$STAGE/.htaccess"
    echo "[deploy] origin lock ARMED (EDGE_TOKEN from .env; Cloudflare must send X-Nfelia-Edge)"
else
    sed -i '/# nf-edge-lock-begin/,/# nf-edge-lock-end/d' "$STAGE/.htaccess"
    echo "[deploy] origin lock OFF: no usable EDGE_TOKEN in .env (letters, digits, - and _, 24 to 128 long)"
fi

# Folders are made when missing and never re-moded: the docroot's own mode
# keeps LiteSpeed (group nobody) able to enter it, or every page is a 403.
"$PHP" "$REPO/bin/deploy_sync.php" "$STAGE" "$DEPLOYPATH"
rm -rf "$STAGE"

# The release now live, for Nfelia Ops (errors are tagged with it, and a
# problem fixed in one release that comes back in a later one says so).
# storage/ is never copied over and is closed to the web (.htaccess).
mkdir -p "$DEPLOYPATH/storage"
git -C "$REPO" log -1 --pretty='%h %cI %s' > "$DEPLOYPATH/storage/release"
echo "[deploy] release $(cut -d' ' -f1 "$DEPLOYPATH/storage/release")"

# uploads/ is runtime data and never copied, so its .htaccess (no scripts
# run there, long caching for the images) is laid down from the repo on
# every deploy instead.
mkdir -p "$DEPLOYPATH/uploads"
cp "$REPO/bin/uploads.htaccess" "$DEPLOYPATH/uploads/.htaccess.deploy-$$" && mv -f "$DEPLOYPATH/uploads/.htaccess.deploy-$$" "$DEPLOYPATH/uploads/.htaccess"
echo "[deploy] uploads/.htaccess refreshed"

# Normalise permissions. tar xp preserves the archive's modes, and the
# archive carries whatever umask was in force when the repo was cloned.
# A clone made under `umask 077` yields mode 600 files, which tar copies
# faithfully into the docroot — the web server can then read nothing and
# every URL 404s. (This happened on 2026-10-17.) Set them explicitly so
# the deploy never depends on anyone's umask.
#   u=rwX  owner reads/writes; x only on directories and already-exec files
#   go=rX  group/other read; traverse directories
chmod -R u=rwX,go=rX "$DEPLOYPATH"

# ...except the secrets, which must stay owner-only: .env, and everything the
# app keeps for itself (sessions, private files and the seal key under them,
# logs, kept PDFs). Those are never served, so the web server needs no
# access to them either.
for secret in "$DEPLOYPATH"/.env "$DEPLOYPATH"/.env.*; do
    [ -f "$secret" ] && chmod 600 "$secret"
done
for private in sessions private logs pdf momo_receipts; do
    [ -d "$DEPLOYPATH/storage/$private" ] && chmod -R o-rwx "$DEPLOYPATH/storage/$private"
done
[ -f "$DEPLOYPATH/storage/private/seal.key" ] && chmod 600 "$DEPLOYPATH/storage/private/seal.key"
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

# --- 5. Errors this deploy fixed --------------------------------------------
# A commit with a "Fixes-Errors: #12, #15" line marks those problems fixed in
# Nfelia Ops now that its code is live. Never fails the deploy.
"$PHP" "$REPO/bin/errors_fixed.php" --env="$DEPLOYPATH/.env" --repo="$REPO" \
    --from="$PRE_HEAD" --to="$(git -C "$REPO" rev-parse HEAD)" || true

echo "============================================================"
echo "[deploy] $(date -Iseconds) OK"
echo "============================================================"
