set -euo pipefail
# The 2026-10-17 outage: config/database.php pinned
# utf8mb4_0900_ai_ci (MySQL 8 only) in MYSQL_ATTR_INIT_COMMAND, so
# every connection threw 1273 on MariaDB and the whole app returned
# "Database connection failed." Never pin a versioned collation.
# Comment-only lines are skipped: the note in config/database.php
# explaining why there is no COLLATE names the collation itself.
hits=$(grep -rn 'utf8mb4_0900' --include='*.php' \
         --exclude-dir=vendor --exclude-dir=lib . \
       | grep -vE '^[^:]+:[0-9]+:[[:space:]]*(//|#|/?\*)' || true)
if [ -n "$hits" ]; then
  echo "$hits"
  echo "::error::utf8mb4_0900_* is MySQL-8 only and does not exist on MariaDB. Remove the pinned collation."
  exit 1
fi
echo "OK: no version-pinned collation in the tree."
