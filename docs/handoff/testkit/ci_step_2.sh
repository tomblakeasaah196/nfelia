set -euo pipefail
fail=0
while IFS= read -r -d '' f; do
  if ! out=$(php -l "$f" 2>&1); then
    echo "::error file=$f::${out}"
    fail=1
  fi
done < <(find . -type f -name '*.php' \
          ! -path './vendor/*' ! -path './lib/*' -print0)
exit $fail
