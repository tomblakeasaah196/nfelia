set -euo pipefail

# bin/deploy.sh copies the repo into the PUBLIC docroot with
#   tar cf - --exclude-from=.deployignore -C "$REPO" .
# Same invocation here, to a temp archive we can list. tar silently
# ignores patterns that start or end with "/", so one rsync-style
# entry puts .git, docs/ or a database dump on the public web with
# no error anywhere. On 2026-10-17 a 252KB dump of the production
# database was in fact being served at /smartqaq_nfelia_core.sql.
tarball=$(mktemp); members=$(mktemp)
trap 'rm -f "$tarball" "$members"' EXIT
tar cf "$tarball" --exclude-from=.deployignore -C . .
tar tf "$tarball" > "$members"

fail=0
for path in .git .github .gitignore .deployignore .cpanel.yml \
            .env .user.ini php.ini docs bin db README.md; do
  if grep -qE "^\./${path//./\\.}(/|\$)" "$members"; then
    echo "::error file=.deployignore::'${path}' would be copied into the PUBLIC docroot — its .deployignore entry does not match under tar (patterns must not start or end with '/'; a root-only entry looks like './${path}')"
    fail=1
  fi
done

if grep -qE '\.sql$' "$members"; then
  echo "::error file=.deployignore::a .sql file would be copied into the public docroot"
  fail=1
fi

# And it must still be a real deploy: a pattern excluding everything
# would otherwise sail through the checks above.
for path in index.php config/database.php webhook/deploy.php \
            lib/PHPMailer/src/PHPMailer.php; do
  if ! grep -qxF "./${path}" "$members"; then
    echo "::error file=.deployignore::'${path}' would NOT be deployed — a .deployignore pattern is too broad"
    fail=1
  fi
done

if [ "$fail" -eq 0 ]; then
  echo "OK: $(wc -l < "$members") members would deploy; none of the excluded paths among them."
fi
exit $fail
