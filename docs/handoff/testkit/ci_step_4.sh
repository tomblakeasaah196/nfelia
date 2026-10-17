set -euo pipefail
fail=0

if git ls-files | grep -qxE '\.env'; then
  echo "::error::.env is tracked in git"
  fail=1
fi

# Collect first: with -q on the first grep nothing reaches the
# second, so the check could never fire.
dumps=$(git ls-files | grep -iE '\.(sql|sql\.gz|dump)$' \
        | grep -vE '^db/migrations/|^db/schema\.sql$' || true)
if [ -n "$dumps" ]; then
  echo "$dumps"
  echo "::error::a database dump is tracked in git"
  fail=1
fi

# db/schema.sql is exempt above only while it stays structure-only:
# no rows, and no DROP TABLE, so running it against the live
# database by mistake fails instead of wiping it.
if [ -f db/schema.sql ] && grep -nE '^[[:space:]]*(INSERT|REPLACE|LOAD[[:space:]]+DATA|DROP[[:space:]]+TABLE)' db/schema.sql; then
  echo "::error file=db/schema.sql::db/schema.sql must be structure only: no INSERT, REPLACE, LOAD DATA or DROP TABLE. See the header of the file."
  fail=1
fi

# Live Paystack keys must never be committed. The .env.example
# placeholders are pk_live_xxxx / sk_live_xxxx, so require real
# key material (hex-ish, 20+ chars) to trigger.
if grep -rnE '\b[ps]k_live_[0-9a-f]{20,}' \
     --exclude-dir=vendor --exclude-dir=lib --exclude-dir=.git \
     --exclude='.env.example' . ; then
  echo "::error::a live Paystack key appears in the tree"
  fail=1
fi

# Credentials must come from .env, never literal PHP.
if grep -rnE "(DB_PASS|DB_USER|MAIL_PASSWORD)\s*=>?\s*['\"][A-Za-z0-9@_.!-]{6,}" \
     --include='*.php' --exclude-dir=vendor --exclude-dir=lib \
     --exclude='.env.example' . ; then
  echo "::error::hardcoded credential found in PHP"
  fail=1
fi

exit $fail
