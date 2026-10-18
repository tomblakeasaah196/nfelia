#!/usr/bin/env bash
# Gets an empty database ready for the notification tests: the schema, every
# migration, and the doc-number procedure (db/schema.sql leaves procedures
# out). Reads the database settings from the checkout's .env. Safe to run
# again: the schema is loaded only into an empty database.
#   bash tests/notifications/setup.sh
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
env_of() { grep -E "^$1=" "$root/.env" | tail -1 | cut -d= -f2- || true; }
case "$(env_of APP_ENV)" in local|testing) ;; *) echo "Refusing: APP_ENV in .env must be local or testing." >&2; exit 2;; esac

export MYSQL_PWD="$(env_of DB_PASS)"
db=(mysql --default-character-set=utf8mb4 -h "$(env_of DB_HOST)" -P "$(env_of DB_PORT || true)" -u "$(env_of DB_USER)" "$(env_of DB_NAME)")
[ -z "$(env_of DB_PORT)" ] && db=(mysql --default-character-set=utf8mb4 -h "$(env_of DB_HOST)" -u "$(env_of DB_USER)" "$(env_of DB_NAME)")

if [ "$("${db[@]}" -N -e 'SHOW TABLES' | wc -l)" -eq 0 ]; then
    echo "[setup] loading db/schema.sql"
    "${db[@]}" < "$root/db/schema.sql"
fi
php "$root/db/migrate.php"
"${db[@]}" < "$root/tests/notifications/procedures.sql.txt"
echo "[setup] ready"
