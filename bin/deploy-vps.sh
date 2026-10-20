#!/usr/bin/env bash
# Deploy Nfelia on its own server: a release directory per commit and an
# atomic switch. Run as the "nfelia" user, by GitHub Actions over SSH (the
# key is restricted to this script, and the commit comes as the SSH
# command) or by hand:
#     bash /var/www/nfelia/current/bin/deploy-vps.sh main
#
#   /var/www/nfelia/repo              bare mirror of the repository
#   /var/www/nfelia/releases/<sha>    one checkout per deploy (last 5 kept)
#   /var/www/nfelia/shared            .env, uploads/, storage/ (never inside a release)
#   /var/www/nfelia/current           symlink to the release in use
#
# Rollback: ln -sfn /var/www/nfelia/releases/<older sha> /var/www/nfelia/current && sudo systemctl reload php8.3-fpm
set -euo pipefail
BASE=/var/www/nfelia
REF="${SSH_ORIGINAL_COMMAND:-${1:-main}}"
case "$REF" in *[!A-Za-z0-9_./-]*|"") echo "[deploy] refusing ref '$REF'" >&2; exit 2;; esac

exec 9>"$BASE/.deploy.lock"
flock -n 9 || { echo "[deploy] another deploy is running" >&2; exit 75; }

cd "$BASE/repo"
git fetch --quiet origin '+refs/heads/*:refs/heads/*'
SHA="$(git rev-parse --verify "${REF}^{commit}")"
REL="$BASE/releases/$SHA"
echo "[deploy] $(date -Is) $REF -> $SHA"

if [ ! -d "$REL" ]; then
    mkdir -p "$REL"
    git archive "$SHA" | tar -x -C "$REL"
    # runtime data lives in shared/: link it in, never copy it
    rm -rf "$REL/uploads" "$REL/storage"
    ln -s "$BASE/shared/uploads" "$REL/uploads"
    ln -s "$BASE/shared/storage" "$REL/storage"
    ln -s "$BASE/shared/.env" "$REL/.env"
    cp "$REL/bin/uploads.htaccess" "$BASE/shared/uploads/.htaccess" 2>/dev/null || true   # harmless on nginx; kept for parity
    ( cd "$REL" && composer install --no-dev --optimize-autoloader --no-interaction --quiet )
    chmod -R u=rwX,go=rX "$REL"
fi

# migrations run against the shared .env before the switch; a failure stops here
php "$REL/db/migrate.php" --env="$BASE/shared/.env"

git -C "$BASE/repo" log -1 --pretty='%h %cI %s' "$SHA" > "$BASE/shared/storage/release"
ln -sfn "$REL" "$BASE/current.new" && mv -Tf "$BASE/current.new" "$BASE/current"
sudo -n systemctl reload php8.3-fpm 2>/dev/null || echo "[deploy] note: could not reload php-fpm (add 'nfelia ALL=NOPASSWD: /bin/systemctl reload php8.3-fpm' to sudoers); OPcache keeps the old code until it is reloaded"

# errors the new commits say they fixed (Ops → Errors)
php "$REL/bin/errors_fixed.php" --env="$BASE/shared/.env" --repo="$BASE/repo" --from="$(cat "$BASE/.last_deployed" 2>/dev/null || echo "$SHA")" --to="$SHA" || true
echo "$SHA" > "$BASE/.last_deployed"

# keep the last five releases
ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf
echo "[deploy] OK $SHA"
