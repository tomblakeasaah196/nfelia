#!/usr/bin/env bash
# =====================================================================
#  Nfelia backup: the database (structure and data), the files people
#  uploaded, the private files, and .env, into one dated folder, kept for
#  a fortnight locally and, when rclone is set up, copied off the server.
#
#  Nightly from the crontab (every day at 02:40 Lagos time):
#      40 1 * * * /usr/bin/env bash /home/smartqaq/repositories/nfelia/bin/backup.sh >/dev/null 2>&1
#  By hand:
#      bash ~/repositories/nfelia/bin/backup.sh
#
#  Where things go:
#      ~/backups/nfelia/<date>/db.sql.gz        the database, mysqldump --single-transaction
#      ~/backups/nfelia/<date>/files.tar.gz     uploads/, storage/private, storage/momo_receipts, momo_receipts
#      ~/backups/nfelia/<date>/env               a copy of .env (mode 600, like the folder)
#      ~/backups/nfelia/backup.log              one line per run; Ops → Health can read it
#
#  Off-site: if `rclone` is on the PATH and a remote named "nfelia-offsite"
#  exists (an R2 bucket: docs/ops/RUNBOOK.md, "Backups"), the dated folder
#  is copied there too and folders older than 90 days are removed from it.
#  Without rclone the local copy is all there is, and the log says so.
#
#  Never run it from the docroot, and never put a dump under public_html:
#  a database dump sat on the public web for three months once.
#
#  Overrides (a VPS or a local copy):
#      NF_DOCROOT=/var/www/nfelia NF_BACKUPS=/var/backups/nfelia bash bin/backup.sh
# =====================================================================
set -uo pipefail
umask 077

HOME_DIR="${HOME:-$(getent passwd "$(id -un)" | cut -d: -f6)}"
DOCROOT="${NF_DOCROOT:-$HOME_DIR/public_html/nfelia.com}"
BACKUPS="${NF_BACKUPS:-$HOME_DIR/backups/nfelia}"
KEEP_DAYS="${NF_BACKUP_KEEP_DAYS:-14}"
OFFSITE_KEEP_DAYS="${NF_BACKUP_OFFSITE_KEEP_DAYS:-90}"
REMOTE="${NF_BACKUP_REMOTE:-nfelia-offsite}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="$BACKUPS/$STAMP"
LOG="$BACKUPS/backup.log"

mkdir -p "$DEST" || { echo "cannot make $DEST" >&2; exit 1; }
chmod 700 "$BACKUPS" "$DEST"
log() { printf '%s %s\n' "$(date -Is)" "$*" | tee -a "$LOG"; }
fail() { log "FAILED: $*"; exit 1; }

case "$DEST" in "$DOCROOT"*|*/public_html/*) fail "refusing to write a backup under the web root ($DEST)";; esac
[ -f "$DOCROOT/.env" ] || fail ".env not found at $DOCROOT/.env"

envval() { grep -E "^$1=" "$DOCROOT/.env" | tail -1 | cut -d= -f2- | sed -E "s/^[\"']//; s/[\"']$//"; }
DB_HOST="$(envval DB_HOST)"; DB_PORT="$(envval DB_PORT)"; DB_NAME="$(envval DB_NAME)"; DB_USER="$(envval DB_USER)"
export MYSQL_PWD="$(envval DB_PASS)"
[ -n "$DB_USER" ] && [ -n "$DB_NAME" ] || fail "DB_USER or DB_NAME missing from .env"

# ---- 1. The database ---------------------------------------------------
# --single-transaction: a consistent snapshot of InnoDB tables without
# locking the app out while it runs. The host named in .env first; when the
# shell cannot reach it (a public IP from inside CageFS), the local socket.
DUMP=(mysqldump --single-transaction --quick --routines --triggers --events --hex-blob --default-character-set=utf8mb4)
if mysql -h "${DB_HOST:-localhost}" ${DB_PORT:+-P "$DB_PORT"} -u "$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    DUMP+=(-h "${DB_HOST:-localhost}" ${DB_PORT:+-P "$DB_PORT"})
else
    DUMP+=(--protocol=socket)
fi
if ! "${DUMP[@]}" -u "$DB_USER" "$DB_NAME" | gzip -6 > "$DEST/db.sql.gz"; then
    fail "mysqldump failed"
fi
gzip -t "$DEST/db.sql.gz" || fail "the database dump does not decompress"
unset MYSQL_PWD
DB_SIZE="$(du -h "$DEST/db.sql.gz" | cut -f1)"
TABLES="$(gzip -dc "$DEST/db.sql.gz" | grep -c '^CREATE TABLE' || true)"
[ "${TABLES:-0}" -gt 10 ] || fail "the dump holds only ${TABLES:-0} tables"

# ---- 2. The files ------------------------------------------------------
# What a deploy never restores: uploads and the private files. Paths that
# do not exist are skipped quietly. The docroot's own code comes from git.
MOMO_DIR="$(envval MOMO_RECEIPT_DIR)"
FILES=()
for d in "$DOCROOT/uploads" "$DOCROOT/storage/private" "$DOCROOT/storage/momo_receipts" "$MOMO_DIR"; do
    [ -n "$d" ] && [ -d "$d" ] && FILES+=("$d")
done
if [ ${#FILES[@]} -gt 0 ]; then
    tar czf "$DEST/files.tar.gz" --exclude='*/uploads/cache/*' --absolute-names "${FILES[@]}" 2>/dev/null \
        || fail "tar of the uploaded files failed"
    FILES_SIZE="$(du -h "$DEST/files.tar.gz" | cut -f1)"
else
    FILES_SIZE="none"
fi

# ---- 3. The settings ---------------------------------------------------
cp "$DOCROOT/.env" "$DEST/env" && chmod 600 "$DEST/env"
[ -f "$DOCROOT/storage/release" ] && cp "$DOCROOT/storage/release" "$DEST/release"

# ---- 4. Keep a fortnight locally ---------------------------------------
find "$BACKUPS" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +"$KEEP_DAYS" -exec rm -rf {} + 2>/dev/null

# ---- 5. Off the server, when rclone is set up --------------------------
OFFSITE="no rclone remote; local copy only"
if command -v rclone >/dev/null 2>&1 && rclone listremotes 2>/dev/null | grep -qx "$REMOTE:"; then
    if rclone copy "$DEST" "$REMOTE:nfelia/$STAMP" --quiet 2>>"$LOG"; then
        OFFSITE="copied to $REMOTE:nfelia/$STAMP"
        rclone delete "$REMOTE:nfelia" --min-age "${OFFSITE_KEEP_DAYS}d" --quiet 2>>"$LOG" || true
        rclone rmdirs "$REMOTE:nfelia" --leave-root --quiet 2>>"$LOG" || true
    else
        OFFSITE="OFF-SITE COPY FAILED (see above)"
    fi
fi

log "OK $STAMP db=$DB_SIZE ($TABLES tables) files=$FILES_SIZE offsite: $OFFSITE"
