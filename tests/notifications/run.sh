#!/usr/bin/env bash
# Every notification test, against a local or CI copy of Nfelia.
#
# It WIPES the database the checkout's .env points at (fixture.php), so it
# refuses to run unless APP_ENV is local or testing and APP_URL is this
# machine. The app must already be served at APP_URL.
#
#   bash tests/notifications/run.sh              engine, flows, cron, sw
#   bash tests/notifications/run.sh engine flows just these
#   bash tests/notifications/run.sh ui           the browser checks (needs Playwright)
#   bash tests/notifications/run.sh ops/foundation   Nfelia Ops (tests/ops/), one suite per file
#   bash tests/notifications/run.sh orders/core      the orders hub (tests/orders/), one suite per file
#   bash tests/notifications/run.sh orders/late      sales typed in late, kept money, deleting a cancelled order
#   bash tests/notifications/run.sh docs/sign        signatures and verify codes on documents (tests/docs/)
#   bash tests/notifications/run.sh reports/money    money out, cash in and cash out, on every screen (tests/reports/)
#
# Phones and inboxes are stood in for by push_sink.php and smtp_sink.py,
# started here on NF_PUSH_PORT (8098) and NF_SMTP_PORT (2525, which the
# .env's MAIL_HOST/MAIL_PORT must point at).
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../.." && pwd)"
suites=("$@")
[ ${#suites[@]} -eq 0 ] && suites=(engine flows cron sw)

work="$(mktemp -d)"
export NF_PUSH_DIR="$work/push" NF_MAIL_DIR="$work/mail"
export NF_PUSH_URL="http://127.0.0.1:${NF_PUSH_PORT:-8098}"
mkdir -p "$NF_PUSH_DIR" "$NF_MAIL_DIR"
pids=()
cleanup() { for p in "${pids[@]}"; do kill "$p" 2>/dev/null; done; rm -rf "$work"; }
trap cleanup EXIT

port_free() { ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }
wait_port() { for _ in $(seq 50); do (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null && return 0; sleep 0.1; done; return 1; }

needs_sinks=0
for s in "${suites[@]}"; do [[ "$s" != sw ]] && needs_sinks=1; done
if [ $needs_sinks -eq 1 ]; then
    for port in "${NF_PUSH_PORT:-8098}" "${NF_SMTP_PORT:-2525}"; do
        port_free "$port" || { echo "Port $port is in use: stop whatever is on it (an old sink?) and run again." >&2; exit 2; }
    done
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj /CN=127.0.0.1 \
        -keyout "$work/sink.key" -out "$work/sink.crt" >/dev/null 2>&1
    python3 "$here/smtp_sink.py" "$NF_MAIL_DIR" "$work/sink.crt" "$work/sink.key" "${NF_SMTP_PORT:-2525}" >"$work/smtp.log" 2>&1 &
    pids+=($!)
    PUSH_DIR="$NF_PUSH_DIR" php -S "127.0.0.1:${NF_PUSH_PORT:-8098}" "$here/push_sink.php" >"$work/push.log" 2>&1 &
    pids+=($!)
    wait_port "${NF_SMTP_PORT:-2525}" && wait_port "${NF_PUSH_PORT:-8098}" || { echo "The stand-in servers did not start." >&2; exit 2; }
fi

failed=()
for s in "${suites[@]}"; do
    echo "════════ $s ════════"
    case "$s" in
        engine|flows|cron) php "$here/${s}_test.php" ;;
        sw)  node "$here/sw_test.js" ;;
        ui)  node "$here/ui_test.js" ;;
        ops/ui) node "$root/tests/ops/ui_test.js" ;;
        ops/*) php "$root/tests/${s}_test.php" ;;
        orders/*) php "$root/tests/${s}_test.php" ;;
        docs/*) php "$root/tests/${s}_test.php" ;;
        auth/*) php "$root/tests/${s}_test.php" ;;
        reports/*) php "$root/tests/${s}_test.php" ;;
        *)   echo "Unknown suite: $s" >&2; false ;;
    esac || failed+=("$s")
done

echo
if [ ${#failed[@]} -eq 0 ]; then
    echo "All notification suites passed: ${suites[*]}"
else
    echo "Failed: ${failed[*]}"
    exit 1
fi
